<?php

namespace wg\membership\Login;

use wg\membership\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Secure_Login {
    public static function register() {
        add_filter('login_redirect', [static::class, 'custom_login_redirect'], 10, 3);
        add_filter('show_admin_bar', [static::class, 'hide_admin_bar_from_non_admins']);
        add_action('init', [static::class, 'redirect_non_admin_users']);
        add_filter('render_block', [static::class, 'render_block_shortcodes'], 20, 2);
    }

    public static function custom_login_redirect($redirect_to, $request, $user) {
        return isset($user->roles) && is_array($user->roles) ? home_url('/schreibtisch') : $redirect_to;
    }

    public static function hide_admin_bar_from_non_admins() {
        return current_user_can('administrator');
    }

    public static function redirect_non_admin_users() {
        if ((is_network_admin() || defined('DOING_AJAX')) || !is_admin() || current_user_can('administrator')) {
            return;
        }
        wp_safe_redirect(home_url('/'));
        exit;
    }

    public static function render_block_shortcodes($content) {
        if (!is_string($content) || $content === '' || strpos($content, '[') === false) {
            return $content;
        }
        if (strpos($content, '[wg_login_form') === false && strpos($content, '[wg_password_reset_form') === false) {
            return $content;
        }
        return do_shortcode($content);
    }
}
