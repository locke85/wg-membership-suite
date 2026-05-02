<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('wg_debug_log')) { function wg_debug_log($message) { \wg\membership\Helpers\Debug::log($message); } }
if (!function_exists('wg_get_angebot_select_options')) { function wg_get_angebot_select_options() { return \wg\membership\Checkout\Pricing::get_angebot_select_options(); } }
if (!function_exists('wg_sanitize_angebot_select_value')) { function wg_sanitize_angebot_select_value($field, $value) { return \wg\membership\Checkout\Pricing::sanitize_angebot_select_value($field, $value); } }
if (!function_exists('wg_parse_decimal')) { function wg_parse_decimal($value) { return \wg\membership\Checkout\Pricing::parse_decimal($value); } }
if (!function_exists('wg_calculate_angebot_price')) { function wg_calculate_angebot_price($netto, $mwst_key) { return \wg\membership\Checkout\Pricing::calculate_angebot_price($netto, $mwst_key); } }
if (!function_exists('wg_build_angebot_pricing')) { function wg_build_angebot_pricing($angebot_id) { return \wg\membership\Checkout\Pricing::build_angebot_pricing($angebot_id); } }
if (!function_exists('wg_get_valid_angebot_post')) { function wg_get_valid_angebot_post($angebot_id) { return \wg\membership\Checkout\Pricing::get_valid_angebot_post($angebot_id); } }
if (!function_exists('wg_angebot_has_category_slug')) { function wg_angebot_has_category_slug($angebot_id, $slug) { return \wg\membership\Checkout\Pricing::angebot_has_category_slug($angebot_id, $slug); } }
if (!function_exists('wg_get_allowed_checkout_payment_methods')) { function wg_get_allowed_checkout_payment_methods() { return \wg\membership\Checkout\Pricing::get_allowed_checkout_payment_methods(); } }
if (!function_exists('wg_sanitize_checkout_payment_method')) { function wg_sanitize_checkout_payment_method($value) { return \wg\membership\Checkout\Pricing::sanitize_checkout_payment_method($value); } }
if (!function_exists('wg_get_payment_type_from_context')) { function wg_get_payment_type_from_context($intervall, $zahlungsart) { return \wg\membership\Checkout\Pricing::get_payment_type_from_context($intervall, $zahlungsart); } }
if (!function_exists('wg_enforce_angebot_payment_method')) { function wg_enforce_angebot_payment_method($angebot_id, $requested_zahlungsart = '') { return \wg\membership\Checkout\Pricing::enforce_angebot_payment_method($angebot_id, $requested_zahlungsart); } }
if (!function_exists('wg_get_angebot_role_slug')) { function wg_get_angebot_role_slug($angebot_id) { return \wg\membership\Checkout\Pricing::get_angebot_role_slug($angebot_id); } }
if (!function_exists('wg_ensure_angebot_role_exists')) { function wg_ensure_angebot_role_exists($angebot_id) { return \wg\membership\Checkout\Pricing::ensure_angebot_role_exists($angebot_id); } }
if (!function_exists('wg_assign_angebot_role_to_user')) { function wg_assign_angebot_role_to_user($user_id, $angebot_id) { return \wg\membership\Checkout\Pricing::assign_angebot_role_to_user($user_id, $angebot_id); } }
if (!function_exists('wg_store_order_context')) { function wg_store_order_context($user_id, $angebot_id, array $data = []) { return \wg\membership\Checkout\Order_Context::store($user_id, $angebot_id, $data); } }
if (!function_exists('wg_get_order_context')) { function wg_get_order_context($user_id, $token) { return \wg\membership\Checkout\Order_Context::get($user_id, $token); } }
if (!function_exists('wg_delete_order_context')) { function wg_delete_order_context($user_id, $token) { return \wg\membership\Checkout\Order_Context::delete($user_id, $token); } }
if (!function_exists('wg_set_last_checkout_offer_for_user')) { function wg_set_last_checkout_offer_for_user($user_id, $angebot_id, $erweiterung_id = 0) { \wg\membership\Checkout\Order_Context::set_last_offer_for_user($user_id, $angebot_id, $erweiterung_id); } }
if (!function_exists('wg_get_last_checkout_offer_for_user')) { function wg_get_last_checkout_offer_for_user($user_id) { return \wg\membership\Checkout\Order_Context::get_last_offer_for_user($user_id); } }
if (!function_exists('wg_get_prefill_user_data')) { function wg_get_prefill_user_data($user_id = 0) { return \wg\membership\Helpers\User_Data::get_prefill_user_data($user_id); } }
if (!function_exists('wg_save_user_profile_data')) { function wg_save_user_profile_data($user_id, array $input) { return \wg\membership\Helpers\User_Data::save_user_profile_data($user_id, $input); } }
if (!function_exists('wg_create_bestellung_for_order')) { function wg_create_bestellung_for_order($user_id, $angebot_id, array $args = []) { return \wg\membership\Checkout\Order_Finalizer::create_bestellung_for_order($user_id, $angebot_id, $args); } }
if (!function_exists('wg_process_checkout_order_submission')) { function wg_process_checkout_order_submission(array $payload) { return \wg\membership\Checkout\Order_Finalizer::process_checkout_order_submission($payload); } }
if (!function_exists('wg_finalize_bestellung_account_flow')) { function wg_finalize_bestellung_account_flow($bestellung_id, array $customer_data, array $forced_decision = []) { return \wg\membership\Checkout\Order_Finalizer::finalize_bestellung_account_flow($bestellung_id, $customer_data, $forced_decision); } }
if (!function_exists('wg_complete_bestellung_for_user')) { function wg_complete_bestellung_for_user($bestellung_id, $user_id, array $customer_data, $created_new_account = false, $initial_password = '') { return \wg\membership\Checkout\Order_Finalizer::complete_bestellung_for_user($bestellung_id, $user_id, $customer_data, $created_new_account, $initial_password); } }
if (!function_exists('wg_get_checkout_order_token')) { function wg_get_checkout_order_token($input, $user_id = 0) { return \wg\membership\Checkout\Order_Finalizer::get_checkout_order_token($input, $user_id); } }
if (!function_exists('wg_find_bestellung_by_checkout_token')) { function wg_find_bestellung_by_checkout_token($token) { return \wg\membership\Checkout\Order_Finalizer::find_bestellung_by_checkout_token($token); } }
if (!function_exists('wg_update_bestellung_status')) { function wg_update_bestellung_status($bestellung_id, $status, $note = '') { return \wg\membership\Checkout\Order_Finalizer::update_bestellung_status($bestellung_id, $status, $note); } }
if (!function_exists('wg_add_bestellung_comment')) { function wg_add_bestellung_comment($bestellung_id, $content) { return \wg\membership\Checkout\Order_Finalizer::add_bestellung_comment($bestellung_id, $content); } }
if (!function_exists('wg_get_bestellung_summary_lines')) { function wg_get_bestellung_summary_lines($bestellung_id) { return \wg\membership\Checkout\Order_Finalizer::get_bestellung_summary_lines($bestellung_id); } }
if (!function_exists('wg_get_bestellung_admin_edit_link')) { function wg_get_bestellung_admin_edit_link($bestellung_id) { return \wg\membership\Checkout\Order_Finalizer::get_bestellung_admin_edit_link($bestellung_id); } }
if (!function_exists('wg_get_order_resolution_link')) { function wg_get_order_resolution_link($bestellung_id, $resolution, $user_id = 0) { return \wg\membership\Checkout\Order_Finalizer::get_order_resolution_link($bestellung_id, $resolution, $user_id); } }
if (!function_exists('wg_build_checkout_order_payload')) { function wg_build_checkout_order_payload(array $input, $user_id = 0) { return \wg\membership\Checkout\Order_Finalizer::build_checkout_order_payload($input, $user_id); } }
if (!function_exists('wg_collect_checkout_user_candidates')) { function wg_collect_checkout_user_candidates($customer_data) { return \wg\membership\Checkout\Account_Resolver::collect_checkout_user_candidates($customer_data); } }
if (!function_exists('wg_evaluate_checkout_account_decision')) { function wg_evaluate_checkout_account_decision($customer_data) { return \wg\membership\Checkout\Account_Resolver::evaluate_checkout_account_decision($customer_data); } }
if (!function_exists('wg_create_checkout_user_account')) { function wg_create_checkout_user_account(array $customer_data) { return \wg\membership\Checkout\Account_Resolver::create_checkout_user_account($customer_data); } }
if (!function_exists('wg_fill_user_account_if_empty')) { function wg_fill_user_account_if_empty($user_id, array $customer_data, $allow_email_update = false) { return \wg\membership\Checkout\Account_Resolver::fill_user_account_if_empty($user_id, $customer_data, $allow_email_update); } }
if (!function_exists('wg_merge_order_products_into_user')) { function wg_merge_order_products_into_user($user_id, $angebot_id, $erweiterung_id = 0) { \wg\membership\Checkout\Order_Finalizer::merge_order_products_into_user($user_id, $angebot_id, $erweiterung_id); } }
if (!function_exists('wg_get_checkout_step2_field_mapping')) { function wg_get_checkout_step2_field_mapping() { return \wg\membership\Checkout\Step_2_User_Data::get_checkout_step2_field_mapping(); } }
if (!function_exists('wg_is_cf7_checkout_step2_form')) { function wg_is_cf7_checkout_step2_form($contact_form) { return \wg\membership\Checkout\Step_2_User_Data::is_cf7_checkout_step2_form($contact_form); } }
if (!function_exists('wg_is_cf7_checkout_step3_form')) { function wg_is_cf7_checkout_step3_form($contact_form) { return \wg\membership\Checkout\Step_3_Order::is_cf7_checkout_step3_form($contact_form); } }
if (!function_exists('wg_cf7_checkout_step2_save_user_data')) { function wg_cf7_checkout_step2_save_user_data($contact_form) { \wg\membership\Checkout\Step_2_User_Data::cf7_checkout_step2_save_user_data($contact_form); } }
if (!function_exists('wg_cf7_checkout_step3_create_order')) { function wg_cf7_checkout_step3_create_order($contact_form) { \wg\membership\Checkout\Step_3_Order::cf7_checkout_step3_create_order($contact_form); } }
if (!function_exists('wg_cf7_prefill_checkout_step2_form_tag')) { function wg_cf7_prefill_checkout_step2_form_tag($tag, $replace = false) { return \wg\membership\Checkout\Step_2_User_Data::cf7_prefill_checkout_step2_form_tag($tag, $replace); } }
if (!function_exists('wg_render_checkout_duplicate_notice')) { function wg_render_checkout_duplicate_notice($customer_data) { return \wg\membership\Checkout\Step_3_Order::render_checkout_duplicate_notice($customer_data); } }
if (!function_exists('wg_handle_cf7_step1_offer_redirect')) { function wg_handle_cf7_step1_offer_redirect() { return \wg\membership\Checkout\Step_1::handle_cf7_step1_offer_redirect(); } }
if (!function_exists('wg_is_legacy_checkout_allowed')) { function wg_is_legacy_checkout_allowed() { return \wg\membership\Checkout\Step_3_Order::is_legacy_checkout_allowed(); } }
if (!function_exists('wg_login_form_shortcode')) { function wg_login_form_shortcode($atts = []) { return \wg\membership\Shortcodes\Login_Form::render($atts); } }
if (!function_exists('wg_password_reset_form_shortcode')) { function wg_password_reset_form_shortcode($atts = []) { return \wg\membership\Shortcodes\Password_Reset_Form::render($atts); } }
if (!function_exists('wg_user_pdf_downloads_shortcode')) { function wg_user_pdf_downloads_shortcode() { return \wg\membership\Shortcodes\User_Downloads::render(); } }
if (!function_exists('wg_user_has_2fa_enabled')) { function wg_user_has_2fa_enabled($user_id = 0) { return \wg\membership\Login\Two_Factor_Email::user_has_2fa_enabled($user_id); } }
if (!function_exists('wg_session_requires_2fa')) { function wg_session_requires_2fa($user_id = 0) { return \wg\membership\Login\Two_Factor_Email::session_requires_2fa($user_id); } }
if (!function_exists('wg_issue_2fa_code')) { function wg_issue_2fa_code(\WP_User $user, $redirect_to = '') { return \wg\membership\Login\Two_Factor_Email::issue_2fa_code($user, $redirect_to); } }
if (!function_exists('wg_get_2fa_pending_sessions')) { function wg_get_2fa_pending_sessions($user_id = 0) { return \wg\membership\Login\Two_Factor_Email::get_2fa_pending_sessions($user_id); } }
if (!function_exists('wg_store_2fa_pending_sessions')) { function wg_store_2fa_pending_sessions($pending, $user_id = 0) { \wg\membership\Login\Two_Factor_Email::store_2fa_pending_sessions($pending, $user_id); } }
if (!function_exists('get_spickzettel_url')) { function get_spickzettel_url() { return \wg\membership\Frontend\Spickzettel::get_spickzettel_url(); } }
