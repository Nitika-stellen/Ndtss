<?php
/**
 * Certificate Management Main Page
 * 
 * Displays all certificates from both legacy and new import systems
 * Includes search, filter, and export functionality
 * 
 * @package SGNDT
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu hook
add_action('admin_menu', 'certificate_management_register_main_page', 5);
add_action('admin_enqueue_scripts', 'certificate_management_enqueue_assets');
add_action('wp_ajax_search_certificates', 'handle_certificate_search');
add_action('wp_ajax_export_certificates', 'handle_certificate_export');

/**
 * Register main certificate management page
 */
function certificate_management_register_main_page() {
    global $submenu, $menu;
    
    // Check if certificate-management menu already exists
    $menu_exists = false;
    if (isset($menu)) {
        foreach ($menu as $menu_item) {
            if (isset($menu_item[2]) && $menu_item[2] === 'certificate-management') {
                $menu_exists = true;
                break;
            }
        }
    }
    
    // If menu doesn't exist, create it with our callback
    if (!$menu_exists) {
        add_menu_page(
            'Certificate Management',
            'Certificates',
            'manage_options',
            'certificate-management',
            'certificate_management_main_page',
            'dashicons-awards',
            30
        );
    } else {
        // Menu exists, update the callback for the main page
        // We'll add a submenu item that redirects to the main page
        add_submenu_page(
            'certificate-management',
            'All Certificates',
            'All Certificates',
            'manage_options',
            'certificate-management',
            'certificate_management_main_page'
        );
    }
}

/**
 * Enqueue CSS and JS
 */
function certificate_management_enqueue_assets($hook) {
    if (strpos($hook, 'certificate-management') === false) {
        return;
    }
    
    wp_enqueue_style(
        'certificate-management-css',
        get_stylesheet_directory_uri() . '/panel/css/certificate-management.css',
        array(),
        '1.0.0'
    );
    
    wp_enqueue_script(
        'certificate-management-js',
        get_stylesheet_directory_uri() . '/panel/js/certificate-management.js',
        array('jquery'),
        '1.0.0',
        true
    );
    
    wp_localize_script('certificate-management-js', 'certManagement', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cert_management_nonce')
    ));
}

/**
 * Main certificate management page
 */
