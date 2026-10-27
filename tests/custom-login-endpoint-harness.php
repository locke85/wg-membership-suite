<?php

/**
 * Focused compatibility harness for the custom login endpoint.
 *
 * Run with: php tests/custom-login-endpoint-harness.php
 */

define('ABSPATH', __DIR__ . '/wordpress/');

$GLOBALS['wg_test_settings'] = ['two_factor_enabled' => true, 'login_page' => 'member-login', 'password_reset_page' => 'member-reset'];

class WP_Error {
    public $code;
    public function __construct($code, $message = '') { $this->code = $code; }
}

function get_option($key, $default = []) { return $key === 'wg_membership_suite_settings' ? $GLOBALS['wg_test_settings'] : ($GLOBALS['wg_options'][$key] ?? $default); }
function update_option($key, $value, $autoload = null) { $GLOBALS['wg_options'][$key] = $value; }
function absint($value) { return abs((int) $value); }
function get_permalink($id) { return 'https://example.test/account/login/'; }
function home_url($path = '') { return 'https://example.test' . $path; }
function get_site_url($blog_id = null, $path = '', $scheme = null) { $GLOBALS['wg_get_site_url_calls'] = ($GLOBALS['wg_get_site_url_calls'] ?? 0) + 1; return 'https://example.test/' . ltrim($path, '/'); }
function network_site_url($path = '', $scheme = null) { return 'https://example.test/' . ltrim($path, '/'); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_unslash($value) { return is_string($value) ? stripslashes($value) : $value; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function __($value, $domain = '') { return $value; }
function esc_html__($value, $domain = '') { return $value; }
function add_query_arg($query, $url) { return $url . '?' . http_build_query((array) $query, '', '&', PHP_QUERY_RFC3986); }
function flush_rewrite_rules($hard = true) { $GLOBALS['wg_rewrite_flushes'] = ($GLOBALS['wg_rewrite_flushes'] ?? 0) + 1; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['wg_hooks'][] = ['type' => 'action', 'hook' => $hook]; }
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['wg_hooks'][] = ['type' => 'filter', 'hook' => $hook]; }

require_once dirname(__DIR__) . '/includes/Config.php';
require_once dirname(__DIR__) . '/includes/Login/Two_Factor_Email.php';

use wg\membership\Login\Two_Factor_Email;

function assert_same($expected, $actual, $message) {
    if ($expected !== $actual) { throw new RuntimeException($message . ': ' . var_export($actual, true)); }
}
function assert_true($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function request_state($uri, $method = 'GET', $post = [], $request = []) {
    $_SERVER = ['REQUEST_URI' => $uri, 'REQUEST_METHOD' => $method, 'SCRIPT_NAME' => '/wp-login.php'];
    $_POST = $post;
    $_REQUEST = $request ?: $post;
}

$GLOBALS['wg_hooks'] = [];
$GLOBALS['wg_options'] = [];
Two_Factor_Email::register();
assert_true(in_array(['type' => 'action', 'hook' => 'template_redirect'], $GLOBALS['wg_hooks'], true), 'Configured login requests must be dispatched before the page template renders.');

request_state('/member-login/');
assert_same('configured', Two_Factor_Email::get_login_request_context(), 'The rewritten slug must be recognized as configured.');
$GLOBALS['wg_get_site_url_calls'] = 0;
assert_same('wp-login.php', Two_Factor_Email::get_core_login_path(), 'The core login path must use the network URL.');
assert_same(0, $GLOBALS['wg_get_site_url_calls'], 'The site_url filter must never recurse through get_site_url().');
assert_same('https://example.test/member-login/?action=logout', Two_Factor_Email::filter_site_login_url('https://example.test/wp-login.php?action=logout', 'wp-login.php?action=logout', 'login'), 'Generated logout URL must preserve its action.');
assert_same('https://example.test/member-login/?reauth=1', Two_Factor_Email::filter_site_login_url('https://example.test/wp-login.php?reauth=1', 'wp-login.php?reauth=1', 'login'), 'Generated reauthentication URL must use the configured path.');
assert_same('https://example.test/member-login/?interim-login=1', Two_Factor_Email::filter_site_login_url('https://example.test/wp-login.php?interim-login=1', 'wp-login.php?interim-login=1', 'login'), 'Generated interim-login URL must use the configured path.');
assert_same('https://example.test/member-reset/?action=rp', Two_Factor_Email::filter_site_login_url('https://example.test/wp-login.php?action=rp', 'wp-login.php?action=rp', 'login'), 'Generated reset URLs must use the configured reset page.');
assert_true(!Two_Factor_Email::should_dispatch_configured_login_request(), 'A configured login GET must render the styled WordPress page.');

request_state('/member-login/', 'POST', ['log' => '', 'pwd' => '']);
assert_true(Two_Factor_Email::should_dispatch_configured_login_request(), 'A configured credential POST must reach the WordPress core login handler.');

request_state('/member-login/', 'POST', ['post_password' => ''], ['action' => 'postpass']);
assert_true(Two_Factor_Email::should_dispatch_configured_login_request(), 'A configured protected-post submission must reach the WordPress core handler.');

request_state('/member-login/', 'GET', [], ['action' => 'logout']);
assert_true(Two_Factor_Email::should_dispatch_configured_login_request(), 'A configured logout link must reach the WordPress core handler.');

request_state('/member-login/', 'GET', [], ['interim-login' => '1']);
assert_true(Two_Factor_Email::should_dispatch_configured_login_request(), 'A configured interim-login request must reach the WordPress core handler.');

request_state('/member-login/', 'GET', [], ['reauth' => '1']);
assert_true(!Two_Factor_Email::should_dispatch_configured_login_request(), 'A reauthentication GET must retain the configured login page and its form.');

request_state('/member-login/', 'GET', [], ['action' => 'lostpassword']);
assert_true(!Two_Factor_Email::should_dispatch_configured_login_request(), 'Password-reset requests must retain the custom reset form.');

Two_Factor_Email::remove_legacy_login_rewrite();
assert_same(1, $GLOBALS['wg_rewrite_flushes'], 'Legacy rewrite rules must be removed exactly once.');
Two_Factor_Email::remove_legacy_login_rewrite();
assert_same(1, $GLOBALS['wg_rewrite_flushes'], 'The rewrite cleanup must not repeat on later requests.');

request_state('/wp-login.php');
assert_same('direct', Two_Factor_Email::get_login_request_context(), 'The public core path must be direct.');
assert_true(Two_Factor_Email::should_reject_core_login_request(), 'A direct core GET must receive the neutral response.');

request_state('/wp-login.php', 'POST', ['log' => 'synthetic', 'pwd' => 'synthetic']);
$blocked = Two_Factor_Email::block_direct_core_credentials(null, 'synthetic', 'synthetic');
assert_true($blocked instanceof WP_Error && $blocked->code === 'wg_login_path_unavailable', 'A direct credential post must be rejected before authentication.');

request_state('/member-login/', 'POST', ['log' => 'synthetic', 'pwd' => 'synthetic']);
assert_same(null, Two_Factor_Email::block_direct_core_credentials(null, 'synthetic', 'synthetic'), 'The configured login path must retain authentication.');

request_state('/wp-login.php', 'POST', ['post_password' => 'synthetic'], ['action' => 'postpass']);
assert_true(!Two_Factor_Email::is_credential_request(), 'The protected-post action must not be treated as a credential login.');
assert_true(!Two_Factor_Email::should_reject_core_login_request(), 'The protected-post action is the sole direct-core exception.');

$GLOBALS['wg_test_settings'] = ['two_factor_enabled' => false, 'login_page' => 'member-login'];
request_state('/wp-login.php');
assert_true(!Two_Factor_Email::should_reject_core_login_request(), 'Disabled 2FA must preserve direct core GET behavior.');
assert_same('https://example.test/wp-login.php?action=logout', Two_Factor_Email::filter_site_login_url('https://example.test/wp-login.php?action=logout', 'wp-login.php?action=logout', 'login'), 'Disabled 2FA must preserve generated core URLs.');
request_state('/wp-login.php', 'POST', ['log' => 'synthetic', 'pwd' => 'synthetic']);
assert_same(null, Two_Factor_Email::block_direct_core_credentials(null, 'synthetic', 'synthetic'), 'Disabled 2FA must preserve core authentication.');
request_state('/member-login/', 'POST', ['log' => '', 'pwd' => '']);
assert_true(!Two_Factor_Email::should_dispatch_configured_login_request(), 'Disabled 2FA must not change configured-page POST handling.');

$GLOBALS['wg_test_settings'] = ['two_factor_enabled' => true, 'login_page_id' => 43, 'login_page' => 'legacy'];
request_state('/account/login/');
assert_same('configured', Two_Factor_Email::get_login_request_context(), 'A configured page ID must be recognized as the login path.');
request_state('/account/login/', 'POST', ['log' => '', 'pwd' => '']);
assert_true(Two_Factor_Email::should_dispatch_configured_login_request(), 'A configured page ID must dispatch credential submissions to WordPress core.');

echo "Custom login endpoint harness passed.\n";
