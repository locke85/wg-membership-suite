<?php

namespace wg\membership\LMS;

if (!defined('ABSPATH')) {
    exit;
}

class Activity_Sync {
    public static function register() {
        add_action('init', [static::class, 'sync_quiz_completions_to_main_activity'], 30);
    }

    public static function get_user_quiz_completions_for_sync($user_id) {
        if (!Quiz_Status::chained_tables_exist()) {
            return [];
        }

        global $wpdb;
        $allowed_ids = implode(',', array_map('intval', Points::allowed_quiz_ids()));
        return $wpdb->get_results($wpdb->prepare("SELECT c.id AS completion_id, c.quiz_id, c.datetime, COALESCE(SUM(a.points), 0) AS points FROM " . Quiz_Status::get_chained_table_name('completed') . " c LEFT JOIN " . Quiz_Status::get_chained_table_name('user_answers') . " a ON a.completion_id = c.id WHERE c.user_id = %d AND c.quiz_id IN ($allowed_ids) GROUP BY c.id, c.quiz_id, c.datetime ORDER BY c.id ASC", $user_id));
    }

    public static function activity_exists_for_completion($user_id, $quiz_id, $completion_id) {
        return !empty(get_posts(['post_type' => 'wg_aktivitaet', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_query' => [['key' => 'wg_user_id', 'value' => (string) $user_id], ['key' => 'wg_quiz_id', 'value' => (string) $quiz_id], ['key' => 'wg_completion_id', 'value' => (string) $completion_id]]]));
    }

    public static function sync_quiz_completions_to_main_activity() {
        if (!is_user_logged_in()) {
            return;
        }
        $user_id = get_current_user_id();
        $source_blog_id = get_current_blog_id();
        $completions = static::get_user_quiz_completions_for_sync($user_id);
        if (empty($completions)) {
            return;
        }
        switch_to_blog(1);
        try {
            foreach ($completions as $completion) {
                if (static::activity_exists_for_completion($user_id, (int) $completion->quiz_id, (int) $completion->completion_id)) {
                    continue;
                }
                $post_id = wp_insert_post(['post_type' => 'wg_aktivitaet', 'post_status' => 'publish', 'post_title' => sprintf('Quiz %d Abschluss #%d', $completion->quiz_id, $completion->completion_id)]);
                if ($post_id && !is_wp_error($post_id)) {
                    update_post_meta($post_id, 'wg_user_id', $user_id);
                    update_post_meta($post_id, 'wg_quiz_id', (int) $completion->quiz_id);
                    update_post_meta($post_id, 'wg_points', (int) $completion->points);
                    update_post_meta($post_id, 'wg_source_blog_id', $source_blog_id);
                    update_post_meta($post_id, 'wg_time_range', 'today');
                    update_post_meta($post_id, 'wg_origin', 'chained_quiz');
                    update_post_meta($post_id, 'wg_completion_id', (int) $completion->completion_id);
                }
            }
        } finally {
            restore_current_blog();
        }
    }
}
