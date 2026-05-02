<?php

namespace wg\membership;

use WP_Site;
use wg\membership\Admin\Angebot_Meta_Boxes;
use wg\membership\Admin\Bestellung_Admin;
use wg\membership\Admin\Settings_Page;
use wg\membership\Admin\User_Profile_Fields;
use wg\membership\Checkout\Step_1;
use wg\membership\Checkout\Step_2_User_Data;
use wg\membership\Checkout\Step_3_Order;
use wg\membership\CPT\Angebot_CPT;
use wg\membership\CPT\Bestellung_CPT;
use wg\membership\CPT\Rechnung_CPT;
use wg\membership\Frontend\Assets;
use wg\membership\Frontend\Avatar;
use wg\membership\Frontend\Spickzettel;
use wg\membership\Integrations\Contact_Form_7;
use wg\membership\Integrations\GenerateBlocks;
use wg\membership\Integrations\MediaElement;
use wg\membership\LMS\Activity_Sync;
use wg\membership\LMS\Points;
use wg\membership\LMS\Progress;
use wg\membership\LMS\Quiz_Status;
use wg\membership\Login\Secure_Login;
use wg\membership\Login\Two_Factor_Email;
use wg\membership\Shortcodes\Avatar_Form;
use wg\membership\Shortcodes\Login_Form;
use wg\membership\Shortcodes\Order_Overview;
use wg\membership\Shortcodes\Password_Reset_Form;
use wg\membership\Shortcodes\User_Data_Preview;
use wg\membership\Shortcodes\User_Downloads;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    public static function boot() {
        add_action('plugins_loaded', [static::class, 'load_textdomain']);
        add_action('init', [Angebot_CPT::class, 'register'], 5);
        add_action('init', [Bestellung_CPT::class, 'register'], 5);
        add_action('init', [Rechnung_CPT::class, 'register'], 5);

        Settings_Page::register();
        Angebot_Meta_Boxes::register();
        User_Profile_Fields::register();
        Bestellung_Admin::register();

        if (is_multisite()) {
            add_action('wp_initialize_site', [static::class, 'initialize_new_site'], 10, 1);
        }

        if (!Config::is_site_enabled()) {
            return;
        }

        Step_1::register();
        Step_2_User_Data::register();
        Step_3_Order::register();
        Contact_Form_7::register();

        User_Data_Preview::register();
        Order_Overview::register();
        Avatar_Form::register();
        Login_Form::register();
        Password_Reset_Form::register();
        User_Downloads::register();

        Secure_Login::register();
        Two_Factor_Email::register();
        GenerateBlocks::register();
        MediaElement::register();
        Assets::register();
        Avatar::register();
        Spickzettel::register();

        Quiz_Status::register();
        Progress::register();
        Points::register();
        Activity_Sync::register();
    }

    public static function initialize_new_site($new_site) {
        if (!is_multisite() || !get_site_option(Config::NETWORK_ACTIVATED_OPTION)) {
            return;
        }

        if ($new_site instanceof WP_Site) {
            $blog_id = (int) $new_site->blog_id;
        } elseif (is_object($new_site) && isset($new_site->blog_id)) {
            $blog_id = (int) $new_site->blog_id;
        } else {
            return;
        }

        if ($blog_id <= 0) {
            return;
        }

        switch_to_blog($blog_id);
        Activator::activate_single_site();
        restore_current_blog();
    }

    public static function load_textdomain() {
        load_plugin_textdomain('wg-membership-suite', false, dirname(plugin_basename(WG_MEMBERSHIP_SUITE_FILE)) . '/languages');
    }
}
