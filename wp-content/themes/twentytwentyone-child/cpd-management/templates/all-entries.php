<?php
/**
 * CPD All Entries Page
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

// Filters
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$filter_user = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : '';

// Build query
$where_conditions = array('1=1');
$where_values = array();

if ($filter_status) {
    $where_conditions[] = 'e.status = %s';
    $where_values[] = $filter_status;
}

if ($filter_user) {
    $where_conditions[] = 'e.user_id = %d';
    $where_values[] = $filter_user;
}

if ($filter_year) {
    $where_conditions[] = 'YEAR(e.activity_date) = %d';
    $where_values[] = $filter_year;
}

$where_clause = implode(' AND ', $where_conditions);

if (!empty($where_values)) {
    $query = $wpdb->prepare(
        "SELECT e.*, u.display_name, u.user_email 
        FROM {$table_name} e 
        LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID 
        WHERE {$where_clause} 
        ORDER BY e.created_at DESC",
        ...$where_values
    );
} else {
    $query = "SELECT e.*, u.display_name, u.user_email 
        FROM {$table_name} e 
        LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID 
        WHERE {$where_clause} 
        ORDER BY e.created_at DESC";
}

$all_entries = $wpdb->get_results($query);

// Get users for filter
$users = $wpdb->get_results(
    "SELECT DISTINCT u.ID, u.display_name, u.user_email 
    FROM {$wpdb->users} u 
    INNER JOIN {$table_name} e ON u.ID = e.user_id 
    ORDER BY u.display_name"
);
?>

<div class="wrap">
    <h1>All CPD Entries</h1>
    
    <div class="cpd-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="cpd-all-entries" />
            
            <select name="status">
                <option value="">All Statuses</option>
                <option value="pending" <?php selected($filter_status, 'pending'); ?>>Pending</option>
                <option value="approved" <?php selected($filter_status, 'approved'); ?>>Approved</option>
                <option value="rejected" <?php selected($filter_status, 'rejected'); ?>>Rejected</option>
            </select>
            
            <select name="user_id">
                <option value="">All Users</option>
                <?php foreach ($users as $user): ?>
                <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($filter_user, $user->ID); ?>>
                    <?php echo esc_html($user->display_name); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="year">
                <option value="">All Years</option>
                <?php
                $current_year = date('Y');
                for ($i = $current_year; $i >= $current_year - 5; $i--) {
                    echo '<option value="' . $i . '" ' . selected($filter_year, $i, false) . '>' . $i . '</option>';
                }
                ?>
            </select>
            
            <button type="submit" class="button">Filter</button>
            <a href="<?php echo admin_url('admin.php?page=cpd-all-entries'); ?>" class="button">Clear</a>
        </form>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>User</th>
                <th>Activity Category</th>
                <th>Activity Date</th>
                <!-- <th>Category</th> -->
                <th>Points Allocated</th>
                <th>Status</th>
                <th>Reviewed By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($all_entries)): ?>
            <tr>
                <td colspan="8">No entries found.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($all_entries as $entry): ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($entry->display_name); ?></strong><br>
                    <small><?php echo esc_html($entry->user_email); ?></small>
                </td>
                <td>
                    <?php if (!empty($entry->activity_category)): ?>
                        <?php echo esc_html(get_cpd_category_name($entry->activity_category)); ?>
                    <?php else: ?>
                        <?php echo esc_html($entry->activity_title ?: '-'); ?>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html(date('M j, Y', strtotime($entry->activity_date))); ?></td>
               <!--  <td>
                    <?php 
                    // $category_code = $entry->activity_category ?: '';
                    // if ($category_code) {
                    //     $category_name = get_cpd_category_name($category_code);
                    //     echo '<strong>' . esc_html($category_code) . '</strong> - ' . esc_html($category_name);
                    // } else {
                    //     echo '-';
                    // }
                    ?>
                </td> -->
                <td><?php echo esc_html($entry->points_allocated > 0 ? $entry->points_allocated : '-'); ?></td>
                <td>
                    <span class="status-badge status-<?php echo esc_attr($entry->status); ?>">
                        <?php echo esc_html(ucfirst($entry->status)); ?>
                    </span>
                </td>
                <td>
                    <?php if ($entry->reviewed_by): ?>
                        <?php
                        $reviewer = get_userdata($entry->reviewed_by);
                        echo $reviewer ? esc_html($reviewer->display_name) : '-';
                        ?>
                        <br><small><?php echo esc_html(date('M j, Y', strtotime($entry->reviewed_at))); ?></small>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
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

