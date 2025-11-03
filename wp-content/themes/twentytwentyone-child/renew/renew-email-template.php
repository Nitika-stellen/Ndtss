<?php

function renew_get_all_admin_emails() {
    $admin_emails = array();
    
    // Get super admin email
    $super_admin_email = get_option('admin_email');
    if ($super_admin_email) {
        $admin_emails[] = $super_admin_email;
    }
    
    // Get users with specific admin roles for renewal
    $admin_roles = ['administrator'];
    
    foreach ($admin_roles as $role) {
        $users = get_users(['role' => $role]);
        foreach ($users as $user) {
            if (!empty($user->user_email) && !in_array($user->user_email, $admin_emails)) {
                $admin_emails[] = $user->user_email;
            }
        }
    }
    
    return array_unique($admin_emails);
}

function renew_send_notification_emails($submission_data) {
    // Prepare template data
    $user_name = $submission_data['name'];
    $renewal_method = $submission_data['method'];
    $certification_level = $submission_data['level'];
    $certification_sector = $submission_data['sector'];
    $submission_date = current_time('Y-m-d H:i:s');
    $total_cpd_points = $submission_data['total_cpd_points'] ?? 0;
    $submission_id = $submission_data['submission_id'] ?? '';
    $user_email = $submission_data['user_email'] ?? '';
    
    // Determine if this is renewal or recertification based on CPD points
    $is_recertification = ($total_cpd_points < 150); // Assuming 150 is the minimum for renewal
    
    // Get email templates
    if ($is_recertification) {
        $user_templates = [
            'enabled' => get_option('user_recertification_submission_enabled', 'yes'),
            'subject' => get_option('user_recertification_submission_subject', ''),
            'heading' => get_option('user_recertification_submission_heading', ''),
            'message' => get_option('user_recertification_submission_message', '')
        ];
        
        $admin_templates = [
            'enabled' => get_option('admin_recertification_notification_enabled', 'yes'),
            'subject' => get_option('admin_recertification_notification_subject', ''),
            'heading' => get_option('admin_recertification_notification_heading', ''),
            'message' => get_option('admin_recertification_notification_message', '')
        ];
    } else {
        $user_templates = [
            'enabled' => get_option('user_renewal_submission_enabled', 'yes'),
            'subject' => get_option('user_renewal_submission_subject', ''),
            'heading' => get_option('user_renewal_submission_heading', ''),
            'message' => get_option('user_renewal_submission_message', '')
        ];
        
        $admin_templates = [
            'enabled' => get_option('admin_renewal_notification_enabled', 'yes'),
            'subject' => get_option('admin_renewal_notification_subject', ''),
            'heading' => get_option('admin_renewal_notification_heading', ''),
            'message' => get_option('admin_renewal_notification_message', '')
        ];
    }
    
    // Replace placeholders in templates
    $placeholders = [
        '{user_name}' => $user_name,
        '{renewal_method}' => $renewal_method,
        '{certification_level}' => $certification_level,
        '{certification_sector}' => $certification_sector,
        '{submission_date}' => $submission_date,
        '{total_cpd_points}' => $total_cpd_points,
        '{submission_id}' => $submission_id
    ];
    
    foreach (['user_templates', 'admin_templates'] as $template_type) {
        foreach (['subject', 'heading', 'message'] as $field) {
            if (!empty(${$template_type}[$field])) {
                ${$template_type}[$field] = str_replace(
                    array_keys($placeholders),
                    array_values($placeholders),
                    ${$template_type}[$field]
                );
            }
        }
    }
    
    // Send email to user
    if ($user_templates['enabled'] === 'yes' && !empty($user_email) && !empty($user_templates['subject']) && !empty($user_templates['message'])) {
        // Prepare email content - use admin's content exactly as configured
        $email_content = $user_templates['message'];
        
        // Add heading if provided
        if (!empty($user_templates['heading'])) {
            $email_content = '<h2>' . esc_html($user_templates['heading']) . '</h2>' . $email_content;
        }
        
        $user_email_sent = send_formatted_email(
            $user_email,
            $user_templates['subject'],
            $email_content  // Send exactly what admin configured
        );
        
        if ($user_email_sent) {
            renew_log_info('User renewal notification email sent', [
                'user_email' => $user_email,
                'renewal_method' => $renewal_method,
                'submission_id' => $submission_id
            ]);
        } else {
            renew_log_error('Failed to send user renewal notification email', [
                'user_email' => $user_email,
                'renewal_method' => $renewal_method,
                'submission_id' => $submission_id
            ]);
        }
    }
    
    // Send email to all admins
    if ($admin_templates['enabled'] === 'yes' && !empty($admin_templates['subject']) && !empty($admin_templates['message'])) {
        $admin_emails = renew_get_all_admin_emails();
       
        if (!empty($admin_emails)) {
            // Prepare admin email content - use admin's content exactly as configured
            $admin_email_content = $admin_templates['message'];
            
            // Add heading if provided
            if (!empty($admin_templates['heading'])) {
                $admin_email_content = '<h2>' . esc_html($admin_templates['heading']) . '</h2>' . $admin_email_content;
            }
            
            $admin_to = implode(',', $admin_emails);
            $admin_email_sent = send_formatted_email(
                $admin_to,
                $admin_templates['subject'],
                $admin_email_content  // Send exactly what admin configured
            );
            
            if ($admin_email_sent) {
                renew_log_info('Admin renewal notification email sent', [
                    'admin_emails' => $admin_emails,
                    'renewal_method' => $renewal_method,
                    'submission_id' => $submission_id
                ]);
            } else {
                renew_log_error('Failed to send admin renewal notification email', [
                    'admin_emails' => $admin_emails,
                    'renewal_method' => $renewal_method,
                    'submission_id' => $submission_id
                ]);
            }
        } else {
            renew_log_warning('No admin emails found for renewal notification', [
                'renewal_method' => $renewal_method,
                'submission_id' => $submission_id
            ]);
        }
    }
}

