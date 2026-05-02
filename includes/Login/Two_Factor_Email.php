<?php

namespace wg\membership\Login;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User;
use wg\membership\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Two_Factor_Email {
    public static function register() {
        add_action('init', [static::class, 'register_secure_login_rewrite']);
        add_filter('login_url', [static::class, 'filter_login_url'], 10, 3);
        add_filter('site_url', [static::class, 'filter_site_login_url'], 10, 4);
        add_action('init', [static::class, 'redirect_wp_login'], 1);
        add_action('wp_login', [static::class, 'handle_2fa_on_login'], 10, 2);
        add_action('show_user_profile', [static::class, 'render_user_profile_2fa_field']);
        add_action('edit_user_profile', [static::class, 'render_user_profile_2fa_field']);
        add_action('personal_options_update', [static::class, 'save_user_profile_2fa_field']);
        add_action('edit_user_profile_update', [static::class, 'save_user_profile_2fa_field']);
        add_action('admin_init', [static::class, 'block_admin_without_2fa'], 0);
        add_filter('login_redirect', [static::class, 'force_frontend_after_login'], 20, 3);
        add_action('wp_logout', [static::class, 'cleanup_2fa_on_logout']);
        add_action('rest_api_init', [static::class, 'register_2fa_rest_routes']);
        add_action('wp_enqueue_scripts', [static::class, 'enqueue_2fa_assets']);
    }

    public static function get_safe_fallback_redirect() {
        return admin_url();
    }

    public static function resolve_requested_redirect($raw_redirect = '') {
        $raw_redirect = $raw_redirect !== '' ? $raw_redirect : (isset($_REQUEST['redirect_to']) ? wp_unslash($_REQUEST['redirect_to']) : '');
        if ($raw_redirect === '') {
            return admin_url();
        }

        return wp_validate_redirect((string) $raw_redirect, admin_url());
    }

    public static function register_secure_login_rewrite() {
        if (!Config::is_two_factor_enabled() || Config::get_login_page_id() > 0) {
            return;
        }
        add_rewrite_rule('^' . Config::get_login_slug() . '/?$', 'wp-login.php', 'top');
    }

    public static function filter_login_url($login_url, $redirect, $force_reauth) {
        if (!Config::is_two_factor_enabled()) {
            return $login_url;
        }
        $url = Config::get_login_url();
        if (!empty($redirect)) {
            $url = add_query_arg('redirect_to', rawurlencode(wp_validate_redirect($redirect, static::get_safe_fallback_redirect())), $url);
        }
        if ($force_reauth) {
            $url = add_query_arg('reauth', '1', $url);
        }
        return $url;
    }

    public static function filter_site_login_url($url, $path, $scheme) {
        if (!Config::is_two_factor_enabled() || strpos($url, 'wp-login.php') === false) {
            return $url;
        }
        return Config::get_login_url();
    }

    public static function redirect_wp_login() {
        if (!Config::is_two_factor_enabled() || wp_doing_ajax() || (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
            return;
        }
        if (strpos(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), 'wp-login.php') !== false) {
            wp_safe_redirect(Config::get_login_url());
            exit;
        }
    }

    public static function user_has_2fa_enabled($user_id = 0) {
        if (!Config::is_two_factor_enabled()) {
            return false;
        }
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        return $user_id > 0 ? (bool) get_user_meta($user_id, Config::TWO_FA_META_KEY, true) : false;
    }

    public static function get_2fa_pending_sessions($user_id = 0) {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        $pending = $user_id > 0 ? get_user_meta($user_id, Config::TWO_FA_PENDING_META_KEY, true) : [];
        return is_array($pending) ? $pending : [];
    }

    public static function store_2fa_pending_sessions($pending, $user_id = 0) {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0) {
            return;
        }
        if (empty($pending)) {
            delete_user_meta($user_id, Config::TWO_FA_PENDING_META_KEY);
            return;
        }
        update_user_meta($user_id, Config::TWO_FA_PENDING_META_KEY, $pending);
    }

    public static function get_session_token() {
        return function_exists('wp_get_session_token') ? (string) wp_get_session_token() : '';
    }

    public static function session_requires_2fa($user_id = 0) {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        if (!$user_id || !static::user_has_2fa_enabled($user_id)) {
            return false;
        }
        $token = static::get_session_token();
        $pending = static::get_2fa_pending_sessions($user_id);
        if (!$token || !isset($pending[$token])) {
            return false;
        }
        $expires = isset($pending[$token]['expires']) ? (int) $pending[$token]['expires'] : 0;
        if ($expires && $expires < time()) {
            unset($pending[$token]);
            static::store_2fa_pending_sessions($pending, $user_id);
            return false;
        }
        return true;
    }

    public static function send_2fa_email(WP_User $user, $code) {
        $subject = sprintf(__('Ihr Sicherheitscode für %s', 'wg-membership-suite'), wp_parse_url(home_url(), PHP_URL_HOST));
        $message = sprintf(__("Hallo %s,\n\nIhr Sicherheitscode lautet: %s\nDer Code ist 5 Minuten gültig.", 'wg-membership-suite'), $user->display_name ?: $user->user_login, $code);
        wp_mail($user->user_email, $subject, $message);
    }

    public static function issue_2fa_code(WP_User $user, $redirect_to = '') {
        $token = static::get_session_token();
        if (!$token) {
            return;
        }

        $redirect_to = static::resolve_requested_redirect($redirect_to);
        $pending = static::get_2fa_pending_sessions($user->ID);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pending[$token] = [
            'hash' => wp_hash_password($code),
            'expires' => time() + (5 * MINUTE_IN_SECONDS),
            'attempts' => 0,
            'redirect_to' => $redirect_to,
            'resent_at' => time(),
        ];
        static::store_2fa_pending_sessions($pending, $user->ID);
        static::send_2fa_email($user, $code);
        setcookie('wg_2fa_verified', 'pending', 0, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true);
    }

    public static function handle_2fa_on_login($user_login, WP_User $user) {
        if (!static::user_has_2fa_enabled($user->ID)) {
            return;
        }
        static::issue_2fa_code($user, static::resolve_requested_redirect());
    }

    public static function render_user_profile_2fa_field(WP_User $user) {
        $enabled = (bool) get_user_meta($user->ID, Config::TWO_FA_META_KEY, true);
        echo '<h2>' . esc_html__('Zwei-Faktor-Authentifizierung', 'wg-membership-suite') . '</h2><table class="form-table"><tr><th>' . esc_html__('2FA aktivieren', 'wg-membership-suite') . '</th><td><label><input type="checkbox" name="wg_enable_2fa" value="1" ' . checked($enabled, true, false) . '> ' . esc_html__('Sicherheitscode beim Login anfordern', 'wg-membership-suite') . '</label></td></tr></table>';
    }

    public static function save_user_profile_2fa_field($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        if (isset($_POST['wg_enable_2fa'])) {
            update_user_meta($user_id, Config::TWO_FA_META_KEY, 1);
        } else {
            delete_user_meta($user_id, Config::TWO_FA_META_KEY);
        }
    }

    public static function block_admin_without_2fa() {
        if (is_user_logged_in() && is_admin() && !wp_doing_ajax() && static::session_requires_2fa()) {
            wp_safe_redirect(home_url('/'));
            exit;
        }
    }

    public static function force_frontend_after_login($redirect_to, $requested_redirect_to, $user) {
        return (!is_wp_error($user) && static::session_requires_2fa($user->ID)) ? home_url('/') : $redirect_to;
    }

    public static function cleanup_2fa_on_logout() {
        $user_id = get_current_user_id();
        $token = static::get_session_token();
        $pending = static::get_2fa_pending_sessions($user_id);
        if ($user_id && $token && isset($pending[$token])) {
            unset($pending[$token]);
            static::store_2fa_pending_sessions($pending, $user_id);
        }
        setcookie('wg_2fa_verified', '', time() - DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true);
    }

    public static function register_2fa_rest_routes() {
        register_rest_route('wg/v1', '/2fa/verify', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [static::class, 'rest_verify_2fa_code'],
            'permission_callback' => static function () {
                return Config::is_two_factor_enabled() && is_user_logged_in() && static::session_requires_2fa();
            },
        ]);
        register_rest_route('wg/v1', '/2fa/resend', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [static::class, 'rest_resend_2fa_code'],
            'permission_callback' => static function () {
                return Config::is_two_factor_enabled() && is_user_logged_in() && static::user_has_2fa_enabled();
            },
        ]);
    }

    public static function rest_verify_2fa_code(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $token = static::get_session_token();
        if (!$user_id || !$token) {
            return new WP_REST_Response(['message' => __('Ungültige Sitzung. Bitte neu anmelden.', 'wg-membership-suite')], 403);
        }

        $pending = static::get_2fa_pending_sessions($user_id);
        if (!isset($pending[$token])) {
            return new WP_REST_Response(['message' => __('Keine Verifizierung erforderlich.', 'wg-membership-suite')], 200);
        }

        $entry = $pending[$token];
        if (!wp_check_password((string) $request->get_param('code'), $entry['hash'], $user_id)) {
            $entry['attempts'] = isset($entry['attempts']) ? ((int) $entry['attempts'] + 1) : 1;
            $pending[$token] = $entry;
            static::store_2fa_pending_sessions($pending, $user_id);
            return new WP_REST_Response(['message' => __('Der Code ist leider falsch. Bitte erneut versuchen.', 'wg-membership-suite')], 401);
        }

        unset($pending[$token]);
        static::store_2fa_pending_sessions($pending, $user_id);
        setcookie('wg_2fa_verified', 'true', time() + DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true);

        return rest_ensure_response([
            'message' => __('Zwei-Faktor-Authentifizierung erfolgreich.', 'wg-membership-suite'),
            'redirect' => wp_validate_redirect($entry['redirect_to'] ?? static::get_safe_fallback_redirect(), static::get_safe_fallback_redirect()),
        ]);
    }

    public static function rest_resend_2fa_code() {
        $user = get_user_by('id', get_current_user_id());
        if (!($user instanceof WP_User)) {
            return new WP_REST_Response(['message' => __('Benutzer nicht gefunden.', 'wg-membership-suite')], 404);
        }

        $pending = static::get_2fa_pending_sessions($user->ID);
        $token = static::get_session_token();
        $redirect_to = ($token && isset($pending[$token]['redirect_to'])) ? (string) $pending[$token]['redirect_to'] : static::resolve_requested_redirect();
        static::issue_2fa_code($user, $redirect_to);

        return rest_ensure_response(['message' => __('Ein neuer Code wurde versendet.', 'wg-membership-suite')]);
    }

    public static function enqueue_2fa_assets() {
        if (!Config::is_two_factor_enabled() || !is_user_logged_in() || !static::session_requires_2fa()) {
            return;
        }
        wp_enqueue_script('wg-2fa', WG_MEMBERSHIP_SUITE_URL . 'assets/js/wg-2fa.js', [], WG_MEMBERSHIP_SUITE_VERSION, true);
        wp_localize_script('wg-2fa', 'wgTwoFactor', [
            'restUrl' => esc_url_raw(rest_url('wg/v1/2fa/verify')),
            'resendUrl' => esc_url_raw(rest_url('wg/v1/2fa/resend')),
            'nonce' => wp_create_nonce('wp_rest'),
            'modalId' => 'wg-2fa-modal',
            'shouldOpen' => true,
            'openDelay' => 100,
            'reloadOnSuccess' => false,
        ]);
    }
}
