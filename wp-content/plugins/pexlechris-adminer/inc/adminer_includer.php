<?php
	
if ( ! defined( 'ABSPATH' ) ) {
	die;
}
/**
 * adminer_object can be overridden, in WP action pexlechris_adminer_before_adminer_loads in a must-use plugin.
 * If a developer wants to make his/her own changes (adding plugins, extensions or customizations),
 * it is strongly recommended to include_once the class Pexlechris_Adminer and extend it and
 * force adminer_object function returns his/her new custom class.
 *
 * It is strongly recommended, because Pexlechris_Adminer class contains WordPress/Adminer integration (auto login with WordPress credentials)
 *
 * If a developer wants to add just JS and/or CSS in head, he/she can just use the action pexlechris_adminer_head in a must-use plugin.
 * See plugin's FAQs, for more.
 *
 * @since 2.1.0
 *
 * @link https://www.adminer.org/en/plugins/#use Documentation URL.
 * @link https://www.adminer.org/en/plugins/ Adminer's plugins Documentation URL.
 * @link https://www.adminer.org/en/extension/ Adminer's extensions Documentation URL.
 */
if ( !function_exists('adminer_object') ) {

	function adminer_object() {

        include_once PEXLECHRIS_ADMINER_DIR . '/inc/class-pexlechris-adminer.php';

		return new Pexlechris_Adminer();

	}

}

/**
 * This function introduced for backward compatibility. Please use \Adminer\get_nonce() instead.
 *
 * @since 4.0.0
 * @deprecated 4.0.0
 * @return mixed|string
 */
function get_nonce(){
	_deprecated_function( 'get_nonce()', 'WP Adminer 4.0.0', '\Adminer\get_nonce()' );
	return \Adminer\get_nonce();
}

if( function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc() && !function_exists('each') ){
	/**
	 * Polyfill for the deprecated each() function (PHP <= 7.2).
	 * Fully emulates pointer-based iteration behavior.
	 *
	 * This polyfill is loaded only in environments where get_magic_quotes_gpc()
	 * still exists and returns true, to preserve compatibility with old codebases
	 * that rely on each() when magic quotes are enabled.
	 *
	 * @param array $array
	 * @return array|false
	 */
	function each(array &$array)
	{
		static $pointers = [];

		// Unique ID for this array instance
		$id = spl_object_id((object) $array);

		// Initialize pointer if not set
		if (!isset($pointers[$id])) {
			$pointers[$id] = 0;
		}

		// Get array keys
		$keys = array_keys($array);

		// Out of bounds → end of array
		if (!isset($keys[$pointers[$id]])) {
			return false;
		}

		// Read current key & value
		$key   = $keys[$pointers[$id]];
		$value = $array[$key];

		// Move pointer forward
		$pointers[$id]++;

		// Return same structure as the original each()
		// 100% compatible structure
		return [
			1       => $value,
			'value' => $value,
			0       => $key,
			'key'   => $key
		];
	}
}



ob_end_clean();
if( defined('PEXLECHRIS_ADMINER_INCLUDE_FILE_ABSPATH') && file_exists( PEXLECHRIS_ADMINER_INCLUDE_FILE_ABSPATH ) ){
	include PEXLECHRIS_ADMINER_INCLUDE_FILE_ABSPATH;
}else{
	include __DIR__ . '/adminer.php';
}