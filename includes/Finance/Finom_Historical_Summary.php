<?php

namespace wg\membership\Finance;

if (!defined('ABSPATH')) {
    exit;
}

class Finom_Historical_Summary {
    const DEFINITION_VERSION = 'finom-screen-history-v2';

    public static function load() {
        return [
            static::object(
                '2025-08-01',
                '2025-12-31',
                '45089.80',
                '5546.59',
                'pasted_finom_transaction_screen_text',
                '951d264af903418b17543511b87c420a55162bc3e25443f520dd53f7d2d8499a'
            ),
            static::object(
                '2026-01-01',
                '2026-08-22',
                '48918.54',
                '14817.42',
                'user_supplied_finom_transaction_screen_text',
                ''
            ),
            static::customer_acquisition_object(),
        ];
    }

    private static function customer_acquisition_object() {
        $snapshot = [
            'definition_version' => self::DEFINITION_VERSION,
            'period_from' => '2026-01-01',
            'period_to' => '2026-08-22',
            'new_customers_by_month' => [
                '2026-02' => 1,
                '2026-08' => 2,
            ],
            'new_customer_count' => 3,
            'source_kind' => 'operator_confirmed_finom_customer_starts',
            'classification' => 'aggregate_customer_acquisition_evidence',
            'contains_customer_identity' => false,
            'detail_complete' => false,
        ];
        $json = wp_json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return [
            'object_type' => 'finom_customer_acquisition_summary',
            'source_system' => 'finom:operator-confirmed-history',
            'source_id' => '2026-01-01:2026-08-22',
            'logical_id' => hash('sha256', 'finom:operator-confirmed-history|2026-01-01|2026-08-22'),
            'object_hash' => hash('sha256', $json),
            'customer_ref_hash' => '',
            'customer_user_id' => 0,
            'status' => 'observed',
            'occurred_at' => '2026-08-22 23:59:59',
            'amount_gross' => null,
            'amount_tax' => null,
            'currency' => 'XXX',
            'snapshot_json' => $json,
        ];
    }

    private static function object($from, $to, $cash_inflow, $scheduled, $source_kind, $source_sha256) {
        $snapshot = [
            'definition_version' => self::DEFINITION_VERSION,
            'period_from' => $from,
            'period_to' => $to,
            'cash_inflow_gross' => $cash_inflow,
            'scheduled_or_open_amount' => $scheduled,
            'currency' => 'EUR',
            'source_kind' => $source_kind,
            'source_sha256' => $source_sha256,
            'classification' => 'cash_inflow_screen_total',
            'detail_complete' => false,
            'is_invoice_revenue_evidence' => false,
            'is_tax_evidence' => false,
            'include_scheduled_in_report' => false,
        ];
        $json = wp_json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return [
            'object_type' => 'finom_cashflow_summary',
            'source_system' => 'finom:screen-history',
            'source_id' => $from . ':' . $to,
            'logical_id' => hash('sha256', 'finom:screen-history|' . $from . '|' . $to),
            'object_hash' => hash('sha256', $json),
            'customer_ref_hash' => '',
            'customer_user_id' => 0,
            'status' => 'observed',
            'occurred_at' => $to . ' 23:59:59',
            'amount_gross' => $cash_inflow,
            'amount_tax' => null,
            'currency' => 'EUR',
            'snapshot_json' => $json,
        ];
    }
}
