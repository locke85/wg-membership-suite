<?php

namespace wg\membership\Admin;

use wg\membership\Checkout\Account_Resolver;
use wg\membership\Checkout\Order_Finalizer;

if (!defined('ABSPATH')) {
    exit;
}

class Bestellung_Admin {
    public static function register() {
        add_action('admin_post_wg_order_resolve', [static::class, 'handle_admin_order_resolution']);
    }

    public static function handle_admin_order_resolution() {
        if (!is_admin() || !current_user_can('edit_posts')) {
            wp_die(__('Keine Berechtigung.', 'wg-membership-suite'));
        }

        $order_id = isset($_REQUEST['order_id']) ? absint($_REQUEST['order_id']) : 0;
        $resolution = isset($_REQUEST['resolution']) ? sanitize_key((string) $_REQUEST['resolution']) : '';
        $target_user_id = isset($_REQUEST['user_id']) ? absint($_REQUEST['user_id']) : 0;

        if ($order_id <= 0 || !in_array($resolution, ['link_existing', 'create_new'], true)) {
            wp_die(__('Ungültige Anfrage.', 'wg-membership-suite'));
        }

        check_admin_referer('wg_order_resolve_' . $order_id . '_' . $resolution . '_' . $target_user_id);

        $customer_data = [];
        foreach (Account_Resolver::get_checkout_customer_field_keys() as $field_key) {
            $customer_data[$field_key] = (string) get_post_meta($order_id, 'customer_' . $field_key, true);
        }

        if ($resolution === 'link_existing') {
            Order_Finalizer::add_bestellung_comment($order_id, 'Admin-Freigabe: bestehendes Konto #' . $target_user_id . ' wurde ausgewählt.');
            Order_Finalizer::finalize_bestellung_account_flow($order_id, $customer_data, ['action' => 'link_existing', 'matched_user_id' => $target_user_id, 'candidates' => [], 'reason' => 'admin_link_existing']);
        } else {
            Order_Finalizer::add_bestellung_comment($order_id, 'Admin-Freigabe: neuer Account soll angelegt werden.');
            Order_Finalizer::finalize_bestellung_account_flow($order_id, $customer_data, ['action' => 'create_new', 'matched_user_id' => 0, 'candidates' => [], 'reason' => 'admin_create_new']);
        }

        wp_safe_redirect(Order_Finalizer::get_bestellung_admin_edit_link($order_id));
        exit;
    }
}
