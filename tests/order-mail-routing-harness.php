<?php

namespace wg\membership {
    class Config {
        const STATUS_HISTORY_KEY = '_wg_status_history';
        public static function get($key, $default = null) { return $default; }
    }
}

namespace wg\membership\Checkout {
    class Account_Resolver {}
    class Pricing {}
}

namespace {
    class WP_Error {}
    class WP_Post {
        public $ID;
        public $post_title;
        public function __construct($id, $title) { $this->ID = $id; $this->post_title = $title; }
    }
    class WP_User {
        public $ID;
        public $user_login;
        public $display_name;
        public $first_name;
        public $user_email;
        public function __construct($id, $login, $display_name, $first_name, $email) {
            $this->ID = $id;
            $this->user_login = $login;
            $this->display_name = $display_name;
            $this->first_name = $first_name;
            $this->user_email = $email;
        }
    }

    define('ABSPATH', __DIR__ . '/');

    $options = [];
    $post_meta = [];
    $mail_calls = [];
    $users = [
        1 => new WP_User(1, 'locke85', 'Jan', 'Jan', 'jan@webgefaehrte.de'),
        2 => new WP_User(2, 'kunde', 'Kundin Beispiel', 'Kundin', 'kunde@example.com'),
        30 => new WP_User(30, 'oliver_f', 'Oliver', 'Oliver', 'oliver@example.com'),
    ];
    $posts = [48912 => new WP_Post(48912, 'eRecruiting für Tanzschul-Unternehmer')];

    function absint($value) { return abs((int) $value); }
    function sanitize_email($value) { return filter_var((string) $value, FILTER_SANITIZE_EMAIL); }
    function is_email($value) { return filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false; }
    function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
    function __($value) { return $value; }
    function current_time() { return '2026-09-04 11:30:00'; }
    function get_option($key, $default = false) { global $options; return array_key_exists($key, $options) ? $options[$key] : $default; }
    function update_option($key, $value) { global $options; $options[$key] = $value; return true; }
    function delete_option($key) { global $options; if (!array_key_exists($key, $options)) { return false; } unset($options[$key]); return true; }
    function get_post_type($post_id) { return isset($GLOBALS['post_meta'][(int) $post_id]) ? 'wg_bestellung' : ''; }
    function get_post_meta($post_id, $key, $single = false) { global $post_meta; return $post_meta[(int) $post_id][$key] ?? ($single ? '' : []); }
    function update_post_meta($post_id, $key, $value) { global $post_meta; $post_meta[(int) $post_id][$key] = $value; return true; }
    function get_userdata($user_id) { global $users; return $users[(int) $user_id] ?? false; }
    function get_post($post_id) { global $posts; return $posts[(int) $post_id] ?? null; }
    function wp_mail($to, $subject, $message) { global $mail_calls; $mail_calls[] = compact('to', 'subject', 'message'); return true; }
    function add_filter() {}
    function add_action() {}

    require_once dirname(__DIR__) . '/includes/Checkout/Order_Finalizer.php';
    require_once dirname(__DIR__) . '/includes/Checkout/Step_3_Order.php';

    use wg\membership\Checkout\Order_Finalizer;
    use wg\membership\Checkout\Step_3_Order;

