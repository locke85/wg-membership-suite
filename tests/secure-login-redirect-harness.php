<?php

/**
 * Run with: php tests/secure-login-redirect-harness.php
 */

define('ABSPATH', __DIR__ . '/wordpress/');

class WP_User {
    public $roles;

    public function __construct($roles = []) {
        $this->roles = $roles;
    }
}

function home_url($path = '') {
    return 'https://example.test' . $path;
}

function wp_validate_redirect($location, $fallback = '') {
    $host = parse_url((string) $location, PHP_URL_HOST);
    return $host === null || $host === 'example.test' ? $location : $fallback;
}

require_once dirname(__DIR__) . '/includes/Login/Secure_Login.php';

use wg\membership\Login\Secure_Login;

function assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': ' . var_export($actual, true));
    }
}

$member = new WP_User(['editor']);
assert_same(
    'https://example.test/narrative-training/',
    Secure_Login::custom_login_redirect('https://example.test/wp-admin/', 'https://example.test/narrative-training/', $member),
    'A valid requested training redirect must take priority.'
);
assert_same(
    'https://example.test/schreibtisch',
    Secure_Login::custom_login_redirect('', '', $member),
    'The dashboard remains the default without a requested destination.'
);
assert_same(
    'https://example.test/schreibtisch',
    Secure_Login::custom_login_redirect('', 'https://untrusted.example/redirect', $member),
    'An external redirect must fall back to the dashboard.'
);

echo "Secure login redirect harness passed.\n";
