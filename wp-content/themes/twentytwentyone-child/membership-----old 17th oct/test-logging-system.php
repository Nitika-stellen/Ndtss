<?php
/**
 * Test script for membership logging system
 * This file helps test the logging functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function test_membership_logging_system() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    echo '<div class="wrap">';
    echo '<h1>Test Membership Logging System</h1>';
    
    // Test 1: Check if logger class exists
    echo '<h2>1. Logger Class Status</h2>';
    if (class_exists('MembershipLogger')) {
        echo '<p style="color: green;">✓ MembershipLogger class is available</p>';
    } else {
        echo '<p style="color: red;">✗ MembershipLogger class not found</p>';
        return;
    }
    
    // Test 2: Check log directory
    echo '<h2>2. Log Directory Status</h2>';
    $log_dir = get_stylesheet_directory() . '/membership/logs';
    if (file_exists($log_dir)) {
        echo '<p style="color: green;">✓ Log directory exists: ' . $log_dir . '</p>';
        if (is_writable($log_dir)) {
            echo '<p style="color: green;">✓ Log directory is writable</p>';
        } else {
            echo '<p style="color: red;">✗ Log directory is not writable</p>';
        }
    } else {
        echo '<p style="color: red;">✗ Log directory does not exist</p>';
    }
    
    // Test 3: Test logging functions
    echo '<h2>3. Test Logging Functions</h2>';
    if (isset($_GET['test_logs'])) {
        echo '<p>Testing logging functions...</p>';
        
        // Test different log levels
        membership_log_info('Test info message', ['test' => true, 'timestamp' => current_time('mysql')]);
        membership_log_warning('Test warning message', ['test' => true, 'timestamp' => current_time('mysql')]);
        membership_log_error('Test error message', ['test' => true, 'timestamp' => current_time('mysql')]);
        membership_log_debug('Test debug message', ['test' => true, 'timestamp' => current_time('mysql')]);
        
        echo '<p style="color: green;">✓ Test log entries created</p>';
    } else {
        echo '<p><a href="' . admin_url('admin.php?page=test-membership-logging&test_logs=1') . '" class="button button-primary">Test Logging Functions</a></p>';
    }
    
    // Test 4: Check log file creation
    echo '<h2>4. Log File Status</h2>';
    $log_file = $log_dir . '/membership-errors-' . date('Y-m') . '.log';
    if (file_exists($log_file)) {
        $file_size = filesize($log_file);
        echo '<p style="color: green;">✓ Log file exists: ' . basename($log_file) . '</p>';
        echo '<p>File size: ' . size_format($file_size) . '</p>';
        
        // Show last few log entries
        $log_content = file_get_contents($log_file);
        $log_lines = explode("\n", $log_content);
        $log_lines = array_filter($log_lines);
        $last_entries = array_slice($log_lines, -5);
        
        echo '<h4>Last 5 log entries:</h4>';
        echo '<div style="background: #f1f1f1; padding: 10px; font-family: monospace; font-size: 12px; max-height: 200px; overflow-y: auto;">';
        foreach ($last_entries as $entry) {
            echo '<div style="margin-bottom: 5px;">' . esc_html($entry) . '</div>';
        }
        echo '</div>';
    } else {
        echo '<p style="color: orange;">⚠ Log file does not exist yet</p>';
    }
    
    // Test 5: Test log statistics
    echo '<h2>5. Log Statistics</h2>';
    $stats = MembershipLogger::get_log_stats();
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Metric</th><th>Value</th></tr></thead>';
    echo '<tbody>';
    echo '<tr><td>Total Files</td><td>' . $stats['total_files'] . '</td></tr>';
    echo '<tr><td>Total Size</td><td>' . size_format($stats['total_size']) . '</td></tr>';
    echo '<tr><td>Total Entries</td><td>' . number_format($stats['total_entries']) . '</td></tr>';
    echo '<tr><td>Current File</td><td>' . $stats['current_file'] . '</td></tr>';
    echo '<tr><td>Current Size</td><td>' . size_format($stats['current_size']) . '</td></tr>';
    echo '</tbody></table>';
    
    // Test 6: Test log retrieval
    echo '<h2>6. Log Retrieval Test</h2>';
    $recent_logs = MembershipLogger::get_logs(10);
    if (!empty($recent_logs)) {
        echo '<p style="color: green;">✓ Successfully retrieved ' . count($recent_logs) . ' recent log entries</p>';
    } else {
        echo '<p style="color: orange;">⚠ No log entries found</p>';
    }
    
    // Test 7: Test log level filtering
    echo '<h2>7. Log Level Filtering Test</h2>';
    $error_logs = MembershipLogger::get_logs(10, 'ERROR');
    $info_logs = MembershipLogger::get_logs(10, 'INFO');
    $warning_logs = MembershipLogger::get_logs(10, 'WARNING');
    
    echo '<p>Error logs: ' . count($error_logs) . '</p>';
    echo '<p>Info logs: ' . count($info_logs) . '</p>';
    echo '<p>Warning logs: ' . count($warning_logs) . '</p>';
    
    // Test 8: Performance test
    echo '<h2>8. Performance Test</h2>';
    if (isset($_GET['perf_test'])) {
        $start_time = microtime(true);
        
        // Generate 100 log entries
        for ($i = 0; $i < 100; $i++) {
            membership_log_info("Performance test entry $i", ['iteration' => $i]);
        }
        
        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
        
        echo '<p style="color: green;">✓ Generated 100 log entries in ' . round($execution_time, 2) . 'ms</p>';
    } else {
        echo '<p><a href="' . admin_url('admin.php?page=test-membership-logging&perf_test=1') . '" class="button">Run Performance Test</a></p>';
    }
    
    // Test 9: Integration test
    echo '<h2>9. Integration Test</h2>';
    if (isset($_GET['integration_test'])) {
        echo '<p>Testing integration with membership functions...</p>';
        
        // Test form submission logging
        membership_log_info('Integration test: Form submission', [
            'form_id' => 999,
            'entry_id' => 999,
            'user_id' => get_current_user_id(),
            'test' => true
        ]);
        
        // Test approval logging
        membership_log_info('Integration test: Membership approval', [
            'user_id' => 999,
            'entry_id' => 999,
            'approver_id' => get_current_user_id(),
            'test' => true
        ]);
        
        // Test reminder logging
        membership_log_info('Integration test: Reminder email', [
            'user_id' => 999,
            'user_email' => 'test@example.com',
            'days_until_expiry' => 30,
            'test' => true
        ]);
        
        echo '<p style="color: green;">✓ Integration test completed</p>';
    } else {
        echo '<p><a href="' . admin_url('admin.php?page=test-membership-logging&integration_test=1') . '" class="button">Run Integration Test</a></p>';
    }
    
    // Test 10: Cleanup test
    echo '<h2>10. Cleanup Test</h2>';
    if (isset($_GET['cleanup_test'])) {
        if (MembershipLogger::clear_logs()) {
            echo '<p style="color: green;">✓ Logs cleared successfully</p>';
        } else {
            echo '<p style="color: red;">✗ Failed to clear logs</p>';
        }
    } else {
        echo '<p><a href="' . admin_url('admin.php?page=test-membership-logging&cleanup_test=1') . '" class="button button-secondary" onclick="return confirm(\'Are you sure you want to clear all logs?\');">Clear All Logs</a></p>';
    }
    
    // Navigation
    echo '<h2>Navigation</h2>';
    echo '<p>';
    echo '<a href="' . admin_url('admin.php?page=membership-logs') . '" class="button">View Logs</a> ';
    echo '<a href="' . admin_url('admin.php?page=membership-management') . '" class="button">Membership Management</a>';
    echo '</p>';
    
    echo '</div>';
}

// Add admin menu for testing
function add_membership_logging_test_menu() {
    add_submenu_page(
        'membership-management',
        'Test Logging',
        'Test Logging',
        'manage_options',
        'test-membership-logging',
        'test_membership_logging_system'
    );
}
add_action('admin_menu', 'add_membership_logging_test_menu');
