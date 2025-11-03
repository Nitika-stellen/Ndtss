<?php
if (!defined('ABSPATH')) { exit; }

require_once get_stylesheet_directory() . '/renew/renew-logger.php';

require_once get_stylesheet_directory() . '/includes/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options; // DOMPDF will be used

// Add admin menu
add_action('admin_menu', 'renew_add_admin_menu');

// Enqueue admin styles
add_action('admin_enqueue_scripts', 'renew_enqueue_admin_styles');

function renew_enqueue_admin_styles($hook) {
    // Only load on our admin pages
    if (strpos($hook, 'renew-recertification') !== false || strpos($hook, 'renewal-email-templates') !== false) {
        wp_enqueue_style(
            'renew-admin-css', 
            get_stylesheet_directory_uri() . '/renew/css/renew-admin.css', 
            array(), 
            '1.0.0'
        );
        
        // Enqueue SweetAlert2 for notifications
        wp_enqueue_script(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11',
            array(),
            '11.0.0',
            true
        );
        
        // Enqueue custom admin JavaScript
        wp_enqueue_script(
            'renew-admin-js',
            get_stylesheet_directory_uri() . '/renew/js/renew-admin.js',
            array('sweetalert2'),
            '1.0.0',
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('renew-admin-js', 'renewAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('renew_admin_nonce'),
            'base_url' => admin_url('admin.php?page=renew-recertification')
        ));
    }
}

function renew_add_admin_menu() {
    // Count pending renewals for notification
    $pending_count = renew_get_pending_count();
    $notification = $pending_count > 0 ? ' <span class="update-plugins count-' . $pending_count . '"><span class="plugin-count">' . $pending_count . '</span></span>' : '';
    
    // Add submenu under exam_center
    add_submenu_page(
        'edit.php?post_type=exam_center',
        'Renew/Recert By CPD',
        'Renew/Recert By CPD' . $notification,
        'manage_options',
        'renew-recertification',
        'renew_admin_page'
    );
    
    // Add submenu for email templates
    add_submenu_page(
        'edit.php?post_type=exam_center',
        'Renewal Email Templates',
        'Email Templates',
        'manage_options',
        'renewal-email-templates',
        'renew_render_email_template_settings_page'
    );
}

// Function to count pending renewal submissions
function renew_get_pending_count() {
    $args = array(
        'post_type' => 'cpd_submission',
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => '_status',
                'value' => 'pending',
                'compare' => '='
            )
        ),
        'fields' => 'ids'
    );
    
    $pending_posts = get_posts($args);
    return count($pending_posts);
}

function renew_admin_page() {
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    $submission_id = isset($_GET['submission_id']) ? intval($_GET['submission_id']) : 0;
    
    switch ($action) {
        case 'view':
            renew_admin_view_submission($submission_id);
            break;
        case 'approve':
            renew_admin_approve_submission($submission_id);
            break;
        case 'reject':
            renew_admin_reject_submission($submission_id);
            break;
        default:
            renew_admin_list_submissions();
            break;
    }
}

// Missing admin action functions for URL-based approval/rejection
function renew_admin_approve_submission($submission_id) {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }
    
    // Call the existing approval function
    renew_approve_submission($submission_id);
    
    // Redirect back to the admin page with success message
    $redirect_url = add_query_arg(array(
        'page' => 'renew-recertification',
        'message' => 'approved'
    ), admin_url('admin.php'));
    
    wp_redirect($redirect_url);
    exit;
}

function renew_admin_reject_submission($submission_id) {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }
    
    // For URL-based rejection, we'll use a default reason
    $reason = 'Submission rejected by administrator';
    
    // Call the existing rejection function
    renew_reject_submission($submission_id, $reason);
    
    // Redirect back to the admin page with success message
    $redirect_url = add_query_arg(array(
        'page' => 'renew-recertification',
        'message' => 'rejected'
    ), admin_url('admin.php'));
    
    wp_redirect($redirect_url);
    exit;
}

