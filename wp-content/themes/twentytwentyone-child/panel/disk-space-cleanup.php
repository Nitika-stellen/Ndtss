<?php
/**
 * Disk Space Cleanup Tool
 * 
 * Analyzes and cleans up unnecessary files taking up space
 * 
 * @package SGNDT
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'disk_space_cleanup_add_admin_menu');
add_action('wp_ajax_disk_space_analyze', 'handle_disk_space_analysis');
add_action('wp_ajax_disk_space_cleanup', 'handle_disk_space_cleanup');

/**
 * Add admin menu
 */
function disk_space_cleanup_add_admin_menu() {
    add_submenu_page(
        'tools.php',
        'Disk Space Cleanup',
        'Disk Space Cleanup',
        'manage_options',
        'disk-space-cleanup',
        'disk_space_cleanup_admin_page'
    );
}

/**
 * Admin page
 */
function disk_space_cleanup_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    ?>
    <div class="wrap">
        <h1>Disk Space Cleanup</h1>
        <p class="description">Analyze and clean up unnecessary files to free up disk space.</p>
        
        <div class="disk-space-tool">
            <button type="button" class="button button-primary" id="analyze-space">Analyze Disk Usage</button>
            <div id="analysis-results" style="display:none;"></div>
            <div id="cleanup-results" style="display:none;"></div>
        </div>
    </div>
    
    <style>
        .disk-space-tool { margin: 20px 0; }
        .space-summary { margin: 20px 0; padding: 15px; background: #f0f0f1; border-radius: 4px; }
        .space-item { padding: 10px; margin: 5px 0; background: #fff; border-left: 4px solid #2271b1; }
        .space-item.warning { border-left-color: #d63638; }
        .space-item.success { border-left-color: #00a32a; }
        .file-list { max-height: 300px; overflow-y: auto; }
        .cleanup-button { margin-top: 10px; }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('#analyze-space').on('click', function() {
            var button = $(this);
            button.prop('disabled', true).text('Analyzing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'disk_space_analyze',
                    nonce: '<?php echo wp_create_nonce('disk_space_nonce'); ?>'
                },
                success: function(response) {
                    button.prop('disabled', false).text('Analyze Disk Usage');
                    if (response.success) {
                        displayAnalysisResults(response.data);
                    } else {
                        alert('Error: ' + (response.data || 'Analysis failed'));
                    }
                },
                error: function() {
                    button.prop('disabled', false).text('Analyze Disk Usage');
                    alert('Error analyzing disk space');
                }
            });
        });
        
        function displayAnalysisResults(data) {
            var html = '<div class="space-summary">';
            html += '<h2>Disk Usage Analysis</h2>';
            html += '<p><strong>Total Size Analyzed:</strong> ' + formatBytes(data.total_size) + '</p>';
            
            if (data.items && data.items.length > 0) {
                html += '<h3>Items Taking Up Space:</h3>';
                data.items.forEach(function(item) {
                    var className = item.can_cleanup ? 'warning' : '';
                    html += '<div class="space-item ' + className + '">';
                    html += '<strong>' + item.name + '</strong>: ' + formatBytes(item.size);
                    html += '<br><small>' + item.path + '</small>';
                    if (item.description) {
                        html += '<br><small>' + item.description + '</small>';
                    }
                    if (item.can_cleanup && item.cleanup_action) {
                        html += '<br><button class="button cleanup-button" data-action="' + item.cleanup_action + '" data-path="' + item.path + '">Clean Up</button>';
                    }
                    html += '</div>';
                });
            }
            
            html += '</div>';
            $('#analysis-results').html(html).show();
            
            // Handle cleanup buttons
            $('.cleanup-button').on('click', function() {
                var button = $(this);
                var action = button.data('action');
                var path = button.data('path');
                
                if (!confirm('Are you sure you want to clean up this item?')) {
                    return;
                }
                
                button.prop('disabled', true).text('Cleaning...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'disk_space_cleanup',
                        cleanup_action: action,
                        path: path,
                        nonce: '<?php echo wp_create_nonce('disk_space_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Cleanup successful! Freed: ' + formatBytes(response.data.freed_space));
                            $('#analyze-space').trigger('click'); // Refresh analysis
                        } else {
                            alert('Error: ' + (response.data || 'Cleanup failed'));
                            button.prop('disabled', false).text('Clean Up');
                        }
                    },
                    error: function() {
                        alert('Error during cleanup');
                        button.prop('disabled', false).text('Clean Up');
                    }
                });
            });
        }
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
    });
    </script>
    <?php
}

/**
 * Handle disk space analysis
 */
