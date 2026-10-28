<?php

define('ABSPATH', __DIR__ . '/');

function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function wp_unslash($value) { return $value; }
function __($value) { return $value; }

require_once dirname(__DIR__) . '/includes/Admin/Historical_Orders_Admin.php';

use wg\membership\Admin\Historical_Orders_Admin;

function historical_orders_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$filters_method = new ReflectionMethod(Historical_Orders_Admin::class, 'filters_from_request');
$filters = $filters_method->invoke(null, [
    'order' => ' INV-42 ',
    'customer' => '<b>Erika</b>',
    'product' => 'Premium Inhalte',
    'year' => '2024',
    'status' => 'Complete!',
    'from' => '2024-01-01',
    'to' => 'not-a-date',
]);
historical_orders_assert($filters['order'] === 'INV-42', 'order filter is normalized');
historical_orders_assert($filters['customer'] === 'Erika', 'customer filter strips markup');
historical_orders_assert($filters['year'] === '2024', 'valid year is retained');
historical_orders_assert($filters['status'] === 'complete', 'status is a safe key');
historical_orders_assert($filters['from'] === '2024-01-01' && $filters['to'] === '', 'invalid date is rejected');

$display_method = new ReflectionMethod(Historical_Orders_Admin::class, 'display_values');
$display = $display_method->invoke(null, [
    'display_name' => '',
    'user_email' => '',
    'snapshot_json' => json_encode([
        'member' => ['display_name' => 'Erika Muster', 'email' => 'erika@example.test'],
        'membership' => ['title' => 'Erweiterung - Premium Inhalte'],
    ]),
]);
historical_orders_assert($display['customer'] === 'Erika Muster', 'archived customer fallback is rendered');
historical_orders_assert($display['product'] === 'Erweiterung - Premium Inhalte', 'historical product snapshot is rendered');

$csv_method = new ReflectionMethod(Historical_Orders_Admin::class, 'csv_cell');
historical_orders_assert($csv_method->invoke(null, '=1+1') === "'=1+1", 'CSV formulas are neutralized');
historical_orders_assert($csv_method->invoke(null, 'INV-42') === 'INV-42', 'normal CSV values stay unchanged');

echo "historical orders harness: OK\n";
