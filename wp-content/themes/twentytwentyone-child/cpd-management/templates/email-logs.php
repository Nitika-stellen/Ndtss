<?php
/**
 * CPD Email Logs Viewer
 */

if (!defined('ABSPATH')) {
    exit;
}

$cpd_manager = CPD_Manager::get_instance();
if (!$cpd_manager->user_can_access_cpd_admin()) {
    wp_die('You do not have permission to access this page.');
}

// Handle actions
$logs_cleared = false;
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'clear_logs' && wp_verify_nonce($_POST['_wpnonce'], 'clear_cpd_email_logs')) {
        CPD_Email_Logger::clear_logs();
        $logs_cleared = true;
    }
}

// Get parameters
$lines = isset($_GET['lines']) ? intval($_GET['lines']) : 100;
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : null;
$email_type = isset($_GET['email_type']) ? sanitize_text_field($_GET['email_type']) : null;

// Get log data
$logs = CPD_Email_Logger::get_logs($lines, $status, $email_type);
$stats = CPD_Email_Logger::get_log_stats();

// Email types for filter
$email_types = [
    'user_entry_submitted' => 'User Entry Submitted',
    'user_entry_approved' => 'User Entry Approved',
    'user_entry_rejected' => 'User Entry Rejected',
    'user_annual_report' => 'Annual Report',
    'admin_new_entry' => 'Admin New Entry Notification'
];
?>

