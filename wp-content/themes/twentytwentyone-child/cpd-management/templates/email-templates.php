<?php
/**
 * CPD Email Templates Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$cpd_manager = CPD_Manager::get_instance();
if (!$cpd_manager->user_can_access_cpd_admin()) {
    wp_die('You do not have permission to access this page.');
}

/**
 * Get default email template content
 */
function cpd_get_email_template_defaults($template_key) {
    $defaults = array(
        'user_entry_submitted' => array(
            'subject' => 'CPD Entry Submitted Successfully',
            'heading' => 'CPD Entry Submitted',
            'message' => '<p>Dear {user_name},</p>
<p>Thank you for submitting your CPD entry. Your entry has been received and is pending review.</p>
<p><strong>Entry Details:</strong></p>
<ul>
    <li>Activity Date: {activity_date}</li>
    <li>Category: {activity_category}</li>
    <li>Points Requested: {points_requested}</li>
</ul>
<p>We will review your entry and notify you once the review is complete. You will receive an email notification when your entry has been approved or if any additional information is required.</p>
<p>You can view your CPD entries and track their status by visiting your profile: <a href="{user_profile_link}">View My CPD Summary</a></p>
<p>Thank you for your participation in continuing professional development.</p>
<p>Best regards,<br>CPD Management Team</p>'
        ),
        'user_entry_approved' => array(
            'subject' => 'CPD Entry Approved - {activity_title}',
            'heading' => 'CPD Entry Approved',
            'message' => '<p>Dear {user_name},</p>
<p>We are pleased to inform you that your CPD entry has been reviewed and approved.</p>
<p><strong>Entry Details:</strong></p>
<ul>
    <li>Activity Category: {activity_title}</li>
    <li>Activity Date: {activity_date}</li>
    <li>Category: {activity_category}</li>
    <li>Points Allocated: <strong>{points_allocated}</strong></li>
</ul>
<p><strong>Review Notes:</strong><br>{review_notes}</p>
<p>Your CPD points have been added to your account. You can view your updated CPD summary by visiting your profile: <a href="{user_profile_link}">View My CPD Summary</a></p>
<p>Thank you for your continued commitment to professional development.</p>
<p>Best regards,<br>CPD Management Team</p>'
        ),
        'user_entry_rejected' => array(
            'subject' => 'CPD Entry Review - {activity_title}',
            'heading' => 'CPD Entry Review',
            'message' => '<p>Dear {user_name},</p>
<p>Thank you for submitting your CPD entry. After review, we regret to inform you that your entry could not be approved at this time.</p>
<p><strong>Entry Details:</strong></p>
<ul>
    <li>Activity Category: {activity_title}</li>
    <li>Activity Date: {activity_date}</li>
    <li>Category: {activity_category}</li>
</ul>
<p><strong>Review Notes:</strong></p>
{review_notes}
<p>If you have any questions or would like to provide additional documentation, please feel free to contact us or submit a new entry with the required information.</p>
<p>You can view your CPD entries and their status by visiting your profile: <a href="{user_profile_link}">View My CPD Summary</a></p>
<p>Best regards,<br>CPD Management Team</p>'
        ),
        'user_annual_report' => array(
            'subject' => 'CPD Annual Report for {report_year}',
            'heading' => 'CPD Annual Report {report_year}',
            'message' => '<p>Dear {user_name},</p>
<p>Your CPD summary for the period {period_years} is now available.</p>
<p><strong>Summary (5-Year Period: {period_years}):</strong></p>
<ul>
    <li><strong>Total Points Accumulated (5 Years):</strong> {total_points}</li>
    <li><strong>Points Needed for Renewal/Recertification:</strong> {points_needed}</li>
    <li><strong>Minimum Required:</strong> 150 points over 5 years</li>
</ul>
<p>To be eligible for certificate renewal or recertification, you need a total of 150 CPD points accumulated over any 5-year period. Your current 5-year total is {total_points} points.</p>
<p>You can view your detailed CPD summary and track your progress by visiting your profile: <a href="{user_profile_link}">View My CPD Summary</a></p>
<p>Continue your professional development activities to maintain your certification and meet renewal requirements.</p>
<p>Best regards,<br>CPD Management Team</p>'
        ),
        'admin_new_entry' => array(
            'subject' => 'New CPD Entry Submitted: {activity_title}',
            'heading' => 'New CPD Entry Submitted',
            'message' => '<p>A new CPD entry has been submitted and requires your review.</p>
<p><strong>Entry Details:</strong></p>
<ul>
    <li><strong>Candidate:</strong> {user_name}</li>
    <li><strong>Activity Category:</strong> {activity_title}</li>
    <li><strong>Activity Date:</strong> {activity_date}</li>
    <li><strong>Category:</strong> {activity_category}</li>
    <li><strong>Points Requested:</strong> {points_requested}</li>
</ul>
<p>Please review this entry and allocate appropriate CPD points.</p>
<p><a href="{admin_review_link}" style="display: inline-block; padding: 12px 24px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0;">Review Entry</a></p>
<p>This is an automated notification from the CPD Management System.</p>'
        )
    );
    
    return isset($defaults[$template_key]) ? $defaults[$template_key] : array(
        'subject' => '',
        'heading' => '',
        'message' => ''
    );
}

