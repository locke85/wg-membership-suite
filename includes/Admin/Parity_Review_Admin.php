<?php

namespace wg\membership\Admin;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class Parity_Review_Admin {
    public const OPTION_KEY = 'wg_membership_suite_parity_reviews_v1';
    public const PAGE_SLUG = 'wg-membership-suite-parity-review';
    public const SAVE_ACTION = 'wg_membership_suite_parity_save';
    public const EXPORT_ACTION = 'wg_membership_suite_parity_export';
    public const REST_NAMESPACE = 'wg-membership/v1';
    public const REST_ROUTE = '/parity-review/export';

    public static function register() {
        add_action('admin_menu', [static::class, 'add_page']);
        add_action('admin_post_' . self::SAVE_ACTION, [static::class, 'handle_save']);
        add_action('admin_post_' . self::EXPORT_ACTION, [static::class, 'handle_export']);
        add_action('rest_api_init', [static::class, 'register_rest_routes']);
    }

    public static function add_page() {
        add_options_page(
            __('Membership Suite Abgleich', 'wg-membership-suite'),
            __('Membership-Abgleich', 'wg-membership-suite'),
            'manage_options',
            self::PAGE_SLUG,
            [static::class, 'render_page']
        );
    }

    public static function register_rest_routes() {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods' => 'GET',
            'callback' => [static::class, 'rest_export'],
            'permission_callback' => [static::class, 'can_review'],
        ]);
    }

    public static function can_review() {
        return current_user_can('manage_options');
    }

    public static function authorize_admin_request($action) {
        if (!static::can_review()) {
            wp_die(__('Du darfst diesen Membership-Abgleich nicht bearbeiten.', 'wg-membership-suite'), '', ['response' => 403]);
        }
        check_admin_referer($action);
        return true;
    }

    public static function configured_site_id() {
        $site_id = defined('WG_MEMBERSHIP_SUITE_PARITY_SITE_ID') ? WG_MEMBERSHIP_SUITE_PARITY_SITE_ID : '';
        return sanitize_key((string) apply_filters('wg_membership_suite_parity_site_id', $site_id));
    }

    public static function manifest_path() {
        $path = defined('WG_MEMBERSHIP_SUITE_PARITY_MANIFEST_PATH') ? WG_MEMBERSHIP_SUITE_PARITY_MANIFEST_PATH : '';
        return (string) apply_filters('wg_membership_suite_parity_manifest_path', $path);
    }

    public static function load_manifest() {
        $site_id = static::configured_site_id();
        if ($site_id === '') {
            return new WP_Error('parity_site_id_missing', __('Für diese Site ist keine Parity-Site-ID konfiguriert.', 'wg-membership-suite'));
        }
        $path = static::manifest_path();
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return new WP_Error('parity_manifest_missing', __('Das Parity-Manifest ist nicht verfügbar.', 'wg-membership-suite'));
        }
        $size = filesize($path);
        if ($size === false || $size > 2 * 1024 * 1024) {
            return new WP_Error('parity_manifest_size', __('Das Parity-Manifest ist zu groß.', 'wg-membership-suite'));
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return new WP_Error('parity_manifest_json', __('Das Parity-Manifest enthält kein gültiges JSON.', 'wg-membership-suite'));
        }
        return static::validate_manifest($decoded, $site_id);
    }

    public static function validate_manifest(array $manifest, $expected_site_id) {
        if (($manifest['schema_version'] ?? null) !== 1 || ($manifest['kind'] ?? '') !== 'membership_suite_parity_comparison') {
            return new WP_Error('parity_manifest_schema', __('Das Parity-Manifest verwendet ein unbekanntes Schema.', 'wg-membership-suite'));
        }
        if (sanitize_key((string) ($manifest['site_id'] ?? '')) !== $expected_site_id) {
            return new WP_Error('parity_manifest_site', __('Das Parity-Manifest gehört zu einer anderen Site.', 'wg-membership-suite'));
        }
        if (!preg_match('/^[a-f0-9]{64}$/', (string) ($manifest['baseline_hash'] ?? ''))) {
            return new WP_Error('parity_manifest_hash', __('Der Baseline-Hash ist ungültig.', 'wg-membership-suite'));
        }
        if (empty($manifest['baseline_version']) || empty($manifest['membership_suite_version']) || !isset($manifest['diffs']) || !is_array($manifest['diffs']) || count($manifest['diffs']) > 1000) {
            return new WP_Error('parity_manifest_fields', __('Das Parity-Manifest ist unvollständig.', 'wg-membership-suite'));
        }

        $diffs = [];
        $seen = [];
        foreach ($manifest['diffs'] as $diff) {
            if (!is_array($diff)) {
                return new WP_Error('parity_manifest_diff', __('Das Parity-Manifest enthält eine ungültige Abweichung.', 'wg-membership-suite'));
            }
            $diff_id = sanitize_key((string) ($diff['diff_id'] ?? ''));
            $classification = sanitize_key((string) ($diff['classification'] ?? ''));
            if (!preg_match('/^msd_[a-f0-9]{20}$/', $diff_id) || isset($seen[$diff_id])) {
                return new WP_Error('parity_manifest_diff_id', __('Das Parity-Manifest enthält eine ungültige oder doppelte Diff-ID.', 'wg-membership-suite'));
            }
            if (!in_array($classification, ['platform', 'expected_site_difference', 'unclear'], true)) {
                return new WP_Error('parity_manifest_classification', __('Das Parity-Manifest enthält eine unbekannte Diff-Klasse.', 'wg-membership-suite'));
            }
            $seen[$diff_id] = true;
            $diffs[] = [
                'diff_id' => $diff_id,
                'path' => sanitize_text_field((string) ($diff['path'] ?? '')),
                'classification' => $classification,
                'baseline_value' => static::sanitize_manifest_value($diff['baseline_value'] ?? null),
                'target_value' => static::sanitize_manifest_value($diff['target_value'] ?? null),
                'impact' => sanitize_text_field((string) ($diff['impact'] ?? '')),
                'evidence' => static::sanitize_manifest_value($diff['evidence'] ?? []),
            ];
        }

        return [
            'schema_version' => 1,
            'kind' => 'membership_suite_parity_comparison',
            'site_id' => $expected_site_id,
            'baseline_version' => sanitize_text_field((string) $manifest['baseline_version']),
            'baseline_hash' => (string) $manifest['baseline_hash'],
            'membership_suite_version' => sanitize_text_field((string) $manifest['membership_suite_version']),
            'diffs' => $diffs,
        ];
    }

    public static function get_review_store() {
        $stored = get_option(self::OPTION_KEY, []);
        return is_array($stored) ? $stored : [];
    }

    public static function save_reviews(array $submitted, array $manifest, $user_id = null, $decided_at = null) {
        $known = array_fill_keys(array_column($manifest['diffs'], 'diff_id'), true);
        $decisions = [];
        foreach ($submitted as $diff_id => $input) {
            $diff_id = sanitize_key((string) $diff_id);
            if (!isset($known[$diff_id]) || !is_array($input)) {
                continue;
            }
            $decision = sanitize_key((string) ($input['decision'] ?? ''));
            $reason = sanitize_textarea_field((string) ($input['reason'] ?? ''));
            if (!in_array($decision, ['sync', 'keep', 'other'], true)) {
                continue;
            }
            if (($decision === 'keep' || $decision === 'other') && $reason === '') {
                return new WP_Error('parity_reason_required', __('Für „Nein“ und „Andere Entscheidung“ ist eine Begründung erforderlich.', 'wg-membership-suite'));
            }
            $decisions[$diff_id] = [
                'diff_id' => $diff_id,
                'decision' => $decision,
                'reason' => $reason,
                'decided_at' => $decided_at ?: gmdate('c'),
                'decided_by' => $user_id === null ? get_current_user_id() : absint($user_id),
            ];
        }
        ksort($decisions);
        $store = [
            'schema_version' => 1,
            'site_id' => $manifest['site_id'],
            'baseline_version' => $manifest['baseline_version'],
            'baseline_hash' => $manifest['baseline_hash'],
            'membership_suite_version' => $manifest['membership_suite_version'],
            'decisions' => $decisions,
        ];
        update_option(self::OPTION_KEY, $store, false);
        return $store;
    }

    public static function build_export(array $manifest, array $store) {
        $current = ($store['site_id'] ?? '') === $manifest['site_id']
            && ($store['baseline_hash'] ?? '') === $manifest['baseline_hash']
            && ($store['membership_suite_version'] ?? '') === $manifest['membership_suite_version'];
        $known = array_fill_keys(array_column($manifest['diffs'], 'diff_id'), true);
        $decisions = [];
        if ($current) {
            foreach ((array) ($store['decisions'] ?? []) as $item) {
                if (!is_array($item) || !isset($known[$item['diff_id'] ?? ''])) {
                    continue;
                }
                $decision = sanitize_key((string) ($item['decision'] ?? ''));
                $reason = sanitize_textarea_field((string) ($item['reason'] ?? ''));
                if (!in_array($decision, ['sync', 'keep', 'other'], true) || (($decision === 'keep' || $decision === 'other') && $reason === '')) {
                    continue;
                }
                $decisions[] = [
                    'diff_id' => sanitize_key((string) $item['diff_id']),
                    'decision' => $decision,
                    'reason' => $reason,
                    'decided_at' => sanitize_text_field((string) ($item['decided_at'] ?? '')),
                    'decided_by' => absint($item['decided_by'] ?? 0),
                ];
            }
        }
        usort($decisions, static function ($left, $right) {
            return strcmp($left['diff_id'], $right['diff_id']);
        });
        $payload = [
            'schema_version' => 1,
            'kind' => 'membership_suite_parity_review_export',
            'site_id' => $manifest['site_id'],
            'baseline_version' => $manifest['baseline_version'],
            'baseline_hash' => $manifest['baseline_hash'],
            'membership_suite_version' => $manifest['membership_suite_version'],
            'decisions' => $decisions,
        ];
        $payload_hash = hash('sha256', static::canonical_json($payload));
        return array_merge($payload, [
            'generated_at' => gmdate('c'),
            'payload_hash' => $payload_hash,
            'signature_algorithm' => 'hmac-sha256',
            'signature' => hash_hmac('sha256', $payload_hash, wp_salt('auth')),
        ]);
    }

    public static function rest_export(WP_REST_Request $request) {
        $manifest = static::load_manifest();
        if (is_wp_error($manifest)) {
            return $manifest;
        }
        return new WP_REST_Response(static::build_export($manifest, static::get_review_store()), 200);
    }

    public static function handle_save() {
        static::authorize_admin_request(self::SAVE_ACTION);
        $manifest = static::load_manifest();
        if (is_wp_error($manifest)) {
            wp_die(esc_html($manifest->get_error_message()), '', ['response' => 400]);
        }
        $submitted = isset($_POST['reviews']) && is_array($_POST['reviews']) ? wp_unslash($_POST['reviews']) : [];
        $saved = static::save_reviews($submitted, $manifest);
        if (is_wp_error($saved)) {
            wp_die(esc_html($saved->get_error_message()), '', ['response' => 400]);
        }
        wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'updated' => '1'], admin_url('options-general.php')));
        exit;
    }

    public static function handle_export() {
        static::authorize_admin_request(self::EXPORT_ACTION);
        $manifest = static::load_manifest();
        if (is_wp_error($manifest)) {
            wp_die(esc_html($manifest->get_error_message()), '', ['response' => 400]);
        }
        $export = static::build_export($manifest, static::get_review_store());
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="membership-suite-parity-' . sanitize_file_name($manifest['site_id']) . '.json"');
        echo wp_json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function render_page() {
        if (!static::can_review()) {
            wp_die(__('Du darfst diesen Membership-Abgleich nicht sehen.', 'wg-membership-suite'), '', ['response' => 403]);
        }
        $manifest = static::load_manifest();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Membership Suite Abgleich', 'wg-membership-suite'); ?></h1>
            <?php if (is_wp_error($manifest)) : ?>
                <div class="notice notice-warning"><p><?php echo esc_html($manifest->get_error_message()); ?></p></div>
            <?php else :
                $store = static::get_review_store();
                $current = ($store['baseline_hash'] ?? '') === $manifest['baseline_hash'] && ($store['membership_suite_version'] ?? '') === $manifest['membership_suite_version'];
                $stored_decisions = $current ? (array) ($store['decisions'] ?? []) : [];
                $export_url = wp_nonce_url(admin_url('admin-post.php?action=' . self::EXPORT_ACTION), self::EXPORT_ACTION);
                ?>
                <p><?php echo esc_html(sprintf(__('Baseline %1$s · %2$d Abweichungen', 'wg-membership-suite'), $manifest['baseline_version'], count($manifest['diffs']))); ?></p>
                <?php if (!$current && !empty($store)) : ?><div class="notice notice-info"><p><?php esc_html_e('Frühere Entscheidungen sind nach dem Baseline- oder Plugin-Update veraltet und werden nicht vorgewählt.', 'wg-membership-suite'); ?></p></div><?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                    <?php wp_nonce_field(self::SAVE_ACTION); ?>
                    <div id="poststuff"><div id="post-body" class="metabox-holder columns-2">
                        <div id="post-body-content">
                            <?php foreach ($manifest['diffs'] as $diff) : ?>
                                <div class="postbox"><div class="postbox-header"><h2><?php echo esc_html($diff['path']); ?></h2></div><div class="inside">
                                    <p><code><?php echo esc_html($diff['diff_id']); ?></code> · <?php echo esc_html($diff['classification']); ?> · <?php echo esc_html($manifest['baseline_version']); ?></p>
                                    <p><?php echo esc_html($diff['impact']); ?></p>
                                    <p><strong><?php esc_html_e('Nachweis', 'wg-membership-suite'); ?>:</strong> <code><?php echo esc_html(wp_json_encode($diff['evidence'], JSON_UNESCAPED_SLASHES)); ?></code></p>
                                    <p><strong><?php esc_html_e('Ist', 'wg-membership-suite'); ?>:</strong> <code><?php echo esc_html(wp_json_encode($diff['target_value'], JSON_UNESCAPED_SLASHES)); ?></code></p>
                                    <p><strong><?php esc_html_e('Schablone', 'wg-membership-suite'); ?>:</strong> <code><?php echo esc_html(wp_json_encode($diff['baseline_value'], JSON_UNESCAPED_SLASHES)); ?></code></p>
                                </div></div>
                            <?php endforeach; ?>
                        </div>
                        <div id="postbox-container-1" class="postbox-container">
                            <div class="postbox"><div class="postbox-header"><h2><?php esc_html_e('Entscheidungen', 'wg-membership-suite'); ?></h2></div><div class="inside">
                                <p><?php esc_html_e('Das Panel speichert nur Deine Entscheidung. Es ändert keine Site.', 'wg-membership-suite'); ?></p>
                                <?php foreach ($manifest['diffs'] as $diff) :
                                    $saved = $stored_decisions[$diff['diff_id']] ?? [];
                                    $name = 'reviews[' . $diff['diff_id'] . ']';
                                    ?>
                                    <fieldset style="border-top:1px solid #dcdcde;padding:12px 0">
                                        <legend><code><?php echo esc_html($diff['diff_id']); ?></code></legend>
                                        <label><input type="radio" name="<?php echo esc_attr($name); ?>[decision]" value="sync" <?php checked(($saved['decision'] ?? '') === 'sync'); ?>> <?php esc_html_e('Ja – synchronisieren', 'wg-membership-suite'); ?></label><br>
                                        <label><input type="radio" name="<?php echo esc_attr($name); ?>[decision]" value="keep" <?php checked(($saved['decision'] ?? '') === 'keep'); ?>> <?php esc_html_e('Nein – bewusst behalten', 'wg-membership-suite'); ?></label><br>
                                        <label><input type="radio" name="<?php echo esc_attr($name); ?>[decision]" value="other" <?php checked(($saved['decision'] ?? '') === 'other'); ?>> <?php esc_html_e('Andere Entscheidung', 'wg-membership-suite'); ?></label>
                                        <textarea class="widefat" rows="2" name="<?php echo esc_attr($name); ?>[reason]" placeholder="<?php esc_attr_e('Begründung oder Klärpunkt', 'wg-membership-suite'); ?>"><?php echo esc_textarea($saved['reason'] ?? ''); ?></textarea>
                                    </fieldset>
                                <?php endforeach; ?>
                                <?php submit_button(__('Entscheidungen speichern', 'wg-membership-suite')); ?>
                                <p><a class="button" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Signierten Export laden', 'wg-membership-suite'); ?></a></p>
                            </div></div>
                        </div>
                    </div></div>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function canonical_json($value) {
        return wp_json_encode(static::canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function canonicalize($value) {
        if (!is_array($value)) {
            return $value;
        }
        $is_list = array_keys($value) === range(0, count($value) - 1);
        if ($is_list) {
            $normalized = array_map([static::class, 'canonicalize'], $value);
            usort($normalized, static function ($left, $right) {
                return strcmp(wp_json_encode($left), wp_json_encode($right));
            });
            return $normalized;
        }
        ksort($value);
        foreach ($value as $key => $child) {
            $value[$key] = static::canonicalize($child);
        }
        return $value;
    }

    private static function sanitize_manifest_value($value, $depth = 0) {
        if ($depth > 8) {
            return '[depth-limited]';
        }
        if (is_array($value)) {
            $sanitized = [];
            foreach (array_slice($value, 0, 250, true) as $key => $child) {
                $safe_key = is_int($key) ? $key : sanitize_key((string) $key);
                if (preg_match('/credential|secret|password_value|token_value|private_key|auth_key/i', (string) $safe_key)) {
                    continue;
                }
                $sanitized[$safe_key] = static::sanitize_manifest_value($child, $depth + 1);
            }
            return $sanitized;
        }
        if (is_string($value)) {
            if (strpos($value, '-----BEGIN') !== false && strpos($value, 'PRIVATE KEY-----') !== false) {
                return '[redacted]';
            }
            return sanitize_text_field(substr($value, 0, 1000));
        }
        return is_scalar($value) || $value === null ? $value : null;
    }
}
