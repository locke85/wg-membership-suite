<?php

namespace wg\membership\Finance;

if (!defined('ABSPATH')) {
    exit;
}

class Historical_Analytics {
    public static function from_memberpress_export(array $export, $default_currency = 'XXX') {
        $objects = [];
        $currency = self::currency($default_currency);
        $members_by_id = self::index_source_rows((array) ($export['members'] ?? []));
        $subscriptions_by_id = self::index_source_rows((array) ($export['subscriptions'] ?? []));
        $transactions_by_id = self::index_source_rows((array) ($export['transactions'] ?? []));
        foreach ((array) ($export['members'] ?? []) as $member) {
            $member = (array) $member;
            $objects[] = self::source_object(
                'customer',
                (string) ($member['id'] ?? ''),
                self::customer_key($member),
                'historical',
                $member['registered_at'] ?? null,
                null,
                'XXX',
                $member
            );
        }
        foreach ((array) ($export['memberships'] ?? []) as $membership) {
            $membership = (array) $membership;
            $type = preg_match('/^Erweiterung(?:\s|\x{2013}|-)/iu', trim((string) ($membership['title'] ?? ''))) ? 'extension' : 'membership_product';
            $objects[] = self::source_object($type, (string) ($membership['id'] ?? ''), '', (string) ($membership['status'] ?? ''), $membership['date_gmt'] ?? $membership['date'] ?? null, null, 'XXX', $membership);
        }
        foreach ((array) ($export['subscriptions'] ?? []) as $subscription) {
            $subscription = (array) $subscription;
            $member = is_array($subscription['member'] ?? null) ? $subscription['member'] : ['id' => $subscription['member'] ?? ''];
            $objects[] = self::source_object('subscription', (string) ($subscription['id'] ?? ''), self::customer_key($member), (string) ($subscription['status'] ?? ''), $subscription['created_at'] ?? null, $subscription['total'] ?? null, self::currency($subscription['currency'] ?? $currency), $subscription);
        }
        foreach ((array) ($export['transactions'] ?? []) as $transaction) {
            $transaction = (array) $transaction;
            $customer_key = self::customer_key(is_array($transaction['member'] ?? null) ? $transaction['member'] : ['id' => $transaction['member'] ?? '']);
            $row_currency = self::currency($transaction['currency'] ?? $currency);
            $objects[] = self::source_object('order', (string) ($transaction['id'] ?? ''), $customer_key, (string) ($transaction['status'] ?? ''), $transaction['created_at'] ?? null, $transaction['total'] ?? null, $row_currency, $transaction);
            if (($transaction['status'] ?? '') === 'refunded') {
                $objects[] = self::source_object('refund', (string) ($transaction['id'] ?? ''), $customer_key, 'refunded', $transaction['created_at'] ?? null, $transaction['total'] ?? null, $row_currency, $transaction);
            }
        }
        foreach ((array) ($export['events'] ?? []) as $event) {
            $event = self::normalize_source_event((array) $event, $members_by_id, $subscriptions_by_id, $transactions_by_id);
            if (!self::is_pause_event_type($event['event_type'] ?? '')) {
                continue;
            }
            $member = is_array($event['member'] ?? null) ? $event['member'] : ['id' => $event['member'] ?? ''];
            $objects[] = self::source_object('membership_event', (string) ($event['id'] ?? ''), self::customer_key($member), (string) ($event['event_type'] ?? $event['status'] ?? ''), $event['occurred_at'] ?? $event['created_at'] ?? null, null, 'XXX', $event);
        }
        return self::build($objects);
    }

