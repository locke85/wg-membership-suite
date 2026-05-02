<?php

namespace wg\membership\Shortcodes;

use wg\membership\Checkout\Order_Context;
use wg\membership\Checkout\Step_3_Order;
use wg\membership\Helpers\User_Data;

if (!defined('ABSPATH')) {
    exit;
}

class Order_Overview {
    public static function register() {
        add_shortcode('bestelluebersicht', [static::class, 'render']);
    }

    public static function render() {
        $uid = get_current_user_id();
        $token = isset($_GET['wg_ot']) ? sanitize_key((string) $_GET['wg_ot']) : '';
        [$angebot_id, $erweiterung_id, $context] = Step_3_Order::resolve_offer_ids($uid, $token);
        $prefill = $uid > 0 ? User_Data::get_prefill_user_data($uid) : [];
        if ($context && isset($context['customer_data']) && is_array($context['customer_data'])) {
            $prefill = array_merge($context['customer_data'], $prefill);
        }
        return Step_3_Order::get_summary_box_markup($prefill, $angebot_id, $erweiterung_id);
    }
}
