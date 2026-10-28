<?php

namespace wg\membership\LMS;

use wg\membership\Helpers\Debug;

if (!defined('ABSPATH')) {
    exit;
}

class Quiz_Status {
    public static function register() {
        add_action('init', [static::class, 'register_dynamic_tag'], 20);
    }

    public static function get_chained_table_name($base_name) {
        global $wpdb;
        return $wpdb->base_prefix . get_current_blog_id() . '_chained_' . $base_name;
    }

    public static function extract_quiz_id_from_shortcode($shortcode) {
        $quiz_ids = static::extract_quiz_ids_from_shortcode($shortcode);
        return $quiz_ids[0] ?? null;
    }

    public static function extract_quiz_ids_from_shortcode($shortcode) {
        $quiz_ids = [];
        if (preg_match_all('/\[chained-quiz\s+(\d+)\]/i', (string) $shortcode, $matches)) {
            $quiz_ids = array_merge($quiz_ids, array_map('intval', $matches[1]));
        }
        if (preg_match_all('/\[chained-quiz[^\]]*(?:id|quiz_id)="?(\d+)"?/i', (string) $shortcode, $matches)) {
            $quiz_ids = array_merge($quiz_ids, array_map('intval', $matches[1]));
        }
        return array_values(array_unique(array_filter($quiz_ids)));
    }

    public static function chained_tables_exist() {
        global $wpdb;

        $completed_table = static::get_chained_table_name('completed');
        $answers_table = static::get_chained_table_name('user_answers');

        $completed_exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $completed_table));
        $answers_exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $answers_table));

        return $completed_exists === $completed_table && $answers_exists === $answers_table;
    }

    public static function user_has_completed_quiz_by_id($quiz_id) {
        if (!is_user_logged_in() || !$quiz_id || !static::chained_tables_exist()) {
            return false;
        }
        global $wpdb;
        $sql = $wpdb->prepare("SELECT COUNT(*) FROM " . static::get_chained_table_name('completed') . " c INNER JOIN " . static::get_chained_table_name('user_answers') . " a ON a.completion_id = c.id WHERE c.user_id = %d AND c.quiz_id = %d", get_current_user_id(), $quiz_id);
        return (int) $wpdb->get_var($sql) > 0;
    }

    public static function user_has_completed_all_quizzes_for_post($post_id) {
        $quiz_ids = static::extract_quiz_ids_from_shortcode(get_post_meta((int) $post_id, 'wg_besenwagen_code', true));
        if (empty($quiz_ids)) {
            return false;
        }
        foreach ($quiz_ids as $quiz_id) {
            if (!static::user_has_completed_quiz_by_id($quiz_id)) {
                return false;
            }
        }
        return true;
    }

    public static function register_dynamic_tag() {
        if (class_exists('GenerateBlocks_Register_Dynamic_Tag')) {
            new \GenerateBlocks_Register_Dynamic_Tag(['title' => __('Stop Quiz Status', 'wg-membership-suite'), 'tag' => 'quiz_status', 'type' => 'post', 'supports' => ['source'], 'return' => [static::class, 'get_quiz_status_dynamic_tag']]);
        }
    }

    public static function get_quiz_status_dynamic_tag($options, $block) {
        $post_id = !empty($options['post_id']) ? (int) $options['post_id'] : (get_the_ID() ?: (int) ($block['context']['postId'] ?? 0));
        return static::user_has_completed_all_quizzes_for_post($post_id) ? '1' : '0';
    }
}
