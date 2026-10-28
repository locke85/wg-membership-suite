<?php

namespace wg\membership\Finance;

use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_API {
    public static function register() {
        add_action('rest_api_init', [static::class, 'routes']);
    }

    public static function routes() {
        register_rest_route('wg-membership/v1', '/finance-import', [
            'methods' => 'POST',
            'callback' => [static::class, 'import'],
            'permission_callback' => function () {
                return current_user_can(\wg\membership\Config::IMPORT_FINANCE_ARCHIVE_CAPABILITY);
            },
        ]);
    }

    public static function import(WP_REST_Request $request) {
        try {
            $payload = $request->get_json_params();
            if (!is_array($payload) || !is_array($payload['export'] ?? null) || !is_array($payload['manifest'] ?? null)) {
                throw new RuntimeException('Export und Manifest muessen als JSON-Objekte uebergeben werden.');
            }
            $manifest = $payload['manifest'];
            $expected_hash = strtolower((string) ($manifest['source_export_sha256'] ?? ''));
            $confirmed_hash = strtolower(trim((string) $request->get_header('X-WG-Migration-Confirm')));
            if (!preg_match('/^[a-f0-9]{64}$/', $expected_hash) || !hash_equals($expected_hash, $confirmed_hash)) {
                throw new RuntimeException('Der bestaetigte Exporthash fehlt oder stimmt nicht.');
            }
            $apply = !empty($payload['apply']);
            $result = (new MemberPress_Importer(!$apply))->run($payload['export'], $manifest);
            return new WP_REST_Response([
                'success' => true,
                'result' => $result,
            ], $apply ? 201 : 200);
        } catch (\Throwable $error) {
            return new WP_Error('wg_finance_import_rejected', $error->getMessage(), ['status' => 409]);
        }
    }
}
