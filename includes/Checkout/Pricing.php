<?php

namespace wg\membership\Checkout;

use wg\membership\Config;
use wg\membership\Helpers\Sanitizer;

if (!defined('ABSPATH')) {
    exit;
}

class Pricing {
    public static function get_angebot_select_options() {
        return [
            'mehrwertsteuer' => ['0' => '0%', '19' => '19%'],
            'rechnungsstellung' => ['einmalig' => 'Einmalig', 'wiederkehrend' => 'Wiederkehrend'],
            'intervall' => ['ohne_intervall' => 'Ohne Intervall', 'monatlich' => 'Monatlich', 'jährlich' => 'Jährlich'],
            'zugang' => ['lifetime' => 'Lifetime', 'trial' => 'Trial', 'monat' => 'Monat'],
            'type' => ['abo' => 'Abo', 'erweiterung' => 'Erweiterung', 'paket' => 'Paket', 'digitales_produkt' => 'Digitales Produkt'],
            'zahlungsform' => ['rechnung' => 'Rechnung (manuelle Zahlung)', 'sepa_ueberweisung' => 'Dauerauftrag / SEPA-Überweisung'],
            'renewal' => ['keine' => 'Keine', '3tage' => '3 Tage vorher'],
        ];
    }

    public static function sanitize_angebot_select_value($field, $value) {
        $options = self::get_angebot_select_options();
        $allowed = isset($options[$field]) ? array_keys($options[$field]) : [];
        $value = sanitize_text_field((string) $value);
        return in_array($value, $allowed, true) ? $value : (!empty($allowed) ? (string) $allowed[0] : '');
    }

    public static function parse_decimal($value) {
        return Sanitizer::parse_decimal($value);
    }

    public static function calculate_angebot_price($netto, $mwst_key) {
        $mwst_rate = ((string) $mwst_key === '19') ? 0.19 : 0.0;
        $mwst_betrag = round((float) $netto * $mwst_rate, 2);
        return [
            'mwst_satz' => (int) round($mwst_rate * 100),
            'mwst_betrag' => $mwst_betrag,
            'endpreis' => round((float) $netto + $mwst_betrag, 2),
        ];
    }

    public static function build_angebot_pricing($angebot_id) {
        $netto = self::parse_decimal(get_post_meta($angebot_id, 'nettopreis', true));
        $mwst_key = self::sanitize_angebot_select_value('mehrwertsteuer', get_post_meta($angebot_id, 'mehrwertsteuer', true));
        $calc = self::calculate_angebot_price($netto, $mwst_key);
        return [
            'netto' => $netto,
            'mwst_satz' => $calc['mwst_satz'],
            'mwst_betrag' => $calc['mwst_betrag'],
            'endpreis' => $calc['endpreis'],
        ];
    }

    public static function get_allowed_checkout_payment_methods() {
        $base = static::get_checkout_payment_method_labels();
        $allowed = Config::get_allowed_payment_methods();
        return array_intersect_key($base, array_flip($allowed));
    }

    public static function get_checkout_payment_method_labels() {
        return [
            'rechnung' => __('Rechnung (manuelle Zahlung)', 'wg-membership-suite'),
            'sepa_ueberweisung' => __('Dauerauftrag / SEPA-Überweisung', 'wg-membership-suite'),
        ];
    }

    public static function get_checkout_payment_method_label($method) {
        $labels = static::get_checkout_payment_method_labels();
        $method = sanitize_key((string) $method);
        return isset($labels[$method]) ? $labels[$method] : $method;
    }

    public static function sanitize_checkout_payment_method($value) {
        $methods = self::get_allowed_checkout_payment_methods();
        $value = sanitize_key((string) $value);
        return isset($methods[$value]) ? $value : 'rechnung';
    }

    public static function get_angebot_role_slug($angebot_id) {
        return 'abonnent_' . absint($angebot_id);
    }

    public static function ensure_angebot_role_exists($angebot_id) {
        $angebot_id = absint($angebot_id);
        if ($angebot_id <= 0) {
            return '';
        }

        $role_slug = self::get_angebot_role_slug($angebot_id);
        if (!get_role($role_slug)) {
            add_role($role_slug, sprintf(__('Abonnent Angebot #%d', 'wg-membership-suite'), $angebot_id), ['read' => true, $role_slug => true]);
        }

        $admin = get_role('administrator');
        if ($admin && !$admin->has_cap($role_slug)) {
            $admin->add_cap($role_slug);
        }

        return $role_slug;
    }

    public static function assign_angebot_role_to_user($user_id, $angebot_id) {
        $user = get_user_by('id', absint($user_id));
        if (!$user) {
            return;
        }

        $angebot_id = absint($angebot_id);
        $type = self::sanitize_angebot_select_value('type', get_post_meta($angebot_id, 'type', true));
        if ($type === 'erweiterung') {
            return;
        }

        $role_slug = self::ensure_angebot_role_exists($angebot_id);
        if ($role_slug && !in_array($role_slug, (array) $user->roles, true)) {
            $user->add_role($role_slug);
        }
    }

    public static function get_valid_angebot_post($angebot_id) {
        $angebot = get_post(absint($angebot_id));
        return ($angebot && $angebot->post_type === 'wg_angebot' && $angebot->post_status === 'publish') ? $angebot : null;
    }

    public static function angebot_has_category_slug($angebot_id, $slug) {
        $terms = wp_get_post_terms(absint($angebot_id), 'category', ['fields' => 'slugs']);
        return !is_wp_error($terms) && in_array($slug, $terms, true);
    }

    public static function get_payment_type_from_context($intervall, $zahlungsart) {
        return $intervall === 'ohne_intervall' ? 'einmal' : (($zahlungsart === 'sepa_ueberweisung') ? 'dauerauftrag' : 'einmal');
    }

    public static function enforce_angebot_payment_method($angebot_id, $requested_zahlungsart = '') {
        $angebot_zahlungsform = self::sanitize_angebot_select_value('zahlungsform', get_post_meta($angebot_id, 'zahlungsform', true));
        $requested_zahlungsart = self::sanitize_checkout_payment_method($requested_zahlungsart);

        if (in_array($angebot_zahlungsform, ['rechnung', 'sepa_ueberweisung'], true)) {
            return [
                'angebot_zahlungsform' => $angebot_zahlungsform,
                'effective_zahlungsart' => $angebot_zahlungsform,
            ];
        }

        return [
            'angebot_zahlungsform' => $angebot_zahlungsform,
            'effective_zahlungsart' => $requested_zahlungsart,
        ];
    }
}
