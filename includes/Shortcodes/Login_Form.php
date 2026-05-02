<?php

namespace wg\membership\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

class Login_Form {
    public static function register() {
        add_shortcode('wg_login_form', [static::class, 'render']);
    }

    public static function render($atts = []) {
        $atts = shortcode_atts(['redirect' => '/schreibtisch', 'form_id' => 'wg-login-form', 'remember' => 'true'], $atts, 'wg_login_form');
        $redirect = trim((string) $atts['redirect']);
        if ($redirect && strpos($redirect, 'http') !== 0) {
            $redirect = home_url('/' . ltrim($redirect, '/'));
        }
        return wp_login_form(['echo' => false, 'form_id' => sanitize_key($atts['form_id']), 'redirect' => $redirect, 'remember' => filter_var($atts['remember'], FILTER_VALIDATE_BOOLEAN)]);
    }
}
