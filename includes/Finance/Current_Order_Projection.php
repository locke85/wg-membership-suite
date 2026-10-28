<?php

namespace wg\membership\Finance;

if (!defined('ABSPATH')) {
    exit;
}

class Current_Order_Projection {
    public static function load($from = '2026-01-01') {
        $posts = get_posts([
            'post_type' => 'wg_bestellung',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'ASC',
            'date_query' => [[
                'after' => $from,
                'inclusive' => true,
            ]],
        ]);

        $objects = [];
        foreach ((array) $posts as $post) {
            if (!is_object($post) || empty($post->ID)) {
                continue;
            }
            $objects[] = static::from_post($post);
        }
        return $objects;
    }

    private static function from_post($post) {
        $post_id = (int) $post->ID;
        $user_id = absint(get_post_meta($post_id, 'user_id', true));
        $user = $user_id ? get_userdata($user_id) : null;
        $email = $user ? (string) $user->user_email : (string) get_post_meta($post_id, 'customer_email', true);
        $display_name = $user ? (string) $user->display_name : trim(
            (string) get_post_meta($post_id, 'customer_vorname', true) . ' ' .
            (string) get_post_meta($post_id, 'customer_nachname', true)
        );
        $registered_at = $user ? (string) $user->user_registered : '';
        $net = static::decimal(get_post_meta($post_id, 'netto', true));
        $gross = static::decimal(get_post_meta($post_id, 'brutto', true));
        $tax = $net !== null && $gross !== null ? number_format(max(0, (float) $gross - (float) $net), 6, '.', '') : null;
        $products = [];

        $angebot_id = absint(get_post_meta($post_id, 'angebot_id', true));
        if ($angebot_id > 0) {
            $angebot = get_post($angebot_id);
            $kind = sanitize_key((string) get_post_meta($post_id, 'angebot_typ', true)) === 'erweiterung' ? 'extension' : 'membership_product';
            $products[] = [
                'id' => 'membership-suite:angebot:' . $angebot_id,
                'source_id' => (string) $angebot_id,
                'title' => $angebot ? (string) $angebot->post_title : ('Angebot #' . $angebot_id),
                'kind' => $kind,
                'source' => 'membership_suite',
            ];
        }
        $erweiterung_id = absint(get_post_meta($post_id, 'erweiterung_id', true));
        if ($erweiterung_id > 0) {
            $erweiterung = get_post($erweiterung_id);
            $products[] = [
                'id' => 'membership-suite:erweiterung:' . $erweiterung_id,
                'source_id' => (string) $erweiterung_id,
                'title' => $erweiterung ? (string) $erweiterung->post_title : ('Erweiterung #' . $erweiterung_id),
                'kind' => 'extension',
                'source' => 'membership_suite',
            ];
        }

        $snapshot = [
            'source' => 'membership_suite',
            'order_number' => (string) get_post_meta($post_id, 'order_number', true),
            'member' => [
                'id' => $user_id,
                'email' => $email,
                'display_name' => $display_name,
                'registered_at' => $registered_at,
            ],
            'products' => $products,
            'net_amount' => $net,
            'tax_amount' => $tax,
            'gross_amount' => $gross,
            'tax_rate' => static::decimal(get_post_meta($post_id, 'mwst_satz', true)),
            'checkout_status_only' => true,
        ];
        $snapshot_json = Archive::canonical_json($snapshot);

        return [
            'object_type' => 'current_order',
            'source_id' => 'wg_bestellung:' . $post_id,
            'object_hash' => hash('sha256', implode('|', [
                $post_id,
                (string) ($post->post_modified_gmt ?? $post->post_modified ?? ''),
                $snapshot_json,
                (string) get_post_meta($post_id, 'status', true),
            ])),
            'batch_id' => 0,
            'status' => sanitize_key((string) get_post_meta($post_id, 'status', true)),
            'occurred_at' => (string) ($post->post_date ?? ''),
            'customer_user_id' => $user_id,
            'customer_ref_hash' => hash('sha256', strtolower('membership-suite|' . $user_id . '|' . $email)),
            'amount_net' => $net,
            'amount_tax' => $tax,
            'amount_gross' => $gross,
            'currency' => 'EUR',
            'snapshot_json' => $snapshot_json,
        ];
    }

    private static function decimal($value) {
        return $value === '' || $value === null || !is_numeric($value)
            ? null
            : number_format((float) $value, 6, '.', '');
    }
}
