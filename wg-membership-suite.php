<?php
/**
 * Plugin Name: webGefaehrte Membership Suite
 * Plugin URI: https://webgefaehrte.de/
 * Description: Membership, checkout, login, 2FA, LMS and frontend integrations for webGefaehrte sites.
 * Version: 1.0.0
 * Author: Jan (webGefährte)
 * Text Domain: wg-membership-suite
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WG_MEMBERSHIP_SUITE_FILE', __FILE__);
define('WG_MEMBERSHIP_SUITE_PATH', plugin_dir_path(__FILE__));
define('WG_MEMBERSHIP_SUITE_URL', plugin_dir_url(__FILE__));
define('WG_MEMBERSHIP_SUITE_VERSION', '0.1.0');

$wg_membership_suite_files = [
    'includes/Config.php',
    'includes/Helpers/Debug.php',
    'includes/Helpers/Sanitizer.php',
    'includes/Helpers/User_Data.php',
    'includes/CPT/Angebot_CPT.php',
    'includes/CPT/Bestellung_CPT.php',
    'includes/CPT/Rechnung_CPT.php',
    'includes/Checkout/Pricing.php',
    'includes/Checkout/Order_Context.php',
    'includes/Checkout/Account_Resolver.php',
    'includes/Checkout/Order_Finalizer.php',
    'includes/Checkout/Step_1.php',
    'includes/Checkout/Step_2_User_Data.php',
    'includes/Checkout/Step_3_Order.php',
    'includes/Admin/Settings_Page.php',
    'includes/Admin/User_Profile_Fields.php',
    'includes/Admin/Angebot_Meta_Boxes.php',
    'includes/Admin/Bestellung_Admin.php',
    'includes/Shortcodes/User_Data_Preview.php',
    'includes/Shortcodes/Order_Overview.php',
    'includes/Shortcodes/Avatar_Form.php',
    'includes/Shortcodes/Login_Form.php',
    'includes/Shortcodes/Password_Reset_Form.php',
    'includes/Shortcodes/User_Downloads.php',
    'includes/Login/Secure_Login.php',
    'includes/Login/Two_Factor_Email.php',
    'includes/Integrations/Contact_Form_7.php',
    'includes/Integrations/GenerateBlocks.php',
    'includes/Integrations/MediaElement.php',
    'includes/Frontend/Assets.php',
    'includes/Frontend/Avatar.php',
    'includes/Frontend/Spickzettel.php',
    'includes/LMS/Quiz_Status.php',
    'includes/LMS/Progress.php',
    'includes/LMS/Points.php',
    'includes/LMS/Activity_Sync.php',
    'includes/Activator.php',
    'includes/Deactivator.php',
    'includes/Compatibility.php',
    'includes/Plugin.php',
];

foreach ($wg_membership_suite_files as $wg_membership_suite_file) {
    require_once WG_MEMBERSHIP_SUITE_PATH . $wg_membership_suite_file;
}

register_activation_hook(__FILE__, ['wg\\membership\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['wg\\membership\\Deactivator', 'deactivate']);

wg\membership\Plugin::boot();
