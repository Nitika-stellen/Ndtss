<?php

if (!defined('ABSPATH')) die('Access denied.');

if (!class_exists('WPO_Page_Optimizer')) :
class WPO_Page_Optimizer {

	/**
	 * Instance of this class
	 *
	 * @var null|WPO_Page_Optimizer
	 */
	private static $instance = null;

	/**
	 * Get the buffer and perform tasks related to page optimization
	 *
	 * @param  string $buffer Page HTML.
	 * @param  int    $flags  OB flags to be passed through.
	 *
	 * @return string
	 */
	private function optimize($buffer, $flags) {
	
		$buffer = apply_filters('wp_optimize_buffer', $buffer);
		$buffer = $this->maybe_remove_unused_css($buffer);
		$buffer = $this->maybe_apply_capojs_rules($buffer);
		$buffer = $this->maybe_cache_page($buffer, $flags);
	
		return $buffer;
	}

	/**
	 * Cache the page if the page cache enabled
	 *
	 * @param  string $buffer Page HTML.
	 * @param  int    $flags  OB flags to be passed through.
	 *
	 * @return string
	 */
	private function maybe_cache_page($buffer, $flags) {

		if (!$this->is_wp_cli() && WP_Optimize()->get_page_cache()->should_cache_page()) {
			return wpo_cache($buffer, $flags);
		}

		return $buffer;
	}

	/**
	 * Remove unused css
	 *
	 * @param String $buffer Page HTML.
	 *
	 * @return String
	 */
	public function maybe_remove_unused_css($buffer) {
		
		if (is_user_logged_in()) return $buffer;
		
		if (WP_Optimize::is_premium() && wp_optimize_minify_config()->get('enable_unused_css')) {
			$unused_css_class = WP_Optimize_Minify_Unused_Css::get_instance();
			return $unused_css_class->remove_unused_css($buffer);
		}
		return $buffer;
	}
	
	/**
	 * Optimize head tags sequence to make web page loading optimally
	 *
	 * @param string $buffer source HTML page
	 * @return string
	 */
	private function maybe_apply_capojs_rules($buffer) {

		if (WP_Optimize::is_premium() && WP_Optimize_CapoJS_Rules::should_apply_capojs_rules($buffer)) {
			$capojs = new WP_Optimize_CapoJS_Rules();
			$buffer = $capojs->optimize($buffer);
		}

		return $buffer;
	}

	/**
	 * Initialise output buffer handler.
	 *
	 * @return void
	 */
	public function initialise() {
		ob_start(array(self::$instance, 'optimize'));
	}

	/**
	 * Checks if the current execution context is WP-CLI.
	 *
	 * @return bool
	 */
	public function is_wp_cli() {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Returns singleton instance of WPO_Page_Optimizer
	 *
	 * @return WPO_Page_Optimizer
	 */
	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

endif;
