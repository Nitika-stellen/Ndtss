<?php
/**
 * Plugin Name: Database Manager - WP Adminer
 * Description: Manage the database from your WordPress Dashboard using Adminer.
 * Version: 4.3.2
 * Stable tag: 4.3.2
 * Adminer version: 5.4.1
 * Author: Pexle Chris
 * Author URI: https://www.pexlechris.dev
 * Contributors: pexlechris
 * Domain Path: /languages
 * Requires at least: 4.7.0
 * Tested up to: 6.9
 * Requires PHP: 7.0
 * Tested up to PHP: 8.3
 * License: GPLv2
 */

if ( ! defined( 'ABSPATH' ) ) die;

/**
 * PEXLECHRIS_ADMINER_DIR constant
 *
 * @since 2.1.0
 */
define('PEXLECHRIS_ADMINER_DIR', __DIR__);

/**
 * PEXLECHRIS_ADMINER_MU_PLUGIN_DATA constant
 *
 * @since 3.0.0
 */
define('PEXLECHRIS_ADMINER_MU_PLUGIN_DATA', [
	'file'          => 'pexlechris_adminer_avoid_conflicts_with_other_plugins.php',
	'version'       => '4.0.1',
	'option_name'   => 'pexlechris_adminer_mu_plugin_version',
]);

require_once WP_PLUGIN_DIR . '/pexlechris-adminer/pluggable-functions.php';


add_filter('plugin_action_links_pexlechris-adminer/pexlechris-adminer.php', 'pexlechris_adminer_add_open_wp_adminer_link_in_plugin_action_links', 15, 2);
function pexlechris_adminer_add_open_wp_adminer_link_in_plugin_action_links($links)
{
    $url = get_pexlechris_adminer_url();
    $anchor = '<a href="' . esc_url($url) . '" target="_blank">' . __('Open WP Adminer', 'pexlechris-adminer') . '</a>';
    $new = [$anchor];

    return array_merge($new, $links);
}




/**
 * @since 2.2.0
 * @since 3.0.0 MU Plugin version controlled by option pexlechris_adminer_mu_plugin_version
 */
add_action( 'admin_init', 'pexlechris_adminer_copy_adminer_mu_plugin', 1 );
function pexlechris_adminer_copy_adminer_mu_plugin() {

	extract(PEXLECHRIS_ADMINER_MU_PLUGIN_DATA);

    $option = get_option( $option_name, null );

    if( $option === null) {
		// continue to creating mu plugin
	}elseif( empty($option) ){ // 0 or empty string
		return;
    }elseif( version_compare( $version, $option ) > 0 ){
		// continue to creating mu plugin
	}else{
		return;
	}

    $from = PEXLECHRIS_ADMINER_DIR . '/' . $file . '.txt';
	$to = WPMU_PLUGIN_DIR . '/' . $file;

	if( file_exists($to) ){
        unlink($to);
    }

    wp_mkdir_p(WPMU_PLUGIN_DIR);
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
    $WP_Filesystem_Direct = new WP_Filesystem_Direct([]);
    $WP_Filesystem_Direct->copy($from, $to);

	update_option($option_name, $version);

}

/**
 * @since 2.2.0
 */
register_deactivation_hook(__FILE__, 'pexlechris_adminer_delete_adminer_mu_plugin');
function pexlechris_adminer_delete_adminer_mu_plugin()
{
	extract(PEXLECHRIS_ADMINER_MU_PLUGIN_DATA);

	$mu_plugin = WPMU_PLUGIN_DIR . '/' . $file;
	if (file_exists($mu_plugin)) {
		unlink($mu_plugin);
	}

    // not delete empty option, save this choice for later when you re-activate WP Adminer
    if( get_option($option_name) ){
		delete_option($option_name);
	}
}

add_action( 'plugins_loaded', 'pexlechris_maybe_set_wp_admin_constant', 1 );
function pexlechris_maybe_set_wp_admin_constant()
{
	if( !pexlechris_is_current_url_the_wp_adminer_url() ) return;
	if( !have_current_user_access_to_pexlechris_adminer() ) return;
	if( defined('WP_ADMIN') ) return;

	define('WP_ADMIN', true); // adminer is an admin tool, so must be considered as admin interface
}



