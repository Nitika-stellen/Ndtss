<?php
/**
 * Event Admin Interface - Admin pages for event management
 * Handles event registrations, attendee management, and logs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display event registrations page
 */
function event_display_registrations_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    try {
        $form_id = 12;
        $search_criteria = array();
        $paging = array('offset' => 0, 'page_size' => 1000);
        $sorting = null;
        
        $entries = GFAPI::get_entries($form_id, $search_criteria, $sorting, $paging);
        
        if (is_wp_error($entries)) {
            event_log_error('Failed to retrieve event entries', [
                'form_id' => $form_id,
                'error' => $entries->get_error_message()
            ]);
            echo '<div class="notice notice-error"><p>Error retrieving entries: ' . esc_html($entries->get_error_message()) . '</p></div>';
            return;
        }
        
        // Sort entries by associated event post publish date
        usort($entries, function($a, $b) {
            $postA = get_post($a['source_id']);
            $postB = get_post($b['source_id']);
            
            $dateA = $postA ? strtotime($postA->post_date) : 0;
            $dateB = $postB ? strtotime($postB->post_date) : 0;
            
            return $dateB - $dateA; // Newest first
        });
        
        event_log_info('Event registrations page loaded', [
            'entry_count' => count($entries)
        ]);
        
        ?>
        <div class="wrap">
            <h1>Event Registrations</h1>
            
            <div class="event-admin-notice" id="event-admin-notice" style="display: none;">
                <p></p>
            </div>
            
            <table id="event_submitted_form" class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User Name</th>
                        <th>User Email</th>
                        <th>User Phone</th>
                        <th>Event Name</th>
                        <th>Status</th>
                        <th>Payment Status</th>
                        <th>CPD Points</th>
                        <th>Submitted Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 20px;">
                                No event registrations found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entries as $entry): ?>
                            <?php
                            $entry_id = $entry['id'];
                            $user_id = rgar($entry, 'created_by');
                            $date_created = $entry['date_created'];
                            $payment_status = rgar($entry, 'payment_status');
                            $user_info = get_userdata($user_id);
                            
                            $user_roles = $user_info ? implode(', ', $user_info->roles) : 'User not found';
                            $event_title = get_the_title($entry['source_id']);
                            $event_link = get_permalink($entry['source_id']);
                            $status = get_user_meta($user_id, 'event_' . $entry['source_id'] . '_approval_status', true);
                            $payment_amount = rgar($entry, 'payment_amount');
                            $cpd_points = get_user_meta($user_id, 'event_' . $entry['source_id'] . '_cpd_points', true);
                            
                            if (empty($payment_amount)) {
                                $payment_amount = 'Free';
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html($entry[29]); ?></td>
                                <td><?php echo esc_html($entry[2]); ?></td>
                                <td><?php echo esc_html($entry[3]); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($event_link); ?>" target="_blank">
                                        <?php echo esc_html($event_title); ?>
                                    </a>
                                </td>
                                <td class="status">
                                    <?php
                                    if ($status === 'approved') {
                                        echo '<span class="status-approved">Approved</span>';
                                    } elseif ($status === 'rejected') {
                                        echo '<span class="status-rejected">Rejected</span>';
                                    } else {
                                        echo '<span class="status-pending">Pending</span>';
                                    }
                                    ?>
                                </td>
                                <td class="payment-status">
                                    <?php
                                    if ($payment_status === 'Paid') {
                                        echo '<span class="payment-paid">Paid</span>';
                                    } elseif ($payment_status === 'Pending') {
                                        echo '<span class="payment-pending">Pending</span>';
                                    } elseif ($payment_status === 'Failed') {
                                        echo '<span class="payment-failed">Failed</span>';
                                    } else {
                                        echo '<span class="payment-na">N/A</span>';
                                    }
                                    ?>
                                </td>
                                <td class="cpd-points"><?php echo esc_html($cpd_points ?: 'N/A'); ?></td>
                                <td><?php echo esc_html(date('d/m/Y, g:i a', strtotime($date_created))); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(home_url("/wp-admin/admin.php?page=gf_entries&view=entry&id=12&lid={$entry_id}")); ?>" 
                                       class="button button-primary">View</a>
                                    
                                    <?php if ($status !== 'approved' && $status !== 'rejected'): ?>
                                        <button class="button button-success approve-entry" 
                                                data-entry-id="<?php echo esc_attr($entry_id); ?>"
                                                data-user-id="<?php echo esc_attr($user_id); ?>"
                                                data-event-id="<?php echo esc_attr($entry['source_id']); ?>">
                                            Approve
                                        </button>
                                        
                                        <button class="button button-danger reject-entry" 
                                                data-entry-id="<?php echo esc_attr($entry_id); ?>"
                                                data-user-id="<?php echo esc_attr($user_id); ?>"
                                                data-event-id="<?php echo esc_attr($entry['source_id']); ?>">
                                            Reject
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        
    } catch (Exception $e) {
        event_log_error('Error displaying registrations page', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        echo '<div class="notice notice-error"><p>An error occurred while loading the page.</p></div>';
    }
}

/**
 * Display attendee management page
 */
