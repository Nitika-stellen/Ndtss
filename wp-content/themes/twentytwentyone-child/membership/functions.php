<?php
include get_stylesheet_directory() . '/membership/membership_module.php';
include get_stylesheet_directory() . '/membership/email_template.php';
include get_stylesheet_directory() . '/membership/generate_certificate.php';
include get_stylesheet_directory() . '/membership/setup_reminder_templates.php';
include get_stylesheet_directory() . '/membership/test_reminder_system.php';
include get_stylesheet_directory() . '/membership/legacy-membership-import.php';

// Restrict users list for membership admins to show only members
add_action('pre_get_users', 'restrict_users_list_for_membership_admin');

function restrict_users_list_for_membership_admin($user_query) {
    // Only apply on admin users page
    if (!is_admin() || !function_exists('get_current_screen')) {
        return;
    }
    
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'users') {
        return;
    }
    
    // Check if current user is membership admin (but not super admin)
    $current_user = wp_get_current_user();
    if (!$current_user || current_user_can('manage_options')) {
        return; // Super admins can see all users
    }
    
    // Check if user has membership_admin role
    if (!in_array('membership_admin', $current_user->roles)) {
        return;
    }
    
    // Define membership roles to show for membership admins
    $membership_roles = [
        'member',
        'individual_member',
        'corporate_member'
    ];
    
    // Set the role filter to only show membership-related users
    $user_query->set('role__in', $membership_roles);
    
    membership_log_info('User query restricted for membership admin', [
        'admin_user_id' => $current_user->ID,
        'admin_username' => $current_user->user_login,
        'restricted_to_roles' => $membership_roles
    ]);
}

add_action('gform_after_submission_4', 'save_membership_data_to_user_meta_and_send_email', 10, 2);
add_action('gform_after_submission_5', 'save_membership_data_to_user_meta_and_send_email', 10, 2);

// Membership reminder system
add_action('wp', 'setup_membership_reminder_cron');
add_action('membership_expiry_reminder_cron', 'send_membership_expiry_reminders');

// Setup membership admin role
add_action('init', 'setup_membership_admin_role');

