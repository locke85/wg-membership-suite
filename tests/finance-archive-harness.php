<?php

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_email($value) { return filter_var($value, FILTER_SANITIZE_EMAIL); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_generate_uuid4() { static $i = 0; $i++; return sprintf('00000000-0000-4000-8000-%012d', $i); }
function get_current_user_id() { return 0; }
function get_user_by($field, $value) { return false; }

class FinanceTestWpdb {
    public $prefix = 'wp_';
    public $latest = [];
    private $prepared_args = [];
    public function prepare($query, ...$args) { $this->prepared_args = $args; return $query; }
    public function get_row($query, $format) {
        $key = implode('|', array_slice($this->prepared_args, 0, 3));
        return $this->latest[$key] ?? null;
    }
}
$wpdb = new FinanceTestWpdb();

require_once dirname(__DIR__) . '/includes/Finance/Schema.php';
require_once dirname(__DIR__) . '/includes/Finance/Archive.php';
require_once dirname(__DIR__) . '/includes/Finance/MemberPress_Importer.php';
require_once dirname(__DIR__) . '/includes/Finance/Historical_Analytics.php';
require_once dirname(__DIR__) . '/includes/Finance/Finom_Historical_Summary.php';
require_once dirname(__DIR__) . '/includes/Finance/Reprint_PDF.php';

use wg\membership\Finance\Archive;
use wg\membership\Finance\MemberPress_Importer;
use wg\membership\Finance\Historical_Analytics;
use wg\membership\Finance\Finom_Historical_Summary;
use wg\membership\Finance\Reprint_PDF;

function assert_true($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

assert_true(
    Archive::hash(['b' => 2, 'a' => 1]) === Archive::hash(['a' => 1, 'b' => 2]),
    'canonical hashes must not depend on object key order'
);

$member = [
    'id' => 7, 'email' => 'customer@example.test', 'first_name' => 'Erika', 'last_name' => 'Muster',
    'address' => ['mepr-address-one' => 'Testweg 1', 'mepr-address-city' => 'Berlin'],
];
$membership = ['id' => 11, 'title' => 'Test-Mitgliedschaft'];
$transaction = [
    'id' => 99, 'status' => 'complete', 'created_at' => '2024-04-05 12:00:00',
    'invoice_number' => 'INV-99', 'amount' => '100.00', 'tax_amount' => '19.00',
    'total' => '119.00', 'currency' => 'EUR', 'member' => $member, 'membership' => $membership,
    'subscription' => ['id' => 23, 'token' => 'must-not-be-imported'],
    'token' => 'must-not-be-imported', 'cc_last4' => '1234', 'cc_exp_month' => '12',
    'cc_exp_year' => '2030', 'response' => ['secret' => 'must-not-be-imported'],
];
$export = [
    'memberships' => [$membership + ['date' => '2024-04-01 00:00:00', 'status' => 'publish']],
    'subscriptions' => [[
        'id' => 23, 'status' => 'active', 'created_at' => '2024-04-05 12:00:00',
        'price' => '100.00', 'tax_amount' => '19.00', 'total' => '119.00', 'currency' => 'EUR',
        'member' => $member, 'membership' => $membership,
    ]],
    'transactions' => [$transaction],
    'members' => [$member],
];
$result = (new MemberPress_Importer(true))->run($export);
assert_true($result['dry_run'] === true && $result['batch_id'] === 0, 'dry-run must not create a batch');
foreach (['customer', 'membership_product', 'subscription', 'order', 'payment', 'invoice'] as $type) {
    assert_true(($result['counts']['inserted'][$type] ?? 0) === 1, "dry-run maps {$type}");
}
$snapshot_method = new ReflectionMethod(MemberPress_Importer::class, 'target_snapshot');
$test_importer = new MemberPress_Importer(true);
$target_snapshot = $snapshot_method->invoke($test_importer, 'invoice', $transaction);
assert_true(!isset($target_snapshot['token'], $target_snapshot['cc_last4'], $target_snapshot['response']), 'target snapshot excludes payment credentials and raw gateway responses');
assert_true(array_keys($target_snapshot['subscription']) === ['id', 'subscr_id'], 'nested subscription is reduced to stable references');

$register_latest = function ($type, $source_id, array $row) use ($snapshot_method, $test_importer, $wpdb) {
    $snapshot = $snapshot_method->invoke($test_importer, $type, $row);
    $snapshot['_source_record_sha256'] = Archive::hash($row);
    $wpdb->latest['memberpress:seo-sharing-system.de|' . $type . '|' . $source_id] = [
        'object_hash' => Archive::hash($snapshot),
        'logical_id' => '10000000-0000-4000-8000-' . str_pad((string) $source_id, 12, '0', STR_PAD_LEFT),
        'version' => 1,
    ];
};
$register_latest('membership_product', 11, $export['memberships'][0]);
$register_latest('customer', 7, $member);
$register_latest('subscription', 23, $export['subscriptions'][0]);
foreach (['order', 'payment', 'invoice'] as $type) {
    $register_latest($type, 99, $transaction);
}
$repeat = (new MemberPress_Importer(true))->run($export);
assert_true(empty($repeat['counts']['inserted']), 'repeat run inserts no duplicate objects');
foreach (['customer', 'membership_product', 'subscription', 'order', 'payment', 'invoice'] as $type) {
    assert_true(($repeat['counts']['unchanged'][$type] ?? 0) === 1, "repeat run recognizes unchanged {$type}");
}

$apply_gate_failed = false;
try {
    (new MemberPress_Importer(false))->run($export, [
        'mapping_approved' => true,
        'database_export_complete' => true,
        'currency_approved' => true,
        'default_currency' => 'EUR',
    ]);
} catch (RuntimeException $error) {
    $apply_gate_failed = strpos($error->getMessage(), 'Eventexport') !== false
        && strpos($error->getMessage(), 'Dokument-/PDF-Inventur') !== false;
}
assert_true($apply_gate_failed, 'apply gate rejects incomplete event export and document inventory before any database write');

$snapshot = Archive::canonical_json($transaction);
$object = [
    'object_type' => 'invoice', 'invoice_number' => 'INV-99', 'occurred_at' => '2024-04-05 12:00:00',
    'amount_net' => '100.000000', 'amount_tax' => '19.000000', 'amount_gross' => '119.000000',
    'currency' => 'EUR', 'status' => 'complete', 'object_hash' => hash('sha256', $snapshot),
    'snapshot_json' => $snapshot,
];
assert_true(Reprint_PDF::is_eligible($object), 'complete historical invoice snapshot is reprint eligible');
$pdf = Reprint_PDF::render($object);
assert_true(strncmp($pdf, '%PDF-1.4', 8) === 0, 'reprint output is a PDF');
assert_true(strpos($pdf, 'RECHNUNGSKOPIE') !== false, 'reprint is visibly marked as a copy');
assert_true(strpos($pdf, 'INV-99') !== false, 'reprint retains the historical invoice number');

$object['currency'] = 'XXX';
assert_true(!Reprint_PDF::is_eligible($object), 'unknown currency blocks reprint');

$customer_key_a = hash('sha256', '7|customer@example.test');
$customer_key_b = hash('sha256', '8|second@example.test');
$analytics_object = function ($type, $source_id, $customer_key, $status, $date, $gross, $currency, array $snapshot) {
    return [
        'object_type' => $type,
        'source_id' => (string) $source_id,
        'customer_ref_hash' => $customer_key,
        'customer_user_id' => 0,
        'status' => $status,
        'occurred_at' => $date,
        'amount_gross' => $gross,
        'currency' => $currency,
        'snapshot_json' => Archive::canonical_json($snapshot),
    ];
};
$analytics_objects = [
    $analytics_object('customer', 7, $customer_key_a, 'historical', '2023-01-01 09:00:00', null, 'XXX', $member + ['registered_at' => '2023-01-01 09:00:00']),
    $analytics_object('customer', 8, $customer_key_b, 'historical', '2023-02-01 09:00:00', null, 'XXX', ['id' => 8, 'email' => 'second@example.test', 'display_name' => 'Zweiter Kunde', 'registered_at' => '2023-02-01 09:00:00']),
    $analytics_object('membership_product', 11, '', 'publish', '2023-01-01 00:00:00', null, 'XXX', ['id' => 11, 'title' => 'Basisprodukt']),
    $analytics_object('extension', 12, '', 'publish', '2023-01-01 00:00:00', null, 'XXX', ['id' => 12, 'title' => 'Erweiterung - Analyse']),
    $analytics_object('subscription', 23, $customer_key_a, 'active', '2025-05-01 00:00:00', '50.00', 'EUR', ['id' => 23, 'status' => 'active']),
    $analytics_object('subscription', 24, $customer_key_b, 'suspended', '2025-06-01 00:00:00', '50.00', 'EUR', ['id' => 24, 'status' => 'suspended']),
    $analytics_object('order', 101, $customer_key_a, 'complete', '2024-01-01 00:00:00', '100.00', 'EUR', ['tax_amount' => '19.00', 'membership' => ['id' => 11, 'title' => 'Basisprodukt'], 'expires_at' => '2024-01-31 00:00:00']),
    $analytics_object('order', 102, $customer_key_a, 'complete', '2024-03-01 00:00:00', '50.00', 'EUR', ['tax_amount' => '8.00', 'membership' => ['id' => 11, 'title' => 'Basisprodukt'], 'expires_at' => '2024-03-31 00:00:00']),
    $analytics_object('order', 103, $customer_key_a, 'complete', '2024-03-01 00:00:00', '20.00', 'EUR', ['tax_amount' => '3.00', 'membership' => ['id' => 12, 'title' => 'Erweiterung - Analyse'], 'expires_at' => '2024-03-31 00:00:00']),
    $analytics_object('order', 104, $customer_key_b, 'pending', '2024-04-01 00:00:00', '80.00', 'EUR', ['membership' => ['id' => 11, 'title' => 'Basisprodukt']]),
    $analytics_object('refund', 105, $customer_key_a, 'refunded', '2024-04-02 00:00:00', '10.00', 'EUR', ['tax_amount' => '1.00', 'membership' => ['id' => 11, 'title' => 'Basisprodukt']]),
    $analytics_object('membership_event', 201, $customer_key_a, 'pause_started', '2024-04-01 00:00:00', null, 'XXX', ['event_type' => 'pause_started', 'subscription' => ['id' => 23]]),
    $analytics_object('membership_event', 202, $customer_key_a, 'pause_ended', '2024-04-11 00:00:00', null, 'XXX', ['event_type' => 'pause_ended', 'subscription' => ['id' => 23]]),
    [
        'object_type' => 'current_order',
        'source_id' => 'wg_bestellung:301',
        'customer_ref_hash' => $customer_key_b,
        'customer_user_id' => 0,
        'status' => 'completed',
        'occurred_at' => '2026-05-01 10:00:00',
        'amount_net' => '100.00',
        'amount_tax' => '19.00',
        'amount_gross' => '119.00',
        'currency' => 'EUR',
        'snapshot_json' => Archive::canonical_json([
            'source' => 'membership_suite',
            'member' => ['id' => 8, 'email' => 'second@example.test', 'display_name' => 'Zweiter Kunde'],
            'products' => [
                ['id' => 'membership-suite:angebot:31', 'title' => 'Neues Basispaket', 'kind' => 'membership_product'],
                ['id' => 'membership-suite:erweiterung:32', 'title' => 'Neue Erweiterung', 'kind' => 'extension'],
            ],
        ]),
    ],
];
$analytics_objects = array_merge($analytics_objects, Finom_Historical_Summary::load());
$analytics = Historical_Analytics::build($analytics_objects);
assert_true($analytics['summary']['registered_customers'] === 2, 'analytics separates registered customers');
assert_true($analytics['summary']['orders_by_status'] === ['complete' => 3, 'pending' => 1], 'analytics keeps source order statuses separate');
$customer_a = array_values(array_filter($analytics['customers'], function ($row) use ($customer_key_a) { return $row['customer_key'] === $customer_key_a; }))[0];
assert_true($customer_a['historical_revenue']['EUR'] === '160.00', 'historical customer revenue subtracts confirmed refunds');
assert_true($customer_a['observed_inactivity_gap_count'] === 1 && $customer_a['observed_inactivity_gap_days'] === 30, 'coverage gaps are reported as observed inactivity');
assert_true($customer_a['explicit_pause_count'] === 1 && $customer_a['explicit_pause_days'] === 10, 'explicit pause events remain distinct');
assert_true($analytics['bundle_candidates'][0]['shared_customers'] === 1, 'extension and base-product overlap is available for bundle analysis');
$annual_2024 = array_values(array_filter($analytics['annual'], function ($row) { return $row['year'] === 2024; }))[0];
assert_true($annual_2024['historical_gross_revenue']['EUR'] === '160.00', 'annual gross revenue subtracts documented refunds');
assert_true($annual_2024['historical_tax_amount']['EUR'] === '29.00', 'annual tax amount subtracts refunded tax');
assert_true($annual_2024['historical_net_revenue']['EUR'] === '131.00', 'annual net revenue is gross less historical tax');
assert_true($annual_2024['new_paying_customer_count'] === 1 && $annual_2024['observed_departure_count'] === 0, 'an active carried subscription prevents a false observed departure');
$annual_2026 = array_values(array_filter($analytics['annual'], function ($row) { return $row['year'] === 2026; }))[0];
assert_true($analytics['definition_version'] === 'membership-analytics-v7', 'combined report uses the v7 definition');
assert_true($analytics['summary']['historical_orders'] === 4 && $analytics['summary']['suite_orders'] === 1 && $analytics['summary']['orders'] === 5, 'summary separates archive and current orders');
assert_true($annual_2026['suite_gross_order_value']['EUR'] === '119.00' && $annual_2026['suite_tax_order_value']['EUR'] === '19.00', 'current order value remains separate from historical revenue');
assert_true($annual_2026['historical_gross_revenue'] === [], 'current order value is not reported as MemberPress revenue');
assert_true($annual_2026['finom_cash_inflow']['EUR'] === '48918.54', '2026 YTD Finom cash inflow remains a separate annual metric');
assert_true($annual_2026['history_value']['EUR'] === '48918.54', 'simplified 2026 history value uses confirmed cashflow without adding suite order projections');
assert_true($annual_2026['finom_coverage_periods'] === ['2026-01-01–2026-08-22'], 'Finom YTD coverage remains visible');
assert_true($annual_2026['new_commercial_customer_count'] === 4, 'commercial acquisitions combine one first suite order with three confirmed Finom-era starts');
assert_true($annual_2026['finom_confirmed_new_customer_count'] === 3, 'Finom-era starts remain separately attributable in the source details');
assert_true($analytics['summary']['finom_confirmed_new_customers_by_month'] === ['2026-02' => 1, '2026-08' => 2], 'confirmed Finom-era starts retain their aggregate month precision');
assert_true($annual_2026['carried_active_subscription_count'] === 1 && $annual_2026['carried_active_customer_count'] === 1, 'active subscriptions started before 2026 are carried into the 2026 retention view');
assert_true($analytics['summary']['carried_active_subscription_count'] === 1 && $analytics['summary']['carried_active_customer_count'] === 1, 'summary exposes carried active subscriptions and unique customers separately');
assert_true($analytics['summary']['finom_cash_inflow_2025']['EUR'] === '45089.80' && $analytics['summary']['finom_cash_inflow_2026']['EUR'] === '48918.54', 'summary exposes confirmed Finom cashflow snapshots by year');
assert_true($analytics['summary']['history_value_2025']['EUR'] === '45089.80' && $analytics['summary']['history_value_2026_ytd']['EUR'] === '48918.54', 'summary exposes unified history values for the simplified overview');
$customer_b = array_values(array_filter($analytics['customers'], function ($row) use ($customer_key_b) { return $row['customer_key'] === $customer_key_b; }))[0];
assert_true($customer_b['suite_order_value']['EUR'] === '119.00' && $customer_b['commercial_lifetime_value']['EUR'] === '119.00', 'commercial LTV combines the separated order-value stream');
$suite_products = array_values(array_filter($analytics['products'], function ($row) { return $row['source'] === 'membership_suite'; }));
assert_true(count($suite_products) === 2 && array_sum(array_column($suite_products, 'suite_completed_order_count')) === 2, 'base product and extension remain separately analyzable');

echo "finance archive harness: OK\n";
