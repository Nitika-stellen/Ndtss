<?php

add_filter('gform_entry_list', 'restrict_center_admin_form_12_entries', 10, 2);
function restrict_center_admin_form_12_entries($query, $form_id) {
    $current_user = wp_get_current_user();
    $user_roles = $current_user->roles;
    if (in_array('center_admin', $user_roles)) {
        $query['paging']['total_count'] = 0;
        $query['entries'] = array();
    }
    return $query;
}
add_action('pre_get_posts', 'restrict_center_admin_from_form_12');
function restrict_center_admin_from_form_12($query) {
    $current_user = wp_get_current_user();
    $user_roles = $current_user->roles;
    if (in_array('center_admin', $user_roles))
    {
        global $pagenow;
        if ($pagenow == 'admin.php' && isset($_GET['id']) && $_GET['id'] == 12) {
            wp_die('You are not allowed to view this form.');
        }
    }
}
add_filter('gform_form_list_forms', 'restrict_center_admin_from_form_list');
function restrict_center_admin_from_form_list($forms) {
    if (current_user_can('center_admin')) {
        foreach ($forms as $key => $form) {
            if ($form->id == 12) {
                unset($forms[$key]);
            }
        }
    }    
    return $forms;
}
add_action('current_screen', 'restrict_center_admin_access_to_form_12');
function restrict_center_admin_access_to_form_12() {
    if (is_admin() && current_user_can('center_admin')) {
        if (isset($_GET['id']) && $_GET['id'] == 12) {
            wp_die('You are not allowed to access this form.');
        }
    }
}

function remove_gravity_forms_capabilities() {
    $center_admin = get_role('center_admin');
    if ($center_admin) {
        $center_admin->remove_cap('gravityforms_edit_forms');
        $center_admin->remove_cap('gravityforms_delete_forms');
        $center_admin->remove_cap('gravityforms_create_form');
    }
}
add_action('admin_init', 'remove_gravity_forms_capabilities');


add_filter('gform_pre_render_12', 'set_price_for_members');
add_filter('gform_pre_process_12', 'set_price_for_members');
add_filter('gform_pre_submission_12', 'set_price_for_members');

function set_price_for_members($form) {
    $current_user = wp_get_current_user();
    $user_roles = $current_user->roles;
    $post_id = get_the_ID();
    $member_price = get_post_meta($post_id, 'member_price', true); 
    $event_cost = get_post_meta($post_id, '_EventCost', true); 
    $member_price = !empty($member_price) ? $member_price : $event_cost; 
    $event_cost = !empty($event_cost) ? $event_cost : 0; 
    foreach ($form['fields'] as &$field) {
        if ($field->label == 'Price') {
            if (in_array('member', $user_roles)) {
                $field->defaultValue = $member_price;
            } else {
                $field->defaultValue = $event_cost;
            }
            $field->cssClass .= ' readonly-price';
        }
    }
    return $form;
}
add_filter('gform_pre_render', 'set_price_for_members');
add_filter('gform_pre_submission_filter', 'set_price_for_members');

