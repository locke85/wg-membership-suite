<?php

namespace wg\membership\Finance;

if (!defined('ABSPATH')) {
    exit;
}

class Archive {
    public static function canonical_json($value) {
        $value = self::canonicalize($value);
        return wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function canonicalize($value) {
        if (!is_array($value)) {
            return $value;
        }
        if (array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }
        return $value;
    }

    public static function hash($value) {
        return hash('sha256', self::canonical_json($value));
    }

    public static function uuid() {
        return function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : sprintf(
            '%08x-%04x-4%03x-%04x-%012x',
            mt_rand(), mt_rand(0, 0xffff), mt_rand(0, 0x0fff),
            mt_rand(0, 0x3fff) | 0x8000, mt_rand()
        );
    }

    public static function audit($event_type, $result, array $details = [], $object_id = 0, $object_hash = '', $batch_id = 0) {
        global $wpdb;
        $wpdb->insert(Schema::table('audit'), [
            'event_uuid' => self::uuid(),
            'event_type' => sanitize_key($event_type),
            'object_id' => absint($object_id),
            'object_hash' => preg_match('/^[a-f0-9]{64}$/', $object_hash) ? $object_hash : '',
            'batch_id' => absint($batch_id),
            'actor_user_id' => get_current_user_id(),
            'result' => sanitize_key($result),
            'detail_json' => self::canonical_json($details),
            'created_at' => current_time('mysql', true),
        ]);
    }

    public static function latest_object($source_system, $object_type, $source_id) {
        global $wpdb;
        $table = Schema::table('objects');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE source_system=%s AND object_type=%s AND source_id=%s ORDER BY version DESC LIMIT 1",
            $source_system,
            $object_type,
            $source_id
        ), ARRAY_A);
    }

    public static function get_object($id) {
        global $wpdb;
        $table = Schema::table('objects');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", absint($id)), ARRAY_A);
    }

    public static function resolve_original_path(array $object) {
        if (!defined('WG_FINANCE_ARCHIVE_ROOT') || trim((string) WG_FINANCE_ARCHIVE_ROOT) === '') {
            return '';
        }
        $root = realpath((string) WG_FINANCE_ARCHIVE_ROOT);
        $relative = ltrim(str_replace('\\', '/', (string) ($object['original_relpath'] ?? '')), '/');
        if (!$root || $relative === '' || strpos($relative, '../') !== false) {
            return '';
        }
        $candidate = realpath($root . DIRECTORY_SEPARATOR . $relative);
        if (!$candidate || strpos($candidate, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($candidate)) {
            return '';
        }
        $expected = (string) ($object['original_sha256'] ?? '');
        return $expected !== '' && hash_file('sha256', $candidate) === $expected ? $candidate : '';
    }
}
