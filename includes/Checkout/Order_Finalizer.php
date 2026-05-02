<?php

namespace wg\membership\Checkout;

use wg\membership\Config;
use wg\membership\Helpers\User_Data;
use WP_Error;
use WP_Post;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

class Order_Finalizer {
    public static function get_supported_statuses() {
        return ['submitted', 'pending_admin_review', 'account_created', 'account_linked', 'completed', 'error'];
    }

    public static function extract_checkout_customer_data(array $input) {
        return Account_Resolver::extract_checkout_customer_data($input);
    }

    public static function add_bestellung_comment($bestellung_id, $content) {
        $bestellung_id = absint($bestellung_id);
        $content = trim((string) $content);
        if ($bestellung_id <= 0 || $content === '') {
            return 0;
        }

        return wp_insert_comment([
            'comment_post_ID' => $bestellung_id,
            'comment_content' => $content,
            'comment_type' => 'comment',
            'user_id' => get_current_user_id(),
            'comment_approved' => 1,
        ]);
    }

    public static function update_bestellung_status($bestellung_id, $status, $note = '') {
        if (!in_array($status, self::get_supported_statuses(), true)) {
            return false;
        }

        update_post_meta($bestellung_id, 'status', $status);
        $history = get_post_meta($bestellung_id, Config::STATUS_HISTORY_KEY, true);
        if (!is_array($history)) {
            $history = [];
        }
        $history[] = [
            'status' => $status,
            'note' => sanitize_text_field((string) $note),
            'changed_at' => current_time('mysql'),
            'changed_by' => get_current_user_id(),
        ];
        update_post_meta($bestellung_id, Config::STATUS_HISTORY_KEY, $history);
        return true;
    }

    public static function acquire_order_sequence_lock($max_wait_seconds = 2) {
        $lock_key = 'wg_order_sequence_lock';
        $wait_until = microtime(true) + (float) $max_wait_seconds;
        while (microtime(true) < $wait_until) {
            if (!get_transient($lock_key)) {
                set_transient($lock_key, 1, 5);
                if (get_transient($lock_key)) {
                    return true;
                }
            }
            usleep(100000);
        }
        return false;
    }

    public static function release_order_sequence_lock() {
        delete_transient('wg_order_sequence_lock');
    }

    public static function get_next_order_number() {
        if (!self::acquire_order_sequence_lock(2)) {
            return '';
        }

        $year = gmdate('Y');
        $map = get_option(Config::ORDER_SEQUENCE_OPTION, []);
        if (!is_array($map)) {
            $map = [];
        }

        try {
            $seq = isset($map[$year]) ? absint($map[$year]) : 0;
            do {
                $seq++;
                $order_number = sprintf('WG-%s-%06d', $year, $seq);
                $exists = get_posts([
                    'post_type' => 'wg_bestellung',
                    'post_status' => 'any',
                    'posts_per_page' => 1,
                    'fields' => 'ids',
                    'meta_key' => 'order_number',
                    'meta_value' => $order_number,
                ]);
            } while (!empty($exists));

            $map[$year] = $seq;
            update_option(Config::ORDER_SEQUENCE_OPTION, $map, false);
        } finally {
            self::release_order_sequence_lock();
        }

        return $order_number;
    }

    public static function get_checkout_order_token($input, $user_id = 0) {
        $candidates = [
            isset($input['wg_ot']) ? $input['wg_ot'] : '',
            isset($_REQUEST['wg_ot']) ? $_REQUEST['wg_ot'] : '',
            $user_id ? get_user_meta($user_id, 'wg_last_checkout_token', true) : '',
        ];
        foreach ($candidates as $candidate) {
            $token = sanitize_key((string) $candidate);
            if ($token !== '') {
                return $token;
            }
        }
        return '';
    }

    public static function find_bestellung_by_checkout_token($token) {
        $token = sanitize_key((string) $token);
        if ($token === '') {
            return 0;
        }
        $ids = get_posts([
            'post_type' => 'wg_bestellung',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => 'checkout_token',
            'meta_value' => $token,
        ]);
        return !empty($ids) ? (int) $ids[0] : 0;
    }

