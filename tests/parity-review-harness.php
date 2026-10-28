<?php

namespace {
    define('ABSPATH', __DIR__ . '/');

    $parity_options = [];
    $parity_updates = [];
    $parity_filters = [
        'wg_membership_suite_parity_site_id' => '',
        'wg_membership_suite_parity_manifest_path' => '',
    ];
    $parity_caps = ['manage_options' => true];
    $parity_nonce_valid = true;

    class WP_Error {
        private $code;
        private $message;
        public function __construct($code, $message) { $this->code = $code; $this->message = $message; }
        public function get_error_code() { return $this->code; }
        public function get_error_message() { return $this->message; }
    }
    class WP_REST_Request {}
    class WP_REST_Response {
        public $data;
        public $status;
        public function __construct($data, $status = 200) { $this->data = $data; $this->status = $status; }
    }

    function is_wp_error($value) { return $value instanceof WP_Error; }
    function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
    function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
    function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
    function absint($value) { return abs((int) $value); }
    function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
    function apply_filters($tag, $value) { global $parity_filters; return array_key_exists($tag, $parity_filters) ? $parity_filters[$tag] : $value; }
    function get_option($key, $default = false) { global $parity_options; return array_key_exists($key, $parity_options) ? $parity_options[$key] : $default; }
    function update_option($key, $value, $autoload = null) { global $parity_options, $parity_updates; $parity_options[$key] = $value; $parity_updates[] = [$key, $autoload]; return true; }
    function get_current_user_id() { return 7; }
    function wp_salt($scheme = 'auth') { return 'synthetic-site-signing-key'; }
    function current_user_can($capability) { global $parity_caps; return !empty($parity_caps[$capability]); }
    function check_admin_referer($action) { global $parity_nonce_valid; if (!$parity_nonce_valid) throw new \RuntimeException('invalid nonce', 401); return true; }
    function wp_die($message, $title = '', $args = []) { throw new \RuntimeException((string) $message, (int) ($args['response'] ?? 500)); }
    function __($message, $domain = null) { return $message; }
    function assert_parity($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
}

namespace {
    require_once dirname(__DIR__) . '/includes/Admin/Parity_Review_Admin.php';

    use wg\membership\Admin\Parity_Review_Admin;

    $manifest = [
        'schema_version' => 1,
        'kind' => 'membership_suite_parity_comparison',
        'site_id' => 'fixture-site',
        'baseline_version' => 'seosharingsystem-1.7.0-aaaaaaaaaaaa',
        'baseline_hash' => str_repeat('a', 64),
        'membership_suite_version' => '1.7.0',
        'diffs' => [
            ['diff_id' => 'msd_' . str_repeat('1', 20), 'path' => 'areas.login.slug', 'classification' => 'platform', 'baseline_value' => 'login-secure', 'target_value' => 'login', 'impact' => 'Login differs.', 'evidence' => ['source' => 'fixture']],
            ['diff_id' => 'msd_' . str_repeat('2', 20), 'path' => 'areas.settings.page_ids.login', 'classification' => 'expected_site_difference', 'baseline_value' => 10, 'target_value' => 20, 'impact' => 'Page ID differs.', 'evidence' => ['source' => 'fixture']],
            ['diff_id' => 'msd_' . str_repeat('3', 20), 'path' => 'areas.unknown.flag', 'classification' => 'unclear', 'baseline_value' => null, 'target_value' => true, 'impact' => 'Needs clarification.', 'evidence' => ['source' => 'fixture']],
        ],
    ];
    $manifest_path = tempnam(sys_get_temp_dir(), 'wg-parity-manifest-');
    file_put_contents($manifest_path, json_encode($manifest));

    $missing = Parity_Review_Admin::load_manifest();
    assert_parity(is_wp_error($missing) && $missing->get_error_code() === 'parity_site_id_missing', 'missing configured site ID fails closed');

    $parity_filters['wg_membership_suite_parity_site_id'] = 'fixture-site';
    $parity_filters['wg_membership_suite_parity_manifest_path'] = $manifest_path;
    $loaded = Parity_Review_Admin::load_manifest();
    assert_parity(!is_wp_error($loaded) && count($loaded['diffs']) === 3, 'valid manifest loads from the read-only path');

