<?php

$registered_filters = [];

define('ABSPATH', __DIR__ . '/');
define('COOKIEPATH', '/');
define('COOKIE_DOMAIN', '.webgefaehrte.de');

function add_filter($hook, $callback) {
    global $registered_filters;
    $registered_filters[$hook][] = $callback;
}

function add_action() {}
function wp_unslash($value) { return $value; }
function is_ssl() { return true; }

require_once dirname(__DIR__) . '/includes/Checkout/Step_1.php';

use wg\membership\Checkout\Step_1;

function assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

Step_1::register();
assert_same(true, isset($registered_filters['cf7msm_force_session'][0]), 'CF7MSM force-session filter registered');
assert_same('tanzlehrer-recruiting.de', Step_1::normalize_request_host('Tanzlehrer-Recruiting.de:443'), 'Request host normalized');
assert_same(true, Step_1::cookie_domain_covers_host('.webgefaehrte.de', 'workspace.webgefaehrte.de'), 'Network subdomain covered');
assert_same(true, Step_1::cookie_domain_covers_host('.webgefaehrte.de', 'webgefaehrte.de'), 'Network apex covered');
assert_same(false, Step_1::cookie_domain_covers_host('.webgefaehrte.de', 'tanzlehrer-recruiting.de'), 'Mapped domain not covered');
assert_same(false, Step_1::should_force_cf7msm_session(false, 'workspace.webgefaehrte.de', '.webgefaehrte.de'), 'Network host keeps cookie state');
assert_same(true, Step_1::should_force_cf7msm_session(false, 'tanzlehrer-recruiting.de', '.webgefaehrte.de'), 'Mapped host uses session state');
assert_same(false, Step_1::should_force_cf7msm_session(false, 'tanzlehrer-recruiting.de', ''), 'Host-only cookies need no session fallback');
assert_same(true, Step_1::should_force_cf7msm_session(true, 'webgefaehrte.de', '.webgefaehrte.de'), 'Existing force-session decision preserved');
assert_same(false, Step_1::should_force_cf7msm_session(false, "bad\nhost", '.webgefaehrte.de'), 'Malformed request host does not activate session');

$_SERVER['HTTP_HOST'] = 'tanzlehrer-recruiting.de';
assert_same(true, Step_1::force_cf7msm_session_for_mapped_domain(false), 'Live callback forces mapped-domain session');
$params = session_get_cookie_params();
assert_same('', $params['domain'], 'Session cookie is host-only');
assert_same('/', $params['path'], 'Session cookie uses WordPress cookie path');
assert_same(true, $params['secure'], 'Session cookie is secure');
assert_same(true, $params['httponly'], 'Session cookie is HttpOnly');
assert_same('Lax', $params['samesite'], 'Session cookie uses SameSite=Lax');

echo "Checkout mapped-domain state harness passed.\n";
