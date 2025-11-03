<?php
/*
Plugin Name: Restrict Dates Add-On for Gravity Forms
Plugin Url: https://pluginscafe.com/plugin/restrict-dates-for-gravity-forms-pro/
Version: 1.2.3
Description: This plugin adds date restrict options on gravity forms datepicker field.
Author: PluginsCafe
Author URI: https://pluginscafe.com
License: GPLv2 or later
Text Domain: restrict-dates-add-on-for-gravity-forms
*/

if (!defined('ABSPATH')) {
    exit;
}

define('GF_RESTRICT_DATES_ADDON_VERSION', '1.2.3');
define('GF_RESTRICT_DATES_ADDON_URL', plugin_dir_url(__FILE__));

if (function_exists('rdfgf_fs')) {
    rdfgf_fs()->set_basename(false, __FILE__);
} else {
    if (! function_exists('rdfgf_fs')) {
        // Create a helper function for easy SDK access.
        function rdfgf_fs() {
            global $rdfgf_fs;

            if (! isset($rdfgf_fs)) {
                // Include Freemius SDK.
                require_once dirname(__FILE__) . '/vendor/freemius/start.php';
                $rdfgf_fs = fs_dynamic_init(array(
                    'id'                  => '15094',
                    'slug'                => 'restrict-dates-for-gravity-forms',
                    'premium_slug'        => 'restrict-dates-for-gravity-forms-pro',
                    'type'                => 'plugin',
                    'public_key'          => 'pk_febc62d94850f83a5b528b4a6db0b',
                    'is_premium'          => false,
                    'premium_suffix'      => 'Pro',
                    // If your plugin is a serviceware, set this option to false.
                    'has_addons'          => false,
                    'has_paid_plans'      => true,
                    // Automatically removed in the free version. If you're not using the
                    'menu'                => array(
                        'slug'           => 'restrict-dates-for-gravity-forms-pro',
                        'support'        => false,
                        'contact'        => false,
                        'parent'         => array(
                            'slug' => 'options-general.php',
                        ),
                    ),
                    'is_live'        => true,
                ));
            }

            return $rdfgf_fs;
        }

        // Init Freemius.
        rdfgf_fs();
        // Signal that SDK was initiated.
        do_action('rdfgf_fs_loaded');
    }
}

if (is_admin()) {
    require_once 'admin/class-admin.php';
}

add_action('gform_loaded', array('GF_Restrict_Dates_AddOn_Bootstrap', 'load'), 5);
class GF_Restrict_Dates_AddOn_Bootstrap {
    public static function load() {
        if (! method_exists('GFForms', 'include_addon_framework')) {
            return;
        }
        // are we on GF 2.5+
        define('GFIC_GF_MIN_2_5', version_compare(GFCommon::$version, '2.5-dev-1', '>='));

        require_once('class-gfrestrictdates.php');
        GFAddOn::register('GFRestrictDatesAddOn');
    }
}

function gf_restrict_dates() {
    return GFRestrictDatesAddOn::get_instance();
}
