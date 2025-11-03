<?php
include get_stylesheet_directory() . '/membership/membership_module.php';
include get_stylesheet_directory() . '/membership/email_template.php';
include get_stylesheet_directory() . '/membership/generate_certificate.php';
include get_stylesheet_directory() . '/membership/setup_reminder_templates.php';
include get_stylesheet_directory() . '/membership/test_reminder_system.php';
include get_stylesheet_directory() . '/membership/membership-logger.php';
include get_stylesheet_directory() . '/membership/membership-log-viewer.php';
include get_stylesheet_directory() . '/membership/test-logging-system.php';

add_action('gform_after_submission_4', 'save_membership_data_to_user_meta_and_send_email', 10, 2);
add_action('gform_after_submission_5', 'save_membership_data_to_user_meta_and_send_email', 10, 2);

// Membership reminder system
add_action('wp', 'setup_membership_reminder_cron');
add_action('membership_expiry_reminder_cron', 'send_membership_expiry_reminders');

// Auto-setup reminder templates on theme activation
add_action('after_switch_theme', 'setup_membership_reminder_system');

function save_membership_data_to_user_meta_and_send_email($entry, $form) {
    $user_id = get_current_user_id();
    
    membership_log_info('Membership form submission started', [
        'form_id' => $form['id'],
        'entry_id' => $entry['id'],
        'user_id' => $user_id
    ]);

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
            $email_sent = send_formatted_email(
                $user_email,
                $email_templates['user']['subject'],
                get_email_template($email_templates['user']['heading'], $email_templates['user']['message'])
            );
            
            if ($email_sent) {
                membership_log_info('User confirmation email sent', [
                    'user_id' => $user_id,
                    'user_email' => $user_email,
                    'membership_type' => $membership_type
                ]);
            } else {
                membership_log_error('Failed to send user confirmation email', [
                    'user_id' => $user_id,
                    'user_email' => $user_email,
                    'membership_type' => $membership_type
                ]);
            }
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
                    $admin_email_sent = send_formatted_email(
                        $to,
                        $email_templates['admin']['subject'],
                        get_email_template($email_templates['admin']['heading'], $email_templates['admin']['message'])
                    );
                    
                    if ($admin_email_sent) {
                        membership_log_info('Admin notification email sent', [
                            'user_id' => $user_id,
                            'admin_emails' => $admin_emails,
                            'membership_type' => $membership_type
                        ]);
                    } else {
                        membership_log_error('Failed to send admin notification email', [
                            'user_id' => $user_id,
                            'admin_emails' => $admin_emails,
                            'membership_type' => $membership_type
                        ]);
                    }
               // }
            } else {
                membership_log_warning('No admin emails found for notification', [
                    'user_id' => $user_id,
                    'membership_type' => $membership_type
                ]);
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

    membership_log_info('Membership approval process started', [
        'entry_id' => $entry_id,
        'user_id' => $user_id,
        'approver_id' => $approver_id
    ]);

    if (!$entry_id || !$user_id) {
        membership_log_error('Invalid entry or user ID for approval', [
            'entry_id' => $entry_id,
            'user_id' => $user_id,
            'approver_id' => $approver_id
        ]);
        wp_send_json_error('Invalid Entry or User ID.');
    }

    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry)) {
        membership_log_error('Failed to retrieve entry for approval', [
            'entry_id' => $entry_id,
            'user_id' => $user_id,
            'error' => $entry->get_error_message()
        ]);
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

    membership_log_info('Membership approved successfully', [
        'user_id' => $user_id,
        'entry_id' => $entry_id,
        'membership_type' => $membership_name,
        'membership_category' => $membership_category,
        'approver_id' => $approver_id,
        'expiry_date' => $expiry_date->format('Y-m-d')
    ]);

    wp_send_json_success();
}

add_action('wp_ajax_membership_reject_entry_ajax', 'membership_reject_entry_ajax');

