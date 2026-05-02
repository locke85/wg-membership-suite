<?php

namespace wg\membership\Checkout;

if (!defined('ABSPATH')) {
    exit;
}

class Order_Context {
    public static function get_transient_key($user_id, $token) {
        return 'wg_order_ctx_' . absint($user_id) . '_' . sanitize_key($token);
    }

    public static function store($user_id, $angebot_id, array $data = []) {
        $token = wp_generate_password(20, false, false);
        $context = [
            'angebot_id' => absint($angebot_id),
            'user_id' => absint($user_id),
            'erweiterung_id' => isset($data['erweiterung_id']) ? absint($data['erweiterung_id']) : 0,
            'customer_data' => isset($data['customer_data']) ? Order_Finalizer::extract_checkout_customer_data((array) $data['customer_data']) : [],
            'created_at' => time(),
        ];

        set_transient(self::get_transient_key($user_id, $token), $context, HOUR_IN_SECONDS);
        if ($user_id) {
            update_user_meta($user_id, 'wg_last_checkout_token', $token);
        }

        return $token;
    }

    public static function get($user_id, $token) {
        $token = sanitize_key((string) $token);
        if ($token === '') {
            return null;
        }

        $context = get_transient(self::get_transient_key($user_id, $token));
        if (!is_array($context) && absint($user_id) > 0) {
            $context = get_transient(self::get_transient_key(0, $token));
        }

        return is_array($context) ? $context : null;
    }

    public static function delete($user_id, $token) {
        $token = sanitize_key((string) $token);
        if ($token !== '') {
            delete_transient(self::get_transient_key($user_id, $token));
            if (absint($user_id) > 0) {
                delete_transient(self::get_transient_key(0, $token));
            }
        }

        if ($user_id) {
            delete_user_meta($user_id, 'wg_last_checkout_token');
        }
    }

    public static function set_last_offer_for_user($user_id, $angebot_id, $erweiterung_id = 0) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        update_user_meta($user_id, 'wg_last_checkout_angebot_id', absint($angebot_id));
        if (absint($erweiterung_id) > 0) {
            update_user_meta($user_id, 'wg_last_checkout_erweiterung_id', absint($erweiterung_id));
        } else {
            delete_user_meta($user_id, 'wg_last_checkout_erweiterung_id');
        }
    }

    public static function get_last_offer_for_user($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return ['angebot_id' => 0, 'erweiterung_id' => 0];
        }

        return [
            'angebot_id' => absint(get_user_meta($user_id, 'wg_last_checkout_angebot_id', true)),
            'erweiterung_id' => absint(get_user_meta($user_id, 'wg_last_checkout_erweiterung_id', true)),
        ];
    }

    public static function capture_checkout_offer_from_request() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $angebot_id = 0;
        $erweiterung_id = 0;

        foreach ([filter_input(INPUT_POST, 'wg_angebote_abo_id'), filter_input(INPUT_POST, 'angebot'), filter_input(INPUT_GET, 'angebot')] as $candidate) {
            $candidate = absint($candidate);
            if ($candidate > 0) {
                $angebot_id = $candidate;
                break;
            }
        }

        foreach ([filter_input(INPUT_POST, 'wg_angebote_erweiterung_id'), filter_input(INPUT_POST, 'erweiterung'), filter_input(INPUT_GET, 'erweiterung')] as $candidate) {
            $candidate = absint($candidate);
            if ($candidate > 0) {
                $erweiterung_id = $candidate;
                break;
            }
        }

        if ($angebot_id > 0 && Pricing::get_valid_angebot_post($angebot_id)) {
            self::set_last_offer_for_user($user_id, $angebot_id, $erweiterung_id);
        }
    }
}
