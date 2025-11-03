<?php
/**
 * Form 31 (Exam Renewal/Recertification) Workflow Handler
 * 
 * This file handles the complete workflow for Form 31 submissions including:
 * - Approval/Rejection process
 * - Examiner/Invigilator assignment
 * - Method slot scheduling and assignment saving
 * - Listing in submitted forms
 * 
 * Created as a separate file to avoid affecting existing Form 15 functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Form 31 examiner assignment to entry detail sidebar
 */
//add_action('gform_entry_detail_sidebar_middle', 'add_form_31_examiner_assignment_to_entry', 10, 2);
function add_form_31_examiner_assignment_to_entry($form, $entry) {
    // Only apply to Form 31
    if ((int)$form['id'] !== 39) {
        return;
    }

    $entry_id = $entry['id'];
    $entry_status = gform_get_meta($entry_id, 'approval_status');
    
    if (strtolower($entry_status) !== 'approved') {
        echo '<div class="postbox"><h3 class="hndle"><span>Assign Examiner (Form 39)</span></h3><div class="inside"><p>This renewal entry must be approved before assigning an examiner.</p></div></div>';
        return;
    }

    $center_id = gform_get_meta($entry_id, '_linked_exam_center');
    if (!$center_id) {
        echo '<div class="postbox"><h3 class="hndle"><span>Assign Examiner/Invigilator (Form 39)</span></h3><div class="inside"><p>No exam center linked to this renewal entry.</p></div></div>';
        return;
    }

    $assigned_examiners = (array)gform_get_meta($entry_id, '_assigned_examiners');
    $assigned_invigilators = (array)gform_get_meta($entry_id, '_assigned_invigilators');
    $examiner_users = get_post_meta($center_id, 'assign_examiners', true);
    $invigilator_users = get_post_meta($center_id, 'assign_invigilator', true);

    if (empty($examiner_users) && empty($invigilator_users)) {
        echo '<div class="postbox"><h3 class="hndle"><span>Assign Examiner/Invigilator (Form 39)</span></h3><div class="inside"><p>No examiners or invigilators found for this center.</p></div></div>';
        return;
    }

    // // Form 31 field mappings - Update these based on actual Form 31 field IDs
    // $field_map = [
    //     'methods' => [
    //         '188.1' => 'ET', '188.2' => 'MT', '188.3' => 'PT', '188.4' => 'UT', 
    //         '188.5' => 'RT', '188.6' => 'VT', '188.7' => 'TT', '188.8' => 'PAUT', '188.9' => 'TOFD'
    //     ],
    //     'exam_order_no' => '789', // Update based on Form 31 field ID
    // ];

    // // Get selected methods for Form 31
    // $field_188_values = [];
    // foreach ($field_map['methods'] as $key => $label) {
    //     if (!empty(rgar($entry, $key))) {
    //         $field_188_values[] = $label;
    //     }
    // }
    $field_188_values =  rgar($entry, '9');
    $exam_order_no = rgar($entry, $field_map['exam_order_no']);

    $method_dates = gform_get_meta($entry_id, '_method_slots', true);
    $method_dates = is_array($method_dates) ? $method_dates : [];
    
    // Check for existing marks
    $marks_entries = GFAPI::get_entries(25, ['field_filters' => [['key' => '20', 'value' => $exam_order_no]]]);
    $disable_assignment = !empty($marks_entries) && !is_wp_error($marks_entries);
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    // Display assignment form
    render_form_31_assignment_interface($entry_id, $examiner_users, $invigilator_users, $assigned_examiners, $assigned_invigilators, $field_188_values, $method_dates, $disable_assignment, $tomorrow);
}

/**
 * Render Form 31 assignment interface
 */
