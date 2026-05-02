<?php

namespace wg\membership\Shortcodes;

use wg\membership\Frontend\Avatar;

if (!defined('ABSPATH')) {
    exit;
}

class Avatar_Form {
    public static function register() {
        add_shortcode('user_avatar_form', [static::class, 'render']);
    }

    public static function render($atts) {
        if (!is_user_logged_in()) {
            return '';
        }
        $atts = shortcode_atts(['size' => 96, 'class' => 'avatar-preview'], $atts);
        $user = wp_get_current_user();
        $avatar_id = get_user_meta($user->ID, 'custom_user_avatar_id', true);
        $avatar_url = $avatar_id ? wp_get_attachment_url($avatar_id) : get_avatar_url($user->ID);
        $status = isset($_GET[Avatar::QUERY_ARG]) ? sanitize_key((string) $_GET[Avatar::QUERY_ARG]) : '';
        $status_message = Avatar::get_status_message($status);
        $status_class = $status === 'success' ? 'notice-success' : ($status !== '' ? 'notice-error' : '');
        ob_start();
        ?>
        <div class="user-avatar-upload">
            <?php if ($status_message !== '') : ?><div class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_message); ?></div><?php endif; ?>
            <img src="<?php echo esc_url($avatar_url); ?>" alt="Avatar" width="<?php echo esc_attr($atts['size']); ?>" height="<?php echo esc_attr($atts['size']); ?>" class="<?php echo esc_attr($atts['class']); ?>">
            <form method="post" enctype="multipart/form-data" id="avatar-upload-form">
                <input type="file" name="frontend_user_avatar" id="avatar-input" accept="image/jpeg,image/png,image/webp" style="display:none;">
                <label for="avatar-input"><?php esc_html_e('Avatar ändern', 'wg-membership-suite'); ?></label>
                <input type="hidden" name="submit_frontend_avatar" value="1">
                <input type="hidden" name="<?php echo esc_attr(Avatar::USER_FIELD); ?>" value="<?php echo esc_attr($user->ID); ?>">
                <?php wp_nonce_field(Avatar::NONCE_ACTION, Avatar::NONCE_NAME); ?>
            </form>
        </div>
        <script>document.getElementById('avatar-input')?.addEventListener('change',function(){if(this.files.length>0){document.getElementById('avatar-upload-form').submit();}});</script>
        <?php
        return ob_get_clean();
    }
}
