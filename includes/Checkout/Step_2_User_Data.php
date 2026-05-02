<?php

namespace wg\membership\Checkout;

use wg\membership\Config;
use wg\membership\Helpers\User_Data;

if (!defined('ABSPATH')) {
    exit;
}

class Step_2_User_Data {
    public static function register() {
        add_filter('wpcf7_form_tag', [static::class, 'cf7_prefill_checkout_step2_form_tag'], 20, 2);
        add_filter('wpcf7_form_elements', [static::class, 'cf7_render_step2_hard_prefill'], 15);
        add_action('wpcf7_before_send_mail', [static::class, 'cf7_checkout_step2_save_user_data'], 20, 1);
        add_action('init', [static::class, 'handle_frontend_profile_form']);
    }

    public static function get_checkout_step2_field_mapping() {
        return ['vorname' => 'vorname', 'nachname' => 'nachname', 'email' => 'email', 'firma' => 'firma', 'strasse' => 'strasse', 'plz' => 'plz', 'stadt' => 'stadt', 'land' => 'land', 'kundenbeziehung' => 'kundenbeziehung', 'steuernummer' => 'steuernummer', 'empfehler' => 'empfehler_email'];
    }

    public static function is_cf7_checkout_step2_form($contact_form) {
        if (!is_object($contact_form)) {
            return false;
        }

        $configured_id = absint(Config::get('cf7_step2_form_id', 0));
        if ($configured_id > 0 && method_exists($contact_form, 'id') && absint($contact_form->id()) === $configured_id) {
            return true;
        }

        if (method_exists($contact_form, 'id') && (string) $contact_form->id() === 'e7eaf7f') {
            return true;
        }

        if (method_exists($contact_form, 'title')) {
            $title = trim((string) $contact_form->title());
            if ($title !== '' && stripos($title, 'checkout step 2') !== false) {
                return true;
            }
        }

        if (!method_exists($contact_form, 'prop')) {
            return false;
        }

        $template = (string) $contact_form->prop('form');
        foreach (array_keys(static::get_checkout_step2_field_mapping()) as $field_name) {
            if (preg_match('/\[[^\]]*\b' . preg_quote($field_name, '/') . '\b[^\]]*\]/i', $template)) {
                return true;
            }
        }

        return false;
    }

    public static function cf7_prefill_checkout_step2_form_tag($tag) {
        $tag_name = is_object($tag) && isset($tag->name) ? (string) $tag->name : (is_array($tag) && isset($tag['name']) ? (string) $tag['name'] : '');
        if ($tag_name === '' || is_admin() || !class_exists('WPCF7_ContactForm') || !is_user_logged_in()) {
            return $tag;
        }
        if (!Config::is_checkout_page('step_2') && !Config::is_checkout_page('step_3')) {
            return $tag;
        }

        $prefill = User_Data::get_prefill_user_data(get_current_user_id());
        $mapping = static::get_checkout_step2_field_mapping();
        $value = isset($mapping[$tag_name]) ? (string) ($prefill[$mapping[$tag_name]] ?? '') : '';

        if ($tag_name === 'angebot' || $tag_name === 'erweiterung') {
            $last = Order_Context::get_last_offer_for_user(get_current_user_id());
            $value = $tag_name === 'angebot' ? (string) $last['angebot_id'] : (string) $last['erweiterung_id'];
        }

        if ($value === '') {
            return $tag;
        }

        if (is_object($tag)) {
            if (property_exists($tag, 'values')) {
                $tag->values = [$value];
            }
            if (property_exists($tag, 'raw_values')) {
                $tag->raw_values = [$value];
            }
        } elseif (is_array($tag)) {
            $tag['values'] = [$value];
            $tag['raw_values'] = [$value];
        }

        return $tag;
    }

    public static function cf7_force_input_value_attr($html, $name, $value) {
        if ($value === '') {
            return $html;
        }
        return preg_replace_callback('/<input\b([^>]*\bname=["\']' . preg_quote($name, '/') . '["\'][^>]*)>/i', static function ($matches) use ($value) {
            $attrs = $matches[1];
            $attrs = preg_match('/\bvalue=["\'][^"\']*["\']/i', $attrs) ? preg_replace('/\bvalue=["\'][^"\']*["\']/i', 'value="' . esc_attr($value) . '"', $attrs) : $attrs . ' value="' . esc_attr($value) . '"';
            return '<input' . $attrs . '>';
        }, $html);
    }

    public static function cf7_force_select_value_attr($html, $name, $value) {
        if ($value === '') {
            return $html;
        }
        return preg_replace_callback('/(<select\b[^>]*\bname=["\']' . preg_quote($name, '/') . '["\'][^>]*>)(.*?)(<\/select>)/is', static function ($matches) use ($value) {
            $options = preg_replace('/\sselected(?:=["\']selected["\'])?/i', '', $matches[2]);
            $options = preg_replace('/(<option\b[^>]*\bvalue=["\']' . preg_quote($value, '/') . '["\'][^>]*)(>)/i', '$1 selected="selected"$2', $options, 1);
            return $matches[1] . $options . $matches[3];
        }, $html);
    }