    private static function index_source_rows(array $rows) {
        $indexed = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $id = (string) ($row['id'] ?? '');
            if ($id !== '') {
                $indexed[$id] = $row;
            }
        }
        return $indexed;
    }

    private static function normalize_source_event(array $event, array $members, array $subscriptions, array $transactions) {
        $entity_type = sanitize_key((string) ($event['source_entity_type'] ?? $event['evt_id_type'] ?? ''));
        $entity_id = (string) ($event['source_entity_id'] ?? $event['evt_id'] ?? '');
        $event['event_type'] = (string) ($event['event_type'] ?? $event['event'] ?? '');
        $event['occurred_at'] = $event['occurred_at'] ?? $event['created_at'] ?? null;
        if ($entity_type === 'users' && isset($members[$entity_id])) {
            $event['member'] = $members[$entity_id];
        } elseif ($entity_type === 'subscriptions' && isset($subscriptions[$entity_id])) {
            $subscription = $subscriptions[$entity_id];
            $event['member'] = $subscription['member'] ?? null;
            $event['membership'] = $subscription['membership'] ?? null;
            $event['subscription'] = ['id' => $subscription['id'] ?? $entity_id];
        } elseif ($entity_type === 'transactions' && isset($transactions[$entity_id])) {
            $transaction = $transactions[$entity_id];
            $event['member'] = $transaction['member'] ?? null;
            $event['membership'] = $transaction['membership'] ?? null;
            $event['subscription'] = $transaction['subscription'] ?? null;
        }
        return $event;
    }

    private static function is_pause_event_type($event_type) {
        return in_array(sanitize_key((string) $event_type), [
            'pause_started', 'paused', 'suspended', 'pause_ended', 'resumed', 'active',
        ], true);
    }

    public static function build(array $objects) {
        $customers = [];
        $products = [];
        $orders = [];
        $refunds = [];
        $current_orders = [];
        $events = [];
        $subscriptions = [];
        $finom_cashflows = [];
        $finom_customer_acquisitions = [];
        $data_state_rows = [];
        $batch_ids = [];

        foreach ($objects as $object) {
            $object = (array) $object;
            $data_state_rows[] = [
                (string) ($object['object_type'] ?? ''),
                (string) ($object['source_id'] ?? ''),
                (string) ($object['object_hash'] ?? hash('sha256', (string) ($object['snapshot_json'] ?? ''))),
            ];
            if (!empty($object['batch_id'])) {
                $batch_ids[(int) $object['batch_id']] = true;
            }
            if (($object['object_type'] ?? '') === 'membership_event' && !self::is_pause_event_type($object['status'] ?? '')) {
                continue;
            }
            $object['snapshot'] = json_decode((string) ($object['snapshot_json'] ?? ''), true);
            if (!is_array($object['snapshot'])) {
                $object['snapshot'] = [];
            }
            switch ($object['object_type'] ?? '') {
                case 'customer':
                    $customers[(string) $object['customer_ref_hash']] = self::customer_from_object($object);
                    break;
                case 'membership_product':
                case 'extension':
                    $products[(string) $object['source_id']] = self::product_from_object($object);
                    break;
                case 'order':
                    $orders[] = $object;
                    break;
                case 'current_order':
                    $current_orders[] = $object;
                    break;
                case 'refund':
                    $refunds[] = $object;
                    break;
                case 'subscription':
                    $subscriptions[] = $object;
                    break;
                case 'finom_cashflow_summary':
                    $finom_cashflows[] = $object;
                    break;
                case 'finom_customer_acquisition_summary':
                    $finom_customer_acquisitions[] = $object;
                    break;
                case 'membership_event':
                    $events[] = $object;
                    break;
            }
        }

        $status_counts = [];
        $current_status_counts = [];
        $coverage = [];
        $customer_products = [];
        $date_min = null;
        $date_max = null;
        $annual = [];
        $last_coverage_end = [];
        $currently_open_customers = [];
        $carried_active_subscriptions = 0;
        $carried_active_customers = [];
        $finom_cash_inflow_by_year = [];
        $finom_new_customers_by_year = [];
        $finom_new_customers_by_month = [];

        foreach ($finom_cashflows as $cashflow) {
            $year = self::year($cashflow['occurred_at'] ?? null);
            if ($year === null) {
                continue;
            }
            self::ensure_annual_year($annual, $year);
            $currency = self::currency($cashflow['currency'] ?? 'EUR');
            $amount = self::minor($cashflow['amount_gross'] ?? 0);
            self::add_money($annual[$year]['finom_cash_inflow_minor'], $currency, $amount);
            if (!isset($finom_cash_inflow_by_year[$year])) {
                $finom_cash_inflow_by_year[$year] = [];
            }
            self::add_money($finom_cash_inflow_by_year[$year], $currency, $amount);
            $period_from = self::date_value($cashflow['snapshot']['period_from'] ?? null);
            $period_to = self::date_value($cashflow['snapshot']['period_to'] ?? null);
            if ($period_from && $period_to) {
                $annual[$year]['finom_coverage_periods'][] = substr($period_from, 0, 10) . '–' . substr($period_to, 0, 10);
            }
        }

        foreach ($finom_customer_acquisitions as $acquisition) {
            foreach ((array) ($acquisition['snapshot']['new_customers_by_month'] ?? []) as $month => $count) {
                if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', (string) $month, $matches)) {
                    continue;
                }
                $count = max(0, (int) $count);
                if ($count === 0) {
                    continue;
                }
                $year = (int) $matches[1];
                $finom_new_customers_by_year[$year] = ($finom_new_customers_by_year[$year] ?? 0) + $count;
                $finom_new_customers_by_month[(string) $month] = ($finom_new_customers_by_month[(string) $month] ?? 0) + $count;
            }
        }

        foreach ($subscriptions as $subscription) {
            $status = sanitize_key((string) ($subscription['status'] ?? ''));
            if (in_array($status, ['active', 'suspended'], true)) {
                $currently_open_customers[(string) ($subscription['customer_ref_hash'] ?? '')] = true;
            }
            $subscription_year = self::year($subscription['occurred_at'] ?? null);
            if ($status === 'active' && $subscription_year !== null && $subscription_year < 2026) {
                $carried_active_subscriptions++;
                $customer_key = (string) ($subscription['customer_ref_hash'] ?? '');
                if ($customer_key !== '') {
                    $carried_active_customers[$customer_key] = true;
                }
            }
        }
        self::ensure_annual_year($annual, 2026);
        $annual[2026]['carried_active_subscription_count'] = $carried_active_subscriptions;
        $annual[2026]['carried_active_customer_count'] = count($carried_active_customers);

        foreach ($customers as $customer) {
            $year = self::year($customer['registered_at'] ?? null);
            if ($year !== null) {
                self::ensure_annual_year($annual, $year);
                $annual[$year]['registered_customer_count']++;
            }
        }

        $customer_keys_by_user = [];
        foreach ($customers as $customer_key => $customer) {
            if (!empty($customer['target_user_id'])) {
                $customer_keys_by_user[(int) $customer['target_user_id']] = $customer_key;
            }
        }

        foreach ($orders as $order) {
            $customer_key = (string) ($order['customer_ref_hash'] ?? '');
            if (!isset($customers[$customer_key])) {
                $customers[$customer_key] = self::empty_customer($customer_key);
            }
            $customer =& $customers[$customer_key];
            $status = sanitize_key((string) ($order['status'] ?? 'unknown')) ?: 'unknown';
            $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
            $occurred_at = self::date_value($order['occurred_at'] ?? null);
            $date_min = self::date_min($date_min, $occurred_at);
            $date_max = self::date_max($date_max, $occurred_at);
            $customer['order_count']++;
            $customer['orders_by_status'][$status] = ($customer['orders_by_status'][$status] ?? 0) + 1;
            $customer['first_order_at'] = self::date_min($customer['first_order_at'], $occurred_at);
            $customer['last_order_at'] = self::date_max($customer['last_order_at'], $occurred_at);
            $year = self::year($occurred_at);
            if ($year !== null) {
                self::ensure_annual_year($annual, $year);
                $annual[$year]['order_count']++;
                $annual[$year]['orders_by_status'][$status] = ($annual[$year]['orders_by_status'][$status] ?? 0) + 1;
            }

            $membership = self::membership_from_snapshot($order['snapshot']);
            $product_id = $membership['id'];
            if ($product_id !== '' && !isset($products[$product_id])) {
                $products[$product_id] = self::empty_product($product_id, $membership['title']);
            }
            if ($product_id !== '') {
                $product =& $products[$product_id];
                $product['orders_by_status'][$status] = ($product['orders_by_status'][$status] ?? 0) + 1;
            }

            if ($status === 'complete') {
                $currency = self::currency($order['currency'] ?? 'XXX');
                $gross = self::minor($order['amount_gross'] ?? 0);
                $tax = self::minor($order['amount_tax'] ?? $order['snapshot']['tax_amount'] ?? 0);
                self::add_money($customer['historical_revenue_minor'], $currency, $gross);
                $customer['completed_order_count']++;
                $customer['first_completed_order_at'] = self::date_min($customer['first_completed_order_at'], $occurred_at);
                if ($product_id !== '') {
                    $product['completed_order_count']++;
                    $product['customer_keys'][$customer_key] = true;
                    self::add_money($product['gross_revenue_minor'], $currency, $gross);
                    $customer_products[$customer_key][$product_id] = true;
                    self::add_coverage($coverage, $customer_key, $product_id, $order);
                }
                if ($year !== null) {
                    self::add_money($annual[$year]['completed_gross_minor'], $currency, $gross);
                    self::add_money($annual[$year]['completed_tax_minor'], $currency, $tax);
                }
                $coverage_end = self::date_value($order['snapshot']['expires_at'] ?? null);
                if ($coverage_end) {
                    $last_coverage_end[$customer_key] = self::date_max($last_coverage_end[$customer_key] ?? null, $coverage_end);
                }
            }
            unset($customer, $product);
        }

        foreach ($current_orders as $order) {
            $user_id = (int) ($order['customer_user_id'] ?? 0);
            $customer_key = $user_id > 0 && isset($customer_keys_by_user[$user_id])
                ? $customer_keys_by_user[$user_id]
                : (string) ($order['customer_ref_hash'] ?? '');
            $is_new_customer = !isset($customers[$customer_key]);
            if ($is_new_customer) {
                $customers[$customer_key] = self::empty_customer($customer_key);
                $member = is_array($order['snapshot']['member'] ?? null) ? $order['snapshot']['member'] : [];
                $customers[$customer_key]['target_user_id'] = $user_id;
                $customers[$customer_key]['source_customer_id'] = $user_id > 0 ? ('membership-suite:' . $user_id) : '';
                $customers[$customer_key]['display_name'] = trim((string) ($member['display_name'] ?? ''));
                $customers[$customer_key]['email'] = (string) ($member['email'] ?? '');
                $customers[$customer_key]['registered_at'] = self::date_value($member['registered_at'] ?? null);
                if ($user_id > 0) {
                    $customer_keys_by_user[$user_id] = $customer_key;
                }
                $registration_year = self::year($customers[$customer_key]['registered_at']);
                if ($registration_year !== null) {
                    self::ensure_annual_year($annual, $registration_year);
                    $annual[$registration_year]['registered_customer_count']++;
                }
            }

            $customer =& $customers[$customer_key];
            $status = sanitize_key((string) ($order['status'] ?? 'unknown')) ?: 'unknown';
            $current_status_counts[$status] = ($current_status_counts[$status] ?? 0) + 1;
            $customer_status = 'membership_suite:' . $status;
            $occurred_at = self::date_value($order['occurred_at'] ?? null);
            $date_min = self::date_min($date_min, $occurred_at);
            $date_max = self::date_max($date_max, $occurred_at);
            $customer['order_count']++;
            $customer['suite_order_count']++;
            $customer['orders_by_status'][$customer_status] = ($customer['orders_by_status'][$customer_status] ?? 0) + 1;
            $customer['first_order_at'] = self::date_min($customer['first_order_at'], $occurred_at);
            $customer['last_order_at'] = self::date_max($customer['last_order_at'], $occurred_at);
            $year = self::year($occurred_at);
            if ($year !== null) {
                self::ensure_annual_year($annual, $year);
                $annual[$year]['order_count']++;
                $annual[$year]['suite_order_count']++;
                $annual[$year]['suite_orders_by_status'][$status] = ($annual[$year]['suite_orders_by_status'][$status] ?? 0) + 1;
            }

            $products_in_order = is_array($order['snapshot']['products'] ?? null) ? $order['snapshot']['products'] : [];
            foreach ($products_in_order as $membership) {
                $membership = (array) $membership;
                $product_id = (string) ($membership['id'] ?? '');
                if ($product_id === '') {
                    continue;
                }
                if (!isset($products[$product_id])) {
                    $products[$product_id] = self::empty_product($product_id, (string) ($membership['title'] ?? ''));
                    $products[$product_id]['kind'] = ($membership['kind'] ?? '') === 'extension' ? 'extension' : 'membership_product';
                    $products[$product_id]['source'] = 'membership_suite';
                }
                $products[$product_id]['orders_by_status'][$customer_status] = ($products[$product_id]['orders_by_status'][$customer_status] ?? 0) + 1;
                if ($status === 'completed') {
                    $products[$product_id]['suite_completed_order_count']++;
                    $products[$product_id]['customer_keys'][$customer_key] = true;
                    $customer_products[$customer_key][$product_id] = true;
                }
            }

            if ($status === 'completed') {
                $currency = self::currency($order['currency'] ?? 'EUR');
                $net = self::minor($order['amount_net'] ?? 0);
                $tax = self::minor($order['amount_tax'] ?? 0);
                $gross = self::minor($order['amount_gross'] ?? 0);
                self::add_money($customer['suite_order_value_minor'], $currency, $gross);
                $customer['suite_completed_order_count']++;
                $customer['first_suite_completed_order_at'] = self::date_min($customer['first_suite_completed_order_at'], $occurred_at);
                if ($year !== null) {
                    self::add_money($annual[$year]['suite_net_minor'], $currency, $net);
                    self::add_money($annual[$year]['suite_tax_minor'], $currency, $tax);
                    self::add_money($annual[$year]['suite_gross_minor'], $currency, $gross);
                }
            }
            unset($customer);
        }

        foreach ($refunds as $refund) {
            $customer_key = (string) ($refund['customer_ref_hash'] ?? '');
            if (!isset($customers[$customer_key])) {
                $customers[$customer_key] = self::empty_customer($customer_key);
            }
            $currency = self::currency($refund['currency'] ?? 'XXX');
            $gross = self::minor($refund['amount_gross'] ?? 0);
            $tax = self::minor($refund['amount_tax'] ?? $refund['snapshot']['tax_amount'] ?? 0);
            self::add_money($customers[$customer_key]['historical_revenue_minor'], $currency, -$gross);
            $customers[$customer_key]['refund_count']++;
            $membership = self::membership_from_snapshot($refund['snapshot']);
            if ($membership['id'] !== '') {
                if (!isset($products[$membership['id']])) {
                    $products[$membership['id']] = self::empty_product($membership['id'], $membership['title']);
                }
                $products[$membership['id']]['refund_count']++;
                self::add_money($products[$membership['id']]['gross_revenue_minor'], $currency, -$gross);
            }
            $year = self::year($refund['occurred_at'] ?? null);
            if ($year !== null) {
                self::ensure_annual_year($annual, $year);
                self::add_money($annual[$year]['refunded_gross_minor'], $currency, $gross);
                self::add_money($annual[$year]['refunded_tax_minor'], $currency, $tax);
            }
        }

        self::apply_coverage_metrics($customers, $coverage);
        self::apply_explicit_pause_metrics($customers, $events);
        $bundle_candidates = self::bundle_candidates($products, $customer_products);

        foreach ($customers as $customer_key => $customer) {
            $first_paid_year = self::year($customer['first_completed_order_at'] ?? null);
            if ($first_paid_year !== null) {
                self::ensure_annual_year($annual, $first_paid_year);
                $annual[$first_paid_year]['new_paying_customer_count']++;
            }
            if (!isset($currently_open_customers[$customer_key]) && !empty($last_coverage_end[$customer_key])) {
                $departure_year = self::year($last_coverage_end[$customer_key]);
                if ($departure_year !== null) {
                    self::ensure_annual_year($annual, $departure_year);
                    $annual[$departure_year]['observed_departure_count']++;
                }
            }
            $first_commercial_at = self::date_min(
                $customer['first_completed_order_at'] ?? null,
                $customer['first_suite_completed_order_at'] ?? null
            );
            $commercial_year = self::year($first_commercial_at);
            if ($commercial_year !== null) {
                self::ensure_annual_year($annual, $commercial_year);
                $annual[$commercial_year]['new_commercial_customer_count']++;
            }
        }
        foreach ($finom_new_customers_by_year as $year => $count) {
            self::ensure_annual_year($annual, $year);
            $annual[$year]['new_commercial_customer_count'] += $count;
            $annual[$year]['finom_confirmed_new_customer_count'] += $count;
        }
        $annual = self::finalize_annual($annual);
        $annual_2025 = self::annual_row($annual, 2025);
        $annual_2026 = self::annual_row($annual, 2026);

        foreach ($customers as &$customer) {
            $customer['historical_revenue'] = self::format_money_map($customer['historical_revenue_minor']);
            $customer['suite_order_value'] = self::format_money_map($customer['suite_order_value_minor']);
            $customer['commercial_lifetime_value'] = self::format_money_map(self::merge_money_maps(
                $customer['historical_revenue_minor'],
                $customer['suite_order_value_minor']
            ));
            unset($customer['historical_revenue_minor'], $customer['suite_order_value_minor']);
            ksort($customer['orders_by_status']);
        }
        unset($customer);
        foreach ($products as &$product) {
            $product['unique_customers'] = count($product['customer_keys']);
            $product['gross_revenue'] = self::format_money_map($product['gross_revenue_minor']);
            unset($product['customer_keys'], $product['gross_revenue_minor']);
            ksort($product['orders_by_status']);
        }
        unset($product);

        sort($data_state_rows);
        ksort($batch_ids);
        $total_explicit_pauses = array_sum(array_column($customers, 'explicit_pause_count'));
        $total_explicit_pause_days = array_sum(array_column($customers, 'explicit_pause_days'));
        $total_observed_gaps = array_sum(array_column($customers, 'observed_inactivity_gap_count'));
        $total_observed_gap_days = array_sum(array_column($customers, 'observed_inactivity_gap_days'));

        uasort($customers, function ($a, $b) {
            return strcmp((string) ($a['first_order_at'] ?? '9999'), (string) ($b['first_order_at'] ?? '9999'));
        });
        uasort($products, function ($a, $b) {
            $a_count = (int) $a['completed_order_count'] + (int) $a['suite_completed_order_count'];
            $b_count = (int) $b['completed_order_count'] + (int) $b['suite_completed_order_count'];
            return ($b_count <=> $a_count) ?: strcasecmp($a['title'], $b['title']);
        });
        ksort($status_counts);

        return [
            'definition_version' => 'membership-analytics-v7',
            'generated_at' => gmdate('c'),
            'data_state' => [
                'object_count' => count($objects),
                'dataset_sha256' => hash('sha256', wp_json_encode($data_state_rows, JSON_UNESCAPED_SLASHES)),
                'batch_ids' => array_map('intval', array_keys($batch_ids)),
            ],
            'summary' => [
                'registered_customers' => count(array_filter($customers, function ($customer) { return !empty($customer['registered_at']); })),
                'customers_with_orders' => count(array_filter($customers, function ($customer) { return $customer['order_count'] > 0; })),
                'orders' => count($orders) + count($current_orders),
                'historical_orders' => count($orders),
                'suite_orders' => count($current_orders),
                'carried_active_subscription_count' => $carried_active_subscriptions,
                'carried_active_customer_count' => count($carried_active_customers),
                'finom_cashflow_summaries' => count($finom_cashflows),
                'finom_customer_acquisition_summaries' => count($finom_customer_acquisitions),
                'finom_confirmed_new_customers_by_month' => $finom_new_customers_by_month,
                'finom_cash_inflow_2025' => self::format_money_map($finom_cash_inflow_by_year[2025] ?? []),
                'finom_cash_inflow_2026' => self::format_money_map($finom_cash_inflow_by_year[2026] ?? []),
                'history_value_2025' => $annual_2025['history_value'] ?? [],
                'history_value_2026_ytd' => $annual_2026['history_value'] ?? [],
                'new_commercial_customers_2026' => (int) ($annual_2026['new_commercial_customer_count'] ?? 0),
                'observed_departures_2026' => (int) ($annual_2026['observed_departure_count'] ?? 0),
                'orders_by_status' => $status_counts,
                'suite_orders_by_status' => $current_status_counts,
                'products' => count(array_filter($products, function ($product) { return $product['kind'] === 'membership_product'; })),
                'extensions' => count(array_filter($products, function ($product) { return $product['kind'] === 'extension'; })),
                'order_date_min' => $date_min,
                'order_date_max' => $date_max,
                'explicit_pause_count' => $total_explicit_pauses,
                'explicit_pause_days' => $total_explicit_pause_days,
                'observed_inactivity_gap_count' => $total_observed_gaps,
                'observed_inactivity_gap_days' => $total_observed_gap_days,
            ],
            'customers' => array_values($customers),
            'products' => array_values($products),
            'annual' => $annual,
            'bundle_candidates' => $bundle_candidates,
            'warnings' => [
                'Beträge mit Währung XXX sind nicht als EUR bestätigt und werden getrennt ausgewiesen.',
                'Historischer Umsatz bis 2025 stammt aus MemberPress. Bestellwerte ab 2026 stammen aus der Membership Suite und sind kein Finom-Zahlungseingang.',
                'Aktive Bestandsabos und Bestandskunden 2026 sind aus vor 2026 begonnenen Abos mit MemberPress-Migrationsstatus active abgeleitet. Pausierte, gekuendigte und ausstehende Abos sind ausgeschlossen; die Kennzahl behauptet weder Rechnung noch Zahlung im Jahr 2026.',
                'Finom History/YTD zeigt bestaetigte Geldeingangssummen aus unvollstaendigen Bildschirm-Snapshots: 2025-08-01 bis 2025-12-31 und 2026-01-01 bis 2026-08-22. Die Werte enthalten keine belastbare Netto-/USt-Aufteilung und werden nicht in Kunden-LTV, Produktanalyse oder Rechnungsumsatz eingerechnet.',
                'Die Neukunden 2026 enthalten drei vom Betreiber bestaetigte Finom-era Starts: einen im Februar und zwei im August. Der Nachweis ist absichtlich nur monatlich aggregiert und fuehrt kein paralleles Kundenregister.',
                'Kommerzieller Lifetime Value addiert historischen MemberPress-Umsatz und abgeschlossene Membership-Suite-Bestellwerte; er ist keine OPOS- oder Zahlungskennzahl.',
                'Beobachtete Inaktivitätslücken sind keine behaupteten vertraglichen Pausen.',
                'Die Projektion ist kein OPOS-, Rechnungs- oder Zahlungsnebenbuch.',
                'Steuerbetraege sind historisch ausgewiesene Umsatzsteuer, kein Nachweis einer Zahlung an das Finanzamt.',
                'Beobachtete Abgaenge bedeuten: letzte bezahlte Abdeckung beendet und kein aktuell aktives oder suspendiertes Abo; sie sind keine behauptete Kuendigung.',
            ],
        ];
    }

    private static function ensure_annual_year(array &$annual, $year) {
        if (isset($annual[$year])) {
            return;
        }
        $annual[$year] = [
            'year' => (int) $year,
            'registered_customer_count' => 0,
            'new_paying_customer_count' => 0,
            'new_commercial_customer_count' => 0,
            'finom_confirmed_new_customer_count' => 0,
            'carried_active_subscription_count' => 0,
            'carried_active_customer_count' => 0,
            'observed_departure_count' => 0,
            'order_count' => 0,
            'orders_by_status' => [],
            'suite_order_count' => 0,
            'suite_orders_by_status' => [],
            'finom_cash_inflow_minor' => [],
            'finom_coverage_periods' => [],
            'completed_gross_minor' => [],
            'refunded_gross_minor' => [],
            'completed_tax_minor' => [],
            'refunded_tax_minor' => [],
            'suite_net_minor' => [],
            'suite_tax_minor' => [],
            'suite_gross_minor' => [],
        ];
    }

    private static function finalize_annual(array $annual) {
        ksort($annual, SORT_NUMERIC);
        foreach ($annual as &$row) {
            $currencies = array_unique(array_merge(
                array_keys($row['completed_gross_minor']),
                array_keys($row['refunded_gross_minor']),
                array_keys($row['completed_tax_minor']),
                array_keys($row['refunded_tax_minor'])
            ));
            $gross = [];
            $tax = [];
            $net = [];
            foreach ($currencies as $currency) {
                $gross_minor = ($row['completed_gross_minor'][$currency] ?? 0) - ($row['refunded_gross_minor'][$currency] ?? 0);
                $tax_minor = ($row['completed_tax_minor'][$currency] ?? 0) - ($row['refunded_tax_minor'][$currency] ?? 0);
                $gross[$currency] = $gross_minor;
                $tax[$currency] = $tax_minor;
                $net[$currency] = $gross_minor - $tax_minor;
            }
            $row['historical_gross_revenue'] = self::format_money_map($gross);
            $row['historical_tax_amount'] = self::format_money_map($tax);
            $row['historical_net_revenue'] = self::format_money_map($net);
            $row['history_value'] = self::format_money_map(self::merge_money_maps($gross, $row['finom_cash_inflow_minor']));
            $row['suite_net_order_value'] = self::format_money_map($row['suite_net_minor']);
            $row['suite_tax_order_value'] = self::format_money_map($row['suite_tax_minor']);
            $row['suite_gross_order_value'] = self::format_money_map($row['suite_gross_minor']);
            $row['finom_cash_inflow'] = self::format_money_map($row['finom_cash_inflow_minor']);
            $row['finom_coverage_periods'] = array_values(array_unique($row['finom_coverage_periods']));
            ksort($row['orders_by_status']);
            ksort($row['suite_orders_by_status']);
            unset(
                $row['completed_gross_minor'],
                $row['refunded_gross_minor'],
                $row['completed_tax_minor'],
                $row['refunded_tax_minor'],
                $row['suite_net_minor'],
                $row['suite_tax_minor'],
                $row['suite_gross_minor'],
                $row['finom_cash_inflow_minor']
            );
        }
        unset($row);
        return array_values($annual);
    }

    private static function annual_row(array $annual, $year) {
        foreach ($annual as $row) {
            if ((int) ($row['year'] ?? 0) === (int) $year) {
                return $row;
            }
        }
        return [];
    }

    private static function customer_from_object(array $object) {
        $customer = self::empty_customer((string) $object['customer_ref_hash']);
        $snapshot = $object['snapshot'];
        $customer['source_customer_id'] = (string) ($object['source_id'] ?? '');
        $customer['target_user_id'] = (int) ($object['customer_user_id'] ?? 0);
        $customer['display_name'] = trim((string) ($snapshot['display_name'] ?? ''));
        if ($customer['display_name'] === '') {
            $customer['display_name'] = trim((string) ($snapshot['first_name'] ?? '') . ' ' . (string) ($snapshot['last_name'] ?? ''));
        }
        $customer['email'] = (string) ($snapshot['email'] ?? '');
        $customer['registered_at'] = self::date_value($object['occurred_at'] ?? $snapshot['registered_at'] ?? null);
        return $customer;
    }

    private static function empty_customer($key) {
        return [
            'customer_key' => $key,
            'source_customer_id' => '',
            'target_user_id' => 0,
            'display_name' => '',
            'email' => '',
            'registered_at' => null,
            'first_order_at' => null,
            'first_completed_order_at' => null,
            'last_order_at' => null,
            'order_count' => 0,
            'completed_order_count' => 0,
            'suite_order_count' => 0,
            'suite_completed_order_count' => 0,
            'first_suite_completed_order_at' => null,
            'refund_count' => 0,
            'orders_by_status' => [],
            'historical_revenue_minor' => [],
            'suite_order_value_minor' => [],
            'observed_inactivity_gap_count' => 0,
            'observed_inactivity_gap_days' => 0,
            'explicit_pause_count' => 0,
            'explicit_pause_days' => 0,
        ];
    }

    private static function product_from_object(array $object) {
        $snapshot = $object['snapshot'];
        $product = self::empty_product((string) $object['source_id'], (string) ($snapshot['title'] ?? ''));
        $product['kind'] = (string) $object['object_type'];
        return $product;
    }

    private static function empty_product($id, $title) {
        return [
            'source_product_id' => (string) $id,
            'title' => $title !== '' ? $title : ('Produkt ' . $id),
            'kind' => 'membership_product',
            'source' => 'memberpress_archive',
            'completed_order_count' => 0,
            'suite_completed_order_count' => 0,
            'refund_count' => 0,
            'orders_by_status' => [],
            'customer_keys' => [],
            'gross_revenue_minor' => [],
        ];
    }

    private static function membership_from_snapshot(array $snapshot) {
        $membership = $snapshot['membership'] ?? [];
        if (!is_array($membership)) {
            return ['id' => (string) $membership, 'title' => ''];
        }
        return ['id' => (string) ($membership['id'] ?? ''), 'title' => (string) ($membership['title'] ?? '')];
    }

    private static function add_coverage(array &$coverage, $customer_key, $product_id, array $order) {
        $start = strtotime((string) ($order['occurred_at'] ?? ''));
        $end = strtotime((string) ($order['snapshot']['expires_at'] ?? ''));
        if (!$start || !$end || $end <= $start) {
            return;
        }
        $coverage[$customer_key][$product_id][] = [$start, $end];
    }

    private static function apply_coverage_metrics(array &$customers, array $coverage) {
        foreach ($coverage as $customer_key => $by_product) {
            foreach ($by_product as $intervals) {
                usort($intervals, function ($a, $b) { return $a[0] <=> $b[0]; });
                $end = null;
                foreach ($intervals as $interval) {
                    if ($end !== null && $interval[0] > $end) {
                        $days = (int) floor(($interval[0] - $end) / 86400);
                        if ($days > 0) {
                            $customers[$customer_key]['observed_inactivity_gap_count']++;
                            $customers[$customer_key]['observed_inactivity_gap_days'] += $days;
                        }
                    }
                    $end = $end === null ? $interval[1] : max($end, $interval[1]);
                }
            }
        }
    }

    private static function apply_explicit_pause_metrics(array &$customers, array $events) {
        $open = [];
        usort($events, function ($a, $b) {
            return strcmp((string) ($a['occurred_at'] ?? ''), (string) ($b['occurred_at'] ?? ''));
        });
        foreach ($events as $event) {
            $customer_key = (string) ($event['customer_ref_hash'] ?? '');
            if (!isset($customers[$customer_key])) {
                $customers[$customer_key] = self::empty_customer($customer_key);
            }
            $snapshot = $event['snapshot'];
            $event_type = sanitize_key((string) ($snapshot['event_type'] ?? $event['status'] ?? ''));
            $subscription = $snapshot['subscription'] ?? [];
            $subscription_id = is_array($subscription) ? (string) ($subscription['id'] ?? '') : (string) $subscription;
            $key = $customer_key . '|' . $subscription_id;
            $timestamp = strtotime((string) ($event['occurred_at'] ?? $snapshot['occurred_at'] ?? ''));
            if (!$timestamp) {
                continue;
            }
            if (in_array($event_type, ['pause_started', 'paused', 'suspended'], true)) {
                $open[$key] = $timestamp;
            } elseif (in_array($event_type, ['pause_ended', 'resumed', 'active'], true) && isset($open[$key])) {
                $days = max(0, (int) floor(($timestamp - $open[$key]) / 86400));
                $customers[$customer_key]['explicit_pause_count']++;
                $customers[$customer_key]['explicit_pause_days'] += $days;
                unset($open[$key]);
            }
        }
    }

    private static function bundle_candidates(array $products, array $customer_products) {
        $product_customers = [];
        foreach ($customer_products as $customer_key => $product_ids) {
            foreach (array_keys($product_ids) as $product_id) {
                $product_customers[$product_id][$customer_key] = true;
            }
        }
        $rows = [];
        foreach ($products as $extension_id => $extension) {
            if ($extension['kind'] !== 'extension') {
                continue;
            }
            $extension_customers = array_keys($product_customers[$extension_id] ?? []);
            if (!$extension_customers) {
                continue;
            }
            foreach ($products as $base_id => $base) {
                if ($base['kind'] === 'extension' || $base_id === $extension_id) {
                    continue;
                }
                $base_customers = array_keys($product_customers[$base_id] ?? []);
                $overlap = count(array_intersect($extension_customers, $base_customers));
                if ($overlap === 0) {
                    continue;
                }
                $rows[] = [
                    'extension_id' => $extension_id,
                    'extension_title' => $extension['title'],
                    'base_product_id' => $base_id,
                    'base_product_title' => $base['title'],
                    'shared_customers' => $overlap,
                    'extension_customers' => count($extension_customers),
                    'base_product_customers' => count($base_customers),
                    'extension_coverage_percent' => round($overlap * 100 / count($extension_customers), 1),
                    'base_attach_rate_percent' => round($overlap * 100 / max(1, count($base_customers)), 1),
                ];
            }
        }
        usort($rows, function ($a, $b) {
            return ($b['shared_customers'] <=> $a['shared_customers'])
                ?: ($b['base_attach_rate_percent'] <=> $a['base_attach_rate_percent']);
        });
        return $rows;
    }

    private static function minor($value) {
        $value = trim((string) $value);
        if (!preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            return 0;
        }
        $fraction = str_pad(substr($matches[3] ?? '', 0, 3), 3, '0');
        $minor = ((int) $matches[2] * 100) + (int) substr($fraction, 0, 2);
        if ((int) substr($fraction, 2, 1) >= 5) {
            $minor++;
        }
        return ($matches[1] ?? '') === '-' ? -$minor : $minor;
    }

    private static function add_money(array &$map, $currency, $minor) {
        $map[$currency] = ($map[$currency] ?? 0) + (int) $minor;
    }

    private static function merge_money_maps(array $left, array $right) {
        foreach ($right as $currency => $minor) {
            $left[$currency] = ($left[$currency] ?? 0) + (int) $minor;
        }
        return $left;
    }

    private static function format_money_map(array $map) {
        ksort($map);
        $result = [];
        foreach ($map as $currency => $minor) {
            $sign = $minor < 0 ? '-' : '';
            $absolute = abs((int) $minor);
            $result[$currency] = sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
        }
        return $result;
    }

    private static function currency($currency) {
        $currency = strtoupper((string) $currency);
        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'XXX';
    }

    private static function customer_key(array $member) {
        $source_id = (string) ($member['id'] ?? '');
        $email = strtolower(trim((string) ($member['email'] ?? '')));
        return hash('sha256', $source_id . '|' . $email);
    }

    private static function source_object($type, $source_id, $customer_key, $status, $date, $gross, $currency, array $snapshot) {
        return [
            'object_type' => $type,
            'source_id' => $source_id,
            'customer_ref_hash' => $customer_key,
            'customer_user_id' => 0,
            'status' => $status,
            'occurred_at' => $date,
            'amount_gross' => $gross,
            'currency' => $currency,
            'snapshot_json' => wp_json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }

    private static function year($value) {
        $date = self::date_value($value);
        if (!$date) {
            return null;
        }
        $year = (int) substr($date, 0, 4);
        return $year >= 1900 && $year <= 2200 ? $year : null;
    }

    private static function date_value($value) {
        $timestamp = strtotime((string) $value);
        return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : null;
    }

    private static function date_min($left, $right) {
        if (!$right) {
            return $left;
        }
        return !$left || strcmp($right, $left) < 0 ? $right : $left;
    }

    private static function date_max($left, $right) {
        if (!$right) {
            return $left;
        }
        return !$left || strcmp($right, $left) > 0 ? $right : $left;
    }
}
