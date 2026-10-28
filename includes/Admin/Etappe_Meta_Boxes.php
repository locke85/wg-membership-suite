<?php

namespace wg\membership\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Etappe_Meta_Boxes {
    public const POST_BOX_ID = 'wg_custom_fields_post';
    public const PAGE_BOX_ID = 'wg_custom_fields_box';
    public const POST_NONCE_ACTION = 'wg_save_post_custom_fields';
    public const POST_NONCE_NAME = 'wg_post_custom_fields_nonce';
    public const PAGE_NONCE_ACTION = 'wg_save_custom_fields';
    public const PAGE_NONCE_NAME = 'wg_custom_fields_nonce';

    public static function register() {
        add_action('add_meta_boxes_post', [static::class, 'add_post_metabox']);
        add_action('add_meta_boxes_page', [static::class, 'add_page_metabox']);
        add_action('save_post_post', [static::class, 'save_post_fields'], 10, 2);
        add_action('save_post_page', [static::class, 'save_page_fields'], 10, 2);
        add_action('admin_enqueue_scripts', [static::class, 'enqueue_page_media']);
    }

    public static function post_fields() {
        return [
            'wg_stop_title' => ['type' => 'text', 'label' => __('Stoptitel (H1)', 'wg-membership-suite'), 'placeholder' => __('z. B. Das große Finale', 'wg-membership-suite')],
            'wg_video_title' => ['type' => 'text', 'label' => __('Video-Titel', 'wg-membership-suite'), 'placeholder' => __('Titel', 'wg-membership-suite')],
            'wg_video_length' => ['type' => 'text', 'label' => __('Video-Dauer', 'wg-membership-suite'), 'placeholder' => __('mm:ss', 'wg-membership-suite'), 'compact' => true],
            'wg_spickzettel_title' => ['type' => 'text', 'label' => __('Spickzettel-Titel', 'wg-membership-suite'), 'placeholder' => __('Titel', 'wg-membership-suite')],
            'wg_spickzettel_effort' => ['type' => 'text', 'label' => __('Spickzettel-Zeitaufwand', 'wg-membership-suite'), 'placeholder' => __('mm:ss', 'wg-membership-suite'), 'compact' => true],
            'wg_besenwagen_title' => ['type' => 'text', 'label' => __('Besenwagen-Titel', 'wg-membership-suite'), 'placeholder' => __('Titel', 'wg-membership-suite')],
            'wg_besenwagen_duration' => ['type' => 'text', 'label' => __('Besenwagen-Dauer', 'wg-membership-suite'), 'placeholder' => __('mm:ss', 'wg-membership-suite'), 'compact' => true],
            'wg_besenwagen_intro' => ['type' => 'textarea', 'label' => __('Besenwagen Intro (P)', 'wg-membership-suite')],
            'wg_besenwagen_code' => ['type' => 'text', 'label' => __('Besenwagen-Shortcode', 'wg-membership-suite'), 'placeholder' => '[chained-quiz 32]', 'description' => __('Die Quiz-ID muss aus diesem Multisite-Blog stammen.', 'wg-membership-suite')],
        ];
    }

    public static function page_fields() {
        return [
            'wg_etappe_title' => ['type' => 'text', 'label' => __('Etappentitel (H1)', 'wg-membership-suite')],
            'wg_p_introtext' => ['type' => 'textarea', 'label' => __('Introtext', 'wg-membership-suite'), 'description' => __('Empfohlen: maximal 100 Wörter.', 'wg-membership-suite')],
            'wg_img_intro' => ['type' => 'image', 'label' => __('Rennbahn mit Bulli', 'wg-membership-suite')],
            'wg_h4_headline1' => ['type' => 'text', 'label' => __('Headline 1 (Ablauf)', 'wg-membership-suite')],
            'wg_list_bullets1' => ['type' => 'text', 'label' => __('Webinar J/N', 'wg-membership-suite')],
            'wg_list_bullets2' => ['type' => 'text', 'label' => __('Anzahl Etappenstopps', 'wg-membership-suite')],
            'wg_list_bullets3' => ['type' => 'text', 'label' => __('Anzahl Spickzettel', 'wg-membership-suite')],
            'wg_list_bullets4' => ['type' => 'text', 'label' => __('Anzahl Anleitungsvideos', 'wg-membership-suite')],
            'wg_list_bullets5' => ['type' => 'text', 'label' => __('Anzahl Besenwagen-Checks', 'wg-membership-suite')],
            'wg_h4_headline2' => ['type' => 'text', 'label' => __('Headline 2 (Zeitbedarf)', 'wg-membership-suite')],
            'wg_h4_headline3' => ['type' => 'text', 'label' => __('Headline 3 (Ergebnis)', 'wg-membership-suite')],
            'wg_h4_headline4' => ['type' => 'text', 'label' => __('Headline 4 (Leer)', 'wg-membership-suite')],
        ];
    }

    public static function add_post_metabox() {
        add_meta_box(
            self::POST_BOX_ID,
            __('Zusatzinfos: Video, Spickzettel & Besenwagen', 'wg-membership-suite'),
            [static::class, 'render_post_fields'],
            'post',
            'normal',
            'default'
        );
    }

