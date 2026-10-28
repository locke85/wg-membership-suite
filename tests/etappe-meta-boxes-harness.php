<?php

/**
 * Minimal WordPress compatibility harness for Etappe editor meta boxes.
 *
 * Run with: php tests/etappe-meta-boxes-harness.php
 */

define('ABSPATH', __DIR__ . '/wordpress/');

$GLOBALS['wg_actions'] = [];
$GLOBALS['wg_metaboxes'] = [];
$GLOBALS['wg_post_types'] = [1 => 'post', 2 => 'page', 3 => 'post'];
$GLOBALS['wg_post_meta'] = [
    1 => ['wg_besenwagen_code' => '[chained-quiz 32]', 'wg_stop_title' => 'Team'],
    2 => ['wg_etappe_title' => 'Etappe 1', 'wg_img_intro' => '77'],
];
$GLOBALS['wg_updates'] = [];
$GLOBALS['wg_capable'] = true;
$GLOBALS['wg_autosave'] = false;
$GLOBALS['wg_revision'] = false;
$GLOBALS['wg_nonce_valid'] = true;
$GLOBALS['wg_media_enqueued'] = false;
$GLOBALS['wg_screen'] = (object) ['post_type' => 'page'];

function __($value, $domain = '') { return $value; }
function esc_html__($value, $domain = '') { return esc_html($value); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_textarea($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function wp_json_encode($value) { return json_encode($value); }
function wp_unslash($value) { return is_string($value) ? stripslashes($value) : $value; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
function absint($value) { return abs((int) $value); }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['wg_actions'][] = [$hook, $callback, $priority, $accepted_args]; }
function add_meta_box($id, $title, $callback, $screen, $context = 'advanced', $priority = 'default') { $GLOBALS['wg_metaboxes'][$id] = compact('id', 'title', 'callback', 'screen', 'context', 'priority'); }
function wp_nonce_field($action, $name) { echo '<input type="hidden" name="' . esc_attr($name) . '" value="valid-' . esc_attr($action) . '">'; }
function wp_verify_nonce($nonce, $action) { return $GLOBALS['wg_nonce_valid'] && $nonce === 'valid-' . $action; }
function get_post_type($post_id) { return $GLOBALS['wg_post_types'][$post_id] ?? ''; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['wg_post_meta'][$post_id][$key] ?? ''; }
function update_post_meta($post_id, $key, $value) { $GLOBALS['wg_post_meta'][$post_id][$key] = $value; $GLOBALS['wg_updates'][] = [$post_id, $key, $value]; return true; }
function current_user_can($capability, $post_id = null) { return $GLOBALS['wg_capable']; }
function wp_is_post_autosave($post_id) { return $GLOBALS['wg_autosave']; }
function wp_is_post_revision($post_id) { return $GLOBALS['wg_revision']; }
function wp_get_attachment_url($attachment_id) { return $attachment_id ? 'https://example.test/media/' . $attachment_id . '.jpg' : false; }
function get_current_screen() { return $GLOBALS['wg_screen']; }
function wp_enqueue_media() { $GLOBALS['wg_media_enqueued'] = true; }

require_once dirname(__DIR__) . '/includes/Admin/Etappe_Meta_Boxes.php';

use wg\membership\Admin\Etappe_Meta_Boxes;

function assert_etappe($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

assert_etappe(count(Etappe_Meta_Boxes::post_fields()) === 9, 'post schema must contain all nine Etappenstopp fields');
assert_etappe(count(Etappe_Meta_Boxes::page_fields()) === 12, 'page schema must contain all twelve Etappe fields');
assert_etappe(isset(Etappe_Meta_Boxes::post_fields()['wg_besenwagen_code']), 'post schema must expose the quiz shortcode');
assert_etappe(isset(Etappe_Meta_Boxes::page_fields()['wg_img_intro']), 'page schema must expose the intro image');

Etappe_Meta_Boxes::register();
$registered_hooks = array_column($GLOBALS['wg_actions'], 0);
foreach (['add_meta_boxes_post', 'add_meta_boxes_page', 'save_post_post', 'save_post_page', 'admin_enqueue_scripts'] as $hook) {
    assert_etappe(in_array($hook, $registered_hooks, true), "{$hook} must be registered");
}

Etappe_Meta_Boxes::add_post_metabox();
Etappe_Meta_Boxes::add_page_metabox();
assert_etappe($GLOBALS['wg_metaboxes'][Etappe_Meta_Boxes::POST_BOX_ID]['screen'] === 'post', 'Etappenstopp box must be post-only');
assert_etappe($GLOBALS['wg_metaboxes'][Etappe_Meta_Boxes::PAGE_BOX_ID]['screen'] === 'page', 'Etappe box must be page-only');

ob_start();
Etappe_Meta_Boxes::render_post_fields((object) ['ID' => 1]);
$post_html = ob_get_clean();
assert_etappe(strpos($post_html, 'id="wg_besenwagen_code"') !== false, 'post render must contain the quiz shortcode input');
assert_etappe(strpos($post_html, '[chained-quiz 32]') !== false, 'post render must show the existing shortcode');

ob_start();
Etappe_Meta_Boxes::render_page_fields((object) ['ID' => 2]);
$page_html = ob_get_clean();
assert_etappe(strpos($page_html, 'id="wg_etappe_title"') !== false, 'page render must contain the Etappe title');
assert_etappe(strpos($page_html, 'https://example.test/media/77.jpg') !== false, 'page render must show the existing image preview');

$_POST = [
    Etappe_Meta_Boxes::POST_NONCE_NAME => 'valid-' . Etappe_Meta_Boxes::POST_NONCE_ACTION,
    'wg_besenwagen_code' => ' [chained-quiz 88] ',
    'wg_besenwagen_intro' => " <b>Intro</b>\nZeile 2 ",
];
Etappe_Meta_Boxes::save_post_fields(1);
assert_etappe($GLOBALS['wg_post_meta'][1]['wg_besenwagen_code'] === '[chained-quiz 88]', 'shortcode must be stored as sanitized text');
assert_etappe($GLOBALS['wg_post_meta'][1]['wg_besenwagen_intro'] === "Intro\nZeile 2", 'intro must preserve lines and remove markup');

$GLOBALS['wg_updates'] = [];
$GLOBALS['wg_nonce_valid'] = false;
Etappe_Meta_Boxes::save_post_fields(1);
assert_etappe(count($GLOBALS['wg_updates']) === 0, 'invalid nonce must block writes');
$GLOBALS['wg_nonce_valid'] = true;

$GLOBALS['wg_updates'] = [];
Etappe_Meta_Boxes::save_post_fields(2);
assert_etappe(count($GLOBALS['wg_updates']) === 0, 'wrong post type must block writes');

$GLOBALS['wg_capable'] = false;
$GLOBALS['wg_updates'] = [];
Etappe_Meta_Boxes::save_post_fields(1);
assert_etappe(count($GLOBALS['wg_updates']) === 0, 'missing edit capability must block writes');
$GLOBALS['wg_capable'] = true;

$GLOBALS['wg_autosave'] = true;
$GLOBALS['wg_updates'] = [];
Etappe_Meta_Boxes::save_post_fields(1);
assert_etappe(count($GLOBALS['wg_updates']) === 0, 'autosave must block writes');
$GLOBALS['wg_autosave'] = false;

$GLOBALS['wg_revision'] = true;
$GLOBALS['wg_updates'] = [];
Etappe_Meta_Boxes::save_post_fields(1);
assert_etappe(count($GLOBALS['wg_updates']) === 0, 'revision must block writes');
$GLOBALS['wg_revision'] = false;

$_POST = [
    Etappe_Meta_Boxes::PAGE_NONCE_NAME => 'valid-' . Etappe_Meta_Boxes::PAGE_NONCE_ACTION,
    'wg_img_intro' => '-91files',
    'wg_p_introtext' => '<em>Etappenintro</em>',
];
Etappe_Meta_Boxes::save_page_fields(2);
assert_etappe($GLOBALS['wg_post_meta'][2]['wg_img_intro'] === 91, 'image value must be normalized to an attachment ID');
assert_etappe($GLOBALS['wg_post_meta'][2]['wg_p_introtext'] === 'Etappenintro', 'page intro must be sanitized');

Etappe_Meta_Boxes::enqueue_page_media('post.php');
assert_etappe($GLOBALS['wg_media_enqueued'], 'page editor must enqueue the media picker');

echo "Etappe meta boxes harness: 12 scenarios passed.\n";
