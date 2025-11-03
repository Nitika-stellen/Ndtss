<?php
/**
 * Plugin Name:      Country and State Selection Addon for Gravity Forms
 * Plugin URI:       https://plugins.hirewebxperts.com/
 * Description:      Country and State Selection Addon for Gravity Forms is used to add country and state dropdown fields depending on your needs. By default, all the countries of the world appear on the country select dropdown on the form and once you select any specific country, its respective states appear in the state select dropdown. All the countries and states are already available in the addon by default. 
 * Version:          1.2
 * Author:           Coder426
 * Author URI:       https://hirewebxperts.com/
 * Text Domain:      gforms-addon-for-country-and-state-selection
 * Domain Path:      /languages
 * License:          GPLv3
 * License URI:      https://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined('ABSPATH')) { exit; }

// Ensure WordPress functions are available
if (!function_exists('plugin_dir_url')) require_once(ABSPATH . 'wp-admin/includes/plugin.php');

// Plugin constants
$version = rand();
$name    = 'gforms-addon-for-country-and-state-selection';

define('GF_CWS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GF_CWS_PLUGIN_DIR', dirname(__FILE__));
define('GF_CWS_JS', GF_CWS_PLUGIN_URL . 'assets/js/');
define('GF_CWS_CSS', GF_CWS_PLUGIN_URL . 'assets/css/');
define('GF_CWS_IMG', GF_CWS_PLUGIN_URL . 'assets/images/');
define('GF_CWS_INC', GF_CWS_PLUGIN_DIR . '/includes/');
define('GF_CWS_VER', $version);
define('GF_CWS_NAME', $name);

// Load the main Add-On class after Gravity Forms is loaded
add_action('gform_loaded', array('GF_CWS_Field_AddOn', 'load'), 5);
class GF_CWS_Field_AddOn {
    public static function load() {
        if (!method_exists('GFForms', 'include_addon_framework')) {
            return;
        }
        require_once('class-gf-cws-fields.php');
    }
}