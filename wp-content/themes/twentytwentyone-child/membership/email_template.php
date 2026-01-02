<?php

function render_email_template_settings_page() {
    $templates = [
        'user' => [
            'user_ack_submission' => [
                'label' => 'Acknowledgement of Form Submission',
                'subject_option' => 'user_ack_submission_subject',
                'heading_option' => 'user_ack_submission_heading',
                'message_option' => 'user_ack_submission_message'
            ],
            'user_ack_status' => [
                'label' => 'Membership Approval Mail',
                'subject_option' => 'user_ack_status_subject',
                'heading_option' => 'user_ack_status_heading',
                'message_option' => 'user_ack_status_message'
            ],
            'user_rejection_notification' => [
                'label' => 'Membership Rejection Mail',
                'subject_option' => 'user_rejection_notification_subject',
                'heading_option' => 'user_rejection_notification_heading',
                'message_option' => 'user_rejection_notification_message'
            ],
            'user_membership_reminder' => [
                'label' => 'Membership Expiry Reminder',
                'subject_option' => 'user_membership_reminder_subject',
                'heading_option' => 'user_membership_reminder_heading',
                'message_option' => 'user_membership_reminder_message'
            ]
        ],
        'admin' => [
            'admin_new_submission' => [
                'label' => 'New Membership Submission',
                'subject_option' => 'admin_new_submission_subject',
                'heading_option' => 'admin_new_submission_heading',
                'message_option' => 'admin_new_submission_message'
            ],
            'admin_status_notification' => [
                'label' => 'Membeship Approval Notification',
                'subject_option' => 'admin_status_notification_subject',
                'heading_option' => 'admin_status_notification_heading',
                'message_option' => 'admin_status_notification_message'
            ],
            'admin_rejection_notification' => [
                'label' => 'Membership Rejection Notification',
                'subject_option' => 'admin_rejection_notification_subject',
                'heading_option' => 'admin_rejection_notification_heading',
                'message_option' => 'admin_rejection_notification_message'
            ]
        ]
    ];

    echo '<div class="wrap email-settings-container"><h1>Email Templates</h1>';
    echo '<p>You can use the following placeholders: 
        <code>{user_name}</code>, 
        <code>{membership_type}</code>, 
        <code>{approval_date}</code>, 
        <code>{expiry_date}</code>, 
        <code>{rejection_reason}</code>,
        <code>{days_until_expiry}</code>
    </p>';

    // Styled tab navigation
    echo '<ul class="nav-tab-wrapper">';
    echo '<li><a href="#user" class="nav-tab nav-tab-active">User Emails</a></li>';
    echo '<li><a href="#admin" class="nav-tab">Admin Emails</a></li>';
    echo '</ul>';

    echo '<form method="post">';
    wp_nonce_field('save_email_templates');

    foreach ($templates as $group => $group_templates) {
        echo '<div id="' . $group . '" class="tab-content" style="' . ($group === 'user' ? '' : 'display:none;') . '">';
        echo '<h2 class="tab-title">' . ucfirst($group) . ' Email Settings</h2>';

        foreach ($group_templates as $key => $details) {
            $subject = get_option($details['subject_option'], '');
            $heading = get_option($details['heading_option'], '');
            $message = get_option($details['message_option'], '');
            $enabled = get_option($key . '_enabled', 'yes');

            echo '<div class="email-template-card">';
            echo "<button class='toggle-btn' data-target='{$key}'>▼ {$details['label']}</button>";
            echo "<div id='{$key}' class='email-content' style='display: none;'>";

            echo "<label><input type='checkbox' name='{$key}_enabled' value='yes' " . checked($enabled, 'yes', false) . "> Enable this email notification</label><br>";
            echo "<label>Subject:<br><input type='text' name='{$details['subject_option']}' value='" . esc_attr($subject) . "' class='regular-text input-field'></label><br>";
            echo "<label>Email Heading:<br><input type='text' name='{$details['heading_option']}' value='" . esc_attr($heading) . "' class='regular-text input-field'></label><br>";
            echo "<label>Message Content:<br>";
            wp_editor($message, $details['message_option'], ['textarea_rows' => 6, 'media_buttons' => false]);
            echo "</label><br><hr></div></div>";
        }
        echo '</div>';
    }

    submit_button('Save Templates');
    
    echo '</form>';
    
    // Certificate Signatures Configuration
    echo '<h2>Certificate Signatures Configuration</h2>';
    echo '<form method="post" action="" enctype="multipart/form-data">';
    wp_nonce_field('save_certificate_signatures', '_wpnonce_cert');
    echo '<table class="form-table">';
    
    // Chairman Section
    echo '<tr>';
    echo '<th scope="row">Chairman Name</th>';
    echo '<td>';
    echo '<input type="text" name="certificate_chairman_name" value="' . esc_attr(get_option('certificate_chairman_name', 'M.S.VETRISELVAN')) . '" class="regular-text" />';
    echo '<p class="description">Name of the Chairman for certificate signatures</p>';
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row">Chairman Title</th>';
    echo '<td>';
    echo '<input type="text" name="certificate_chairman_title" value="' . esc_attr(get_option('certificate_chairman_title', 'CHAIRMAN-MEMBERSHIP (NDTSS)')) . '" class="regular-text" />';
    echo '<p class="description">Title of the Chairman</p>';
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row">Chairman Signature</th>';
    echo '<td>';
    $chairman_sig = get_option('certificate_chairman_signature', '');
    if ($chairman_sig) {
        echo '<div style="margin-bottom: 10px;"><img src="' . esc_url($chairman_sig) . '" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; padding: 5px;" /></div>';
    }
    echo '<input type="file" name="certificate_chairman_signature" accept="image/png,image/jpeg,image/jpg" />';
    echo '<p class="description">Upload chairman signature image (PNG or JPG, recommended size: 200x100px)</p>';
    if ($chairman_sig) {
        echo '<label><input type="checkbox" name="remove_chairman_signature" value="1" /> Remove current signature</label>';
    }
    echo '</td>';
    echo '</tr>';
    
    // President Section
    echo '<tr>';
    echo '<th scope="row">President Name</th>';
    echo '<td>';
    echo '<input type="text" name="certificate_president_name" value="' . esc_attr(get_option('certificate_president_name', 'BABU SAJEESH KUMAR')) . '" class="regular-text" />';
    echo '<p class="description">Name of the President for certificate signatures</p>';
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row">President Title</th>';
    echo '<td>';
    echo '<input type="text" name="certificate_president_title" value="' . esc_attr(get_option('certificate_president_title', 'PRESIDENT (NDTSS)')) . '" class="regular-text" />';
    echo '<p class="description">Title of the President</p>';
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row">President Signature</th>';
    echo '<td>';
    $president_sig = get_option('certificate_president_signature', '');
    if ($president_sig) {
        echo '<div style="margin-bottom: 10px;"><img src="' . esc_url($president_sig) . '" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; padding: 5px;" /></div>';
    }
    echo '<input type="file" name="certificate_president_signature" accept="image/png,image/jpeg,image/jpg" />';
    echo '<p class="description">Upload president signature image (PNG or JPG, recommended size: 200x100px)</p>';
    if ($president_sig) {
        echo '<label><input type="checkbox" name="remove_president_signature" value="1" /> Remove current signature</label>';
    }
    echo '</td>';
    echo '</tr>';
    
    // Secretary Section
    echo '<tr>';
    echo '<th scope="row">Secretary Name</th>';
    echo '<td>';
    echo '<input type="text" name="certificate_secretary_name" value="' . esc_attr(get_option('certificate_secretary_name', 'P.PUGALENDHI')) . '" class="regular-text" />';
    echo '<p class="description">Name of the Secretary for certificate signatures</p>';
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row">Secretary Title</th>';
    echo '<td>';
    echo '<input type="text" name="certificate_secretary_title" value="' . esc_attr(get_option('certificate_secretary_title', 'HONORARY SECRETARY (NDTSS)')) . '" class="regular-text" />';
    echo '<p class="description">Title of the Secretary</p>';
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row">Secretary Signature</th>';
    echo '<td>';
    $secretary_sig = get_option('certificate_secretary_signature', '');
    if ($secretary_sig) {
        echo '<div style="margin-bottom: 10px;"><img src="' . esc_url($secretary_sig) . '" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; padding: 5px;" /></div>';
    }
    echo '<input type="file" name="certificate_secretary_signature" accept="image/png,image/jpeg,image/jpg" />';
    echo '<p class="description">Upload secretary signature image (PNG or JPG, recommended size: 200x100px)</p>';
    if ($secretary_sig) {
        echo '<label><input type="checkbox" name="remove_secretary_signature" value="1" /> Remove current signature</label>';
    }
    echo '</td>';
    echo '</tr>';
    
    echo '</table>';
    echo '<p class="submit">';
    echo '<input type="submit" name="save_certificate_signatures" class="button-primary" value="Save Certificate Signatures" />';
    echo '</p>';
    echo '</form>';
    
    echo '</div>';
}