add_action('gform_entry_detail_sidebar_before', 'add_back_button_to_entry_detail', 10, 2);
function add_back_button_to_entry_detail($form, $entry) {
    if (in_array($form['id'], array(4, 5, 12, 15,30))) {
        $back_urls = [
            4 => admin_url('/admin.php?page=corporate-membership-forms'),
            5 => admin_url('/admin.php?page=individual-membership-forms'),
            12 => admin_url('/edit.php?post_type=tribe_events&page=event-registrations'),
            15 => admin_url('/edit.php?post_type=exam_center&page=submitted-forms'),
            30 => admin_url('/edit.php?post_type=exam_center&page=retest-forms'),
        ];
        $back_url = $back_urls[$form['id']] ?? '';
        $approver_id = get_current_user_id();
        $status = '';
        $approve_disabled = '';
        $reject_disabled = '';
        $status_label = '';
        $form_id = $form['id'];

        if (in_array($form_id, [4, 5])) {
            $status = get_user_meta($entry['created_by'], 'membership_approval_status', true);
        } elseif (in_array($form_id, [15, 30]) ) {
            $status = gform_get_meta($entry['id'], 'approval_status');
            $status_approved_by = gform_get_meta($entry['id'], 'approved_by');
            $status_rejected_by = gform_get_meta($entry['id'], 'rejected_by');

        } elseif ($form_id == 12 && isset($entry['source_id'])) {
            $status = get_user_meta($entry['created_by'], 'event_' . $entry['source_id'] . '_approval_status', true);
        }
        if (!$status) {
            $status = 'pending';
        }


        if ($status === 'approved') {
            $approve_disabled = 'disabled';
        }
        if ($status === 'rejected') {
            $reject_disabled = 'disabled';
        }
        if ($status === 'cancelled') {
            $approve_disabled = 'disabled';
            $reject_disabled = 'disabled';
        }
        $status_label = '<span class="status-label ' . esc_attr($status) . '">' . ucfirst($status) . '</span>';
        echo '<div class="back-button-container" style="margin-bottom: 15px;">
        <a href="' . esc_url($back_url) . '" class="button button-primary">Back to Entries</a>
        </div>';
        echo '<div class="custom_actions">
        <div class="status-container"><strong>Status: </strong>' . $status_label . '</div>';
        if (in_array($form_id, [4, 5])) {
            $approve_button_id = 'membership-approve-button';
            $reject_button_id  = 'membership-reject-button';
            $action_type       = 'membership';
        } elseif (in_array($form_id, [15, 30])) {
            $approve_button_id = 'exam-approve-button';
            $reject_button_id  = 'exam-reject-button';
            $action_type       = 'exam';
        } else {
            $approve_button_id = 'approve-button';
            $reject_button_id  = 'reject-button';
            $action_type       = 'event';
        }


        if (
            current_user_can( 'administrator' ) || 
            current_user_has_micro_permission( 'exam_approve-reject-the-exam-form' )
        ) {
            echo '<div class="event-approval-actions">
            <button id="' . esc_attr( $approve_button_id ) . '" ' . $approve_disabled . ' class="button button-primary" data-entry="' . esc_attr( $entry['id'] ) . '" data-user="' . esc_attr( $entry['created_by'] ) . '" data-approver="' . esc_attr( $approver_id ) . '" data-event="' . esc_attr( $entry['source_id'] ?? '' ) . '">Approve</button>
            <button id="' . esc_attr( $reject_button_id ) . '" ' . $reject_disabled . ' class="button button-secondary" data-entry="' . esc_attr( $entry['id'] ) . '" data-user="' . esc_attr( $entry['created_by'] ) . '" data-event="' . esc_attr( $entry['source_id'] ?? '' ) . '">Reject</button>
            </div>';
             if($status_approved_by){
                $approver_info = get_userdata($status_approved_by);
                if($approver_info){
                    echo '<p class="approved-by-info" style="margin-top:10px;"><strong>Approved By:</strong> ' . esc_html($approver_info->display_name) . '</p>';
                }
            }
             if($status_rejected_by){
                $rejector_info = get_userdata($status_rejected_by);
                if($rejector_info){
                    echo '<p class="approved-by-info" style="margin-top:10px;"><strong>Rejected By:</strong> ' . esc_html($rejector_info->display_name) . '</p>';
                }
            }
        }
        echo '</div>';
    }
}
add_action('admin_footer', function () {
    ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" ></script>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            function handleApproval(buttonId, action) {
                $(buttonId).on('click', function(e) {
                    e.preventDefault();
                    var entry_id = $(this).data('entry');
                    var user_id = $(this).data('user');
                    var event_id = $(this).data('event');
                    var approver_id = $(this).data('approver');

                    Swal.fire({
                        title: 'Are you sure?',
                        text: 'Do you want to approve this entry?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, approve it!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Processing...',
                                text: 'Please wait while we approve the entry.',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                didOpen: () => Swal.showLoading()
                            });

                            $.post(ajaxurl, {
                                action: action,
                                entry_id: entry_id,
                                user_id: user_id,
                                event_id: event_id,
                                approver_id: approver_id,
                                nonce: '<?php echo wp_create_nonce('approve_nonce'); ?>'
                            }, function(response) {
                               console.log(response);
                               Swal.fire({
                                title: response.success ? 'Approved!' : 'Error',
                                text: response.success ? 'The entry has been approved.' : response.data,
                                icon: response.success ? 'success' : 'error'
                            });
                               location.reload();
                           });
                        }
                    });
                });
            }

            function handleRejection(buttonId, action) {
                $(buttonId).on('click', function(e) {
                    e.preventDefault();
                    var entry_id = $(this).data('entry');
                    var user_id = $(this).data('user');
                    var event_id = $(this).data('event');

                    Swal.fire({
                        title: 'Reject Entry',
                        text: 'Please provide a reason for rejection:',
                        input: 'text',
                        inputPlaceholder: 'Enter rejection reason',
                        showCancelButton: true,
                        confirmButtonText: 'Reject',
                        cancelButtonText: 'Cancel',
                        showLoaderOnConfirm: true,
                        inputValidator: (value) => value ? null : 'You need to provide a reason!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Processing...',
                                text: 'Please wait while we reject the entry.',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                didOpen: () => Swal.showLoading()
                            });

                            $.post(ajaxurl, {
                                action: action,
                                entry_id: entry_id,
                                user_id: user_id,
                                event_id: event_id,
                                reject_reason: result.value,
                                nonce: '<?php echo wp_create_nonce('reject_nonce'); ?>'
                            }, function(response) {
                                //console.log(response);
                                Swal.fire({
                                    title: response.success ? 'Rejected!' : 'Error',
                                    text: response.success ? 'The entry has been rejected.' : response.data,
                                    icon: response.success ? 'success' : 'error'
                                });
                                location.reload();
                            });
                        }
                    });
                });
            }

            handleApproval('#approve-button', 'event_approve_entry_ajax');
            handleApproval('#membership-approve-button', 'membership_approve_entry_ajax');
            handleApproval('#exam-approve-button', 'exam_approve_entry_ajax');
            handleRejection('#reject-button', 'event_reject_entry_ajax');
            handleRejection('#membership-reject-button', 'membership_reject_entry_ajax');
            handleRejection('#exam-reject-button', 'exam_reject_entry_ajax');

        });
    </script>
    <?php
});



