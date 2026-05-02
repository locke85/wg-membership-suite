<?php

namespace wg\membership\Frontend;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class Spickzettel {
    public static function register() {
        add_action('rest_api_init', [static::class, 'register_rest_routes']);
    }

    public static function register_rest_routes() {
        register_rest_route('custom/v1', '/spickzettel-url', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [static::class, 'get_spickzettel_url'],
            'permission_callback' => [static::class, 'can_access_spickzettel_url'],
        ]);
    }

    public static function can_access_spickzettel_url(WP_REST_Request $request) {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'not_logged_in',
                __('Nicht eingeloggt', 'wg-membership-suite'),
                ['status' => 401]
            );
        }

        if (!static::has_valid_rest_nonce($request)) {
            return new WP_Error(
                'invalid_rest_nonce',
                __('Ungültige oder fehlende REST-Nonce.', 'wg-membership-suite'),
                ['status' => 403]
            );
        }

        return true;
    }

    public static function get_spickzettel_url() {
        $user_id = get_current_user_id();

        if ($user_id <= 0) {
            return new WP_Error(
                'not_logged_in',
                __('Nicht eingeloggt', 'wg-membership-suite'),
                ['status' => 401]
            );
        }

        $url = static::get_user_spickzettel_url($user_id);

        if ($url === '') {
            return new WP_Error(
                'no_spickzettel_url',
                __('Kein Spickzettel-Link im Benutzerprofil gefunden.', 'wg-membership-suite'),
                ['status' => 404]
            );
        }

        return rest_ensure_response([
            'user_id' => $user_id,
            'url' => $url,
        ]);
    }

    public static function get_user_spickzettel_url($user_id = 0) {
        $user_id = $user_id ? absint($user_id) : get_current_user_id();

        if ($user_id <= 0) {
            return '';
        }

        return esc_url_raw(trim((string) get_user_meta($user_id, 'spickzettel_url', true)));
    }

    protected static function has_valid_rest_nonce(WP_REST_Request $request) {
        $nonce = $request->get_header('X-WP-Nonce');

        if (!is_string($nonce) || $nonce === '') {
            $nonce = $request->get_param('_wpnonce');
        }

        if (!is_string($nonce) || $nonce === '') {
            return false;
        }

        return (bool) wp_verify_nonce($nonce, 'wp_rest');
    }
}
