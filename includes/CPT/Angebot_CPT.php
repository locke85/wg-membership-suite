<?php

namespace wg\membership\CPT;

if (!defined('ABSPATH')) {
    exit;
}

class Angebot_CPT {
    public static function register() {
        register_post_type('wg_angebot', [
            'labels' => [
                'name' => __('Angebote', 'wg-membership-suite'),
                'singular_name' => __('Angebot', 'wg-membership-suite'),
            ],
            'public' => true,
            'has_archive' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author'],
            'menu_icon' => 'dashicons-cart',
            'taxonomies' => ['category'],
            'rewrite' => ['slug' => 'angebote'],
            'rest_base' => 'angebote',
            'capability_type' => 'post',
            'show_in_nav_menus' => true,
            'show_admin_column' => true,
        ]);
    }
}
