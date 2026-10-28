<?php

namespace wg\membership\Admin;

use WP_User;
use WP_User_Query;
use wg\membership\Checkout\Account_Resolver;
use wg\membership\Checkout\Order_Finalizer;
use wg\membership\Checkout\Pricing;
use wg\membership\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Customer_Order {
    const ACTION = 'wg_customer_order_create';
    const RESEND_ACTION = 'wg_customer_order_resend_confirmation';
    const AUDIT_META = '_wg_on_behalf_audit';
    const MAIL_STATUS_META = '_wg_on_behalf_confirmation_status';

    public static function register() {
        add_action('init', [static::class, 'ensure_administrator_capability'], 20);
        add_action('admin_post_' . self::ACTION, [static::class, 'handle_create']);
        add_action('admin_post_' . self::RESEND_ACTION, [static::class, 'handle_resend']);
        add_filter('the_content', [static::class, 'append_panel']);
        add_shortcode('wg_admin_customer_order', [static::class, 'render_panel']);
        add_action('add_meta_boxes_wg_bestellung', [static::class, 'add_order_meta_box']);
    }

    public static function ensure_administrator_capability() {
        $role = get_role('administrator');
        if ($role && !$role->has_cap(Config::ORDER_ON_BEHALF_CAPABILITY)) {
            $role->add_cap(Config::ORDER_ON_BEHALF_CAPABILITY);
        }
    }

    public static function can_order() {
        return is_user_logged_in() && current_user_can(Config::ORDER_ON_BEHALF_CAPABILITY);
    }

    public static function append_panel($content) {
        if (!self::can_order() || is_admin() || !is_main_query() || !in_the_loop()) {
            return $content;
        }

        $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        if ($path !== 'schreibtisch/angebote') {
            return $content;
        }

        if (has_shortcode($content, 'wg_admin_customer_order')) {
            return $content;
        }

        return $content . self::render_panel();
    }

    public static function find_customers($search) {
        $search = trim(sanitize_text_field((string) $search));
        if ($search === '') {
            return [];
        }

        if (ctype_digit($search)) {
            $user = get_userdata(absint($search));
            return $user instanceof WP_User ? [$user] : [];
        }

        $query = new WP_User_Query([
            'number' => 20,
            'search' => '*' . esc_attr($search) . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
        $users = $query->get_results();

        $name_ids = get_users([
            'number' => 20,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                ['key' => 'first_name', 'value' => $search, 'compare' => 'LIKE'],
                ['key' => 'last_name', 'value' => $search, 'compare' => 'LIKE'],
                ['key' => 'vorname', 'value' => $search, 'compare' => 'LIKE'],
                ['key' => 'nachname', 'value' => $search, 'compare' => 'LIKE'],
            ],
        ]);

        $by_id = [];
        foreach (array_merge($users, array_map('get_userdata', $name_ids)) as $user) {
            if ($user instanceof WP_User) {
                $by_id[$user->ID] = $user;
            }
        }
        return array_slice(array_values($by_id), 0, 20);
    }

    public static function render_panel() {
        if (!self::can_order()) {
            return '';
        }

        $search = isset($_GET['wg_customer_search']) ? sanitize_text_field(wp_unslash($_GET['wg_customer_search'])) : '';
        $customers = self::find_customers($search);
        $offers = get_posts([
            'post_type' => 'wg_angebot',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $return_url = remove_query_arg(['wg_customer_order', 'order_id'], home_url('/schreibtisch/angebote/'));

        ob_start();
        ?>
        <section class="wg-customer-order" style="margin-top:2rem;padding:1.25rem;border:1px solid #dcdcde;background:#fff">
            <h2><?php esc_html_e('Für einen Kunden bestellen', 'wg-membership-suite'); ?></h2>
            <?php if (isset($_GET['wg_customer_order'])) : ?>
                <p role="status"><strong><?php echo esc_html($_GET['wg_customer_order'] === 'success' ? __('Bestellung wurde angelegt und verarbeitet.', 'wg-membership-suite') : __('Die Bestellung konnte nicht angelegt werden.', 'wg-membership-suite')); ?></strong></p>
                <?php if (($_GET['wg_customer_order_detail'] ?? '') === 'mail_failed') : ?>
                    <p role="alert"><?php esc_html_e('Die Bestätigung konnte nicht versendet werden. Sie kann in der Bestellung im Backend erneut gesendet werden.', 'wg-membership-suite'); ?></p>
                <?php endif; ?>
            <?php endif; ?>
            <form method="get" action="<?php echo esc_url($return_url); ?>">
                <label for="wg-customer-search"><?php esc_html_e('Kunde nach ID, Name oder E-Mail suchen', 'wg-membership-suite'); ?></label><br>
                <input id="wg-customer-search" name="wg_customer_search" value="<?php echo esc_attr($search); ?>" required>
                <button type="submit"><?php esc_html_e('Suchen', 'wg-membership-suite'); ?></button>
            </form>
            <?php if ($search !== '' && empty($customers)) : ?>
                <p><?php esc_html_e('Kein Kunde gefunden.', 'wg-membership-suite'); ?></p>
            <?php elseif (!empty($customers)) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:1rem">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                    <input type="hidden" name="return_url" value="<?php echo esc_url($return_url); ?>">
                    <input type="hidden" name="idempotency_key" value="<?php echo esc_attr(wp_generate_uuid4()); ?>">
                    <?php wp_nonce_field(self::ACTION, '_wg_customer_order_nonce'); ?>
                    <p><label for="wg-customer-id"><?php esc_html_e('Eindeutiges Kundenkonto', 'wg-membership-suite'); ?></label><br>
                    <select id="wg-customer-id" name="customer_id" required>
                        <option value=""><?php esc_html_e('Bitte auswählen', 'wg-membership-suite'); ?></option>
                        <?php foreach ($customers as $customer) : ?>
                            <option value="<?php echo absint($customer->ID); ?>"><?php echo esc_html(sprintf('#%d — %s — %s', $customer->ID, $customer->display_name, $customer->user_email)); ?></option>
                        <?php endforeach; ?>
                    </select></p>
                    <p><label for="wg-offer-id"><?php esc_html_e('Angebot', 'wg-membership-suite'); ?></label><br>
                    <select id="wg-offer-id" name="offer_id" required>
                        <option value=""><?php esc_html_e('Bitte auswählen', 'wg-membership-suite'); ?></option>
                        <?php foreach ($offers as $offer) : $pricing = Pricing::build_angebot_pricing($offer->ID); ?>
                            <option value="<?php echo absint($offer->ID); ?>"><?php echo esc_html(sprintf('%s — %.2f EUR brutto — %s — %s', $offer->post_title, $pricing['endpreis'], get_post_meta($offer->ID, 'intervall', true), get_post_meta($offer->ID, 'zahlungsform', true))); ?></option>
                        <?php endforeach; ?>
                    </select></p>
                    <p><label><input type="checkbox" name="confirmed" value="1" required> <?php esc_html_e('Kunde, Angebot und Konditionen wurden geprüft. Bestellung verbindlich im Auftrag anlegen und Bestätigung senden.', 'wg-membership-suite'); ?></label></p>
                    <button type="submit"><?php esc_html_e('Bestellung jetzt anlegen', 'wg-membership-suite'); ?></button>
                </form>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public static function customer_data_from_user($user_id) {
        $user = get_userdata(absint($user_id));
        if (!($user instanceof WP_User)) {
            return [];
        }
        $data = [];
        foreach (Account_Resolver::get_checkout_customer_field_keys() as $key) {
            $data[$key] = (string) get_user_meta($user->ID, $key, true);
        }
        $data['vorname'] = $user->first_name !== '' ? $user->first_name : $data['vorname'];
        $data['nachname'] = $user->last_name !== '' ? $user->last_name : $data['nachname'];
        $data['email'] = $user->user_email;
        return $data;
    }

    public static function handle_create() {
        if (!self::can_order()) {
            wp_die(__('Keine Berechtigung.', 'wg-membership-suite'), 403);
        }
        check_admin_referer(self::ACTION, '_wg_customer_order_nonce');

        $return_url = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['return_url'] ?? '')), home_url('/schreibtisch/angebote/'));
        $customer_id = absint($_POST['customer_id'] ?? 0);
        $offer_id = absint($_POST['offer_id'] ?? 0);
        $key = sanitize_text_field(wp_unslash($_POST['idempotency_key'] ?? ''));
        $customer = get_userdata($customer_id);
        $offer = Pricing::get_valid_angebot_post($offer_id);

        if (!isset($_POST['confirmed']) || !($customer instanceof WP_User) || !$offer || !wp_is_uuid($key)) {
            self::redirect($return_url, 'error');
        }

        $option_key = 'wg_customer_order_' . hash('sha256', $key);
        if (!add_option($option_key, 'processing', '', false)) {
            $existing = absint(get_option($option_key));
            self::redirect($return_url, $existing > 0 ? 'success' : 'error', $existing);
        }

        $customer_data = self::customer_data_from_user($customer_id);
        $order_id = Order_Finalizer::create_bestellung_for_order($customer_id, $offer_id, [
            'customer_data' => $customer_data,
            'checkout_token' => 'admin-' . str_replace('-', '', $key),
            'status' => 'submitted',
        ]);
        if ($order_id <= 0) {
            delete_option($option_key);
            self::redirect($return_url, 'error');
        }

        Order_Finalizer::merge_order_products_into_user($customer_id, $offer_id);
        Pricing::assign_angebot_role_to_user($customer_id, $offer_id);
        update_user_meta($customer_id, 'letzte_bestellung_id', $order_id);
        update_post_meta($order_id, self::AUDIT_META, [
            'worker_user_id' => get_current_user_id(),
            'customer_user_id' => $customer_id,
            'offer_id' => $offer_id,
            'source' => 'admin_on_behalf_of_customer',
            'created_at' => current_time('mysql'),
            'idempotency_key' => $key,
        ]);
        Order_Finalizer::add_bestellung_comment($order_id, sprintf('Bestellung im Kundenauftrag durch Worker #%d für Kunde #%d angelegt.', get_current_user_id(), $customer_id));
        Order_Finalizer::update_bestellung_status($order_id, 'account_linked', 'Bestehendes Konto wurde durch Worker zugeordnet');
        Order_Finalizer::update_bestellung_status($order_id, 'completed', 'Bestellung im Kundenauftrag vollständig verarbeitet');

        $sent = self::send_confirmation($order_id);
        update_option($option_key, $order_id, false);
        self::redirect($return_url, 'success', $order_id, $sent ? '' : 'mail_failed');
    }

    public static function send_confirmation($order_id) {
        $order_id = absint($order_id);
        if (get_post_meta($order_id, self::MAIL_STATUS_META, true) === 'sent') {
            return true;
        }
        $sent = Order_Finalizer::send_on_behalf_confirmation(absint(get_post_meta($order_id, 'user_id', true)), $order_id);
        update_post_meta($order_id, self::MAIL_STATUS_META, $sent ? 'sent' : 'failed');
        update_post_meta($order_id, '_wg_on_behalf_confirmation_attempted_at', current_time('mysql'));
        Order_Finalizer::add_bestellung_comment($order_id, $sent ? 'Bestellbestätigung wurde versendet.' : 'Bestellbestätigung konnte nicht versendet werden.');
        return $sent;
    }

    public static function handle_resend() {
        if (!self::can_order()) {
            wp_die(__('Keine Berechtigung.', 'wg-membership-suite'), 403);
        }
        $order_id = absint($_POST['order_id'] ?? 0);
        check_admin_referer(self::RESEND_ACTION . '_' . $order_id, '_wg_customer_order_nonce');
        if (get_post_type($order_id) !== 'wg_bestellung' || !get_post_meta($order_id, self::AUDIT_META, true)) {
            wp_die(__('Ungültige Bestellung.', 'wg-membership-suite'), 400);
        }
        self::send_confirmation($order_id);
        wp_safe_redirect(Order_Finalizer::get_bestellung_admin_edit_link($order_id));
        exit;
    }

    public static function add_order_meta_box() {
        add_meta_box('wg_customer_order_audit', __('Bestellung im Kundenauftrag', 'wg-membership-suite'), [static::class, 'render_order_meta_box'], 'wg_bestellung', 'side');
    }

    public static function render_order_meta_box($post) {
        $audit = get_post_meta($post->ID, self::AUDIT_META, true);
        if (!is_array($audit)) {
            echo '<p>' . esc_html__('Keine Worker-Bestellung.', 'wg-membership-suite') . '</p>';
            return;
        }
        echo '<p>' . esc_html(sprintf('Worker #%d · Kunde #%d · Angebot #%d', absint($audit['worker_user_id'] ?? 0), absint($audit['customer_user_id'] ?? 0), absint($audit['offer_id'] ?? 0))) . '</p>';
        echo '<p>' . esc_html(sprintf(__('Mailstatus: %s', 'wg-membership-suite'), get_post_meta($post->ID, self::MAIL_STATUS_META, true) ?: 'offen')) . '</p>';
        if (self::can_order() && get_post_meta($post->ID, self::MAIL_STATUS_META, true) !== 'sent') {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="' . esc_attr(self::RESEND_ACTION) . '"><input type="hidden" name="order_id" value="' . absint($post->ID) . '">';
            wp_nonce_field(self::RESEND_ACTION . '_' . $post->ID, '_wg_customer_order_nonce');
            submit_button(__('Bestätigung erneut senden', 'wg-membership-suite'), 'secondary', 'submit', false);
            echo '</form>';
        }
    }

    private static function redirect($url, $status, $order_id = 0, $detail = '') {
        $args = ['wg_customer_order' => sanitize_key($status)];
        if ($order_id > 0) {
            $args['order_id'] = absint($order_id);
        }
        if ($detail !== '') {
            $args['wg_customer_order_detail'] = sanitize_key($detail);
        }
        wp_safe_redirect(add_query_arg($args, $url));
        exit;
    }
}
