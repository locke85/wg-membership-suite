<?php

namespace wg\membership\Admin;

use wg\membership\Config;
use wg\membership\Finance\Archive;
use wg\membership\Finance\Reprint_PDF;
use wg\membership\Finance\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Finance_Archive_Admin {
    public static function register() {
        add_action('admin_menu', [static::class, 'menu']);
        add_action('admin_post_wg_finance_original', [static::class, 'download_original']);
        add_action('admin_post_wg_finance_reprint', [static::class, 'download_reprint']);
    }

    public static function menu() {
        add_management_page(
            __('Finanzarchiv', 'wg-membership-suite'),
            __('Finanzarchiv', 'wg-membership-suite'),
            Config::VIEW_FINANCE_ARCHIVE_CAPABILITY,
            'wg-finance-archive',
            [static::class, 'render']
        );
    }

    public static function render() {
        if (!current_user_can(Config::VIEW_FINANCE_ARCHIVE_CAPABILITY)) {
            wp_die(esc_html__('Kein Zugriff auf das Finanzarchiv.', 'wg-membership-suite'), '', ['response' => 403]);
        }
        $object_id = isset($_GET['object_id']) ? absint($_GET['object_id']) : 0;
        echo '<div class="wrap"><h1>' . esc_html__('Finanzarchiv', 'wg-membership-suite') . '</h1>';
        echo '<p><a class="button" href="' . esc_url(admin_url('tools.php?page=wg-finance-analytics')) . '">' . esc_html__('Historische Membership-Analysen', 'wg-membership-suite') . '</a></p>';
        if ($object_id) {
            static::render_detail($object_id);
        } else {
            static::render_search();
        }
        echo '</div>';
    }

    private static function render_search() {
        global $wpdb;
        $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        $from = static::valid_date($_GET['from'] ?? '');
        $to = static::valid_date($_GET['to'] ?? '');
        $objects = Schema::table('objects');
        $where = ['newer.id IS NULL'];
        $params = [];
        if ($search !== '') {
            $where[] = '(o.invoice_number LIKE %s OR o.source_id LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($from) {
            $where[] = 'o.occurred_at >= %s';
            $params[] = $from . ' 00:00:00';
        }
        if ($to) {
            $where[] = 'o.occurred_at <= %s';
            $params[] = $to . ' 23:59:59';
        }
        $sql = "SELECT o.id,o.object_type,o.source_id,o.invoice_number,o.status,o.occurred_at,o.amount_gross,o.currency,o.original_sha256,u.display_name
            FROM {$objects} o
            LEFT JOIN {$objects} newer ON newer.logical_id=o.logical_id AND newer.version>o.version
            LEFT JOIN {$wpdb->users} u ON u.ID=o.customer_user_id
            WHERE " . implode(' AND ', $where) . ' ORDER BY o.occurred_at DESC,o.id DESC LIMIT 200';
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        echo '<form method="get"><input type="hidden" name="page" value="wg-finance-archive">';
        echo '<p class="search-box"><label class="screen-reader-text" for="wg-finance-search">' . esc_html__('Finanzarchiv durchsuchen', 'wg-membership-suite') . '</label>';
        echo '<input id="wg-finance-search" name="s" value="' . esc_attr($search) . '" placeholder="Rechnungsnummer, Kunde oder Quell-ID"> ';
        echo '<input type="date" name="from" value="' . esc_attr($from) . '"> <input type="date" name="to" value="' . esc_attr($to) . '"> ';
        submit_button(__('Suchen', 'wg-membership-suite'), 'secondary', '', false);
        echo '</p></form>';
        echo '<table class="widefat striped"><thead><tr><th>Typ</th><th>Datum</th><th>Rechnung</th><th>Kunde</th><th>Status</th><th>Brutto</th><th>Nachweis</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $url = add_query_arg(['page' => 'wg-finance-archive', 'object_id' => (int) $row['id']], admin_url('tools.php'));
            echo '<tr><td><a href="' . esc_url($url) . '">' . esc_html($row['object_type']) . '</a></td><td>' . esc_html($row['occurred_at']) . '</td><td>' . esc_html($row['invoice_number']) . '</td><td>' . esc_html($row['display_name'] ?: 'Review') . '</td><td>' . esc_html($row['status']) . '</td><td>' . esc_html($row['amount_gross'] . ' ' . $row['currency']) . '</td><td>' . esc_html($row['original_sha256'] ? 'Original' : 'Snapshot') . '</td></tr>';
        }
        if (!$rows) {
            echo '<tr><td colspan="7">' . esc_html__('Keine Finanzobjekte gefunden.', 'wg-membership-suite') . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_detail($object_id) {
        $object = Archive::get_object($object_id);
        if (!$object) {
            wp_die(esc_html__('Finanzobjekt nicht gefunden.', 'wg-membership-suite'), '', ['response' => 404]);
        }
        Archive::audit('object_viewed', 'success', [], (int) $object['id'], (string) $object['object_hash'], (int) $object['batch_id']);
        echo '<p><a href="' . esc_url(admin_url('tools.php?page=wg-finance-archive')) . '">&larr; ' . esc_html__('Zur Suche', 'wg-membership-suite') . '</a></p>';
        echo '<h2>' . esc_html($object['object_type'] . ' / ' . $object['source_id']) . '</h2>';
        echo '<dl><dt>Rechnungsnummer</dt><dd>' . esc_html($object['invoice_number']) . '</dd><dt>Datenstand</dt><dd><code>' . esc_html($object['object_hash']) . '</code></dd><dt>Aufbewahrung</dt><dd>' . esc_html($object['document_class'] . '; frueheste Aussonderung ' . $object['earliest_disposal_on'] . '; ' . $object['legal_basis']) . '</dd><dt>Sperrgrund</dt><dd>' . esc_html($object['hold_reason'] ?: 'keiner hinterlegt') . '</dd></dl>';
        $original = Archive::resolve_original_path($object);
        if ($original) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=wg_finance_original&object_id=' . (int) $object['id']), 'wg_finance_original_' . (int) $object['id']);
            echo '<p><a class="button button-primary" href="' . esc_url($url) . '">' . esc_html__('Unverändertes Original herunterladen', 'wg-membership-suite') . '</a></p>';
        } elseif (current_user_can(Config::REPRINT_FINANCE_ARCHIVE_CAPABILITY) && Reprint_PDF::is_eligible($object)) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=wg_finance_reprint&object_id=' . (int) $object['id']), 'wg_finance_reprint_' . (int) $object['id']);
            echo '<p><a class="button" href="' . esc_url($url) . '">' . esc_html__('Gekennzeichnetes Mehrstück erzeugen', 'wg-membership-suite') . '</a></p>';
        } else {
            echo '<p><strong>' . esc_html__('Kein freigegebenes Original oder vollständiger Reprint-Datensatz vorhanden.', 'wg-membership-suite') . '</strong></p>';
        }
        $snapshot = json_decode((string) $object['snapshot_json'], true);
        echo '<h3>' . esc_html__('Archivierter strukturierter Datenstand', 'wg-membership-suite') . '</h3><pre style="white-space:pre-wrap">' . esc_html(wp_json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
    }

    public static function download_original() {
        $object = static::authorize_download(Config::VIEW_FINANCE_ARCHIVE_CAPABILITY, 'wg_finance_original');
        $file = Archive::resolve_original_path($object);
        if (!$file) {
            Archive::audit('original_downloaded', 'failed', ['reason' => 'missing_or_hash_mismatch'], (int) $object['id'], (string) $object['object_hash'], (int) $object['batch_id']);
            wp_die(esc_html__('Original fehlt oder die Prüfsumme stimmt nicht.', 'wg-membership-suite'), '', ['response' => 409]);
        }
        Archive::audit('original_downloaded', 'success', ['document_sha256' => $object['original_sha256']], (int) $object['id'], (string) $object['object_hash'], (int) $object['batch_id']);
        static::send_file($file, basename($file));
    }

    public static function download_reprint() {
        $object = static::authorize_download(Config::REPRINT_FINANCE_ARCHIVE_CAPABILITY, 'wg_finance_reprint');
        if (Archive::resolve_original_path($object)) {
            wp_die(esc_html__('Für diesen Datensatz existiert ein unverändertes Original.', 'wg-membership-suite'), '', ['response' => 409]);
        }
        try {
            $pdf = Reprint_PDF::render($object);
            Archive::audit('reprint_generated', 'success', ['template_version' => Reprint_PDF::TEMPLATE_VERSION, 'pdf_sha256' => hash('sha256', $pdf)], (int) $object['id'], (string) $object['object_hash'], (int) $object['batch_id']);
            nocache_headers();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="Rechnungskopie-' . sanitize_file_name($object['invoice_number']) . '.pdf"');
            header('Content-Length: ' . strlen($pdf));
            echo $pdf;
            exit;
        } catch (\Throwable $error) {
            Archive::audit('reprint_generated', 'failed', ['reason' => 'incomplete_snapshot'], (int) $object['id'], (string) $object['object_hash'], (int) $object['batch_id']);
            wp_die(esc_html($error->getMessage()), '', ['response' => 409]);
        }
    }

    private static function authorize_download($capability, $nonce_action) {
        if (!current_user_can($capability)) {
            wp_die(esc_html__('Keine Berechtigung.', 'wg-membership-suite'), '', ['response' => 403]);
        }
        $object_id = absint($_GET['object_id'] ?? 0);
        check_admin_referer($nonce_action . '_' . $object_id);
        $object = Archive::get_object($object_id);
        if (!$object) {
            wp_die(esc_html__('Finanzobjekt nicht gefunden.', 'wg-membership-suite'), '', ['response' => 404]);
        }
        return $object;
    }

    private static function send_file($file, $name) {
        nocache_headers();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($name) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    private static function valid_date($value) {
        $value = sanitize_text_field(wp_unslash((string) $value));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
}