add_action( 'plugins_loaded', 'pexlechris_adminer_load_plugin_textdomain', 1 );
function pexlechris_adminer_load_plugin_textdomain() {
	load_plugin_textdomain(
		'pexlechris-adminer',
		false,
		'pexlechris-adminer/languages'
	);
}


//INIT
function determine_if_pexlechris_adminer_will_be_included()
{
	if( have_current_user_access_to_pexlechris_adminer() ){
		/**
		 * @hooked pexlechris_adminer_before_adminer_loads, priority: 10
		 * @hooked pexlechris_adminer_disable_display_errors_before_adminer_loads, priority: 100
		 */
		do_action('pexlechris_adminer_before_adminer_loads');
		include 'inc/adminer_includer.php';
		exit;
	}else{
		do_action('pexlechris_adminer_current_user_has_not_access');
	}
}



if( pexlechris_is_current_url_the_wp_adminer_url() )
{
    add_action('plugins_loaded', 'determine_if_pexlechris_adminer_will_be_included', 2);
}



//POSITION 1
add_action('admin_bar_menu', 'pexlechris_adminer_register_in_wp_admin_bar' , 50);
function pexlechris_adminer_register_in_wp_admin_bar($wp_admin_bar) {

	if( have_current_user_access_to_pexlechris_adminer() ){

        global $wpdb;

		$args = array(
			'id'    => 'wp_adminer',
			'title' => esc_html__('WP Adminer', 'pexlechris-adminer'),
			'href'  => esc_url( get_pexlechris_adminer_url() ),
			'meta'  => [
				'target' => '_blank'
			]
		);
		$wp_admin_bar->add_node($args);

        foreach(pexlechris_adminer_admin_bar_dropdown_items() as $table)
        {
			$name  = $table['name'];
			$label = $table['label'];
			$args  = $table['args'] ?? [];

            $table_name = $wpdb->$name ?? $wpdb->prefix . $name;

			$wp_admin_bar->add_node([
				'id'        => 'wp_adminer_' . $name . ($args ? '_with_args' : ''),
				'title'     => $label,
				'parent'    => 'wp_adminer',
				'href'      => esc_url( get_pexlechris_adminer_url($table_name, $args) ),
				'meta'      => [
					'target' => '_blank'
				]
			]);
		}

	}

}

//POSITION 2
add_action('admin_menu', 'register_pexlechris_adminer_as_tool');
function register_pexlechris_adminer_as_tool(){
	add_submenu_page(
		'tools.php',
		esc_html__('WP Adminer', 'pexlechris-adminer'),
		esc_html__('WP Adminer', 'pexlechris-adminer'),
		implode(',', pexlechris_adminer_access_capabilities()),
		PEXLECHRIS_ADMINER_SLUG,
		'pexlechris_adminer_tools_page_content',
		3
	);
}


//IN TOOLS
if( !function_exists('pexlechris_adminer_tools_page_content') ){
	function pexlechris_adminer_tools_page_content(){
        global $wpdb;
		?>
		<br>
		<a href="<?php echo esc_url( get_pexlechris_adminer_url() ); ?>" class="button-primary pexlechris-adminer-tools-page-button" target="_blank">
			<?php esc_html_e('Open Adminer in a new tab', 'pexlechris-adminer'); ?>
        </a>
		<?php
        foreach(pexlechris_adminer_admin_bar_dropdown_items() as $table):
            $name  = $table['name'];
            $label = $table['label'];
            $args  = $table['args'] ?? [];

            $table_name = $wpdb->$name ?? $wpdb->prefix . $name;
            ?>
            <br>
            <br>
            <a href="<?php echo esc_url( get_pexlechris_adminer_url($table_name, $args) ); ?>" class="button pexlechris-adminer-tools-page-button" target="_blank">
                <?php echo $label; ?>
            </a>
        <?php endforeach;
	}
}


