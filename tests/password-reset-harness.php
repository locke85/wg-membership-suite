<?php

/**
 * Minimal WordPress compatibility harness for the password-reset flow.
 *
 * Run with: php tests/password-reset-harness.php
 */

define('ABSPATH', __DIR__ . '/wordpress/');

$GLOBALS['wg_test_settings'] = [
    'login_page_id' => 43,
    'password_reset_page_id' => 44,
];
$GLOBALS['wg_test_passwords'] = [];

class WP_Error {
    private $errors;

    public function __construct($code, $message = '', $data = null) {
        $this->errors = [$code => [$message, $data]];
    }

    public function get_error_codes() {
        return array_keys($this->errors);
    }
}

class WP_User {
    public $user_login;

    public function __construct($user_login) {
        $this->user_login = $user_login;
    }
}

function get_option($key, $default = []) {
    return $key === 'wg_membership_suite_settings' ? $GLOBALS['wg_test_settings'] : $default;
}
function absint($value) { return abs((int) $value); }
function get_permalink($id) { return 'https://example.test/page-' . (int) $id . '/'; }
function home_url($path = '') { return 'https://example.test' . $path; }
function shortcode_atts($defaults, $atts, $shortcode = '') { return array_merge($defaults, (array) $atts); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function wp_unslash($value) { return is_string($value) ? stripslashes($value) : $value; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function esc_url_raw($value) { return filter_var((string) $value, FILTER_SANITIZE_URL); }
function __($value, $domain = '') { return $value; }
function esc_html__($value, $domain = '') { return esc_html($value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_json_encode($value) { return json_encode($value); }
function get_current_blog_id() { return 9; }
function wp_verify_nonce($nonce, $action) { return hash_equals('valid-' . $action, (string) $nonce); }
function wp_nonce_field($action, $name) {
    echo '<input type="hidden" name="' . esc_attr($name) . '" value="valid-' . esc_attr($action) . '">';
}
function add_shortcode($name, $callback) {}
function add_filter($name, $callback, $priority = 10, $accepted_args = 1) {}
function add_action($name, $callback, $priority = 10, $accepted_args = 1) {}
function wp_safe_redirect($url, $status = 302) {}

function add_query_arg($key, $value = null, $url = null) {
    if (is_array($key)) {
        $args = $key;
        $url = (string) $value;
    } else {
        $args = [$key => $value];
        $url = (string) $url;
    }

    $parts = parse_url($url);
    $query = [];
    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query = array_merge($query, $args);
    $result = $parts['scheme'] . '://' . $parts['host'] . ($parts['path'] ?? '');
    return $result . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function retrieve_password() {
    $login = $_POST['user_login'] ?? '';
    if ($login === 'mailfail') {
        $error = new WP_Error('retrieve_password_email_failure', 'recipient@example.test secret-key');
        $GLOBALS['wg_test_mail_log'] = \wg\membership\Shortcodes\Password_Reset_Form::get_mail_failure_log_entry($error);
        return $error;
    }
    if ($login === 'missing') {
        return new WP_Error('invalidcombo', 'Account does not exist');
    }
    return true;
}

function check_password_reset_key($key, $login) {
    if ($key !== 'valid-key' || $login !== 'alice') {
        return new WP_Error('expired_key', 'Expired');
    }
    return new WP_User($login);
}

function reset_password($user, $password) {
    $GLOBALS['wg_test_passwords'][$user->user_login] = password_hash($password, PASSWORD_DEFAULT);
}

function wp_authenticate_username_password($unused, $login, $password) {
    $hash = $GLOBALS['wg_test_passwords'][$login] ?? '';
    return $hash !== '' && password_verify($password, $hash) ? new WP_User($login) : new WP_Error('incorrect_password', 'Incorrect');
}

require_once dirname(__DIR__) . '/includes/Config.php';
require_once dirname(__DIR__) . '/includes/Shortcodes/Password_Reset_Form.php';

use wg\membership\Shortcodes\Password_Reset_Form;

function assert_true($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_contains($needle, $haystack, $message) {
    assert_true(strpos($haystack, $needle) !== false, $message . ' (missing: ' . $needle . ')');
}

function render_with_query($query) {
    $_GET = $query;
    return Password_Reset_Form::render();
}

$lost_url = Password_Reset_Form::filter_lostpassword_url('', 'https://example.test/private/?a=1&b=2');
parse_str((string) parse_url($lost_url, PHP_URL_QUERY), $lost_query);
assert_true(strpos($lost_url, 'https://example.test/page-44/') === 0, 'Lost-password URL must use the configured page.');
assert_true($lost_query['action'] === 'lostpassword', 'Lost-password action is missing.');
assert_true($lost_query['redirect_to'] === 'https://example.test/private/?a=1&b=2', 'Redirect must be encoded exactly once.');

$mail = Password_Reset_Form::filter_reset_notification_email(['message' => 'core /wp-login.php?action=rp'], 'key +/=?', 'alice@example.test', new WP_User('alice'));
assert_contains('https://example.test/page-44/', $mail['message'], 'Mail must use the configured reset page.');
assert_contains('action=rp', $mail['message'], 'Mail reset action is missing.');
assert_contains('key=key%20%2B%2F%3D%3F', $mail['message'], 'Reset key is not URL-encoded.');
assert_contains('login=alice%40example.test', $mail['message'], 'Login is not URL-encoded.');
assert_true(strpos($mail['message'], '/wp-login.php') === false, 'Mail must not contain the WordPress core reset URL.');

$valid_form = render_with_query(['action' => 'rp', 'key' => 'valid-key', 'login' => 'alice']);
assert_contains('name="pass1"', $valid_form, 'A valid key must render the password fields.');
assert_true(strpos($valid_form, '<p><form') === false, 'The shortcode must not generate nested paragraph/form markup.');

$expired_form = render_with_query(['action' => 'rp', 'key' => 'expired-key', 'login' => 'alice']);
assert_contains('role="alert"', $expired_form, 'An expired key must render an accessible alert.');
assert_contains('ungültig oder abgelaufen', $expired_form, 'An expired key needs a visible generic message.');

$invalid_nonce = Password_Reset_Form::process_post('request', ['user_login' => 'alice']);
assert_true($invalid_nonce['wg_password_reset_status'] === 'invalid_nonce', 'A missing nonce must produce an explicit state.');
$invalid_nonce_html = render_with_query($invalid_nonce);
assert_contains('role="alert"', $invalid_nonce_html, 'A nonce failure must stay visible after redirect.');

$known_result = Password_Reset_Form::process_post('request', ['wg_lostpassword_nonce' => 'valid-wg_lostpassword', 'user_login' => 'alice']);
$missing_result = Password_Reset_Form::process_post('request', ['wg_lostpassword_nonce' => 'valid-wg_lostpassword', 'user_login' => 'missing']);
assert_true($known_result === $missing_result, 'Known and unknown accounts must receive the same frontend state.');
assert_contains('role="status"', render_with_query($known_result), 'Request success must stay visible after redirect.');

$mail_failure = Password_Reset_Form::process_post('request', ['wg_lostpassword_nonce' => 'valid-wg_lostpassword', 'user_login' => 'mailfail']);
assert_true($mail_failure['wg_password_reset_status'] === 'mail_failed', 'A mail transport failure must be visible.');
assert_contains('role="alert"', render_with_query($mail_failure), 'Mail failure must render an accessible alert.');
assert_true($GLOBALS['wg_test_mail_log']['context'] === 'password_reset_request', 'Mail failure log needs the reset-request context.');
$log = wp_json_encode(Password_Reset_Form::get_mail_failure_log_entry(new WP_Error('smtp_failed', 'recipient@example.test key=secret')));
assert_contains('"site_id":9', $log, 'Mail failure log needs the site ID.');
assert_contains('smtp_failed', $log, 'Mail failure log needs the error code.');
assert_true(strpos($log, 'recipient@example.test') === false && strpos($log, 'secret') === false, 'Mail failure log must not contain error data or secrets.');

$reset_result = Password_Reset_Form::process_post('reset', [
    'wg_resetpassword_nonce' => 'valid-wg_resetpassword',
    'rp_login' => 'alice',
    'rp_key' => 'valid-key',
    'pass1' => 'new-secret-password',
    'pass2' => 'new-secret-password',
]);
assert_true($reset_result['wg_password_reset_status'] === 'reset_complete', 'A valid reset must complete.');
assert_true(!is_wp_error(wp_authenticate_username_password(null, 'alice', 'new-secret-password')), 'The new password must authenticate the synthetic user.');
assert_true(is_wp_error(wp_authenticate_username_password(null, 'alice', 'old-password')), 'The old password must no longer authenticate the synthetic user.');
assert_contains('Zur Anmeldung', render_with_query($reset_result), 'Completion must link back to login.');

echo "Password reset harness: 8 scenarios passed.\n";
