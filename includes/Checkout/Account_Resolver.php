<?php

namespace wg\membership\Checkout;

use WP_Error;
use WP_User;
use wg\membership\Helpers\Sanitizer;
use wg\membership\Helpers\User_Data;

if (!defined('ABSPATH')) {
    exit;
}

class Account_Resolver {
    public static function get_checkout_customer_field_keys() {
        return ['vorname', 'nachname', 'email', 'firma', 'strasse', 'plz', 'stadt', 'land', 'kundenbeziehung', 'nutzung', 'steuernummer', 'empfehler_email', 'zahlungsart'];
    }

    public static function extract_checkout_customer_data(array $input) {
        if (!isset($input['empfehler_email']) && isset($input['empfehler'])) {
            $input['empfehler_email'] = $input['empfehler'];
        }

        $data = [];
        foreach (self::get_checkout_customer_field_keys() as $key) {
            $value = array_key_exists($key, $input) ? $input[$key] : '';
            if (is_array($value)) {
                $value = reset($value);
            }

            switch ($key) {
                case 'email':
                case 'empfehler_email':
                    $data[$key] = sanitize_email((string) $value);
                    break;
                case 'land':
                    $data[$key] = Sanitizer::country($value);
                    break;
                case 'kundenbeziehung':
                    $data[$key] = Sanitizer::kundenbeziehung($value);
                    break;
                case 'nutzung':
                    $data[$key] = Sanitizer::nutzung($value);
                    break;
                case 'zahlungsart':
                    $data[$key] = Pricing::sanitize_checkout_payment_method($value);
                    break;
                default:
                    $data[$key] = sanitize_text_field((string) $value);
            }
        }

        return $data;
    }

    public static function get_user_identity_snapshot($user_id) {
        $user = get_userdata(absint($user_id));
        if (!($user instanceof WP_User)) {
            return [];
        }

        $vorname = (string) $user->first_name ?: (string) get_user_meta($user_id, 'vorname', true);
        $nachname = (string) $user->last_name ?: (string) get_user_meta($user_id, 'nachname', true);

        return [
            'user_id' => (int) $user_id,
            'user_login' => (string) $user->user_login,
            'display_name' => (string) $user->display_name,
            'vorname' => sanitize_text_field($vorname),
            'nachname' => sanitize_text_field($nachname),
            'email' => sanitize_email((string) $user->user_email),
        ];
    }