function renew_admin_list_submissions() {
    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $per_page = 20;
    $offset = ($paged - 1) * $per_page;
    
    // Get filter type
    $current_filter = isset($_GET['type_filter']) ? sanitize_text_field($_GET['type_filter']) : 'all';
    
    $args = array(
        'post_type' => 'cpd_submission',
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'offset' => $offset,
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    // Add meta query for filtering by type
    if ($current_filter !== 'all') {
        $method_value = ($current_filter === 'renewal') ? 'CPD' : 'RECERT';
        $args['meta_query'] = array(
            array(
                'key' => '_method',
                'value' => $method_value,
                'compare' => '='
            )
        );
    }
    
    $submissions = get_posts($args);
    $total_posts = wp_count_posts('cpd_submission')->publish;
    $total_pages = ceil($total_posts / $per_page);
    
    ?>
    <div class="wrap">
        <h1>Renew/Recertification Submissions</h1>
        
        <?php
        // Filter dropdown is already defined above
        ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get" style="display: inline-block;">
                    <input type="hidden" name="page" value="renew-recertification" />
                    <select name="type_filter" onchange="this.form.submit()">
                        <option value="all" <?php selected($current_filter, 'all'); ?>>All Submissions</option>
                        <option value="renewal" <?php selected($current_filter, 'renewal'); ?>>Renewals Only</option>
                        <option value="recertification" <?php selected($current_filter, 'recertification'); ?>>Recertifications Only</option>
                    </select>
                </form>
            </div>
        </div>
        
        <?php
        // Display success/error messages
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            switch ($message) {
                case 'approved':
                    echo '<div class="notice notice-success is-dismissible"><p>Submission approved successfully!</p></div>';
                    break;
                case 'rejected':
                    echo '<div class="notice notice-error is-dismissible"><p>Submission rejected successfully!</p></div>';
                    break;
                case 'certificate_generated':
                    echo '<div class="notice notice-success is-dismissible"><p>Certificate generated successfully!</p></div>';
                    break;
                case 'notes_updated':
                    echo '<div class="notice notice-success is-dismissible"><p>Admin notes updated successfully!</p></div>';
                    break;
            }
        }
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Method</th>
                    <th>Level</th>
                    <th>Sector</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissions)): ?>
                    <tr><td colspan="8">No submissions found.</td></tr>
                <?php else: ?>
                    <?php foreach ($submissions as $submission): ?>
                        <?php
                        $user_id = get_post_meta($submission->ID, '_user_id', true);
                        $name = get_post_meta($submission->ID, '_name', true);
                        $level = get_post_meta($submission->ID, '_level', true);
                        $sector = get_post_meta($submission->ID, '_sector', true);
                        $status = get_post_meta($submission->ID, '_status', true) ?: 'pending';
                        $years = get_post_meta($submission->ID, '_years', true) ?: array();
                        $cert_id = get_post_meta($submission->ID, '_cert_id', true);
                        $submission_method = get_post_meta($submission->ID, '_method', true) ?: 'CPD';
                        $submission_type = ($submission_method === 'RECERT') ? 'Recertification' : 'Renewal';

                        // Get original certificate method
                        $original_cert_method = 'N/A';
                        if ($cert_id && $user_id) {
                            global $wpdb;
                            $original_cert = $wpdb->get_row($wpdb->prepare(
                                "SELECT method FROM {$wpdb->prefix}sgndt_final_certifications
                                 WHERE user_id = %d AND final_certification_id = %d
                                 AND status = 'issued'
                                 ORDER BY issue_date DESC LIMIT 1",
                                $user_id, $cert_id
                            ));
                            $original_cert_method = $original_cert ? $original_cert->method : 'N/A';
                        }
                        ?>
                        <tr>
                            <td><?php echo $submission->ID; ?></td>
                            <td><?php echo esc_html($name); ?></td>
                            <td>
                                <?php echo esc_html($original_cert_method); ?>
                                <br><small style="color: <?php echo $submission_method === 'RECERT' ? '#d63384' : '#0d6efd'; ?>;">
                                    <strong><?php echo esc_html($submission_type); ?></strong>
                                </small>
                            </td>
                            <td><?php echo esc_html($level); ?></td>
                            <td><?php echo esc_html($sector); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($submission->post_date)); ?></td>
                            <td>
                                <span class="status-<?php echo esc_attr($status); ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=renew-recertification&action=view&submission_id=' . $submission->ID); ?>" class="button button-small">View</a>
                                <?php if ($status === 'pending'): ?>
                                    <a href="#" onclick="confirmApproval(<?php echo $submission->ID; ?>)" class="button button-small button-primary">Approve</a>
                                    <a href="#" onclick="confirmRejection(<?php echo $submission->ID; ?>)" class="button button-small">Reject</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $paged
                    ));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
    .status-pending { color: #f56e28; font-weight: bold; }
    .status-approved { color: #46b450; font-weight: bold; }
    .status-rejected { color: #dc3232; font-weight: bold; }
    
    .certificate-number-field:disabled,
    .certificate-number-field[readonly] {
        background-color: #f0f0f0 !important;
        cursor: not-allowed !important;
        color: #666 !important;
    }

    /* File thumbnail styling */
    .pdf-thumbnail, .file-thumbnail {
        border-radius: 4px;
        overflow: hidden;
    }

    .file-preview img {
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    /* Required field indicators */
    .form-table th .required {
        border-color: #dc3545 !important;
    }
    
    /* File display styling */
    .uploaded-files-container { margin: 20px 0; }
    .file-type-section { margin-bottom: 30px; border: 1px solid #ddd; border-radius: 4px; padding: 15px; }
    .file-type-section h3 { margin-top: 0; color: #0073aa; font-size: 16px; }
    .files-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
    .file-card { border: 1px solid #ddd; border-radius: 4px; overflow: hidden; background: #fff; transition: box-shadow 0.2s; }
    .file-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .file-header { padding: 12px; display: flex; align-items: center; gap: 10px; background: #f9f9f9; }
    .file-icon { font-size: 24px; color: #666; }
    .file-info { flex: 1; min-width: 0; }
    .file-name { font-weight: 600; color: #0073aa; word-wrap: break-word; }
    .file-meta { font-size: 12px; color: #666; margin-top: 4px; }
    .file-preview { padding: 0; text-align: center; max-height: 200px; overflow: hidden; }
    .file-preview img { max-width: 100%; height: auto; display: block; }
    .file-actions { padding: 12px; display: flex; gap: 8px; justify-content: center; background: #f9f9f9;margin-top: 10px;}
    .no-files-uploaded { text-align: center; padding: 40px; color: #666; }
    .no-files-uploaded .dashicons { font-size: 48px; margin-bottom: 10px; display: block; }
    
    /* Certificate info styling */
    .certificate-info { background: #e8f5e8; padding: 15px; border: 1px solid #bbe1bb; border-radius: 4px; margin: 15px 0; }
    .certificate-info h3 { margin-top: 0; color: #2e7d2e; }
    .certificate-download-section .button { display: inline-flex ; gap: 5px; align-items: center; }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // CPD categories with max points
        var maxPoints = {
            'A1': 95, 'A2': 15, 'A3': 25, 'A4': 75, 'A5': 60,
            '6': 10, '7': 15, '8': 5, '9': 40, '10': 20, '11': 40
        };
        
        // Update totals when inputs change
        $('.cpd-category-input').on('input', function() {
            updateCPDTotals();
            validateInput($(this));
        });
        
        function validateInput($input) {
            var value = parseFloat($input.val()) || 0;
            var maxValue = parseFloat($input.attr('data-max')) || 0;
            var $warning = $('.validation-warning');
            
            if (value > maxValue) {
                $input.addClass('error');
                $warning.show();
            } else {
                $input.removeClass('error');
                
                // Check if any other inputs have errors
                if ($('.cpd-category-input.error').length === 0) {
                    $warning.hide();
                }
            }
        }
        
        function updateCPDTotals() {
            var grandTotal = 0;
            
            // Update category totals
            Object.keys(maxPoints).forEach(function(category) {
                var categoryTotal = 0;
                $('[data-category="' + category + '"]').each(function() {
                    var value = parseFloat($(this).val()) || 0;
                    categoryTotal += value;
                });
                $('.category-total-' + category).text(categoryTotal.toFixed(1));
                grandTotal += categoryTotal;
            });
            
            // Update year totals
            for (var year = 1; year <= 5; year++) {
                var yearTotal = 0;
                $('[data-year="' + year + '"]').each(function() {
                    var value = parseFloat($(this).val()) || 0;
                    yearTotal += value;
                });
                $('.year-total-' + year).text(yearTotal.toFixed(1));
            }
            
            // Update grand total
            $('.grand-total, .grand-total-display').text(grandTotal.toFixed(1));
            
            // Update status indicator
            var $statusIndicator = $('.status-indicator');
            if (grandTotal >= 150) {
                $statusIndicator.removeClass('insufficient').addClass('sufficient').text('Sufficient');
            } else {
                var needed = (150 - grandTotal).toFixed(1);
                $statusIndicator.removeClass('sufficient').addClass('insufficient').text('Insufficient (' + needed + ' more needed)');
            }
        }
        
        // Initial calculation
        updateCPDTotals();

    });
    </script>
    <?php
}

function renew_admin_view_submission($submission_id) {
    $submission = get_post($submission_id);
    if (!$submission || $submission->post_type !== 'cpd_submission') {
        wp_die('Submission not found.');
    }
    
    $user_id = get_post_meta($submission_id, '_user_id', true);
    $name = get_post_meta($submission_id, '_name', true);
    $dob = get_post_meta($submission_id, '_dob', true);
    $level = get_post_meta($submission_id, '_level', true);
    $sector = get_post_meta($submission_id, '_sector', true);
    $cert_number = get_post_meta($submission_id, '_cert_number', true);
    $years = get_post_meta($submission_id, '_years', true) ?: array();
    $uploads = get_post_meta($submission_id, '_uploads', true) ?: array();
    $status = get_post_meta($submission_id, '_status', true) ?: 'pending';
    $admin_notes = get_post_meta($submission_id, '_admin_notes', true);
    $total_cpd_points = get_post_meta($submission_id, '_total_cpd_points', true);
    $renewal_date = get_post_meta($submission_id, '_renewal_date', true);
    $certificate_number = get_post_meta($submission_id, '_certificate_number', true);
    $cert_id = get_post_meta($submission->ID, '_cert_id', true);

    // Get original certificate method
    $original_cert_method = 'N/A';
    if ($cert_id && $user_id) {
        global $wpdb;
        $original_cert = $wpdb->get_row($wpdb->prepare(
            "SELECT method FROM {$wpdb->prefix}sgndt_final_certifications
             WHERE user_id = %d AND final_certification_id = %d
             AND status = 'issued'
             ORDER BY issue_date DESC LIMIT 1",
            $user_id, $cert_id
        ));
        $original_cert_method = $original_cert ? $original_cert->method : 'N/A';
    }
    
    
    
    $user = get_userdata($user_id);
    ?>
    <div class="wrap">
        <h1>Renew/Recertification Details</h1>
        
        <?php if (isset($_GET['updated']) && $_GET['updated'] == '1'): ?>
            <div class="notice notice-success"><p>Submission updated successfully!</p></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['cpd_updated']) && $_GET['cpd_updated'] == '1'): ?>
            <div class="notice notice-success"><p>CPD points updated successfully!</p></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['certificate_generated']) && $_GET['certificate_generated'] == '1'): ?>
            <div class="notice notice-success"><p>Certificate generated and emailed to user successfully!</p></div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Certificate Generated!',
                            text: 'The certificate has been generated and sent to the user via email.',
                            icon: 'success',
                            confirmButtonText: 'Great!'
                        });
                    }
                });
            </script>
            <?php if (isset($_GET['auto_download'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Auto-download the certificate after a short delay
                    setTimeout(function() {
                        var downloadUrl = '<?php echo esc_js($_GET['auto_download']); ?>';
                        var link = document.createElement('a');
                        link.href = downloadUrl;
                        link.download = 'certificate_<?php echo esc_js($certificate_number); ?>.pdf';
                        link.target = '_blank';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    }, 1000); // 1 second delay to let the page load
                });
            </script>
            <?php endif; ?>
        <?php endif; ?>
        
        <p><a href="<?php echo admin_url('admin.php?page=renew-recertification'); ?>" class="button">&larr; Back to List</a></p>
        
        <div class="renew-recertification-details">
            <h2>Basic Information</h2>
            <form id="edit-submission-form" method="post">
                <?php wp_nonce_field('renew_admin_action', 'renew_nonce'); ?>
                <input type="hidden" name="action" value="update_submission">
                <input type="hidden" name="submission_id" value="<?php echo $submission_id; ?>">
                
                <table class="form-table">
                    <tr>
                        <th>Submission ID</th>
                        <td><?php echo $submission_id; ?></td>
                    </tr>
                    <tr>
                        <th>User</th>
                        <td><?php echo $user ? esc_html($user->display_name) : 'Unknown'; ?> (ID: <?php echo $user_id; ?>)</td>
                    </tr>
                    <tr>
                        <th>Name</th>
                        <td><input type="text" name="name" value="<?php echo esc_attr($name); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th>Method</th>
                        <td><?php echo esc_html( $original_cert_method ? $original_cert_method : 'N/A'); ?></td>
                    </tr>
                    <tr style="display: none;">
                        <th>Date of Birth</th>
                        <td><input type="date" name="dob" value="<?php echo esc_attr($dob); ?>" class="regular-text" /></td>
                    </tr>
                    <?php if ($cert_number): ?>
                    <tr>
                        <th>Original Certificate Number</th>
                        <td><?php echo esc_html($cert_number); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Level</th>
                        <td><input type="text" name="level" value="<?php echo esc_attr($level); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th>Sector</th>
                        <td><input type="text" name="sector" value="<?php echo esc_attr($sector); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <select name="status" class="regular-text">
                                <option value="pending" <?php selected($status, 'pending'); ?>>Pending</option>
                                <option value="approved" <?php selected($status, 'approved'); ?>>Approved</option>
                                <option value="rejected" <?php selected($status, 'rejected'); ?>>Rejected</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Total CPD Points</th>
                        <td><input type="number" name="total_cpd_points" value="<?php echo esc_attr($total_cpd_points); ?>" step="0.1" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th>Renewal Certificate Number</th>
                        <td>
                            <input type="text" name="certificate_number" value="<?php echo esc_attr($certificate_number); ?>" class="regular-text certificate-number-field"
                                   <?php echo $certificate_number ? 'readonly style="background-color: #f0f0f0; cursor: not-allowed;"' : ''; ?> />
                            <?php if (!$certificate_number && $status === 'approved'): ?>
                                <?php 
                                // Auto-generate certificate number preview
                                global $wpdb;
                                $original_cert = $wpdb->get_var($wpdb->prepare(
                                    "SELECT certificate_number FROM {$wpdb->prefix}sgndt_final_certifications 
                                     WHERE user_id = %d AND level = %s AND sector = %s 
                                     AND status = 'issued' 
                                     ORDER BY issue_date DESC LIMIT 1",
                                    $user_id, $level, $sector
                                ));
                                if ($original_cert): ?>
                                    <?php $method_for_suffix = strtoupper(get_post_meta($submission_id, '_method', true)); $sfx = ($method_for_suffix === 'RECERT') ? '-02' : '-01'; ?>
                                    <br><small><strong>Suggested:</strong> <?php echo esc_html($original_cert . $sfx); ?> (Based on original certificate: <?php echo esc_html($original_cert); ?>)</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <br><small><em>This field is auto-filled after certificate generation and cannot be modified.</em></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Issue Date</th>
                        <td><?php echo esc_html($renewal_date ? date('F j, Y', strtotime($renewal_date)) : 'Auto-calculated on generation'); ?></td>
                    </tr>
                    <tr>
                        <th>Expiry Date</th>
                        <td><?php echo esc_html($renewal_date ? date('F j, Y', strtotime($renewal_date . ' +5 years')) : 'Issue Date + 5 years'); ?></td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="update_submission" class="button button-primary" value="Update Submission" />
                </p>
            </form>
            
            <h2>CPD Points by Category (Editable)</h2>
            <form id="edit-cpd-points-form" method="post">
                <?php wp_nonce_field('renew_admin_action', 'renew_nonce'); ?>
                <input type="hidden" name="action" value="update_cpd_points">
                <input type="hidden" name="submission_id" value="<?php echo $submission_id; ?>">
                
                <?php 
                // CPD Categories with their maximum points
                $cpd_categories = array(
                    'A1' => array('title' => 'Performing NDT Activity', 'max' => 95),
                    'A2' => array('title' => 'Theoretical Training', 'max' => 15),
                    'A3' => array('title' => 'Practical Training', 'max' => 25),
                    'A4' => array('title' => 'Delivery of Training', 'max' => 75),
                    'A5' => array('title' => 'Research Activities', 'max' => 60),
                    '6' => array('title' => 'Technical Seminar/Paper', 'max' => 10),
                    '7' => array('title' => 'Presenting Technical Seminar', 'max' => 15),
                    '8' => array('title' => 'Society Membership', 'max' => 5),
                    '9' => array('title' => 'Technical Oversight', 'max' => 40),
                    '10' => array('title' => 'Committee Participation', 'max' => 20),
                    '11' => array('title' => 'Certification Body Role', 'max' => 40)
                );
                ?>
                
                <div class="cpd-admin-table-wrapper">
                    <table class="wp-list-table widefat cpd-admin-table">
                        <thead>
                            <tr>
                                <th rowspan="2">Category</th>
                                <th rowspan="2">Description</th>
                                <th rowspan="2">Max Points</th>
                                <th colspan="5">Years</th>
                                <th rowspan="2">Category Total</th>
                            </tr>
                            <tr>
                                <?php for ($y = 1; $y <= 5; $y++): ?>
                                    <th>Year <?php echo $y; ?><br><small>(<?php echo date('Y') - (5 - $y); ?>)</small></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grand_total = 0;
                            foreach ($cpd_categories as $code => $category): 
                                $category_total = 0;
                            ?>
                                <tr data-category="<?php echo $code; ?>">
                                    <td><strong><?php echo $code; ?></strong></td>
                                    <td><?php echo $category['title']; ?></td>
                                    <td><span class="max-points"><?php echo $category['max']; ?></span></td>
                                    <?php for ($y = 1; $y <= 5; $y++): ?>
                                        <?php 
                                        $value = isset($years[$y][$code]) ? floatval($years[$y][$code]) : 0;
                                        $category_total += $value;
                                        ?>
                                        <td>
                                            <input 
                                                type="number" 
                                                name="years[<?php echo $y; ?>][<?php echo $code; ?>]" 
                                                value="<?php echo esc_attr($value); ?>" 
                                                step="0.1" 
                                                min="0" 
                                                max="<?php echo $category['max']; ?>"
                                                class="small-text cpd-category-input" 
                                                data-max="<?php echo $category['max']; ?>"
                                                data-year="<?php echo $y; ?>"
                                                data-category="<?php echo $code; ?>"
                                            />
                                        </td>
                                    <?php endfor; ?>
                                    <td><strong class="category-total-<?php echo $code; ?>"><?php echo number_format($category_total, 1); ?></strong></td>
                                </tr>
                            <?php 
                                $grand_total += $category_total;
                            endforeach; 
                            ?>
                            <tr class="total-row">
                                <td colspan="3"><strong>Total Points</strong></td>
                                <?php for ($y = 1; $y <= 5; $y++): ?>
                                    <?php 
                                    $year_total = 0;
                                    foreach ($cpd_categories as $code => $category) {
                                        $year_total += isset($years[$y][$code]) ? floatval($years[$y][$code]) : 0;
                                    }
                                    ?>
                                    <td><strong class="year-total-<?php echo $y; ?>"><?php echo number_format($year_total, 1); ?></strong></td>
                                <?php endfor; ?>
                                <td><strong class="grand-total"><?php echo number_format($grand_total, 1); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="cpd-validation-summary">
                    <div class="validation-item">
                        <span class="label">Total CPD Points:</span>
                        <span class="value grand-total-display"><?php echo number_format($grand_total, 1); ?></span>
                    </div>
                    <div class="validation-item">
                        <span class="label">Minimum Required:</span>
                        <span class="value">150.0</span>
                    </div>
                    <div class="validation-item">
                        <span class="label">Status:</span>
                        <span class="value status-indicator <?php echo $grand_total >= 150 ? 'sufficient' : 'insufficient'; ?>">
                            <?php echo $grand_total >= 150 ? 'Sufficient' : 'Insufficient (' . number_format(150 - $grand_total, 1) . ' more needed)'; ?>
                        </span>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="update_cpd_points" class="button button-primary" value="Update CPD Points" />
                    <span class="validation-warning" style="display:none; margin-left: 10px; color: #dc3545;">
                        <i class="dashicons dashicons-warning"></i> Some values exceed maximum limits
                    </span>
                </p>
            </form>
            
            <h2>Uploaded Files</h2>
            <?php if (!empty($uploads)): ?>
                <?php 
                $file_type_labels = array(
                    'cpd_files' => array('title' => 'CPD Proof Documents', 'icon' => 'dashicons-media-document'),
                    'previous_certificates' => array('title' => 'Previous Certificates', 'icon' => 'dashicons-awards'),
                    'support_docs' => array('title' => 'Additional Supporting Documents', 'icon' => 'dashicons-paperclip')
                );
                ?>
                <div class="uploaded-files-container">
                    <?php foreach ($uploads as $type => $files): ?>
                        <?php if (is_array($files) && !empty($files)): ?>
                            <?php $type_info = isset($file_type_labels[$type]) ? $file_type_labels[$type] : array('title' => ucfirst(str_replace('_', ' ', $type)), 'icon' => 'dashicons-media-default'); ?>
                            <div class="file-type-section">
                                <h3><i class="dashicons <?php echo $type_info['icon']; ?>"></i> <?php echo $type_info['title']; ?> (<?php echo count($files); ?> files)</h3>
                                <div class="files-grid">
                                    <?php foreach ($files as $index => $file): ?>
                                        <div class="file-card">
                                            <div class="file-header">
                                                <?php 
                                                $file_ext = strtolower(pathinfo($file['file'], PATHINFO_EXTENSION));
                                                $is_image = in_array($file_ext, array('jpg', 'jpeg', 'png', 'gif'));
                                                $is_pdf = $file_ext === 'pdf';
                                                $icon_class = 'dashicons-media-default';
                                                if ($is_image) $icon_class = 'dashicons-format-image';
                                                elseif ($is_pdf) $icon_class = 'dashicons-pdf';
                                                elseif (in_array($file_ext, array('doc', 'docx'))) $icon_class = 'dashicons-media-document';
                                                ?>
                                                <i class="dashicons <?php echo $icon_class; ?> file-icon"></i>
                                                <div class="file-info">
                                                    <div class="file-name" title="<?php echo esc_attr(isset($file['original_name']) ? $file['original_name'] : basename($file['file'])); ?>">
                                                        <?php echo esc_html(isset($file['original_name']) ? $file['original_name'] : basename($file['file'])); ?>
                                                    </div>
                                                    <div class="file-meta">
                                                        <?php if (isset($file['file_size'])): ?>
                                                            <span class="file-size"><?php echo size_format($file['file_size']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if (isset($file['upload_date'])): ?>
                                                            <span class="upload-date"><?php echo date('M j, Y', strtotime($file['upload_date'])); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($is_image): ?>
                                                <div class="file-preview">
                                                    <img src="<?php echo esc_url($file['url']); ?>" alt="Preview"
                                                         style="max-width: 100%; height: auto; max-height: 200px; object-fit: contain;" />
                                                </div>
                                            <?php elseif ($is_pdf): ?>
                                                <div class="pdf-thumbnail" style="display: flex; align-items: center; justify-content: center; height: 200px; background: #effaff; border: 1px solid #e1e5e9;border-radius:4px; margin-bottom: 20px" >
                                                    <div style="text-align: center; color: #666;">
                                                        <i class="dashicons dashicons-pdf" style="font-size: 48px; margin-bottom: 10px;"></i>
                                                        <p style="margin: 0; font-size: 12px;">PDF Document</p>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="file-thumbnail" style="display: flex; align-items: center; justify-content: center; height: 200px; background: #effaff; border: 1px solid #e1e5e9; margin-bottom: 20px;border-radius:4px;">
                                                    <div style="text-align: center; color: #666;">
                                                        <i class="dashicons <?php echo $icon_class; ?>" style="font-size: 48px; margin-bottom: 10px;"></i>
                                                        <p style="margin: 0; font-size: 12px;"><?php echo strtoupper($file_ext); ?> File</p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="file-actions">
                                                <a href="<?php echo esc_url($file['url']); ?>" target="_blank" class="button button-small button-primary">
                                                    <i class="dashicons dashicons-visibility"></i> View
                                                </a>
                                                <a href="<?php echo esc_url($file['url']); ?>" download class="button button-small">
                                                    <i class="dashicons dashicons-download"></i> Download
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-files-uploaded">
                    <i class="dashicons dashicons-media-default"></i>
                    <p>No files uploaded with this submission.</p>
                </div>
            <?php endif; ?>
            
            <h2>Admin Actions</h2>
            <div class="admin-actions">
                <?php if ($status === 'pending'): ?>
                    <button type="button" onclick="confirmApproval(<?php echo $submission_id; ?>)" class="button button-primary">Approve Submission</button>
                    <button type="button" onclick="confirmRejection(<?php echo $submission_id; ?>)" class="button" style="margin-left: 10px;">Reject Submission</button>
                <?php endif; ?>
               
                
                <?php if ($status === 'approved' && !$certificate_number): ?>
                    <form method="post" style="display: inline; margin-left: 10px;" id="certificateForm<?php echo $submission_id; ?>">
                        <?php wp_nonce_field('renew_admin_action', 'renew_nonce'); ?>
                        <input type="hidden" name="action" value="renew_certificate">
                        <input type="hidden" name="submission_id" value="<?php echo $submission_id; ?>">
                        <?php 
                        $submission_method_admin = get_post_meta($submission_id, '_method', true);
                        $generate_label = (strtoupper($submission_method_admin) === 'RECERT') ? 'Generate ReCertificate' : 'Generate Renewed Certificate';
                        ?>
                        <button type="button" onclick="confirmCertificateGeneration(<?php echo $submission_id; ?>)" class="button button-secondary" data-method="<?php echo esc_attr(strtoupper($submission_method_admin)); ?>"><?php echo esc_html($generate_label); ?></button>
                    </form>
                <?php endif; ?>
                
                <?php if ($certificate_number): ?>
                    <div class="certificate-info">
                        <?php $is_recert_view = (strtoupper(get_post_meta($submission_id, '_method', true)) === 'RECERT'); ?>
                        <h3><?php echo $is_recert_view ? 'Recertified Certificate Information' : 'Renewed Certificate Information'; ?></h3>
                        <p><strong>Certificate Number:</strong> <?php echo esc_html($certificate_number); ?></p>
                        <p><strong>Renewal Date:</strong> <?php echo esc_html($renewal_date ? date('F j, Y', strtotime($renewal_date)) : 'Not set'); ?></p>
                        <?php
                        // Calculate expiry date from renewal date if not already stored
                        $stored_expiry_date = get_post_meta($submission_id, '_expiry_date', true);
                        if (empty($stored_expiry_date) && !empty($renewal_date)) {
                            $calculated_expiry_date = date('F j, Y', strtotime($renewal_date . ' +5 years'));
                        } else {
                            $calculated_expiry_date = $stored_expiry_date ? date('F j, Y', strtotime($stored_expiry_date)) : 'Not set';
                        }
                        ?>
                        <p><strong>Expiry Date:</strong> <?php echo esc_html($calculated_expiry_date); ?></p>
                        <p><strong>Status:</strong> <span class="status-approved"><?php echo $is_recert_view ? 'Certificate Recertified' : 'Certificate Renewed via CPD'; ?></span></p>
                        <p><strong>Original Certificate:</strong> <?php 
                            global $wpdb;
                            $original_cert = $wpdb->get_var($wpdb->prepare(
                                "SELECT certificate_number FROM {$wpdb->prefix}sgndt_final_certifications 
                                 WHERE user_id = %d AND level = %s AND sector = %s AND status = 'renewed' 
                                 ORDER BY issue_date DESC LIMIT 1",
                                $user_id, $level, $sector
                            ));
                            echo esc_html($original_cert ? $original_cert : 'N/A');
                        ?></p>
                        <p><strong>Renewal Method:</strong> CPD Points (<?php echo esc_html($total_cpd_points); ?> points)</p>

                        <!-- Certificate Download Section -->
                        <div class="certificate-download-section" style="margin-top: 20px; padding: 15px; background: #d1f1d1; border: 1px solid #97d997; border-radius: 4px;">
                            <h4 style="margin-top: 0; color: #2e7d2e;">📄 <?php echo $is_recert_view ? 'Recertified Certificate' : 'Renewed Certificate'; ?></h4>
                            <p><strong>Certificate Number:</strong> <?php echo esc_html($certificate_number); ?></p>
                            <p><strong>Renewal Date:</strong> <?php echo esc_html($renewal_date ? date('F j, Y', strtotime($renewal_date)) : 'Not set'); ?></p>
                            <?php
                            // Calculate expiry date consistently
                            $stored_expiry_date = get_post_meta($submission_id, '_expiry_date', true);
                            if (empty($stored_expiry_date) && !empty($renewal_date)) {
                                $display_expiry_date = date('F j, Y', strtotime($renewal_date . ' +5 years'));
                            } else {
                                $display_expiry_date = $stored_expiry_date ? date('F j, Y', strtotime($stored_expiry_date)) : 'Not set';
                            }
                            ?>
                            <p><strong>Expiry Date:</strong> <?php echo esc_html($display_expiry_date); ?></p>
                            <p><strong>View Certificate:</strong>
                            <a href="<?php
                                // Get the certificate URL from the database instead of using undefined $pdf_result
                                global $wpdb;
                                $cert_query = $wpdb->prepare(
                                    "SELECT certificate_link FROM {$wpdb->prefix}sgndt_final_certifications
                                     WHERE certificate_number = %s AND user_id = %d
                                     ORDER BY issue_date DESC LIMIT 1",
                                    $certificate_number, $user_id
                                );
                                $cert_result = $wpdb->get_var($cert_query);
                                echo esc_url($cert_result ?: '#');
                            ?>"
                               target="_blank"
                               class="button button-secondary"
                               style="margin-left: 10px;">
                                <i class="dashicons dashicons-visibility"></i> View Certificate
                            </a>
                        </p>
                        <p><strong>Download Certificate:</strong>
                            <a href="<?php
                                // Get the certificate URL from the database
                                global $wpdb;
                                $cert_query = $wpdb->prepare(
                                    "SELECT certificate_link FROM {$wpdb->prefix}sgndt_final_certifications
                                     WHERE certificate_number = %s AND user_id = %d
                                     ORDER BY issue_date DESC LIMIT 1",
                                    $certificate_number, $user_id
                                );
                                $cert_result = $wpdb->get_var($cert_query);
                                echo esc_url($cert_result ?: '#');
                            ?>"
                               download="<?php echo 'Certificate_' . $certificate_number . '.pdf'; ?>"
                               class="button button-primary"
                               style="margin-left: 10px;">
                                <i class="dashicons dashicons-download"></i> Download PDF
                            </a>
                        </p>
                            <p style="font-size: 12px; color: #666; margin-top: 10px;">
                                <em>This certificate is active and valid for 5 years from the renewal date.</em>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <h3>Admin Notes</h3>
            <form method="post">
                <?php wp_nonce_field('renew_admin_action', 'renew_nonce'); ?>
                <input type="hidden" name="action" value="update_notes">
                <input type="hidden" name="submission_id" value="<?php echo $submission_id; ?>">
                <textarea name="admin_notes" rows="4" cols="50"><?php echo esc_textarea($admin_notes); ?></textarea>
                <button type="submit" class="button">Update Notes</button>
            </form>
        </div>
    </div>
    <?php
}

// Handle admin actions
add_action('admin_init', 'renew_handle_admin_actions');

function renew_handle_admin_actions() {
    if (!isset($_POST['renew_nonce']) || !wp_verify_nonce($_POST['renew_nonce'], 'renew_admin_action')) {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }
    
    $action = sanitize_text_field($_POST['action']);
    $submission_id = intval($_POST['submission_id']);
    
    switch ($action) {
        case 'approve':
            renew_approve_submission($submission_id);
            break;
        case 'reject':
            $reason = sanitize_textarea_field($_POST['reject_reason']);
            renew_reject_submission($submission_id, $reason);
            break;
        case 'update_notes':
            $notes = sanitize_textarea_field($_POST['admin_notes']);
            update_post_meta($submission_id, '_admin_notes', $notes);
            wp_redirect(admin_url('admin.php?page=renew-recertification&action=view&submission_id=' . $submission_id));
            exit;
        case 'update_submission':
            renew_update_submission($submission_id);
            break;
        case 'update_cpd_points':
            renew_update_cpd_points($submission_id);
            break;
        case 'renew_certificate':
            renew_generate_certificate($submission_id);
            break;
    }
}

function renew_approve_submission($submission_id) {
    global $wpdb;
    
    $user_id = get_post_meta($submission_id, '_user_id', true);
    $level = get_post_meta($submission_id, '_level', true);
    $sector = get_post_meta($submission_id, '_sector', true);
    $cert_number = get_post_meta($submission_id, '_cert_number', true);
   
    
    update_post_meta($submission_id, '_status', 'approved');
    update_post_meta($submission_id, '_approved_by', get_current_user_id());
    update_post_meta($submission_id, '_approved_date', current_time('mysql'));
    
    // Update renewal status in the original certificate row
    $original_cert_id = get_post_meta($submission_id, '_original_cert_id', true);
    if (!empty($original_cert_id)) {
        $wpdb->update(
            $wpdb->prefix . 'sgndt_final_certifications',
            array(
                'renewal_status' => 'approved',
                'renewal_approved_date' => current_time('mysql'),
                'renewal_approved_by' => get_current_user_id()
            ),
            array('final_certification_id' => $original_cert_id),
            array('%s', '%s', '%d'),
            array('%d')
        );
        
        renew_log_info('Updated original certificate renewal status to approved', array(
            'cert_id' => $original_cert_id,
            'submission_id' => $submission_id,
            'approved_by' => get_current_user_id()
        ));
    }
    
    // Update user profile renewal status
    if ($user_id) {
        $renewal_status_key = 'renewal_status_' . strtolower($level) . '_' . strtolower(str_replace(' ', '_', $sector));
        update_user_meta($user_id, $renewal_status_key, 'approved');
        update_user_meta($user_id, $renewal_status_key . '_date', current_time('mysql'));
        update_user_meta($user_id, $renewal_status_key . '_submission_id', $submission_id);
        
        // Update simple submission tracking
        if (!empty($cert_number)) {
            $submission_key = 'renewal_submission_' . $cert_number;
            update_user_meta($user_id, $submission_key, 'approved');
            update_user_meta($user_id, $submission_key . '_approval_date', current_time('mysql'));
            
            // Also update the certificate status using the same system as Form 31
            $cert_status_key = 'cert_status_' . $cert_number;
            update_user_meta($user_id, $cert_status_key, 'approved');
            update_user_meta($user_id, $cert_status_key . '_date', current_time('mysql'));
            update_user_meta($user_id, $cert_status_key . '_approved_by', get_current_user_id());
            update_user_meta($user_id, $cert_status_key . '_submission_method', 'cpd_form');
            
            renew_log_info('CPD approval: Certificate status updated', array(
                'user_id' => $user_id,
                'cert_number' => $cert_number,
                'status_key' => $cert_status_key
            ));
        }
    }
    
    renew_log_info('Renewal/Recertification submission approved', array(
        'submission_id' => $submission_id,
        'user_id' => $user_id,
        'level' => $level,
        'sector' => $sector,
        'approved_by' => get_current_user_id()
    ));
    
    wp_redirect(admin_url('admin.php?page=renew-recertification&action=view&submission_id=' . $submission_id));
    exit;
}

function renew_reject_submission($submission_id, $reason) {
    update_post_meta($submission_id, '_status', 'rejected');
    update_post_meta($submission_id, '_rejected_by', get_current_user_id());
    update_post_meta($submission_id, '_rejected_date', current_time('mysql'));
    update_post_meta($submission_id, '_reject_reason', $reason);
    
    // Update renewal status in the original certificate row
    $original_cert_id = get_post_meta($submission_id, '_original_cert_id', true);
    $user_id = get_post_meta($submission_id, '_user_id', true);
    $cert_number = get_post_meta($submission_id, '_cert_number', true);
    
    if (!empty($original_cert_id)) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'sgndt_final_certifications',
            array(
                'renewal_status' => NULL, // Reset renewal status so user can try again
                'renewal_method' => NULL,
                'renewal_submitted_date' => NULL,
                'renewal_approved_date' => NULL,
                'renewal_rejected_date' => current_time('mysql'),
                'renewal_rejected_by' => get_current_user_id(),
                'renewal_rejection_reason' => $reason,
                'renewal_submission_id' => NULL
            ),
            array('final_certification_id' => $original_cert_id),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d'),
            array('%d')
        );
        
        renew_log_info('Updated original certificate renewal status to rejected', array(
            'cert_id' => $original_cert_id,
            'submission_id' => $submission_id,
            'rejected_by' => get_current_user_id(),
            'reason' => $reason
        ));
    }
    
    // Also update the certificate status in the user profile system for consistency
    if ($user_id && $cert_number) {
        // Update certificate status using the same system as Form 31
        $cert_status_key = 'cert_status_' . $cert_number;
        update_user_meta($user_id, $cert_status_key, 'rejected');
        update_user_meta($user_id, $cert_status_key . '_date', current_time('mysql'));
        update_user_meta($user_id, $cert_status_key . '_rejected_by', get_current_user_id());
        update_user_meta($user_id, $cert_status_key . '_rejection_reason', $reason);
        update_user_meta($user_id, $cert_status_key . '_submission_method', 'cpd_form');
        
        renew_log_info('CPD rejection: Certificate status updated', array(
            'user_id' => $user_id,
            'cert_number' => $cert_number,
            'status_key' => $cert_status_key
        ));
    }
    
    renew_log_info('Renewal/Recertification submission rejected', array(
        'submission_id' => $submission_id,
        'rejected_by' => get_current_user_id(),
        'reason' => $reason
    ));
    
    wp_redirect(admin_url('admin.php?page=renew-recertification&action=view&submission_id=' . $submission_id));
    exit;
}

function renew_update_submission($submission_id) {
    $name = sanitize_text_field($_POST['name']);
    $dob = sanitize_text_field($_POST['dob']);
    $level = sanitize_text_field($_POST['level']);
    $sector = sanitize_text_field($_POST['sector']);
    $status = sanitize_text_field($_POST['status']);
    $total_cpd_points = floatval($_POST['total_cpd_points']);
    $certificate_number = sanitize_text_field($_POST['certificate_number']);
    
    // Update post meta
    update_post_meta($submission_id, '_name', $name);
    update_post_meta($submission_id, '_dob', $dob);
    update_post_meta($submission_id, '_level', $level);
    update_post_meta($submission_id, '_sector', $sector);
    update_post_meta($submission_id, '_status', $status);
    update_post_meta($submission_id, '_total_cpd_points', $total_cpd_points);
    update_post_meta($submission_id, '_certificate_number', $certificate_number);
    
    // Update post title
    wp_update_post(array(
        'ID' => $submission_id,
        'post_title' => $name . ' - ' . $level . ' - ' . current_time('Y-m-d H:i')
    ));
    
    renew_log_info('Submission updated by admin', array(
        'submission_id' => $submission_id,
        'updated_by' => get_current_user_id(),
        'changes' => array('name' => $name, 'level' => $level, 'sector' => $sector, 'status' => $status)
    ));
    
    wp_redirect(admin_url('admin.php?page=renew-recertification&action=view&submission_id=' . $submission_id . '&updated=1'));
    exit;
}

function renew_update_cpd_points($submission_id) {
    $years = isset($_POST['years']) && is_array($_POST['years']) ? $_POST['years'] : array();
    
    // CPD Categories with their maximum points for validation
    $cpd_categories = array(
        'A1' => 95,   // Performing NDT Activity
        'A2' => 15,   // Theoretical Training
        'A3' => 25,   // Practical Training
        'A4' => 75,   // Delivery of Training
        'A5' => 60,   // Research Activities
        '6' => 10,    // Technical Seminar/Paper
        '7' => 15,    // Presenting Technical Seminar
        '8' => 5,     // Society Membership
        '9' => 40,    // Technical Oversight
        '10' => 20,   // Committee Participation
        '11' => 40    // Certification Body Role
    );
    
    // Validate and sanitize CPD points
    $sanitized_years = array();
    $total_points = 0;
    $validation_errors = array();
    
    for ($y = 1; $y <= 5; $y++) {
        if (isset($years[$y]) && is_array($years[$y])) {
            $year_total = 0;
            foreach ($cpd_categories as $category => $max_points) {
                $value = floatval($years[$y][$category] ?? 0);
                
                // Validate against maximum points
                if ($value > $max_points) {
                    $validation_errors[] = "Year $y - Category $category: Maximum $max_points points allowed (entered: $value)";
                    $value = $max_points; // Cap at maximum
                }
                
                if ($value < 0) {
                    $validation_errors[] = "Year $y - Category $category: Points cannot be negative";
                    $value = 0;
                }
                
                $sanitized_years[$y][$category] = $value;
                $year_total += $value;
            }
            $total_points += $year_total;
        }
    }
    
    // Log validation errors if any
    if (!empty($validation_errors)) {
        renew_log_warn('CPD points validation warnings during admin update', array(
            'submission_id' => $submission_id,
            'errors' => $validation_errors,
            'updated_by' => get_current_user_id()
        ));
    }
    
    // Update the submission
    update_post_meta($submission_id, '_years', $sanitized_years);
    update_post_meta($submission_id, '_total_cpd_points', $total_points);
    update_post_meta($submission_id, '_cpd_last_updated', current_time('Y-m-d H:i:s'));
    update_post_meta($submission_id, '_cpd_updated_by', get_current_user_id());
    
    renew_log_info('CPD points updated by admin', array(
        'submission_id' => $submission_id,
        'updated_by' => get_current_user_id(),
        'total_points' => $total_points,
        'validation_errors_count' => count($validation_errors)
    ));
    
    $redirect_url = admin_url('admin.php?page=renew-recertification&action=view&submission_id=' . $submission_id . '&cpd_updated=1');
    
    if (!empty($validation_errors)) {
        $redirect_url .= '&validation_warnings=' . urlencode(implode('; ', $validation_errors));
    }
    
    wp_redirect($redirect_url);
    exit;
}

/**
 * Clean up duplicate certificate entries for renewals
 * This function removes duplicate entries to prevent issues
 */
function renew_cleanup_duplicate_entries() {
    global $wpdb;

    renew_log_info('Starting duplicate certificate cleanup process');

    // Find all certificates with -01 or -02 suffix (renewal/recertification)
    $renewed_certs = $wpdb->get_results($wpdb->prepare(
        "SELECT id, certificate_number, user_id, status, issue_date
         FROM {$wpdb->prefix}sgndt_final_certifications
         WHERE certificate_number LIKE %s OR certificate_number LIKE %s
         ORDER BY certificate_number, user_id, issue_date DESC",
        '%-01', '%-02'
    ));

    $cleanup_count = 0;
    $processed_certs = array();

    foreach ($renewed_certs as $cert) {
        $cert_key = $cert->certificate_number . '_' . $cert->user_id;

        if (isset($processed_certs[$cert_key])) {
            // This is a duplicate - remove it
            $delete_result = $wpdb->delete(
                $wpdb->prefix . 'sgndt_final_certifications',
                array('id' => $cert->id),
                array('%d')
            );

            if ($delete_result !== false) {
                $cleanup_count++;
                renew_log_info('Removed duplicate certificate entry', array(
                    'duplicate_id' => $cert->id,
                    'certificate_number' => $cert->certificate_number,
                    'user_id' => $cert->user_id,
                    'status' => $cert->status
                ));
            }
        } else {
            $processed_certs[$cert_key] = true;
        }
    }

    if ($cleanup_count > 0) {
        renew_log_info('Duplicate cleanup completed', array(
            'total_duplicates_removed' => $cleanup_count,
            'remaining_certificates' => count($processed_certs)
        ));
        return $cleanup_count;
    } else {
        renew_log_info('No duplicate certificates found');
        return 0;
    }
}

/**
 * Generate renewed certificate for a submission
 * @param int $submission_id The CPD submission ID
 */
function renew_generate_certificate($submission_id) {
    global $wpdb;

    $submission = get_post($submission_id);
    if (!$submission || $submission->post_type !== 'cpd_submission') {
        wp_die('Submission not found.');
    }

    // Check if certificate has already been generated for this submission
    $existing_certificate = get_post_meta($submission_id, '_certificate_generated_date', true);
    if (!empty($existing_certificate)) {
        renew_log_warn('Attempted to generate certificate for already processed submission', array(
            'submission_id' => $submission_id,
            'existing_certificate_date' => $existing_certificate
        ));
        wp_die('Certificate has already been generated for this submission. Please refresh the page.');
    }

    $name = get_post_meta($submission_id, '_name', true);
    $level = get_post_meta($submission_id, '_level', true);
    $sector = get_post_meta($submission_id, '_sector', true);
    $total_cpd_points = get_post_meta($submission_id, '_total_cpd_points', true);
    $user_id = get_post_meta($submission_id, '_user_id', true);
    $cert_id = get_post_meta($submission_id, '_cert_id', true);

    // Find the original certificate to renew
    $original_cert = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications
         WHERE user_id = %d AND final_certification_id = %d
         AND status = 'issued'
         ORDER BY issue_date DESC LIMIT 1",
        $user_id, $cert_id
    ));

    // Check if original certificate exists
    if (!$original_cert) {
        renew_log_error('Original certificate not found', array(
            'submission_id' => $submission_id,
            'user_id' => $user_id,
            'level' => $level,
            'sector' => $sector
        ));
        wp_die('Original certificate not found. Please ensure the certificate exists and is in issued status.');
    }

    $cert_number = get_post_meta($submission_id, '_cert_number', true);

    // Calculate renewal issue date automatically
    $submission_method = strtoupper($submission_method_generate);
    $today = date('Y-m-d');

    if ($submission_method === 'RECERT') {
        // For recertification: find renewal certificate expiry
        $renewal_cert = $wpdb->get_row($wpdb->prepare(
            "SELECT expiry_date FROM {$wpdb->prefix}sgndt_final_certifications
             WHERE user_id = %d AND certificate_number LIKE %s AND status = 'issued'
             ORDER BY issue_date DESC LIMIT 1",
            $user_id, $cert_number . '-01'
        ));
        $reference_expiry = $renewal_cert ? $renewal_cert->expiry_date : $original_cert->expiry_date;
    } else {
        // For renewal: use original certificate expiry
        $reference_expiry = $original_cert->expiry_date;
    }

    // Use expiry date if not past, otherwise current date
    $renewal_date = (strtotime($reference_expiry) < strtotime($today)) ? $today : $reference_expiry;
    $expiry_date = date('Y-m-d', strtotime($renewal_date . ' +5 years'));

    // Generate certificate number depending on method
    $suffix = ($submission_method === 'RECERT') ? '-02' : '-01';
    $renewed_certificate_number = $cert_number . $suffix;

    // Mark submission as being processed to prevent concurrent generations
    update_post_meta($submission_id, '_certificate_being_generated', '1');
    update_post_meta($submission_id, '_certificate_generation_start', current_time('mysql'));

    // Update submission with certificate info
    update_post_meta($submission_id, '_certificate_number', $renewed_certificate_number);
    update_post_meta($submission_id, '_renewal_date', $renewal_date);
    update_post_meta($submission_id, '_expiry_date', $expiry_date);
    update_post_meta($submission_id, '_certificate_generated_by', get_current_user_id());
    update_post_meta($submission_id, '_certificate_generated_date', current_time('mysql'));

    // Update original certificate status to 'renewed'
    $wpdb->update(
        $wpdb->prefix . 'sgndt_final_certifications',
        array('status' => 'renewed'),
        array('id' => $original_cert->final_certification_id),
        array('%s'),
        array('%d')
    );

    // Generate certificate PDF
    $pdf_result = renew_generate_certificate_pdf($original_cert, $renewed_certificate_number, $renewal_date, $expiry_date, $total_cpd_points, strtoupper($submission_method_generate) === 'RECERT');

    if (!$pdf_result) {
        wp_die('Failed to generate renewed certificate PDF.');
    }

    // Update the existing renewed certificate record with the generated certificate
    // First, check if the record exists - look for it with status 'reviewing' or 'issued'
    $existing_record = $wpdb->get_row($wpdb->prepare(
        "SELECT id, status FROM {$wpdb->prefix}sgndt_final_certifications
         WHERE certificate_number = %s AND user_id = %d
         ORDER BY id DESC LIMIT 1",
        $renewed_certificate_number, $user_id
    ));

    if ($existing_record) {
        $current_status = $existing_record->status;

        if ($current_status === 'issued') {
            // Certificate already exists and is issued
            renew_log_info('Certificate already exists with issued status - no need to regenerate', array(
                'submission_id' => $submission_id,
                'user_id' => $user_id,
                'certificate_number' => $renewed_certificate_number,
                'existing_record_id' => $existing_record->id
            ));
        } else {
            // Update existing record (whether 'reviewing' or other status)
            $cert_update_result = $wpdb->update(
                $wpdb->prefix . 'sgndt_final_certifications',
                array(
                    'issue_date' => $renewal_date,
                    'expiry_date' => $expiry_date,
                    'status' => 'issued',
                    'certificate_link' => $pdf_result['url']
                ),
                array('id' => $existing_record->id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );

            if ($cert_update_result === false) {
                renew_log_error('Failed to update existing renewed certificate record', array(
                    'submission_id' => $submission_id,
                    'user_id' => $user_id,
                    'certificate_number' => $renewed_certificate_number,
                    'existing_record_id' => $existing_record->id,
                    'current_status' => $current_status,
                    'pdf_result' => $pdf_result,
                    'wpdb_last_error' => $wpdb->last_error,
                    'wpdb_last_query' => $wpdb->last_query
                ));
                wp_die('Failed to update renewed certificate record.');
            }

            renew_log_info('Successfully updated existing renewed certificate record to issued', array(
                'submission_id' => $submission_id,
                'user_id' => $user_id,
                'certificate_number' => $renewed_certificate_number,
                'existing_record_id' => $existing_record->id,
                'previous_status' => $current_status,
                'rows_updated' => $cert_update_result
            ));
        }
    } else {
        // Only create new record if no record exists at all
        $cert_insert_result = $wpdb->insert(
            $wpdb->prefix . 'sgndt_final_certifications',
            array(
                'user_id' => $user_id,
                'exam_entry_id' => $original_cert->exam_entry_id,
                'marks_entry_id' => $original_cert->marks_entry_id,
                'method' => $original_cert->method,
                'level' => $original_cert->level,
                'sector' => $original_cert->sector,
                'scope' => $original_cert->scope,
                'certificate_number' => $renewed_certificate_number,
                'issue_date' => $renewal_date,
                'expiry_date' => $expiry_date,
                'certificate_link' => $pdf_result['url'],
                'status' => 'issued',
                'validity_period' => 'renewal'
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($cert_insert_result === false) {
            renew_log_error('Failed to create new renewed certificate record', array(
                'submission_id' => $submission_id,
                'user_id' => $user_id,
                'certificate_number' => $renewed_certificate_number,
                'pdf_result' => $pdf_result,
                'wpdb_last_error' => $wpdb->last_error,
                'wpdb_last_query' => $wpdb->last_query
            ));
            wp_die('Failed to create renewed certificate record.');
        }

        renew_log_info('Successfully created new renewed certificate record', array(
            'submission_id' => $submission_id,
            'user_id' => $user_id,
            'certificate_number' => $renewed_certificate_number,
            'new_record_id' => $wpdb->insert_id
        ));
    }

    // Send notification email with certificate attachment
    $user = get_userdata($user_id);
    if ($user) {
        renew_send_certificate_email($user, $renewed_certificate_number, $level, $sector, $total_cpd_points, $renewal_date, $expiry_date, $pdf_result['path'], strtoupper($submission_method_generate) === 'RECERT');
    }

    // Update user profile renewal status to 'certificate_issued'
    $renewal_status_key = 'renewal_status_' . strtolower($level) . '_' . strtolower(str_replace(' ', '_', $sector));
    update_user_meta($user_id, $renewal_status_key, 'certificate_issued');
    update_user_meta($user_id, $renewal_status_key . '_certificate_number', $renewed_certificate_number);
    update_user_meta($user_id, $renewal_status_key . '_certificate_date', current_time('mysql'));

    // Update simple submission tracking
    $submission_key = 'renewal_submission_' . $cert_number;
    update_user_meta($user_id, $submission_key, 'issued');
    update_user_meta($user_id, $submission_key . '_issued_date', current_time('mysql'));

    renew_log_info('Certificate renewed successfully', array(
        'submission_id' => $submission_id,
        'user_id' => $user_id,
        'original_certificate' => $original_cert ? $original_cert->certificate_number : 'null',
        'renewed_certificate' => $renewed_certificate_number,
        'generated_by' => get_current_user_id(),
        'user_profile_updated' => true
    ));

    // Redirect back to admin page with success message (no auto-download)
    wp_redirect(admin_url('admin.php?page=renew-recertification&action=view&submission_id=' . $submission_id));
    exit;
}

// Generate renewed certificate PDF
function renew_generate_certificate_pdf($original_cert, $renewed_certificate_number, $renewal_date, $expiry_date, $total_cpd_points, $is_recert = false) {
    // Check if original certificate is valid
    if (!$original_cert) {
        renew_log_error('Original certificate is null in PDF generation', array(
            'certificate_number' => $renewed_certificate_number
        ));
        return false;
    }
    
    // Get user data
    $user_data = get_userdata($original_cert->user_id);
    $candidate_name = $user_data ? $user_data->display_name : 'N/A';
    $candidate_reg_number = get_user_meta($original_cert->user_id, 'candidate_reg_number', true);
    
    if (empty($candidate_reg_number)) {
        $candidate_reg_number = 'N/A';
    }
    
    // Prepare PDF file
    $upload_dir = wp_upload_dir();
    $dir = $upload_dir['basedir'] . '/certificates';
    wp_mkdir_p($dir);
    
    if (!is_writable($dir)) {
        renew_log_error('Certificate directory not writable', array('directory' => $dir));
        return false;
    }
    
    $file_name = ($is_recert ? 'recertified' : 'renewed') . "_certificate_{$original_cert->user_id}_{$renewed_certificate_number}_" . time() . ".pdf";
    $file_path = "$dir/$file_name";
    $file_url = $upload_dir['baseurl'] . "/certificates/$file_name?v=" . time();
    
    // Format dates for display
    $issue_date_display = date('d.m.Y', strtotime($renewal_date));
    $expiry_date_display = date('d.m.Y', strtotime($expiry_date));

       // Extract certificate data from original certificate
    $method = $original_cert->method ?? 'N/A';
    $certificate_number = $renewed_certificate_number;
    $sector = $original_cert->sector ?? 'N/A';
    $exam_level = $original_cert->level ?? 'N/A';
    
    // Handle scope - it might be serialized or array
    $scope_raw = $original_cert->scope ?? '';
    if (is_serialized($scope_raw)) {
        $scope = maybe_unserialize($scope_raw);
    } else {
        $scope = $scope_raw;
    }
    
    // Convert scope to display format
    if (is_array($scope)) {
        $scope_display = implode(', ', $scope);
    } else {
        $scope_display = $scope;
    }
    
    $issue_date = $issue_date_display;
    $expiry_date = $expiry_date_display;
    
    // Get user signature (if available)
    $sign = get_user_meta($original_cert->user_id, 'user_signature', true);
    if (empty($sign)) {
        // Use default signature or empty
        $sign = get_stylesheet_directory_uri() . '/assets/logos/default-signature.png';
    }
    
    // Log certificate data for debugging
    renew_log_info('PDF Generation - Certificate Data', array(
        'candidate_name' => $candidate_name,
        'candidate_reg_number' => $candidate_reg_number,
        'method' => $method,
        'certificate_number' => $certificate_number,
        'sector' => $sector,
        'exam_level' => $exam_level,
        'scope_raw' => $scope_raw,
        'scope_display' => $scope_display,
        'issue_date' => $issue_date,
        'expiry_date' => $expiry_date,
        'sign' => $sign,
        'original_cert_id' => $original_cert->id ?? 'N/A'
    ));
    
    // Generate PDF with DOMPDF
    $html = '<head>
    <style>
        @page { margin: 20px; }
        body { margin: 0; padding: 15px; border: 2px solid #494949; width: 278mm; height: 190mm; box-sizing: border-box; }
    </style>
    </head>
    <div style="position:relative; font-size:11pt;">
        <img src="' . get_stylesheet_directory_uri() . '/assets/logos/gvf-pdf.jpg" style="position:absolute; top: -40px; left:0; width:100%; opacity:0.6; height: 100%; z-index:-1;"/>
        <div style="text-align:center; margin-top:10px;">
            <table style="width:100%;"><tr><td style="text-align:left;"><img src="' . get_stylesheet_directory_uri() . '/assets/logos/logondtss.png" style="height:56px;"/></td><td style="text-align:right;"><img src="' . get_stylesheet_directory_uri() . '/assets/logos/icndt.jpg" style="height:56px;"/></td></tr></table>
            <div style="text-align:center; margin-top:60px;">
                <h2 style="color:#3453a5; margin-top: -30px; margin-bottom: 0; padding: 0;" class="main_title"> NON-DESTRUCTIVE TESTING SOCIETY(SINGAPORE)</h2>
                <p style="margin-bottom: 0; padding: 0;width: 100%; text-align: center;">SGNDT Number: <span style="color: #1712fd; padding-top: 10px;">' . $candidate_reg_number . ' Issue 0</span></p>
                <p style="margin-bottom: 0; padding: 0;margin-top: 15px;">This is to certify that</p>
                <h3 style="color: #0001fc; margin-top: 0; padding-top: 5px; font-size: 28px; font-weight: 600;">' . strtoupper($candidate_name) . '</h3>
                <p style="border-top: 1px solid #444; margin-top: 0px; padding-top: 15px; text-align: center;width: fit-content; margin-inline: auto;">has met the established and published Requirements of NDTSS in accordance with ISO 9712:2021 <br>and certified in the following Non-destructive Testing Methods</p>
            </div>
            <div style="margin-top:-0px; text-align:center;">
                <p style="display: inline;"><i>Signature of Certified Individual</i></p>
                <img src="' . $sign . '" style="height:50px; margin-top:5px; border-bottom: 1px solid #000; display: inline;"/>
            </div>
            <table style="width:100%; border-collapse:collapse; text-align: center; margin-top:30px; border-color: #bdbdbd;" border="1" cellpadding="4">
                <thead style="background: #f7f7f7;"><tr><th>Method</th><th>Cert No</th><th>Sector</th><th>Level</th><th>Scope</th><th>Issue Date</th><th>Expiry Date</th></tr></thead>
                <tbody><tr style="text-align: center;"><td style="color: #1712fd;">' . $method . '</td><td style="color: #1712fd;">' . $certificate_number . '</td><td style="color: #1712fd;">' . $sector . '</td><td style="color: #1712fd;">' . $exam_level . '</td><td style="color: #1712fd;">' . $scope_display . '</td><td style="color: #1712fd;">' . $issue_date . '</td><td style="color: #1712fd;">' . $expiry_date . '</td></tr></tbody>
            </table>
            <div style="position: absolute; right: 50px; bottom: 30px;">
                   <img src="' . get_stylesheet_directory_uri() . '/assets/logos/seal.png" 
     style="height:160px; width:160px; object-fit: contain; z-index: 99;" 
     alt="SGNDT Seals"/>
                </div>
            <div style="margin-top:40px; text-align:left; position: absolute; bottom: 80px;width:100%;">
                <table style="width:100%;"><tr><td style="text-align:left; width: 33%; padding: 15px;"><strong style="font-size: 12px;">CHAIRMAN / VICE CHAIRMAN</strong><br><strong style="font-size: 12px;">CERTIFICATION COMMITTEE</strong></td><td style="text-align:left; width: 34%;padding: 15px;"><strong style="font-size: 12px;">AUTHORIZED SIGNATORY</strong><br><strong style="font-size: 12px;">NDTSS</strong></td><td style="text-align:center; width: 33%;padding: 15px;">&nbsp;</td></tr><tr><td style="text-align:left;padding: 15px;">__________________</td><td style="text-align:left;padding: 15px;">__________________</td><td style="text-align:center;padding: 0px;"></td></tr></table>
                <div style="margin-top:60px; text-align:left; position: absolute; bottom: 0px;width:100%;"><table style="width:100%;"><tr><td style="text-align:left; width: 33%; padding: 15px; font-size: 12px;">Form No: NDTSS-QMS-FM-024</td><td style="text-align:center; width: 34%;padding: 15px; font-size: 12px;">Refer overleaf for Notes, details of certification sector and scope</td><td style="text-align:right; width: 33%; padding: 15px; font-size: 12px;"> Rev. 5 (' . date('d F Y') . ')</td></tr></table></div>
            </div>
        </div>
    </div>';

    $html_notes = '<div style="font-size:10pt;"><img src="' . get_stylesheet_directory_uri() . '/assets/logos/gvf-pdf.jpg" style="position:absolute; top: -40px; left:0; width:100%; opacity:0.6; height: 100%; z-index:-1;"/><div style="width: 100%; display: block; clear: both; margin-top: 30px;"><div style="width: 35%;float: left; padding-right: 10px;"><p style="text-align: left; font-size: 16px; font-weight: 600;">Abbreviation for Certification Sector</p><table border="1" cellpadding="4" style="width:100%; border-collapse:collapse;"><tr><th style="width: 65px; background: #f7f7f7;">Industry<br> Sector</th><th style="background: #f7f7f7;">Details</th></tr><tr><td style="text-align: center;">s</td><td>Pre- & In-service Inspection which includes Manufacturing</td></tr><tr><td style="text-align: center;">a</td><td>Aerospace</td></tr><tr><td style="text-align: center;">r</td><td>Railway Maintenance</td></tr><tr><td style="text-align: center;">m</td><td>Manufacturing</td></tr><tr><td style="text-align: center;">ci, me, el</td><td>Civil, Mechanical, Electrical (TT)</td></tr></table><table border="1" cellpadding="4" style="width:100%; border-collapse:collapse;"><tr><th style="text-align: center;width: 65px;background: #f7f7f7;">Product <br>Sector</th><th style="background: #f7f7f7;">Details</th></tr><tr><td style="text-align: center;">w</td><td>Welds</td></tr><tr><td style="text-align: center;">c</td><td>Castings</td></tr><tr><td style="text-align: center;">wp</td><td>Wrought Products </td></tr><tr><td style="text-align: center;">t</td><td>Tubes and Pipes</td></tr><tr><td style="text-align: center;">f</td><td>Forgings</td></tr><tr><td style="text-align: center;">frp</td><td>Reinforced Plastics</td></tr></table></div><div style="width: 62%;float: right; padding-left: 10px;"><p style="text-align: left; font-size: 16px; padding-inline: 15px;font-weight: 600;">Abbreviation for Scope / Technique</p><table border="1" cellpadding="4" style="width:100%; border-collapse:collapse;"><tr><th style="text-align: left;background: #f7f7f7;">Scope</th><th style="text-align: left;background: #f7f7f7;">Details</th></tr><tr><td>F / P / L / ML</td><td>Fixed / Portable Equipment / Line System / Magnetic Flux Leakage</td></tr><tr><td>X / G / DR / CR</td><td>X-ray / Gamma-ray / Digital Radiography / Computed Radiography</td></tr><tr><td>PL / P / T / N / NZ / PAUT / TOFD / AUT</td><td>Plate / Pipe / T Joint / Node / Nozzle Weld, Phased Array, Time of Flight, Auto UT</td></tr><tr><td>S / W / Fe / NFe / FP</td><td>Seamless, Welded, Ferrous, Non-Ferrous, Flat Plate</td></tr><tr><td>Tu</td><td>Tubes (ET)</td></tr><tr><td>D / R</td><td>Direct / Remote (VT)</td></tr><tr><td>V / FL</td><td>Visible / Fluorescent (PT / MT)</td></tr><tr><td>TT / LM</td><td>Thickness Testing / Lamination (UT)</td></tr><tr><td>Pa / Ac</td><td>Passive / Active (Thermal Infrared Testing)</td></tr></table></div></div><div style="clear: both; width: 100%; display: block;"></div><table style="width:100%;"><tr><td><div style="width: 100%; display: block; max-width: 100%;"><h4 style="margin-top:20px; display: block; width: 100%; font-size: 20px; margin-bottom: 8px;">Notes:</h4><ol style="padding-left: 15px; margin-top: 0;"><li style="margin-bottom: 8px;">Candidate appearing in Industrial Sector “s” will be given 3 specimens with a mixture of welding and casting or forging or wrought products as per ISO 9712:2021.</li><li style="margin-bottom: 8px;">For UT, scope applies to product sector welds only.</li><li style="margin-bottom: 8px;">The SAC accreditation mark indicates accreditation certificate number PC-2017-03.</li><li style="margin-bottom: 8px;">This certificate is property of NDTSS and not valid without SGNDT seal.</li><li style="margin-bottom: 8px;">NDTSS is accredited by SAC under ISO/IEC 17024:2012.</li><li style="margin-bottom: 8px;">This certificate is issued as per NDTSS/SGNDT OM-001 and ISO 9712:2021.</li></ol></div></td></tr></table><div style="text-align:right; font-style:italic; font-size:9pt; margin-top:20px;">Form No: NDTSS-QMS-FM-024  Rev. 5 (' . date('d F Y') . ')</div></div>';

    $full_html = $html . '<div style="page-break-after: always;"></div>' . $html_notes;

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->loadHtml($full_html);

    try {
        $dompdf->render();

        // Verify the PDF has exactly 2 pages
        $page_count = $dompdf->getCanvas()->get_page_count();
        if ($page_count != 2) {
            renew_log_warn('Certificate PDF generated with unexpected page count', array(
                'expected_pages' => 2,
                'actual_pages' => $page_count,
                'certificate_number' => $renewed_certificate_number,
                'total_cpd_points' => $total_cpd_points
            ));
        }

        // Save PDF to file
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        file_put_contents($file_path, $dompdf->output());

        renew_log_info('Optimized renewed certificate PDF generated successfully', array(
            'certificate_number' => $renewed_certificate_number,
            'file_path' => $file_path,
            'page_count' => $page_count,
            'user_id' => $original_cert ? $original_cert->user_id : 'null',
            'cpd_points' => $total_cpd_points,
            'file_size' => filesize($file_path) . ' bytes'
        ));

        return array(
            'path' => $file_path,
            'url' => $file_url,
            'filename' => $file_name
        );

    } catch (Exception $e) {
        renew_log_error('Failed to generate optimized renewed certificate PDF', array(
            'error' => $e->getMessage(),
            'certificate_number' => $renewed_certificate_number,
            'user_id' => $original_cert ? $original_cert->user_id : 'null',
            'trace' => $e->getTraceAsString()
        ));
        return false;
    }
}

// Send certificate email with attachment (renewal or recertification)
function renew_send_certificate_email($user, $certificate_number, $level, $sector, $total_cpd_points, $renewal_date, $expiry_date, $certificate_path, $is_recert = false) {
    $subject = ($is_recert ? 'Certificate Recertified - ' : 'Certificate Renewed - ') . $certificate_number;
    
    $heading = $is_recert ? 'Certificate Recertification Confirmation' : 'Certificate Renewal Confirmation';
    $intro   = $is_recert ? 'Your certification has been successfully recertified through CPD points.' : 'Your certification has been successfully renewed through CPD points.';
    $dateLbl = $is_recert ? 'Recertification Date' : 'Renewal Date';
    $attach  = $is_recert ? 'Your recertified certificate is attached' : 'Your renewed certificate is attached';
    
    $message = "
    <h2>" . $heading . "</h2>
    <p>Dear " . $user->display_name . ",</p>
    <p>" . $intro . "</p>
    <p><strong>Certificate Details:</strong></p>
    <ul>
        <li><strong>Certificate Number:</strong> " . $certificate_number . "</li>
        <li><strong>Level:</strong> " . $level . "</li>
        <li><strong>Sector:</strong> " . $sector . "</li>
        <li><strong>Total CPD Points:</strong> " . $total_cpd_points . "</li>
        <li><strong>" . $dateLbl . ":</strong> " . date('F j, Y', strtotime($renewal_date)) . "</li>
        <li><strong>Expiry Date:</strong> " . date('F j, Y', strtotime($expiry_date)) . "</li>
    </ul>
    <p>" . $attach . " and is now active and valid for 5 years.</p>
    <p>You can also download your certificate from your user profile on our website.</p>
    <p>Best regards,<br>NDTSS Team</p>
    ";
    
    // Send email with certificate attachment
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
    );
    
    $attachments = array();
    if (file_exists($certificate_path)) {
        $attachments[] = $certificate_path;
    }
    
    $email_sent = wp_mail($user->user_email, $subject, $message, $headers, $attachments);
    
    if ($email_sent) {
        renew_log_info(($is_recert ? 'Recertified' : 'Renewed') . ' certificate email sent successfully', array(
            'user_email' => $user->user_email,
            'certificate_number' => $certificate_number
        ));
    } else {
        renew_log_error('Failed to send ' . ($is_recert ? 'recertified' : 'renewed') . ' certificate email', array(
            'user_email' => $user->user_email,
            'certificate_number' => $certificate_number
        ));
    }
    
    return $email_sent;
}

// Email template settings page function
function renew_render_email_template_settings_page() {
    echo '<div class="wrap email-settings-container">';
    echo '<h1>Renewal Email Templates</h1>';
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'save_renewal_email_templates')) {
        foreach ($_POST as $key => $value) {
            if ($key !== '_wpnonce' && $key !== 'submit') {
                update_option(sanitize_text_field($key), wp_kses_post($value));
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>Renewal email templates saved successfully!</p></div>';
    }
    
    // Available placeholders help
    echo '<p>You can use the following placeholders: 
        <code>{user_name}</code>, 
        <code>{renewal_method}</code>, 
        <code>{certification_level}</code>, 
        <code>{certification_sector}</code>, 
        <code>{submission_date}</code>, 
        <code>{total_cpd_points}</code>,
        <code>{submission_id}</code>
    </p>';
    
    // Styled tab navigation
    echo '<ul class="nav-tab-wrapper">';
    echo '<li><a href="#user" class="nav-tab nav-tab-active">User Emails</a></li>';
    echo '<li><a href="#admin" class="nav-tab">Admin Emails</a></li>';
    echo '</ul>';
    
    $templates = [
        'user' => [
            'user_renewal_submission' => [
                'label' => 'Renewal Application Submitted (User)',
                'subject_option' => 'user_renewal_submission_subject',
                'heading_option' => 'user_renewal_submission_heading',
                'message_option' => 'user_renewal_submission_message'
            ],
            'user_recertification_submission' => [
                'label' => 'Recertification Application Submitted (User)',
                'subject_option' => 'user_recertification_submission_subject',
                'heading_option' => 'user_recertification_submission_heading', 
                'message_option' => 'user_recertification_submission_message'
            ],
            'user_renewal_reminder' => [
                'label' => 'Renewal Reminder (User)',
                'subject_option' => 'user_renewal_reminder_subject',
                'heading_option' => 'user_renewal_reminder_heading',
                'message_option' => 'user_renewal_reminder_message'
            ]
        ],
        'admin' => [
            'admin_renewal_notification' => [
                'label' => 'New Renewal Application (Admin)',
                'subject_option' => 'admin_renewal_notification_subject',
                'heading_option' => 'admin_renewal_notification_heading',
                'message_option' => 'admin_renewal_notification_message'
            ],
            'admin_recertification_notification' => [
                'label' => 'New Recertification Application (Admin)',
                'subject_option' => 'admin_recertification_notification_subject',
                'heading_option' => 'admin_recertification_notification_heading',
                'message_option' => 'admin_recertification_notification_message'
            ]
        ]
    ];

    echo '<form method="post">';
    wp_nonce_field('save_renewal_email_templates');

    foreach ($templates as $group => $group_templates) {
        echo '<div id="' . $group . '" class="tab-content" style="' . ($group === 'user' ? '' : 'display:none;') . '">';
        echo '<h2 class="tab-title">' . ucfirst($group) . ' Email Settings</h2>';

        foreach ($group_templates as $key => $details) {
            $subject = get_option($details['subject_option'], '');
            $heading = get_option($details['heading_option'], '');
            $message = get_option($details['message_option'], '');
            $enabled = get_option($key . '_enabled', 'yes');

            echo '<div class="email-template-card">';
            echo "<button type='button' class='toggle-btn' data-target='{$key}'>▼ {$details['label']}</button>";
            echo "<div id='{$key}' class='email-content' style='display: none;'>";

            echo "<label><input type='checkbox' name='{$key}_enabled' value='yes' " . checked($enabled, 'yes', false) . "> Enable this email notification</label><br>";
            echo "<label><strong>Subject:</strong><br><input type='text' name='{$details['subject_option']}' value='" . esc_attr($subject) . "' class='regular-text input-field'></label><br>";
            echo "<label><strong>Email Heading:</strong><br><input type='text' name='{$details['heading_option']}' value='" . esc_attr($heading) . "' class='regular-text input-field'></label><br>";
            echo "<label><strong>Message Content:</strong><br>";
            wp_editor($message, $details['message_option'], ['textarea_rows' => 6, 'media_buttons' => false]);
            echo "</label><br><hr></div></div>";
        }
        echo '</div>';
    }

    submit_button('Save Templates');
    echo '</form></div>';
    
    // Add enhanced CSS styling
    echo '<style>
        .email-settings-container {
            max-width: 1200px;
        }
        .nav-tab-wrapper {
            border-bottom: 2px solid #ddd;
            margin-bottom: 20px;
            padding: 0;
        }
        .nav-tab {
            display: inline-block;
            text-decoration: none;
            padding: 12px 20px;
            background: #f1f1f1;
            border: 2px solid #ddd;
            border-bottom: none;
            margin-right: 5px;
            cursor: pointer;
            font-weight: 500;
            color: #555;
            transition: all 0.3s ease;
        }
        .nav-tab:hover {
            background: #e8e8e8;
            color: #333;
        }
        .nav-tab-active {
            background: #fff;
            border-bottom: 2px solid #fff;
            margin-bottom: -2px;
            color: #0073aa;
            font-weight: 600;
        }
        .tab-content {
            background: #fff;
            padding: 25px;
            border: 2px solid #ddd;
            border-top: none;
            border-radius: 0 0 6px 6px;
        }
        .tab-title {
            margin-top: 0;
            margin-bottom: 25px;
            color: #23282d;
            font-size: 24px;
            font-weight: 600;
        }
        .email-template-card {
            margin-bottom: 25px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #fafafa;
            overflow: hidden;
        }
        .toggle-btn {
            width: 100%;
            background: #f8f9fa;
            border: none;
            padding: 15px 20px;
            text-align: left;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            border-bottom: 1px solid #ddd;
            transition: background-color 0.3s ease;
            color: #333;
        }
        .toggle-btn:hover {
            background: #e9ecef;
        }
        .toggle-btn:focus {
            outline: none;
            background: #e9ecef;
        }
        .email-content {
            padding: 20px;
            background: #fff;
        }
        .email-content label {
            display: block;
            margin-bottom: 15px;
            font-weight: 600;
            color: #333;
        }
        .email-content input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.1);
        }
        .input-field {
            width: 100%;
            margin-bottom: 15px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .input-field:focus {
            border-color: #0073aa;
            outline: none;
            box-shadow: 0 0 0 1px #0073aa;
        }
        .email-content .wp-editor-container {
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .email-content hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #eee;
        }
        code {
            background: #f0f6fc;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: Monaco, Consolas, monospace;
            color: #d63384;
            font-weight: 600;
        }
    </style>';

    // Add JavaScript for tab functionality and collapsible sections
    echo '<script>
        jQuery(document).ready(function($) {
            // Tab switching
            $(".nav-tab").click(function(e) {
                e.preventDefault();
                var target = $(this).attr("href");
                
                $(".nav-tab").removeClass("nav-tab-active");
                $(this).addClass("nav-tab-active");
                
                $(".tab-content").hide();
                $(target).show();
            });
            
            // Toggle email content sections
            $(".toggle-btn").click(function(e) {
                e.preventDefault();
                var target = $(this).data("target");
                $("#" + target).toggle();
                
                // Change arrow direction
                var arrow = $(this).text().charAt(0);
                if (arrow === "▼") {
                    $(this).html($(this).html().replace("▼", "▶"));
                } else {
                    $(this).html($(this).html().replace("▶", "▼"));
                }
            });
        });
    </script>';
}