function render_form_31_assignment_interface($entry_id, $examiner_users, $invigilator_users, $assigned_examiners, $assigned_invigilators, $field_188_values, $method_dates, $disable_assignment, $tomorrow) {
    ?>
    <div id="side-sortables" class="meta-box-sortables ui-sortable">
        <div id="submitdiv" class="postbox">
            <div class="postbox-header">
                <h2 class="hndle ui-sortable-handle">Assign Examiner and Invigilator for Renew/Recertification</h2>
            </div>
            <div class="inside Examiners_label p-4">
                <form id="assign-users-form-31">
                    <?php wp_nonce_field('assign_users_nonce_31', 'assign_users_nonce_field_31'); ?>
                    
                    <h3 class="text-lg font-semibold mb-3">Select Examiners:</h3>
                    <?php foreach ((array)$examiner_users as $user_id):
                        $user = get_userdata($user_id);
                        if ($user): ?>
                            <label class="block mb-2">
                                <input type="checkbox" name="assigned_examiners[]" value="<?= esc_attr($user_id) ?>" <?= in_array($user_id, $assigned_examiners) ? 'checked' : '' ?> class="mr-2">
                                <?= esc_html($user->display_name) ?>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <h3 class="text-lg font-semibold mt-4 mb-3">Select Invigilators:</h3>
                    <?php foreach ((array)$invigilator_users as $user_id):
                        $user = get_userdata($user_id);
                        if ($user): ?>
                            <label class="block mb-2">
                                <input type="checkbox" name="assigned_invigilators[]" value="<?= esc_attr($user_id) ?>" <?= in_array($user_id, $assigned_invigilators) ? 'checked' : '' ?> class="mr-2">
                                <?= esc_html($user->display_name) ?>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <?php if (!($field_188_values)): ?>
                        <h3 class="text-lg font-semibold mt-4 mb-3">Exam Slots</h3>
                        
                            <div class="mb-4 border border-gray-200 rounded-lg p-4 bg-gray-50 method-slot">
                                <h4 class="font-semibold mb-3"><?= esc_html($method) ?> Exam Slots</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label>Slot 1 Date <span class="text-red-500">*</span></label>
                                        <input type="date" name="method_slots[<?= esc_attr($method) ?>][slot_1_date]" min="<?= esc_attr($tomorrow) ?>" value="<?= esc_attr($method_dates[$method]['slot_1']['date'] ?? '') ?>" class="w-full p-2 border rounded" required />
                                    </div>
                                    <div>
                                        <label>Slot 1 Time <span class="text-red-500">*</span></label>
                                        <input type="time" name="method_slots[<?= esc_attr($method) ?>][slot_1_time]" value="<?= esc_attr($method_dates[$method]['slot_1']['time'] ?? '') ?>" class="w-full p-2 border rounded" required />
                                    </div>
                                </div>
                            </div>
                        
                    <?php endif; ?>
                    
                    <input type="hidden" name="entry_id" value="<?= esc_attr($entry_id) ?>">
                    <div class="mt-4 text-center">
                        <input type="button" class="button button-primary save-assignment-31" value="Save Assignments" <?= $disable_assignment ? 'disabled' : '' ?> />
                    </div>
                    <?php if ($disable_assignment): ?>
                        <p class="text-green-700 font-semibold text-center mt-2">Marks already entered. No further assignment needed.</p>
                    <?php endif; ?>
                    <div id="assign-response-31" class="mt-3 text-center"></div>
                </form>
            </div>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            $('.save-assignment-31').on('click', function(e) {
                e.preventDefault();
                $('#assign-response-31').html('<div class="text-blue-600">Processing...</div>');
                
                let examiner_ids = [], invigilator_ids = [], method_slots = {};
                $('input[name="assigned_examiners[]"]:checked').each(function() {
                    examiner_ids.push($(this).val());
                });
                $('input[name="assigned_invigilators[]"]:checked').each(function() {
                    invigilator_ids.push($(this).val());
                });
                
                $('input[name^="method_slots"]').each(function() {
                    let name = $(this).attr('name'), value = $(this).val();
                    let parts = name.split(/\[|\]/).filter(p => p !== '');
                    if (parts.length === 3) {
                        let method = parts[1], combined = parts[2];
                        let match = combined.match(/(slot_\d+)_(date|time)/);
                        if (match) {
                            let slot = match[1], type = match[2];
                            method_slots[method] = method_slots[method] || {};
                            method_slots[method][slot] = method_slots[method][slot] || {};
                            method_slots[method][slot][type] = value;
                        }
                    }
                });

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'save_form_31_assignments',
                        entry_id: $('input[name="entry_id"]').val(),
                        assigned_examiners: examiner_ids,
                        assigned_invigilators: invigilator_ids,
                        method_slots: method_slots,
                        _nonce: $('#assign_users_nonce_field_31').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Form 31 assignments saved successfully.',
                                icon: 'success'
                            }).then(() => location.reload());
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: response.data || 'Assignment failed.'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'AJAX request failed.'
                        });
                        $('#assign-response-31').html('');
                    }
                });
            });
        });
    </script>
    <?php
}

