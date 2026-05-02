<?php

namespace wg\membership;

if (!defined('ABSPATH')) {
    exit;
}

class Config {
    public const OPTION_KEY = 'wg_membership_suite_settings';
    public const NETWORK_ACTIVATED_OPTION = 'wg_membership_suite_network_activated';
    public const STATUS_HISTORY_KEY = '_wg_status_history';
    public const REVIEW_CONTEXT_KEY = '_wg_review_context';
    public const REVIEW_CANDIDATES_KEY = '_wg_review_candidates';
    public const ORDER_SEQUENCE_OPTION = 'wg_bestellung_sequence_by_year';
    public const LOGIN_SLUG_LEGACY = 'login-secure';
    public const TWO_FA_META_KEY = 'wg_enable_2fa';
    public const TWO_FA_PENDING_META_KEY = 'wg_2fa_pending_sessions';

    public static function defaults() {
        return [
            'cf7_step2_form_id' => 0,
            'cf7_step3_form_id' => 0,
            'login_page_id' => 0,
            'password_reset_page_id' => 0,
            'checkout_page_ids' => [
                'step_1' => 0,
                'step_2' => 0,
                'step_3' => 0,
                'step_4' => 0,
            ],
            'login_page' => self::LOGIN_SLUG_LEGACY,
            'password_reset_page' => self::LOGIN_SLUG_LEGACY,
            'checkout_pages' => [
                'step_1' => '1-produkt-auswaehlen',
                'step_2' => '2-daten-eingeben',
                'step_3' => '3-bestellung-pruefen',
                'step_4' => '4-bestaetigung',
            ],
            'site_enabled' => true,
            'two_factor_enabled' => false,
            'allowed_countries' => ['Deutschland', 'Österreich', 'Schweiz'],
            'allowed_payment_methods' => ['rechnung', 'sepa_ueberweisung'],
            'debug_mode' => false,
        ];
    }

    public static function get_settings() {
        $settings = get_option(self::OPTION_KEY, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        return array_replace_recursive(self::defaults(), $settings);
    }

    public static function get($key, $default = null) {
        $settings = self::get_settings();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function is_debug_enabled() {
        return self::get('debug_mode', false) || (defined('WG_DEBUG') && WG_DEBUG);
    }

    public static function is_site_enabled() {
        return (bool) self::get('site_enabled', true);
    }

    public static function is_two_factor_enabled() {
        return self::get('two_factor_enabled', false) || (defined('WG_2FA_ENABLED') && WG_2FA_ENABLED);
    }

    public static function get_login_slug() {
        $page = trim((string) self::get('login_page', self::LOGIN_SLUG_LEGACY), '/');
        return $page !== '' ? $page : self::LOGIN_SLUG_LEGACY;
    }

    public static function get_login_page_id() {
        return absint(self::get('login_page_id', 0));
    }

    public static function get_password_reset_slug() {
        $page = trim((string) self::get('password_reset_page', self::get_login_slug()), '/');
        return $page !== '' ? $page : self::LOGIN_SLUG_LEGACY;
    }

    public static function get_password_reset_page_id() {
        return absint(self::get('password_reset_page_id', 0));
    }

    public static function get_checkout_page_id($step) {
        $pages = self::get('checkout_page_ids', []);
        return isset($pages[$step]) ? absint($pages[$step]) : 0;
    }

    public static function get_checkout_page_slug($step) {
        $pages = self::get('checkout_pages', []);
        return isset($pages[$step]) ? trim((string) $pages[$step], '/') : '';
    }

    public static function get_page_url_by_id_or_slug($page_id, $fallback_slug, $fallback_default = '') {
        $page_id = absint($page_id);
        if ($page_id > 0) {
            $permalink = get_permalink($page_id);
            if (is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }

        $fallback_slug = trim((string) $fallback_slug, '/');
        if ($fallback_slug !== '') {
            return home_url('/' . $fallback_slug . '/');
        }

        $fallback_default = trim((string) $fallback_default, '/');
        return home_url('/' . $fallback_default . '/');
    }

    public static function get_login_url() {
        return self::get_page_url_by_id_or_slug(self::get_login_page_id(), self::get_login_slug(), self::LOGIN_SLUG_LEGACY);
    }

    public static function get_password_reset_url() {
        return self::get_page_url_by_id_or_slug(self::get_password_reset_page_id(), self::get_password_reset_slug(), self::LOGIN_SLUG_LEGACY);
    }

    public static function get_checkout_page_url($step) {
        return self::get_page_url_by_id_or_slug(self::get_checkout_page_id($step), self::get_checkout_page_slug($step), self::get_checkout_page_slug($step));
    }

    public static function is_checkout_page($step) {
        $page_id = self::get_checkout_page_id($step);
        if ($page_id > 0) {
            return is_page($page_id);
        }

        $slug = self::get_checkout_page_slug($step);
        return $slug !== '' ? is_page($slug) : false;
    }

    public static function get_allowed_countries() {
        $countries = array_values(array_filter(array_map('sanitize_text_field', (array) self::get('allowed_countries', []))));
        return !empty($countries) ? $countries : self::defaults()['allowed_countries'];
    }

    public static function get_allowed_payment_methods() {
        $methods = array_values(array_filter(array_map('sanitize_key', (array) self::get('allowed_payment_methods', []))));
        return !empty($methods) ? $methods : self::defaults()['allowed_payment_methods'];
    }
}