//add_action('wp_ajax_event_reject_entry_ajax', 'event_handle_reject_entry_ajax');
function event_handle_reject_entry_ajaxs() {
    check_ajax_referer('reject_nonce', 'nonce');
    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $reject_reason = isset($_POST['reject_reason']) ? sanitize_text_field($_POST['reject_reason']) : '';
    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry)) {
        wp_send_json_error('Invalid Entry.');
    }
    $form_id = $entry['form_id'];
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    if ($form_id != 4 && $form_id != 5 && !$event_id) {
        wp_send_json_error('Missing Event ID.');
    }
    if (!$entry_id || !$user_id || !$reject_reason) {
        wp_send_json_error('Missing parameters.');
    }
    update_user_meta($user_id, 'event_' . $event_id . '_approval_status', 'rejected');
    update_user_meta($user_id, 'event_' . $event_id . '_reject_reason', $reject_reason);   
    $event_name = get_the_title($event_id);    
    $user_info = get_userdata($user_id);
    $user_email = $user_info->user_email;
    $admin_email = get_option('admin_email');
    $user_subject = 'Your Form has been Rejected';
    $user_message = 'Hello ' . $user_info->display_name . ',<br> <br> Your form for ' . $event_name . ' has been rejected for the reason: <strong>' . $reject_reason . '</strong> <br> <br> Please contact '.$admin_email.' for more information.';
    
    $admin_subject = 'Form Rejected Notification';
    $admin_message = 'The form for ' . $event_name . ' for user <strong>' . $user_info->display_name . '</strong> has been rejected for the reason:<strong>' . $reject_reason . '</strong>';
    $user_data = get_email_template($user_subject, $user_message);
    $admin_data = get_email_template($admin_subject, $admin_message);
    add_filter('wp_mail_content_type', function() { return 'text/html'; });
    wp_mail($user_email, $user_subject, $user_data);
    wp_mail($admin_email, $admin_subject, $admin_data);
    remove_filter('wp_mail_content_type', function() { return 'text/html'; });
    $user = new WP_User($user_id);
    $user->remove_role('member');
    wp_send_json_success();
}




