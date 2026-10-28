<?php

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tests/finance-export-dry-run.php /protected/export-directory\n");
    exit(64);
}

$source_directory = realpath($argv[1]);
if (!$source_directory || !is_dir($source_directory)) {
    fwrite(STDERR, "Export directory is not readable.\n");
    exit(66);
}

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_email($value) { return filter_var($value, FILTER_SANITIZE_EMAIL); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_generate_uuid4() { static $i = 0; return sprintf('00000000-0000-4000-8000-%012d', ++$i); }
function get_current_user_id() { return 0; }
function get_user_by($field, $value) { return false; }

class FinanceExportDryRunWpdb {
    public $prefix = 'wp_';
    public function prepare($query, ...$args) { return $query; }
    public function get_row($query, $format) { return null; }
}
$wpdb = new FinanceExportDryRunWpdb();

require_once dirname(__DIR__) . '/includes/Finance/Schema.php';
require_once dirname(__DIR__) . '/includes/Finance/Archive.php';
require_once dirname(__DIR__) . '/includes/Finance/MemberPress_Importer.php';
require_once dirname(__DIR__) . '/includes/Finance/Historical_Analytics.php';

function read_export_json($directory, $name, $required = true) {
    $file = $directory . DIRECTORY_SEPARATOR . $name . '.json';
    if (!is_file($file)) {
        if (!$required) {
            return [];
        }
        throw new RuntimeException("Missing {$name}.json");
    }
    $value = json_decode(file_get_contents($file), true);
    if (!is_array($value)) {
        throw new RuntimeException("Invalid {$name}.json");
    }
    return $value;
}

try {
    $export = [];
    foreach (['memberships', 'subscriptions', 'transactions', 'members'] as $name) {
        $export[$name] = read_export_json($source_directory, $name);
    }
    $export['documents'] = read_export_json($source_directory, 'documents', false);
    $export['events'] = read_export_json($source_directory, 'events', false);
    $manifest = read_export_json($source_directory, 'manifest', false);
    $result = (new \wg\membership\Finance\MemberPress_Importer(true))->run($export, $manifest);
    $analytics = \wg\membership\Finance\Historical_Analytics::from_memberpress_export($export, $manifest['default_currency'] ?? 'XXX');
    $inserted = $result['counts']['inserted'];
    $expected_orders = count($export['transactions']);
    $expected_invoices = count(array_filter($export['transactions'], function ($row) {
        return trim((string) ($row['invoice_number'] ?? '')) !== '';
    }));
    $expected_refunds = count(array_filter($export['transactions'], function ($row) {
        return ($row['status'] ?? '') === 'refunded';
    }));
    $checks = [
        'customers' => ($inserted['customer'] ?? 0) === count($export['members']),
        'orders' => ($inserted['order'] ?? 0) === $expected_orders,
        'money_objects' => (($inserted['payment'] ?? 0) + ($inserted['refund'] ?? 0)) === $expected_orders,
        'refunds' => ($inserted['refund'] ?? 0) === $expected_refunds,
        'invoices' => ($inserted['invoice'] ?? 0) === $expected_invoices,
        'subscriptions' => ($inserted['subscription'] ?? 0) === count($export['subscriptions']),
        'products' => (($inserted['membership_product'] ?? 0) + ($inserted['extension'] ?? 0)) === count($export['memberships']),
        'no_writes' => $result['dry_run'] === true && $result['batch_id'] === 0,
    ];
    $report = [
        'dry_run' => true,
        'source_counts' => $result['counts']['input'],
        'projected_counts' => $inserted,
        'review_counts' => $result['counts']['review'],
        'analytics_data_state' => $analytics['data_state'],
        'analytics_summary' => $analytics['summary'],
        'annual' => $analytics['annual'],
        'bundle_candidate_count' => count($analytics['bundle_candidates']),
        'top_products' => array_map(function ($product) {
            return [
                'source_product_id' => $product['source_product_id'],
                'title' => $product['title'],
                'kind' => $product['kind'],
                'completed_order_count' => $product['completed_order_count'],
                'unique_customers' => $product['unique_customers'],
            ];
        }, array_slice($analytics['products'], 0, 10)),
        'checks' => $checks,
        'all_checks_passed' => !in_array(false, $checks, true),
    ];
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit($report['all_checks_passed'] ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
