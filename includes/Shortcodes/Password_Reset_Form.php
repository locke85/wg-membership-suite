<?php

namespace wg\membership\Shortcodes;

use wg\membership\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Password_Reset_Form {
    public static function register() {
        add_shortcode('wg_password_reset_form', [static::class, 'render']);
        add_filter('lostpassword_url', [static::class, 'filter_lostpassword_url'], 10, 2);
    }

    public static function render($atts = []) {
        $atts = shortcode_atts(['login_url' => Config::get_login_url()], $atts, 'wg_password_reset_form');
        $message = '';
        $error = '';
        $mode = 'lostpassword';
        $login = isset($_REQUEST['login']) ? sanitize_text_field(wp_unslash($_REQUEST['login'])) : (isset($_REQUEST['rp_login']) ? sanitize_text_field(wp_unslash($_REQUEST['rp_login'])) : '');
        $key = isset($_REQUEST['key']) ? sanitize_text_field(wp_unslash($_REQUEST['key'])) : (isset($_REQUEST['rp_key']) ? sanitize_text_field(wp_unslash($_REQUEST['rp_key'])) : '');
        if ($login !== '' && $key !== '') {
            $mode = 'reset';
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (isset($_POST['wg_lostpassword_nonce']) && wp_verify_nonce($_POST['wg_lostpassword_nonce'], 'wg_lostpassword')) {
                $_POST['user_login'] = isset($_POST['user_login']) ? sanitize_text_field(wp_unslash($_POST['user_login'])) : '';
                $result = retrieve_password();
                if ($result === true) {
                    $message = __('E-Mail zum Zurücksetzen wurde versendet.', 'wg-membership-suite');
                } elseif (is_wp_error($result)) {
                    $error = $result->get_error_message();
                }
            } elseif (isset($_POST['wg_resetpassword_nonce']) && wp_verify_nonce($_POST['wg_resetpassword_nonce'], 'wg_resetpassword')) {
                $login = sanitize_text_field(wp_unslash($_POST['rp_login'] ?? ''));
                $key = sanitize_text_field(wp_unslash($_POST['rp_key'] ?? ''));
                $pass1 = (string) ($_POST['pass1'] ?? '');
                $pass2 = (string) ($_POST['pass2'] ?? '');
                $user = check_password_reset_key($key, $login);
                if (is_wp_error($user)) {
                    $error = $user->get_error_message();
                } elseif ($pass1 === '' || $pass1 !== $pass2) {
                    $error = __('Die Passwörter stimmen nicht überein.', 'wg-membership-suite');
                } else {
                    reset_password($user, $pass1);
                    $message = __('Passwort wurde erfolgreich gesetzt.', 'wg-membership-suite');
                    $mode = 'done';
                }
            }
        }

        ob_start();
        if ($message !== '') { echo '<div class="wg-login-message">' . esc_html($message) . '</div>'; }
        if ($error !== '') { echo '<div class="wg-login-error">' . esc_html($error) . '</div>'; }
        if ($mode === 'reset') {
            echo '<form method="post" class="wg-reset-password-form">';
            wp_nonce_field('wg_resetpassword', 'wg_resetpassword_nonce');
            echo '<input type="hidden" name="rp_login" value="' . esc_attr($login) . '"><input type="hidden" name="rp_key" value="' . esc_attr($key) . '"><p><input type="password" name="pass1" placeholder="' . esc_attr__('Neues Passwort', 'wg-membership-suite') . '" required></p><p><input type="password" name="pass2" placeholder="' . esc_attr__('Passwort wiederholen', 'wg-membership-suite') . '" required></p><p><button type="submit">' . esc_html__('Passwort setzen', 'wg-membership-suite') . '</button></p></form>';
        } elseif ($mode === 'done') {
            echo '<p><a href="' . esc_url($atts['login_url']) . '">' . esc_html__('Zur Anmeldung', 'wg-membership-suite') . '</a></p>';
        } else {
            echo '<form method="post" class="wg-lost-password-form">';
            wp_nonce_field('wg_lostpassword', 'wg_lostpassword_nonce');
            echo '<p><input type="text" name="user_login" placeholder="' . esc_attr__('E-Mail oder Benutzername', 'wg-membership-suite') . '" required></p><p><button type="submit">' . esc_html__('Passwort zurücksetzen', 'wg-membership-suite') . '</button></p></form>';
        }
        return ob_get_clean();
    }

    public static function filter_lostpassword_url($lostpassword_url, $redirect) {
        $url = Config::get_password_reset_url();
        if (!empty($redirect)) {
            $url = add_query_arg('redirect_to', rawurlencode($redirect), $url);
        }
        return add_query_arg('action', 'lostpassword', $url);
    }
}
