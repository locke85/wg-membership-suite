<?php

namespace wg\membership\Helpers;

use wg\membership\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Debug {
    public static function log($message) {
        if (!Config::is_debug_enabled()) {
            return;
        }

        if (is_array($message) || is_object($message)) {
            $message = wp_json_encode($message);
        }

        error_log('[wg-membership-suite] ' . (string) $message);
    }
}
