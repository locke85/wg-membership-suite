<?php

namespace wg\membership\Helpers;

use wg\membership\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Sanitizer {
    public static function parse_decimal($value) {
        return round((float) str_replace(',', '.', (string) $value), 2);
    }

    public static function country($value) {
        $value = sanitize_text_field((string) $value);
        return in_array($value, Config::get_allowed_countries(), true) ? $value : 'Deutschland';
    }

    public static function kundenbeziehung($value) {
        $value = sanitize_text_field((string) $value);
        return in_array($value, ['Privat', 'Geschäftlich'], true) ? $value : '';
    }

    public static function nutzung($value) {
        $value = sanitize_text_field((string) $value);
        return in_array($value, ['privat', 'geschäftlich'], true) ? $value : '';
    }
}