add_action('pexlechris_adminer_before_adminer_loads', 'pexlechris_adminer_before_adminer_loads');
function pexlechris_adminer_before_adminer_loads()
{
	if( !defined('PEXLECHRIS_ADMINER_HAVE_ACCESS_ONLY_IN_WP_DB') || true === PEXLECHRIS_ADMINER_HAVE_ACCESS_ONLY_IN_WP_DB ){
		if( !isset($_GET['db']) && isset($_GET['username']) && '' == $_GET['username'] ){
			// show WordPress database
			wp_redirect( $_SERVER['REQUEST_URI'] . '&db=' . DB_NAME);
			exit;
		}elseif( isset($_GET['db']) && DB_NAME != $_GET['db'] ){
			// if try to show another of WordPress database, wp_die
			wp_die(
				esc_html__("You haven't access to any database other than the site's database. In order to enable access, you need to add the following line code in the wp-config.php file", 'pexlechris-adminer') .
				"<pre>define('PEXLECHRIS_ADMINER_HAVE_ACCESS_ONLY_IN_WP_DB', false);</pre>"
			);
		}
	}
}


add_action('pexlechris_adminer_before_adminer_loads', 'pexlechris_adminer_disable_display_errors_before_adminer_loads', 100);
function pexlechris_adminer_disable_display_errors_before_adminer_loads()
{
	/**
	 * @since 2.1.0 firstly set
	 * @since 2.1.1 move after action pexlechris_adminer_before_adminer_loads
     * @since 2.2.0 moved to a wp action with priority 100
     */
	ini_set('display_errors', 0);

	/**
	 * @since 3.0.0
	 */
	add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
}

/**
 * @since 4.0.0
 *
 * @param string $table The DB Table.
 * @param array $args Function args
 *
 * @return string $table_url
 */
function get_pexlechris_adminer_url( $table = null, $args = [] )
{
    $get_parameters = [];

    if( $table ){
		$get_parameters = [
			'db'        => DB_NAME,
            'select'    => $table,
		];
    }

	$get_parameters = array_merge($get_parameters, $args);

    $str = '';
    foreach($get_parameters as $get_key => $get_value){
        $str .= '&' . $get_key . '=' . $get_value;
    }

    if( get_option('permalink_structure') ) {
        $table_url = strtok(home_url(), '?') . '/' . PEXLECHRIS_ADMINER_SLUG . '?username=' . $str;
    }else{
        $table_url = strtok(admin_url(), '?') . '?username=' . $str;
    }

	/**
     * Filter to alter generated adminer URL
     *
	 * @since 2.3.0
     *
     * @param string $table_url
     * @param string $table The DB Table.
     * @param array $args Function args
	 */
	$table_url = apply_filters('pexlechris_adminer_url', $table_url, $get_parameters, $table, $args);

	return $table_url;

}

