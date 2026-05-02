<?php

namespace wg\membership\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

class GenerateBlocks {
    public static function register() {
        add_filter('render_block', [static::class, 'replace_logout_placeholder'], 10, 2);
        add_filter('render_block', [static::class, 'sanitize_icons'], 10, 2);
        add_filter('content_save_pre', [static::class, 'remove_dynamic_tags_from_icons'], 10);
        add_action('admin_notices', [static::class, 'admin_notice']);
    }

    public static function replace_logout_placeholder($content, $block) {
        if (is_array($block) && strpos((string) $content, '{{logout_url}}') !== false) {
            $content = str_replace(
                '{{logout_url}}',
                esc_url(wp_logout_url(home_url('/abmelden-erfolgreich/'))),
                $content
            );
        }
        return $content;
    }

    public static function remove_dynamic_tags_from_icons($content) {
        if (strpos((string) $content, 'gb-icon') === false) {
            return $content;
        }
        return preg_replace_callback('/(<span[^>]*class="[^"]*gb-icon[^"]*"[^>]*>)(.*?)(<\/span>)/s', static function ($matches) {
            return $matches[1] . preg_replace('/\{\{.*?\}\}/', '', $matches[2]) . $matches[3];
        }, $content);
    }

    public static function sanitize_icons($block_content, $block) {
        if (!is_array($block) || ($block['blockName'] ?? '') !== 'generateblocks/text') {
            return $block_content;
        }
        return static::remove_dynamic_tags_from_icons($block_content);
    }

    public static function admin_notice() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->base === 'post') {
            echo '<div class="notice notice-info"><p>' . esc_html__('Hinweis: Verwende keine Dynamic Tags in SVG- oder Icon-Feldern von GenerateBlocks.', 'wg-membership-suite') . '</p></div>';
        }
    }
}
