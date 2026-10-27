<?php

/**
 * Focused compatibility harness for the login-page presentation.
 *
 * Run with: php tests/login-form-presentation-harness.php
 */

define('ABSPATH', __DIR__ . '/wordpress/');
define('WG_MEMBERSHIP_SUITE_URL', 'https://example.test/wp-content/plugins/wg-membership-suite/');
define('WG_MEMBERSHIP_SUITE_VERSION', '1.9.4');

$GLOBALS['wg_test_settings'] = ['login_page_id' => 43, 'login_page' => 'member-login'];
$GLOBALS['wg_is_page'] = 43;
$GLOBALS['wg_hooks'] = [];
$GLOBALS['wg_removed_actions'] = [];
$GLOBALS['wg_enqueued_styles'] = [];

function get_option($key, $default = []) { return $key === 'wg_membership_suite_settings' ? $GLOBALS['wg_test_settings'] : $default; }
function absint($value) { return abs((int) $value); }
function is_page($page) { return $page === $GLOBALS['wg_is_page']; }
function add_shortcode($tag, $callback) { $GLOBALS['wg_hooks'][] = ['type' => 'shortcode', 'hook' => $tag]; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['wg_hooks'][] = ['type' => 'action', 'hook' => $hook, 'priority' => $priority]; }
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['wg_hooks'][] = ['type' => 'filter', 'hook' => $hook, 'priority' => $priority]; }
function remove_action($hook, $callback, $priority = 10) { $GLOBALS['wg_removed_actions'][] = [$hook, $callback, $priority]; }
function remove_all_actions($hook) { $GLOBALS['wg_removed_actions'][] = [$hook, 'all', null]; }
function wp_enqueue_style($handle, $src, $deps = [], $version = false) { $GLOBALS['wg_enqueued_styles'][] = [$handle, $src, $version]; }

require_once dirname(__DIR__) . '/includes/Config.php';
require_once dirname(__DIR__) . '/includes/Shortcodes/Login_Form.php';

use wg\membership\Shortcodes\Login_Form;

function assert_true($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }

Login_Form::register();
assert_true(in_array(['type' => 'action', 'hook' => 'wp', 'priority' => 101], $GLOBALS['wg_hooks'], true), 'GeneratePress integration must run after Layout Elements.');

Login_Form::configure_login_page();
assert_true(in_array(['generate_before_header', 'all', null], $GLOBALS['wg_removed_actions'], true), 'The login page must remove GenerateBlocks hooked before the header.');
assert_true(in_array(['generate_header', 'generate_construct_header', 10], $GLOBALS['wg_removed_actions'], true), 'The login page must remove the GeneratePress header.');
assert_true(in_array(['generate_footer', 'generate_construct_footer', 10], $GLOBALS['wg_removed_actions'], true), 'The login page must remove the GeneratePress footer.');

Login_Form::enqueue_styles();
assert_true(in_array(['wg-membership-login-form', WG_MEMBERSHIP_SUITE_URL . 'assets/css/login-form.css', WG_MEMBERSHIP_SUITE_VERSION], $GLOBALS['wg_enqueued_styles'], true), 'The login page must enqueue its scoped form stylesheet.');

assert_true(in_array('wg-membership-login-page', Login_Form::add_body_class([]), true), 'The login page needs a scoped body class.');

$GLOBALS['wg_is_page'] = 99;
$before = count($GLOBALS['wg_removed_actions']);
Login_Form::configure_login_page();
assert_true($before === count($GLOBALS['wg_removed_actions']), 'Other pages must not lose their GeneratePress layout.');

echo "Login form presentation harness passed.\n";