function pexlechris_adminer_admin_bar_dropdown_items()
{
    global $pagenow;

	$post_id = null;
	if( is_admin() ){
		$post_id = $_GET['post'] ?? null;
	}elseif( ($object = get_queried_object()) instanceof WP_Post ){
		$post_id = $object->ID;
	}

	$user_id = null;
	if( $pagenow == 'profile.php' ){
		$user_id = get_current_user_id();
	}elseif( $pagenow == 'user-edit.php' ){
	    $user_id = $_GET['user_id'] ?? null;
    }elseif( !is_admin() && ($object = get_queried_object()) instanceof WP_User ){
		$user_id = $object->ID;
	}

    // Woocommerce HPOS orders integrate
    $is_hpos_enabled = false;
	if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
	    $is_hpos_enabled = true;
	}

	// integrate with variable-inspector plugin by Bowo
	require_once ABSPATH . 'wp-admin/includes/plugin.php'; // includes function is_plugin_active
	$is_variable_inspector_enabled = is_plugin_active( 'variable-inspector/variable-inspector.php' );

    if($is_hpos_enabled){

		$hpos_order_id = ($_GET['page'] ?? null) === 'wc-orders'
            ? $_GET['id']
            : null;


		$dropdown_items[] = [
			'name'  => 'wc_orders',
			'label' => __('Orders Table', 'pexlechris-adminer'),
		];

		if($hpos_order_id){
			$dropdown_items[] = [
				'name'  => 'wc_orders',
				'label' => __('-> Current Order', 'pexlechris-adminer'),
				'args'  => [
					'where%5B0%5D%5Bcol%5D' => 'id',
					'where%5B0%5D%5Bop%5D'  => '%3D', // op = '='
					'where%5B0%5D%5Bval%5D' => $hpos_order_id,
				]
			];
		}

		$dropdown_items[] = [
			'name'  => 'wc_orders_meta',
			'label' => __('Orders meta Table', 'pexlechris-adminer'),
		];

		if($hpos_order_id){
			$dropdown_items[] = [
				'name'  => 'wc_orders_meta',
				'label' => __('-> Current Orders meta', 'pexlechris-adminer'),
				'args'  => [
					'where%5B0%5D%5Bcol%5D' => 'order_id',
					'where%5B0%5D%5Bop%5D'  => '%3D', // op = '='
					'where%5B0%5D%5Bval%5D' => $hpos_order_id,
					'limit'                 => '1000'
				]
			];
		}

    }


	$dropdown_items[] = [
		'name'  => 'posts',
		'label' => __('Posts Table', 'pexlechris-adminer'),
	];

	if($post_id){
		$dropdown_items[] = [
			'name'  => 'posts',
			'label' => __('-> Current Post', 'pexlechris-adminer'),
			'args'  => [
				'where%5B0%5D%5Bcol%5D' => 'ID',
				'where%5B0%5D%5Bop%5D'  => '%3D', // op = '='
				'where%5B0%5D%5Bval%5D' => $post_id,
			]
		];
	}

	$dropdown_items[] = [
		'name'  => 'postmeta',
		'label' => __('Postmeta Table', 'pexlechris-adminer'),
	];

	if($post_id){
		$dropdown_items[] = [
			'name'  => 'postmeta',
			'label' => __('-> Current Postmeta', 'pexlechris-adminer'),
			'args'  => [
				'where%5B0%5D%5Bcol%5D' => 'post_id',
				'where%5B0%5D%5Bop%5D'  => '%3D', // op = '='
				'where%5B0%5D%5Bval%5D' => $post_id,
				'limit'                 => '1000'
			]
		];
	}

	$dropdown_items[] = [
		'name'  => 'options',
		'label' => __('Options Table', 'pexlechris-adminer'),
	];

	$dropdown_items[] = [
		'name'  => 'users',
		'label' => __('Users Table', 'pexlechris-adminer'),
	];

	if($user_id){
		$dropdown_items[] = [
			'name'  => 'users',
			'label' => __('-> Current User', 'pexlechris-adminer'),
			'args'  => [
				'where%5B0%5D%5Bcol%5D' => 'ID',
				'where%5B0%5D%5Bop%5D'  => '%3D', // op = '='
				'where%5B0%5D%5Bval%5D' => $user_id,
				'limit'                 => '1000'
			]
		];
	}

	$dropdown_items[] = [
		'name'  => 'usermeta',
		'label' => __('Usermeta Table', 'pexlechris-adminer'),
	];

	if($user_id){
		$dropdown_items[] = [
			'name'  => 'usermeta',
			'label' => __('-> Current Usermeta', 'pexlechris-adminer'),
			'args'  => [
				'where%5B0%5D%5Bcol%5D' => 'user_id',
				'where%5B0%5D%5Bop%5D'  => '%3D', // op = '='
				'where%5B0%5D%5Bval%5D' => $user_id,
				'limit'                 => '1000'
			]
		];
	}

	if ($is_variable_inspector_enabled) {
		$dropdown_items[] = [
			'name'  => 'variable_inspector',
			'label' => __('Variable Inspector Table', 'pexlechris-adminer'),
		];
    }


	/**
	 * The dropdown items.
     *
     * @param array $dropdown_items{
     *      @type string $name. The table name.
     *      @type string $label. The label of the dropdown item.
     *      @type array $args. The array of extra url parameters. Parameters will not be encoded.
     *                         Developers could avoid defining args in the array.
     * }
	 */
	$dropdown_items = apply_filters('pexlechris_adminer_adminbar_dropdown_items', $dropdown_items);

    return $dropdown_items;

}