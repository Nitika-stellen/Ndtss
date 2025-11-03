<?php
include get_stylesheet_directory() . '/membership/membership_module.php';
include get_stylesheet_directory() . '/membership/email_template.php';
include get_stylesheet_directory() . '/membership/generate_certificate.php';

add_action('gform_after_submission_4', 'save_membership_data_to_user_meta_and_send_email', 10, 2);
add_action('gform_after_submission_5', 'save_membership_data_to_user_meta_and_send_email', 10, 2);

function save_membership_data_to_user_meta_and_send_email($entry, $form) {
    $user_id = get_current_user_id();

    if ($user_id) {
        $membership_type = ($form['id'] == 5) ? 'individual' : 'corporate';

        update_user_meta($user_id, 'membership_type', $membership_type);
        update_user_meta($user_id, 'membership_approval_status', 'pending');

        // Get user info
        $user_info = get_userdata($user_id);
        $user_email = $user_info->user_email;
        $user_name = $user_info->display_name;
        $submission_date = date("Y-m-d");

        // Email templates
        $email_templates = [
            'user' => [
                'enabled' => get_option('user_ack_submission_enabled', 'yes'),
                'subject' => get_option('user_ack_submission_subject', ''),
                'heading' => get_option('user_ack_submission_heading', ''),
                'message' => get_option('user_ack_submission_message', '')
            ],
            'admin' => [
                'enabled' => get_option('admin_new_submission_enabled', 'yes'),
                'subject' => get_option('admin_new_submission_subject', ''),
                'heading' => get_option('admin_new_submission_heading', ''),
                'message' => get_option('admin_new_submission_message', '')
            ]
        ];

        // Replace placeholders dynamically
        foreach ($email_templates as $role => &$data) {
            if (!empty($data['subject']) && !empty($data['message'])) {
                $data['subject'] = str_replace(
                    ['{user_name}', '{membership_status}', '{submission_date}', '{membership_type}'],
                    [$user_name, 'pending', $submission_date, $membership_type],
                    $data['subject']
                );

                $data['message'] = str_replace(
                    ['{user_name}', '{membership_status}', '{submission_date}', '{membership_type}'],
                    [$user_name, 'pending', $submission_date, $membership_type],
                    $data['message']
                );
            } else {
                $data['enabled'] = 'no';
            }
        }

        // Send email to user
        if ($email_templates['user']['enabled'] === 'yes') {
            send_formatted_email(
                $user_email,
                $email_templates['user']['subject'],
                get_email_template($email_templates['user']['heading'], $email_templates['user']['message'])
            );
        }
         add_filter('wp_mail_content_type', function () {
        return 'text/html';
    });

        // Send email to admin(s) – super admin + membership_admin role
        if ($email_templates['admin']['enabled'] === 'yes') {
            $admin_emails = get_all_membership_admin_emails();
            if (!empty($admin_emails)) {
                 $to = implode(',', $admin_emails); // All admins in To
               // foreach ($admin_emails as $admin_email) {
                    send_formatted_email(
                        $to,
                        $email_templates['admin']['subject'],
                        get_email_template($email_templates['admin']['heading'], $email_templates['admin']['message'])
                    );
               // }
            }
        }
          remove_filter('wp_mail_content_type', function () {
        return 'text/html';
    });
    }
}


function indi_membership_form_submission_shortcode() {
    ob_start();
    if ( !is_user_logged_in() ) {
        wp_redirect(home_url('/sign-in/'));
        return;
    }
    $user_id = get_current_user_id();
    $membership_status = get_user_meta($user_id, 'membership_approval_status', true);
    $membership_type = get_user_meta($user_id, 'membership_type', true);
    /*if ($membership_type) {
        if ( $membership_status == 'approved' ) {
            echo '<p>Congratulations! You are already an approved '.$membership_type.' member. You can now enjoy the full benefits of your membership. No further action is required at this time.</p>';
            return;
        } elseif ( $membership_status == 'pending' ) {
            echo '<p>Your '.$membership_type.' membership application is currently under review. You will receive an update shortly.</p>';
            return;
        } elseif ( $membership_status == 'cancelled' ) {
            echo '<p>Your '.$membership_type.' membership application has been cancelled. Please complete the form below to reapply.</p>';
        } elseif ( $membership_status == 'rejected' ) {
            echo '<p>Your '.$membership_type.' membership application was declined. If you wish to reapply, please fill out the form below.</p>';
            //return;
        }
    } else {
        //echo '<p>Please complete the Individual membership application form below to become a member.</p>';
    }*/
    echo do_shortcode('[gravityform id="5" title="false" description="false" ajax="true"]'); 
  
    return ob_get_clean();
}
function Corporate_membership_form() {
    ob_start();
    if ( !is_user_logged_in() ) {
        wp_redirect(home_url('/sign-in/'));
        return;
    }
    $user_id = get_current_user_id();
    $membership_status = get_user_meta($user_id, 'membership_approval_status', true);
    $membership_type = get_user_meta($user_id, 'membership_type', true);
   /* if ($membership_type) {
        if ( $membership_status == 'approved' ) {
            echo '<p>Congratulations! You are already an approved '.$membership_type.' member. You can now enjoy the full benefits of your membership. No further action is required at this time.</p>';
            return;
        } elseif ( $membership_status == 'pending' ) {
            echo '<p>Your '.$membership_type.' membership application is currently under review. You will receive an update shortly.</p>';
            return;
        } elseif ( $membership_status == 'cancelled' ) {
            echo '<p>Your '.$membership_type.' membership application has been cancelled. Please complete the form below to reapply.</p>';
        } elseif ( $membership_status == 'rejected' ) {
            echo '<p>Your '.$membership_type.' membership application was declined. If you wish to reapply, please fill out the form below.</p>';
            return;
        }
    } else {
        //echo '<p>Please complete the Corporate membership application form below to become a member.</p>';
    }*/
    echo do_shortcode('[gravityform id="4" title="false" description="false" ajax="true"]'); 
  
    return ob_get_clean();
}
add_shortcode('Corporate_membership_form', 'Corporate_membership_form');
add_shortcode('Individual_membership_form', 'indi_membership_form_submission_shortcode');