function certificate_management_main_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    // Get filter parameters
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $method_filter = isset($_GET['method']) ? sanitize_text_field($_GET['method']) : '';
    $import_source = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : '';
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
    // Validate per_page to allowed values
    $allowed_per_page = array(10, 20, 50, 100);
    if (!in_array($per_page, $allowed_per_page)) {
        $per_page = 20;
    }
    
    // Get sorting parameters
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'issue_date';
    $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get certificates
    $result = get_certificates_with_filters($search, $status_filter, $method_filter, $import_source, $paged, $per_page, $orderby, $order);
    $certificates = $result['certificates'];
    $total_count = $result['total'];
    $stats = get_certificate_statistics();
    
    ?>
    <div class="wrap certificate-management-wrap">
        <h1>Certificate Management</h1>
        
        <!-- Statistics Dashboard -->
        <div class="cert-stats-dashboard">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total Certificates</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['active']); ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['expired']); ?></div>
                <div class="stat-label">Expired</div>
            </div>
        </div>
        
        <!-- Search and Filters -->
        <div class="cert-filters-section">
            <form method="get" action="" class="cert-filters-form">
                <input type="hidden" name="page" value="certificate-management">
                
                <div class="filter-row">
                    <input type="text" 
                           name="s" 
                           value="<?php echo esc_attr($search); ?>" 
                           placeholder="Search by certificate number, name, or email..."
                           class="cert-search-input">
                    
                    <select name="status" class="cert-filter-select">
                        <option value="">All Statuses</option>
                        <option value="active" <?php selected($status_filter, 'active'); ?>>Active</option>
                        <option value="expired" <?php selected($status_filter, 'expired'); ?>>Expired</option>
                    </select>
                    
                    <select name="method" class="cert-filter-select">
                        <option value="">All Methods</option>
                        <?php
                        $methods = get_unique_certificate_methods();
                        foreach ($methods as $method) {
                            echo '<option value="' . esc_attr($method) . '" ' . selected($method_filter, $method, false) . '>' . esc_html($method) . '</option>';
                        }
                        ?>
                    </select>
                    
                    <button type="submit" class="button button-primary">Filter</button>
                    <a href="?page=certificate-management" class="button">Reset</a>
                    <button type="button" class="button" id="export-certificates">Export CSV</button>
                    
                    <div class="per-page-selector">
                        <label for="per-page-select">Show:</label>
                        <select name="per_page" id="per-page-select" class="cert-filter-select" onchange="this.form.submit()">
                            <option value="10" <?php selected($per_page, 10); ?>>10</option>
                            <option value="20" <?php selected($per_page, 20); ?>>20</option>
                            <option value="50" <?php selected($per_page, 50); ?>>50</option>
                            <option value="100" <?php selected($per_page, 100); ?>>100</option>
                        </select>
                        <span class="per-page-label">per page</span>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Certificates Table -->
        <?php if (empty($certificates)): ?>
            <div class="no-certificates">
                <p>No certificates found.</p>
            </div>
        <?php else: ?>
            <div class="cert-table-container">
                <table class="wp-list-table widefat fixed striped cert-table">
                    <thead>
                        <tr>
                            <?php
                            // Helper function to generate sortable column header
                            function get_sortable_column_header($label, $column_key, $current_orderby, $current_order) {
                                $is_current = ($current_orderby === $column_key);
                                $new_order = ($is_current && $current_order === 'ASC') ? 'DESC' : 'ASC';
                                $arrow = '';
                                if ($is_current) {
                                    $arrow = $current_order === 'ASC' ? ' ▲' : ' ▼';
                                }
                                
                                $url = add_query_arg(array(
                                    'orderby' => $column_key,
                                    'order' => $new_order
                                ));
                                
                                return '<a href="' . esc_url($url) . '" class="sortable-header' . ($is_current ? ' sorted' : '') . '">' . 
                                       esc_html($label) . $arrow . '</a>';
                            }
                            ?>
                            <th><?php echo get_sortable_column_header('User', 'user_id', $orderby, $order); ?></th>
                            <th><?php echo get_sortable_column_header('Certificate Number', 'certificate_number', $orderby, $order); ?></th>
                            <th><?php echo get_sortable_column_header('Method', 'method', $orderby, $order); ?></th>
                            <th><?php echo get_sortable_column_header('Level', 'level', $orderby, $order); ?></th>
                            <th><?php echo get_sortable_column_header('Sector', 'sector', $orderby, $order); ?></th>
                            <th><?php echo get_sortable_column_header('Issue Date', 'issue_date', $orderby, $order); ?></th>
                            <th><?php echo get_sortable_column_header('Expiry Date', 'expiry_date', $orderby, $order); ?></th>
                            <th><?php echo get_sortable_column_header('Status', 'status', $orderby, $order); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($certificates as $cert): ?>
                            <tr>
                                <td>
                                    <?php
                                    $user = get_userdata($cert->user_id);
                                    if ($user) {
                                        echo '<strong>' . esc_html($user->display_name) . '</strong><br>';
                                        echo '<small>' . esc_html($user->user_email) . '</small>';
                                    } else {
                                        echo '<em>User not found</em>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($cert->certificate_number); ?></strong>
                                    <?php
                                    $reg_number = get_user_meta($cert->user_id, 'candidate_reg_number', true);
                                    if ($reg_number) {
                                        echo '<br><small>Reg: ' . esc_html($reg_number) . '</small>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($cert->method); ?></td>
                                <td><?php echo esc_html($cert->level); ?></td>
                                <td><?php echo esc_html($cert->sector); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($cert->issue_date)); ?></td>
                                <td>
                                    <?php 
                                    echo date('d/m/Y', strtotime($cert->expiry_date));
                                    $is_expired = strtotime($cert->expiry_date) < time();
                                    if ($is_expired) {
                                        echo '<br><span class="expired-badge">Expired</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr(strtolower($cert->status)); ?>">
                                        <?php echo esc_html(ucfirst($cert->status)); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php
            $total_pages = ceil($total_count / $per_page);
            ?>
            <div class="cert-pagination">
                <?php
                $base_url = remove_query_arg('paged');
                $start_record = (($paged - 1) * $per_page) + 1;
                $end_record = min($paged * $per_page, $total_count);
                
                if ($total_pages > 1) {
                    if ($paged > 1) {
                        echo '<a href="' . esc_url(add_query_arg('paged', $paged - 1, $base_url)) . '" class="button">« Previous</a>';
                    } else {
                        echo '<span class="button disabled">« Previous</span>';
                    }
                }
                
                echo '<span class="pagination-info">';
                echo 'Showing ' . number_format($start_record) . '-' . number_format($end_record) . ' of ' . number_format($total_count) . ' certificates';
                if ($total_pages > 1) {
                    echo ' (Page ' . $paged . ' of ' . $total_pages . ')';
                }
                echo '</span>';
                
                if ($total_pages > 1) {
                    if ($paged < $total_pages) {
                        echo '<a href="' . esc_url(add_query_arg('paged', $paged + 1, $base_url)) . '" class="button">Next »</a>';
                    } else {
                        echo '<span class="button disabled">Next »</span>';
                    }
                }
                ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Get certificates with filters
 */
function get_certificates_with_filters($search, $status, $method, $source, $paged, $per_page, $orderby = 'issue_date', $order = 'DESC') {
    global $wpdb;
    $table = $wpdb->prefix . 'sgndt_final_certifications';
    
    $where = array('1=1');
    $params = array();
    
    // Search filter
    if (!empty($search)) {
        $where[] = "(certificate_number LIKE %s OR user_id IN (
            SELECT ID FROM {$wpdb->users} 
            WHERE display_name LIKE %s OR user_email LIKE %s
        ))";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    // Status filter
    if (!empty($status)) {
        $where[] = "status = %s";
        $params[] = $status;
    }
    
    // Method filter
    if (!empty($method)) {
        $where[] = "method = %s";
        $params[] = $method;
    }
    
    // Source filter (based on file path)
    if ($source === 'legacy') {
        $where[] = "certificate_file_path LIKE %s";
        $params[] = '%Old-certificates%';
    } elseif ($source === 'new') {
        $where[] = "(certificate_file_path LIKE %s AND certificate_file_path NOT LIKE %s)";
        $params[] = '%uploads/certificates%';
        $params[] = '%Old-certificates%';
    } elseif ($source === 'manual') {
        $where[] = "(certificate_file_path IS NULL OR certificate_file_path = '')";
    }
    
    $where_clause = implode(' AND ', $where);
    
    // Validate orderby column
    $allowed_orderby = array('certificate_number', 'method', 'level', 'sector', 'issue_date', 'expiry_date', 'status', 'user_id');
    if (!in_array($orderby, $allowed_orderby)) {
        $orderby = 'issue_date';
    }
    
    // Validate order direction
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get total count
    $count_query = "SELECT COUNT(*) FROM $table WHERE $where_clause";
    if (!empty($params)) {
        $count_query = $wpdb->prepare($count_query, $params);
    }
    $total = $wpdb->get_var($count_query);
    
    // Get paginated results with sorting
    $offset = ($paged - 1) * $per_page;
    $query = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d";
    $params[] = $per_page;
    $params[] = $offset;
    
    $certificates = $wpdb->get_results($wpdb->prepare($query, $params));
    
    return array(
        'certificates' => $certificates,
        'total' => $total
    );
}

/**
 * Get certificate statistics
 */
function get_certificate_statistics() {
    global $wpdb;
    $table = $wpdb->prefix . 'sgndt_final_certifications';
    
    $stats = array(
        'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
        'active' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE expiry_date >= CURDATE() AND status != 'expired'"),
        'expired' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE expiry_date < CURDATE() OR status = 'expired'"),
        'legacy_imports' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE certificate_file_path LIKE '%Old-certificates%'"),
        'with_pdfs' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE certificate_file_path IS NOT NULL AND certificate_file_path != ''")
    );
    
    return $stats;
}

/**
 * Get unique certificate methods
 */
function get_unique_certificate_methods() {
    global $wpdb;
    $table = $wpdb->prefix . 'sgndt_final_certifications';
    
    $methods = $wpdb->get_col("SELECT DISTINCT method FROM $table WHERE method IS NOT NULL AND method != '' ORDER BY method");
    
    return $methods;
}

/**
 * Get certificate file URL
 */
function get_certificate_file_url($file_path) {
    if (empty($file_path)) {
        return false;
    }
    
    // Check if it's an Old-certificates path
    if (strpos($file_path, 'Old-certificates') !== false) {
        $theme_dir = get_stylesheet_directory();
        $theme_uri = get_stylesheet_directory_uri();
        
        // Convert absolute path to URL
        if (file_exists($file_path)) {
            $relative_path = str_replace($theme_dir, '', $file_path);
            return $theme_uri . $relative_path;
        }
    }
    
    // Check if it's a uploads path
    $upload_dir = wp_upload_dir();
    if (file_exists($file_path)) {
        $relative_path = str_replace($upload_dir['basedir'], '', $file_path);
        return $upload_dir['baseurl'] . $relative_path;
    }
    
    // Check if it's already a URL
    if (filter_var($file_path, FILTER_VALIDATE_URL)) {
        return $file_path;
    }
    
    return false;
}

/**
 * Handle AJAX certificate search
 */
function handle_certificate_search() {
    check_ajax_referer('cert_management_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $result = get_certificates_with_filters($search, '', '', '', 1, 50);
    
    wp_send_json_success($result);
}

/**
 * Handle certificate export
 */
function handle_certificate_export() {
    check_ajax_referer('cert_management_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'sgndt_final_certifications';
    
    $certificates = $wpdb->get_results("SELECT * FROM $table ORDER BY issue_date DESC");
    
    // Create CSV
    $filename = 'certificates_export_' . date('Y-m-d_H-i-s') . '.csv';
    $filepath = wp_upload_dir()['basedir'] . '/certificate-exports/' . $filename;
    
    // Create directory if needed
    $export_dir = wp_upload_dir()['basedir'] . '/certificate-exports';
    if (!file_exists($export_dir)) {
        wp_mkdir_p($export_dir);
    }
    
    $fp = fopen($filepath, 'w');
    
    // Headers
    fputcsv($fp, array(
        'Certificate Number',
        'User ID',
        'User Name',
        'User Email',
        'Method',
        'Level',
        'Sector',
        'Scope',
        'Issue Date',
        'Expiry Date',
        'Status',
        'PDF Path'
    ));
    
    // Data
    foreach ($certificates as $cert) {
        $user = get_userdata($cert->user_id);
        fputcsv($fp, array(
            $cert->certificate_number,
            $cert->user_id,
            $user ? $user->display_name : '',
            $user ? $user->user_email : '',
            $cert->method,
            $cert->level,
            $cert->sector,
            $cert->scope,
            $cert->issue_date,
            $cert->expiry_date,
            $cert->status,
            $cert->certificate_file_path
        ));
    }
    
    fclose($fp);
    
    wp_send_json_success(array(
        'url' => wp_upload_dir()['baseurl'] . '/certificate-exports/' . $filename
    ));
}
