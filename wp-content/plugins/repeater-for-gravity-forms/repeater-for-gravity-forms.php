<?php
/**
 * Plugin Name: Repeater for Gravity Forms
 * Description: The add-on that allows specified groups of fields to be repeated by the user.
 * Plugin URI: https://add-ons.org/plugin/gravity-forms-repeater-fields/
 * Version: 2.4.3
 * Author: add-ons.org
 * Author URI: https://add-ons.org/
 * Text Domain: repeater-for-gravity-forms
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
global $yeeaddons_gf_repeater_settings;
define( 'SUPERADDONS_GF_REPEATER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SUPERADDONS_GF_REPEATER_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
add_action( 'gform_loaded', array( 'Superaddons_Grepeater_Field_AddOn_Init', 'load' ), 5 );
class Superaddons_Grepeater_Field_AddOn_Init {
    public static function load() {
        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }
        include SUPERADDONS_GF_REPEATER_PLUGIN_PATH."add_on.php";
        GFAddOn::register( 'Superaddons_Grepeater_Field_Addon' );
    }
    
}
class Yeeaddons_GF_Repeater_Init {
    function __construct(){
        include_once SUPERADDONS_GF_REPEATER_PLUGIN_PATH."yeekit/document.php";
        add_action( "yeeaddons_gf_repeater_settings", array($this,"yeeaddons_gf_repeater_settings"),10);
        add_action( 'init', array($this,'load_textdomain' ));
    }
    function load_textdomain(){
    	load_plugin_textdomain( 'repeater-for-gravity-forms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
    }
    function yeeaddons_gf_repeater_settings(){
		?>
		<li class="field_field_repeater_initial_rows_setting field_setting">
			<label class="section_label">
				<?php esc_html_e('Initial Rows', 'repeater-for-gravity-forms'); ?>
			</label>
			<div class="pro_disable">
				<input placeholder="1" type="text" id="repeater_initial_rows" placeholder="Upgrade to pro version" value="" readonly="true">
			</div>
			<?php esc_html_e("The number of rows at start, if empty no rows will be created",'repeater-for-gravity-forms') ?>
		</li>
		<li class="field_field_repeater_initial_rows_setting field_setting">
			<label class="section_label">
				<?php esc_html_e('Map field with Initial Rows', 'repeater-for-gravity-forms'); ?>
			</label>
			<div class="pro_disable">
				<input type="text" id="repeater_initial_rows_map" placeholder="Upgrade to pro version" value="" readonly="true" >
			</div>
			<?php esc_html_e("Map field with Initial Rows (ID field)",'repeater-for-gravity-forms') ?>
		</li>
		<li class="field_field_repeater_max_setting field_setting">
			<label class="section_label">
				<?php esc_html_e('Limit', 'repeater-for-gravity-forms'); ?>
			</label>
			<input type="text" id="repeater_max" placeholder="5" value="" onchange="SetFieldProperty('repeater_max', this.value);">
			<?php esc_html_e("Max number of rows applicable by the user, leave empty for no limit (Free version limit = 5 )",'repeater-for-gravity-forms') ?>
		</li>
		<?php
	}
}
$yeeaddons_gf_repeater_settings = new Yeeaddons_GF_Repeater_Init;