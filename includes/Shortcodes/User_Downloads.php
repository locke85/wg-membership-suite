<?php

namespace wg\membership\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

class User_Downloads {
    public static function register() {
        add_shortcode('user_pdf_downloads', [static::class, 'render']);
        add_filter('attachment_fields_to_edit', [static::class, 'attachment_fields_to_edit'], 10, 2);
        add_filter('attachment_fields_to_save', [static::class, 'attachment_fields_to_save'], 10, 2);
    }

    public static function attachment_fields_to_edit($form_fields, $post) {
        if ($post->post_mime_type !== 'application/pdf' || !current_user_can('manage_options')) {
            return $form_fields;
        }
        $form_fields['wg_pdf_user_id'] = ['label' => __('Zugewiesene User-ID', 'wg-membership-suite'), 'input' => 'text', 'value' => absint(get_post_meta($post->ID, '_pdf_user_id', true)), 'helps' => __('Gib die ID des Nutzers ein, der Zugriff auf diese PDF erhalten soll.', 'wg-membership-suite')];
        return $form_fields;
    }

    public static function attachment_fields_to_save($post, $attachment) {
        if (!isset($post['ID']) || get_post_mime_type($post['ID']) !== 'application/pdf' || !current_user_can('manage_options')) {
            return $post;
        }
        if (isset($attachment['wg_pdf_user_id'])) {
            $user_id = absint($attachment['wg_pdf_user_id']);
            if ($user_id && get_user_by('id', $user_id)) {
                update_post_meta($post['ID'], '_pdf_user_id', $user_id);
            } else {
                delete_post_meta($post['ID'], '_pdf_user_id');
            }
        }
        return $post;
    }

    public static function render() {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Bitte logge dich ein, um deine PDF-Downloads zu sehen.', 'wg-membership-suite') . '</p>';
        }

        $query = new \WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_mime_type' => 'application/pdf',
            'meta_query' => [[
                'key' => '_pdf_user_id',
                'value' => get_current_user_id(),
            ]],
        ]);

        if (!$query->have_posts()) {
            return '<p>' . esc_html__('Keine PDF-Downloads verfügbar.', 'wg-membership-suite') . '</p>';
        }

        $items = [];
        while ($query->have_posts()) {
            $query->the_post();
            $url = wp_get_attachment_url(get_the_ID());
            if ($url) {
                $items[] = '<li><a href="' . esc_url($url) . '" download>' . esc_html(get_the_title()) . '</a></li>';
            }
        }
        wp_reset_postdata();

        return !empty($items) ? '<ul class="user-pdf-downloads">' . implode('', $items) . '</ul>' : '<p>' . esc_html__('Keine PDF-Downloads verfügbar.', 'wg-membership-suite') . '</p>';
    }
}
