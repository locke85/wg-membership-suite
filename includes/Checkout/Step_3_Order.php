<?php

namespace wg\membership\Checkout;

use wg\membership\Config;
use wg\membership\Helpers\User_Data;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Step_3_Order {
    public static function register() {
        add_filter('wpcf7_form_elements', [static::class, 'cf7_render_step3_dynamic_preview'], 20);
        add_action('wpcf7_before_send_mail', [static::class, 'cf7_checkout_step3_create_order'], 30, 1);
        add_action('init', [static::class, 'handle_legacy_order_submission']);
    }

    public static function is_legacy_checkout_allowed() {
        return current_user_can('administrator') || (defined('ALLOW_LEGACY_CHECKOUT') && ALLOW_LEGACY_CHECKOUT === true);
    }

    public static function is_cf7_checkout_step3_form($contact_form) {
        if (!is_object($contact_form)) {
            return false;
        }

        $configured_id = absint(Config::get('cf7_step3_form_id', 0));
        if ($configured_id > 0 && method_exists($contact_form, 'id') && absint($contact_form->id()) === $configured_id) {
            return true;
        }

        if (method_exists($contact_form, 'title')) {
            $title = trim((string) $contact_form->title());
            if ($title !== '' && stripos($title, 'step 3') !== false) {
                return true;
            }
        }
        if (!method_exists($contact_form, 'prop')) {
            return false;
        }

        $template = (string) $contact_form->prop('form');
        return preg_match('/\[[^\]]*\bangebot\b[^\]]*\]/i', $template)
            && preg_match('/\[[^\]]*\bagb\b[^\]]*\]/i', $template)
            && preg_match('/\[[^\]]*\bdatenschutz\b[^\]]*\]/i', $template)
            && preg_match('/\[[^\]]*\bwiderruf\b[^\]]*\]/i', $template);
    }

    public static function posted_field_is_truthy($posted_data, $field_name) {
        if (!is_array($posted_data) || !array_key_exists($field_name, $posted_data)) {
            return false;
        }
        $value = is_array($posted_data[$field_name]) ? implode('', array_map('strval', $posted_data[$field_name])) : (string) $posted_data[$field_name];
        return trim($value) !== '';
    }

    public static function render_checkout_duplicate_notice($customer_data) {
        if (is_user_logged_in()) {
            return '';
        }
        $decision = Account_Resolver::evaluate_checkout_account_decision($customer_data);
        if (!in_array($decision['action'], ['pending_admin_review', 'link_existing'], true)) {
            return '';
        }
        $login_url = wp_login_url(Config::get_login_url());
        return '<div class="wg-checkout-account-hint" style="margin:1rem 0; padding:0.75rem; border:1px solid #d2c47c; background:#fffbe8;">'
            . '<p style="margin:0 0 0.5rem 0;">' . esc_html__('Zu diesen Angaben existiert wahrscheinlich bereits ein Konto. Bitte melden Sie sich nach Möglichkeit vor dem Kauf an.', 'wg-membership-suite') . '</p>'
            . '<p style="margin:0;"><a href="' . esc_url($login_url) . '">' . esc_html__('Zum Login', 'wg-membership-suite') . '</a></p></div>';
    }

    public static function resolve_offer_ids($user_id = 0, $token = '') {
        $context = $token !== '' ? Order_Context::get($user_id, $token) : null;
        $angebot_id = $context ? absint($context['angebot_id']) : 0;
        $erweiterung_id = $context ? absint($context['erweiterung_id']) : 0;

        foreach ([filter_input(INPUT_GET, 'angebot'), filter_input(INPUT_POST, 'angebot'), $_REQUEST['wg_angebote_abo_id'] ?? null] as $candidate) {
            if ($angebot_id > 0) {
                break;
            }
            $candidate = absint($candidate);
            if ($candidate > 0) {
                $angebot_id = $candidate;
            }
        }

        foreach ([filter_input(INPUT_GET, 'erweiterung'), filter_input(INPUT_POST, 'erweiterung')] as $candidate) {
            if ($erweiterung_id > 0) {
                break;
            }
            $candidate = absint($candidate);
            if ($candidate > 0) {
                $erweiterung_id = $candidate;
            }
        }

        if ($angebot_id <= 0 && $user_id > 0) {
            $last = Order_Context::get_last_offer_for_user($user_id);
            $angebot_id = $last['angebot_id'];
            if ($erweiterung_id <= 0) {
                $erweiterung_id = $last['erweiterung_id'];
            }
        }

        return [$angebot_id, $erweiterung_id, $context];
    }

    public static function get_summary_box_markup($prefill = null, $angebot_id = 0, $erweiterung_id = 0) {
        $prefill = is_array($prefill) ? $prefill : [];
        $angebot = Pricing::get_valid_angebot_post($angebot_id);
        if (!$angebot) {
            return '<p>' . esc_html__('Kein gültiges Angebot ausgewählt.', 'wg-membership-suite') . '</p>';
        }

        $pricing = Pricing::build_angebot_pricing($angebot_id);
        $netto_total = (float) $pricing['netto'];
        $mwst_total = (float) $pricing['mwst_betrag'];
        $brutto_total = (float) $pricing['endpreis'];
        $erweiterung_name = '';

        if ($erweiterung_id > 0 && Pricing::angebot_has_category_slug($erweiterung_id, 'erweiterung')) {
            $erweiterung = Pricing::get_valid_angebot_post($erweiterung_id);
            if ($erweiterung) {
                $erweiterung_name = $erweiterung->post_title;
                $extension_pricing = Pricing::build_angebot_pricing($erweiterung_id);
                $netto_total += (float) $extension_pricing['netto'];
                $mwst_total += (float) $extension_pricing['mwst_betrag'];
                $brutto_total += (float) $extension_pricing['endpreis'];
            }
        }

        $intervall = Pricing::sanitize_angebot_select_value('intervall', get_post_meta($angebot_id, 'intervall', true));
        $requested_payment = isset($prefill['zahlungsart']) ? Pricing::sanitize_checkout_payment_method((string) $prefill['zahlungsart']) : '';
        $payment = Pricing::enforce_angebot_payment_method($angebot_id, $requested_payment);
        $payment_label = Pricing::get_checkout_payment_method_label($payment['effective_zahlungsart']);

        ob_start();
        ?>
        <div class="bestelluebersicht">
            <?php if (!empty($prefill)) : ?>
            <p><strong><?php esc_html_e('Name:', 'wg-membership-suite'); ?></strong> <?php echo esc_html(trim(($prefill['vorname'] ?? '') . ' ' . ($prefill['nachname'] ?? ''))); ?></p>
            <p><strong><?php esc_html_e('E-Mail:', 'wg-membership-suite'); ?></strong> <?php echo esc_html($prefill['email'] ?? ''); ?></p>
            <?php endif; ?>
            <p><strong><?php esc_html_e('Produkt:', 'wg-membership-suite'); ?></strong> <?php echo esc_html($angebot->post_title); ?></p>
            <?php if ($erweiterung_name !== '') : ?><p><strong><?php esc_html_e('Erweiterung:', 'wg-membership-suite'); ?></strong> <?php echo esc_html($erweiterung_name); ?></p><?php endif; ?>
            <p><strong><?php esc_html_e('Intervall:', 'wg-membership-suite'); ?></strong> <?php echo esc_html($intervall); ?></p>
            <p><strong><?php esc_html_e('Zahlungsart:', 'wg-membership-suite'); ?></strong> <?php echo esc_html($payment_label); ?></p>
            <p><strong><?php esc_html_e('Netto:', 'wg-membership-suite'); ?></strong> <?php echo esc_html(number_format($netto_total, 2, ',', '.')); ?> €</p>
            <p><strong><?php esc_html_e('MwSt:', 'wg-membership-suite'); ?></strong> <?php echo esc_html(number_format($mwst_total, 2, ',', '.')); ?> €</p>
            <p><strong><?php esc_html_e('Brutto:', 'wg-membership-suite'); ?></strong> <strong><?php echo esc_html(number_format($brutto_total, 2, ',', '.')); ?> €</strong></p>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function cf7_render_step3_dynamic_preview($content) {
        if (!Config::is_checkout_page('step_3')) {
            return $content;
        }
        $token = isset($_REQUEST['wg_ot']) ? sanitize_key((string) $_REQUEST['wg_ot']) : '';
        [$angebot_id, $erweiterung_id, $context] = static::resolve_offer_ids(get_current_user_id(), $token);
        $prefill = is_user_logged_in() ? User_Data::get_prefill_user_data(get_current_user_id()) : [];
        if ($context && isset($context['customer_data']) && is_array($context['customer_data'])) {
            $prefill = array_merge($context['customer_data'], $prefill);
        }
        $request_customer_data = Account_Resolver::extract_checkout_customer_data($_REQUEST);
        if (!empty($request_customer_data)) {
            $prefill = array_merge($request_customer_data, $prefill);
        }

        $content = strtr($content, [
            '[firma]' => esc_html((string) ($prefill['firma'] ?? '')),
            '[steuernummer]' => esc_html((string) ($prefill['steuernummer'] ?? '')),
            '[strasse]' => esc_html((string) ($prefill['strasse'] ?? '')),
            '[plz]' => esc_html((string) ($prefill['plz'] ?? '')),
            '[stadt]' => esc_html((string) ($prefill['stadt'] ?? '')),
            '[land]' => esc_html((string) ($prefill['land'] ?? '')),
            '[email]' => esc_html((string) ($prefill['email'] ?? '')),
            '[kundenbeziehung]' => esc_html((string) ($prefill['kundenbeziehung'] ?? '')),
            '[empfehler]' => esc_html((string) ($prefill['empfehler_email'] ?? '')),
        ]);

        $content = preg_replace('/<div[^>]*id=["\']bestell-uebersicht["\'][^>]*>.*?<\/div>/is', static::get_summary_box_markup($prefill, $angebot_id, $erweiterung_id), $content, 1);
        return static::render_checkout_duplicate_notice($request_customer_data) . $content;
    }

    public static function cf7_checkout_step3_create_order($contact_form) {
        if (!class_exists('WPCF7_Submission') || !static::is_cf7_checkout_step3_form($contact_form)) {
            return;
        }
        $submission = \WPCF7_Submission::get_instance();
        $posted_data = $submission ? $submission->get_posted_data() : null;
        if (!is_array($posted_data)) {
            return;
        }
        if (!static::posted_field_is_truthy($posted_data, 'agb') || !static::posted_field_is_truthy($posted_data, 'datenschutz') || !static::posted_field_is_truthy($posted_data, 'widerruf')) {
            return;
        }

        $user_id = get_current_user_id();
        $payload = Order_Finalizer::build_checkout_order_payload($posted_data, $user_id);
        [$angebot_id, $erweiterung_id, $context] = static::resolve_offer_ids($user_id, $payload['checkout_token']);
        if ($payload['angebot_id'] <= 0) {
            $payload['angebot_id'] = $angebot_id;
        }
        if ($payload['erweiterung_id'] <= 0) {
            $payload['erweiterung_id'] = $erweiterung_id;
        }
        if (empty($payload['customer_data']) && $context && isset($context['customer_data'])) {
            $payload['customer_data'] = Account_Resolver::extract_checkout_customer_data($context['customer_data']);
        }

        $result = Order_Finalizer::process_checkout_order_submission($payload);
        if (!is_wp_error($result) && $user_id > 0) {
            update_user_meta($user_id, 'agb_ok', '1');
            update_user_meta($user_id, 'datenschutz_ok', '1');
            update_user_meta($user_id, 'widerruf_erloschen', '1');
        }
        if (!is_wp_error($result) && $payload['checkout_token'] !== '') {
            Order_Context::delete($user_id, $payload['checkout_token']);
        }
    }

    public static function handle_legacy_order_submission() {
        if (!isset($_POST['bestellung_bestaetigt'])) {
            return;
        }
        if (!isset($_POST['wg_bestellung_nonce']) || !wp_verify_nonce($_POST['wg_bestellung_nonce'], 'wg_bestellung_confirm')) {
            wp_die(__('Ungültige Bestellung. Bitte Seite neu laden.', 'wg-membership-suite'));
        }

        $angebot_id = isset($_POST['angebot']) ? absint($_POST['angebot']) : 0;
        $order_token = isset($_POST['wg_ot']) ? sanitize_key((string) $_POST['wg_ot']) : '';
        $user_id = get_current_user_id();
        $context = Order_Context::get($user_id, $order_token);

        if (!$context && !static::is_legacy_checkout_allowed()) {
            wp_die(__('Bestellvorgang abgelaufen. Bitte erneut starten.', 'wg-membership-suite'));
        }
        if ($context && absint($context['angebot_id']) !== $angebot_id) {
            wp_die(__('Ungültige Produktzuordnung. Bitte Bestellung neu starten.', 'wg-membership-suite'));
        }
        if (!Pricing::get_valid_angebot_post($angebot_id)) {
            wp_die(__('Ungültiges Angebot.', 'wg-membership-suite'));
        }
        if (!isset($_POST['agb'], $_POST['datenschutz'], $_POST['widerruf'])) {
            wp_die(__('Bitte AGB, Datenschutz und Widerruf bestätigen.', 'wg-membership-suite'));
        }

        $customer_data = $context['customer_data'] ?? ($user_id > 0 ? User_Data::get_prefill_user_data($user_id) : []);
        $payload = [
            'user_id' => $user_id,
            'angebot_id' => $angebot_id,
            'erweiterung_id' => isset($context['erweiterung_id']) ? absint($context['erweiterung_id']) : 0,
            'checkout_token' => $order_token,
            'customer_data' => Account_Resolver::extract_checkout_customer_data($customer_data),
        ];

        $result = Order_Finalizer::process_checkout_order_submission($payload);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }
        if ($user_id > 0) {
            update_user_meta($user_id, 'agb_ok', '1');
            update_user_meta($user_id, 'datenschutz_ok', '1');
            update_user_meta($user_id, 'widerruf_erloschen', '1');
        }
        Order_Context::delete($user_id, $order_token);
        wp_safe_redirect(Config::get_checkout_page_url('step_4'));
        exit;
    }
}