// Setup default email templates on activation
function renew_setup_default_email_templates() {
    // Default user renewal submission template
    if (!get_option('user_renewal_submission_subject')) {
        update_option('user_renewal_submission_subject', 'Renewal Application Received - {submission_id}');
        update_option('user_renewal_submission_heading', 'Renewal Application Confirmed');
        update_option('user_renewal_submission_message', 
            'Dear {user_name},\n\nWe have successfully received your renewal application for {certification_level} in {certification_sector}.\n\nApplication Details:\n- Submission ID: {submission_id}\n- Renewal Method: {renewal_method}\n- Certification Level: {certification_level}\n- Certification Sector: {certification_sector}\n- Total CPD Points: {total_cpd_points}\n- Submitted Date: {submission_date}\n\nYour application is currently under review. We will notify you once the review process is complete.\n\nThank you for choosing NDTSS for your certification renewal.'
        );
        update_option('user_renewal_submission_enabled', 'yes');
    }
    
    // Default user recertification submission template
    if (!get_option('user_recertification_submission_subject')) {
        update_option('user_recertification_submission_subject', 'Recertification Application Received - {submission_id}');
        update_option('user_recertification_submission_heading', 'Recertification Application Confirmed');
        update_option('user_recertification_submission_message', 
            'Dear {user_name},\n\nWe have successfully received your recertification application for {certification_level} in {certification_sector}.\n\nApplication Details:\n- Submission ID: {submission_id}\n- Method: {renewal_method}\n- Certification Level: {certification_level}\n- Certification Sector: {certification_sector}\n- Total CPD Points: {total_cpd_points}\n- Submitted Date: {submission_date}\n\nSince your CPD points are insufficient for direct renewal, your application will be processed as a recertification.\n\nThank you for your submission.'
        );
        update_option('user_recertification_submission_enabled', 'yes');
    }
    
    // Default admin renewal notification template
    if (!get_option('admin_renewal_notification_subject')) {
        update_option('admin_renewal_notification_subject', 'New Renewal Application - {user_name}');
        update_option('admin_renewal_notification_heading', 'New Renewal Application Submitted');
        update_option('admin_renewal_notification_message', 
            'A new renewal application has been submitted and requires review.\n\nApplication Details:\n- Applicant: {user_name}\n- Submission ID: {submission_id}\n- Renewal Method: {renewal_method}\n- Certification Level: {certification_level}\n- Certification Sector: {certification_sector}\n- Total CPD Points: {total_cpd_points}\n- Submitted Date: {submission_date}\n\nPlease review this application in the admin panel.'
        );
        update_option('admin_renewal_notification_enabled', 'yes');
    }
    
    // Default admin recertification notification template
    if (!get_option('admin_recertification_notification_subject')) {
        update_option('admin_recertification_notification_subject', 'New Recertification Application - {user_name}');
        update_option('admin_recertification_notification_heading', 'New Recertification Application Submitted');
        update_option('admin_recertification_notification_message', 
            'A new recertification application has been submitted and requires review.\n\nApplication Details:\n- Applicant: {user_name}\n- Submission ID: {submission_id}\n- Method: {renewal_method}\n- Certification Level: {certification_level}\n- Certification Sector: {certification_sector}\n- Total CPD Points: {total_cpd_points}\n- Submitted Date: {submission_date}\n\nNote: This application has insufficient CPD points for direct renewal and requires recertification process.\n\nPlease review this application in the admin panel.'
        );
        update_option('admin_recertification_notification_enabled', 'yes');
    }
}

// Setup templates when the module is first loaded
add_action('after_setup_theme', 'renew_setup_default_email_templates');

// Setup reminder email templates
function renew_setup_reminder_email_templates() {
    // User renewal reminder template
    if (!get_option('user_renewal_reminder_subject')) {
        update_option('user_renewal_reminder_subject', 'Renewal Reminder - Certificate Expiring Soon');
        update_option('user_renewal_reminder_heading', 'Certificate Expiry Reminder');
        update_option('user_renewal_reminder_message', 
            'Dear {user_name},\n\nYour {certification_level} certificate in {certification_sector} (Certificate #: {certificate_number}) will expire on {expiry_date}.\n\nYou have {days_remaining} days to submit your renewal application.\n\nPlease visit your profile to submit your renewal application.\n\nBest regards,\nNDTSS Team'
        );
    }
    
    // User recertification reminder template
    if (!get_option('user_recertification_reminder_subject')) {
        update_option('user_recertification_reminder_subject', 'Recertification Reminder - Certificate Expiring Soon');
        update_option('user_recertification_reminder_heading', 'Certificate Expiry Reminder');
        update_option('user_recertification_reminder_message', 
            'Dear {user_name},\n\nYour {certification_level} certificate in {certification_sector} (Certificate #: {certificate_number}) will expire on {expiry_date}.\n\nYou have {days_remaining} days to submit your recertification application.\n\nPlease visit your profile to submit your recertification application.\n\nBest regards,\nNDTSS Team'
        );
    }
}

// Setup reminder templates when the module is first loaded
add_action('after_setup_theme', 'renew_setup_reminder_email_templates');