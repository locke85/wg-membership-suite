<?php

namespace wg\membership\Finance;

use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

class CLI {
    public static function register() {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('wg-finance memberpress-import', [static::class, 'memberpress_import']);
            \WP_CLI::add_command('wg-finance memberpress-analyze', [static::class, 'memberpress_analyze']);
        }
    }

    /**
     * Validate and import a read-only MemberPress export.
     *
     * ## OPTIONS
     *
     * --source-dir=<directory>
     * : Directory containing memberships.json, subscriptions.json, transactions.json and members.json.
     *
     * [--manifest=<file>]
     * : Optional migration manifest JSON.
     *
     * [--apply]
     * : Persist append-only archive objects. Without this flag the command is a dry run.
     */
    public static function memberpress_import($args, $assoc_args) {
        try {
            $directory = realpath((string) ($assoc_args['source-dir'] ?? ''));
            if (!$directory || !is_dir($directory)) {
                throw new RuntimeException('--source-dir muss auf ein lesbares Exportverzeichnis zeigen.');
            }
            $export = [];
            foreach (['memberships', 'subscriptions', 'transactions', 'members'] as $name) {
                $export[$name] = self::read_json($directory . DIRECTORY_SEPARATOR . $name . '.json');
            }
            $documents_file = $directory . DIRECTORY_SEPARATOR . 'documents.json';
            $export['documents'] = is_file($documents_file) ? self::read_json($documents_file) : [];
            $events_file = $directory . DIRECTORY_SEPARATOR . 'events.json';
            $export['events'] = is_file($events_file) ? self::read_json($events_file) : [];
            $manifest = [];
            if (!empty($assoc_args['manifest'])) {
                $manifest_path = realpath((string) $assoc_args['manifest']);
                if (!$manifest_path || !is_file($manifest_path)) {
                    throw new RuntimeException('--manifest ist nicht lesbar.');
                }
                $manifest = self::read_json($manifest_path);
            }
            $apply = isset($assoc_args['apply']);
            if ($apply && !current_user_can(\wg\membership\Config::IMPORT_FINANCE_ARCHIVE_CAPABILITY)) {
                throw new RuntimeException('Dem aktuellen WordPress-Nutzer fehlt die Import-Capability.');
            }
            $result = (new MemberPress_Importer(!$apply))->run($export, $manifest);
            \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            \WP_CLI::success($apply ? 'Append-only Import abgeschlossen.' : 'Dry-run abgeschlossen; keine Zielobjekte geschrieben.');
        } catch (\Throwable $error) {
            \WP_CLI::error($error->getMessage());
        }
    }

    /**
     * Build an anonymized aggregate report from a MemberPress export without writes.
     *
     * ## OPTIONS
     *
     * --source-dir=<directory>
     * : Directory containing the MemberPress JSON export.
     *
     * [--manifest=<file>]
     * : Optional manifest with an approved default currency.
     */
    public static function memberpress_analyze($args, $assoc_args) {
        try {
            $directory = realpath((string) ($assoc_args['source-dir'] ?? ''));
            if (!$directory || !is_dir($directory)) {
                throw new RuntimeException('--source-dir muss auf ein lesbares Exportverzeichnis zeigen.');
            }
            $export = [];
            foreach (['memberships', 'subscriptions', 'transactions', 'members'] as $name) {
                $export[$name] = self::read_json($directory . DIRECTORY_SEPARATOR . $name . '.json');
            }
            $events_file = $directory . DIRECTORY_SEPARATOR . 'events.json';
            $export['events'] = is_file($events_file) ? self::read_json($events_file) : [];
            $manifest = [];
            if (!empty($assoc_args['manifest'])) {
                $manifest_path = realpath((string) $assoc_args['manifest']);
                if (!$manifest_path || !is_file($manifest_path)) {
                    throw new RuntimeException('--manifest ist nicht lesbar.');
                }
                $manifest = self::read_json($manifest_path);
            }
            $currency = !empty($manifest['currency_approved']) ? ($manifest['default_currency'] ?? 'XXX') : 'XXX';
            $report = Historical_Analytics::from_memberpress_export($export, $currency);
            $safe_report = [
                'definition_version' => $report['definition_version'],
                'generated_at' => $report['generated_at'],
                'data_state' => $report['data_state'],
                'summary' => $report['summary'],
                'annual' => $report['annual'],
                'products' => $report['products'],
                'bundle_candidates' => $report['bundle_candidates'],
                'warnings' => $report['warnings'],
            ];
            \WP_CLI::line(wp_json_encode($safe_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            \WP_CLI::success('Anonymisierte historische Analyse erstellt; keine Zielobjekte geschrieben.');
        } catch (\Throwable $error) {
            \WP_CLI::error($error->getMessage());
        }
    }

    private static function read_json($file) {
        $raw = file_get_contents($file);
        $data = json_decode($raw, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Ungueltiges JSON: ' . basename($file));
        }
        return $data;
    }
}
