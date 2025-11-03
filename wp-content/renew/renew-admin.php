<?php
if (!defined('ABSPATH')) { exit; }

require_once get_stylesheet_directory() . '/renew/renew-logger.php';

// Add admin menu
add_action('admin_menu', 'renew_add_admin_menu');

function renew_add_admin_menu() {
    add_menu_page(
        'CPD Submissions',
        'CPD Submissions',
        'manage_options',
        'cpd-submissions',
        'renew_admin_page',
        'dashicons-media-spreadsheet',
        30
    );
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

function renew_admin_list_submissions() {
    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $per_page = 20;
    $offset = ($paged - 1) * $per_page;
    
    $args = array(
        'post_type' => 'cpd_submission',
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'offset' => $offset,
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $submissions = get_posts($args);
    $total_posts = wp_count_posts('cpd_submission')->publish;
    $total_pages = ceil($total_posts / $per_page);
    
    ?>
    <div class="wrap">
        <h1>CPD Submissions</h1>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Level</th>
                    <th>Sector</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissions)): ?>
                    <tr><td colspan="7">No submissions found.</td></tr>
                <?php else: ?>
                    <?php foreach ($submissions as $submission): ?>
                        <?php
                        $user_id = get_post_meta($submission->ID, '_user_id', true);
                        $name = get_post_meta($submission->ID, '_name', true);
                        $level = get_post_meta($submission->ID, '_level', true);
                        $sector = get_post_meta($submission->ID, '_sector', true);
                        $status = get_post_meta($submission->ID, '_status', true) ?: 'pending';
                        $years = get_post_meta($submission->ID, '_years', true) ?: array();
                        ?>
                        <tr>
                            <td><?php echo $submission->ID; ?></td>
                            <td><?php echo esc_html($name); ?></td>
                            <td><?php echo esc_html($level); ?></td>
                            <td><?php echo esc_html($sector); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($submission->post_date)); ?></td>
                            <td>
                                <span class="status-<?php echo esc_attr($status); ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=cpd-submissions&action=view&submission_id=' . $submission->ID); ?>" class="button button-small">View</a>
                                <?php if ($status === 'pending'): ?>
                                    <a href="<?php echo admin_url('admin.php?page=cpd-submissions&action=approve&submission_id=' . $submission->ID); ?>" class="button button-small button-primary">Approve</a>
                                    <a href="<?php echo admin_url('admin.php?page=cpd-submissions&action=reject&submission_id=' . $submission->ID); ?>" class="button button-small">Reject</a>
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
    
    .admin-actions { margin: 20px 0; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; }
    .admin-actions form { display: inline-block; margin-right: 15px; }
    .admin-actions textarea { width: 300px; height: 60px; margin-right: 10px; }
    .certificate-info { background: #e8f5e8; padding: 15px; border: 1px solid #5cb85c; border-radius: 4px; margin: 10px 0; }
    .cpd-points-table input[type="number"] { width: 80px; }
    .year-total { font-weight: bold; color: #0073aa; }
    .form-table th { width: 200px; }
    .form-table input, .form-table select { width: 100%; max-width: 300px; }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Real-time CPD calculations
        $('input[name^="years["]').on('input', function() {
            var yearNum = $(this).attr('name').match(/years\[(\d+)\]/)[1];
            var total = 0;
            $('input[name^="years[' + yearNum + ']"]').each(function() {
                total += parseFloat($(this).val()) || 0;
            });
            $('.year-total-' + yearNum).text(total.toFixed(1));
        });
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
    $years = get_post_meta($submission_id, '_years', true) ?: array();
    $uploads = get_post_meta($submission_id, '_uploads', true) ?: array();
    $status = get_post_meta($submission_id, '_status', true) ?: 'pending';
    $admin_notes = get_post_meta($submission_id, '_admin_notes', true);
    $total_cpd_points = get_post_meta($submission_id, '_total_cpd_points', true);
    $renewal_date = get_post_meta($submission_id, '_renewal_date', true);
    $certificate_number = get_post_meta($submission_id, '_certificate_number', true);
    
    $user = get_userdata($user_id);
    ?>
    <div class="wrap">
        <h1>CPD Submission Details</h1>
        
        <?php if (isset($_GET['updated']) && $_GET['updated'] == '1'): ?>
            <div class="notice notice-success"><p>Submission updated successfully!</p></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['cpd_updated']) && $_GET['cpd_updated'] == '1'): ?>
            <div class="notice notice-success"><p>CPD points updated successfully!</p></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['certificate_generated']) && $_GET['certificate_generated'] == '1'): ?>
            <div class="notice notice-success"><p>Certificate generated successfully!</p></div>
        <?php endif; ?>
        
        <p><a href="<?php echo admin_url('admin.php?page=cpd-submissions'); ?>" class="button">&larr; Back to List</a></p>
        
        <div class="cpd-submission-details">
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
                        <th>Date of Birth</th>
                        <td><input type="date" name="dob" value="<?php echo esc_attr($dob); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th>Level</th>
                        <td>
                            <select name="level" class="regular-text">
                                <option value="Level 1" <?php selected($level, 'Level 1'); ?>>Level 1</option>
                                <option value="Level 2" <?php selected($level, 'Level 2'); ?>>Level 2</option>
                                <option value="Level 3" <?php selected($level, 'Level 3'); ?>>Level 3</option>
                                <option value="Senior Level" <?php selected($level, 'Senior Level'); ?>>Senior Level</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Sector</th>
                        <td>
                            <select name="sector" class="regular-text">
                                <option value="Aerospace" <?php selected($sector, 'Aerospace'); ?>>Aerospace</option>
                                <option value="Automotive" <?php selected($sector, 'Automotive'); ?>>Automotive</option>
                                <option value="Construction" <?php selected($sector, 'Construction'); ?>>Construction</option>
                                <option value="Marine" <?php selected($sector, 'Marine'); ?>>Marine</option>
                                <option value="Oil & Gas" <?php selected($sector, 'Oil & Gas'); ?>>Oil & Gas</option>
                                <option value="Power Generation" <?php selected($sector, 'Power Generation'); ?>>Power Generation</option>
                                <option value="Railway" <?php selected($sector, 'Railway'); ?>>Railway</option>
                                <option value="Other" <?php selected($sector, 'Other'); ?>>Other</option>
                            </select>
                        </td>
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
                        <th>Certificate Number</th>
                        <td><input type="text" name="certificate_number" value="<?php echo esc_attr($certificate_number); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th>Renewal Date</th>
                        <td><input type="date" name="renewal_date" value="<?php echo esc_attr($renewal_date); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="update_submission" class="button button-primary" value="Update Submission" />
                </p>
            </form>
            
            <h2>CPD Points by Year (Editable)</h2>
            <form id="edit-cpd-points-form" method="post">
                <?php wp_nonce_field('renew_admin_action', 'renew_nonce'); ?>
                <input type="hidden" name="action" value="update_cpd_points">
                <input type="hidden" name="submission_id" value="<?php echo $submission_id; ?>">
                
                <table class="wp-list-table widefat">
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Training</th>
                            <th>Workshops</th>
                            <th>Seminars</th>
                            <th>Publications</th>
                            <th>Other</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($y = 1; $y <= 5; $y++): ?>
                            <?php
                            $year_data = isset($years[$y]) ? $years[$y] : array();
                            $total = 0;
                            foreach (['training', 'workshops', 'seminars', 'publications', 'other'] as $cat) {
                                $total += floatval($year_data[$cat] ?? 0);
                            }
                            ?>
                            <tr>
                                <td><strong>Year <?php echo $y; ?></strong></td>
                                <td><input type="number" name="years[<?php echo $y; ?>][training]" value="<?php echo esc_attr($year_data['training'] ?? 0); ?>" step="0.1" min="0" class="small-text" /></td>
                                <td><input type="number" name="years[<?php echo $y; ?>][workshops]" value="<?php echo esc_attr($year_data['workshops'] ?? 0); ?>" step="0.1" min="0" class="small-text" /></td>
                                <td><input type="number" name="years[<?php echo $y; ?>][seminars]" value="<?php echo esc_attr($year_data['seminars'] ?? 0); ?>" step="0.1" min="0" class="small-text" /></td>
                                <td><input type="number" name="years[<?php echo $y; ?>][publications]" value="<?php echo esc_attr($year_data['publications'] ?? 0); ?>" step="0.1" min="0" class="small-text" /></td>
                                <td><input type="number" name="years[<?php echo $y; ?>][other]" value="<?php echo esc_attr($year_data['other'] ?? 0); ?>" step="0.1" min="0" class="small-text" /></td>
                                <td><strong class="year-total-<?php echo $y; ?>"><?php echo $total; ?></strong></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" name="update_cpd_points" class="button button-primary" value="Update CPD Points" />
                </p>
            </form>
            
            <h2>Uploaded Files</h2>
            <?php if (!empty($uploads)): ?>
                <?php foreach ($uploads as $type => $files): ?>
                    <h3><?php echo ucfirst(str_replace('_', ' ', $type)); ?></h3>
                    <?php if (is_array($files)): ?>
                        <ul>
                            <?php foreach ($files as $file): ?>
                                <li>
                                    <a href="<?php echo esc_url($file['url']); ?>" target="_blank">
                                        <?php echo esc_html(basename($file['file'])); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No files uploaded.</p>
            <?php endif; ?>
            
            <h2>Admin Actions</h2>
            <div class="admin-actions">
                <?php if ($status === 'pending'): ?>
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('renew_admin_action', 'renew_nonce'); ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="submission_id" value="<?php echo $submission_id; ?>">
                        <button type="submit" class="button button-primary">Approve Submission</button>
                    </form>
                    
                    <form method="post" style="display: inline; margin-left: 10px;">
                        <?php wp_nonce_field('renew_admin_action', 'renew_nonce'); ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="submission_id" value="<?php echo $submission_id; ?>">
                        <textarea name="reject_reason" placeholder="Reason for rejection" required></textarea>
                        <button type="submit" class="button">Reject Submission</button>
                    </form>
                <?php endif; ?>
                
                <?php if ($status === 'approved' && !$certificate_number): ?>
                    <form method="post" style="display: inline; margin-left: 10px;">
                        <?php wp_nonce_field('renew_admin_action', 'renew_nonce'); ?>
                        <input type="hidden" name="action" value="renew_certificate">
                        <input type="hidden" name="submission_id" value="<?php echo $submission_id; ?>">
                        <button type="submit" class="button button-secondary">Generate Renewed Certificate</button>
                    </form>
                <?php endif; ?>
                
                <?php if ($certificate_number): ?>
                    <div class="certificate-info">
                        <h3>Certificate Information</h3>
                        <p><strong>Certificate Number:</strong> <?php echo esc_html($certificate_number); ?></p>
                        <p><strong>Renewal Date:</strong> <?php echo esc_html($renewal_date ? date('F j, Y', strtotime($renewal_date)) : 'Not set'); ?></p>
                        <p><strong>Status:</strong> <span class="status-approved">Certificate Generated</span></p>
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
            wp_redirect(admin_url('admin.php?page=cpd-submissions&action=view&submission_id=' . $submission_id));
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
    update_post_meta($submission_id, '_status', 'approved');
    update_post_meta($submission_id, '_approved_by', get_current_user_id());
    update_post_meta($submission_id, '_approved_date', current_time('mysql'));
    
    renew_log_info('CPD submission approved', array(
        'submission_id' => $submission_id,
        'approved_by' => get_current_user_id()
    ));
    
    wp_redirect(admin_url('admin.php?page=cpd-submissions&action=view&submission_id=' . $submission_id));
    exit;
}