add_action('wp_ajax_membership_approve_entry_ajax', 'membership_approve_entry_ajax');
function membership_approve_entry_ajax() {
    check_ajax_referer('approve_nonce', 'nonce');

    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $approver_id = intval($_POST['approver_id']);

    if (!$entry_id || !$user_id) {
        wp_send_json_error('Invalid Entry or User ID.');
    }

    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry)) {
        wp_send_json_error('Invalid Entry.');
    }

    $form_id = $entry['form_id'];
    $membership_name = "";
    $membership_duration = 0;

    if ($form_id == 5) {
        $membership_name = "Individual Membership";
        $membership_duration = intval(explode('|', $entry[27])[0]);
        $membership_category =  rgar($entry, '24');
    } elseif ($form_id == 4) {
        $membership_name = "Corporate Membership";
        $membership_duration = intval(explode('|', $entry[31])[0]);
        $membership_category =  "Corporate";
    }

    $user_info = get_userdata($user_id);
    $user_email = $user_info->user_email;
    $user_name = $user_info->display_name;

    $approval_date = new DateTime(current_time('mysql'));
    $expiry_date = clone $approval_date;
    if ($membership_duration > 0) {
        $expiry_date->modify('+' . $membership_duration . ' years');
    }

    $formatted_approval_date = $approval_date->format('F j, Y');
    $formatted_expiry_date = $expiry_date->format('F j, Y');

    $placeholders = [
        '{user_name}' => $user_name,
        '{membership_type}' => $membership_name,
        '{approval_date}' => $formatted_approval_date,
        '{expiry_date}' => $formatted_expiry_date,
        '{approver_id}' => $approver_id
    ];

    // Load dynamic templates
    $email_templates = [
        'user' => [
            'enabled' => get_option('user_ack_status_enabled', 'yes'),
            'subject' => get_option('user_ack_status_subject', ''),
            'heading' => get_option('user_ack_status__heading', ''),
            'message' => get_option('user_ack_status_message', '')
        ],
        'admin' => [
            'enabled' => get_option('admin_status_notification_enabled', 'yes'),
            'subject' => get_option('admin_status_notification_subject', ''),
            'heading' => get_option('admin_status_notification_heading', ''),
            'message' => get_option('admin_status_notification_message', '')
        ]
    ];

    foreach ($email_templates as $role => &$template) {
        if (!empty($template['subject']) && !empty($template['message'])) {
            $template['subject'] = strtr($template['subject'], $placeholders);
            $template['heading'] = strtr($template['heading'], $placeholders);
            $template['message'] = wpautop(strtr($template['message'], $placeholders));
        } else {
            $template['enabled'] = 'no';
        }
    }

    // Send emails
    add_filter('wp_mail_content_type', function () {
        return 'text/html';
    });

    if ($email_templates['user']['enabled'] === 'yes') {
        $user_email_body = get_email_template($email_templates['user']['heading'], $email_templates['user']['message']);
        wp_mail($user_email, $email_templates['user']['subject'], $user_email_body);
    }

    if ($email_templates['admin']['enabled'] === 'yes') {
        $admin_emails = get_all_membership_admin_emails();
        if (!empty($admin_emails)) {
            $to = implode(',', $admin_emails); // Send as one mail to all
            $admin_email_body = get_email_template($email_templates['admin']['heading'], $email_templates['admin']['message']);
            wp_mail($to, $email_templates['admin']['subject'], $admin_email_body);
        }
    }

    remove_filter('wp_mail_content_type', function () {
        return 'text/html';
    });

    // Save membership status
    update_user_meta($user_id, 'membership_approval_status', 'approved');
    update_user_meta($user_id, 'membership_approved_by', $approver_id);
    update_user_meta($user_id, 'membership_approval_date', $approval_date->format('Y-m-d'));
    update_user_meta($user_id, 'membership_expiry_date', $expiry_date->format('Y-m-d'));
    update_user_meta($user_id, 'ind_member_form_entry', $entry_id);
    update_user_meta($user_id, 'member_type', $membership_category);

    // Assign role
    $member_type = strtolower($membership_category).'_member';
    $user = new WP_User($user_id);

    $user->add_role('member');
    $user->add_role( $member_type );

    wp_send_json_success();
}

