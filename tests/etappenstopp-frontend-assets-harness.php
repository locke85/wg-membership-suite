<?php

define('ABSPATH', __DIR__ . '/wordpress/');
define('WG_MEMBERSHIP_SUITE_PATH', dirname(__DIR__) . '/');
define('WG_MEMBERSHIP_SUITE_URL', 'https://example.test/wp-content/plugins/wg-membership-suite/');

$GLOBALS['wg_actions'] = [];
$GLOBALS['wg_filters'] = [];
$GLOBALS['wg_styles'] = [];
$GLOBALS['wg_scripts'] = [];
$GLOBALS['wg_inline_scripts'] = [];
$GLOBALS['wg_admin'] = false;
$GLOBALS['wg_singular'] = true;
$GLOBALS['wg_forced'] = null;

function add_action($hook, $callback, $priority = 10) { $GLOBALS['wg_actions'][] = compact('hook', 'callback', 'priority'); }
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['wg_filters'][] = compact('hook', 'callback', 'priority', 'accepted_args'); }
function apply_filters($hook, $value) { return $GLOBALS['wg_forced']; }
function is_admin() { return $GLOBALS['wg_admin']; }
function is_singular() { return $GLOBALS['wg_singular']; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_enqueue_style($handle, $src, $deps, $version, $media) { $GLOBALS['wg_styles'][$handle] = compact('src', 'deps', 'version', 'media'); }
function wp_enqueue_script($handle, $src, $deps, $version, $footer) { $GLOBALS['wg_scripts'][$handle] = compact('src', 'deps', 'version', 'footer'); }
function wp_add_inline_script($handle, $data, $position = 'after') { $GLOBALS['wg_inline_scripts'][$handle][] = compact('data', 'position'); }
function get_stylesheet_directory() { return __DIR__; }
function get_stylesheet_directory_uri() { return 'https://example.test/theme'; }
function esc_url($url) { return $url; }
function esc_url_raw($url) { return $url; }
function rest_url($path = '') { return 'https://example.test/wp-json/' . ltrim($path, '/'); }
function wp_create_nonce($action) { return 'rest-nonce'; }
function wp_json_encode($value) { return json_encode($value); }

require_once dirname(__DIR__) . '/includes/Frontend/Assets.php';

use wg\membership\Frontend\Assets;

function assert_assets($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

Assets::register();
$hooks = array_column($GLOBALS['wg_actions'], 'hook');
$filters = array_column($GLOBALS['wg_filters'], 'hook');
assert_assets(in_array('enqueue_block_editor_assets', $hooks, true), 'editor asset hook must remain registered');
assert_assets(in_array('wp_enqueue_scripts', $hooks, true), 'frontend asset hook must be registered');
assert_assets(in_array('maas_ssg_authenticated_static_html', $filters, true), 'static response integration must be registered');
assert_assets(!in_array('render_block', $filters, true), 'temporary block output normalization filter must be removed');

$_SERVER['REQUEST_URI'] = '/schreibtisch/etappen/e06/#top';
assert_assets(Assets::should_load_etappenstopp_assets(), 'Etappen route must load assets');

$_SERVER['REQUEST_URI'] = '/schreibtisch/';
assert_assets(!Assets::should_load_etappenstopp_assets(), 'unrelated route must not load assets');

$GLOBALS['wg_forced'] = true;
assert_assets(Assets::should_load_etappenstopp_assets(), 'filter must allow an explicit opt-in');
$GLOBALS['wg_forced'] = null;

$_SERVER['REQUEST_URI'] = '/schreibtisch/etappen/e01/';
Assets::enqueue_etappenstopp_assets();
assert_assets(isset($GLOBALS['wg_styles']['wg-membership-etappenstopps']), 'responsive stylesheet must be enqueued');
assert_assets(isset($GLOBALS['wg_scripts']['wg-membership-etappenstopps']), 'state synchronization script must be enqueued');
assert_assets($GLOBALS['wg_styles']['wg-membership-etappenstopps']['version'] !== '', 'stylesheet needs a cache-busting version');
assert_assets($GLOBALS['wg_scripts']['wg-membership-etappenstopps']['version'] !== '', 'script needs a cache-busting version');
assert_assets(($GLOBALS['wg_inline_scripts']['wg-membership-etappenstopps'][0]['position'] ?? '') === 'before', 'runtime configuration must load before the script');
assert_assets(strpos($GLOBALS['wg_inline_scripts']['wg-membership-etappenstopps'][0]['data'] ?? '', 'custom\/v1\/spickzettel-url') !== false, 'runtime configuration needs the site-local REST endpoint');

$static_html = '<html><head></head><body><main>Etappe</main></body></html>';
$injected = Assets::inject_authenticated_static_assets($static_html);
assert_assets(substr_count($injected, 'id="wg-membership-etappenstopps-css"') === 1, 'static response needs the Etappenstopp stylesheet');
assert_assets(substr_count($injected, 'id="wg-membership-etappenstopps-config"') === 1, 'static response needs the Etappenstopp runtime configuration');
assert_assets(strpos($injected, '"restNonce":"rest-nonce"') !== false, 'static response nonce must be encoded');
assert_assets(strpos($injected, 'custom\\/v1\\/spickzettel-url') !== false, 'static response needs the site-local REST endpoint');
assert_assets(substr_count($injected, 'id="wg-membership-etappenstopps-js"') === 1, 'static response needs the Etappenstopp script');
assert_assets(Assets::inject_authenticated_static_assets($injected) === $injected, 'static injection must be idempotent');

echo "Etappenstopp frontend assets harness: 15 scenarios passed.\n";
