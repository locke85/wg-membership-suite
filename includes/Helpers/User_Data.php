<?php

namespace wg\membership\Helpers;

use wg\membership\Checkout\Pricing;
use WP_Error;
use WP_User as WP_User_Object;

if (!defined('ABSPATH')) {
    exit;
}

class User_Data {
    public static function get_prefill_user_data($user_id = 0) {
        $user_id = $user_id ? absint($user_id) : get_current_user_id();
        $user = $user_id ? get_userdata($user_id) : null;

        $vorname = $user instanceof WP_User_Object ? (string) $user->first_name : '';
        $nachname = $user instanceof WP_User_Object ? (string) $user->last_name : '';
        $email = $user instanceof WP_User_Object ? (string) $user->user_email : '';

        if ($vorname === '') {
            $vorname = (string) get_user_meta($user_id, 'first_name', true);
        }
        if ($vorname === '') {
            $vorname = (string) get_user_meta($user_id, 'vorname', true);
        }
        if ($nachname === '') {
            $nachname = (string) get_user_meta($user_id, 'last_name', true);
        }
        if ($nachname === '') {
            $nachname = (string) get_user_meta($user_id, 'nachname', true);
        }

        $land = Sanitizer::country($user_id ? get_user_meta($user_id, 'land', true) : '');
        $kundenbeziehung = Sanitizer::kundenbeziehung($user_id ? get_user_meta($user_id, 'kundenbeziehung', true) : '');
        $nutzung = Sanitizer::nutzung($user_id ? get_user_meta($user_id, 'nutzung', true) : '');

        return [
            'vorname' => sanitize_text_field($vorname),
            'nachname' => sanitize_text_field($nachname),
            'email' => sanitize_email($email),
            'firma' => $user_id ? (string) get_user_meta($user_id, 'firma', true) : '',
            'strasse' => $user_id ? (string) get_user_meta($user_id, 'strasse', true) : '',
            'plz' => $user_id ? (string) get_user_meta($user_id, 'plz', true) : '',
            'stadt' => $user_id ? (string) get_user_meta($user_id, 'stadt', true) : '',
            'land' => $land,
            'kundenbeziehung' => $kundenbeziehung,
            'nutzung' => $nutzung,
            'steuernummer' => $user_id ? (string) get_user_meta($user_id, 'steuernummer', true) : '',
            'empfehler_email' => $user_id ? (string) get_user_meta($user_id, 'empfehler_email', true) : '',
            'zahlungsart' => $user_id ? Pricing::sanitize_checkout_payment_method((string) get_user_meta($user_id, 'zahlungsart', true)) : '',
            'spickzettel_url' => $user_id ? (string) get_user_meta($user_id, 'spickzettel_url', true) : '',
        ];
    }

    public static function save_user_profile_data($user_id, array $input) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return new WP_Error('invalid_user', __('Ungültiger Benutzer.', 'wg-membership-suite'));
        }

        $user = get_userdata($user_id);
        if (!($user instanceof WP_User_Object)) {
            return new WP_Error('missing_user', __('Benutzer nicht gefunden.', 'wg-membership-suite'));
        }

        $vorname = isset($input['vorname']) ? sanitize_text_field((string) $input['vorname']) : (string) $user->first_name;
        $nachname = isset($input['nachname']) ? sanitize_text_field((string) $input['nachname']) : (string) $user->last_name;
        $email = (string) $user->user_email;

        if (isset($input['email']) && (string) $input['email'] !== '') {
            $candidate = sanitize_email((string) $input['email']);
            if (is_email($candidate)) {
                $email = $candidate;
            }
        }

        $result = wp_update_user([
            'ID' => $user_id,
            'first_name' => $vorname,
            'last_name' => $nachname,
            'user_email' => $email,
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        update_user_meta($user_id, 'first_name', $vorname);
        update_user_meta($user_id, 'last_name', $nachname);
        update_user_meta($user_id, 'vorname', $vorname);
        update_user_meta($user_id, 'nachname', $nachname);

        $meta_map = [
            'firma' => 'sanitize_text_field',
            'strasse' => 'sanitize_text_field',
            'plz' => 'sanitize_text_field',
            'stadt' => 'sanitize_text_field',
            'steuernummer' => 'sanitize_text_field',
            'spickzettel_url' => 'esc_url_raw',
        ];

        foreach ($meta_map as $key => $callback) {
            if (array_key_exists($key, $input)) {
                update_user_meta($user_id, $key, call_user_func($callback, (string) $input[$key]));
            }
        }

        if (array_key_exists('land', $input)) {
            update_user_meta($user_id, 'land', Sanitizer::country($input['land']));
        }
        if (array_key_exists('kundenbeziehung', $input)) {
            update_user_meta($user_id, 'kundenbeziehung', Sanitizer::kundenbeziehung($input['kundenbeziehung']));
        }
        if (array_key_exists('nutzung', $input)) {
            update_user_meta($user_id, 'nutzung', Sanitizer::nutzung($input['nutzung']));
        }

        $empfehler_email = '';
        if (array_key_exists('empfehler_email', $input)) {
            $empfehler_email = sanitize_email((string) $input['empfehler_email']);
        } elseif (array_key_exists('empfehler', $input)) {
            $empfehler_email = sanitize_email((string) $input['empfehler']);
        }

        if (array_key_exists('empfehler_email', $input) || array_key_exists('empfehler', $input)) {
            update_user_meta($user_id, 'empfehler_email', $empfehler_email);
        }

        if (array_key_exists('zahlungsart', $input)) {
            update_user_meta($user_id, 'zahlungsart', \wg\membership\Checkout\Pricing::sanitize_checkout_payment_method($input['zahlungsart']));
        }

        return true;
    }
}
