<?php

namespace wg\membership\Admin;

use wg\membership\Config;
use wg\membership\Finance\Archive;
use wg\membership\Finance\Historical_Analytics;
use wg\membership\Finance\Current_Order_Projection;
use wg\membership\Finance\Finom_Historical_Summary;
use wg\membership\Finance\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Finance_Analytics_Admin {
    public static function register() {
        add_action('admin_menu', [static::class, 'menu']);
        add_action('admin_post_wg_finance_analytics_export', [static::class, 'export']);
    }

    public static function menu() {
        add_management_page(
            __('Historische Membership-Analysen', 'wg-membership-suite'),
            __('Membership-Analysen', 'wg-membership-suite'),
            'manage_options',
            'wg-finance-analytics',
            [static::class, 'render']
        );
    }

    public static function render() {
        Analytics_Access_Gate::require_admin();
        $unlock_error = Analytics_Access_Gate::process_unlock();
        if (!Analytics_Access_Gate::is_unlocked()) {
            Analytics_Access_Gate::render_locked($unlock_error);
            return;
        }
        $report = static::report();
        Archive::audit('analytics_viewed', 'success', ['definition_version' => $report['definition_version']]);
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=wg_finance_analytics_export'), 'wg_finance_analytics_export');

        echo '<div class="wrap"><h1>' . esc_html__('Historische Membership-Analysen', 'wg-membership-suite') . '</h1>';
        echo '<p>' . esc_html__('Historischer Geschäftsverlauf, Mitgliederentwicklung und Produktnutzung auf einen Blick. Detailquellen und Definitionen bleiben bei Bedarf nachvollziehbar.', 'wg-membership-suite') . '</p>';
        echo '<p><a class="button" href="' . esc_url(admin_url('tools.php?page=wg-finance-archive')) . '">' . esc_html__('Zum Finanzarchiv', 'wg-membership-suite') . '</a> <a class="button" href="' . esc_url($export_url) . '">' . esc_html__('Analyse als JSON exportieren', 'wg-membership-suite') . '</a></p>';
        static::render_summary($report['summary']);
        static::render_annual_overview($report['annual']);
        static::accordion_start(__('Definitionen und Datenqualität', 'wg-membership-suite'));
        static::render_warnings($report['warnings']);
        static::accordion_end();
        static::accordion_start(__('Quellen-, Steuer- und Bestelldetails', 'wg-membership-suite'));
        static::render_annual($report['annual']);
        static::accordion_end();
        static::accordion_start(sprintf(__('Mitglieder und Lifetime Value (%d)', 'wg-membership-suite'), count($report['customers'])));
        static::render_customers($report['customers']);
        static::accordion_end();
        static::accordion_start(sprintf(__('Produkte und Erweiterungen (%d)', 'wg-membership-suite'), count($report['products'])));
        static::render_products($report['products']);
        static::accordion_end();
        static::accordion_start(sprintf(__('Kandidaten für zukünftige Pakete (%d)', 'wg-membership-suite'), count($report['bundle_candidates'])));
        static::render_bundles($report['bundle_candidates']);
        static::accordion_end();
        echo '<p><small>' . esc_html(sprintf('Definition: %s · erzeugt %s', $report['definition_version'], $report['generated_at'])) . '</small></p></div>';
    }

    public static function export() {
        Analytics_Access_Gate::require_admin();
        if (!Analytics_Access_Gate::is_unlocked()) {
            wp_die(esc_html__('Bitte öffnen Sie zuerst Werkzeuge > Membership-Analysen und bestätigen Sie den Zugriff mit Ihrem WordPress-Passwort.', 'wg-membership-suite'), '', ['response' => 403]);
        }
        check_admin_referer('wg_finance_analytics_export');
        $report = static::report();
        $json = wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        Archive::audit('analytics_exported', 'success', [
            'definition_version' => $report['definition_version'],
            'report_sha256' => hash('sha256', $json),
        ]);
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="membership-history-analytics-' . gmdate('Ymd-His') . '.json"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    private static function report() {
        global $wpdb;
        $objects = Schema::table('objects');
        $rows = $wpdb->get_results(
            "SELECT o.* FROM {$objects} o
             LEFT JOIN {$objects} newer ON newer.logical_id=o.logical_id AND newer.version>o.version
             WHERE newer.id IS NULL
             ORDER BY o.id ASC",
            ARRAY_A
        );
        return Historical_Analytics::build(array_merge(
            (array) $rows,
            Current_Order_Projection::load('2026-01-01'),
            Finom_Historical_Summary::load()
        ));
    }

    private static function render_summary(array $summary) {
        $cards = [
            'Historie 2025' => static::money_map($summary['history_value_2025'], 0),
            '2026 YTD' => static::money_map($summary['history_value_2026_ytd'], 0),
            'Bestandskunden 2026' => $summary['carried_active_customer_count'],
            'Neue Kunden 2026' => $summary['new_commercial_customers_2026'],
            'Abgänge 2026' => $summary['observed_departures_2026'],
            'Bestellungen gesamt' => $summary['orders'],
        ];
        echo '<div style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0">';
        foreach ($cards as $label => $value) {
            $display = is_numeric($value) ? number_format_i18n((int) $value) : (string) $value;
            echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:14px;min-width:150px"><strong style="font-size:22px">' . esc_html($display) . '</strong><br>' . esc_html($label) . '</div>';
        }
        echo '</div><p>' . esc_html(sprintf('Bestellzeitraum: %s bis %s', $summary['order_date_min'] ?: '–', $summary['order_date_max'] ?: '–')) . '</p>';
    }

    private static function render_warnings(array $warnings) {
        echo '<div class="notice notice-info inline" style="margin:0"><ul>';
        foreach ($warnings as $warning) {
            echo '<li>' . esc_html($warning) . '</li>';
        }
        echo '</ul></div>';
    }

    private static function render_annual_overview(array $annual) {
        echo '<h2>' . esc_html__('Historie und Entwicklung', 'wg-membership-suite') . '</h2>';
        echo '<p>' . esc_html__('Historie/YTD verbindet die verfügbaren historischen Transaktionswerte mit den bestätigten Geldeingängen. Operative Bestellwerte werden nicht zusätzlich eingerechnet.', 'wg-membership-suite') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>Jahr</th><th>Historie / YTD</th><th>USt. nachweisbar</th><th>Registrierungen</th><th>Neue Kunden</th><th>Bestandskunden</th><th>Abgänge</th><th>Datenstand</th></tr></thead><tbody>';
        foreach (array_reverse($annual) as $row) {
            $coverage = $row['finom_coverage_periods'] ? implode(', ', $row['finom_coverage_periods']) : 'Gesamtjahr';
            $tax = static::money_map($row['historical_tax_amount']);
            if ($row['finom_cash_inflow']) {
                $tax = $tax === '–' ? '– (nicht aufgeteilt)' : $tax . ' (teilweise)';
            }
            echo '<tr><td>' . esc_html((string) $row['year']) . '</td><td><strong>' . esc_html(static::money_map($row['history_value'])) . '</strong></td><td>' . esc_html($tax) . '</td><td>' . esc_html((string) $row['registered_customer_count']) . '</td><td>' . esc_html((string) $row['new_commercial_customer_count']) . '</td><td>' . esc_html((string) $row['carried_active_customer_count']) . '</td><td>' . esc_html((string) $row['observed_departure_count']) . '</td><td>' . esc_html($coverage) . '</td></tr>';
        }
        if (!$annual) {
            echo '<tr><td colspan="8">' . esc_html__('Noch keine Jahresdaten vorhanden.', 'wg-membership-suite') . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_annual(array $annual) {
        echo '<h2>' . esc_html__('Finanzwerte nach Datenquelle', 'wg-membership-suite') . '</h2>';
        echo '<p>' . esc_html__('MemberPress-Werte sind historische Transaktionswerte. Finom History/YTD ist der bestätigte Geldeingang des angegebenen Abdeckungszeitraums; mangels strukturierter Rechnungsdaten werden daraus weder Netto noch USt. abgeleitet. Suite-Werte sind abgeschlossene Bestellwerte.', 'wg-membership-suite') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>Jahr</th><th>MemberPress netto</th><th>MemberPress USt.</th><th>MemberPress brutto</th><th>Finom Geldeingang (History/YTD)</th><th>Finom-Abdeckung</th><th>Suite-Bestellwert netto</th><th>Suite kalk. USt.</th><th>Suite-Bestellwert brutto</th></tr></thead><tbody>';
        foreach ($annual as $row) {
            echo '<tr><td>' . esc_html((string) $row['year']) . '</td><td>' . esc_html(static::money_map($row['historical_net_revenue'])) . '</td><td>' . esc_html(static::money_map($row['historical_tax_amount'])) . '</td><td>' . esc_html(static::money_map($row['historical_gross_revenue'])) . '</td><td>' . esc_html(static::money_map($row['finom_cash_inflow'])) . '</td><td>' . esc_html(implode(', ', $row['finom_coverage_periods']) ?: '–') . '</td><td>' . esc_html(static::money_map($row['suite_net_order_value'])) . '</td><td>' . esc_html(static::money_map($row['suite_tax_order_value'])) . '</td><td>' . esc_html(static::money_map($row['suite_gross_order_value'])) . '</td></tr>';
        }
        if (!$annual) {
            echo '<tr><td colspan="9">' . esc_html__('Noch keine Jahresdaten vorhanden.', 'wg-membership-suite') . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Bestellungen und Kundenbewegungen im Detail', 'wg-membership-suite') . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Jahr</th><th>Registrierungen</th><th>Neue kommerzielle Kunden</th><th>Davon Finom-bestätigt</th><th>Neue MemberPress-Zahler</th><th>Aktive Bestandsabos</th><th>Bestandskunden</th><th>Beobachtete Abgänge</th><th>MemberPress-Bestellungen</th><th>Suite-Bestellungen</th><th>Suite-Status</th></tr></thead><tbody>';
        foreach ($annual as $row) {
            $memberpress_orders = max(0, (int) $row['order_count'] - (int) $row['suite_order_count']);
            echo '<tr><td>' . esc_html((string) $row['year']) . '</td><td>' . esc_html((string) $row['registered_customer_count']) . '</td><td>' . esc_html((string) $row['new_commercial_customer_count']) . '</td><td>' . esc_html((string) $row['finom_confirmed_new_customer_count']) . '</td><td>' . esc_html((string) $row['new_paying_customer_count']) . '</td><td>' . esc_html((string) $row['carried_active_subscription_count']) . '</td><td>' . esc_html((string) $row['carried_active_customer_count']) . '</td><td>' . esc_html((string) $row['observed_departure_count']) . '</td><td>' . esc_html((string) $memberpress_orders) . '</td><td>' . esc_html((string) $row['suite_order_count']) . '</td><td>' . esc_html(static::status_map($row['suite_orders_by_status'])) . '</td></tr>';
        }
        if (!$annual) {
            echo '<tr><td colspan="11">' . esc_html__('Noch keine Jahresdaten vorhanden.', 'wg-membership-suite') . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_customers(array $customers) {
        echo '<p>' . esc_html__('Einzelansicht für Registrierung, Bestellverlauf, Lifetime Value sowie belegte Pausen und beobachtete Lücken.', 'wg-membership-suite') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>Mitglied</th><th>Registriert</th><th>Erste Bestellung</th><th>Letzte Bestellung</th><th>Bestellungen</th><th>MemberPress-Umsatz</th><th>Suite-Bestellwert</th><th>Kommerzieller LTV</th><th>Pausen / Lücken</th></tr></thead><tbody>';
        foreach (array_slice($customers, 0, 500) as $customer) {
            $identity = trim((string) $customer['display_name']);
            if ($identity === '') {
                $identity = (string) $customer['email'];
            }
            if ($identity === '') {
                $identity = 'Quellkunde ' . $customer['source_customer_id'];
            }
            $gaps = sprintf('%d belegt / %d Tage; %d beobachtet / %d Tage',
                $customer['explicit_pause_count'], $customer['explicit_pause_days'],
                $customer['observed_inactivity_gap_count'], $customer['observed_inactivity_gap_days']
            );
            echo '<tr><td>' . esc_html($identity) . '</td><td>' . esc_html($customer['registered_at'] ?: '–') . '</td><td>' . esc_html($customer['first_order_at'] ?: '–') . '</td><td>' . esc_html($customer['last_order_at'] ?: '–') . '</td><td>' . esc_html((string) $customer['order_count']) . '</td><td>' . esc_html(static::money_map($customer['historical_revenue'])) . '</td><td>' . esc_html(static::money_map($customer['suite_order_value'])) . '</td><td>' . esc_html(static::money_map($customer['commercial_lifetime_value'])) . '</td><td>' . esc_html($gaps) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_products(array $products) {
        echo '<p>' . esc_html__('Buchungshäufigkeit, eindeutige Kunden, Erstattungen und Status je Produkt.', 'wg-membership-suite') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>Quelle</th><th>Typ</th><th>Produkt</th><th>MemberPress abgeschlossen</th><th>Suite abgeschlossen</th><th>Eindeutige Kunden</th><th>Erstattungen</th><th>Historischer Umsatz</th><th>Statusverteilung</th></tr></thead><tbody>';
        foreach ($products as $product) {
            $source = $product['source'] === 'membership_suite' ? 'Membership Suite' : 'MemberPress-Archiv';
            echo '<tr><td>' . esc_html($source) . '</td><td>' . esc_html($product['kind']) . '</td><td>' . esc_html($product['title']) . '</td><td>' . esc_html((string) $product['completed_order_count']) . '</td><td>' . esc_html((string) $product['suite_completed_order_count']) . '</td><td>' . esc_html((string) $product['unique_customers']) . '</td><td>' . esc_html((string) $product['refund_count']) . '</td><td>' . esc_html(static::money_map($product['gross_revenue'])) . '</td><td>' . esc_html(static::status_map($product['orders_by_status'])) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_bundles(array $candidates) {
        echo '<p>' . esc_html__('Gemeinsame Kunden zeigen Nutzungsmuster, keine automatische oder kausale Produktempfehlung.', 'wg-membership-suite') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>Erweiterung</th><th>Basisprodukt</th><th>Gemeinsame Kunden</th><th>Abdeckung Erweiterung</th><th>Attach Rate Basisprodukt</th></tr></thead><tbody>';
        foreach (array_slice($candidates, 0, 100) as $candidate) {
            echo '<tr><td>' . esc_html($candidate['extension_title']) . '</td><td>' . esc_html($candidate['base_product_title']) . '</td><td>' . esc_html((string) $candidate['shared_customers']) . '</td><td>' . esc_html(number_format_i18n($candidate['extension_coverage_percent'], 1) . ' %') . '</td><td>' . esc_html(number_format_i18n($candidate['base_attach_rate_percent'], 1) . ' %') . '</td></tr>';
        }
        if (!$candidates) {
            echo '<tr><td colspan="5">' . esc_html__('Noch keine auswertbaren Produktüberschneidungen.', 'wg-membership-suite') . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function accordion_start($title) {
        echo '<details style="margin:16px 0;background:#fff;border:1px solid #ccd0d4">';
        echo '<summary style="cursor:pointer;padding:14px 16px;font-size:15px"><strong>' . esc_html($title) . '</strong></summary>';
        echo '<div style="padding:0 16px 16px">';
    }

    private static function accordion_end() {
        echo '</div></details>';
    }

    private static function money_map(array $map, $decimals = 2) {
        $parts = [];
        foreach ($map as $currency => $amount) {
            $currency = strtoupper((string) $currency);
            $label = $currency === 'EUR' ? '€' : $currency;
            $parts[] = number_format_i18n((float) $amount, (int) $decimals) . ' ' . $label;
        }
        return $parts ? implode(', ', $parts) : '–';
    }

    private static function status_map(array $map) {
        $parts = [];
        foreach ($map as $status => $count) {
            $parts[] = $status . ': ' . $count;
        }
        return $parts ? implode(', ', $parts) : '–';
    }
}
