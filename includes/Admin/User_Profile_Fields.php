<?php

namespace wg\membership\Admin;

use wg\membership\Helpers\User_Data;

if (!defined('ABSPATH')) {
    exit;
}

class User_Profile_Fields {
    public static function register() {
        add_action('show_user_profile', [static::class, 'render']);
        add_action('edit_user_profile', [static::class, 'render']);
        add_action('personal_options_update', [static::class, 'save']);
        add_action('edit_user_profile_update', [static::class, 'save']);
    }

    public static function render($user) {
        $fields = ['firma', 'strasse', 'plz', 'stadt', 'land', 'nutzung', 'steuernummer', 'zahlungsart', 'empfehler_email', 'spickzettel_url', 'kooperationsvereinbarung_ok', 'widerruf_erloschen', 'agb_ok', 'datenschutz_ok'];
        echo '<h2>' . esc_html__('Zusätzliche Benutzerdaten', 'wg-membership-suite') . '</h2><table class="form-table">';
        foreach ($fields as $key) {
            $value = esc_attr(get_user_meta($user->ID, $key, true));
            echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($key) . '</label></th><td><input type="text" class="regular-text" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . $value . '"></td></tr>';
        }
        $products = get_user_meta($user->ID, 'gekaufte_produkte', true);
        echo '<tr><th><label for="gekaufte_produkte">gekaufte_produkte</label></th><td><input type="text" class="regular-text" name="gekaufte_produkte" id="gekaufte_produkte" value="' . esc_attr(is_array($products) ? implode(', ', $products) : (string) $products) . '"></td></tr>';
        echo '</table>';
    }

    public static function save($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        $result = User_Data::save_user_profile_data($user_id, $_POST);
        if (is_wp_error($result)) {
            return;
        }
        foreach (['kooperationsvereinbarung_ok', 'widerruf_erloschen', 'agb_ok', 'datenschutz_ok'] as $key) {
            if (isset($_POST[$key])) {
                update_user_meta($user_id, $key, sanitize_text_field((string) $_POST[$key]));
            }
        }
        if (isset($_POST['gekaufte_produkte'])) {
            $product_list = array_map('trim', explode(',', (string) $_POST['gekaufte_produkte']));
            update_user_meta($user_id, 'gekaufte_produkte', $product_list);
        }
    }
}
