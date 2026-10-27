<?php

namespace wg\membership\Shortcodes;

use wg\membership\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Login_Form {
    public static function register() {
        add_shortcode('wg_login_form', [static::class, 'render']);
        add_action('wp', [static::class, 'configure_login_page'], 101);
        add_action('wp_enqueue_scripts', [static::class, 'enqueue_styles']);
        add_filter('body_class', [static::class, 'add_body_class']);
    }

    /**
     * Apply the GeneratePress "Disable Elements" behaviour to the configured
     * login page without changing settings on other pages in the network.
     */
    public static function configure_login_page() {
        if (!static::is_login_page()) {
            return;
        }

        remove_all_actions('generate_before_header');
        remove_action('generate_before_header', 'generate_top_bar', 5);
        remove_action('generate_header', 'generate_construct_header');
        remove_action('generate_after_header', 'generate_menu_plus_mobile_header', 5);
        remove_action('generate_footer', 'generate_construct_footer_widgets', 5);
        remove_action('generate_footer', 'generate_construct_footer');
        remove_all_actions('generate_footer');

        add_filter('generate_navigation_location', '__return_false', 20);
        add_filter('generate_disable_mobile_header_menu', '__return_true');
        add_filter('generate_show_title', '__return_false');
        add_filter('generate_footer_widgets', '__return_null');
    }

    public static function enqueue_styles() {
        if (!static::is_login_page()) {
            return;
        }

        wp_enqueue_style(
            'wg-membership-login-form',
            WG_MEMBERSHIP_SUITE_URL . 'assets/css/login-form.css',
            [],
            WG_MEMBERSHIP_SUITE_VERSION
        );
    }

    public static function add_body_class($classes) {
        if (static::is_login_page()) {
            $classes[] = 'wg-membership-login-page';
        }

        return $classes;
    }

    private static function is_login_page() {
        $page_id = Config::get_login_page_id();

        if ($page_id > 0) {
            return is_page($page_id);
        }

        $slug = Config::get_login_slug();
        return $slug !== '' && is_page($slug);
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