add_action('wp_ajax_membership_reject_entry_ajax', 'membership_reject_entry_ajax');

function membership_reject_entry_ajax() {
    check_ajax_referer('reject_nonce', 'nonce');

    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $reject_reason = isset($_POST['reject_reason']) ? sanitize_text_field($_POST['reject_reason']) : '';

    if (!$entry_id || !$user_id || !$reject_reason) {
        wp_send_json_error('Missing parameters.');
    }

    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry)) {
        wp_send_json_error('Invalid Entry.');
    }

    $form_id = $entry['form_id'];
    $event_name = $membership_type = "";

    if ($form_id == 5) {
        $event_name = "Individual Membership";
        $membership_type = explode('|', $entry[27])[0];
        $membership_category =  rgar($entry, '24');
    } elseif ($form_id == 4) {
        $event_name = "Corporate Membership";
        $membership_type = explode('|', $entry[31])[0];
        $membership_category =  "Corporate";
    } else {
        wp_send_json_error('Invalid Form ID for Membership.');
    }

    // Update user metadata
    update_user_meta($user_id, 'membership_approval_status', 'rejected');
    update_user_meta($user_id, 'membership_reject_reason', $reject_reason);

    // Get user info
    $user_info = get_userdata($user_id);
    $user_email = $user_info->user_email;

    // Dynamic placeholders
    $placeholders = [
        '{user_name}' => $user_info->display_name,
        '{membership_type}' => $event_name,
        '{reject_reason}' => $reject_reason
    ];

    $email_templates = [
        'user' => [
            'enabled' => get_option('user_rejection_notification_enabled', 'yes'),
            'subject' => get_option('user_rejection_notification_subject', ''),
            'heading' => get_option('user_rejection_notification_heading', ''),
            'message' => get_option('user_rejection_notification_message', '')
        ],
        'admin' => [
            'enabled' => get_option('admin_rejection_notification_enabled', 'yes'),
            'subject' => get_option('admin_rejection_notification_subject', ''),
            'heading' => get_option('admin_rejection_notification_heading', ''),
            'message' => get_option('admin_rejection_notification_message', '')
        ]
    ];

    foreach ($email_templates as $role => &$template) {
        if (!empty($template['subject']) && !empty($template['message'])) {
            $template['subject'] = str_replace(array_keys($placeholders), array_values($placeholders), $template['subject']);
            $template['heading'] = str_replace(array_keys($placeholders), array_values($placeholders), $template['heading']);
            $template['message'] = wpautop(str_replace(array_keys($placeholders), array_values($placeholders), $template['message']));
        } else {
            $template['enabled'] = 'no';
        }
    }

    // Send emails
    add_filter('wp_mail_content_type', function () {
        return 'text/html';
    });

    if ($email_templates['user']['enabled'] === 'yes') {
        $user_email_body = get_email_template($email_templates['user']['heading'], $email_templates['user']['message']);
        wp_mail($user_email, $email_templates['user']['subject'], $user_email_body);
    }

    if ($email_templates['admin']['enabled'] === 'yes') {
        $admin_emails = get_all_membership_admin_emails();
        if (!empty($admin_emails)) {
            $to = implode(',', $admin_emails); // All admins in To
            $admin_email_body = get_email_template($email_templates['admin']['heading'], $email_templates['admin']['message']);
            wp_mail($to, $email_templates['admin']['subject'], $admin_email_body);
        }
    }

    remove_filter('wp_mail_content_type', function () {
        return 'text/html';
    });
    $member_type = strtolower($membership_category).'_member';  
    update_user_meta($user_id, 'member_type', $membership_category);
    
    
   
    // Remove member role
    $user = new WP_User($user_id);
    $user->remove_role('member');
    $user->add_role($member_type);

    wp_send_json_success();
}


add_action('admin_enqueue_scripts', function ($hook) {
    $version = rand();
    
    if ($hook !== 'membership_page_membership-email-templates') {

        return;
    }

    wp_enqueue_script(
        'email-template-settings',
        get_stylesheet_directory_uri() . '/membership/js/email-template-settings.js', // Adjust path if needed
        [],
        '1.0',
        true
    );
    $css_path = get_stylesheet_directory() . '/membership/css/email-template-settings.css';
    wp_enqueue_style(
        'email-template-settings-style',
        get_stylesheet_directory_uri() . '/membership/css/email-template-settings.css?' . filemtime($css_path)
    );

});


function get_all_membership_admin_emails() {
    $emails = [];

    // 1. Super admin
    $admin_email = get_option('admin_email');
    if ($admin_email) {
        $emails[] = sanitize_email($admin_email);
    }

    // 2. Membership admins
    $membership_admins = get_users(['role' => 'membership_admin']);
    foreach ($membership_admins as $admin) {
        if (!empty($admin->user_email)) {
            $emails[] = sanitize_email($admin->user_email);
        }
    }

    return array_unique(array_filter($emails));
}

