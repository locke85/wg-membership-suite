<?php

namespace wg\membership;

use wg\membership\CPT\Angebot_CPT;
use wg\membership\CPT\Bestellung_CPT;
use wg\membership\CPT\Rechnung_CPT;
use wg\membership\Checkout\Pricing;

if (!defined('ABSPATH')) {
    exit;
}

class Activator {
    public static function activate($network_wide = false) {
        if (is_multisite() && $network_wide) {
            foreach (get_sites(['fields' => 'ids']) as $blog_id) {
                switch_to_blog((int) $blog_id);
                static::activate_single_site();
                restore_current_blog();
            }

            update_site_option(Config::NETWORK_ACTIVATED_OPTION, 1);
            return;
        }

        static::activate_single_site();
    }

    public static function activate_single_site() {
        add_option(Config::OPTION_KEY, Config::defaults());
        $settings = get_option(Config::OPTION_KEY, []);
        if (!is_array($settings)) {
            $settings = [];
        }
        update_option(Config::OPTION_KEY, array_replace_recursive(Config::defaults(), $settings), false);

        Angebot_CPT::register();
        Bestellung_CPT::register();
        Rechnung_CPT::register();

        $angebote = get_posts([
            'post_type' => 'wg_angebot',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        foreach ($angebote as $angebot_id) {
            $type = Pricing::sanitize_angebot_select_value('type', get_post_meta($angebot_id, 'type', true));
            if (in_array($type, ['abo', 'paket'], true)) {
                Pricing::ensure_angebot_role_exists($angebot_id);
            }
        }

        flush_rewrite_rules();
    }
}
