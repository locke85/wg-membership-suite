<?php

namespace wg\membership\Checkout {
    class Pricing {
        public static function get_valid_angebot_post($offer_id) {
            return in_array((int) $offer_id, [48911, 48912, 50001], true) ? (object) ['ID' => (int) $offer_id] : null;
        }

        public static function angebot_has_category_slug($offer_id, $slug) {
            $categories = [
                48911 => ['abos'],
                48912 => ['abos'],
                50001 => ['erweiterungen'],
            ];
            return in_array((string) $slug, $categories[(int) $offer_id] ?? [], true);
        }
    }
}

namespace {
    define('ABSPATH', __DIR__ . '/');

    function add_filter() {}
    function add_action() {}
    function absint($value) { return abs((int) $value); }
    function wp_unslash($value) { return $value; }
    function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
    function trailingslashit($value) { return rtrim((string) $value, '/') . '/'; }
    function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
    function esc_url($value) { return str_replace('&', '&#038;', (string) $value); }
    function add_query_arg($key, $value, $url) {
        $fragment = '';
        if (strpos($url, '#') !== false) {
            [$url, $fragment] = explode('#', $url, 2);
            $fragment = '#' . $fragment;
        }
        $separator = strpos($url, '?') === false ? '?' : '&';
        $url = preg_replace('/([?&])' . preg_quote($key, '/') . '=[^&]*/', '$1' . $key . '=' . rawurlencode((string) $value), $url, 1, $count);
        return ($count ?? 0) > 0 ? $url . $fragment : $url . $separator . rawurlencode($key) . '=' . rawurlencode((string) $value) . $fragment;
    }

    require_once dirname(__DIR__) . '/includes/Checkout/Step_1.php';

    use wg\membership\Checkout\Step_1;

    function assert_same($expected, $actual, $message) {
        if ($expected !== $actual) {
            fwrite(STDERR, $message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
            exit(1);
        }
    }

    $checkout_url = 'https://tanzlehrer-recruiting.de/schreibtisch/angebote/1-produkt-auswaehlen/';
    assert_same(true, Step_1::is_checkout_step_1_href('/schreibtisch/angebote/1-produkt-auswaehlen/', $checkout_url), 'Relative checkout URL recognized');
    assert_same(true, Step_1::is_checkout_step_1_href($checkout_url, $checkout_url), 'Absolute checkout URL recognized');
    assert_same(true, Step_1::is_checkout_step_1_href('/schreibtisch/angebote/1-produkt-auswaehlen/', 'https://tanzlehrer-recruiting.de/1-produkt-auswaehlen/', '1-produkt-auswaehlen'), 'Nested checkout URL recognized by configured slug');
    assert_same(false, Step_1::is_checkout_step_1_href('https://example.com/schreibtisch/angebote/1-produkt-auswaehlen/', $checkout_url), 'Foreign host rejected');
    assert_same(false, Step_1::is_checkout_step_1_href('/schreibtisch/angebote/2-daten-eingeben/', $checkout_url), 'Other checkout step rejected');

    $button = '<a class="button" href="/schreibtisch/angebote/1-produkt-auswaehlen/">Angebot bestellen</a>';
    $expected = '<a class="button" href="/schreibtisch/angebote/1-produkt-auswaehlen/?angebot=48912">Angebot bestellen</a>';
    assert_same($expected, Step_1::add_offer_parameter_to_checkout_links($button, 48912, $checkout_url), 'Offer parameter appended');
    assert_same($expected, Step_1::add_offer_parameter_to_checkout_links($expected, 48912, $checkout_url), 'Offer parameter remains idempotent');

    $with_query = '<a href="/schreibtisch/angebote/1-produkt-auswaehlen/?ref=detail#order">Angebot bestellen</a>';
    $expected_query = '<a href="/schreibtisch/angebote/1-produkt-auswaehlen/?ref=detail&#038;angebot=48911#order">Angebot bestellen</a>';
    assert_same($expected_query, Step_1::add_offer_parameter_to_checkout_links($with_query, 48911, $checkout_url), 'Existing query and fragment preserved');

    $foreign = '<a href="https://example.com/schreibtisch/angebote/1-produkt-auswaehlen/">Extern</a>';
    assert_same($foreign, Step_1::add_offer_parameter_to_checkout_links($foreign, 48912, $checkout_url), 'Foreign link unchanged');

    assert_same(48912, Step_1::get_requested_prefill_offer_id('wg_angebote_abo_id', 'abos', ['angebot' => '48912']), 'Published Abo accepted for prefill');
    assert_same(0, Step_1::get_requested_prefill_offer_id('wg_angebote_abo_id', 'abos', ['angebot' => '50001']), 'Extension rejected for Abo prefill');
    assert_same(0, Step_1::get_requested_prefill_offer_id('wg_angebote_abo_id', 'abos', ['angebot' => '42']), 'Unpublished offer rejected');
    assert_same(0, Step_1::get_requested_prefill_offer_id('wg_angebote_erweiterung_id', 'erweiterungen', ['angebot' => '50001']), 'Abo query ignored for extension field');
    assert_same(0, Step_1::get_requested_prefill_offer_id('wg_angebote_abo_id', 'abos', ['angebot' => ['48912']]), 'Array query rejected');

    echo "Checkout offer prefill harness passed.\n";
}
