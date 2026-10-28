<?php

namespace wg\membership\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class Assets {
    public static function register() {
        add_action('enqueue_block_editor_assets', [static::class, 'enqueue_editor_assets']);
        add_action('wp_enqueue_scripts', [static::class, 'enqueue_etappenstopp_assets'], 40);
        add_filter('maas_ssg_authenticated_static_html', [static::class, 'inject_authenticated_static_assets']);
    }

    public static function enqueue_editor_assets() {
        $editor_css = get_stylesheet_directory() . '/editor.css';
        if (file_exists($editor_css)) {
            wp_enqueue_style('wg-membership-editor-styles', get_stylesheet_directory_uri() . '/editor.css', [], (string) filemtime($editor_css), 'all');
        }
    }

    public static function should_load_etappenstopp_assets() {
        $forced = apply_filters('wg_membership_load_etappenstopp_assets', null);
        if ($forced !== null) {
            return (bool) $forced;
        }

        if (is_admin() || !is_singular()) {
            return false;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = wp_parse_url($request_uri, PHP_URL_PATH);

        return is_string($path) && (bool) preg_match('~/schreibtisch/etappen(?:/|$)~', $path);
    }

    public static function enqueue_etappenstopp_assets() {
        if (!static::should_load_etappenstopp_assets()) {
            return;
        }

        $css_file = WG_MEMBERSHIP_SUITE_PATH . 'assets/css/etappenstopps.css';

        if (file_exists($css_file)) {
            wp_enqueue_style(
                'wg-membership-etappenstopps',
                WG_MEMBERSHIP_SUITE_URL . 'assets/css/etappenstopps.css',
                [],
                (string) filemtime($css_file),
                'all'
            );
        }

        $js_file = WG_MEMBERSHIP_SUITE_PATH . 'assets/js/etappenstopps.js';
        if (file_exists($js_file)) {
            wp_enqueue_script(
                'wg-membership-etappenstopps',
                WG_MEMBERSHIP_SUITE_URL . 'assets/js/etappenstopps.js',
                [],
                (string) filemtime($js_file),
                true
            );
            wp_add_inline_script(
                'wg-membership-etappenstopps',
                'window.wgMembershipEtappenstopps = ' . wp_json_encode(static::runtime_config()) . ';',
                'before'
            );
        }
    }

    /**
     * Restore the runtime data and assets that a terminating static response
     * cannot receive through wp_enqueue_scripts/wp_footer.
     */
    public static function inject_authenticated_static_assets($html) {
        if (!static::should_load_etappenstopp_assets()) {
            return $html;
        }

        $html = (string) $html;
        $css_file = WG_MEMBERSHIP_SUITE_PATH . 'assets/css/etappenstopps.css';
        $js_file = WG_MEMBERSHIP_SUITE_PATH . 'assets/js/etappenstopps.js';

        if (file_exists($css_file) && strpos($html, 'id="wg-membership-etappenstopps-css"') === false) {
            $stylesheet = sprintf(
                '<link rel="stylesheet" id="wg-membership-etappenstopps-css" href="%s" media="all" />',
                esc_url(WG_MEMBERSHIP_SUITE_URL . 'assets/css/etappenstopps.css?ver=' . filemtime($css_file))
            );
            $html = static::insert_before_closing_tag($html, '</head>', $stylesheet);
        }

        $footer = '';
        if (strpos($html, 'window.wgMembershipEtappenstopps') === false) {
            $footer .= '<script id="wg-membership-etappenstopps-config">window.wgMembershipEtappenstopps = '
                . wp_json_encode(static::runtime_config())
                . ';</script>';
        }
        if (file_exists($js_file) && strpos($html, 'id="wg-membership-etappenstopps-js"') === false) {
            $footer .= sprintf(
                '<script id="wg-membership-etappenstopps-js" src="%s"></script>',
                esc_url(WG_MEMBERSHIP_SUITE_URL . 'assets/js/etappenstopps.js?ver=' . filemtime($js_file))
            );
        }

        return $footer === '' ? $html : static::insert_before_closing_tag($html, '</body>', $footer);
    }

    private static function insert_before_closing_tag($html, $tag, $markup) {
        $position = strripos($html, $tag);
        if ($position === false) {
            return $html . $markup;
        }

        return substr($html, 0, $position) . $markup . "\n" . substr($html, $position);
    }

    private static function runtime_config() {
        return [
            'spickzettelUrl' => esc_url_raw(rest_url('custom/v1/spickzettel-url')),
            'restNonce' => wp_create_nonce('wp_rest'),
        ];
    }

}
