<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://#
 * @since      1.0.0
 *
 * @package    Stellen_Gravity
 * @subpackage Stellen_Gravity/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Stellen_Gravity
 * @subpackage Stellen_Gravity/public
 * @author     vikas <vikas@stelleninfotech.in>
 */
class Stellen_Gravity_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Stellen_Gravity_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Stellen_Gravity_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/stellen-gravity-public.css', array(), $this->version, 'all' );
		
		wp_localize_script('gf-location-script', 'ajaxurl', [
		'url' => admin_url('admin-ajax.php')
	]);
		
		

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Stellen_Gravity_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Stellen_Gravity_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/stellen-gravity-public.js', array( 'jquery' ), rand(), true );

		wp_localize_script( $this->plugin_name, 'my_ajax_object', [
		    'ajax_url' => admin_url( 'admin-ajax.php' ),
		   // 'home_url' => home_url(),
		]);

	}

}