function event_display_attendee_management_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    try {
        global $wpdb;
        
        $users = $wpdb->get_results("
            SELECT um1.user_id, um1.meta_key AS event_checkin, um1.meta_value AS checkin_time, 
                   um2.meta_value AS cpd_points,
                   p.post_title AS event_name,
                   p.ID as event_id
            FROM {$wpdb->usermeta} um1
            LEFT JOIN {$wpdb->usermeta} um2 
                ON REPLACE(um1.meta_key, '_check_in_time', '_cpd_points') = um2.meta_key 
                AND um1.user_id = um2.user_id
            LEFT JOIN {$wpdb->posts} p 
                ON p.ID = REPLACE(REPLACE(um1.meta_key, 'event_', ''), '_check_in_time', '') 
            WHERE um1.meta_key LIKE 'event_%_check_in_time'
        ");
        
        $processed_data = [];
        foreach ($users as $user) {
            $user_id = $user->user_id;
            $event_name = $user->event_name ?: "Unknown Event";
            $event_id = $user->event_id ?: 0;
            $cpd = floatval($user->cpd_points);
            
            if (!isset($processed_data[$user_id])) {
                $processed_data[$user_id] = [
                    'user_id' => $user_id,
                    'events' => [],
                    'total_cpd' => 0
                ];
            }
            
            $processed_data[$user_id]['events'][$event_id] = $event_name;
            $processed_data[$user_id]['total_cpd'] += $cpd;
        }
        
        event_log_info('Attendee management page loaded', [
            'user_count' => count($processed_data)
        ]);
        
        ?>
        <div class="wrap">
            <h1>Attendee Management</h1>
            
            <div class="event-admin-actions">
                <button id="export-pdf" class="button button-primary">📄 Export CPD Report</button>
                <button id="refresh-data" class="button button-secondary">🔄 Refresh Data</button>
            </div>
            
            <table id="eventTable" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Attended Events</th>
                        <th>Total CPD Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processed_data as $user): 
                        $user_info = get_userdata($user['user_id']);
                        $user_name = $user_info ? $user_info->display_name : 'Unknown';
                        $user_email = $user_info ? $user_info->user_email : 'Unknown';
                        $events = $user['events'];
                        $total_events = count($events);
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($user_name); ?></strong>
                            </td>
                            <td>
                                <?php echo esc_html($user_email); ?>
                            </td>
                            <td>
                                <div class="event-list" data-user-id="<?php echo $user['user_id']; ?>">
                                    <?php 
                                    $i = 0;
                                    foreach ($events as $event_id => $event_name):
                                        $cpd_point = get_user_meta($user['user_id'], 'event_'.$event_id.'_cpd_points', true);
                                        $cpd_point = $cpd_point === '' ? '0' : $cpd_point;
                                        $hidden_class = ($i >= 2) ? 'extra-event' : '';
                                    ?>
                                        <div class="single-event <?php echo $hidden_class; ?>" style="<?php echo $i >= 2 ? 'display:none;' : ''; ?>">
                                            <div class="event_data">
                                                <strong><?php echo esc_html($event_name); ?></strong> - 
                                                <span id="cpd-points-<?php echo $user['user_id'] . '-' . $event_id; ?>"><?php echo $cpd_point; ?></span> Points
                                            </div>
                                            <button class="button-secondary edit-cpd"
                                                    data-user-id="<?php echo $user['user_id']; ?>"
                                                    data-event-id="<?php echo $event_id; ?>"
                                                    data-current-points="<?php echo $cpd_point; ?>">
                                                Edit
                                            </button>
                                        </div>
                                    <?php 
                                        $i++;
                                    endforeach; 
                                    if ($total_events > 2): ?>
                                        <a href="#" class="toggle-events">Show More</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo $user['total_cpd']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        
    } catch (Exception $e) {
        event_log_error('Error displaying attendee management page', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        echo '<div class="notice notice-error"><p>An error occurred while loading the page.</p></div>';
    }
}

/**
 * Display event logs page
 */
function event_display_logs_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    // Handle actions
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'clear_logs' && wp_verify_nonce($_POST['_wpnonce'], 'clear_event_logs')) {
            EventLogger::clear_logs();
            echo '<div class="notice notice-success"><p>Logs cleared successfully!</p></div>';
        }
    }
    
    // Get parameters
    $lines = isset($_GET['lines']) ? intval($_GET['lines']) : 100;
    $level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : null;
    
    // Get log data
    $logs = EventLogger::get_logs($lines, $level);
    $stats = EventLogger::get_log_stats();
    
    ?>
    <div class="wrap">
        <h1>Event Module Logs</h1>
        
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
                <input type="hidden" name="page" value="event-logs">
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
                    <?php wp_nonce_field('clear_event_logs'); ?>
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
            <a href="<?php echo admin_url('admin.php?page=event-logs'); ?>" class="button">Refresh</a>
        </div>
    </div>
    
    <style>
    .log-error { border-left-color: #dc3232 !important; background-color: #fbeaea; }
    .log-warning { border-left-color: #ffb900 !important; background-color: #fff8e5; }
    .log-info { border-left-color: #00a0d2 !important; background-color: #e5f3ff; }
    .log-debug { border-left-color: #666 !important; background-color: #f5f5f5; }
    .log-entry:hover { background-color: #fff !important; }
    .status-approved { color: #46b450; font-weight: bold; }
    .status-rejected { color: #dc3232; font-weight: bold; }
    .status-pending { color: #ffb900; font-weight: bold; }
    .payment-paid { color: #46b450; font-weight: bold; }
    .payment-pending { color: #ffb900; font-weight: bold; }
    .payment-failed { color: #dc3232; font-weight: bold; }
    .payment-na { color: #666; font-weight: bold; }
    .event-admin-actions { margin-bottom: 20px; }
    .event-admin-actions .button { margin-right: 10px; }
    </style>
    <?php
}


