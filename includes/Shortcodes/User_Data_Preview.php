<?php

namespace wg\membership\Shortcodes;

use wg\membership\Config;
use wg\membership\Helpers\User_Data;

if (!defined('ABSPATH')) {
    exit;
}

class User_Data_Preview {
    public const AJAX_ACTION = 'benutzerdaten_vorschau_refresh';
    public const AJAX_NONCE_ACTION = 'wg_benutzerdaten_vorschau_refresh';
    public const AJAX_NONCE_NAME = 'nonce';

    public static function register() {
        add_shortcode('benutzerdaten_vorschau', [static::class, 'render']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [static::class, 'ajax_refresh']);
    }

    public static function render($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Bitte einloggen.', 'wg-membership-suite') . '</p>';
        }
        $atts = shortcode_atts(['context' => 'profil'], $atts);
        $prefill = User_Data::get_prefill_user_data(get_current_user_id());
        $produkt_id = isset($_GET['angebot']) ? absint($_GET['angebot']) : 0;
        $weiter_url = Config::get_checkout_page_url('step_3');
        $success = isset($_GET['saved']) && $_GET['saved'] === '1';
        $ajax_nonce = wp_create_nonce(self::AJAX_NONCE_ACTION);

        ob_start();
        ?>
        <div
            class="wg-user-data-preview-wrapper"
            data-wg-preview-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
            data-wg-preview-ajax-action="<?php echo esc_attr(self::AJAX_ACTION); ?>"
            data-wg-preview-ajax-nonce="<?php echo esc_attr($ajax_nonce); ?>"
        >
            <form method="post" id="nutzerdaten-formular">
                <?php wp_nonce_field('wg_user_data_submit', 'wg_user_data_nonce'); ?>
                <h3><?php echo esc_html($atts['context'] === 'profil' ? '...einfach überschreiben, ergänzen und speichern' : 'Vertragsdaten eingeben'); ?></h3>
                <?php foreach (['vorname', 'nachname', 'email', 'firma', 'strasse', 'plz', 'stadt'] as $field) : ?>
                <p><input type="<?php echo esc_attr($field === 'email' ? 'email' : 'text'); ?>" name="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr($prefill[$field] ?? ''); ?>" placeholder="<?php echo esc_attr($field); ?>" <?php echo in_array($field, ['vorname', 'nachname', 'email'], true) ? 'required' : ''; ?>></p>
                <?php endforeach; ?>
                <?php if ($atts['context'] === 'profil') : ?><p><input type="text" name="spickzettel_url" value="<?php echo esc_attr($prefill['spickzettel_url']); ?>" placeholder="spickzettel_url"></p><?php endif; ?>
                <p><select name="land"><?php foreach (Config::get_allowed_countries() as $country) : ?><option value="<?php echo esc_attr($country); ?>" <?php selected($prefill['land'], $country); ?>><?php echo esc_html($country); ?></option><?php endforeach; ?></select></p>
                <p><select name="kundenbeziehung"><option value=""><?php esc_html_e('Bitte wählen', 'wg-membership-suite'); ?></option><option value="Privat" <?php selected($prefill['kundenbeziehung'], 'Privat'); ?>>Privat</option><option value="Geschäftlich" <?php selected($prefill['kundenbeziehung'], 'Geschäftlich'); ?>>Geschäftlich</option></select></p>
                <p><select name="nutzung"><option value=""><?php esc_html_e('Rechnungsstellung wählen', 'wg-membership-suite'); ?></option><option value="privat" <?php selected($prefill['nutzung'], 'privat'); ?>>Privat</option><option value="geschäftlich" <?php selected($prefill['nutzung'], 'geschäftlich'); ?>>Geschäftlich</option></select></p>
                <p><input type="text" name="steuernummer" value="<?php echo esc_attr($prefill['steuernummer']); ?>" placeholder="steuernummer"></p>
                <?php if ($atts['context'] === 'bestellung') : ?><p><input type="text" name="empfehler_email" value="<?php echo esc_attr($prefill['empfehler_email']); ?>" placeholder="empfehler_email"></p><?php endif; ?>
                <input type="hidden" name="form_submitted" value="1">
                <input type="hidden" name="context" value="<?php echo esc_attr($atts['context']); ?>">
                <?php if ($atts['context'] === 'bestellung') : ?>
                <input type="hidden" name="angebot" value="<?php echo esc_attr($produkt_id); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($weiter_url); ?>">
                <button type="submit"><?php esc_html_e('Bestellung prüfen', 'wg-membership-suite'); ?></button>
                <?php else : ?>
                <button type="submit"><?php esc_html_e('Änderungen speichern', 'wg-membership-suite'); ?></button>
                <?php endif; ?>
                <?php if ($success && $atts['context'] === 'profil') : ?><div>...erledigt.</div><?php endif; ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function ajax_refresh() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Nicht eingeloggt.', 'wg-membership-suite')], 403);
        }

        check_ajax_referer(self::AJAX_NONCE_ACTION, self::AJAX_NONCE_NAME);

        $prefill = User_Data::get_prefill_user_data(get_current_user_id());
        $produkte = get_user_meta(get_current_user_id(), 'gekaufte_produkte', true);
        $produkte_output = is_array($produkte) ? implode(', ', array_map('strval', $produkte)) : (string) $produkte;

        ob_start();
        ?>
        <div class="user-data-preview">
            <h3><?php esc_html_e('Ihre gespeicherten Daten:', 'wg-membership-suite'); ?></h3>
            <?php foreach (['vorname', 'nachname', 'email', 'firma', 'strasse', 'plz', 'stadt', 'land', 'nutzung', 'steuernummer'] as $field) : ?>
            <p><strong><?php echo esc_html($field); ?>:</strong> <?php echo esc_html($prefill[$field] ?? ''); ?></p>
            <?php endforeach; ?>
            <?php if (!empty($prefill['spickzettel_url'])) : ?><p><a href="<?php echo esc_url($prefill['spickzettel_url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Online-Marketing-Plan (Spickzettel)', 'wg-membership-suite'); ?></a></p><?php endif; ?>
            <p><strong>gekaufte_produkte:</strong> <?php echo esc_html($produkte_output); ?></p>
        </div>
        <?php
        wp_send_json_success(['html' => ob_get_clean()]);
    }
}
