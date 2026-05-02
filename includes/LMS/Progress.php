<?php

namespace wg\membership\LMS;

if (!defined('ABSPATH')) {
    exit;
}

class Progress {
    public static function register() {
        add_shortcode('render_etappe_progress', [static::class, 'render_shortcode']);
    }

    public static function get_etappe_progress_percent($etappe_term_id) {
        if (!is_user_logged_in()) {
            return 0;
        }
        $stopp_ids = get_posts(['post_type' => 'etappenstopp', 'posts_per_page' => -1, 'fields' => 'ids', 'tax_query' => [[ 'taxonomy' => 'etappe', 'field' => 'term_id', 'terms' => $etappe_term_id ]]]);
        if (empty($stopp_ids)) {
            return 0;
        }
        $completed = 0;
        foreach ($stopp_ids as $post_id) {
            $quiz_id = Quiz_Status::extract_quiz_id_from_shortcode(get_post_meta($post_id, 'wg_besenwagen_code', true));
            if ($quiz_id && Quiz_Status::user_has_completed_quiz_by_id($quiz_id)) {
                $completed++;
            }
        }
        return round(($completed / count($stopp_ids)) * 100);
    }

    public static function render_shortcode($atts) {
        $atts = shortcode_atts(['etappe_id' => 0], $atts);
        return (string) static::get_etappe_progress_percent((int) $atts['etappe_id']);
    }
}