    function assert_same($expected, $actual, $message) {
        if ($expected !== $actual) {
            fwrite(STDERR, $message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
            exit(1);
        }
    }

    $options[Order_Finalizer::OFFER_OWNER_USER_ID_OPTION] = 30;
    $post_meta[590] = ['user_id' => 1, 'angebot_id' => 48912, 'order_number' => 'WG-2026-000590', 'brutto' => '109'];
    $post_meta[599] = ['user_id' => 2, 'angebot_id' => 48912, 'order_number' => 'WG-2026-000599', 'brutto' => '109', 'customer_vorname' => 'Kundin', 'customer_nachname' => 'Beispiel', 'customer_email' => 'kunde@example.com'];
    $post_meta[600] = ['user_id' => 1, 'angebot_id' => 48912, 'order_number' => 'WG-2026-000600', 'brutto' => '109', 'customer_vorname' => 'Jan', 'customer_nachname' => 'Test', 'customer_email' => 'jan@webgefaehrte.de'];
    $post_meta[601] = ['user_id' => 2, 'angebot_id' => 48912, 'order_number' => 'WG-2026-000601', 'brutto' => '109'];

    assert_same(true, Order_Finalizer::send_existing_account_mail(2, 599), 'Normal buyer mail sent');
    assert_same(true, Order_Finalizer::send_offer_owner_mail(2, 599), 'Normal Oliver mail sent');
    assert_same('kunde@example.com', $mail_calls[0]['to'], 'Normal buyer recipient retained');
    assert_same('oliver@example.com', $mail_calls[1]['to'], 'Configured Oliver recipient used');

    $mail_calls = [];
    $options[Order_Finalizer::MAIL_TEST_ROUTE_OPTION] = [
        'enabled' => true,
        'trigger_user_id' => 1,
        'test_recipient' => 'jan@webgefaehrte.de',
        'armed_after_order_id' => 599,
    ];
    assert_same(true, Order_Finalizer::send_existing_account_mail(1, 600), 'Test buyer mail sent');
    assert_same(true, Order_Finalizer::send_offer_owner_mail(1, 600), 'Test Oliver mail sent');
    assert_same('jan@webgefaehrte.de', $mail_calls[0]['to'], 'Buyer test routed to Jan');
    assert_same('jan@webgefaehrte.de', $mail_calls[1]['to'], 'Oliver test routed to Jan');
    assert_same(true, strpos($mail_calls[0]['subject'], '[TEST KÄUFER]') === 0, 'Buyer test subject distinguished');
    assert_same(true, strpos($mail_calls[1]['subject'], '[TEST OLIVER]') === 0, 'Oliver test subject distinguished');
    assert_same(false, isset($options[Order_Finalizer::MAIL_TEST_ROUTE_OPTION]), 'One-time route disarmed after claim');
    assert_same('jan@webgefaehrte.de', $post_meta[600][Order_Finalizer::MAIL_TEST_RECIPIENT_META], 'Claim retained for both order mails');
    assert_same(2, count($post_meta[600][Order_Finalizer::MAIL_DELIVERY_LOG_META]), 'Two mail attempts logged');
    assert_same(false, strpos(json_encode($post_meta[600][Order_Finalizer::MAIL_DELIVERY_LOG_META]), '@') !== false, 'Delivery log contains no cleartext email');

    $mail_calls = [];
    $options[Order_Finalizer::MAIL_TEST_ROUTE_OPTION] = [
        'enabled' => true,
        'trigger_user_id' => 1,
        'test_recipient' => 'jan@webgefaehrte.de',
        'armed_after_order_id' => 600,
    ];
    assert_same(true, Order_Finalizer::send_existing_account_mail(2, 601), 'Other buyer mail sent normally');
    assert_same('kunde@example.com', $mail_calls[0]['to'], 'Other buyer not routed');
    assert_same(true, isset($options[Order_Finalizer::MAIL_TEST_ROUTE_OPTION]), 'Route remains armed for intended buyer');
    assert_same(true, Order_Finalizer::send_existing_account_mail(1, 590), 'Older buyer order mail sent normally');
    assert_same('jan@webgefaehrte.de', $mail_calls[1]['to'], 'Older order uses intended buyer address without claiming route');
    assert_same(true, isset($options[Order_Finalizer::MAIL_TEST_ROUTE_OPTION]), 'Older order does not consume route');

    $options[Order_Finalizer::OFFER_OWNER_USER_ID_OPTION] = 999;
    assert_same(false, Order_Finalizer::send_offer_owner_mail(2, 599), 'Missing owner blocks owner mail');

    $checkout_form = new class {
        public function title() { return 'Checkout step 3'; }
    };
    $other_form = new class {
        public function title() { return 'Kontakt'; }
        public function prop() { return ''; }
    };
    assert_same(true, Step_3_Order::skip_native_mail_for_checkout_step3(false, $checkout_form), 'Native checkout mail skipped');
    assert_same(false, Step_3_Order::skip_native_mail_for_checkout_step3(false, $other_form), 'Unrelated CF7 mail retained');
    assert_same(true, Step_3_Order::skip_native_mail_for_checkout_step3(true, $other_form), 'Existing skip decision retained');

    echo "Order mail routing harness passed.\n";
}