<div class="wrap">
    <h1>CPD Email Logs</h1>
    
    <!-- Statistics -->
    <div class="notice notice-info">
        <h3>Email Log Statistics</h3>
        <p>
            <strong>Total Files:</strong> <?php echo esc_html($stats['total_files']); ?> | 
            <strong>Total Size:</strong> <?php echo esc_html(size_format($stats['total_size'])); ?> | 
            <strong>Total Entries:</strong> <?php echo esc_html(number_format($stats['total_entries'])); ?> | 
            <strong>Current File:</strong> <?php echo esc_html($stats['current_file']); ?> | 
            <strong>Current Size:</strong> <?php echo esc_html(size_format($stats['current_size'])); ?>
        </p>
        <p style="margin-top: 10px;">
            <strong>Status Breakdown:</strong> 
            <span style="color: #46b450;">✓ Sent: <?php echo esc_html($stats['status_counts']['sent']); ?></span> | 
            <span style="color: #dc3232;">✗ Failed: <?php echo esc_html($stats['status_counts']['failed']); ?></span> | 
            <span style="color: #ffb900;">⊘ Skipped: <?php echo esc_html($stats['status_counts']['skipped']); ?></span>
        </p>
        <?php if ($stats['status_counts']['failed'] > 0): ?>
        <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffb900; border-radius: 4px;">
            <strong>⚠️ Note:</strong> Failed emails on localhost are normal. WordPress <code>wp_mail()</code> requires proper email/SMTP configuration to work. 
            Install an SMTP plugin (like "WP Mail SMTP") for local testing, or these will work automatically in production if your server has email configured.
        </div>
        <?php endif; ?>
    </div>
    
    <p class="description" style="margin-top: 20px;">Log entries are hidden by default to keep this page compact. Click the button below whenever you need to review detailed email logs.</p>
    <p>
        <button type="button" class="button" id="toggle-email-logs">Show Log Entries</button>
    </p>
    
    <div id="email-log-wrapper" style="display:none; margin-top: 15px;">
        <!-- Filters -->
        <div class="tablenav top">
            <form method="get" style="display: inline-block;">
                <input type="hidden" name="page" value="cpd-email-logs">
                
                <label for="lines">Show last:</label>
                <select name="lines" id="lines">
                    <option value="50" <?php selected($lines, 50); ?>>50 entries</option>
                    <option value="100" <?php selected($lines, 100); ?>>100 entries</option>
                    <option value="200" <?php selected($lines, 200); ?>>200 entries</option>
                    <option value="500" <?php selected($lines, 500); ?>>500 entries</option>
                    <option value="1000" <?php selected($lines, 1000); ?>>1000 entries</option>
                </select>
                
                <label for="status" style="margin-left: 10px;">Filter by status:</label>
                <select name="status" id="status">
                    <option value="">All Statuses</option>
                    <option value="sent" <?php selected($status, 'sent'); ?>>Sent</option>
                    <option value="failed" <?php selected($status, 'failed'); ?>>Failed</option>
                    <option value="skipped" <?php selected($status, 'skipped'); ?>>Skipped</option>
                </select>
                
                <label for="email_type" style="margin-left: 10px;">Filter by email type:</label>
                <select name="email_type" id="email_type">
                    <option value="">All Types</option>
                    <?php foreach ($email_types as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($email_type, $key); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="submit" class="button" value="Filter" style="margin-left: 10px;">
                <a href="<?php echo admin_url('admin.php?page=cpd-email-logs'); ?>" class="button" style="margin-left: 5px;">Clear Filters</a>
            </form>
            
            <div class="tablenav-pages" style="float: right;">
                <form method="post" style="display: inline-block;">
                    <?php wp_nonce_field('clear_cpd_email_logs'); ?>
                    <input type="hidden" name="action" value="clear_logs">
                    <button type="submit" class="button button-secondary" id="clear-logs-btn">
                        Clear All Logs
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Log Entries -->
        <div class="log-viewer" style="background: #1e1e1e; border: 1px solid #ccd0d4; padding: 15px; margin-top: 20px; max-height: 600px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 12px; border-radius: 4px;">
        <?php if (empty($logs)): ?>
            <p style="color: #fff;">No log entries found.</p>
        <?php else: ?>
            <?php foreach ($logs as $log_entry): ?>
                <?php
                $log_class = 'log-entry';
                $border_color = '#666';
                $bg_color = 'transparent';
                
                if (strpos($log_entry, '[EMAIL-SENT]') !== false) {
                    $log_class .= ' log-sent';
                    $border_color = '#46b450';
                    $bg_color = 'rgba(70, 180, 80, 0.1)';
                } elseif (strpos($log_entry, '[EMAIL-FAILED]') !== false) {
                    $log_class .= ' log-failed';
                    $border_color = '#dc3232';
                    $bg_color = 'rgba(220, 50, 50, 0.1)';
                } elseif (strpos($log_entry, '[EMAIL-SKIPPED]') !== false) {
                    $log_class .= ' log-skipped';
                    $border_color = '#ffb900';
                    $bg_color = 'rgba(255, 185, 0, 0.1)';
                }
                
                // Clean and display log entry (remove any accidental HTML)
                $clean_entry = strip_tags($log_entry);
                $clean_entry = html_entity_decode($clean_entry, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
                // Simple highlighting - do it after cleaning
                $clean_entry = preg_replace('/\[([^\]]+)\]/', '<span style="color: #569cd6; font-weight: bold;">[$1]</span>', esc_html($clean_entry));
                ?>
                <div class="<?php echo esc_attr($log_class); ?>" 
                     style="margin-bottom: 8px; padding: 8px; border-left: 4px solid <?php echo esc_attr($border_color); ?>; background-color: <?php echo esc_attr($bg_color); ?>; color: #d4d4d4; border-radius: 2px;">
                    <div style="font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.6;">
                        <?php echo $clean_entry; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
        <!-- Actions -->
        <div style="margin-top: 20px;">
            <a href="<?php echo admin_url('admin.php?page=cpd-email-logs'); ?>" class="button">Refresh</a>
            <span style="margin-left: 10px; color: #666;">
                Showing <?php echo esc_html(count($logs)); ?> of <?php echo esc_html($stats['total_entries']); ?> total entries
            </span>
        </div>
    </div>
</div>

<style>
.log-entry {
    transition: background-color 0.2s;
}
.log-entry:hover {
    background-color: rgba(255, 255, 255, 0.05) !important;
}
.log-sent {
    border-left-color: #46b450 !important;
}
.log-failed {
    border-left-color: #dc3232 !important;
}
.log-skipped {
    border-left-color: #ffb900 !important;
}
.tablenav {
    margin: 10px 0;
}
.tablenav label {
    margin-right: 5px;
    font-weight: 600;
}
</style>

<script>
jQuery(document).ready(function($) {
    const storageKey = 'cpd_email_logs_visible';
    const $logWrapper = $('#email-log-wrapper');
    const $toggleButton = $('#toggle-email-logs');
    let logsVisible = false;

    // Determine initial visibility from localStorage (if available)
    try {
        logsVisible = localStorage.getItem(storageKey) === 'yes';
    } catch (err) {
        logsVisible = false;
    }

    if (logsVisible) {
        $logWrapper.show();
        $toggleButton.text('Hide Log Entries');
    }

    $toggleButton.on('click', function(e) {
        e.preventDefault();
        logsVisible = !logsVisible;
        if (logsVisible) {
            $logWrapper.slideDown(200);
            $toggleButton.text('Hide Log Entries');
        } else {
            $logWrapper.slideUp(200);
            $toggleButton.text('Show Log Entries');
        }

        try {
            localStorage.setItem(storageKey, logsVisible ? 'yes' : 'no');
        } catch (err) {
            // Ignore storage errors (private browsing, etc.)
        }
    });

    // Handle clear logs with confirmation
    $('#clear-logs-btn').on('click', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Clear All Email Logs?',
            text: 'This will permanently delete all email log files. This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3232',
            cancelButtonColor: '#0073aa',
            confirmButtonText: 'Yes, Clear All Logs',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Clearing Logs...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: function() {
                        Swal.showLoading();
                    }
                });
                
                // Submit the form
                $(e.target).closest('form').submit();
            }
        });
        
        return false;
    });
    
    // Show success message if logs were cleared
    <?php if ($logs_cleared): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'All email logs have been cleared successfully!',
        confirmButtonColor: '#0073aa',
        timer: 2000,
        timerProgressBar: true
    }).then(function() {
        window.location.href = '<?php echo admin_url('admin.php?page=cpd-email-logs'); ?>';
    });
    <?php endif; ?>
});
</script>

