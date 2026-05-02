<?php

namespace wg\membership\Admin;

use wg\membership\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Settings_Page {
    public static function register() {
        add_action('admin_menu', [static::class, 'add_page']);
        add_action('admin_init', [static::class, 'register_settings']);
    }

    public static function add_page() {
        add_options_page(__('Membership Suite', 'wg-membership-suite'), __('Membership Suite', 'wg-membership-suite'), 'manage_options', 'wg-membership-suite', [static::class, 'render_page']);
    }

    public static function register_settings() {
        register_setting(Config::OPTION_KEY, Config::OPTION_KEY, [static::class, 'sanitize_settings']);
    }

    public static function sanitize_settings($input) {
        $defaults = Config::defaults();
        $settings = array_replace_recursive($defaults, is_array($input) ? $input : []);

        $settings['cf7_step2_form_id'] = absint($settings['cf7_step2_form_id']);
        $settings['cf7_step3_form_id'] = absint($settings['cf7_step3_form_id']);
        $settings['login_page_id'] = absint($settings['login_page_id']);
        $settings['password_reset_page_id'] = absint($settings['password_reset_page_id']);
        $settings['login_page'] = sanitize_title((string) $settings['login_page']);
        $settings['password_reset_page'] = sanitize_title((string) $settings['password_reset_page']);

        foreach ($defaults['checkout_page_ids'] as $key => $default_value) {
            $settings['checkout_page_ids'][$key] = absint($settings['checkout_page_ids'][$key] ?? $default_value);
        }
        foreach ($defaults['checkout_pages'] as $key => $default_value) {
            $settings['checkout_pages'][$key] = sanitize_title((string) ($settings['checkout_pages'][$key] ?? $default_value));
        }

        $settings['site_enabled'] = !empty($settings['site_enabled']);
        $settings['two_factor_enabled'] = !empty($settings['two_factor_enabled']);
        $settings['debug_mode'] = !empty($settings['debug_mode']);
        $settings['allowed_countries'] = array_values(array_filter(array_map('sanitize_text_field', preg_split('/\r\n|\r|\n/', (string) ($input['allowed_countries_raw'] ?? implode("\n", $defaults['allowed_countries']))))));
        $settings['allowed_payment_methods'] = array_values(array_intersect(['rechnung', 'sepa_ueberweisung'], array_map('sanitize_key', (array) ($settings['allowed_payment_methods'] ?? []))));

        return $settings;
    }

    public static function render_page() {
        $settings = Config::get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('webGefährte Membership Suite', 'wg-membership-suite'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields(Config::OPTION_KEY); ?>
                <table class="form-table" role="presentation">
                    <tr><th><label for="wg_cf7_step2_form_id"><?php esc_html_e('CF7 Step-2 Formular-ID', 'wg-membership-suite'); ?></label></th><td><input class="small-text" type="number" min="0" id="wg_cf7_step2_form_id" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[cf7_step2_form_id]" value="<?php echo esc_attr($settings['cf7_step2_form_id']); ?>"></td></tr>
                    <tr><th><label for="wg_cf7_step3_form_id"><?php esc_html_e('CF7 Step-3 Formular-ID', 'wg-membership-suite'); ?></label></th><td><input class="small-text" type="number" min="0" id="wg_cf7_step3_form_id" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[cf7_step3_form_id]" value="<?php echo esc_attr($settings['cf7_step3_form_id']); ?>"></td></tr>
                    <tr><th><label for="wg_login_page_id"><?php esc_html_e('Login-Seite (Page-ID)', 'wg-membership-suite'); ?></label></th><td><input class="small-text" type="number" min="0" id="wg_login_page_id" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[login_page_id]" value="<?php echo esc_attr($settings['login_page_id']); ?>"><p class="description"><?php esc_html_e('Primärwert. Legacy-Fallback bleibt login_page /login-secure.', 'wg-membership-suite'); ?></p></td></tr>
                    <tr><th><label for="wg_password_reset_page_id"><?php esc_html_e('Passwort-Reset-Seite (Page-ID)', 'wg-membership-suite'); ?></label></th><td><input class="small-text" type="number" min="0" id="wg_password_reset_page_id" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[password_reset_page_id]" value="<?php echo esc_attr($settings['password_reset_page_id']); ?>"></td></tr>
                    <?php foreach ($settings['checkout_page_ids'] as $step => $page_id) : ?>
                    <tr><th><label for="wg_checkout_page_id_<?php echo esc_attr($step); ?>"><?php echo esc_html(sprintf(__('Checkout-Seite %s (Page-ID)', 'wg-membership-suite'), $step)); ?></label></th><td><input class="small-text" type="number" min="0" id="wg_checkout_page_id_<?php echo esc_attr($step); ?>" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[checkout_page_ids][<?php echo esc_attr($step); ?>]" value="<?php echo esc_attr($page_id); ?>"></td></tr>
                    <?php endforeach; ?>
                    <tr><th><label for="wg_login_page"><?php esc_html_e('Login-Seite Legacy-Slug', 'wg-membership-suite'); ?></label></th><td><input class="regular-text" id="wg_login_page" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[login_page]" value="<?php echo esc_attr($settings['login_page']); ?>"></td></tr>
                    <tr><th><label for="wg_password_reset_page"><?php esc_html_e('Passwort-Reset-Seite Legacy-Slug', 'wg-membership-suite'); ?></label></th><td><input class="regular-text" id="wg_password_reset_page" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[password_reset_page]" value="<?php echo esc_attr($settings['password_reset_page']); ?>"></td></tr>
                    <?php foreach ($settings['checkout_pages'] as $step => $slug) : ?>
                    <tr><th><label for="wg_checkout_<?php echo esc_attr($step); ?>"><?php echo esc_html(sprintf(__('Checkout-Seite %s Legacy-Slug', 'wg-membership-suite'), $step)); ?></label></th><td><input class="regular-text" id="wg_checkout_<?php echo esc_attr($step); ?>" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[checkout_pages][<?php echo esc_attr($step); ?>]" value="<?php echo esc_attr($slug); ?>"></td></tr>
                    <?php endforeach; ?>
                    <tr><th><?php esc_html_e('Membership Suite auf dieser Site aktiv', 'wg-membership-suite'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[site_enabled]" value="1" <?php checked(!empty($settings['site_enabled'])); ?>> <?php esc_html_e('Site-lokale Laufzeitfunktionen aktivieren', 'wg-membership-suite'); ?></label></td></tr>
                    <tr><th><?php esc_html_e('2FA aktiv', 'wg-membership-suite'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[two_factor_enabled]" value="1" <?php checked(!empty($settings['two_factor_enabled'])); ?>> <?php esc_html_e('Login-2FA aktivieren', 'wg-membership-suite'); ?></label></td></tr>
                    <tr><th><label for="wg_allowed_countries"><?php esc_html_e('Erlaubte Länder', 'wg-membership-suite'); ?></label></th><td><textarea class="large-text" rows="4" id="wg_allowed_countries" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[allowed_countries_raw]"><?php echo esc_textarea(implode("\n", $settings['allowed_countries'])); ?></textarea></td></tr>
                    <tr><th><?php esc_html_e('Erlaubte Zahlungsarten', 'wg-membership-suite'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[allowed_payment_methods][]" value="rechnung" <?php checked(in_array('rechnung', $settings['allowed_payment_methods'], true)); ?>> Rechnung</label><br><label><input type="checkbox" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[allowed_payment_methods][]" value="sepa_ueberweisung" <?php checked(in_array('sepa_ueberweisung', $settings['allowed_payment_methods'], true)); ?>> SEPA-Überweisung</label></td></tr>
                    <tr><th><?php esc_html_e('Debug-Modus', 'wg-membership-suite'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[debug_mode]" value="1" <?php checked(!empty($settings['debug_mode'])); ?>> <?php esc_html_e('Debug-Logging aktivieren', 'wg-membership-suite'); ?></label></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