//add_action('wp_ajax_event_approve_entry_ajax', 'event_handle_approve_entry_ajax');
function event_handle_approve_entry_ajaxs() {
    // Include the PHP QR Code library from the child theme
    $library_path = get_stylesheet_directory() . '/phpqrcode/qrlib.php';
    if (!file_exists($library_path)) {
        error_log('QR library not found at: ' . $library_path);
        wp_send_json_error('QR library not found.');
    }
    require_once $library_path;

    check_ajax_referer('approve_nonce', 'nonce');

    // Get the required data from AJAX
    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $approver_id = intval($_POST['approver_id']);

    if (!$entry_id || !$user_id) {
        wp_send_json_error('Invalid Entry, User ID, or Event.');
    }

    $user_info = get_userdata($user_id);
    $user_email = $user_info->user_email;
    $event_name = get_the_title($event_id);

    // Get event details
    $event_start = get_post_meta($event_id, '_EventStartDate', true);
    $event_end = get_post_meta($event_id, '_EventEndDate', true);
    $event_start_time = get_post_meta($event_id, '_EventStartTime', true);
    $event_end_time = get_post_meta($event_id, '_EventEndTime', true);

    // Format the event start and end date and time
    $event_start_datetime = date('F j, Y, g:i a', strtotime($event_start . ' ' . $event_start_time));
    $event_end_datetime = date('F j, Y, g:i a', strtotime($event_end . ' ' . $event_end_time));

    // Generate a unique token for verification
    $verification_token = wp_generate_password(20, false);

    // Prepare QR code data safely
    $verification_page_url = site_url('/verify-qr-code'); // URL of the verification page
    $qr_data = $verification_page_url . '?user_id=' . $user_id 
    . '&event_id=' . $event_id 
    . '&token=' . $verification_token 
    . '&starttime=' . urlencode($event_start_datetime) 
    . '&endtime=' . urlencode($event_end_datetime)
    . '&nocache=' . wp_generate_password(20, false) . rand(1000, 9999);  



    // Save the QR Code as a PNG file
    $upload_dir = wp_upload_dir();
    $qr_code_path = $upload_dir['path'] . '/qr_code_' . $entry_id . '.png';
    QRcode::png($qr_data, $qr_code_path, QR_ECLEVEL_M); // Medium error correction level

    if (!file_exists($qr_code_path)) {
        error_log('QR code file not created at: ' . $qr_code_path);
        wp_send_json_error('QR code generation failed.');
    }

    // Store the hashed verification token in the database
    $hashed_token = wp_hash_password($verification_token);
    update_user_meta($user_id, 'event_' . $event_id . '_verification_token', $hashed_token);

    // Prepare email content for user
    $user_subject = 'Your Event Registration has been Approved';
    $user_message = '
    <p>Hello ' . $user_info->display_name . ',</p>
    <p>Your registration for Event: <strong>' . $event_name . '</strong> has been approved.</p>
    <p><strong>Event Details:</strong></p>
    <ul>
    <li><strong>Event Name:</strong> ' . $event_name . '</li>
    <li><strong>Event Start:</strong> ' . $event_start_datetime . '</li>
    <li><strong>Event End:</strong> ' . $event_end_datetime . '</li>
    </ul>
    <p>Please find your QR code attached. Use it at the event entrance for verification.</p>
    ';
    $headers = [];
    $attachments = [$qr_code_path];

    // Send email to user
    add_filter('wp_mail_content_type', function() { return 'text/html'; });
    $mail_sent_user = wp_mail($user_email, $user_subject, $user_message, $headers, $attachments);
    remove_filter('wp_mail_content_type', function() { return 'text/html'; });

    if (!$mail_sent_user) {
        error_log('Failed to send email to ' . $user_email);
        wp_send_json_error('User email delivery failed.');
    }

    // Prepare email content for admin
    $admin_email = get_option('admin_email');
    $admin_subject = 'User Registration Approved for Event: ' . $event_name;
    $admin_message = '
    <p>Dear Admin,</p>
    <p>The following user has been approved for the event:</p>
    <ul>
    <li><strong>User Name:</strong> ' . $user_info->display_name . '</li>
    <li><strong>Email:</strong> ' . $user_email . '</li>
    <li><strong>Event Name:</strong> ' . $event_name . '</li>
    <li><strong>Event Start:</strong> ' . $event_start_datetime . '</li>
    <li><strong>Event End:</strong> ' . $event_end_datetime . '</li>
    <li><strong>Approved By:</strong> User ID ' . $approver_id . '</li>
    </ul>
    <p>Best Regards,<br>Your Website Team</p>
    ';

    // Send email to admin
    add_filter('wp_mail_content_type', function() { return 'text/html'; });
    $mail_sent_admin = wp_mail($admin_email, $admin_subject, $admin_message, $headers);
    remove_filter('wp_mail_content_type', function() { return 'text/html'; });

    if (!$mail_sent_admin) {
        error_log('Failed to send email to admin at ' . $admin_email);
    }

    // Update user meta for approval status
    update_user_meta($user_id, 'event_' . $event_id . '_approval_status', 'approved');
    update_user_meta($user_id, 'event_' . $event_id . '_approved_by', $approver_id);

    // Return success response
    wp_send_json_success([
        'message' => 'Event registration approved. Emails sent to user and admin successfully.',
        'qr_code_url' => $verification_page_url
    ]);
}