    public static function get_bestellung_summary_lines($bestellung_id) {
        $angebot_id = absint(get_post_meta($bestellung_id, 'angebot_id', true));
        $erweiterung_id = absint(get_post_meta($bestellung_id, 'erweiterung_id', true));
        $angebot = $angebot_id ? get_post($angebot_id) : null;
        $erweiterung = $erweiterung_id ? get_post($erweiterung_id) : null;
        $order_number = (string) get_post_meta($bestellung_id, 'order_number', true);

        $lines = [];
        if ($order_number !== '') {
            $lines[] = __('Bestellnummer:', 'wg-membership-suite') . ' ' . $order_number;
        }
        if ($angebot instanceof WP_Post) {
            $lines[] = __('Produkt:', 'wg-membership-suite') . ' ' . $angebot->post_title;
        }
        if ($erweiterung instanceof WP_Post) {
            $lines[] = __('Erweiterung:', 'wg-membership-suite') . ' ' . $erweiterung->post_title;
        }
        $brutto = get_post_meta($bestellung_id, 'brutto', true);
        if ($brutto !== '') {
            $lines[] = __('Brutto:', 'wg-membership-suite') . ' ' . number_format((float) $brutto, 2, ',', '.') . ' EUR';
        }
        return $lines;
    }

    public static function send_checkout_user_mail($to_email, $subject, array $lines) {
        $to_email = sanitize_email((string) $to_email);
        return ($to_email !== '' && is_email($to_email)) ? wp_mail($to_email, $subject, implode("\n", array_filter($lines))) : false;
    }

    public static function send_new_account_mail($user_id, $bestellung_id, $initial_password) {
        $user = get_userdata($user_id);
        if (!($user instanceof WP_User)) {
            return false;
        }

        $lines = [
            'Hallo ' . ($user->first_name !== '' ? $user->first_name : $user->user_login) . ',',
            '',
            __('zu Ihrer Bestellung wurde ein neuer Zugang angelegt.', 'wg-membership-suite'),
            __('Benutzername:', 'wg-membership-suite') . ' ' . $user->user_login,
            __('Initialpasswort:', 'wg-membership-suite') . ' ' . (string) $initial_password,
            __('Bitte ändern Sie dieses Passwort über die Reset-Funktion.', 'wg-membership-suite'),
            '',
        ];

        return self::send_checkout_user_mail($user->user_email, __('Ihre Bestellung und Ihr neuer Zugang', 'wg-membership-suite'), array_merge($lines, self::get_bestellung_summary_lines($bestellung_id)));
    }

    public static function send_existing_account_mail($user_id, $bestellung_id) {
        $user = get_userdata($user_id);
        if (!($user instanceof WP_User)) {
            return false;
        }

        $lines = [
            'Hallo ' . ($user->first_name !== '' ? $user->first_name : $user->user_login) . ',',
            '',
            __('Ihre Bestellung wurde Ihrem bestehenden Konto zugeordnet.', 'wg-membership-suite'),
            __('Ihre vorhandenen Zugangsdaten bleiben unverändert.', 'wg-membership-suite'),
            '',
        ];

        return self::send_checkout_user_mail($user->user_email, __('Ihre Bestellung wurde Ihrem Konto zugeordnet', 'wg-membership-suite'), array_merge($lines, self::get_bestellung_summary_lines($bestellung_id)));
    }

    public static function get_bestellung_admin_edit_link($bestellung_id) {
        return admin_url('post.php?post=' . absint($bestellung_id) . '&action=edit');
    }

    public static function get_order_resolution_link($bestellung_id, $resolution, $user_id = 0) {
        return wp_nonce_url(admin_url('admin-post.php?action=wg_order_resolve&order_id=' . absint($bestellung_id) . '&resolution=' . rawurlencode($resolution) . '&user_id=' . absint($user_id)), 'wg_order_resolve_' . absint($bestellung_id) . '_' . $resolution . '_' . absint($user_id));
    }

