<?php

namespace wg\membership\Admin;

use wg\membership\Checkout\Pricing;

if (!defined('ABSPATH')) {
    exit;
}

class Angebot_Meta_Boxes {
    public static function register() {
        add_action('add_meta_boxes', [static::class, 'add_metaboxes']);
        add_action('save_post', [static::class, 'save_angebot_meta']);
    }

    public static function add_metaboxes() {
        add_meta_box('wg_angebot_details', __('Angebotsdetails', 'wg-membership-suite'), [static::class, 'render_angebot_fields'], 'wg_angebot', 'normal', 'high');
    }

    public static function render_angebot_fields($post) {
        $select_options = Pricing::get_angebot_select_options();
        $fields = [
            'heading_text' => ['textarea', 'Heading Text'],
            'benefit_1' => ['text', 'Benefit 1'],
            'benefit_2' => ['text', 'Benefit 2'],
            'benefit_3' => ['text', 'Benefit 3'],
            'benefit_4' => ['text', 'Benefit 4'],
            'benefit_5' => ['text', 'Benefit 5'],
            'footer_text' => ['textarea', 'Footer Text'],
            'nettopreis' => ['number', 'Nettopreis'],
            'mehrwertsteuer' => ['select', 'Mehrwertsteuer', $select_options['mehrwertsteuer']],
            'endpreis' => ['readonly', 'Endpreis'],
            'rechnungsstellung' => ['select', 'Rechnungsstellung', $select_options['rechnungsstellung']],
            'intervall' => ['select', 'Intervall', $select_options['intervall']],
            'zugang' => ['select', 'Zugang', $select_options['zugang']],
            'type' => ['select', 'Typ', $select_options['type']],
            'zahlungsform' => ['select', 'Zahlungsform', $select_options['zahlungsform']],
            'renewal' => ['select', 'Renewal', $select_options['renewal']],
        ];

        wp_nonce_field('wg_angebot_save', 'wg_angebot_nonce');
        echo '<table class="form-table">';
        foreach ($fields as $key => $data) {
            $value = esc_attr(get_post_meta($post->ID, $key, true));
            echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($data[1]) . '</label></th><td>';
            if ($data[0] === 'textarea') {
                echo '<textarea class="large-text" rows="3" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '">' . esc_textarea($value) . '</textarea>';
            } elseif ($data[0] === 'select') {
                echo '<select name="' . esc_attr($key) . '" id="' . esc_attr($key) . '">';
                foreach ($data[2] as $option_value => $label) {
                    echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
            } else {
                echo '<input class="regular-text" type="' . esc_attr($data[0]) . '" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . $value . '"' . ($data[0] === 'readonly' ? ' readonly' : '') . '>';
            }
            echo '</td></tr>';
        }
        echo '</table>';
    }

    public static function save_angebot_meta($post_id) {
        if (get_post_type($post_id) !== 'wg_angebot' || !isset($_POST['wg_angebot_nonce']) || !wp_verify_nonce($_POST['wg_angebot_nonce'], 'wg_angebot_save') || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
            return;
        }

        foreach (['heading_text', 'benefit_1', 'benefit_2', 'benefit_3', 'benefit_4', 'benefit_5', 'footer_text', 'nettopreis', 'mehrwertsteuer', 'rechnungsstellung', 'intervall', 'zugang', 'type', 'zahlungsform', 'renewal'] as $key) {
            if (!isset($_POST[$key])) {
                continue;
            }
            if ($key === 'nettopreis') {
                update_post_meta($post_id, $key, Pricing::parse_decimal($_POST[$key]));
            } elseif (in_array($key, ['mehrwertsteuer', 'rechnungsstellung', 'intervall', 'zugang', 'type', 'zahlungsform', 'renewal'], true)) {
                update_post_meta($post_id, $key, Pricing::sanitize_angebot_select_value($key, $_POST[$key]));
            } else {
                update_post_meta($post_id, $key, sanitize_text_field((string) $_POST[$key]));
            }
        }

        if ((string) get_post_meta($post_id, 'intervall', true) === 'ohne_intervall') {
            update_post_meta($post_id, 'rechnungsstellung', 'einmalig');
        }
        $price = Pricing::calculate_angebot_price(get_post_meta($post_id, 'nettopreis', true), get_post_meta($post_id, 'mehrwertsteuer', true));
        update_post_meta($post_id, 'endpreis', $price['endpreis']);

        $type = (string) get_post_meta($post_id, 'type', true);
        if (in_array($type, ['abo', 'paket'], true)) {
            update_post_meta($post_id, 'rollen_slug', Pricing::ensure_angebot_role_exists($post_id));
        }
    }
}