/**
 * Handle Form 31 assignment saving via AJAX
 */
add_action('wp_ajax_save_form_31_assignments', 'handle_form_31_assignments_ajax');
function handle_form_31_assignments_ajax() {
    if (!wp_verify_nonce($_POST['_nonce'], 'assign_users_nonce_31')) {
        wp_send_json_error('Security check failed.');
    }

    $entry_id = absint($_POST['entry_id']);
    $entry = GFAPI::get_entry($entry_id);
    
    if (is_wp_error($entry) || $entry['form_id'] != 39) {
        wp_send_json_error('Renew/Recertification application-form not found.');
    }

    $assigned_examiners = array_map('intval', $_POST['assigned_examiners'] ?? []);
    $assigned_invigilators = array_map('intval', $_POST['assigned_invigilators'] ?? []);
    $method_slots = $_POST['method_slots'] ?? [];

    // Sanitize method slots
    $sanitized_method_slots = [];
    foreach ($method_slots as $method => $slots) {
        foreach ($slots as $slot_key => $slot_values) {
            if (!empty($slot_values['date']) && !empty($slot_values['time'])) {
                $sanitized_method_slots[sanitize_text_field($method)][$slot_key] = [
                    'date' => sanitize_text_field($slot_values['date']),
                    'time' => sanitize_text_field($slot_values['time']),
                ];
            }
        }
    }

    // Save assignments
    gform_update_meta($entry_id, '_method_slots', $sanitized_method_slots);
    gform_update_meta($entry_id, '_assigned_examiners', $assigned_examiners);
    gform_update_meta($entry_id, '_assigned_invigilators', $assigned_invigilators);

    // Update user assigned entries
    $roles = ['examiner' => $assigned_examiners, 'invigilator' => $assigned_invigilators];
    foreach ($roles as $role => $users) {
        foreach ($users as $user_id) {
            $meta_key = "_assigned_entries_{$role}";
            $entries = get_user_meta($user_id, $meta_key, true) ?: [];
            if (!in_array($entry_id, $entries)) {
                $entries[] = $entry_id;
                update_user_meta($user_id, $meta_key, $entries);
            }
        }
    }

    wp_send_json_success('Form 39 assignments saved successfully.');
}

/**
 * Handle Form 31 approval via AJAX
 */
add_action('wp_ajax_form_31_approve_entry_ajax', 'form_31_handle_approve_entry_ajax');
function form_31_handle_approve_entry_ajax() {
    check_ajax_referer('approve_nonce', 'nonce');

    $entry_id = intval($_POST['entry_id']);
    $user_id = intval($_POST['user_id']);
    
    if (!$entry_id || !$user_id) {
        wp_send_json_error('Invalid Entry or User ID.');
    }

    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry) || $entry['form_id'] != 39) {
        wp_send_json_error('Renew/Recertification application-form not found.');
    }

    $user_info = get_userdata($user_id);
    
    // Save approval status
    gform_update_meta($entry_id, 'approval_status', 'approved');
    gform_update_meta($entry_id, 'approved_by', get_current_user_id());
    gform_update_meta($entry_id, 'approval_time', current_time('mysql'));

    // Send notification email
    $field_789_value = rgar($entry, '789'); // Update based on Form 31 structure
    $center_name = trim(rgar($entry, '833')); // Update based on Form 31 structure

    $user_subject = "🎉 Congratulations! Your Renewal Exam Form Has Been Approved";
    $user_message = '<p>Dear ' . esc_html($user_info->display_name) . ',</p>';
    $user_message .= "<p>Your application for the <strong>Renewal Examination</strong> has been <strong>approved</strong>.</p>";
    $user_message .= '<strong>Order Number:</strong>' . $field_789_value . '<br>';
    $user_message .= '<strong>Examination Center:</strong> ' . $center_name . '<br><br>';
    $user_message .= '<p>Best regards,<br>NDTSS Examination Team</p>';

    add_filter('wp_mail_content_type', function () { return 'text/html'; });
    wp_mail($user_info->user_email, $user_subject, $user_message);
    remove_filter('wp_mail_content_type', function () { return 'text/html'; });

    wp_send_json_success(['message' => 'Form 31 renewal exam approved successfully.']);
}

