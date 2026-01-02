<?php
/**
 * CPD Management Admin Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

// Function to get category name from code (avoid redeclare across templates)
if (!function_exists('get_cpd_category_name')) {
    function get_cpd_category_name($code) {
        $categories = array(
            'A1' => 'Performing NDT Activity',
            'A2' => 'Theoretical Training',
            'A3' => 'Practical Training',
            'A4' => 'Delivery of Training',
            'A5' => 'Research Activities',
            '6' => 'Technical Seminar/Paper',
            '7' => 'Presenting Technical Seminar',
            '8' => 'Society Membership',
            '9' => 'Technical Oversight',
            '10' => 'Committee Participation',
            '11' => 'Certification Body Role'
        );
        
        return isset($categories[$code]) ? $categories[$code] : $code;
    }
}

$cpd_manager = CPD_Manager::get_instance();
if (!$cpd_manager->user_can_access_cpd_admin()) {
    wp_die('You do not have permission to access this page.');
}

global $wpdb;
$table_name = $wpdb->prefix . 'sgndt_cpd_entries';

// Get statistics
$total_entries = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
$pending_entries = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending'");
$approved_entries = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'approved'");

// Get recent entries
$recent_entries = $wpdb->get_results(
    "SELECT e.*, u.display_name, u.user_email 
    FROM {$table_name} e 
    LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID 
    ORDER BY e.created_at DESC 
    LIMIT 10"
);
?>

<div class="wrap">
    <h1>CPD Management Dashboard</h1>
    
    <div class="cpd-dashboard-stats">
        <div class="stat-box">
            <div class="stat-icon dashicons dashicons-clipboard"></div>
            <div class="stat-content">
                <h3><?php echo esc_html($total_entries); ?></h3>
                <p>Total Entries</p>
            </div>
        </div>
        
        <div class="stat-box pending">
            <div class="stat-icon dashicons dashicons-clock"></div>
            <div class="stat-content">
                <h3><?php echo esc_html($pending_entries); ?></h3>
                <p>Pending Reviews</p>
            </div>
        </div>
        
        <div class="stat-box approved">
            <div class="stat-icon dashicons dashicons-yes-alt"></div>
            <div class="stat-content">
                <h3><?php echo esc_html($approved_entries); ?></h3>
                <p>Approved Entries</p>
            </div>
        </div>
    </div>
    
    <div class="cpd-dashboard-sections">
        <div class="dashboard-section">
            <h2>Recent Entries</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Activity</th>
                        <th>Date</th>
                        <th>Points</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_entries)): ?>
                    <tr>
                        <td colspan="6">No entries found.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($recent_entries as $entry): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($entry->display_name); ?></strong><br>
                            <small><?php echo esc_html($entry->user_email); ?></small>
                        </td>
                        <td>
                            <?php 
                            // Show only the activity category name in the Activity column
                            $category_code = $entry->activity_category ?: '';
                            if ($category_code) {
                                echo '<strong>' . esc_html(get_cpd_category_name($category_code)) . '</strong>';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html(date('M j, Y', strtotime($entry->activity_date))); ?></td>
                        <td>
                            <?php if ($entry->points_allocated > 0): ?>
                                <?php echo esc_html($entry->points_allocated); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($entry->status); ?>">
                                <?php echo esc_html(ucfirst($entry->status)); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=cpd-pending-reviews&entry_id=' . $entry->cpd_id); ?>" class="button button-small">
                                View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="dashboard-section">
            <h2>Quick Actions</h2>
            <div class="quick-actions">
                <a href="<?php echo admin_url('admin.php?page=cpd-pending-reviews'); ?>" class="button button-primary">
                    Review Pending Entries
                </a>
                <a href="<?php echo admin_url('admin.php?page=cpd-all-entries'); ?>" class="button">
                    View All Entries
                </a>
                <a href="<?php echo admin_url('admin.php?page=cpd-annual-reports'); ?>" class="button">
                    Annual Reports
                </a>
                <a href="<?php echo admin_url('admin.php?page=cpd-settings'); ?>" class="button">
                    Settings
                </a>
            </div>
        </div>
    </div>
</div>