function setup_membership_admin_role() {
    // Check if role already exists
    if (get_role('membership_admin')) {
        return;
    }
    
    // Create membership admin role with specific capabilities
    add_role('membership_admin', 'Membership Admin', [
        // Basic WordPress capabilities
        'read' => true,
        'edit_users' => true,
        'list_users' => true,
        'access_admin' => true,
        
        // Cannot create, delete, or promote users
        'create_users' => false,
        'delete_users' => false,
        'promote_users' => false,
        
        // Cannot access other admin areas
        'manage_options' => false,
        'edit_posts' => false,
        'edit_pages' => false,
        'edit_themes' => false,
        'install_plugins' => false,
        'update_plugins' => false,
    ]);
}

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
function Corporates_membership_form() {
    ob_start();
    if ( !is_user_logged_in() ) {
        wp_redirect(home_url('/sign-in/'));
        exit;
    }
    
    // Ensure Gravity Forms is loaded
    if ( ! class_exists('GFAPI') ) {
        echo '<p style="color:red;">Gravity Forms is not active.</p>';
        return ob_get_clean();
    }
    
    // Verify form exists and is active
    $form = GFAPI::get_form(4);
    if ( is_wp_error($form) || empty($form) ) {
        echo '<p style="color:red;">Form 4 not found.</p>';
        return ob_get_clean();
    }
    
    if ( isset($form['is_active']) && ! $form['is_active'] ) {
        echo '<p style="color:red;">Form 4 is inactive.</p>';
        return ob_get_clean();
    }
    
    // Enqueue Gravity Forms scripts/styles
    if ( function_exists('gravity_form_enqueue_scripts') ) {
        gravity_form_enqueue_scripts(4, true);
    }
    
    // Add inline script fix for gf_legacy error - must be before form renders
    echo '<script type="text/javascript">
    if (typeof window.gf_global === "undefined") {
        window.gf_global = {};
    }
    if (typeof window.gf_legacy === "undefined") {
        window.gf_legacy = {};
    }
    </script>';
    
    // Render form using shortcode - disable AJAX to prevent script loading issues
    $form_output = do_shortcode('[gravityform id="4" title="false" description="false" ajax="false"]');
    
    if ( empty(trim($form_output)) ) {
        // Fallback: try direct gravity_form() function
        $form_output = gravity_form(4, false, false, false, null, true, 1, false);
    }
    
    if ( empty(trim($form_output)) ) {
        echo '<p style="color:red;">Form 4 failed to render. Form exists: ' . (empty($form) ? 'No' : 'Yes') . ', Active: ' . (isset($form['is_active']) ? ($form['is_active'] ? 'Yes' : 'No') : 'Unknown') . '</p>';
    } else {
        echo $form_output;
    }
    
    return ob_get_clean();
}
// Register both shortcode names for compatibility
add_shortcode('Corporates_membership_form', 'Corporates_membership_form');
add_shortcode('Corporate_membership_form', 'Corporates_membership_form');
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

    // if ($form_id == 5) {
    //     $membership_name = "Individual Membership";
    //     $membership_duration = intval(explode('|', $entry[27])[0]);
    //     $membership_category =  rgar($entry, '24');
    // } elseif ($form_id == 4) {
    //     $membership_name = "Corporate Membership";
    //     $membership_duration = intval(explode('|', $entry[31])[0]);
    //     $membership_category =  "Corporate";
    // }

    if ($form_id == 5) {
    $form = GFAPI::get_form($form_id);
    $membership_name = "Individual Membership";
    $membership_duration = 0;
    $membership_category = rgar($entry, '24');
    
    // Find the selected product field dynamically
    foreach ($form['fields'] as $field) {
        if ($field->type === 'product' && !empty($entry[$field->id])) {
            $product_value = $entry[$field->id];
            if (!empty($product_value)) {
                // Extract duration from the product name (e.g., "1 Year" or "3 Years")
                if (preg_match('/(\d+)\s*(?:Year|Yr)s?/i', $product_value, $matches)) {
                    $membership_duration = intval($matches[1]);
                }
                break; // Stop at the first found product with a value
            }
        }
    }
}
elseif ($form_id == 4) {
    $form = GFAPI::get_form($form_id);
    $membership_name = "Corporate Membership";
    $membership_duration = 0;
    $membership_category = "Corporate";
    
    // Find the selected product field dynamically
    foreach ($form['fields'] as $field) {
        if ($field->type === 'product' && !empty($entry[$field->id])) {
            $product_value = $entry[$field->id];
            if (!empty($product_value)) {
                if (preg_match('/(\d+)\s*(?:Year|Yr)s?/i', $product_value, $matches)) {
                    $membership_duration = intval($matches[1]);
                }
                break; // Stop at the first found product with a value
            }
        }
    }
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

    // Save membership status + dates
    $approval_date_str = $approval_date->format('Y-m-d');
    
    // --- START: ARCHIVE EXISTING MEMBERSHIP (HISTORY) ---
    // Before overwriting, check if there is an existing membership and archive it
    $current_approval_date = get_user_meta($user_id, 'membership_approval_date', true);
    if (!empty($current_approval_date)) {
        $history = get_user_meta($user_id, 'membership_history_log', true);
        if (!is_array($history)) {
            $history = [];
        }
        
        $current_expiry = get_user_meta($user_id, 'membership_expiry_date', true);
        $current_entry_id = get_user_meta($user_id, 'ind_member_form_entry', true);
        $current_tier = get_user_meta($user_id, 'member_type', true);
        
        // Add to history
        $history[] = [
            'approval_date' => $current_approval_date,
            'expiry_date'   => $current_expiry,
            'entry_id'      => $current_entry_id,
            'member_type'   => $current_tier,
            'archived_at'   => current_time('mysql'),
            'type'          => 'renewal_archive'
        ];
        
        update_user_meta($user_id, 'membership_history_log', $history);
    }
    // --- END: ARCHIVE HISTORY ---

    update_user_meta($user_id, 'membership_approval_status', 'approved');
    update_user_meta($user_id, 'membership_approved_by', $approver_id);
    // Always record the latest approval date
    update_user_meta($user_id, 'membership_approval_date', $approval_date_str);
    // Only set member_since if not already stored (preserve original join date)
    if (!get_user_meta($user_id, 'member_since', true)) {
        update_user_meta($user_id, 'member_since', $approval_date_str);
    }
    update_user_meta($user_id, 'membership_expiry_date', $expiry_date->format('Y-m-d'));
    update_user_meta($user_id, 'ind_member_form_entry', $entry_id);
    update_user_meta($user_id, 'member_type', $membership_category);
    
    // Mark as admin approval
    update_user_meta($user_id, 'import_source', 'admin_approval');
    
    // Calculate membership duration
    $duration_years = 0;
    $membership_period = '';
    if ($approval_date_str && $expiry_date) {
        $start_ts = strtotime($approval_date_str);
        $expiry_ts = strtotime($expiry_date->format('Y-m-d'));
        $duration_years = round(($expiry_ts - $start_ts) / (365.25 * 24 * 60 * 60), 1);
        
        // Determine membership period label
        if ($duration_years > 10) {
            $membership_period = 'Lifetime';
        } elseif ($duration_years >= 5) {
            $membership_period = '5 Years';
        } elseif ($duration_years >= 3) {
            $membership_period = '3 Years';
        } elseif ($duration_years >= 2) {
            $membership_period = '2 Years';
        } elseif ($duration_years >= 1) {
            $membership_period = '1 Year';
        } else {
            $membership_period = round($duration_years, 1) . ' Years';
        }
    }
    update_user_meta($user_id, 'membership_duration_years', $duration_years);
    update_user_meta($user_id, 'membership_period', $membership_period);

    // Assign roles
    $user = new WP_User($user_id);
    
    // Always add base member role
    $user->add_role('member');
    
    // For individual members, use the selected classification as their role
    if ($form_id == 5) {
        // Get the selected classification and sanitize it for role name
        $classification = strtolower(sanitize_title($membership_category));
        $user->add_role($classification);
        
        // Log the role assignment
        membership_log_info('Assigned member role based on classification', [
            'user_id' => $user_id,
            'classification' => $membership_category,
            'role_assigned' => $classification
        ]);
    } else {
        // For corporate members, use the standard role assignment
        $member_type = strtolower($membership_category).'_member';
        $user->add_role($member_type);
    }

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
        $form = GFAPI::get_form($form_id);
        $event_name = "Individual Membership";
         $membership_type = 0;
        $membership_category =  rgar($entry, '24');
         foreach ($form['fields'] as $field) {
        if ($field->type === 'product' && !empty($entry[$field->id])) {
            $product_value = $entry[$field->id];
            if (!empty($product_value)) {
                // Extract duration from the product name (e.g., "1 Year" or "3 Years")
                if (preg_match('/(\d+)\s*(?:Year|Yr)s?/i', $product_value, $matches)) {
                    $membership_type = intval($matches[1]);
                }
                break; // Stop at the first found product with a value
            }
        }
    }
    } elseif ($form_id == 4) {
        $event_name = "Corporate Membership";
        $membership_type = 0;
        $membership_category =  "Corporate";
           foreach ($form['fields'] as $field) {
        if ($field->type === 'product' && !empty($entry[$field->id])) {
            $product_value = $entry[$field->id];
            if (!empty($product_value)) {
                // Extract duration from the product name (e.g., "1 Year" or "3 Years")
                if (preg_match('/(\d+)\s*(?:Year|Yr)s?/i', $product_value, $matches)) {
                    $membership_type = intval($matches[1]);
                }
                break; // Stop at the first found product with a value
            }
        }
    }
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

// AJAX handler to send certificate via email
add_action('wp_ajax_send_certificate_email', 'send_certificate_email_ajax');

function send_certificate_email_ajax() {
    // Verify nonce
    check_ajax_referer('send_certificate_email_nonce', 'nonce');
    
    // Check if user is admin
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    
    if (!$user_id || !$member_id) {
        wp_send_json_error('Invalid user or member ID');
    }
    
    // Get user info
    $user = get_userdata($user_id);
    if (!$user) {
        wp_send_json_error('User not found');
    }
    
    // Get certificate file path
    $upload_dir = wp_upload_dir();
    $cert_dir = $upload_dir['basedir'] . '/certificates/';
    $certificate_filename = 'user-' . $user_id . '-member-' . $member_id . '.pdf';
    $certificate_path = $cert_dir . $certificate_filename;
    
    // Check if certificate exists
    if (!file_exists($certificate_path)) {
        wp_send_json_error('Certificate file not found. Please generate the certificate first.');
    }
    
    // Get entry to determine membership type from form_id
    $entry = GFAPI::get_entry($member_id);
    $form_id = 0;
    if (!is_wp_error($entry) && isset($entry['form_id'])) {
        $form_id = intval($entry['form_id']);
    }
    
    // Determine membership type based on form_id
    // Form 4 = Corporate, Form 5 = Individual
    if ($form_id == 4) {
        $membership_type = 'Corporate';
    } elseif ($form_id == 5) {
        // For individual, check if there's a classification (Fellow, Associate, etc.)
        $member_classification = '';
        if (!is_wp_error($entry) && isset($entry['24'])) {
            $member_classification = rgar($entry, '24');
        }
        $membership_type = !empty($member_classification) && $member_classification !== 'N/A' 
            ? $member_classification 
            : 'Individual';
    } else {
        // Fallback to user meta if form_id not found
        $membership_type = get_user_meta($user_id, 'member_type', true) ?: 'Member';
    }
    
    $membership_number = '';
    
    // Get membership number from membership_data
    $membership_data = get_user_meta($user_id, 'membership_data', true);
    if (is_array($membership_data)) {
        foreach ($membership_data as $membership) {
            if (($membership['entry_id'] ?? 0) == $member_id) {
                $membership_number = $membership['membership_number'] ?? '';
                break;
            }
        }
    }
    
    // Validate email address
    if (!is_email($user->user_email)) {
        $error_msg = 'Invalid email address: ' . $user->user_email;
        error_log('[Membership Email] ' . $error_msg);
        membership_log_error('Invalid email address', [
            'user_id' => $user_id,
            'member_id' => $member_id,
            'user_email' => $user->user_email
        ]);
        wp_send_json_error($error_msg);
    }
    
    // Prepare email
    $to = $user->user_email;
    $subject = 'Your ' . $membership_type . ' Membership Certificate';
    
    $message = '<p>Dear ' . esc_html($user->display_name) . ',</p>';
    $message .= '<p>Congratulations! Please find your membership certificate attached to this email.</p>';
    if ($membership_number) {
        $message .= '<p><strong>Membership Number:</strong> ' . esc_html($membership_number) . '</p>';
    }
    $message .= '<p><strong>Membership Type:</strong> ' . esc_html($membership_type) . '</p>';
    $message .= '<p>Thank you for being a valued member of the Non-Destructive Testing Society (Singapore).</p>';
    $message .= '<p>Best regards,<br>NDTSS Team</p>';
    
    // Check if email template function exists
    if (!function_exists('get_email_template')) {
        error_log('[Membership Email] WARNING: get_email_template function not found, using plain HTML');
        $email_body = $message;
    } else {
        $email_body = get_email_template('Your Membership Certificate', $message);
    }
    
    // Set email headers
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    // Verify attachment file exists and is readable
    if (!file_exists($certificate_path)) {
        $error_msg = 'Certificate file not found at: ' . $certificate_path;
        error_log('[Membership Email] ' . $error_msg);
        membership_log_error('Certificate file missing', [
            'certificate_path' => $certificate_path,
            'file_exists' => false
        ]);
        wp_send_json_error($error_msg);
    }
    
    if (!is_readable($certificate_path)) {
        $error_msg = 'Certificate file is not readable: ' . $certificate_path;
        error_log('[Membership Email] ' . $error_msg);
        membership_log_error('Certificate file not readable', [
            'certificate_path' => $certificate_path,
            'file_permissions' => substr(sprintf('%o', fileperms($certificate_path)), -4)
        ]);
        wp_send_json_error($error_msg);
    }
    
    // Attach certificate
    $attachments = array($certificate_path);
    $file_size = filesize($certificate_path);
    
    // Check file size (some servers limit attachment size)
    if ($file_size > 10 * 1024 * 1024) { // 10MB limit
        error_log('[Membership Email] WARNING: Certificate file is large: ' . round($file_size / 1024 / 1024, 2) . ' MB');
    }
    
    // Send email
    $content_type_callback = function() {
        return 'text/html';
    };
    add_filter('wp_mail_content_type', $content_type_callback);
    
    // Check email configuration
    $mail_config = [
        'wp_mail_from' => apply_filters('wp_mail_from', get_option('admin_email')),
        'wp_mail_from_name' => apply_filters('wp_mail_from_name', get_option('blogname')),
        'smtp_configured' => false
    ];
    
    // Check if SMTP plugin is active
    if (function_exists('phpmailer_init')) {
        $mail_config['smtp_configured'] = true;
    }
    
    // Log email attempt with full details
    $log_data = [
        'user_id' => $user_id,
        'member_id' => $member_id,
        'user_email' => $to,
        'certificate_file' => $certificate_filename,
        'certificate_path' => $certificate_path,
        'file_exists' => file_exists($certificate_path),
        'file_size' => $file_size,
        'file_readable' => is_readable($certificate_path),
        'mail_from' => $mail_config['wp_mail_from'],
        'mail_from_name' => $mail_config['wp_mail_from_name'],
        'smtp_configured' => $mail_config['smtp_configured']
    ];
    
    error_log('[Membership Email] Attempting to send certificate email: ' . json_encode($log_data));
    membership_log_info('Attempting to send certificate email', $log_data);
    
    // Capture any output from wp_mail
    ob_start();
    $sent = wp_mail($to, $subject, $email_body, $headers, $attachments);
    $mail_output = ob_get_clean();
    
    // Get any mail errors from PHPMailer
    global $phpmailer;
    $mail_error = '';
    $phpmailer_errors = [];
    
    if (isset($phpmailer)) {
        if (!empty($phpmailer->ErrorInfo)) {
            $mail_error = $phpmailer->ErrorInfo;
        }
        if (method_exists($phpmailer, 'getSMTPInstance') && $phpmailer->getSMTPInstance()) {
            $smtp = $phpmailer->getSMTPInstance();
            if (method_exists($smtp, 'getError')) {
                $smtp_error = $smtp->getError();
                if ($smtp_error) {
                    $phpmailer_errors['smtp'] = $smtp_error;
                }
            }
        }
        if (!empty($phpmailer->smtp->error)) {
            $phpmailer_errors['smtp_error'] = $phpmailer->smtp->error;
        }
    }
    
    remove_filter('wp_mail_content_type', $content_type_callback);
    
    if ($sent) {
        $success_msg = 'Mail sent successfully to ' . $to;
        error_log('[Membership Email] SUCCESS: ' . $success_msg);
        membership_log_info('Certificate email sent successfully', [
            'user_id' => $user_id,
            'member_id' => $member_id,
            'user_email' => $to,
            'certificate_file' => $certificate_filename
        ]);
        wp_send_json_success($success_msg);
    } else {
        // Build comprehensive error message
        $error_details = [];
        if (!empty($mail_error)) {
            $error_details[] = 'PHPMailer Error: ' . $mail_error;
        }
        if (!empty($phpmailer_errors)) {
            $error_details[] = 'SMTP Errors: ' . json_encode($phpmailer_errors);
        }
        if (!empty($mail_output)) {
            $error_details[] = 'Output: ' . $mail_output;
        }
        
        $error_msg = !empty($error_details) ? implode(' | ', $error_details) : 'Failed to send email. Please check server email configuration.';
        
        $error_log_data = [
            'user_id' => $user_id,
            'member_id' => $member_id,
            'user_email' => $to,
            'mail_error' => $mail_error,
            'phpmailer_errors' => $phpmailer_errors,
            'mail_output' => $mail_output,
            'certificate_path' => $certificate_path,
            'file_exists' => file_exists($certificate_path),
            'file_readable' => is_readable($certificate_path),
            'mail_config' => $mail_config
        ];
        
        error_log('[Membership Email] FAILED: ' . json_encode($error_log_data));
        membership_log_error('Failed to send certificate email', $error_log_data);
        
        // Return user-friendly error message
        $user_error_msg = 'Failed to send email. ';
        if (!empty($mail_error)) {
            $user_error_msg .= 'Error: ' . $mail_error;
        } else {
            $user_error_msg .= 'Please check server email configuration or contact administrator.';
        }
        
        wp_send_json_error($user_error_msg);
    }
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
add_action('admin_menu', 'add_membership_reminder_admin_menu', 20);

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
add_action('init', 'setup_membership_reminder_system');

// AJAX handler for saving member_since date only (without generating certificate)
add_action('wp_ajax_save_member_since_only', 'save_member_since_only');

function save_member_since_only() {
    // Verify nonce
    check_ajax_referer('cert_review_nonce', 'nonce');
    
    // Check permissions (admin or membership_admin)
    if (!current_user_can('manage_options') && !current_user_can('edit_users')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $user_id = intval($_POST['user_id']);
    $member_id = intval($_POST['member_id']);
    $new_member_since = sanitize_text_field($_POST['member_since']);
    
    if (!$user_id || !$member_id) {
        wp_send_json_error('Invalid user or member ID');
    }
    
    // Validate date format (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_member_since)) {
        wp_send_json_error('Invalid date format. Use YYYY-MM-DD');
    }
    
    // Validate date is not in future
    if (strtotime($new_member_since) > time()) {
        wp_send_json_error('Member since date cannot be in the future');
    }
    
    // Get old value for logging
    $old_member_since = get_user_meta($user_id, 'member_since', true);
    
    // Update member_since
    update_user_meta($user_id, 'member_since', $new_member_since);
    
    // Log the change
    membership_log_info('Member since date saved (without certificate generation)', [
        'user_id' => $user_id,
        'member_id' => $member_id,
        'old_member_since' => $old_member_since,
        'new_member_since' => $new_member_since,
        'updated_by' => get_current_user_id(),
        'updated_by_name' => wp_get_current_user()->display_name
    ]);
    
    wp_send_json_success('Saved successfully.');
}

// AJAX handler for updating member_since and generating certificate
add_action('wp_ajax_update_member_since_and_generate', 'update_member_since_and_generate');

function update_member_since_and_generate() {
    // Verify nonce
    check_ajax_referer('cert_review_nonce', 'nonce');
    
    // Check permissions (admin or membership_admin)
    if (!current_user_can('manage_options') && !current_user_can('edit_users')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $user_id = intval($_POST['user_id']);
    $member_id = intval($_POST['member_id']);
    $new_member_since = sanitize_text_field($_POST['member_since']);
    $membership_type = sanitize_text_field($_POST['membership_type']);
    $form_id = intval($_POST['form_id']);
    $member_classification = sanitize_text_field($_POST['member_classification']);
    
    // Validate date format (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_member_since)) {
        wp_send_json_error('Invalid date format. Use YYYY-MM-DD');
    }
    
    // Validate date is not in future
    if (strtotime($new_member_since) > time()) {
        wp_send_json_error('Member since date cannot be in the future');
    }
    
    // Get old value for logging
    $old_member_since = get_user_meta($user_id, 'member_since', true);
    
    // Update member_since
    update_user_meta($user_id, 'member_since', $new_member_since);
    
    // Log the change
    membership_log_info('Member since date updated before certificate generation', [
        'user_id' => $user_id,
        'member_id' => $member_id,
        'old_member_since' => $old_member_since,
        'new_member_since' => $new_member_since,
        'updated_by' => get_current_user_id(),
        'updated_by_name' => wp_get_current_user()->display_name
    ]);
    
    // Now trigger certificate generation
    // Reuse existing certificate generation logic
    $_POST['user_id'] = $user_id;
    $_POST['member_id'] = $member_id;
    $_POST['membership_type'] = $membership_type;
    $_POST['form_id'] = $form_id;
    $_POST['member_classification'] = $member_classification;
    $_POST['nonce'] = wp_create_nonce('generate_certificate_nonce');
    
    // Call existing certificate generation function
    handle_generate_member_certificate();
}