/**
 * Handle Form 31 rejection via AJAX
 */
add_action('wp_ajax_form_31_reject_entry_ajax', 'form_31_handle_reject_entry_ajax');
function form_31_handle_reject_entry_ajax() {
    check_ajax_referer('reject_nonce', 'nonce');

    $entry_id = intval($_POST['entry_id']);
    $user_id = intval($_POST['user_id']);
    $reject_reason = sanitize_textarea_field($_POST['reject_reason']);

    $entry = GFAPI::get_entry($entry_id);
    if (!$entry || is_wp_error($entry) || $entry['form_id'] != 39) {
        wp_send_json_error('Renew/Recertification application-form not found.');
    }

    // Save rejection status
    gform_update_meta($entry_id, 'approval_status', 'rejected');
    gform_update_meta($entry_id, 'rejected_by', get_current_user_id());
    gform_update_meta($entry_id, 'rejection_reason', $reject_reason);

    // Send notification
    $user_info = get_userdata($user_id);
    $user_subject = "Renewal Exam Application - Update Required";
    $user_message = '<p>Dear ' . esc_html($user_info->display_name) . ',</p>';
    $user_message .= '<p>Your renewal exam application requires attention.</p>';
    $user_message .= '<p><strong>Reason:</strong> ' . esc_html($reject_reason) . '</p>';

    add_filter('wp_mail_content_type', function () { return 'text/html'; });
    wp_mail($user_info->user_email, $user_subject, $user_message);
    remove_filter('wp_mail_content_type', function () { return 'text/html'; });

    wp_send_json_success(['message' => 'Form 31 renewal exam rejected successfully.']);
}

/**
 * Display Form 31 entries in submitted forms listing
 */
