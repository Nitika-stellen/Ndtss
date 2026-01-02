<?php
/**
 * CPD Pending Reviews Page
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

// Handle single entry view
$entry_id = isset($_GET['entry_id']) ? intval($_GET['entry_id']) : 0;

if ($entry_id > 0) {
    $entry = $wpdb->get_row($wpdb->prepare(
        "SELECT e.*, u.display_name, u.user_email 
        FROM {$table_name} e 
        LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID 
        WHERE e.cpd_id = %d",
        $entry_id
    ));
    
    if ($entry) {
        require_once get_stylesheet_directory() . '/cpd-management/templates/review-entry.php';
        return;
    }
}

// Get pending entries
$pending_entries = $wpdb->get_results(
    "SELECT e.*, u.display_name, u.user_email 
    FROM {$table_name} e 
    LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID 
    WHERE e.status = 'pending' 
    ORDER BY e.created_at ASC"
);
?>

<div class="wrap">
    <h1>Pending CPD Reviews</h1>
    
    <?php if (empty($pending_entries)): ?>
    <div class="notice notice-info">
        <p>No pending entries to review.</p>
    </div>
    <?php else: ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>User</th>
                <!-- <th>Activity Title</th> -->
                <th>Activity Date</th>
                <th>Category</th>
                <th>Points Requested</th>
                <th>Submitted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pending_entries as $entry): ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($entry->display_name); ?></strong><br>
                    <small><?php echo esc_html($entry->user_email); ?></small>
                </td>
                <!-- <td>
                    <?php //if (!empty($entry->activity_category)): ?>
                        <?php //echo esc_html(get_cpd_category_name($entry->activity_category)); ?>
                    <?php //else: ?>
                        <?php //echo esc_html($entry->activity_title ?: '-'); ?>
                    <?php //endif; ?>
                </td> -->
                <td><?php echo esc_html(date('M j, Y', strtotime($entry->activity_date))); ?></td>
                <td>
                    <?php 
                    $category_code = $entry->activity_category ?: '';
                    if ($category_code) {
                        $category_name = get_cpd_category_name($category_code);
                        echo '<strong>' . esc_html($category_code) . '</strong> - ' . esc_html($category_name);
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
                <td><?php echo esc_html($entry->points_requested > 0 ? $entry->points_requested : '-'); ?></td>
                <td><?php echo esc_html(human_time_diff(strtotime($entry->created_at), current_time('timestamp')) . ' ago'); ?></td>
                <td>
                    <a href="<?php echo admin_url('admin.php?page=cpd-pending-reviews&entry_id=' . $entry->cpd_id); ?>" class="button button-primary">
                        Review
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

