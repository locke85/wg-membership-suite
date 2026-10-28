<?php

namespace wg\membership\Checkout;

use wg\membership\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Step_1 {
    public static function register() {
        add_filter('cf7msm_force_session', [static::class, 'force_cf7msm_session_for_mapped_domain']);
        add_filter('render_block', [static::class, 'render_offer_checkout_links'], 30, 2);
        add_action('init', [Order_Context::class, 'capture_checkout_offer_from_request'], 2);
        add_action('wpcf7_init', [static::class, 'register_cf7_tags']);
        add_action('template_redirect', [static::class, 'handle_cf7_step1_offer_redirect'], 1);
        add_action('template_redirect', [static::class, 'disable_checkout_cache'], 0);
    }

    public static function normalize_request_host($host) {
        $host = strtolower(trim((string) $host));
        $host = preg_replace('/:\d+$/', '', $host);
        $host = trim((string) $host, '.');

        return preg_match('/^[a-z0-9.-]+$/', $host) ? $host : '';
    }

    public static function cookie_domain_covers_host($cookie_domain, $request_host) {
        $request_host = static::normalize_request_host($request_host);
        $cookie_domain = static::normalize_request_host(ltrim((string) $cookie_domain, '.'));
        if ($request_host === '' || $cookie_domain === '') {
            return true;
        }

        return $request_host === $cookie_domain
            || substr($request_host, -strlen('.' . $cookie_domain)) === '.' . $cookie_domain;
    }

    public static function should_force_cf7msm_session($already_forced, $request_host, $cookie_domain) {
        if ($already_forced) {
            return true;
        }

        $request_host = static::normalize_request_host($request_host);
        if ($request_host === '') {
            return false;
        }

        return !static::cookie_domain_covers_host($cookie_domain, $request_host);
    }

    public static function force_cf7msm_session_for_mapped_domain($already_forced) {
        $request_host = isset($_SERVER['HTTP_HOST']) ? wp_unslash($_SERVER['HTTP_HOST']) : '';
        $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
        $force_session = static::should_force_cf7msm_session($already_forced, $request_host, $cookie_domain);
        if (!$force_session || PHP_SESSION_ACTIVE === session_status() || headers_sent()) {
            return $force_session;
        }

        $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $path,
            'domain' => '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');

        return true;
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
        $selected_offer_id = self::get_requested_prefill_offer_id($field_name, $category_slug);
        $required_attr = $required ? ' required aria-required="true"' : '';
        $html = '<span class="wpcf7-form-control-wrap ' . esc_attr($field_name) . '"><select name="' . esc_attr($field_name) . '" class="wpcf7-form-control wpcf7-select"' . $required_attr . '>';
        $html .= '<option value="">' . esc_html($placeholder) . '</option>';
        foreach ($offers as $offer) {
            $html .= '<option value="' . esc_attr($offer['id']) . '"' . selected($selected_offer_id, (int) $offer['id'], false) . '>' . esc_html($offer['title']) . '</option>';
        }
        return $html . '</select></span>';
    }

    public static function get_requested_prefill_offer_id($field_name, $category_slug, $query = null) {
        if ((string) $field_name !== 'wg_angebote_abo_id') {
            return 0;
        }

        $query = is_array($query) ? $query : $_GET;
        if (!isset($query['angebot']) || !is_scalar($query['angebot'])) {
            return 0;
        }

        $offer_id = absint(wp_unslash($query['angebot']));
        if ($offer_id <= 0 || !Pricing::get_valid_angebot_post($offer_id)) {
            return 0;
        }

        return Pricing::angebot_has_category_slug($offer_id, $category_slug) ? $offer_id : 0;
    }

    public static function is_checkout_step_1_href($href, $checkout_url, $checkout_slug = '') {
        $href = html_entity_decode(trim((string) $href), ENT_QUOTES, 'UTF-8');
        $checkout_url = trim((string) $checkout_url);
        if ($href === '' || $checkout_url === '') {
            return false;
        }

        $href_path = (string) wp_parse_url($href, PHP_URL_PATH);
        $checkout_path = (string) wp_parse_url($checkout_url, PHP_URL_PATH);
        if ($href_path === '' || $checkout_path === '') {
            return false;
        }

        $paths_match = trailingslashit($href_path) === trailingslashit($checkout_path);
        $checkout_slug = trim((string) $checkout_slug, '/');
        if (!$paths_match && ($checkout_slug === '' || basename(rtrim($href_path, '/')) !== basename($checkout_slug))) {
            return false;
        }

        $href_host = strtolower((string) wp_parse_url($href, PHP_URL_HOST));
        $checkout_host = strtolower((string) wp_parse_url($checkout_url, PHP_URL_HOST));

        return $href_host === '' || $checkout_host === '' || $href_host === $checkout_host;
    }

    public static function add_offer_parameter_to_checkout_links($block_content, $offer_id, $checkout_url, $checkout_slug = '') {
        $offer_id = absint($offer_id);
        if ($offer_id <= 0 || stripos((string) $block_content, '<a') === false) {
            return $block_content;
        }

        $updated = preg_replace_callback(
            '/(<a\b[^>]*\bhref=)(["\'])(.*?)\2([^>]*>)/is',
            static function ($matches) use ($offer_id, $checkout_url, $checkout_slug) {
                $href = html_entity_decode((string) $matches[3], ENT_QUOTES, 'UTF-8');
                if (!static::is_checkout_step_1_href($href, $checkout_url, $checkout_slug)) {
                    return $matches[0];
                }

                $url = add_query_arg('angebot', $offer_id, $href);
                return $matches[1] . $matches[2] . esc_url($url) . $matches[2] . $matches[4];
            },
            (string) $block_content
        );

        return is_string($updated) ? $updated : $block_content;
    }

    public static function render_offer_checkout_links($block_content, $block = []) {
        if (is_admin() || !is_singular('wg_angebot')) {
            return $block_content;
        }

        $offer_id = absint(get_queried_object_id());
        if (!Pricing::get_valid_angebot_post($offer_id) || !Pricing::angebot_has_category_slug($offer_id, 'abos')) {
            return $block_content;
        }

        return static::add_offer_parameter_to_checkout_links(
            $block_content,
            $offer_id,
            Config::get_checkout_page_url('step_1'),
            Config::get_checkout_page_slug('step_1')
        );
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
