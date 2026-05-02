<?php

namespace wg\membership\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class Assets {
    public static function register() {
        add_action('enqueue_block_editor_assets', [static::class, 'enqueue_editor_assets']);
    }

    public static function enqueue_editor_assets() {
        $editor_css = get_stylesheet_directory() . '/editor.css';
        if (file_exists($editor_css)) {
            wp_enqueue_style('wg-membership-editor-styles', get_stylesheet_directory_uri() . '/editor.css', [], (string) filemtime($editor_css), 'all');
        }
    }
}