add_action('admin_init', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'save_email_templates')) {
        foreach ($_POST as $key => $value) {
            update_option(sanitize_text_field($key), wp_kses_post($value));
        }
    }
    
    // Handle certificate signatures saving
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_wpnonce_cert']) && wp_verify_nonce($_POST['_wpnonce_cert'], 'save_certificate_signatures')) {
        $certificate_fields = [
            'certificate_chairman_name',
            'certificate_chairman_title',
            'certificate_president_name',
            'certificate_president_title',
            'certificate_secretary_name',
            'certificate_secretary_title'
        ];
        
        foreach ($certificate_fields as $field) {
            if (isset($_POST[$field])) {
                update_option($field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Handle signature image uploads
        $signature_uploads = [
            'certificate_chairman_signature' => 'chairman-signature',
            'certificate_president_signature' => 'president-signature',
            'certificate_secretary_signature' => 'secretary-signature'
        ];
        
        $upload_errors = [];
        $upload_success = [];
        
        foreach ($signature_uploads as $option_name => $file_prefix) {
            // Handle removal
            $remove_field = 'remove_' . str_replace('certificate_', '', $option_name);
            if (isset($_POST[$remove_field]) && $_POST[$remove_field] == '1') {
                $old_url = get_option($option_name, '');
                if ($old_url) {
                    // Delete old file
                    $old_path = str_replace(
                        get_stylesheet_directory_uri() . '/assets/logos/',
                        get_stylesheet_directory() . '/assets/logos/',
                        $old_url
                    );
                    if (file_exists($old_path)) {
                        @unlink($old_path);
                    }
                }
                delete_option($option_name);
                $upload_success[] = ucfirst(str_replace('-', ' ', $file_prefix)) . ' removed successfully';
                continue;
            }
            
            // Handle new upload
            if (isset($_FILES[$option_name]) && $_FILES[$option_name]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$option_name];
                
                // Validate file type
                $allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
                $file_type = wp_check_filetype($file['name']);
                
                if (!in_array($file['type'], $allowed_types)) {
                    $upload_errors[] = ucfirst(str_replace('-', ' ', $file_prefix)) . ': Invalid file type. Only PNG and JPG allowed.';
                    continue;
                }
                
                // Validate file size (max 2MB)
                if ($file['size'] > 2 * 1024 * 1024) {
                    $upload_errors[] = ucfirst(str_replace('-', ' ', $file_prefix)) . ': File too large. Maximum 2MB allowed.';
                    continue;
                }
                
                // Create target directory if it doesn't exist
                $target_dir = get_stylesheet_directory() . '/assets/logos/';
                if (!file_exists($target_dir)) {
                    wp_mkdir_p($target_dir);
                }
                
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = $file_prefix . '.' . $extension;
                $target_path = $target_dir . $filename;
                
                // Delete old file if exists
                $old_url = get_option($option_name, '');
                if ($old_url) {
                    $old_path = str_replace(
                        get_stylesheet_directory_uri() . '/assets/logos/',
                        get_stylesheet_directory() . '/assets/logos/',
                        $old_url
                    );
                    if (file_exists($old_path) && $old_path !== $target_path) {
                        @unlink($old_path);
                    }
                }
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    // Save URL to options
                    $file_url = get_stylesheet_directory_uri() . '/assets/logos/' . $filename;
                    update_option($option_name, $file_url);
                    $upload_success[] = ucfirst(str_replace('-', ' ', $file_prefix)) . ' uploaded successfully';
                } else {
                    $upload_errors[] = ucfirst(str_replace('-', ' ', $file_prefix)) . ': Failed to save file.';
                }
            }
        }
        
        // Add admin notices
        if (!empty($upload_success)) {
            add_action('admin_notices', function() use ($upload_success) {
                echo '<div class="notice notice-success is-dismissible"><p>' . implode('<br>', $upload_success) . '</p></div>';
            });
        }
        
        if (!empty($upload_errors)) {
            add_action('admin_notices', function() use ($upload_errors) {
                echo '<div class="notice notice-error is-dismissible"><p>' . implode('<br>', $upload_errors) . '</p></div>';
            });
        }
        
        if (empty($upload_errors) && empty($upload_success)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>Certificate signatures updated successfully!</p></div>';
            });
        }
    }
});

// Email template functions are already declared in the main functions.php file
// No need to redeclare them here to avoid "Cannot redeclare" errors

