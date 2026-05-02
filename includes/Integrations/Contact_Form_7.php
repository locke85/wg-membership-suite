<?php

namespace wg\membership\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

class Contact_Form_7 {
    public static function register() {
        if (!static::is_available()) {
            return;
        }

        // CF7-bezogene Hooks werden defensiv in den Checkout-Klassen registriert.
        // Diese Integrationsklasse dient als zentrale Verfügbarkeitsgrenze, damit die
        // Plugin-Bootstrap-Logik nachvollziehbar bleibt und Sites ohne CF7 nicht brechen.
    }

    public static function is_available() {
        return function_exists('wpcf7_add_form_tag') || class_exists('WPCF7_ContactForm');
    }
}