    public static function collect_checkout_user_candidates($customer_data) {
        $customer_data = self::extract_checkout_customer_data((array) $customer_data);
        $candidate_ids = [];

        if ($customer_data['email'] !== '' && is_email($customer_data['email'])) {
            $user = get_user_by('email', $customer_data['email']);
            if ($user instanceof WP_User) {
                $candidate_ids[] = (int) $user->ID;
            }
        }

        if ($customer_data['vorname'] !== '' || $customer_data['nachname'] !== '') {
            $name_ids = get_users([
                'fields' => 'ids',
                'number' => 50,
                'meta_query' => [
                    'relation' => 'OR',
                    ['key' => 'first_name', 'value' => $customer_data['vorname']],
                    ['key' => 'vorname', 'value' => $customer_data['vorname']],
                    ['key' => 'last_name', 'value' => $customer_data['nachname']],
                    ['key' => 'nachname', 'value' => $customer_data['nachname']],
                ],
            ]);

            foreach ($name_ids as $id) {
                $candidate_ids[] = (int) $id;
            }
        }

        $candidate_ids = array_values(array_unique(array_filter(array_map('absint', $candidate_ids))));
        $candidates = [];

        foreach ($candidate_ids as $candidate_id) {
            $snapshot = self::get_user_identity_snapshot($candidate_id);
            if (empty($snapshot)) {
                continue;
            }

            $email_match = $customer_data['email'] !== '' && strcasecmp($snapshot['email'], $customer_data['email']) === 0;
            $name_match = $customer_data['vorname'] !== '' && $customer_data['nachname'] !== '' &&
                strcasecmp($snapshot['vorname'], $customer_data['vorname']) === 0 &&
                strcasecmp($snapshot['nachname'], $customer_data['nachname']) === 0;

            $score = ($email_match ? 100 : 0) + ($name_match ? 50 : 0);
            $candidates[] = $snapshot + [
                'email_match' => $email_match,
                'name_match' => $name_match,
                'exact_match' => $email_match && $name_match,
                'score' => $score,
            ];
        }

        usort($candidates, static function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $candidates;
    }

    public static function evaluate_checkout_account_decision($customer_data) {
        $customer_data = self::extract_checkout_customer_data((array) $customer_data);
        $candidates = self::collect_checkout_user_candidates($customer_data);
        $exact = array_values(array_filter($candidates, static function ($candidate) { return !empty($candidate['exact_match']); }));
        $email = array_values(array_filter($candidates, static function ($candidate) { return !empty($candidate['email_match']); }));
        $name = array_values(array_filter($candidates, static function ($candidate) { return !empty($candidate['name_match']); }));

        if (count($exact) === 1) {
            return ['action' => 'link_existing', 'matched_user_id' => (int) $exact[0]['user_id'], 'candidates' => $candidates, 'reason' => 'exact_match'];
        }
        if (count($exact) > 1) {
            return ['action' => 'pending_admin_review', 'matched_user_id' => 0, 'candidates' => $exact, 'reason' => 'multiple_exact_matches'];
        }
        if (!empty($email)) {
            return ['action' => 'pending_admin_review', 'matched_user_id' => count($email) === 1 ? (int) $email[0]['user_id'] : 0, 'candidates' => $email, 'reason' => 'email_conflict'];
        }
        if (!empty($name)) {
            return ['action' => 'pending_admin_review', 'matched_user_id' => count($name) === 1 ? (int) $name[0]['user_id'] : 0, 'candidates' => $name, 'reason' => 'name_conflict'];
        }

        return ['action' => 'create_new', 'matched_user_id' => 0, 'candidates' => [], 'reason' => 'no_match'];
    }

    public static function normalize_user_login_fragment($value) {
        $value = strtolower((string) $value);
        $value = strtr($value, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $value = preg_replace('/[^a-z0-9]+/', '', $value);
        return is_string($value) ? $value : '';
    }

    public static function generate_checkout_user_login($vorname, $nachname) {
        $vorname = self::normalize_user_login_fragment($vorname);
        $nachname = self::normalize_user_login_fragment($nachname);
        $vorname = $vorname !== '' ? $vorname : 'user';
        $nachname = $nachname !== '' ? $nachname : 'x';

        for ($i = 1, $max = strlen($nachname); $i <= $max; $i++) {
            $candidate = $vorname . '_' . substr($nachname, 0, $i);
            if (!username_exists($candidate)) {
                return $candidate;
            }
        }

        $suffix = 2;
        do {
            $candidate = $vorname . '_' . $nachname . $suffix;
            $suffix++;
        } while (username_exists($candidate));

        return $candidate;
    }

    public static function fill_user_account_if_empty($user_id, array $customer_data, $allow_email_update = false) {
        $user = get_userdata(absint($user_id));
        $customer_data = self::extract_checkout_customer_data($customer_data);
        if (!($user instanceof WP_User)) {
            return new WP_Error('missing_user', __('Benutzer nicht gefunden.', 'wg-membership-suite'));
        }

        $updates = ['ID' => $user->ID];
        $has_updates = false;

        if ($user->first_name === '' && $customer_data['vorname'] !== '') {
            $updates['first_name'] = $customer_data['vorname'];
            update_user_meta($user->ID, 'first_name', $customer_data['vorname']);
            update_user_meta($user->ID, 'vorname', $customer_data['vorname']);
            $has_updates = true;
        }
        if ($user->last_name === '' && $customer_data['nachname'] !== '') {
            $updates['last_name'] = $customer_data['nachname'];
            update_user_meta($user->ID, 'last_name', $customer_data['nachname']);
            update_user_meta($user->ID, 'nachname', $customer_data['nachname']);
            $has_updates = true;
        }
        if ($allow_email_update && $user->user_email === '' && $customer_data['email'] !== '' && is_email($customer_data['email'])) {
            $updates['user_email'] = $customer_data['email'];
            $has_updates = true;
        }

        if ($has_updates) {
            $result = wp_update_user($updates);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        foreach (['firma', 'strasse', 'plz', 'stadt', 'land', 'kundenbeziehung', 'nutzung', 'steuernummer', 'empfehler_email', 'zahlungsart'] as $meta_key) {
            $existing = get_user_meta($user->ID, $meta_key, true);
            if ($existing === '' && $customer_data[$meta_key] !== '') {
                update_user_meta($user->ID, $meta_key, $customer_data[$meta_key]);
            }
        }

        return true;
    }

    public static function create_checkout_user_account(array $customer_data) {
        $customer_data = self::extract_checkout_customer_data($customer_data);
        if ($customer_data['email'] === '' || !is_email($customer_data['email'])) {
            return new WP_Error('invalid_email', __('Ungültige E-Mail-Adresse.', 'wg-membership-suite'));
        }

        $user_login = self::generate_checkout_user_login($customer_data['vorname'], $customer_data['nachname']);
        $password = wp_generate_password(20, true, true);
        $user_id = wp_create_user($user_login, $password, $customer_data['email']);
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        $saved = User_Data::save_user_profile_data($user_id, $customer_data);
        if (is_wp_error($saved)) {
            return $saved;
        }

        return ['user_id' => (int) $user_id, 'user_login' => $user_login, 'password' => $password];
    }
}
