<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://#
 * @since      1.0.0
 *
 * @package    Stellen_Gravity
 * @subpackage Stellen_Gravity/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Stellen_Gravity
 * @subpackage Stellen_Gravity/includes
 * @author     Awebstar <info@awebstar.com.sg>
 */
class Stellen_Gravity {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Stellen_Gravity_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'STELLEN_GRAVITY_VERSION' ) ) {
			$this->version = STELLEN_GRAVITY_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'stellen-gravity';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Stellen_Gravity_Loader. Orchestrates the hooks of the plugin.
	 * - Stellen_Gravity_i18n. Defines internationalization functionality.
	 * - Stellen_Gravity_Admin. Defines all hooks for the admin area.
	 * - Stellen_Gravity_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-stellen-gravity-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-stellen-gravity-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-stellen-gravity-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-stellen-gravity-public.php';

		$this->loader = new Stellen_Gravity_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Stellen_Gravity_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Stellen_Gravity_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Stellen_Gravity_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		$this->loader->add_filter('gform_pre_render', $plugin_admin, 'populate_country_dropdown_class');
		$this->loader->add_filter('gform_pre_validation', $plugin_admin, 'populate_country_dropdown_class');

		$this->loader->add_filter('gform_field_validation', $plugin_admin, 'validate_phone_by_country_code', 10, 4);
		$this->loader->add_filter('gform_field_validation', $plugin_admin, 'validate_state_field', 10, 4);
		$this->loader->add_filter('gform_field_validation', $plugin_admin, 'validate_city_field', 10, 4);
		
		//$this->loader->add_filter('gform_pre_render', $plugin_admin,'gf_restore_selected_dropdowns');


		$this->loader->add_action('wp_ajax_get_country_id_by_name',  $plugin_admin,'get_country_id_by_name');
		$this->loader->add_action('wp_ajax_nopriv_get_country_id_by_name', $plugin_admin, 'get_country_id_by_name');

		$this->loader->add_action('wp_ajax_get_state_id_by_name', $plugin_admin, 'get_state_id_by_name');
		$this->loader->add_action('wp_ajax_nopriv_get_state_id_by_name', $plugin_admin, 'get_state_id_by_name');

		$this->loader->add_action('wp_ajax_get_states', $plugin_admin, 'get_states');
		$this->loader->add_action('wp_ajax_nopriv_get_states', $plugin_admin, 'get_states');

		$this->loader->add_action('wp_ajax_get_cities', $plugin_admin, 'get_cities');
		$this->loader->add_action('wp_ajax_nopriv_get_cities', $plugin_admin, 'get_cities');



	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Stellen_Gravity_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Stellen_Gravity_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
