<?php

namespace wg\membership\Admin;

use wg\membership\Config;
use wg\membership\Finance\Archive;
use wg\membership\Finance\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Historical_Orders_Admin {
    const SLUG = 'wg-historical-orders';
    const PER_PAGE = 50;

    public static function register() {
        add_action('admin_menu', [static::class, 'menu'], 20);
        add_action('admin_post_wg_historical_orders_csv', [static::class, 'export_csv']);
    }

    public static function menu() {
        add_submenu_page(
            'edit.php?post_type=wg_bestellung',
            __('Historische Bestellungen', 'wg-membership-suite'),
            __('Historische Bestellungen', 'wg-membership-suite'),
            Config::VIEW_FINANCE_ARCHIVE_CAPABILITY,
            static::SLUG,
            [static::class, 'render']
        );

        global $submenu;
        $parent = 'edit.php?post_type=wg_bestellung';
        if (!empty($submenu[$parent])) {
            foreach ($submenu[$parent] as &$item) {
                if (($item[2] ?? '') === $parent) {
                    $item[0] = __('Aktuelle Bestellungen', 'wg-membership-suite');
                    break;
                }
            }
            unset($item);
        }
    }

    public static function render() {
        static::authorize();
        $filters = static::filters_from_request($_GET);
        $page = max(1, absint($_GET['paged'] ?? 1));
        $result = static::query($filters, $page, static::PER_PAGE);

        Archive::audit('historical_orders_viewed', 'success', [
            'filtered' => (bool) array_filter($filters),
            'result_count' => (int) $result['total'],
        ]);

        echo '<div class="wrap"><h1>' . esc_html__('Historische Bestellungen', 'wg-membership-suite') . '</h1>';
        echo '<p>' . esc_html__('Read-only MemberPress-Archiv. Diese Datensätze lösen keine Bestell-, E-Mail-, Rollen-, Zahlungs- oder Finom-Prozesse aus.', 'wg-membership-suite') . '</p>';
        static::render_navigation();
        static::render_filters($filters);
        static::render_table($result['rows']);
        static::render_pagination($result['total'], $page, static::PER_PAGE, $filters);
        echo '</div>';
    }

    public static function export_csv() {
        static::authorize();
        check_admin_referer('wg_historical_orders_csv');
        $filters = static::filters_from_request($_GET);
        $result = static::query($filters, 1, 0);

        Archive::audit('historical_orders_exported', 'success', [
            'format' => 'csv',
            'row_count' => (int) $result['total'],
            'filtered' => (bool) array_filter($filters),
        ]);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="historische-bestellungen-' . gmdate('Ymd-His') . '.csv"');
        echo "\xEF\xBB\xBF";
        $stream = fopen('php://output', 'w');
        fputcsv($stream, [
            'Quellbestellung', 'Rechnungsnummer', 'Datum', 'Kunde', 'Produkt',
            'Status', 'Netto', 'USt.', 'Brutto', 'Waehrung', 'Archivdetail',
        ], ';', '"', '');
        foreach ($result['rows'] as $row) {
            $display = static::display_values($row);
            fputcsv($stream, array_map([static::class, 'csv_cell'], [
                'MemberPress #' . $row['source_id'],
                $row['invoice_number'],
                $row['occurred_at'],
                $display['customer'],
                $display['product'],
                $row['status'],
                $row['amount_net'],
                $row['amount_tax'],
                $row['amount_gross'],
                $row['currency'],
                static::detail_url((int) $row['id']),
            ]), ';', '"', '');
        }
        fclose($stream);
        exit;
    }

    private static function render_navigation() {
        echo '<nav class="nav-tab-wrapper">';
        echo '<a class="nav-tab" href="' . esc_url(admin_url('edit.php?post_type=wg_bestellung')) . '">' . esc_html__('Aktuelle Bestellungen', 'wg-membership-suite') . '</a>';
        echo '<a class="nav-tab nav-tab-active" href="' . esc_url(static::page_url()) . '">' . esc_html__('Historische Bestellungen', 'wg-membership-suite') . '</a>';
        echo '<a class="nav-tab" href="' . esc_url(admin_url('tools.php?page=wg-finance-analytics')) . '">' . esc_html__('Membership-Reports', 'wg-membership-suite') . '</a>';
        echo '</nav>';
    }

    private static function render_filters(array $filters) {
        $years = static::available_values('year');
        $statuses = static::available_values('status');
        $export_args = array_merge(['action' => 'wg_historical_orders_csv'], array_filter($filters, 'strlen'));
        $export_url = wp_nonce_url(add_query_arg($export_args, admin_url('admin-post.php')), 'wg_historical_orders_csv');

        echo '<form method="get" style="margin:16px 0;padding:12px;background:#fff;border:1px solid #ccd0d4">';
        echo '<input type="hidden" name="post_type" value="wg_bestellung"><input type="hidden" name="page" value="' . esc_attr(static::SLUG) . '">';
        echo '<p><label>' . esc_html__('Bestellung oder Rechnung', 'wg-membership-suite') . '<br><input name="order" value="' . esc_attr($filters['order']) . '" placeholder="Quell-ID oder Rechnungsnummer"></label> ';
        echo '<label>' . esc_html__('Kunde', 'wg-membership-suite') . '<br><input name="customer" value="' . esc_attr($filters['customer']) . '" placeholder="Name oder E-Mail"></label> ';
        echo '<label>' . esc_html__('Produkt', 'wg-membership-suite') . '<br><input name="product" value="' . esc_attr($filters['product']) . '" placeholder="Produkt oder Erweiterung"></label> ';
        echo '<label>' . esc_html__('Jahr', 'wg-membership-suite') . '<br><select name="year"><option value="">Alle</option>';
        foreach ($years as $year) {
            echo '<option value="' . esc_attr($year) . '"' . selected($filters['year'], $year, false) . '>' . esc_html($year) . '</option>';
        }
        echo '</select></label> ';
        echo '<label>' . esc_html__('Status', 'wg-membership-suite') . '<br><select name="status"><option value="">Alle</option>';
        foreach ($statuses as $status) {
            echo '<option value="' . esc_attr($status) . '"' . selected($filters['status'], $status, false) . '>' . esc_html($status) . '</option>';
        }
        echo '</select></label> ';
        echo '<label>' . esc_html__('Von', 'wg-membership-suite') . '<br><input type="date" name="from" value="' . esc_attr($filters['from']) . '"></label> ';
        echo '<label>' . esc_html__('Bis', 'wg-membership-suite') . '<br><input type="date" name="to" value="' . esc_attr($filters['to']) . '"></label></p>';
        submit_button(__('Filtern', 'wg-membership-suite'), 'primary', '', false);
        echo ' <a class="button" href="' . esc_url(static::page_url()) . '">' . esc_html__('Zurücksetzen', 'wg-membership-suite') . '</a>';
        echo ' <a class="button" href="' . esc_url($export_url) . '">' . esc_html__('Gefilterte Liste als CSV exportieren', 'wg-membership-suite') . '</a>';
        echo '</form>';
    }

    private static function render_table(array $rows) {
        echo '<table class="widefat striped"><thead><tr><th>Bestellung</th><th>Rechnung</th><th>Datum</th><th>Kunde</th><th>Produkt</th><th>Status</th><th>Netto</th><th>USt.</th><th>Brutto</th><th>Quelle</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $display = static::display_values($row);
            $url = static::detail_url((int) $row['id']);
            echo '<tr><td><a href="' . esc_url($url) . '">' . esc_html('MemberPress #' . $row['source_id']) . '</a></td>';
            echo '<td>' . esc_html($row['invoice_number'] ?: '–') . '</td><td>' . esc_html($row['occurred_at'] ?: '–') . '</td>';
            echo '<td>' . esc_html($display['customer']) . '</td><td>' . esc_html($display['product']) . '</td><td>' . esc_html($row['status']) . '</td>';
            echo '<td>' . esc_html(static::money($row['amount_net'], $row['currency'])) . '</td><td>' . esc_html(static::money($row['amount_tax'], $row['currency'])) . '</td><td>' . esc_html(static::money($row['amount_gross'], $row['currency'])) . '</td>';
            echo '<td><span aria-label="Nur lesen">Archiv · nur lesen</span></td></tr>';
        }
        if (!$rows) {
            echo '<tr><td colspan="10">' . esc_html__('Keine historischen Bestellungen gefunden.', 'wg-membership-suite') . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_pagination($total, $page, $per_page, array $filters) {
        $pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;
        if ($pages <= 1) {
            echo '<p>' . esc_html(sprintf(_n('%d historische Bestellung', '%d historische Bestellungen', $total, 'wg-membership-suite'), $total)) . '</p>';
            return;
        }
        $base_args = array_merge(['post_type' => 'wg_bestellung', 'page' => static::SLUG, 'paged' => '%#%'], array_filter($filters, 'strlen'));
        echo '<div class="tablenav"><div class="tablenav-pages"><span class="displaying-num">' . esc_html(sprintf(__('%d Einträge', 'wg-membership-suite'), $total)) . '</span> ';
        echo wp_kses_post(paginate_links([
            'base' => add_query_arg($base_args, admin_url('edit.php')),
            'format' => '',
            'current' => $page,
            'total' => $pages,
            'type' => 'plain',
        ]));
        echo '</div></div>';
    }

    private static function filters_from_request(array $request) {
        $year = sanitize_text_field(wp_unslash((string) ($request['year'] ?? '')));
        return [
            'order' => sanitize_text_field(wp_unslash((string) ($request['order'] ?? ''))),
            'customer' => sanitize_text_field(wp_unslash((string) ($request['customer'] ?? ''))),
            'product' => sanitize_text_field(wp_unslash((string) ($request['product'] ?? ''))),
            'year' => preg_match('/^20\d{2}$/', $year) ? $year : '',
            'status' => sanitize_key(wp_unslash((string) ($request['status'] ?? ''))),
            'from' => static::valid_date($request['from'] ?? ''),
            'to' => static::valid_date($request['to'] ?? ''),
        ];
    }

    private static function query(array $filters, $page, $per_page) {
        global $wpdb;
        $objects = Schema::table('objects');
        $where = ["newer.id IS NULL", "o.object_type = 'order'"];
        $params = [];

        if ($filters['order'] !== '') {
            $like = '%' . $wpdb->esc_like($filters['order']) . '%';
            $where[] = '(o.source_id LIKE %s OR o.invoice_number LIKE %s)';
            array_push($params, $like, $like);
        }
        if ($filters['customer'] !== '') {
            $like = '%' . $wpdb->esc_like($filters['customer']) . '%';
            $where[] = '(u.display_name LIKE %s OR u.user_email LIKE %s OR o.snapshot_json LIKE %s)';
            array_push($params, $like, $like, $like);
        }
        if ($filters['product'] !== '') {
            $where[] = 'o.snapshot_json LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['product']) . '%';
        }
        if ($filters['year'] !== '') {
            $where[] = 'o.occurred_at >= %s AND o.occurred_at < %s';
            $params[] = $filters['year'] . '-01-01 00:00:00';
            $params[] = ((int) $filters['year'] + 1) . '-01-01 00:00:00';
        }
        if ($filters['status'] !== '') {
            $where[] = 'o.status = %s';
            $params[] = $filters['status'];
        }
        if ($filters['from'] !== '') {
            $where[] = 'o.occurred_at >= %s';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if ($filters['to'] !== '') {
            $where[] = 'o.occurred_at <= %s';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        $from = " FROM {$objects} o LEFT JOIN {$objects} newer ON newer.logical_id=o.logical_id AND newer.version>o.version LEFT JOIN {$wpdb->users} u ON u.ID=o.customer_user_id WHERE " . implode(' AND ', $where);
        $count_sql = 'SELECT COUNT(*)' . $from;
        $total = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, $params) : $count_sql);
        $rows_sql = 'SELECT o.id,o.source_id,o.invoice_number,o.occurred_at,o.status,o.amount_net,o.amount_tax,o.amount_gross,o.currency,o.snapshot_json,u.display_name,u.user_email' . $from . ' ORDER BY o.occurred_at DESC,o.id DESC';
        if ($per_page > 0) {
            $rows_sql .= ' LIMIT ' . absint($per_page) . ' OFFSET ' . absint(($page - 1) * $per_page);
        }
        $rows = $wpdb->get_results($params ? $wpdb->prepare($rows_sql, $params) : $rows_sql, ARRAY_A);
        return ['total' => $total, 'rows' => (array) $rows];
    }

    private static function available_values($type) {
        global $wpdb;
        $objects = Schema::table('objects');
        $field = $type === 'year' ? 'YEAR(o.occurred_at)' : 'o.status';
        $sql = "SELECT DISTINCT {$field} AS value FROM {$objects} o LEFT JOIN {$objects} newer ON newer.logical_id=o.logical_id AND newer.version>o.version WHERE newer.id IS NULL AND o.object_type='order' AND {$field} IS NOT NULL AND {$field}<>'' ORDER BY value DESC";
        return array_values(array_filter(array_map('strval', (array) $wpdb->get_col($sql))));
    }

    private static function display_values(array $row) {
        $snapshot = json_decode((string) $row['snapshot_json'], true);
        $snapshot = is_array($snapshot) ? $snapshot : [];
        $member = is_array($snapshot['member'] ?? null) ? $snapshot['member'] : [];
        $membership = is_array($snapshot['membership'] ?? null) ? $snapshot['membership'] : [];
        $customer = trim((string) ($row['display_name'] ?? ''));
        if ($customer === '') {
            $customer = trim((string) ($member['display_name'] ?? ''));
        }
        if ($customer === '') {
            $customer = trim((string) ($row['user_email'] ?? $member['email'] ?? ''));
        }
        return [
            'customer' => $customer !== '' ? $customer : __('Nicht zugeordnet', 'wg-membership-suite'),
            'product' => trim((string) ($membership['title'] ?? '')) ?: __('Unbekanntes historisches Produkt', 'wg-membership-suite'),
        ];
    }

    private static function money($amount, $currency) {
        return $amount === null || $amount === '' ? '–' : number_format_i18n((float) $amount, 2) . ' ' . $currency;
    }

    private static function csv_cell($value) {
        $value = (string) $value;
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    private static function detail_url($object_id) {
        return add_query_arg(['page' => 'wg-finance-archive', 'object_id' => $object_id], admin_url('tools.php'));
    }

    private static function page_url() {
        return add_query_arg(['post_type' => 'wg_bestellung', 'page' => static::SLUG], admin_url('edit.php'));
    }

    private static function valid_date($value) {
        $value = sanitize_text_field(wp_unslash((string) $value));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    private static function authorize() {
        if (!current_user_can(Config::VIEW_FINANCE_ARCHIVE_CAPABILITY)) {
            wp_die(esc_html__('Keine Berechtigung für historische Bestellungen.', 'wg-membership-suite'), '', ['response' => 403]);
        }
    }
}
