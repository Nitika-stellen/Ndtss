<?php

/**
 * To allow this to be overridden in a must-use plugin
 */
if( !defined('PEXLECHRIS_ADMINER_SLUG') ){
	if( is_multisite() ){
		define('PEXLECHRIS_ADMINER_SLUG', 'multisite-adminer');
	}else{
		define('PEXLECHRIS_ADMINER_SLUG', 'wp-adminer');
	}
}

if( !function_exists('pexlechris_is_current_url_the_wp_adminer_url') ){
	function pexlechris_is_current_url_the_wp_adminer_url(){
		$REQUEST_URI = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
		$REQUEST_URI = rtrim($REQUEST_URI, '/');

		/**
		 * In order to support plain permalinks.
		 *
		 * @since 4.3.0
		 */
		if( !get_option('permalink_structure', null) ){
			$username = $_GET['username'] ?? null;
			$server = $_GET['server'] ?? null;
			if( pexlechris_adminer_ends_with($REQUEST_URI, 'wp-admin') && in_array('', [$username, $server], true) ){
				return true;
			}else{
				return false;
			}
		}


		if( pexlechris_adminer_ends_with($REQUEST_URI, PEXLECHRIS_ADMINER_SLUG) ){
			return true;
		}else{
			return false;
		}
	}
}

if( !function_exists('pexlechris_adminer_ends_with') ) {
	function pexlechris_adminer_ends_with( $haystack, $needle ) {
		$length = strlen( $needle );
		if( !$length ) {
			return true;
		}
		return substr( $haystack, -$length ) === $needle;
	}
}

if( !function_exists('pexlechris_adminer_access_capabilities') ) {
	function pexlechris_adminer_access_capabilities()
	{
		if (is_multisite()) {
			//only Super Admins of website has the capability <code>manage_network_options</code>
			$capabilities = ['manage_wp_adminer', 'manage_network_options'];
		} else {
			//only administrator of website has the capability <code>manage_options</code>
			$capabilities = ['manage_wp_adminer', 'manage_options'];
		}

		$capabilities = apply_filters('pexlechris_adminer_access_capabilities', $capabilities);
		$capabilities = (array)( $capabilities ?: [] );
		return $capabilities;
	}
}

if( !function_exists('have_current_user_access_to_pexlechris_adminer') ){
	function have_current_user_access_to_pexlechris_adminer()
	{
		foreach (pexlechris_adminer_access_capabilities() as $capability) {
			require_once ABSPATH . WPINC . '/pluggable.php';
			if( current_user_can($capability) ){
				return true;
			}
		}

		return false;
	}
}