    $wrong_site = Parity_Review_Admin::validate_manifest($manifest, 'other-site');
    assert_parity(is_wp_error($wrong_site) && $wrong_site->get_error_code() === 'parity_manifest_site', 'manifest cannot cross site boundaries');

    $submitted = [
        'msd_' . str_repeat('1', 20) => ['decision' => 'sync', 'reason' => ''],
        'msd_' . str_repeat('2', 20) => ['decision' => 'keep', 'reason' => 'Local page ID'],
        'msd_' . str_repeat('3', 20) => ['decision' => 'other', 'reason' => 'Clarify the owner'],
        'msd_' . str_repeat('9', 20) => ['decision' => 'sync', 'reason' => 'Unknown diff'],
    ];
    $stored = Parity_Review_Admin::save_reviews($submitted, $loaded, 7, '2026-09-01T09:00:00Z');
    assert_parity(!is_wp_error($stored) && count($stored['decisions']) === 3, 'all three decisions persist and unknown diffs are ignored');
    assert_parity(Parity_Review_Admin::get_review_store() === $stored, 'review decisions survive reload');
    assert_parity(count($parity_updates) === 1 && $parity_updates[0][0] === Parity_Review_Admin::OPTION_KEY, 'adapter writes only the dedicated review option');

    $missing_reason = Parity_Review_Admin::save_reviews([
        'msd_' . str_repeat('2', 20) => ['decision' => 'keep', 'reason' => ''],
    ], $loaded, 7, '2026-09-01T09:00:00Z');
    assert_parity(is_wp_error($missing_reason) && $missing_reason->get_error_code() === 'parity_reason_required', 'keep requires a reason');

    $parity_caps['manage_options'] = false;
    assert_parity(!Parity_Review_Admin::can_review(), 'REST permission denies non-admins');
    try {
        Parity_Review_Admin::authorize_admin_request(Parity_Review_Admin::SAVE_ACTION);
        assert_parity(false, 'non-admin request must stop');
    } catch (\RuntimeException $error) {
        assert_parity($error->getCode() === 403, 'non-admin request receives 403');
    }
    $parity_caps['manage_options'] = true;
    $parity_nonce_valid = false;
    try {
        Parity_Review_Admin::authorize_admin_request(Parity_Review_Admin::SAVE_ACTION);
        assert_parity(false, 'invalid nonce must stop');
    } catch (\RuntimeException $error) {
        assert_parity($error->getCode() === 401, 'invalid nonce is rejected');
    }
    $parity_nonce_valid = true;
    assert_parity(Parity_Review_Admin::authorize_admin_request(Parity_Review_Admin::SAVE_ACTION), 'authorized request passes capability and nonce');

    $export = Parity_Review_Admin::build_export($loaded, $stored);
    assert_parity(count($export['decisions']) === 3, 'export contains current decisions');
    assert_parity($export['payload_hash'] === hash('sha256', Parity_Review_Admin::canonical_json(array_intersect_key($export, array_flip(['schema_version', 'kind', 'site_id', 'baseline_version', 'baseline_hash', 'membership_suite_version', 'decisions'])))), 'payload hash is canonical');
    assert_parity($export['signature'] === hash_hmac('sha256', $export['payload_hash'], wp_salt('auth')), 'export is signed with the site trust boundary');
    assert_parity(strpos(json_encode($export), 'baseline_value') === false && strpos(json_encode($export), 'evidence') === false, 'export excludes manifest evidence and values');

    $stale_manifest = $loaded;
    $stale_manifest['baseline_hash'] = str_repeat('b', 64);
    $stale_export = Parity_Review_Admin::build_export($stale_manifest, $stored);
    assert_parity(count($stale_export['decisions']) === 0, 'baseline change reopens prior decisions');

    if (($argv[1] ?? '') === '--export-json') {
        echo json_encode($export, JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        echo "parity review harness: OK\n";
    }
    unlink($manifest_path);
}