function renew_reject_submission($submission_id, $reason) {
    update_post_meta($submission_id, '_status', 'rejected');
    update_post_meta($submission_id, '_rejected_by', get_current_user_id());
    update_post_meta($submission_id, '_rejected_date', current_time('mysql'));
    update_post_meta($submission_id, '_reject_reason', $reason);
    
    renew_log_info('CPD submission rejected', array(
        'submission_id' => $submission_id,
        'rejected_by' => get_current_user_id(),
        'reason' => $reason
    ));
    
    wp_redirect(admin_url('admin.php?page=cpd-submissions&action=view&submission_id=' . $submission_id));
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
    $renewal_date = sanitize_text_field($_POST['renewal_date']);
    
    // Update post meta
    update_post_meta($submission_id, '_name', $name);
    update_post_meta($submission_id, '_dob', $dob);
    update_post_meta($submission_id, '_level', $level);
    update_post_meta($submission_id, '_sector', $sector);
    update_post_meta($submission_id, '_status', $status);
    update_post_meta($submission_id, '_total_cpd_points', $total_cpd_points);
    update_post_meta($submission_id, '_certificate_number', $certificate_number);
    update_post_meta($submission_id, '_renewal_date', $renewal_date);
    
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
    
    wp_redirect(admin_url('admin.php?page=cpd-submissions&action=view&submission_id=' . $submission_id . '&updated=1'));
    exit;
}