function membership_reject_entry_ajax() {
    check_ajax_referer('reject_nonce', 'nonce');

    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $reject_reason = isset($_POST['reject_reason']) ? sanitize_text_field($_POST['reject_reason']) : '';

    membership_log_info('Membership rejection process started', [
        'entry_id' => $entry_id,
        'user_id' => $user_id,
        'reject_reason' => $reject_reason
    ]);

    if (!$entry_id || !$user_id || !$reject_reason) {
        membership_log_error('Missing parameters for rejection', [
            'entry_id' => $entry_id,
            'user_id' => $user_id,
            'reject_reason' => $reject_reason
        ]);
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

/**
 * Setup membership reminder cron job
 */
function setup_membership_reminder_cron() {
    if (!wp_next_scheduled('membership_expiry_reminder_cron')) {
        wp_schedule_event(time(), 'daily', 'membership_expiry_reminder_cron');
    }
}

/**
 * Send membership expiry reminders
 */
function send_membership_expiry_reminders() {
    membership_log_info('Membership expiry reminder process started');
    
    // Get all users with approved membership
    $users = get_users([
        'meta_query' => [
            [
                'key' => 'membership_approval_status',
                'value' => 'approved',
                'compare' => '='
            ]
        ]
    ]);

    membership_log_info('Found users with approved memberships', [
        'user_count' => count($users)
    ]);

    $reminder_days = [30, 14, 7, 1]; // Days before expiry to send reminders
    $today = new DateTime(current_time('Y-m-d'));
    
    foreach ($users as $user) {
        $expiry_date = get_user_meta($user->ID, 'membership_expiry_date', true);
        
        if (empty($expiry_date)) {
            continue;
        }
        
        $expiry = new DateTime($expiry_date);
        $days_until_expiry = $today->diff($expiry)->days;
        
        // Check if we should send a reminder today
        if (in_array($days_until_expiry, $reminder_days)) {
            membership_log_info('Sending reminder email', [
                'user_id' => $user->ID,
                'user_email' => $user->user_email,
                'days_until_expiry' => $days_until_expiry,
                'expiry_date' => $expiry_date
            ]);
            send_membership_reminder_email($user, $days_until_expiry, $expiry_date);
        }
    }
}

/**
 * Send individual membership reminder email
 */
function send_membership_reminder_email($user, $days_until_expiry, $expiry_date) {
    // Check if reminder already sent for this period
    $last_reminder = get_user_meta($user->ID, 'last_reminder_sent', true);
    $today = current_time('Y-m-d');
    
    if ($last_reminder === $today) {
        return; // Already sent today
    }
    
    $membership_type = get_user_meta($user->ID, 'membership_type', true) ?: 'Individual';
    $member_type = get_user_meta($user->ID, 'member_type', true) ?: $membership_type;
    
    // Get email template
    $email_templates = [
        'enabled' => get_option('user_membership_reminder_enabled', 'yes'),
        'subject' => get_option('user_membership_reminder_subject', ''),
        'heading' => get_option('user_membership_reminder_heading', ''),
        'message' => get_option('user_membership_reminder_message', '')
    ];
    
    if ($email_templates['enabled'] !== 'yes' || empty($email_templates['subject']) || empty($email_templates['message'])) {
        return;
    }
    
    // Prepare placeholders
    $placeholders = [
        '{user_name}' => $user->display_name,
        '{membership_type}' => ucfirst($member_type),
        '{expiry_date}' => date('F j, Y', strtotime($expiry_date)),
        '{days_until_expiry}' => $days_until_expiry
    ];
    
    // Replace placeholders
    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $email_templates['subject']);
    $heading = str_replace(array_keys($placeholders), array_values($placeholders), $email_templates['heading']);
    $message = wpautop(str_replace(array_keys($placeholders), array_values($placeholders), $email_templates['message']));
    
    // Send email
    add_filter('wp_mail_content_type', function () {
        return 'text/html';
    });
    
    $email_body = get_email_template($heading, $message);
    $sent = wp_mail($user->user_email, $subject, $email_body);
    
    remove_filter('wp_mail_content_type', function () {
        return 'text/html';
    });
    
    if ($sent) {
        // Mark reminder as sent
        update_user_meta($user->ID, 'last_reminder_sent', $today);
        update_user_meta($user->ID, 'last_reminder_days', $days_until_expiry);
        
        membership_log_info('Reminder email sent successfully', [
            'user_id' => $user->ID,
            'user_email' => $user->user_email,
            'days_until_expiry' => $days_until_expiry,
            'expiry_date' => $expiry_date
        ]);
    } else {
        membership_log_error('Failed to send reminder email', [
            'user_id' => $user->ID,
            'user_email' => $user->user_email,
            'days_until_expiry' => $days_until_expiry,
            'expiry_date' => $expiry_date
        ]);
    }
}

/**
 * Manual trigger for membership reminders (for testing)
 */
function trigger_membership_reminders_manually() {
    if (current_user_can('manage_options') && isset($_GET['trigger_reminders'])) {
        send_membership_expiry_reminders();
        wp_die('Reminders sent successfully!', 'Reminders Sent', ['response' => 200]);
    }
}
add_action('admin_init', 'trigger_membership_reminders_manually');

/**
 * Add admin menu for membership reminders
 */
function add_membership_reminder_admin_menu() {
    add_submenu_page(
        'membership-management',
        'Membership Reminders',
        'Membership Reminders',
        'manage_options',
        'membership-reminders',
        'render_membership_reminder_page'
    );
}
add_action('admin_menu', 'add_membership_reminder_admin_menu');

/**
 * Render membership reminder admin page
 */
function render_membership_reminder_page() {
    $users = get_users([
        'meta_query' => [
            [
                'key' => 'membership_approval_status',
                'value' => 'approved',
                'compare' => '='
            ]
        ]
    ]);
    
    $today = new DateTime(current_time('Y-m-d'));
    $expiring_soon = [];
    
    foreach ($users as $user) {
        $expiry_date = get_user_meta($user->ID, 'membership_expiry_date', true);
        if (!empty($expiry_date)) {
            $expiry = new DateTime($expiry_date);
            $days_until_expiry = $today->diff($expiry)->days;
            
            if ($days_until_expiry <= 30) {
                $expiring_soon[] = [
                    'user' => $user,
                    'expiry_date' => $expiry_date,
                    'days_until_expiry' => $days_until_expiry,
                    'last_reminder' => get_user_meta($user->ID, 'last_reminder_sent', true)
                ];
            }
        }
    }
    
    // Sort by days until expiry
    usort($expiring_soon, function($a, $b) {
        return $a['days_until_expiry'] - $b['days_until_expiry'];
    });
    
    ?>
    <div class="wrap">
        <h1>Membership Expiry Reminders</h1>
        
        <div class="notice notice-info">
            <p>
                <strong>Reminder Schedule:</strong> Emails are sent automatically 30, 14, 7, and 1 days before membership expiry.
                <br>
                <a href="<?php echo admin_url('admin.php?page=membership-reminders&trigger_reminders=1'); ?>" class="button button-primary">Send Reminders Now</a>
            </p>
        </div>
        
        <h2>Memberships Expiring Soon (Next 30 Days)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>User Name</th>
                    <th>Email</th>
                    <th>Membership Type</th>
                    <th>Expiry Date</th>
                    <th>Days Until Expiry</th>
                    <th>Last Reminder Sent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expiring_soon)): ?>
                    <tr><td colspan="7">No memberships expiring in the next 30 days.</td></tr>
                <?php else: ?>
                    <?php foreach ($expiring_soon as $member): ?>
                        <tr>
                            <td><?php echo esc_html($member['user']->display_name); ?></td>
                            <td><?php echo esc_html($member['user']->user_email); ?></td>
                            <td><?php echo esc_html(get_user_meta($member['user']->ID, 'member_type', true) ?: 'Individual'); ?></td>
                            <td><?php echo esc_html(date('M j, Y', strtotime($member['expiry_date']))); ?></td>
                            <td>
                                <span style="color: <?php echo $member['days_until_expiry'] <= 7 ? 'red' : ($member['days_until_expiry'] <= 14 ? 'orange' : 'green'); ?>;">
                                    <?php echo $member['days_until_expiry']; ?> days
                                </span>
                            </td>
                            <td><?php echo $member['last_reminder'] ? esc_html(date('M j, Y', strtotime($member['last_reminder']))) : 'Never'; ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=membership-reminders&send_reminder=' . $member['user']->ID); ?>" class="button">Send Reminder</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Handle manual reminder sending
 */
