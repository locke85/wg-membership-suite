<?php

namespace {
    define('ABSPATH', __DIR__ . '/');
    define('MINUTE_IN_SECONDS', 60);

    $gate_transients = [];
    $gate_caps = ['manage_options' => true, 'wg_view_finance_archive' => true];
    $gate_audits = [];
    $gate_redirect = '';

    function current_user_can($capability) { global $gate_caps; return !empty($gate_caps[$capability]); }
    function wp_die($message, $title = '', $args = []) { throw new \RuntimeException((string) $message, (int) ($args['response'] ?? 0)); }
    function esc_html__($message) { return $message; }
    function __($message) { return $message; }
    function get_site_transient($key) { global $gate_transients; return $gate_transients[$key] ?? false; }
    function set_site_transient($key, $value, $ttl) { global $gate_transients; $gate_transients[$key] = $value; return true; }
    function delete_site_transient($key) { global $gate_transients; unset($gate_transients[$key]); return true; }
    function get_current_user_id() { return 7; }
    function wp_get_session_token() { return 'session-token'; }
    function check_admin_referer($action) { return true; }
    function wp_get_current_user() { return (object) ['ID' => 7, 'user_pass' => 'correct-password']; }
    function wp_unslash($value) { return $value; }
    function wp_check_password($password, $hash, $user_id) { return hash_equals($hash, $password); }
    function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
    function wp_safe_redirect($url) { global $gate_redirect; $gate_redirect = $url; return true; }
    function assert_gate($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
}

namespace wg\membership {
    class Config { const VIEW_FINANCE_ARCHIVE_CAPABILITY = 'wg_view_finance_archive'; }
}

namespace wg\membership\Finance {
    class Archive {
        public static function audit($event, $result, array $details = []) {
            $GLOBALS['gate_audits'][] = [$event, $result, $details];
        }
    }
}

namespace {
    require_once dirname(__DIR__) . '/includes/Admin/Analytics_Access_Gate.php';

    $gate_caps['manage_options'] = false;
    try {
        \wg\membership\Admin\Analytics_Access_Gate::require_admin();
        assert_gate(false, 'non-administrator access must be rejected');
    } catch (\RuntimeException $error) {
        assert_gate($error->getCode() === 403, 'non-administrator receives HTTP 403');
    }
    $gate_caps['manage_options'] = true;
    \wg\membership\Admin\Analytics_Access_Gate::require_admin();
    assert_gate(!\wg\membership\Admin\Analytics_Access_Gate::is_unlocked(), 'new session starts locked');

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['wg_finance_analytics_unlock' => '1', 'wg_finance_analytics_password' => 'wrong-password'];
    $error = \wg\membership\Admin\Analytics_Access_Gate::process_unlock();
    assert_gate($error !== '', 'wrong password returns a generic error');
    assert_gate(!\wg\membership\Admin\Analytics_Access_Gate::is_unlocked(), 'wrong password does not unlock analytics');
    assert_gate(json_encode($gate_audits) !== false && strpos(json_encode($gate_audits), 'wrong-password') === false, 'audit does not retain password input');

    register_shutdown_function(function () {
        global $gate_redirect, $gate_audits;
        assert_gate(\wg\membership\Admin\Analytics_Access_Gate::is_unlocked(), 'correct password unlocks current session');
        assert_gate(strpos($gate_redirect, 'wg-finance-analytics') !== false, 'successful unlock redirects to analytics');
        assert_gate(strpos(json_encode($gate_audits), 'correct-password') === false, 'successful audit does not retain password input');
        echo "analytics access gate harness: OK\n";
    });
    $_POST['wg_finance_analytics_password'] = 'correct-password';
    \wg\membership\Admin\Analytics_Access_Gate::process_unlock();
}