add_action('wp_ajax_exam_approve_entry_ajax', 'exam_handle_approve_entry_ajax');
function exam_handle_approve_entry_ajax() {
    check_ajax_referer('approve_nonce', 'nonce');

    $entry_id     = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    $user_id      = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $action       = 'approved';
    $approver_id  = get_current_user_id();
    $approver = get_userdata($approver_id);
    $role = !empty($approver->roles) ? $approver->roles[0] : 'unknown';

    $admin_email  = get_option('admin_email');

    if (!$entry_id || !$user_id) {
        wp_send_json_error('Invalid Entry or User ID.');
    }
    $user_info = get_userdata($user_id);
    $user_email = $user_info->user_email;
    $candidate_reg_number = get_user_meta($user_id,'candidate_reg_number',true);

    // ✅ Save current status to entry meta
    gform_update_meta($entry_id, 'approval_status', $action);
    gform_update_meta($entry_id, 'approved_by', $approver_id);
    gform_update_meta($entry_id, 'approval_time', current_time('mysql'));

    // ✅ Log history
    $existing_log = gform_get_meta($entry_id, 'approval_log');
    if (!$existing_log || !is_array($existing_log)) {
        $existing_log = [];
    }
     $entry = GFAPI::get_entry($entry_id); // ✅ Required to read field values like center name
     if ($entry['form_id'] == 30) {
        $field_789_value = rgar($entry, '12');
        $candidate_email = rgar($entry, '26');
        $candidate_name  = rgar($entry, '19');
        $center_name     = trim(rgar($entry, '9'));
    } else {
        $field_789_value = rgar($entry, '789');
        $candidate_email = rgar($entry, '12');
        $candidate_name  = rgar($entry, '840'); 
        $center_name     = trim(rgar($entry, '833')); 
    }
    $form_type = ($entry['form_id'] == 30) ? 'Retest' : 'Initial';


    $center_post     = get_page_by_title($center_name, OBJECT, 'exam_center');
    $center_address = get_post_meta($center_post->ID, 'location', true);



    $existing_log[] = [
        'action' => $action,
        'by'     => $approver_id,
        'role'   => $role,
        'time'   => current_time('mysql'),
        'ip'     => $_SERVER['REMOTE_ADDR'],
    ];

    gform_update_meta($entry_id, 'approval_log', $existing_log);
    // ✅ Email notifications
    $user_subject = "🎉 Congratulations! Your {$form_type} Exam Form Has Been Approved";
    $user_message  = '<p>Dear ' . esc_html($user_info->display_name) . ',</p>';
    $user_message .= "<p>Your application for the upcoming <strong>{$form_type} Examination</strong> has been <strong>approved</strong>.</p>";
    $user_message .= ' <strong>Order Number:</strong>' . $field_789_value.'<br>
    <strong>Examination Center:</strong> '. $center_name.'<br><br>';
    $user_message .= '<p>Please make sure to check your center allocation and further instructions in your email.</p>';
    $user_message .= '<p>We wish you all the best for your exam!</p>';
    $user_message .= '<p>Best regards,<br>NDTSS Examination Team</p>';

    $approver = wp_get_current_user(); // Or use get_userdata(get_current_user_id())
    $approver_name = $approver->display_name;

    $admin_subject = "📢 {$form_type} Exam Form Approved";
    $admin_message  = "<p><strong>{$form_type} Exam Form Approval Notification</strong></p>";
    $admin_message .= '<p>User <strong>' . esc_html($user_info->display_name) . '</strong> (User ID: ' . esc_html($user_id) . ') Registration No: '.$candidate_reg_number.' has been approved by:</strong> ' . ucfirst($approver_name) . '</p>';
    $admin_message .= '<p>This is a system notification from the exam management platform.</p>';

      // ✅ Center admin logic
    $entry = GFAPI::get_entry($entry_id);
    $center_name     = trim(rgar($entry, '833')); 
    $center_post     = get_page_by_title($center_name, OBJECT, 'exam_center');
    $center_admin_id = get_post_meta($center_post->ID, '_center_admin_id', true);
    $aqb_admin_id    = get_post_meta($center_post->ID, '_aqb_admin_id', true);

    $center_admin = get_userdata($center_admin_id);
    $aqb_admin    = get_userdata($aqb_admin_id);

    $admin_users = get_users([
        'role'   => 'administrator',
        'fields' => ['user_email'],
    ]);

    $admin_emails = [];

    if ($center_admin && is_email($center_admin->user_email)) {
        $admin_emails[] = $center_admin->user_email;
    }
    if ($aqb_admin && is_email($aqb_admin->user_email)) {
        $admin_emails[] = $aqb_admin->user_email;
    }

    foreach ($admin_users as $admin_user) {
       if (is_email($admin_user->user_email) && !in_array($admin_user->user_email, $admin_emails)) {
        $admin_emails[] = $admin_user->user_email;
    }
    }

    $admin_emails = array_unique($admin_emails);
    if (function_exists('get_email_template')) {
        $user_data  = get_email_template($user_subject, $user_message);
        $admin_data = get_email_template($admin_subject, $admin_message);
    } else {
        $user_data  = $user_message;
        $admin_data = $admin_message;
    }


    add_filter('wp_mail_content_type', function () { return 'text/html'; });
    wp_mail($user_email, $user_subject, $user_data);
    wp_mail($admin_emails, $admin_subject, $admin_data);
    remove_filter('wp_mail_content_type', function () { return 'text/html'; });

    wp_send_json_success([
        'message' => 'Exam registration approved. Emails sent to user and admin successfully.',
    ]);
}

