<?php

define('ABSPATH', __DIR__ . '/');

function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }

$current_order_meta = [
    'user_id' => 17,
    'customer_email' => 'fallback@example.test',
    'netto' => '100.00',
    'brutto' => '119.00',
    'angebot_id' => 31,
    'erweiterung_id' => 32,
    'angebot_typ' => 'paket',
    'order_number' => 'WG-2026-000001',
    'mwst_satz' => '19',
    'status' => 'completed',
    'checkout_token' => 'must-not-be-projected',
];

function get_posts($args) {
    return [(object) [
        'ID' => 301,
        'post_date' => '2026-05-01 10:00:00',
        'post_modified' => '2026-05-01 10:01:00',
        'post_modified_gmt' => '2026-05-01 08:01:00',
    ]];
}
function get_post_meta($post_id, $key, $single) {
    global $current_order_meta;
    return $current_order_meta[$key] ?? '';
}
function get_userdata($user_id) {
    return (object) [
        'user_email' => 'customer@example.test',
        'display_name' => 'Erika Muster',
        'user_registered' => '2026-04-01 09:00:00',
    ];
}
function get_post($post_id) {
    return (object) ['ID' => $post_id, 'post_title' => $post_id === 31 ? 'Neues Paket' : 'Neue Erweiterung'];
}

require_once dirname(__DIR__) . '/includes/Finance/Archive.php';
require_once dirname(__DIR__) . '/includes/Finance/Current_Order_Projection.php';

use wg\membership\Finance\Current_Order_Projection;

function projection_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$objects = Current_Order_Projection::load('2026-01-01');
projection_assert(count($objects) === 1, 'one current order is projected');
$order = $objects[0];
projection_assert($order['object_type'] === 'current_order' && $order['status'] === 'completed', 'checkout order stays a distinct source type');
projection_assert($order['amount_net'] === '100.000000' && $order['amount_tax'] === '19.000000' && $order['amount_gross'] === '119.000000', 'order values are projected reproducibly');
$snapshot = json_decode($order['snapshot_json'], true);
projection_assert(count($snapshot['products']) === 2, 'base product and extension are both projected');
projection_assert(!isset($snapshot['checkout_token']), 'checkout credentials are excluded from analytics');
projection_assert($snapshot['checkout_status_only'] === true, 'the projection does not claim a Finom payment status');

echo "current order projection harness: OK\n";
