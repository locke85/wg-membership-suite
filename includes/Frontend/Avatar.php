<?php

namespace wg\membership\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class Avatar {
    public const NONCE_ACTION = 'wg_avatar_upload';
    public const NONCE_NAME = 'wg_avatar_upload_nonce';
    public const USER_FIELD = 'wg_avatar_user_id';
    public const QUERY_ARG = 'wg_avatar_status';

    public static function register() {
        add_filter('get_avatar_data', [static::class, 'filter_avatar_data'], 1000, 2);
        add_filter('get_avatar', [static::class, 'override_user_avatar_output'], 10, 6);
        add_action('template_redirect', [static::class, 'save_frontend_avatar_upload']);
    }

    public static function get_max_upload_size() {
        return (int) apply_filters('wg_membership_suite_avatar_max_size', 2 * 1024 * 1024);
    }

    public static function get_allowed_mimes() {
        return [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];
    }

    public static function get_status_message($status) {
        $messages = [
            'success' => __('Avatar erfolgreich aktualisiert.', 'wg-membership-suite'),
            'invalid_nonce' => __('Ungültige Anfrage für den Avatar-Upload.', 'wg-membership-suite'),
            'not_logged_in' => __('Bitte logge dich ein, um deinen Avatar zu ändern.', 'wg-membership-suite'),
            'invalid_user' => __('Der Avatar kann nur für das eigene Konto geändert werden.', 'wg-membership-suite'),
            'missing_file' => __('Bitte wähle eine Bilddatei aus.', 'wg-membership-suite'),
            'file_too_large' => __('Die Datei ist größer als 2 MB.', 'wg-membership-suite'),
            'invalid_filetype' => __('Nur JPG, PNG und WEBP sind erlaubt.', 'wg-membership-suite'),
            'upload_failed' => __('Der Avatar konnte nicht hochgeladen werden.', 'wg-membership-suite'),
        ];
        return $messages[$status] ?? '';
    }

    public static function build_status_redirect($status) {
        return add_query_arg(static::QUERY_ARG, sanitize_key((string) $status), wp_get_referer() ?: home_url('/'));
    }

    public static function get_user_id_from_avatar_input($id_or_email) {
        if (is_numeric($id_or_email)) {
            return absint($id_or_email);
        }
        if (is_object($id_or_email) && isset($id_or_email->user_id)) {
            return absint($id_or_email->user_id);
        }
        if ($id_or_email instanceof \WP_Comment) {
            return absint($id_or_email->user_id);
        }
        if (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            return $user ? (int) $user->ID : 0;
        }
        return 0;
    }

    public static function filter_avatar_data($args, $id_or_email) {
        $args['size'] = max(512, isset($args['size']) ? (int) $args['size'] : 0);
        $user_id = static::get_user_id_from_avatar_input($id_or_email);
        $avatar_id = $user_id ? absint(get_user_meta($user_id, 'custom_user_avatar_id', true)) : 0;
        if (!$avatar_id) {
            return $args;
        }
        $url = wp_get_attachment_image_url($avatar_id, [512, 512]) ?: wp_get_attachment_image_url($avatar_id, 'full');
        if ($url) {
            $args['url'] = $url;
            $args['found_avatar'] = true;
        }
        return $args;
    }

    public static function save_frontend_avatar_upload() {
        if (!isset($_POST['submit_frontend_avatar'])) {
            return;
        }

        if (!is_user_logged_in()) {
            wp_safe_redirect(static::build_status_redirect('not_logged_in'));
            exit;
        }

        $current_user_id = get_current_user_id();
        $posted_user_id = isset($_POST[static::USER_FIELD]) ? absint($_POST[static::USER_FIELD]) : 0;
        if ($posted_user_id !== $current_user_id || !isset($_POST[static::NONCE_NAME]) || !wp_verify_nonce($_POST[static::NONCE_NAME], static::NONCE_ACTION)) {
            wp_safe_redirect(static::build_status_redirect('invalid_nonce'));
            exit;
        }

        if ($posted_user_id <= 0 || $posted_user_id !== $current_user_id) {
            wp_safe_redirect(static::build_status_redirect('invalid_user'));
            exit;
        }

        if (!isset($_FILES['frontend_user_avatar']) || !is_array($_FILES['frontend_user_avatar'])) {
            wp_safe_redirect(static::build_status_redirect('missing_file'));
            exit;
        }

        $file = $_FILES['frontend_user_avatar'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_safe_redirect(static::build_status_redirect('missing_file'));
            exit;
        }

        if ((int) ($file['size'] ?? 0) > static::get_max_upload_size()) {
            wp_safe_redirect(static::build_status_redirect('file_too_large'));
            exit;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $type_check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], static::get_allowed_mimes());
        $allowed_mimes = array_values(static::get_allowed_mimes());
        if (empty($type_check['type']) || !in_array($type_check['type'], $allowed_mimes, true)) {
            wp_safe_redirect(static::build_status_redirect('invalid_filetype'));
            exit;
        }

        $file['name'] = !empty($type_check['proper_filename']) ? $type_check['proper_filename'] : $file['name'];
        $upload = wp_handle_upload($file, ['test_form' => false, 'mimes' => static::get_allowed_mimes()]);
        if (isset($upload['error']) || empty($upload['file'])) {
            wp_safe_redirect(static::build_status_redirect('upload_failed'));
            exit;
        }

        $attachment = [
            'post_mime_type' => $type_check['type'],
            'post_title' => sanitize_file_name((string) $file['name']),
            'post_status' => 'inherit',
        ];
        $attach_id = wp_insert_attachment($attachment, $upload['file']);
        if (!$attach_id || is_wp_error($attach_id)) {
            wp_safe_redirect(static::build_status_redirect('upload_failed'));
            exit;
        }

        wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $upload['file']));
        update_user_meta($current_user_id, 'custom_user_avatar_id', $attach_id);

        wp_safe_redirect(static::build_status_redirect('success'));
        exit;
    }

    public static function override_user_avatar_output($avatar, $id_or_email, $size, $default, $alt, $args) {
        $user_id = static::get_user_id_from_avatar_input($id_or_email);
        $avatar_id = $user_id ? get_user_meta($user_id, 'custom_user_avatar_id', true) : 0;
        if ($avatar_id) {
            $url = wp_get_attachment_image_url($avatar_id, [$size, $size]);
            if ($url) {
                $class = isset($args['class']) ? esc_attr($args['class']) : 'avatar';
                return '<img alt="' . esc_attr($alt) . '" src="' . esc_url($url) . '" class="' . $class . '" height="' . esc_attr($size) . '" width="' . esc_attr($size) . '">';
            }
        }
        return $avatar;
    }
}