function handle_manual_reminder_sending() {
    if (current_user_can('manage_options') && isset($_GET['send_reminder'])) {
        $user_id = intval($_GET['send_reminder']);
        $user = get_userdata($user_id);
        
        if ($user) {
            $expiry_date = get_user_meta($user_id, 'membership_expiry_date', true);
            if ($expiry_date) {
                $today = new DateTime(current_time('Y-m-d'));
                $expiry = new DateTime($expiry_date);
                $days_until_expiry = $today->diff($expiry)->days;
                
                send_membership_reminder_email($user, $days_until_expiry, $expiry_date);
                
                wp_redirect(admin_url('admin.php?page=membership-reminders&reminder_sent=1'));
                exit;
            }
        }
    }
}
add_action('admin_init', 'handle_manual_reminder_sending');

/**
 * Setup membership reminder system
 */
function setup_membership_reminder_system() {
    // Setup default templates
    setup_default_reminder_templates();
    
    // Setup cron job
    if (!wp_next_scheduled('membership_expiry_reminder_cron')) {
        wp_schedule_event(time(), 'daily', 'membership_expiry_reminder_cron');
    }
}

/**
 * Clean up cron job on deactivation
 */
function cleanup_membership_reminder_cron() {
    wp_clear_scheduled_hook('membership_expiry_reminder_cron');
}
register_deactivation_hook(__FILE__, 'cleanup_membership_reminder_cron');

