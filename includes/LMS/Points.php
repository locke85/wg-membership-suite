<?php

namespace wg\membership\LMS;

if (!defined('ABSPATH')) {
    exit;
}

class Points {
    public static function register() {
        add_shortcode('wg_punkte', [static::class, 'display_points_shortcode']);
        add_shortcode('wg_punkte_transaktionen', [static::class, 'display_points_transactions_shortcode']);
    }

    public static function allowed_quiz_ids() {
        return defined('WG_ALLOWED_QUIZ_IDS') ? WG_ALLOWED_QUIZ_IDS : [27, 28, 29];
    }

    public static function get_user_points_results() {
        if (!is_user_logged_in()) {
            return __('Bitte logge dich ein, um dein Punkte-Guthaben zu sehen.', 'wg-membership-suite');
        }
        if (!Quiz_Status::chained_tables_exist()) {
            return __('Punkte-Guthaben:', 'wg-membership-suite') . ' ' . 0;
        }
        global $wpdb;
        $allowed_ids = implode(',', array_map('intval', static::allowed_quiz_ids()));
        $total = $wpdb->get_var($wpdb->prepare("SELECT SUM(a.points) FROM " . Quiz_Status::get_chained_table_name('user_answers') . " a INNER JOIN " . Quiz_Status::get_chained_table_name('completed') . " c ON a.completion_id = c.id WHERE c.user_id = %d AND c.quiz_id IN ($allowed_ids)", get_current_user_id()));
        return __('Punkte-Guthaben:', 'wg-membership-suite') . ' ' . (int) $total;
    }

    public static function get_user_points_transactions() {
        if (!is_user_logged_in()) {
            return __('Bitte melde dich an, um deine Punkte-Transaktionen zu sehen.', 'wg-membership-suite');
        }
        if (!Quiz_Status::chained_tables_exist()) {
            return __('Noch keine Punkte-Transaktionen vorhanden.', 'wg-membership-suite');
        }
        global $wpdb;
        $allowed_ids = implode(',', array_map('intval', static::allowed_quiz_ids()));
        $results = $wpdb->get_results($wpdb->prepare("SELECT c.quiz_id, c.datetime, SUM(a.points) AS points, GROUP_CONCAT(a.comments SEPARATOR ', ') AS comments FROM " . Quiz_Status::get_chained_table_name('completed') . " c INNER JOIN " . Quiz_Status::get_chained_table_name('user_answers') . " a ON a.completion_id = c.id WHERE c.user_id = %d AND c.quiz_id IN ($allowed_ids) GROUP BY c.quiz_id, c.datetime ORDER BY c.datetime DESC", get_current_user_id()));
        if (empty($results)) {
            return __('Noch keine Punkte-Transaktionen vorhanden.', 'wg-membership-suite');
        }
        $output = '<ul class="wg-punkte-transaktionen">';
        foreach ($results as $row) {
            $output .= '<li>' . esc_html(date('d.m.Y', strtotime($row->datetime)) . ': ' . ((float) $row->points > 0 ? '+' : '') . $row->points . ' - ' . ($row->comments ?: '')) . '</li>';
        }
        return $output . '</ul>';
    }

    public static function display_points_shortcode() { return static::get_user_points_results(); }
    public static function display_points_transactions_shortcode() { return static::get_user_points_transactions(); }
}
