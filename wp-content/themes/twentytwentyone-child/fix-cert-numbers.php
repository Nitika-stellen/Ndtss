<?php
/**
 * Fix Certificate Number Format
 * This script updates certificate numbers from -1 format to -01 format
 * URL: http://localhost/ndtss/wp-content/themes/twentytwentyone-child/fix-cert-numbers.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is admin
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('Unauthorized access');
}

global $wpdb;

echo '<h2>Certificate Number Format Fix</h2>';

// Find all certificates with single-digit suffix
$certificates = $wpdb->get_results(
    "SELECT final_certification_id, certificate_number 
     FROM {$wpdb->prefix}sgndt_final_certifications 
     WHERE certificate_number REGEXP '-[0-9]$'",
    ARRAY_A
);

echo '<p><strong>Certificates found with single-digit suffix:</strong> ' . count($certificates) . '</p>';

if (empty($certificates)) {
    echo '<p>No certificates need updating.</p>';
} else {
    echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
    echo '<tr><th>ID</th><th>Old Number</th><th>New Number</th><th>Status</th></tr>';
    
    foreach ($certificates as $cert) {
        $old_number = $cert['certificate_number'];
        
        // Convert -1 to -01, -2 to -02, etc.
        $new_number = preg_replace('/-([0-9])$/', '-0$1', $old_number);
        
        // Update the certificate number
        $updated = $wpdb->update(
            $wpdb->prefix . 'sgndt_final_certifications',
            array('certificate_number' => $new_number),
            array('final_certification_id' => $cert['final_certification_id']),
            array('%s'),
            array('%d')
        );
        
        $status = $updated !== false ? '✅ Updated' : '❌ Failed';
        
        echo '<tr>';
        echo '<td>' . $cert['final_certification_id'] . '</td>';
        echo '<td>' . esc_html($old_number) . '</td>';
        echo '<td>' . esc_html($new_number) . '</td>';
        echo '<td>' . $status . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
}

// Clear cache after update
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '%_transient_%final_certificates%' 
        OR option_name LIKE '%user_profile_cache%'"
);

echo '<hr>';
echo '<p><strong>Cache cleared!</strong></p>';
echo '<p><a href="' . home_url('/user-profile') . '">Go to User Profile</a></p>';