    public static function send_bestellung_admin_review_mail($bestellung_id, array $decision, array $customer_data) {
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return false;
        }

        $lines = [
            'Konfliktfall bei Bestellung #' . absint($bestellung_id),
            'Backend: ' . self::get_bestellung_admin_edit_link($bestellung_id),
            'Grund: ' . (string) $decision['reason'],
            'Kunde: ' . trim($customer_data['vorname'] . ' ' . $customer_data['nachname']) . ' <' . $customer_data['email'] . '>',
            '',
        ];

        foreach ((array) $decision['candidates'] as $candidate) {
            $lines[] = sprintf('Kandidat %d: %s (%s) Score %d', (int) $candidate['user_id'], (string) $candidate['user_login'], (string) $candidate['email'], (int) $candidate['score']);
            $lines[] = 'Freigabe Link Existing: ' . self::get_order_resolution_link($bestellung_id, 'link_existing', (int) $candidate['user_id']);
        }
        $lines[] = 'Freigabe Neuer Account: ' . self::get_order_resolution_link($bestellung_id, 'create_new', 0);

        return wp_mail($admin_email, __('Bestellung wartet auf Admin-Prüfung', 'wg-membership-suite'), implode("\n", $lines));
    }

    public static function create_bestellung_for_order($user_id, $angebot_id, array $args = []) {
        $angebot = Pricing::get_valid_angebot_post($angebot_id);
        if (!$angebot) {
            return 0;
        }

        $customer_data = isset($args['customer_data']) ? self::extract_checkout_customer_data((array) $args['customer_data']) : [];
        $erweiterung_id = isset($args['erweiterung_id']) ? absint($args['erweiterung_id']) : 0;
        $checkout_token = isset($args['checkout_token']) ? sanitize_key((string) $args['checkout_token']) : '';
        $status = isset($args['status']) && in_array($args['status'], self::get_supported_statuses(), true) ? $args['status'] : 'submitted';

        $pricing = Pricing::build_angebot_pricing($angebot_id);
        if ($erweiterung_id > 0 && Pricing::angebot_has_category_slug($erweiterung_id, 'erweiterung')) {
            $extension_pricing = Pricing::build_angebot_pricing($erweiterung_id);
            $pricing['netto'] += (float) $extension_pricing['netto'];
            $pricing['mwst_betrag'] += (float) $extension_pricing['mwst_betrag'];
            $pricing['endpreis'] += (float) $extension_pricing['endpreis'];
        }

        $intervall = Pricing::sanitize_angebot_select_value('intervall', get_post_meta($angebot_id, 'intervall', true));
        $angebot_typ = Pricing::sanitize_angebot_select_value('type', get_post_meta($angebot_id, 'type', true));
        $rechnungsstellung = Pricing::sanitize_angebot_select_value('rechnungsstellung', get_post_meta($angebot_id, 'rechnungsstellung', true));
        $requested_zahlungsart = $user_id > 0 ? (string) get_user_meta($user_id, 'zahlungsart', true) : ($customer_data['zahlungsart'] ?? '');
        $payment = Pricing::enforce_angebot_payment_method($angebot_id, $requested_zahlungsart);
        $customer_data['zahlungsart'] = $payment['effective_zahlungsart'];
        $order_number = self::get_next_order_number();
        if ($order_number === '') {
            return 0;
        }

        $bestellung_id = wp_insert_post([
            'post_type' => 'wg_bestellung',
            'post_status' => 'publish',
            'post_title' => $order_number,
        ], true);
        if (is_wp_error($bestellung_id) || !$bestellung_id) {
            return 0;
        }

        $meta = [
            'order_number' => $order_number,
            'user_id' => absint($user_id),
            'angebot_id' => absint($angebot_id),
            'erweiterung_id' => $erweiterung_id,
            'status' => $status,
            'payment_type' => Pricing::get_payment_type_from_context($intervall, $payment['effective_zahlungsart']),
            'angebot_typ' => $angebot_typ,
            'angebot_rechnungsstellung' => $rechnungsstellung,
            'angebot_zahlungsform' => $payment['angebot_zahlungsform'],
            'effective_zahlungsart' => $payment['effective_zahlungsart'],
            'zahlungsart' => $payment['effective_zahlungsart'],
            'intervall' => $intervall,
            'mwst_satz' => $pricing['mwst_satz'],
            'netto' => $pricing['netto'],
            'brutto' => $pricing['endpreis'],
            'checkout_token' => $checkout_token,
        ];

        foreach ($meta as $key => $value) {
            update_post_meta($bestellung_id, $key, $value);
        }
        foreach (Account_Resolver::get_checkout_customer_field_keys() as $field_key) {
            if (isset($customer_data[$field_key])) {
                update_post_meta($bestellung_id, 'customer_' . $field_key, $customer_data[$field_key]);
            }
        }

        self::update_bestellung_status($bestellung_id, $status, 'Bestellung angelegt');
        return (int) $bestellung_id;
    }

    public static function merge_order_products_into_user($user_id, $angebot_id, $erweiterung_id = 0) {
        $products = get_user_meta($user_id, 'gekaufte_produkte', true);
        if (!is_array($products)) {
            $products = [];
        }
        foreach ([absint($angebot_id), absint($erweiterung_id)] as $product_id) {
            if ($product_id > 0 && !in_array($product_id, $products, true)) {
                $products[] = $product_id;
            }
        }
        update_user_meta($user_id, 'gekaufte_produkte', array_values($products));
    }

    public static function complete_bestellung_for_user($bestellung_id, $user_id, array $customer_data, $created_new_account = false, $initial_password = '') {
        $angebot_id = absint(get_post_meta($bestellung_id, 'angebot_id', true));
        $erweiterung_id = absint(get_post_meta($bestellung_id, 'erweiterung_id', true));

        $result = Account_Resolver::fill_user_account_if_empty($user_id, $customer_data, false);
        if (is_wp_error($result)) {
            self::update_bestellung_status($bestellung_id, 'error', 'Kontodaten konnten nicht ergänzt werden');
            return false;
        }

        self::merge_order_products_into_user($user_id, $angebot_id, $erweiterung_id);
        update_user_meta($user_id, 'letzte_bestellung_id', $bestellung_id);
        update_post_meta($bestellung_id, 'user_id', $user_id);

        if ($angebot_id > 0) {
            Pricing::assign_angebot_role_to_user($user_id, $angebot_id);
        }

        if ($created_new_account) {
            self::update_bestellung_status($bestellung_id, 'account_created', 'Neuer Account wurde angelegt');
            self::send_new_account_mail($user_id, $bestellung_id, $initial_password);
        } else {
            self::update_bestellung_status($bestellung_id, 'account_linked', 'Bestehender Account wurde erweitert');
            self::send_existing_account_mail($user_id, $bestellung_id);
        }

        self::update_bestellung_status($bestellung_id, 'completed', 'Bestellung vollständig verarbeitet');
        return true;
    }

    public static function store_bestellung_review_context($bestellung_id, array $customer_data, array $decision) {
        update_post_meta($bestellung_id, Config::REVIEW_CONTEXT_KEY, ['customer_data' => $customer_data, 'decision' => $decision, 'stored_at' => current_time('mysql')]);
        update_post_meta($bestellung_id, Config::REVIEW_CANDIDATES_KEY, $decision['candidates']);

        $lines = ['Konfliktfall erkannt für ' . trim($customer_data['vorname'] . ' ' . $customer_data['nachname']) . ' <' . $customer_data['email'] . '>.', 'Grund: ' . (string) $decision['reason']];
        foreach ((array) $decision['candidates'] as $candidate) {
            $lines[] = sprintf('Kandidat %d: %s <%s> Score %d', (int) $candidate['user_id'], (string) $candidate['user_login'], (string) $candidate['email'], (int) $candidate['score']);
        }
        self::add_bestellung_comment($bestellung_id, implode("\n", $lines));
    }

    public static function finalize_bestellung_account_flow($bestellung_id, array $customer_data, array $forced_decision = []) {
        $status = (string) get_post_meta($bestellung_id, 'status', true);
        if (in_array($status, ['completed', 'account_created', 'account_linked'], true)) {
            return $bestellung_id;
        }
        if ($status === 'pending_admin_review' && empty($forced_decision)) {
            return $bestellung_id;
        }

        $customer_data = self::extract_checkout_customer_data($customer_data);
        $decision = !empty($forced_decision) ? $forced_decision : Account_Resolver::evaluate_checkout_account_decision($customer_data);

        if ($decision['action'] === 'pending_admin_review') {
            self::update_bestellung_status($bestellung_id, 'pending_admin_review', 'Bestellung wartet auf Admin-Prüfung');
            update_post_meta($bestellung_id, 'suggested_user_id', absint($decision['matched_user_id']));
            self::store_bestellung_review_context($bestellung_id, $customer_data, $decision);
            self::send_bestellung_admin_review_mail($bestellung_id, $decision, $customer_data);
            return $bestellung_id;
        }

        if ($decision['action'] === 'link_existing') {
            $matched_user_id = absint($decision['matched_user_id']);
            if ($matched_user_id <= 0) {
                self::update_bestellung_status($bestellung_id, 'error', 'Kein Zielkonto für Verknüpfung gefunden');
                return $bestellung_id;
            }
            self::complete_bestellung_for_user($bestellung_id, $matched_user_id, $customer_data, false, '');
            return $bestellung_id;
        }

        if ($decision['action'] === 'create_new') {
            $created = Account_Resolver::create_checkout_user_account($customer_data);
            if (is_wp_error($created)) {
                self::update_bestellung_status($bestellung_id, 'error', $created->get_error_message());
                return $bestellung_id;
            }
            self::complete_bestellung_for_user($bestellung_id, $created['user_id'], $customer_data, true, $created['password']);
            return $bestellung_id;
        }

        self::update_bestellung_status($bestellung_id, 'error', 'Unbekannte Entscheidungslogik');
        return $bestellung_id;
    }

    public static function build_checkout_order_payload(array $input, $user_id = 0) {
        return [
            'user_id' => absint($user_id),
            'angebot_id' => isset($input['angebot']) ? absint($input['angebot']) : 0,
            'erweiterung_id' => isset($input['erweiterung']) ? absint($input['erweiterung']) : 0,
            'checkout_token' => self::get_checkout_order_token($input, $user_id),
            'customer_data' => self::extract_checkout_customer_data($input),
        ];
    }

    public static function process_checkout_order_submission(array $payload) {
        $angebot_id = absint($payload['angebot_id']);
        if ($angebot_id <= 0 || !Pricing::get_valid_angebot_post($angebot_id)) {
            return new WP_Error('invalid_angebot', __('Ungültiges Angebot.', 'wg-membership-suite'));
        }

        $token = isset($payload['checkout_token']) ? sanitize_key((string) $payload['checkout_token']) : '';
        $existing = $token !== '' ? self::find_bestellung_by_checkout_token($token) : 0;
        if ($existing > 0) {
            return self::finalize_bestellung_account_flow($existing, $payload['customer_data']);
        }

        $bestellung_id = self::create_bestellung_for_order($payload['user_id'] ?? 0, $angebot_id, [
            'customer_data' => $payload['customer_data'],
            'erweiterung_id' => $payload['erweiterung_id'] ?? 0,
            'checkout_token' => $token,
            'status' => 'submitted',
        ]);
        if ($bestellung_id <= 0) {
            return new WP_Error('order_create_failed', __('Bestellung konnte nicht gespeichert werden.', 'wg-membership-suite'));
        }

        return self::finalize_bestellung_account_flow($bestellung_id, $payload['customer_data']);
    }
}