    public static function cf7_render_step2_hard_prefill($content) {
        if (!is_user_logged_in() || !Config::is_checkout_page('step_2')) {
            return $content;
        }

        $prefill = User_Data::get_prefill_user_data(get_current_user_id());
        $last = Order_Context::get_last_offer_for_user(get_current_user_id());
        $input_map = [
            'vorname' => $prefill['vorname'],
            'nachname' => $prefill['nachname'],
            'firma' => $prefill['firma'],
            'strasse' => $prefill['strasse'],
            'plz' => $prefill['plz'],
            'stadt' => $prefill['stadt'],
            'email' => $prefill['email'],
            'steuernummer' => $prefill['steuernummer'],
            'empfehler' => $prefill['empfehler_email'],
            'angebot' => $last['angebot_id'] ? (string) $last['angebot_id'] : '',
            'erweiterung' => $last['erweiterung_id'] ? (string) $last['erweiterung_id'] : '',
        ];
        foreach ($input_map as $name => $value) {
            $content = static::cf7_force_input_value_attr($content, $name, $value);
        }
        foreach (['land' => $prefill['land'], 'kundenbeziehung' => $prefill['kundenbeziehung']] as $name => $value) {
            $content = static::cf7_force_select_value_attr($content, $name, $value);
        }

        return $content;
    }

    public static function cf7_checkout_step2_save_user_data($contact_form) {
        if (!is_user_logged_in() || !class_exists('WPCF7_Submission') || !static::is_cf7_checkout_step2_form($contact_form)) {
            return;
        }
        $submission = \WPCF7_Submission::get_instance();
        $posted_data = $submission ? $submission->get_posted_data() : null;
        if (!is_array($posted_data)) {
            return;
        }

        $save_input = [];
        foreach (static::get_checkout_step2_field_mapping() as $cf7_field => $profile_field) {
            if (array_key_exists($cf7_field, $posted_data)) {
                $save_input[$profile_field] = $posted_data[$cf7_field];
            }
        }
        User_Data::save_user_profile_data(get_current_user_id(), $save_input);
    }

    public static function handle_frontend_profile_form() {
        if (!isset($_POST['form_submitted'])) {
            return;
        }
        if (!isset($_POST['wg_user_data_nonce']) || !wp_verify_nonce($_POST['wg_user_data_nonce'], 'wg_user_data_submit')) {
            wp_die(__('Ungültige Formular-Anfrage. Bitte neu laden.', 'wg-membership-suite'));
        }

        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        $email = isset($_POST['email']) ? sanitize_email((string) $_POST['email']) : '';
        if (!is_email($email)) {
            wp_die(__('Ungültige E-Mail-Adresse.', 'wg-membership-suite'));
        }

        $save_input = $_POST;
        $save_input['email'] = $email;
        if ($user_id > 0) {
            $result = User_Data::save_user_profile_data($user_id, $save_input);
            if (is_wp_error($result)) {
                wp_die(esc_html($result->get_error_message()));
            }
        }

        foreach (['agb' => 'agb_ok', 'datenschutz' => 'datenschutz_ok', 'widerruf' => 'widerruf_erloschen', 'kooperation' => 'kooperationsvereinbarung_ok'] as $post_key => $meta_key) {
            if ($user_id > 0 && isset($_POST[$post_key])) {
                update_user_meta($user_id, $meta_key, '1');
            }
        }

        $redirect = !empty($_POST['redirect_to']) ? wp_validate_redirect((string) $_POST['redirect_to'], home_url('/')) : add_query_arg('saved', '1', wp_get_referer());
        if (($save_input['context'] ?? '') === 'bestellung' && !empty($_POST['angebot'])) {
            $angebot_id = absint($_POST['angebot']);
            $erweiterung_id = isset($_POST['erweiterung']) ? absint($_POST['erweiterung']) : 0;
            if (!Pricing::get_valid_angebot_post($angebot_id)) {
                wp_die(__('Ungültiges Angebot ausgewählt.', 'wg-membership-suite'));
            }

            $payment = Pricing::enforce_angebot_payment_method($angebot_id, $_POST['zahlungsart'] ?? '');
            if ($user_id > 0) {
                update_user_meta($user_id, 'zahlungsart', $payment['effective_zahlungsart']);
                Order_Context::set_last_offer_for_user($user_id, $angebot_id, $erweiterung_id);
            }

            $token = Order_Context::store($user_id, $angebot_id, ['erweiterung_id' => $erweiterung_id, 'customer_data' => $save_input]);
            $redirect = add_query_arg(['angebot' => $angebot_id, 'erweiterung' => $erweiterung_id, 'wg_ot' => $token], $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }
}