function handle_disk_space_analysis() {
    check_ajax_referer('disk_space_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $items = array();
    $total_size = 0;
    
    // 1. Check certificate-imports temporary directory
    $upload_dir = wp_upload_dir();
    $import_dir = $upload_dir['basedir'] . '/certificate-imports';
    if (file_exists($import_dir)) {
        $size = get_directory_size($import_dir);
        $items[] = array(
            'name' => 'Certificate Import Temporary Files',
            'path' => $import_dir,
            'size' => $size,
            'description' => 'Temporary CSV and certificate files from imports. Safe to delete if no import is in progress.',
            'can_cleanup' => true,
            'cleanup_action' => 'cleanup_imports'
        );
        $total_size += $size;
    }
    
    // 2. Check log files
    $log_dirs = array(
        get_stylesheet_directory() . '/renew/logs' => 'Renewal Log Files',
        get_stylesheet_directory() . '/cpd-management/logs' => 'CPD Management Log Files',
        get_stylesheet_directory() . '/membership/logs' => 'Membership Log Files'
    );
    
    foreach ($log_dirs as $log_dir => $log_name) {
        if (file_exists($log_dir)) {
            $log_files = glob($log_dir . '/*.log');
            $size = 0;
            foreach ($log_files as $file) {
                $size += filesize($file);
            }
            if ($size > 0) {
                // Keep only last 3 months
                $old_logs = array_filter($log_files, function($file) {
                    $file_time = filemtime($file);
                    $three_months_ago = strtotime('-3 months');
                    return $file_time < $three_months_ago;
                });
                
                $old_size = 0;
                foreach ($old_logs as $file) {
                    $old_size += filesize($file);
                }
                
                if ($old_size > 0) {
                    $items[] = array(
                        'name' => $log_name . ' (Old Logs)',
                        'path' => $log_dir,
                        'size' => $old_size,
                        'description' => count($old_logs) . ' log files older than 3 months. Safe to delete.',
                        'can_cleanup' => true,
                        'cleanup_action' => 'cleanup_old_logs'
                    );
                    $total_size += $old_size;
                }
            }
        }
    }
    
    // 3. Check phpqrcode .dat files
    $qrcode_dir = get_stylesheet_directory() . '/phpqrcode';
    if (file_exists($qrcode_dir)) {
        $dat_files = glob($qrcode_dir . '/**/*.dat', GLOB_BRACE);
        $size = 0;
        foreach ($dat_files as $file) {
            $size += filesize($file);
        }
        if ($size > 0) {
            $items[] = array(
                'name' => 'QR Code Cache Files (.dat)',
                'path' => $qrcode_dir,
                'size' => $size,
                'description' => count($dat_files) . ' QR code cache files. Safe to delete - will be regenerated.',
                'can_cleanup' => true,
                'cleanup_action' => 'cleanup_qrcode_cache'
            );
            $total_size += $size;
        }
    }
    
    // 4. Check for large upload directories
    $upload_base = $upload_dir['basedir'];
    $cert_dir = $upload_base . '/certificates';
    if (file_exists($cert_dir)) {
        $size = get_directory_size($cert_dir);
        $items[] = array(
            'name' => 'Certificate PDF Files',
            'path' => $cert_dir,
            'size' => $size,
            'description' => 'Certificate PDF files. Do NOT delete - these are needed for downloads.',
            'can_cleanup' => false
        );
    }
    
    // 5. Check WordPress transients
    global $wpdb;
    $transient_size = $wpdb->get_var(
        "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_%' 
         OR option_name LIKE '_site_transient_%'"
    );
    if ($transient_size > 1024 * 1024) { // Only show if > 1MB
        $items[] = array(
            'name' => 'Expired WordPress Transients',
            'path' => 'Database',
            'size' => $transient_size,
            'description' => 'Cached data in database. Safe to clean.',
            'can_cleanup' => true,
            'cleanup_action' => 'cleanup_transients'
        );
        $total_size += $transient_size;
    }
    
    wp_send_json_success(array(
        'total_size' => $total_size,
        'items' => $items
    ));
}

/**
 * Handle cleanup action
 */
function handle_disk_space_cleanup() {
    check_ajax_referer('disk_space_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $action = sanitize_text_field($_POST['cleanup_action']);
    $path = sanitize_text_field($_POST['path']);
    $freed_space = 0;
    
    switch ($action) {
        case 'cleanup_imports':
            $freed_space = cleanup_import_directory($path);
            break;
        case 'cleanup_old_logs':
            $freed_space = cleanup_old_log_files($path);
            break;
        case 'cleanup_qrcode_cache':
            $freed_space = cleanup_qrcode_cache($path);
            break;
        case 'cleanup_transients':
            $freed_space = cleanup_transients();
            break;
        default:
            wp_send_json_error('Invalid cleanup action');
    }
    
    wp_send_json_success(array(
        'freed_space' => $freed_space,
        'message' => 'Cleanup completed successfully'
    ));
}

/**
 * Get directory size recursively
 */
function get_directory_size($directory) {
    $size = 0;
    if (!file_exists($directory)) {
        return 0;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    
    return $size;
}

/**
 * Cleanup import directory
 */
function cleanup_import_directory($dir) {
    $size = 0;
    if (!file_exists($dir)) {
        return 0;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
            unlink($file->getRealPath());
        } elseif ($file->isDir()) {
            rmdir($file->getRealPath());
        }
    }
    
    // Remove main directory if empty
    if (is_dir($dir)) {
        @rmdir($dir);
    }
    
    return $size;
}

/**
 * Cleanup old log files
 */
function cleanup_old_log_files($dir) {
    $size = 0;
    if (!file_exists($dir)) {
        return 0;
    }
    
    $log_files = glob($dir . '/*.log');
    $three_months_ago = strtotime('-3 months');
    
    foreach ($log_files as $file) {
        if (filemtime($file) < $three_months_ago) {
            $size += filesize($file);
            unlink($file);
        }
    }
    
    return $size;
}

/**
 * Cleanup QR code cache files
 */
function cleanup_qrcode_cache($dir) {
    $size = 0;
    if (!file_exists($dir)) {
        return 0;
    }
    
    $dat_files = glob($dir . '/**/*.dat', GLOB_BRACE);
    
    foreach ($dat_files as $file) {
        $size += filesize($file);
        unlink($file);
    }
    
    return $size;
}

/**
 * Cleanup expired transients
 */
function cleanup_transients() {
    global $wpdb;
    
    $deleted = $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE (option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%')
         AND option_name NOT LIKE '_transient_timeout_%'
         AND option_name NOT LIKE '_site_transient_timeout_%'"
    );
    
    // Estimate size (rough calculation)
    return $deleted * 1024; // Rough estimate
}

