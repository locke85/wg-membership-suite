<?php

namespace wg\membership\Shortcodes;

use wg\membership\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Password_Reset_Form {
    private const ACTION_FIELD = 'wg_password_reset_action';
    private const STATUS_FIELD = 'wg_password_reset_status';

    private static $mail_context = '';

    public static function register() {
        add_shortcode('wg_password_reset_form', [static::class, 'render']);
        add_filter('lostpassword_url', [static::class, 'filter_lostpassword_url'], 10, 2);
        add_filter('retrieve_password_notification_email', [static::class, 'filter_reset_notification_email'], 10, 4);
        add_action('template_redirect', [static::class, 'handle_post']);
        add_action('wp_mail_failed', [static::class, 'handle_mail_failure']);
    }

    public static function handle_post() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST[self::ACTION_FIELD])) {
            return;
        }

        $action = sanitize_key(static::request_string($_POST, self::ACTION_FIELD));
        if (!in_array($action, ['request', 'reset'], true)) {
            return;
        }

        $url = add_query_arg(static::process_post($action, $_POST), Config::get_password_reset_url());
        wp_safe_redirect($url, 303);
        exit;
    }

    /**
     * Return query arguments for the Post/Redirect/Get redirect.
     *
     * This is public so the WordPress compatibility harness can exercise the
     * state transitions without terminating the PHP process on redirect.
     */
    public static function process_post($action, $post) {
        if ($action === 'request') {
            $nonce = static::request_string($post, 'wg_lostpassword_nonce');
            if (!wp_verify_nonce($nonce, 'wg_lostpassword')) {
                return [self::STATUS_FIELD => 'invalid_nonce'];
            }

            $_POST['user_login'] = sanitize_text_field(static::request_string($post, 'user_login'));
            static::$mail_context = 'password_reset_request';
            try {
                $result = retrieve_password();
            } finally {
                static::$mail_context = '';
            }

            if (is_wp_error($result) && in_array('retrieve_password_email_failure', $result->get_error_codes(), true)) {
                return [self::STATUS_FIELD => 'mail_failed'];
            }

            // Account lookup failures deliberately use the same response as success.
            return [self::STATUS_FIELD => 'request_sent'];
        }

        $login = sanitize_text_field(static::request_string($post, 'rp_login'));
        $key = sanitize_text_field(static::request_string($post, 'rp_key'));
        $reset_args = ['action' => 'rp', 'key' => $key, 'login' => $login];
        $nonce = static::request_string($post, 'wg_resetpassword_nonce');
        if (!wp_verify_nonce($nonce, 'wg_resetpassword')) {
            return array_merge($reset_args, [self::STATUS_FIELD => 'invalid_nonce']);
        }

        $user = check_password_reset_key($key, $login);
        if (is_wp_error($user)) {
            return [self::STATUS_FIELD => 'invalid_key'];
        }

        $pass1 = static::request_string($post, 'pass1');
        $pass2 = static::request_string($post, 'pass2');
        if ($pass1 === '' || $pass1 !== $pass2) {
            return array_merge($reset_args, [self::STATUS_FIELD => 'password_mismatch']);
        }

        reset_password($user, $pass1);
        return [self::STATUS_FIELD => 'reset_complete'];
    }

    public static function render($atts = []) {
        $atts = shortcode_atts(['login_url' => Config::get_login_url()], $atts, 'wg_password_reset_form');
        $status = sanitize_key(static::request_string($_GET, self::STATUS_FIELD));
        $login = sanitize_text_field(static::request_string($_GET, 'login'));
        $key = sanitize_text_field(static::request_string($_GET, 'key'));
        $mode = 'request';

        if ($status === 'reset_complete') {
            $mode = 'complete';
        } elseif ($status === 'request_sent') {
            $mode = 'sent';
        } elseif ($login !== '' && $key !== '') {
            if (is_wp_error(check_password_reset_key($key, $login))) {
                $status = 'invalid_key';
            } else {
                $mode = 'reset';
            }
        }

        ob_start();
        static::render_status($status);

        if ($mode === 'reset') {
            echo '<form method="post" class="wg-reset-password-form">';
            echo '<input type="hidden" name="' . esc_attr(self::ACTION_FIELD) . '" value="reset">';
            wp_nonce_field('wg_resetpassword', 'wg_resetpassword_nonce');
            echo '<input type="hidden" name="rp_login" value="' . esc_attr($login) . '">';
            echo '<input type="hidden" name="rp_key" value="' . esc_attr($key) . '">';
            echo '<div><label>' . esc_html__('Neues Passwort', 'wg-membership-suite') . '<input type="password" name="pass1" autocomplete="new-password" required></label></div>';
            echo '<div><label>' . esc_html__('Passwort wiederholen', 'wg-membership-suite') . '<input type="password" name="pass2" autocomplete="new-password" required></label></div>';
            echo '<div><button type="submit">' . esc_html__('Passwort setzen', 'wg-membership-suite') . '</button></div></form>';
        } elseif ($mode === 'complete') {
            echo '<a href="' . esc_url($atts['login_url']) . '">' . esc_html__('Zur Anmeldung', 'wg-membership-suite') . '</a>';
        } elseif ($mode !== 'sent') {
            echo '<form method="post" class="wg-lost-password-form">';
            echo '<input type="hidden" name="' . esc_attr(self::ACTION_FIELD) . '" value="request">';
            wp_nonce_field('wg_lostpassword', 'wg_lostpassword_nonce');
            echo '<div><label>' . esc_html__('E-Mail oder Benutzername', 'wg-membership-suite') . '<input type="text" name="user_login" autocomplete="username" required></label></div>';
            echo '<div><button type="submit">' . esc_html__('Passwort zurücksetzen', 'wg-membership-suite') . '</button></div></form>';
        }

        return ob_get_clean();
    }

    private static function request_string($source, $key) {
        if (!isset($source[$key]) || !is_string($source[$key])) {
            return '';
        }
        return wp_unslash($source[$key]);
    }

    private static function render_status($status) {
        $messages = [
            'request_sent' => ['status', __('Wenn ein passendes Konto existiert, wurde eine E-Mail zum Zurücksetzen versendet.', 'wg-membership-suite')],
            'reset_complete' => ['status', __('Das Passwort wurde erfolgreich gesetzt.', 'wg-membership-suite')],
            'invalid_nonce' => ['alert', __('Die Anfrage ist abgelaufen. Bitte versuchen Sie es erneut.', 'wg-membership-suite')],
            'invalid_key' => ['alert', __('Der Link zum Zurücksetzen ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen an.', 'wg-membership-suite')],
            'password_mismatch' => ['alert', __('Die Passwörter stimmen nicht überein.', 'wg-membership-suite')],
            'mail_failed' => ['alert', __('Die E-Mail konnte gerade nicht versendet werden. Bitte versuchen Sie es später erneut.', 'wg-membership-suite')],
        ];

        if (!isset($messages[$status])) {
            return;
        }

        [$role, $message] = $messages[$status];
        $class = $role === 'alert' ? 'wg-login-error' : 'wg-login-message';
        echo '<div class="' . esc_attr($class) . '" role="' . esc_attr($role) . '">' . esc_html($message) . '</div>';
    }

    public static function filter_lostpassword_url($lostpassword_url, $redirect) {
        $url = Config::get_password_reset_url();
        if (!empty($redirect)) {
            $url = add_query_arg('redirect_to', $redirect, $url);
        }
        return add_query_arg('action', 'lostpassword', $url);
    }

    public static function filter_reset_notification_email($defaults, $key, $user_login, $user_data) {
        $reset_url = add_query_arg([
            'action' => 'rp',
            'key' => $key,
            'login' => $user_login,
        ], Config::get_password_reset_url());

        $defaults['message'] = sprintf(
            /* translators: %s: password reset URL */
            __("Jemand hat das Zurücksetzen des Passworts angefordert. Falls Sie das waren, öffnen Sie diesen Link:\n\n%s\n\nFalls nicht, können Sie diese E-Mail ignorieren.", 'wg-membership-suite'),
            esc_url_raw($reset_url)
        );
        return $defaults;
    }

    public static function handle_mail_failure($error) {
        error_log('[wg-membership-suite] ' . wp_json_encode(static::get_mail_failure_log_entry($error)));
    }

    public static function get_mail_failure_log_entry($error) {
        $codes = is_wp_error($error) ? array_map('sanitize_key', $error->get_error_codes()) : ['unknown'];
        return [
            'event' => 'wp_mail_failed',
            'timestamp_utc' => gmdate('c'),
            'site_id' => function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0,
            'error_codes' => array_values($codes),
            'context' => static::$mail_context !== '' ? static::$mail_context : 'wp_mail',
        ];
    }
}
