<?php

define('ABSPATH', __DIR__ . '/wordpress/');

$GLOBALS['wg_completed_quizzes'] = [30 => true, 31 => false];
$GLOBALS['wg_post_meta'] = [
    101 => '[chained-quiz 30]',
    102 => '[chained-quiz 31]',
    103 => '[chained-quiz 30] [chained-quiz id="31"]',
];

function add_action($hook, $callback, $priority = 10) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function __($text, $domain = null) { return $text; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function get_current_blog_id() { return 8; }
function get_current_user_id() { return 7; }
function get_post_meta($post_id, $key, $single) { return $GLOBALS['wg_post_meta'][$post_id] ?? ''; }
function is_user_logged_in() { return true; }

class Wg_Quiz_Status_Wpdb {
    public $base_prefix = 'wp_';

    public function prepare($query, ...$args) {
        foreach ($args as $arg) {
            $query = preg_replace('/%[ds]/', (string) $arg, $query, 1);
        }
        return $query;
    }

    public function get_var($query) {
        if (str_starts_with($query, 'SHOW TABLES LIKE')) {
            return trim(substr($query, strlen('SHOW TABLES LIKE')));
        }
        if (preg_match('/c\.quiz_id = (\d+)/', $query, $matches)) {
            return !empty($GLOBALS['wg_completed_quizzes'][(int) $matches[1]]) ? 1 : 0;
        }
        return 0;
    }
}

$GLOBALS['wpdb'] = new Wg_Quiz_Status_Wpdb();

require_once dirname(__DIR__) . '/includes/LMS/Quiz_Status.php';
require_once dirname(__DIR__) . '/includes/Integrations/GenerateBlocks.php';

use wg\membership\Integrations\GenerateBlocks;
use wg\membership\LMS\Quiz_Status;

function assert_icons($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

assert_icons(Quiz_Status::extract_quiz_ids_from_shortcode('[chained-quiz 30] [chained-quiz quiz_id="31"]') === [30, 31], 'all unique quiz IDs must be extracted');
assert_icons(Quiz_Status::user_has_completed_all_quizzes_for_post(101), 'single completed quiz must complete the stop');
assert_icons(!Quiz_Status::user_has_completed_all_quizzes_for_post(102), 'incomplete quiz must keep the stop incomplete');
assert_icons(!Quiz_Status::user_has_completed_all_quizzes_for_post(103), 'all quizzes must be completed');

$title_block = [
    'blockName' => 'generateblocks/text',
    'attrs' => ['tagName' => 'h4'],
    'innerHTML' => '<h4 class="gb-text">{{post_meta key:wg_stop_title}}</h4>',
];
$completed_instance = (object) ['context' => ['postId' => 101]];
$incomplete_instance = (object) ['context' => ['postId' => 102]];
$completed = GenerateBlocks::render_etappenstopp_icons('<h4 class="gb-text">Stop 1</h4>', $title_block, $completed_instance);
$incomplete = GenerateBlocks::render_etappenstopp_icons('<h4 class="gb-text">Stop 2</h4>', $title_block, $incomplete_instance);
assert_icons(substr_count($completed, 'wg-stop-status-icon') === 1, 'completed stop must render exactly one status icon');
assert_icons(str_contains($completed, 'data-stop-status="complete"'), 'completed state must be exposed semantically');
assert_icons(!str_contains($incomplete, 'wg-stop-status-icon'), 'incomplete stop must not render a status icon');
assert_icons(str_contains($incomplete, 'data-stop-status="incomplete"'), 'incomplete state must be exposed semantically');

$entry_block = [
    'blockName' => 'generateblocks/text',
    'attrs' => ['tagName' => 'button', 'className' => 'toggle-box'],
    'innerHTML' => '<button class="gb-text toggle-box"><span class="gb-shape"></span><span class="gb-text">Video</span></button>',
];
$entry = GenerateBlocks::render_etappenstopp_icons($entry_block['innerHTML'], $entry_block, null);
assert_icons(substr_count($entry, 'wg-entry-toggle-icon__closed') === 1, 'entry must contain the closed icon once');
assert_icons(substr_count($entry, 'wg-entry-toggle-icon__open') === 1, 'entry must contain the open icon once');
assert_icons(substr_count($entry, 'gb-accordion__toggle-icon-open') === 1 && substr_count($entry, 'gb-accordion__toggle-icon-close') === 1, 'entry must reuse the GenerateBlocks accordion icon states');
assert_icons(str_contains($entry, 'aria-expanded="false"'), 'entry must start in the collapsed state');
assert_icons(str_contains($entry, 'wg-etappenstopp-entry-toggle'), 'entry must receive its semantic interaction class');

echo "Etappenstopp icons harness: 13 scenarios passed.\n";
