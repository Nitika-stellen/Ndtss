<?php
/**
 * Test script for DomPDF certificate generation
 * This file helps test the new DomPDF certificate functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function test_dompdf_certificate_generation() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    echo '<div class="wrap">';
    echo '<h1>Test DomPDF Certificate Generation</h1>';
    
    // Test 1: Check if DomPDF is available
    echo '<h2>1. DomPDF Library Status</h2>';
    if (class_exists('Dompdf\Dompdf')) {
        echo '<p style="color: green;">✓ DomPDF library is available</p>';
    } else {
        echo '<p style="color: red;">✗ DomPDF library not found. Please ensure it\'s installed via Composer.</p>';
        echo '<p>Run: <code>composer install</code> in the theme directory</p>';
    }
    
    // Test 2: Check if helper functions exist
    echo '<h2>2. Helper Functions</h2>';
    $functions = [
        'generate_level_2_table',
        'generate_level_3_table',
        'send_certification_notification',
        'get_exam_dates_by_method',
        'crop_signature_image'
    ];
    
    $all_functions_exist = true;
    foreach ($functions as $function) {
        if (function_exists($function)) {
            echo '<p style="color: green;">✓ ' . $function . ' exists</p>';
        } else {
            echo '<p style="color: red;">✗ ' . $function . ' not found</p>';
            $all_functions_exist = false;
        }
    }
    
    // Test 3: Check if Gravity Forms is available
    echo '<h2>3. Gravity Forms Integration</h2>';
    if (class_exists('GFAPI')) {
        echo '<p style="color: green;">✓ Gravity Forms API is available</p>';
    } else {
        echo '<p style="color: red;">✗ Gravity Forms not found</p>';
    }
    
    // Test 4: Check file permissions
    echo '<h2>4. File Permissions</h2>';
    $upload_dir = wp_upload_dir();
    $cert_dir = $upload_dir['basedir'] . '/certificates';
    
    if (wp_mkdir_p($cert_dir)) {
        echo '<p style="color: green;">✓ Certificates directory is writable</p>';
    } else {
        echo '<p style="color: red;">✗ Cannot create certificates directory</p>';
    }
    
    // Test 5: Check logo files
    echo '<h2>5. Logo Files</h2>';
    $logo_files = [
        'ndtss_logo.png' => get_stylesheet_directory() . '/assets/logos/ndtss_logo.png',
        'sgndt_logo.png' => get_stylesheet_directory() . '/assets/logos/sgndt_logo.png'
    ];
    
    foreach ($logo_files as $name => $path) {
        if (file_exists($path)) {
            echo '<p style="color: green;">✓ ' . $name . ' exists</p>';
        } else {
            echo '<p style="color: orange;">⚠ ' . $name . ' not found at ' . $path . '</p>';
        }
    }
    
    // Test 6: Sample certificate generation (if test data available)
    echo '<h2>6. Sample Certificate Generation</h2>';
    if (isset($_GET['test_generate']) && $all_functions_exist) {
        echo '<p>Testing certificate generation...</p>';
        
        // You would need to provide actual test data here
        // For now, just show the interface
        echo '<p><em>To test certificate generation, you need to provide valid exam_entry_id, marks_entry_id, and method parameters.</em></p>';
    } else {
        echo '<p><a href="' . admin_url('admin.php?page=test-dompdf-certificate&test_generate=1') . '" class="button button-primary">Test Certificate Generation</a></p>';
    }
    
    // Test 7: Compare with TCPDF version
    echo '<h2>7. Version Comparison</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Feature</th><th>TCPDF Version</th><th>DomPDF Version</th></tr></thead>';
    echo '<tbody>';
    echo '<tr><td>Library</td><td>TCPDF</td><td>DomPDF</td></tr>';
    echo '<tr><td>HTML Support</td><td>Limited</td><td>Full HTML5/CSS3</td></tr>';
    echo '<tr><td>Page Layout</td><td>Programmatic</td><td>CSS-based</td></tr>';
    echo '<tr><td>Font Support</td><td>Built-in fonts</td><td>Web fonts + system fonts</td></tr>';
    echo '<tr><td>Performance</td><td>Good</td><td>Good</td></tr>';
    echo '<tr><td>Maintenance</td><td>Active</td><td>Very Active</td></tr>';
    echo '</tbody></table>';
    
    // Test 8: Usage instructions
    echo '<h2>8. Usage Instructions</h2>';
    echo '<div class="notice notice-info">';
    echo '<p><strong>To use the new DomPDF version:</strong></p>';
    echo '<ol>';
    echo '<li>Replace calls to <code>generate_exam_certificate_pdf()</code> with <code>generate_exam_certificate_pdf_dompdf()</code></li>';
    echo '<li>Ensure DomPDF is installed: <code>composer install</code></li>';
    echo '<li>Test with sample data to verify output</li>';
    echo '<li>Update any hardcoded references to the old function</li>';
    echo '</ol>';
    echo '</div>';
    
    echo '</div>';
}

// Add admin menu for testing
function add_dompdf_test_menu() {
    add_submenu_page(
        'tools.php',
        'Test DomPDF Certificate',
        'Test DomPDF Certificate',
        'manage_options',
        'test-dompdf-certificate',
        'test_dompdf_certificate_generation'
    );
}
add_action('admin_menu', 'add_dompdf_test_menu');