function renew_update_cpd_points($submission_id) {
    $years = isset($_POST['years']) && is_array($_POST['years']) ? $_POST['years'] : array();
    
    // Validate and sanitize CPD points
    $sanitized_years = array();
    $total_points = 0;
    
    for ($y = 1; $y <= 5; $y++) {
        if (isset($years[$y]) && is_array($years[$y])) {
            $year_total = 0;
            foreach (['training', 'workshops', 'seminars', 'publications', 'other'] as $cat) {
                $value = floatval($years[$y][$cat] ?? 0);
                $sanitized_years[$y][$cat] = $value;
                $year_total += $value;
            }
            $total_points += $year_total;
        }
    }
    
    update_post_meta($submission_id, '_years', $sanitized_years);
    update_post_meta($submission_id, '_total_cpd_points', $total_points);
    
    renew_log_info('CPD points updated by admin', array(
        'submission_id' => $submission_id,
        'updated_by' => get_current_user_id(),
        'total_points' => $total_points
    ));
    
    wp_redirect(admin_url('admin.php?page=cpd-submissions&action=view&submission_id=' . $submission_id . '&cpd_updated=1'));
    exit;
}

function renew_generate_certificate($submission_id) {
    $submission = get_post($submission_id);
    if (!$submission || $submission->post_type !== 'cpd_submission') {
        wp_die('Submission not found.');
    }
    
    $name = get_post_meta($submission_id, '_name', true);
    $level = get_post_meta($submission_id, '_level', true);
    $sector = get_post_meta($submission_id, '_sector', true);
    $total_cpd_points = get_post_meta($submission_id, '_total_cpd_points', true);
    
    // Generate certificate number
    $certificate_number = 'REN-' . date('Y') . '-' . str_pad($submission_id, 6, '0', STR_PAD_LEFT);
    $renewal_date = current_time('Y-m-d');
    $expiry_date = date('Y-m-d', strtotime('+5 years'));
    
    // Update submission with certificate info
    update_post_meta($submission_id, '_certificate_number', $certificate_number);
    update_post_meta($submission_id, '_renewal_date', $renewal_date);
    update_post_meta($submission_id, '_expiry_date', $expiry_date);
    update_post_meta($submission_id, '_certificate_generated_by', get_current_user_id());
    update_post_meta($submission_id, '_certificate_generated_date', current_time('mysql'));
    
    // Add to user's final certifications
    $user_id = get_post_meta($submission_id, '_user_id', true);
    $final_certifications = get_user_meta($user_id, 'final_certifications', true) ?: array();
    
    $new_certification = array(
        'certificate_number' => $certificate_number,
        'method' => 'CPD Renewal',
        'level' => $level,
        'sector' => $sector,
        'scope' => 'Renewal',
        'issue_date' => $renewal_date,
        'expiry_date' => $expiry_date,
        'status' => 'active',
        'cpd_points' => $total_cpd_points,
        'renewal_submission_id' => $submission_id
    );
    
    $final_certifications[] = $new_certification;
    update_user_meta($user_id, 'final_certifications', $final_certifications);
    
    // Send notification email
    $user = get_userdata($user_id);
    if ($user) {
        $subject = 'Certificate Renewed - ' . $certificate_number;
        $message = "
        <h2>Certificate Renewal Confirmation</h2>
        <p>Dear " . $user->display_name . ",</p>
        <p>Your certification has been successfully renewed through CPD points.</p>
        <p><strong>Certificate Details:</strong></p>
        <ul>
            <li><strong>Certificate Number:</strong> " . $certificate_number . "</li>
            <li><strong>Level:</strong> " . $level . "</li>
            <li><strong>Sector:</strong> " . $sector . "</li>
            <li><strong>Total CPD Points:</strong> " . $total_cpd_points . "</li>
            <li><strong>Renewal Date:</strong> " . date('F j, Y', strtotime($renewal_date)) . "</li>
            <li><strong>Expiry Date:</strong> " . date('F j, Y', strtotime($expiry_date)) . "</li>
        </ul>
        <p>Your renewed certificate is now active and valid for 5 years.</p>
        <p>Best regards,<br>NDTSS Team</p>
        ";
        
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        wp_mail($user->user_email, $subject, $message);
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
    }
    
    renew_log_info('Certificate renewed', array(
        'submission_id' => $submission_id,
        'user_id' => $user_id,
        'certificate_number' => $certificate_number,
        'generated_by' => get_current_user_id()
    ));
    
    wp_redirect(admin_url('admin.php?page=cpd-submissions&action=view&submission_id=' . $submission_id . '&certificate_generated=1'));
    exit;
}
