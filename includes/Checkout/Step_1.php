<?php

namespace wg\membership\Checkout;

use wg\membership\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Step_1 {
    public static function register() {
        add_action('init', [Order_Context::class, 'capture_checkout_offer_from_request'], 2);
        add_action('wpcf7_init', [static::class, 'register_cf7_tags']);
        add_action('template_redirect', [static::class, 'handle_cf7_step1_offer_redirect'], 1);
        add_action('template_redirect', [static::class, 'disable_checkout_cache'], 0);
    }

    public static function get_cf7_angebote_for_category($category_slug) {
        $cache_key = 'wg_cf7_offers_' . sanitize_key($category_slug);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $query = new \WP_Query([
            'post_type' => 'wg_angebot',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
            'tax_query' => [[
                'taxonomy' => 'category',
                'field' => 'slug',
                'terms' => [sanitize_key($category_slug)],
            ]],
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        $offers = [];
        foreach ($query->posts as $post_id) {
            $offers[] = ['id' => (int) $post_id, 'title' => get_the_title($post_id)];
        }
        set_transient($cache_key, $offers, 5 * MINUTE_IN_SECONDS);

        return $offers;
    }

    public static function render_cf7_angebote_select($field_name, $category_slug, $required = false, $placeholder = '') {
        $offers = self::get_cf7_angebote_for_category($category_slug);
        $required_attr = $required ? ' required aria-required="true"' : '';
        $html = '<span class="wpcf7-form-control-wrap ' . esc_attr($field_name) . '"><select name="' . esc_attr($field_name) . '" class="wpcf7-form-control wpcf7-select"' . $required_attr . '>';
        $html .= '<option value="">' . esc_html($placeholder) . '</option>';
        foreach ($offers as $offer) {
            $html .= '<option value="' . esc_attr($offer['id']) . '">' . esc_html($offer['title']) . '</option>';
        }
        return $html . '</select></span>';
    }

    public static function register_cf7_tags() {
        if (!function_exists('wpcf7_add_form_tag')) {
            return;
        }
        wpcf7_add_form_tag('wg_angebote_abo', [static::class, 'tag_angebote_abo_render']);
        wpcf7_add_form_tag('wg_angebote_erweiterung', [static::class, 'tag_angebote_erweiterung_render']);
    }

    public static function tag_angebote_abo_render() {
        return self::render_cf7_angebote_select('wg_angebote_abo_id', 'abos', true, __('Bitte wählen', 'wg-membership-suite'));
    }

    public static function tag_angebote_erweiterung_render() {
        return self::render_cf7_angebote_select('wg_angebote_erweiterung_id', 'erweiterungen', false, __('Keine Erweiterung', 'wg-membership-suite'));
    }

    public static function handle_cf7_step1_offer_redirect() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['_wpcf7'], $_POST['wg_angebote_abo_id'])) {
            return;
        }

        $abo_id = absint($_POST['wg_angebote_abo_id']);
        $erweiterung_id = isset($_POST['wg_angebote_erweiterung_id']) ? absint($_POST['wg_angebote_erweiterung_id']) : 0;

        if (!Pricing::get_valid_angebot_post($abo_id) || !Pricing::angebot_has_category_slug($abo_id, 'abo')) {
            wp_die(__('Ungültige Abo-Auswahl.', 'wg-membership-suite'));
        }

        $query_args = ['angebot' => $abo_id];
        if ($erweiterung_id > 0) {
            if (!Pricing::get_valid_angebot_post($erweiterung_id) || !Pricing::angebot_has_category_slug($erweiterung_id, 'erweiterung')) {
                wp_die(__('Ungültige Erweiterungs-Auswahl.', 'wg-membership-suite'));
            }
            $query_args['erweiterung'] = $erweiterung_id;
        }

        if (is_user_logged_in()) {
            Order_Context::set_last_offer_for_user(get_current_user_id(), $abo_id, $erweiterung_id);
        }

        $target = add_query_arg($query_args, Config::get_checkout_page_url('step_2'));
        wp_safe_redirect($target);
        exit;
    }

    public static function disable_checkout_cache() {
        if (!Config::is_checkout_page('step_2') && !Config::is_checkout_page('step_3') && !Config::is_checkout_page('step_4')) {
            return;
        }

        foreach (['DONOTCACHEPAGE', 'DONOTCACHEOBJECT', 'DONOTCACHEDB'] as $constant) {
            if (!defined($constant)) {
                define($constant, true);
            }
        }
        nocache_headers();
    }
}
