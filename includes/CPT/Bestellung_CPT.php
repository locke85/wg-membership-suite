<?php

namespace wg\membership\CPT;

if (!defined('ABSPATH')) {
    exit;
}

class Bestellung_CPT {
    public static function register() {
        register_post_type('wg_bestellung', [
            'labels' => [
                'name' => __('Bestellungen', 'wg-membership-suite'),
                'singular_name' => __('Bestellung', 'wg-membership-suite'),
            ],
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'supports' => ['title', 'custom-fields', 'comments'],
            'capability_type' => 'post',
            'rewrite' => false,
            'query_var' => false,
            'has_archive' => false,
            'menu_icon' => 'dashicons-cart',
        ]);
    }
}
