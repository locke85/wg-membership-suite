<?php

namespace wg\membership\Admin;

use wg\membership\Config;
use wg\membership\Finance\Archive;

if (!defined('ABSPATH')) {
    exit;
}

class Analytics_Access_Gate {
    const SESSION_TTL = 15 * MINUTE_IN_SECONDS;
    const MAX_ATTEMPTS = 5;

    public static function require_admin() {
        if (!current_user_can('manage_options') || !current_user_can(Config::VIEW_FINANCE_ARCHIVE_CAPABILITY)) {
            wp_die(esc_html__('Die Membership-Analysen sind ausschließlich für Administratoren freigegeben.', 'wg-membership-suite'), '', ['response' => 403]);
        }
    }

    public static function is_unlocked() {
        return (bool) get_site_transient(static::unlock_key());
    }

    public static function process_unlock() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['wg_finance_analytics_unlock'])) {
            return '';
        }
        check_admin_referer('wg_finance_analytics_unlock');
        $attempts = (int) get_site_transient(static::attempt_key());
        if ($attempts >= self::MAX_ATTEMPTS) {
            Archive::audit('analytics_reauthentication', 'rate_limited');
            return __('Zu viele Fehlversuche. Bitte in 15 Minuten erneut versuchen.', 'wg-membership-suite');
        }
        $user = wp_get_current_user();
        $password = isset($_POST['wg_finance_analytics_password']) ? (string) wp_unslash($_POST['wg_finance_analytics_password']) : '';
        if ($user->ID <= 0 || !wp_check_password($password, $user->user_pass, $user->ID)) {
            set_site_transient(static::attempt_key(), $attempts + 1, self::SESSION_TTL);
            Archive::audit('analytics_reauthentication', 'failed');
            return __('Das WordPress-Passwort ist nicht korrekt.', 'wg-membership-suite');
        }
        delete_site_transient(static::attempt_key());
        set_site_transient(static::unlock_key(), 1, self::SESSION_TTL);
        Archive::audit('analytics_reauthentication', 'success', ['ttl_seconds' => self::SESSION_TTL]);
        wp_safe_redirect(admin_url('tools.php?page=wg-finance-analytics&unlocked=1'));
        exit;
    }

    public static function render_locked($error = '') {
        echo '<div class="wrap"><h1>' . esc_html__('Historische Membership-Analysen', 'wg-membership-suite') . '</h1>';
        echo '<p>' . esc_html__('Diese personenbezogenen Finanz- und Mitgliedschaftsauswertungen sind nur für Administratoren sichtbar. Bitte bestätigen Sie den Zugriff mit Ihrem aktuellen WordPress-Passwort.', 'wg-membership-suite') . '</p>';
        if ($error !== '') {
            echo '<div class="notice notice-error inline"><p>' . esc_html($error) . '</p></div>';
        }
        echo '<form method="post" autocomplete="off" style="max-width:480px;background:#fff;border:1px solid #ccd0d4;padding:20px">';
        wp_nonce_field('wg_finance_analytics_unlock');
        echo '<input type="hidden" name="wg_finance_analytics_unlock" value="1">';
        echo '<p><label for="wg-finance-analytics-password"><strong>' . esc_html__('WordPress-Passwort', 'wg-membership-suite') . '</strong></label></p>';
        echo '<p><input id="wg-finance-analytics-password" name="wg_finance_analytics_password" type="password" class="regular-text" autocomplete="current-password" required></p>';
        submit_button(__('Analysen für 15 Minuten entsperren', 'wg-membership-suite'));
        echo '</form></div>';
    }

    private static function unlock_key() {
        return 'wg_finance_analytics_unlocked_' . static::session_hash();
    }

    private static function attempt_key() {
        return 'wg_finance_analytics_attempts_' . static::session_hash();
    }

    private static function session_hash() {
        return hash('sha256', get_current_user_id() . '|' . wp_get_session_token());
    }
}