add_action('wp_ajax_exam_reject_entry_ajax', 'event_handle_exam_reject_entry_ajax');
function event_handle_exam_reject_entry_ajax() {
    check_ajax_referer('reject_nonce', 'nonce');

    $entry_id      = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    $user_id       = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $reject_reason = isset($_POST['reject_reason']) ? sanitize_textarea_field($_POST['reject_reason']) : '';

    if (!$entry_id || !$user_id || empty($reject_reason)) {
        wp_send_json_error('Missing or invalid parameters.');
    }

    $entry = GFAPI::get_entry($entry_id);
    if (!$entry || is_wp_error($entry)) {
        wp_send_json_error('Entry not found.');
    }

    $user_info = get_userdata($user_id);
    if (!$user_info) {
        wp_send_json_error('User not found.');
    }

    $form_type = ($entry['form_id'] == 30) ? 'Retest' : 'Initial';

    $user_email  = $user_info->user_email;
    $admin_email = get_option('admin_email');
    $approver_id = get_current_user_id();
    $approver    = get_userdata($approver_id);
    $role        = !empty($approver->roles) ? $approver->roles[0] : 'unknown';

    // ✅ Update entry meta for rejection
    gform_update_meta($entry_id, 'approval_status', 'rejected');
    gform_update_meta($entry_id, 'rejected_by', $approver_id);
    gform_update_meta($entry_id, 'rejection_time', current_time('mysql'));
    gform_update_meta($entry_id, 'reject_reason', $reject_reason);

    // ✅ Log rejection history
    $existing_log = gform_get_meta($entry_id, 'approval_log');
    if (!$existing_log || !is_array($existing_log)) {
        $existing_log = [];
    }

    $existing_log[] = [
        'action'   => 'rejected',
        'by'       => $approver_id,
        'role'     => $role,
        'reason'   => $reject_reason,
        'time'     => current_time('mysql'),
        'ip'       => $_SERVER['REMOTE_ADDR'],
    ];

    gform_update_meta($entry_id, 'approval_log', $existing_log);

    // ✅ Email notifications
    $user_subject = "❌ Your {$form_type} Exam Form Has Been Rejected";
    $user_message = '<p>Hello ' . esc_html($user_info->display_name) . ',</p>';
    $user_message .= "<p>Your <strong>{$form_type} Exam Form</strong> has been <strong>rejected</strong> for the following reason:</p>";
    $user_message .= '<blockquote><strong>' . esc_html($reject_reason) . '</strong></blockquote>';
    $user_message .= '<p>If you believe this was a mistake, please contact ' . esc_html($admin_email) . '.</p>';

    $approver = wp_get_current_user(); // Or use get_userdata(get_current_user_id())
    $approver_name = $approver->display_name;

    $admin_subject = "📢 {$form_type} Exam Form Rejected";
    $admin_message = '<p><strong>' . esc_html($user_info->display_name) . '</strong>\'s <strong>' . $form_type . ' Exam Form</strong> (Entry ID: ' . $entry_id . ') has been rejected by <strong>' . ucfirst($approver_name) . '</strong></p>';
    $admin_message .= '<p><strong>Reason:</strong> ' . esc_html($reject_reason) . '</p>';

    $entry = GFAPI::get_entry($entry_id);
    $center_name     = trim(rgar($entry, '833')); 
    $center_post     = get_page_by_title($center_name, OBJECT, 'exam_center');
    $center_admin_id = get_post_meta($center_post->ID, '_center_admin_id', true);
    $aqb_admin_id    = get_post_meta($center_post->ID, '_aqb_admin_id', true);

    $center_admin = get_userdata($center_admin_id);
    $aqb_admin    = get_userdata($aqb_admin_id);

    $admin_users = get_users([
        'role'   => 'administrator',
        'fields' => ['user_email'],
    ]);

    $admin_emails = [];

    if ($center_admin && is_email($center_admin->user_email)) {
        $admin_emails[] = $center_admin->user_email;
    }
    if ($aqb_admin && is_email($aqb_admin->user_email)) {
        $admin_emails[] = $aqb_admin->user_email;
    }

    foreach ($admin_users as $admin_user) {
        if (is_email($admin_user->user_email) && !in_array($admin_user->user_email, $admin_emails)) {
            $admin_emails[] = $admin_user->user_email;
        }
    }

    $admin_emails = array_unique($admin_emails);

    if (function_exists('get_email_template')) {
        $user_data  = get_email_template($user_subject, $user_message);
        $admin_data = get_email_template($admin_subject, $admin_message);
    } else {
        $user_data  = $user_message;
        $admin_data = $admin_message;
    }

    add_filter('wp_mail_content_type', function () { return 'text/html'; });
    wp_mail($user_email, $user_subject, $user_data);
    wp_mail($admin_emails, $admin_subject, $admin_data);
    remove_filter('wp_mail_content_type', function () { return 'text/html'; });

    wp_send_json_success(['message' => "{$form_type} Exam Form has been rejected and notifications sent."]);
}

