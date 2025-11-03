<?php
/**
 * Migration script to switch from TCPDF to DomPDF
 * This script helps migrate existing certificate generation to use DomPDF
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function migrate_to_dompdf_certificates() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    echo '<div class="wrap">';
    echo '<h1>Migrate to DomPDF Certificates</h1>';
    
    // Check if DomPDF is available
    if (!class_exists('Dompdf\Dompdf')) {
        echo '<div class="notice notice-error">';
        echo '<p><strong>Error:</strong> DomPDF library not found. Please install it first:</p>';
        echo '<pre>cd ' . get_stylesheet_directory() . ' && composer install</pre>';
        echo '</div>';
        return;
    }
    
    // Step 1: Backup existing files
    echo '<h2>Step 1: Backup Existing Files</h2>';
    $backup_files = [
        'pdf-cert-generator.php' => get_stylesheet_directory() . '/includes/pdf-cert-generator.php',
        'pdf-cert-generator-dompdf.php' => get_stylesheet_directory() . '/includes/pdf-cert-generator-dompdf.php'
    ];
    
    $backup_dir = get_stylesheet_directory() . '/backups/' . date('Y-m-d_H-i-s');
    if (wp_mkdir_p($backup_dir)) {
        echo '<p style="color: green;">✓ Backup directory created: ' . $backup_dir . '</p>';
        
        foreach ($backup_files as $name => $file) {
            if (file_exists($file)) {
                if (copy($file, $backup_dir . '/' . $name)) {
                    echo '<p style="color: green;">✓ Backed up ' . $name . '</p>';
                } else {
                    echo '<p style="color: red;">✗ Failed to backup ' . $name . '</p>';
                }
            }
        }
    } else {
        echo '<p style="color: red;">✗ Failed to create backup directory</p>';
    }
    
    // Step 2: Update function calls
    echo '<h2>Step 2: Update Function Calls</h2>';
    echo '<p>Search for the following patterns in your code and replace them:</p>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Find</th><th>Replace With</th></tr></thead>';
    echo '<tbody>';
    echo '<tr><td><code>generate_exam_certificate_pdf(</code></td><td><code>generate_exam_certificate_pdf_dompdf(</code></td></tr>';
    echo '<tr><td><code>require_once get_stylesheet_directory() . \'/TCPDF/tcpdf.php\';</code></td><td><code>require_once get_stylesheet_directory() . \'/includes/vendor/autoload.php\';</code></td></tr>';
    echo '<tr><td><code>new TCPDF(</code></td><td><code>new Dompdf(</code></td></tr>';
    echo '</tbody></table>';
    
    // Step 3: Test the new function
    echo '<h2>Step 3: Test New Function</h2>';
    if (isset($_GET['test_function'])) {
        echo '<p>Testing DomPDF function...</p>';
        
        // Check if the function exists
        if (function_exists('generate_exam_certificate_pdf_dompdf')) {
            echo '<p style="color: green;">✓ DomPDF function is available</p>';
        } else {
            echo '<p style="color: red;">✗ DomPDF function not found. Please include the file.</p>';
        }
        
        // Test basic DomPDF functionality
        try {
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'Times-Roman');
            $options->set('isRemoteEnabled', true);
            $options->set('isPhpEnabled', false);
            
            $pdf = new \Dompdf\Dompdf($options);
            $pdf->setPaper('A4', 'portrait');
            $pdf->loadHtml('<html><body><h1>Test PDF</h1><p>This is a test PDF generated with DomPDF.</p></body></html>');
            $pdf->render();
            
            echo '<p style="color: green;">✓ DomPDF basic functionality works</p>';
        } catch (Exception $e) {
            echo '<p style="color: red;">✗ DomPDF test failed: ' . esc_html($e->getMessage()) . '</p>';
        }
    } else {
        echo '<p><a href="' . admin_url('admin.php?page=migrate-to-dompdf&test_function=1') . '" class="button button-primary">Test DomPDF Function</a></p>';
    }
    
    // Step 4: Performance comparison
    echo '<h2>Step 4: Performance Comparison</h2>';
    echo '<div class="notice notice-info">';
    echo '<p><strong>Expected Benefits of DomPDF:</strong></p>';
    echo '<ul>';
    echo '<li>Better HTML/CSS support</li>';
    echo '<li>More modern codebase</li>';
    echo '<li>Better font handling</li>';
    echo '<li>Easier maintenance</li>';
    echo '<li>Better page layout control</li>';
    echo '</ul>';
    echo '</div>';
    
    // Step 5: Rollback instructions
    echo '<h2>Step 5: Rollback Instructions</h2>';
    echo '<div class="notice notice-warning">';
    echo '<p><strong>If you need to rollback:</strong></p>';
    echo '<ol>';
    echo '<li>Restore the original files from backup</li>';
    echo '<li>Revert function calls back to TCPDF version</li>';
    echo '<li>Test certificate generation</li>';
    echo '</ol>';
    echo '</div>';
    
    // Step 6: Implementation checklist
    echo '<h2>Step 6: Implementation Checklist</h2>';
    echo '<div class="notice notice-success">';
    echo '<p><strong>Before going live:</strong></p>';
    echo '<ul>';
    echo '<li>✓ Test with sample data</li>';
    echo '<li>✓ Verify all certificates generate correctly</li>';
    echo '<li>✓ Check file permissions</li>';
    echo '<li>✓ Test email notifications</li>';
    echo '<li>✓ Verify database updates</li>';
    echo '<li>✓ Test on staging environment</li>';
    echo '</ul>';
    echo '</div>';
    
    echo '</div>';
}

// Add admin menu for migration
function add_dompdf_migration_menu() {
    add_submenu_page(
        'tools.php',
        'Migrate to DomPDF',
        'Migrate to DomPDF',
        'manage_options',
        'migrate-to-dompdf',
        'migrate_to_dompdf_certificates'
    );
}
add_action('admin_menu', 'add_dompdf_migration_menu');
