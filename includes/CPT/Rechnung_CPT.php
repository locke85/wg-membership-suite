<?php

namespace wg\membership\CPT;

if (!defined('ABSPATH')) {
    exit;
}

class Rechnung_CPT {
    public static function register() {
        register_post_type('wg_rechnung', [
            'public' => false,
            'publicly_queryable' => true,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'supports' => ['custom-fields'],
            'capability_type' => 'post',
            'rewrite' => false,
            'query_var' => true,
            'has_archive' => false,
        ]);
    }
}