$templates = [
    'user' => [
        'user_entry_submitted' => [
            'label' => 'CPD Entry Submission Acknowledgement',
            'subject_option' => 'cpd_user_entry_submitted_subject',
            'heading_option' => 'cpd_user_entry_submitted_heading',
            'message_option' => 'cpd_user_entry_submitted_message'
        ],
        'user_entry_approved' => [
            'label' => 'CPD Entry Approved Notification',
            'subject_option' => 'cpd_user_entry_approved_subject',
            'heading_option' => 'cpd_user_entry_approved_heading',
            'message_option' => 'cpd_user_entry_approved_message'
        ],
        'user_entry_rejected' => [
            'label' => 'CPD Entry Rejected Notification',
            'subject_option' => 'cpd_user_entry_rejected_subject',
            'heading_option' => 'cpd_user_entry_rejected_heading',
            'message_option' => 'cpd_user_entry_rejected_message'
        ],
        'user_annual_report' => [
            'label' => 'Annual CPD Report',
            'subject_option' => 'cpd_user_annual_report_subject',
            'heading_option' => 'cpd_user_annual_report_heading',
            'message_option' => 'cpd_user_annual_report_message'
        ]
    ],
    'admin' => [
        'admin_new_entry' => [
            'label' => 'New CPD Entry Notification',
            'subject_option' => 'cpd_admin_new_entry_subject',
            'heading_option' => 'cpd_admin_new_entry_heading',
            'message_option' => 'cpd_admin_new_entry_message'
        ]
    ]
];

// Handle form submission
$templates_saved = false;
if (isset($_POST['save_cpd_email_templates']) && check_admin_referer('save_cpd_email_templates')) {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'cpd_') === 0 || strpos($key, 'user_entry_') === 0 || strpos($key, 'admin_new_entry') === 0) {
            update_option(sanitize_text_field($key), wp_kses_post($value));
        }
    }
    $templates_saved = true;
}

echo '<div class="wrap email-settings-container"><h1>CPD Email Templates</h1>';
echo '<p>You can use the following placeholders in your email templates: 
    <code>{user_name}</code>, 
    <code>{activity_title}</code>, 
    <code>{activity_date}</code>, 
    <code>{activity_category}</code>, 
    <code>{points_allocated}</code>, 
    <code>{points_requested}</code>, 
    <code>{review_notes}</code>, 
    <code>{total_points}</code> (5-year total), 
    <code>{points_needed}</code>, 
    <code>{report_year}</code>,
    <code>{period_years}</code> (e.g., "2020 - 2024"),
    <code>{admin_review_link}</code>,
    <code>{user_profile_link}</code>
</p>';

// Styled tab navigation
echo '<ul class="nav-tab-wrapper">';
echo '<li><a href="#user" class="nav-tab nav-tab-active">User Emails</a></li>';
echo '<li><a href="#admin" class="nav-tab">Admin Emails</a></li>';
echo '</ul>';

echo '<form method="post">';
wp_nonce_field('save_cpd_email_templates');

foreach ($templates as $group => $group_templates) {
    echo '<div id="' . $group . '" class="tab-content" style="' . ($group === 'user' ? '' : 'display:none;') . '">';
    echo '<h2 class="tab-title">' . ucfirst($group) . ' Email Settings</h2>';

    foreach ($group_templates as $key => $details) {
        // Get saved values or use defaults
        $defaults = cpd_get_email_template_defaults($key);
        
        $subject = get_option($details['subject_option'], $defaults['subject']);
        $heading = get_option($details['heading_option'], $defaults['heading']);
        $message = get_option($details['message_option'], $defaults['message']);
        $enabled = get_option($key . '_enabled', 'yes');

        echo '<div class="email-template-card">';
        echo "<button type='button' class='toggle-btn' data-target='{$key}'>▼ {$details['label']}</button>";
        echo "<div id='{$key}' class='email-content' style='display: none;'>";

        echo "<label><input type='checkbox' name='{$key}_enabled' value='yes' " . checked($enabled, 'yes', false) . "> Enable this email notification</label><br><br>";
        echo "<label>Subject:<br><input type='text' name='{$details['subject_option']}' value='" . esc_attr($subject) . "' class='regular-text input-field'></label><br><br>";
        echo "<label>Email Heading:<br><input type='text' name='{$details['heading_option']}' value='" . esc_attr($heading) . "' class='regular-text input-field'></label><br><br>";
        echo "<label>Message Content:<br>";
        wp_editor($message, $details['message_option'], ['textarea_rows' => 6, 'media_buttons' => false]);
        echo "</label><br><hr></div></div>";
    }
    echo '</div>';
}

submit_button('Save Templates', 'primary', 'save_cpd_email_templates');

echo '</form>';
echo '</div>';

// Add SweetAlert for save confirmation
?>
<script>
jQuery(document).ready(function($) {
    // Handle email templates form submission
    $('form').on('submit', function(e) {
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        
        // Only handle if it's the email templates form
        if ($submitBtn.attr('name') !== 'save_cpd_email_templates') {
            return;
        }
        
        e.preventDefault();
        
        // Show loading
        Swal.fire({
            title: 'Saving Templates...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function() {
                Swal.showLoading();
            }
        });
        
        // Submit form via AJAX
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                Swal.close();
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Email templates saved successfully!',
                    confirmButtonColor: '#0073aa',
                    timer: 2000,
                    timerProgressBar: true
                }).then(function() {
                    window.location.reload();
                });
            },
            error: function() {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while saving templates. Please try again.',
                    confirmButtonColor: '#dc3232'
                });
            }
        });
    });
    
    // Show success message if templates were saved
    <?php if ($templates_saved): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Email templates saved successfully!',
        confirmButtonColor: '#0073aa',
        timer: 2000,
        timerProgressBar: true
    });
    <?php endif; ?>
});
</script>
<?php

