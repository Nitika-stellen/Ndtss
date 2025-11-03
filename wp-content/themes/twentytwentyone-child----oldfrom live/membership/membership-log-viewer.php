<?php
/**
 * Membership Log Viewer
 * Admin interface to view and manage membership logs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function render_membership_log_viewer() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    // Handle actions
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'clear_logs' && wp_verify_nonce($_POST['_wpnonce'], 'clear_membership_logs')) {
            MembershipLogger::clear_logs();
            echo '<div class="notice notice-success"><p>Logs cleared successfully!</p></div>';
        }
    }
    
    // Get parameters
    $lines = isset($_GET['lines']) ? intval($_GET['lines']) : 100;
    $level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : null;
    
    // Get log data
    $logs = MembershipLogger::get_logs($lines, $level);
    $stats = MembershipLogger::get_log_stats();
    
    ?>
    <div class="wrap">
        <h1>Membership Module Logs</h1>
        
        <!-- Statistics -->
        <div class="notice notice-info">
            <h3>Log Statistics</h3>
            <p>
                <strong>Total Files:</strong> <?php echo $stats['total_files']; ?> | 
                <strong>Total Size:</strong> <?php echo size_format($stats['total_size']); ?> | 
                <strong>Total Entries:</strong> <?php echo number_format($stats['total_entries']); ?> | 
                <strong>Current File:</strong> <?php echo $stats['current_file']; ?> | 
                <strong>Current Size:</strong> <?php echo size_format($stats['current_size']); ?>
            </p>
        </div>
        
        <!-- Filters -->
        <div class="tablenav top">
            <form method="get" style="display: inline-block;">
                <input type="hidden" name="page" value="membership-logs">
                <label for="lines">Show last:</label>
                <select name="lines" id="lines">
                    <option value="50" <?php selected($lines, 50); ?>>50 entries</option>
                    <option value="100" <?php selected($lines, 100); ?>>100 entries</option>
                    <option value="200" <?php selected($lines, 200); ?>>200 entries</option>
                    <option value="500" <?php selected($lines, 500); ?>>500 entries</option>
                    <option value="1000" <?php selected($lines, 1000); ?>>1000 entries</option>
                </select>
                
                <label for="level">Filter by level:</label>
                <select name="level" id="level">
                    <option value="">All levels</option>
                    <option value="ERROR" <?php selected($level, 'ERROR'); ?>>ERROR</option>
                    <option value="WARNING" <?php selected($level, 'WARNING'); ?>>WARNING</option>
                    <option value="INFO" <?php selected($level, 'INFO'); ?>>INFO</option>
                    <option value="DEBUG" <?php selected($level, 'DEBUG'); ?>>DEBUG</option>
                </select>
                
                <input type="submit" class="button" value="Filter">
            </form>
            
            <div class="tablenav-pages">
                <form method="post" style="display: inline-block; margin-left: 20px;">
                    <?php wp_nonce_field('clear_membership_logs'); ?>
                    <input type="hidden" name="action" value="clear_logs">
                    <input type="submit" class="button button-secondary" value="Clear All Logs" 
                           onclick="return confirm('Are you sure you want to clear all logs? This action cannot be undone.');">
                </form>
            </div>
        </div>
        
        <!-- Log Entries -->
        <div class="log-viewer" style="background: #f1f1f1; border: 1px solid #ccd0d4; padding: 10px; margin-top: 20px; max-height: 600px; overflow-y: auto; font-family: monospace; font-size: 12px;">
            <?php if (empty($logs)): ?>
                <p>No log entries found.</p>
            <?php else: ?>
                <?php foreach ($logs as $log_entry): ?>
                    <?php
                    $log_class = 'log-entry';
                    if (strpos($log_entry, '[ERROR]') !== false) {
                        $log_class .= ' log-error';
                    } elseif (strpos($log_entry, '[WARNING]') !== false) {
                        $log_class .= ' log-warning';
                    } elseif (strpos($log_entry, '[INFO]') !== false) {
                        $log_class .= ' log-info';
                    } elseif (strpos($log_entry, '[DEBUG]') !== false) {
                        $log_class .= ' log-debug';
                    }
                    ?>
                    <div class="<?php echo $log_class; ?>" style="margin-bottom: 5px; padding: 5px; border-left: 4px solid #ddd;">
                        <?php echo esc_html($log_entry); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Refresh Button -->
        <div style="margin-top: 20px;">
            <a href="<?php echo admin_url('admin.php?page=membership-logs'); ?>" class="button">Refresh</a>
        </div>
    </div>
    
    <style>
    .log-error { border-left-color: #dc3232 !important; background-color: #fbeaea; }
    .log-warning { border-left-color: #ffb900 !important; background-color: #fff8e5; }
    .log-info { border-left-color: #00a0d2 !important; background-color: #e5f3ff; }
    .log-debug { border-left-color: #666 !important; background-color: #f5f5f5; }
    .log-entry:hover { background-color: #fff !important; }
    </style>
    <?php
}

// Add admin menu for log viewer
function add_membership_log_menu() {
    add_submenu_page(
        'membership-management',
        'Membership Logs',
        'Membership Logs',
        'manage_options',
        'membership-logs',
        'render_membership_log_viewer'
    );
}
add_action('admin_menu', 'add_membership_log_menu');
