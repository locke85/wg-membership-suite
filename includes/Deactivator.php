<?php

namespace wg\membership;

if (!defined('ABSPATH')) {
    exit;
}

class Deactivator {
    public static function deactivate($network_wide = false) {
        if (is_multisite() && $network_wide) {
            foreach (get_sites(['fields' => 'ids']) as $blog_id) {
                switch_to_blog((int) $blog_id);
                static::deactivate_single_site();
                restore_current_blog();
            }

            delete_site_option(Config::NETWORK_ACTIVATED_OPTION);
            return;
        }

        static::deactivate_single_site();
    }

    public static function deactivate_single_site() {
        flush_rewrite_rules();
    }
}
