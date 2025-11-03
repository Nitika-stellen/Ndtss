<?php
/**
 * Clear Certificate Cache Script
 * Run this file directly in browser to clear all certificate-related cache
 * URL: http://localhost/clear-cert-cache.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is admin
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('Unauthorized access');
}

// Clear all certificate-related transients
global $wpdb;

// Clear transients
$deleted_transients = $wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '%_transient_%final_certificates%' 
        OR option_name LIKE '%user_profile_cache%'
        OR option_name LIKE '%cert_lifecycle%'"
);

// Clear user meta cache
$deleted_usermeta = $wpdb->query(
    "DELETE FROM {$wpdb->usermeta} 
     WHERE meta_key LIKE '%_transient_%'"
);

// Clear object cache if available
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Output results
echo '<h2>Certificate Cache Cleared Successfully!</h2>';
echo '<p><strong>Transients deleted:</strong> ' . $deleted_transients . '</p>';
echo '<p><strong>User meta cache cleared:</strong> ' . $deleted_usermeta . '</p>';
echo '<p><strong>Object cache flushed:</strong> Yes</p>';
echo '<hr>';
echo '<p><a href="' . home_url('/user-profile') . '">Go to User Profile</a></p>';
echo '<p><a href="javascript:history.back()">Go Back</a></p>';
