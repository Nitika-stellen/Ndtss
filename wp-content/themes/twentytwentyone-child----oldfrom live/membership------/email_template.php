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
        <code>{rejection_reason}</code>
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
    
    echo '</form></div>';
}

add_action('admin_init', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'save_email_templates')) {
        foreach ($_POST as $key => $value) {
            update_option(sanitize_text_field($key), wp_kses_post($value));
        }
    }
});