    public static function add_page_metabox() {
        add_meta_box(
            self::PAGE_BOX_ID,
            __('Etappeninhalte', 'wg-membership-suite'),
            [static::class, 'render_page_fields'],
            'page',
            'normal',
            'default'
        );
    }

    public static function render_post_fields($post) {
        wp_nonce_field(self::POST_NONCE_ACTION, self::POST_NONCE_NAME);
        self::render_fields($post, self::post_fields());
    }

    public static function render_page_fields($post) {
        wp_nonce_field(self::PAGE_NONCE_ACTION, self::PAGE_NONCE_NAME);
        self::render_fields($post, self::page_fields());
        self::render_media_script();
    }

    private static function render_fields($post, array $fields) {
        echo '<style>';
        echo '.wg-membership-fields{width:100%;border-collapse:collapse}.wg-membership-fields th{width:220px;text-align:left;vertical-align:top;padding:12px 12px 12px 0}.wg-membership-fields td{padding:8px 0}.wg-membership-fields .wg-field-compact{max-width:120px}.wg-membership-fields img{display:block;max-width:150px;height:auto;margin:0 0 10px}';
        echo '</style>';
        echo '<table class="form-table wg-membership-fields"><tbody>';

        foreach ($fields as $key => $field) {
            $value = get_post_meta($post->ID, $key, true);
            $type = isset($field['type']) ? $field['type'] : 'text';
            $description = isset($field['description']) ? $field['description'] : '';

            echo '<tr><th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($field['label']) . '</label></th><td>';

            if ($type === 'textarea') {
                echo '<textarea class="widefat" rows="3" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '">' . esc_textarea($value) . '</textarea>';
            } elseif ($type === 'image') {
                $image_url = $value ? wp_get_attachment_url(absint($value)) : '';
                if (is_string($image_url) && $image_url !== '') {
                    echo '<img class="wg-img-preview" src="' . esc_url($image_url) . '" alt="">';
                }
                echo '<input type="hidden" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '"> ';
                echo '<button type="button" class="button wg-img-select" data-target="' . esc_attr($key) . '">' . esc_html__('Bild auswählen', 'wg-membership-suite') . '</button>';
            } else {
                $classes = !empty($field['compact']) ? 'regular-text wg-field-compact' : 'widefat';
                $placeholder = isset($field['placeholder']) ? $field['placeholder'] : '';
                echo '<input type="text" class="' . esc_attr($classes) . '" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '">';
            }

            if ($description !== '') {
                echo '<p class="description">' . esc_html($description) . '</p>';
            }
            echo '</td></tr>';
        }

        echo '</tbody></table>';
    }

    private static function render_media_script() {
        ?>
        <script>
        jQuery(function ($) {
            $('.wg-img-select').off('click.wgEtappe').on('click.wgEtappe', function (event) {
                event.preventDefault();
                const button = $(this);
                const target = $('#' + button.data('target'));
                const uploader = wp.media({
                    title: <?php echo wp_json_encode(__('Bild auswählen', 'wg-membership-suite')); ?>,
                    button: { text: <?php echo wp_json_encode(__('Bild übernehmen', 'wg-membership-suite')); ?> },
                    library: { type: 'image' },
                    multiple: false
                });

                uploader.on('select', function () {
                    const attachment = uploader.state().get('selection').first().toJSON();
                    target.val(attachment.id);
                    target.siblings('.wg-img-preview').remove();
                    $('<img>', { class: 'wg-img-preview', src: attachment.url, alt: '' }).insertBefore(target);
                });

                uploader.open();
            });
        });
        </script>
        <?php
    }

    public static function enqueue_page_media($hook_suffix) {
        if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && isset($screen->post_type) && $screen->post_type === 'page') {
            wp_enqueue_media();
        }
    }

    public static function save_post_fields($post_id, $post = null) {
        self::save_fields($post_id, 'post', self::POST_NONCE_NAME, self::POST_NONCE_ACTION, self::post_fields());
    }

    public static function save_page_fields($post_id, $post = null) {
        self::save_fields($post_id, 'page', self::PAGE_NONCE_NAME, self::PAGE_NONCE_ACTION, self::page_fields());
    }

    private static function save_fields($post_id, $expected_post_type, $nonce_name, $nonce_action, array $fields) {
        if (get_post_type($post_id) !== $expected_post_type) {
            return;
        }

        if (!isset($_POST[$nonce_name]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_name])), $nonce_action)) {
            return;
        }

        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        foreach ($fields as $key => $field) {
            if (!array_key_exists($key, $_POST)) {
                continue;
            }

            $raw_value = wp_unslash($_POST[$key]);
            if ($field['type'] === 'image') {
                $value = absint($raw_value);
            } elseif ($field['type'] === 'textarea') {
                $value = sanitize_textarea_field($raw_value);
            } else {
                $value = sanitize_text_field($raw_value);
            }

            update_post_meta($post_id, $key, $value);
        }
    }
}