add_action('template_redirect', 'verify_qr_code_at_gate');
function verify_qr_code_at_gate() {
    if (is_page('verify-qr-code')) {

        // Force refresh by adding a cache-busting query param
        if (!isset($_GET['nocache'])) {
            $current_url = home_url(add_query_arg(NULL, NULL)); // Get full current URL
            $redirect_url = add_query_arg('nocache', time(), $current_url); // Add nocache param with timestamp
            wp_redirect($redirect_url);
            exit;
        }

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        // Validate parameters
        if (!$user_id || !$event_id || !$token) {
            echo '<h2 style="color: red;">Invalid or expired QR code!</h2>';
            exit;
        }

        // Fetch the event details
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'tribe_events') {
            echo '<h2 style="color: red;">Invalid event!</h2>';
            exit;
        }

        // Fetch event start and end datetime
        $event_start_date = get_post_meta($event_id, '_EventStartDate', true);
        $event_end_date = get_post_meta($event_id, '_EventEndDate', true);

        // Get current datetime
        $current_time = current_time('Y-m-d H:i:s');

        // Check if event has not started yet
        if ($current_time < $event_start_date) {
            echo '<h2 style="color: red;">Check-in is not allowed before the event starts!</h2>';
            exit;
        }

        // Check if event has already ended
        if ($current_time > $event_end_date) {
            echo '<h2 style="color: red;">This event has already ended!</h2>';
            exit;
        }

        // Retrieve stored token from user meta
        $stored_token = get_user_meta($user_id, 'event_' . $event_id . '_verification_token', true);

        // Verify the token
        if (!$stored_token || !wp_check_password($token, $stored_token)) {
            echo '<h2 style="color: red;">Invalid or expired QR code!</h2>';
            exit;
        }

        // Check if the user is approved for this event
        $approval_status = get_user_meta($user_id, 'event_' . $event_id . '_approval_status', true);
        if ($approval_status !== 'approved') {
            echo '<h2 style="color: red;">You are not registered for this event!</h2>';
            exit;
        }

        // Check if the user is already checked in
        $check_in_status = get_user_meta($user_id, 'event_' . $event_id . '_check_in_status', true);
        if ($check_in_status === 'checked_in') {
            $check_in_time = get_user_meta($user_id, 'event_' . $event_id . '_check_in_time', true);
            if ($check_in_time) {
                echo '<h2 style="color: orange;">You have already checked in at ' . esc_html($check_in_time) . '.</h2>';
            } else {
                echo '<h2 style="color: orange;">You have already checked in.</h2>';
            }
            exit;
        }

        // Everything checks out, proceed with check-in
        update_user_meta($user_id, 'event_' . $event_id . '_check_in_status', 'checked_in');
        update_user_meta($user_id, 'event_' . $event_id . '_check_in_time', current_time('mysql'));

        // Success message
        echo '<h2 style="color: green;">✅ QR code verified successfully!<br>Check-in recorded at ' . current_time('mysql') . '.</h2>';
        exit;
    }
}

add_action('gform_after_update_entry', 'update_exam_center_on_entry_edit_form15', 10, 2);
function update_exam_center_on_entry_edit_form15($entry, $form) {
    $form_id = is_array($form) ? (int) $form['id'] : (int) $form;
    if ($form_id !== 15) {
        return;
    }
    $center_field_id = 833;
    $center_name = rgar($entry, $center_field_id);
    if (!$center_name) {
        return; 
    }
    // Find the exam_center post by title
    $center_post = get_page_by_title($center_name, OBJECT, 'exam_center');
    if ($center_post) {
        $user_id = rgar($entry, 'created_by');
       // update_user_meta($user_id, '_exam_center', $center_post->ID);
        gform_update_meta($entry['id'], '_linked_exam_center', $center_post->ID);
    }
}
