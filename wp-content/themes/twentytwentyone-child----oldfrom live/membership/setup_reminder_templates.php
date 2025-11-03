<?php
/**
 * Setup default reminder email templates
 * Run this once to initialize the reminder system with default templates
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function setup_default_reminder_templates() {
    // Default reminder email templates
    $default_templates = [
        'user_membership_reminder_enabled' => 'yes',
        'user_membership_reminder_subject' => 'Membership Expiry Reminder - {days_until_expiry} days remaining',
        'user_membership_reminder_heading' => 'Your {membership_type} Membership is Expiring Soon',
        'user_membership_reminder_message' => 'Dear {user_name},

Your {membership_type} membership with the Non-Destructive Testing Society (Singapore) will expire on {expiry_date}.

You have {days_until_expiry} days remaining to renew your membership to continue enjoying all the benefits and privileges.

To renew your membership, please visit our website and complete the renewal process.

If you have any questions or need assistance with the renewal process, please don\'t hesitate to contact us.

Thank you for being a valued member of our society.

Best regards,
NDTSS Team'
    ];
    
    // Set default values only if they don't exist
    foreach ($default_templates as $option => $value) {
        if (get_option($option) === false) {
            update_option($option, $value);
        }
    }
    
    // Setup the cron job
    if (!wp_next_scheduled('membership_expiry_reminder_cron')) {
        wp_schedule_event(time(), 'daily', 'membership_expiry_reminder_cron');
    }
    
    return true;
}

// Run setup if called directly
if (isset($_GET['setup_reminder_templates']) && current_user_can('manage_options')) {
    setup_default_reminder_templates();
    echo '<div class="notice notice-success"><p>Reminder templates setup completed successfully!</p></div>';
}
