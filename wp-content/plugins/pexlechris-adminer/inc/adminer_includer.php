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
 * If a developer wants to add just JS and/or CSS in head, he/she can just use the action pexlechris_adminer_head.
 * See plugin's FAQs, for more.
 *
 * @since 2.1.0
 *
 * @link https://www.adminer.org/en/plugins/#use Documentation URL.
 * @link https://www.adminer.org/en/plugins/ Adminer' plugins Documentation URL.
 * @link https://www.adminer.org/en/extension/ Adminer' extensions Documentation URL.
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


ob_end_clean();
if( defined('PEXLECHRIS_ADMINER_INCLUDE_FILE_ABSPATH') && file_exists( PEXLECHRIS_ADMINER_INCLUDE_FILE_ABSPATH ) ){
	include PEXLECHRIS_ADMINER_INCLUDE_FILE_ABSPATH;
}else{
	include __DIR__ . '/adminer.php';
}