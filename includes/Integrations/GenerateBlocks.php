<?php

namespace wg\membership\Integrations;

use wg\membership\LMS\Quiz_Status;

if (!defined('ABSPATH')) {
    exit;
}

class GenerateBlocks {
    public static function register() {
        add_filter('render_block', [static::class, 'replace_logout_placeholder'], 10, 2);
        add_filter('render_block', [static::class, 'sanitize_icons'], 10, 2);
        add_filter('render_block_generateblocks/text', [static::class, 'render_etappenstopp_icons'], 20, 3);
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

    private static function stop_status_svg() {
        return '<svg aria-hidden="true" role="img" height="1em" width="1em" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M504 256c0 136.967-111.033 248-248 248S8 392.967 8 256 119.033 8 256 8s248 111.033 248 248zM227.314 387.314l184-184c6.248-6.248 6.248-16.379 0-22.627l-22.627-22.627c-6.248-6.249-16.379-6.249-22.628 0L216 308.118l-70.059-70.059c-6.248-6.248-16.379-6.248-22.628 0l-22.627 22.627c-6.248 6.248-6.248 16.379 0 22.627l104 104c6.249 6.249 16.379 6.249 22.628.001z"></path></svg>';
    }

    private static function entry_closed_svg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14" height="14" width="14" aria-hidden="true"><rect fill="currentColor" transform="matrix(-1 0 0 1 2 0)" rx="1" height="2" width="2"></rect><rect fill="currentColor" transform="matrix(-1 0 0 1 2 6)" rx="1" height="2" width="2"></rect><rect fill="currentColor" transform="matrix(-1 0 0 1 2 12)" rx="1" height="2" width="2"></rect><rect fill="currentColor" transform="matrix(-1 0 0 1 8 0)" rx="1" height="2" width="2"></rect><rect fill="currentColor" transform="matrix(-1 0 0 1 8 6)" rx="1" height="2" width="2"></rect><rect fill="currentColor" transform="matrix(-1 0 0 1 8 12)" rx="1" height="2" width="2"></rect><rect fill="currentColor" transform="matrix(-1 0 0 1 14 0)" rx="1" height="2" width="2"></rect><rect fill="currentColor" transform="matrix(-1 0 0 1 14 6)" rx="1" height="2" width="2"></rect><rect fill="currentColor" transform="matrix(-1 0 0 1 14 12)" rx="1" height="2" width="2"></rect></svg>';
    }

    private static function entry_open_svg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 12 12" height="12" width="12" aria-hidden="true"><rect fill="currentColor" transform="rotate(-45 0 11.3137)" rx="0.5" height="1" width="16" y="11.3137"></rect><rect fill="currentColor" transform="matrix(-0.707107 -0.707107 -0.707107 0.707107 12 11.3137)" rx="0.5" height="1" width="16"></rect></svg>';
    }

    private static function resolve_loop_post_id($block, $instance) {
        if (is_object($instance) && isset($instance->context['postId'])) {
            return (int) $instance->context['postId'];
        }
        if (isset($block['context']['postId'])) {
            return (int) $block['context']['postId'];
        }
        return 0;
    }

    private static function add_class_and_attribute($content, $tag, $class_name, $attribute, $value) {
        return preg_replace_callback(
            '/<' . preg_quote($tag, '/') . '\b[^>]*>/i',
            static function ($matches) use ($class_name, $attribute, $value) {
                $opening = $matches[0];
                if (preg_match('/\bclass="([^"]*)"/i', $opening)) {
                    $opening = preg_replace('/\bclass="([^"]*)"/i', 'class="$1 ' . $class_name . '"', $opening, 1);
                } else {
                    $opening = substr($opening, 0, -1) . ' class="' . $class_name . '">';
                }
                if (!preg_match('/\b' . preg_quote($attribute, '/') . '=/i', $opening)) {
                    $opening = substr($opening, 0, -1) . ' ' . $attribute . '="' . esc_attr($value) . '">';
                }
                return $opening;
            },
            $content,
            1
        );
    }

    private static function render_entry_toggle_icons($block_content) {
        $icons = '<span class="gb-accordion__toggle-icon-open wg-entry-toggle-icon__closed">' . static::entry_closed_svg() . '</span>'
            . '<span class="gb-accordion__toggle-icon-close wg-entry-toggle-icon__open">' . static::entry_open_svg() . '</span>';
        $updated = preg_replace(
            '/<span\b[^>]*class="([^"]*\bgb-shape\b[^"]*)"[^>]*>.*?<\/span>/is',
            '<span class="$1 gb-icon gb-accordion__toggle-icon wg-entry-toggle-icon" aria-hidden="true">' . $icons . '</span>',
            $block_content,
            1
        );
        if (!is_string($updated)) {
            return $block_content;
        }
        return static::add_class_and_attribute($updated, 'button', 'wg-etappenstopp-entry-toggle', 'aria-expanded', 'false');
    }

    public static function render_etappenstopp_icons($block_content, $block, $instance = null) {
        if (!is_array($block) || ($block['blockName'] ?? '') !== 'generateblocks/text') {
            return $block_content;
        }

        $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];
        $classes = preg_split('/\s+/', (string) ($attrs['className'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        if (in_array('toggle-box', $classes, true) || in_array('open-spickzettel', $classes, true)) {
            return static::render_entry_toggle_icons($block_content);
        }

        $raw_content = (string) ($block['innerHTML'] ?? '');
        if (strpos($raw_content, '{{post_meta key:wg_stop_title}}') === false) {
            return $block_content;
        }

        $block_content = static::add_class_and_attribute($block_content, 'h4', 'wg-etappenstopp-title', 'data-stop-status', 'incomplete');
        $post_id = static::resolve_loop_post_id($block, $instance);
        if (!$post_id || !Quiz_Status::user_has_completed_all_quizzes_for_post($post_id)) {
            return $block_content;
        }

        $icon = '<span class="gb-shape gb-icon wg-stop-status-icon" aria-label="Besenwagen-Check abgeschlossen">' . static::stop_status_svg() . '</span>';
        $updated = preg_replace('/(<h4\b[^>]*>)/i', '$1' . $icon, $block_content, 1);
        if (!is_string($updated)) {
            return $block_content;
        }
        return str_replace('data-stop-status="incomplete"', 'data-stop-status="complete"', $updated);
    }

    public static function admin_notice() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->base === 'post') {
            echo '<div class="notice notice-info"><p>' . esc_html__('Hinweis: Verwende keine Dynamic Tags in SVG- oder Icon-Feldern von GenerateBlocks.', 'wg-membership-suite') . '</p></div>';
        }
    }
}