function display_form_31_entries_page() {
    if (!class_exists('GFAPI')) {
        echo '<p class="text-red-600">Gravity Forms plugin is not active.</p>';
        return;
    }
    
    $field_map = [
        'certificate_no' => '13',
        'exam_order_no' => '12',    // Update field ID
        'candidate_name' => '19',     // Update field ID
        'user_email' => '31',        // Update field ID
        'prefer_center' => '9',    // Update field ID
        'methods' => '1',
        
    ];
    $form_id = '39';

    // Server-side pagination
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $rows_per_page = 10;
    $search_criteria = [
        'start_date' => null,
        'end_date' => null,
    ];
    $paging = [
        'offset' => ($current_page - 1) * $rows_per_page,
        'page_size' => $rows_per_page,
    ];
    $sorting = array(
        'key'       => 'date_created',
        'direction' => 'DESC'
    );
    //$entries = GFAPI::get_entries($form_id, $search_criteria, [], $paging);
    $entries = GFAPI::get_entries($form_id,$search_criteria, $paging);
    $total_entries = GFAPI::count_entries($form_id, $search_criteria);

    if (is_wp_error($entries) || empty($entries)) {
        echo '<p class="text-red-600">Renew/Recertification application-form not found.</p>';
        return;
    }

    // User access control
    $current_user = wp_get_current_user();
    $is_super_admin = current_user_can('administrator');
    $is_aqb_admin = current_user_can('custom_aqb');
    $is_center_admin = current_user_can('custom_center');
    $manager = current_user_can('manager_admin');
    $allowed_center_name = null;

    $allowed_centers = [];

    if ($is_aqb_admin) {
        $center_ids = (array) get_user_meta($current_user->ID, '_exam_center_aqb_admin', true);
        if (!empty($center_ids)) {
            foreach ($center_ids as $center_id) {
                $center_title = get_the_title($center_id);
                if ($center_title) {
                    $allowed_centers[] = $center_title;
                }
            }
        }
    }

    if ($is_center_admin) {
        $center_ids = (array) get_user_meta($current_user->ID, '_exam_center_center_admin', true);
        if (!empty($center_ids)) {
            foreach ($center_ids as $center_id) {
                $center_title = get_the_title($center_id);
                if ($center_title) {
                    $allowed_centers[] = $center_title;
                }
            }
        }
    }

    $entries = GFAPI::get_entries(39, [], [], ['offset' => 0, 'page_size' => 999]);
    
    if (is_wp_error($entries) || empty($entries)) {
        echo '<div class="wrap"><h1>Renewal/Recertification by Exam</h1>';
        echo '<p>No renewal exam entries found.</p></div>';
        return;
    }

    echo '<div class="wrap"><h1>Renewal/Recertification by Exam</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>Certificate Number</th><th>Exam Order No</th><th>Candidate Name</th><th>User Email</th><th>Prefer Center</th><th>Methods</th><th>Status</th><th>Date</th><th>Actions</th>';
    echo '</tr></thead><tbody>';
    

    foreach ($entries as $entry) {
        $entry_id = $entry['id'];
        $certificate_no = rgar($entry, $field_map['certificate_no']);
        $exam_order_no = rgar($entry, $field_map['exam_order_no']);
        $candidate_name = rgar($entry, $field_map['candidate_name']);
        $user_email = rgar($entry, $field_map['user_email']);
        $prefer_center = rgar($entry, $field_map['prefer_center']);
        $status = gform_get_meta($entry_id, 'approval_status') ?: 'pending';
        $date_created = date('d/m/Y', strtotime($entry['date_created']));
        $methods_display = rgar($entry, $field_map['methods']);
        $status_class = $status === 'approved' ? 'status-approved' : ($status === 'rejected' ? 'status-rejected' : 'status-pending');
        $status_badge = '<span class="' . $status_class . '">' . ucfirst($status) . '</span>';
         // Skip if not super admin and not in allowed centers
         if (!$is_super_admin && !in_array($prefer_center, $allowed_centers, true)) {
            continue;
        }

        // For super admin, only apply center filter if explicitly set
        if (!$is_super_admin) {
            if ($manager && $center_filter && $prefer_center !== $center_filter) {
                continue;
            }
            if (!$manager && !in_array($prefer_center, $allowed_centers, true)) {
                continue;
            }
        } else if ($center_filter && $prefer_center !== $center_filter) {
            // Even for super admin, respect the center filter if it's set
            continue;
        }
        if ($status_filter && $status !== $status_filter) {
            continue;
        }
        if ($method_filter && ($is_retest ? $method_filter !== ($selected_labels[0] ?? '') : !in_array($method_filter, $selected_labels))) {
            continue;
        }
        if ($name_filter && stripos($user_email . ' ' . $candidate_name, $name_filter) === false) {
            continue;
        }


        echo '<tr>';
        echo '<td>' . esc_html($certificate_no ?: 'N/A') . '</td>';
        echo '<td>' . esc_html($exam_order_no ?: 'N/A') . '</td>';
        echo '<td>' . esc_html($candidate_name ?: 'N/A') . '</td>';
        echo '<td>' . esc_html($user_email ?: 'N/A') . '</td>';
        echo '<td>' . esc_html($prefer_center ?: 'N/A') . '</td>';
        echo '<td>' . esc_html($methods_display) . '</td>';
        echo '<td>' . $status_badge . '</td>';
        echo '<td>' . esc_html($date_created) . '</td>';
        echo '<td><a href="' . esc_url("admin.php?page=gf_entries&view=entry&id=39&lid={$entry_id}") . '" class="button button-primary button-small">View</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';

    // CSS for status badges
    echo '<style>
    .status-approved { background: #46b450; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px; }
    .status-rejected { background: #dc3232; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px; }
    .status-pending { background: #ffb900; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px; }
    </style>';
}

/**
 * Add Form 31 menu page
 */
// add_action('admin_menu', 'add_form_31_admin_menu');
// function add_form_31_admin_menu() {
//     add_submenu_page(
//         'edit.php?post_type=exam_center',
//         'Renew/Recert By Exam',
//         'Renew/Recert By Exam',
//         'manage_options',
//         'form-31-entries',
//         'display_form_31_entries_page'
//     );
// }

/**
 * Add Form 31 approval/rejection buttons to entry detail
 */
//add_action('gform_entry_detail_sidebar_before', 'add_form_31_approval_buttons', 10, 2);
function add_form_31_approval_buttons($form, $entry) {
    if ((int)$form['id'] !== 39) {
        return;
    }

    $status = gform_get_meta($entry['id'], 'approval_status') ?: 'pending';
    $approve_disabled = ($status === 'approved') ? 'disabled' : '';
    $reject_disabled = ($status === 'rejected') ? 'disabled' : '';
    
    echo '<div class="custom_actions" style="margin-bottom: 15px;">';
    echo '<div class="status-container"><strong>Status: </strong><span class="status-label ' . esc_attr($status) . '">' . ucfirst($status) . '</span></div>';
    
    if (current_user_can('administrator')) {
        echo '<div class="form-31-approval-actions" style="margin-top: 10px;">';
        echo '<button id="form-31-approve-button" class="button button-primary" ' . $approve_disabled . ' 
              data-entry-id="' . esc_attr($entry['id']) . '" 
              data-user-id="' . esc_attr($entry['created_by']) . '">
              Approve
              </button>';
        echo '<button id="form-31-reject-button" class="button button-secondary" ' . $reject_disabled . ' 
              data-entry-id="' . esc_attr($entry['id']) . '" 
              data-user-id="' . esc_attr($entry['created_by']) . '">
              Reject
              </button>';
        echo '</div>';
    }
    echo '</div>';

    // Add JavaScript for approval/rejection handling
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#form-31-approve-button').on('click', function() {
            var entryId = $(this).data('entry-id');
            var userId = $(this).data('user-id');
            
            Swal.fire({
                title: 'Approve Form 31?',
                text: 'This will approve the renewal exam application.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, approve it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'form_31_approve_entry_ajax',
                            entry_id: entryId,
                            user_id: userId,
                            nonce: '<?php echo wp_create_nonce('approve_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Approved!', 'Form 31 approved successfully.', 'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Error!', response.data, 'error');
                            }
                        }
                    });
                }
            });
        });

        $('#form-31-reject-button').on('click', function() {
            var entryId = $(this).data('entry-id');
            var userId = $(this).data('user-id');
            
            Swal.fire({
                title: 'Reject Form 39?',
                input: 'textarea',
                inputLabel: 'Rejection Reason',
                inputPlaceholder: 'Please provide a reason for rejection...',
                inputValidator: (value) => {
                    if (!value) return 'You need to provide a rejection reason!';
                },
                showCancelButton: true,
                confirmButtonText: 'Reject'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'form_31_reject_entry_ajax',
                            entry_id: entryId,
                            user_id: userId,
                            reject_reason: result.value,
                            nonce: '<?php echo wp_create_nonce('reject_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Rejected!', 'Form 39 rejected successfully.', 'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Error!', response.data, 'error');
                            }
                        }
                    });
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Link Form 31 entry to exam center on submission
 * Replicates the same functionality as Form 15
 */
add_action('gform_after_submission_39', 'link_form_31_to_exam_center', 11, 2);
function link_form_31_to_exam_center($entry, $form) {
    $entry_id = rgar($entry, 'id');
    if (!$entry_id) return;
    
    // Get the preferred center name from field ID 833 (same as Form 15)
    $center_name = rgar($entry, '9');
    if (empty($center_name)) return;
    
    // Find the exam_center post by title
    $center_post = get_page_by_title($center_name, OBJECT, 'exam_center');
    if ($center_post) {
        // Link the entry to the exam center
        gform_update_meta($entry_id, '_linked_exam_center', $center_post->ID);
    }
}

// Include this file in your functions.php or wp-config.php:
// require_once get_stylesheet_directory() . '/panel/form-31-workflow.php';
?>