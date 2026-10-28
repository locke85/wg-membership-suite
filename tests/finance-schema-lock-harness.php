<?php

namespace wg\membership\Finance;

\define('ABSPATH', '/tmp/');

$GLOBALS['wg_schema_options'] = [];
$GLOBALS['wg_schema_add_results'] = [];
$GLOBALS['wg_schema_calls'] = [];

function get_option($key, $default = false) {
    $GLOBALS['wg_schema_calls'][] = ['get', $key];
    return array_key_exists($key, $GLOBALS['wg_schema_options']) ? $GLOBALS['wg_schema_options'][$key] : $default;
}

function add_option($key, $value, $deprecated = '', $autoload = true) {
    $GLOBALS['wg_schema_calls'][] = ['add', $key, $value, $autoload];
    $result = array_shift($GLOBALS['wg_schema_add_results']);
    if ($result) {
        $GLOBALS['wg_schema_options'][$key] = $value;
    }
    return (bool) $result;
}

function delete_option($key) {
    $GLOBALS['wg_schema_calls'][] = ['delete', $key];
    unset($GLOBALS['wg_schema_options'][$key]);
    return true;
}

function time() {
    return 1000;
}

require_once dirname(__DIR__) . '/includes/Finance/Schema.php';

function assert_schema_lock($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function reset_schema_lock(array $options, array $add_results) {
    $GLOBALS['wg_schema_options'] = $options;
    $GLOBALS['wg_schema_add_results'] = $add_results;
    $GLOBALS['wg_schema_calls'] = [];
}

reset_schema_lock([Schema::VERSION_OPTION => Schema::VERSION], []);
Schema::maybe_upgrade();
assert_schema_lock(count(array_filter($GLOBALS['wg_schema_calls'], static fn($call) => $call[0] === 'add')) === 0, 'current schema must not acquire a lock');

reset_schema_lock([
    Schema::VERSION_OPTION => '',
    Schema::UPGRADE_LOCK_OPTION => 900,
], [false]);
Schema::maybe_upgrade();
assert_schema_lock(count(array_filter($GLOBALS['wg_schema_calls'], static fn($call) => $call[0] === 'delete')) === 0, 'fresh lock must remain owned by the other request');

reset_schema_lock([
    Schema::VERSION_OPTION => Schema::VERSION,
    Schema::UPGRADE_LOCK_OPTION => 600,
], [false, true]);
$GLOBALS['wg_schema_options'][Schema::VERSION_OPTION] = '';
// Drive the private lock path through reflection so no database installer is needed.
$method = new \ReflectionMethod(Schema::class, 'acquire_upgrade_lock');
assert_schema_lock($method->invoke(null) === true, 'stale lock must be replaceable');
$deletes = array_values(array_filter($GLOBALS['wg_schema_calls'], static fn($call) => $call[0] === 'delete'));
assert_schema_lock(count($deletes) === 1, 'stale lock must be deleted exactly once before reacquisition');

echo "Finance schema lock harness passed.\n";
