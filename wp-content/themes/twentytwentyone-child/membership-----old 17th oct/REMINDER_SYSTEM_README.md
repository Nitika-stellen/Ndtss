# Membership Expiry Reminder System

## Overview
This system automatically sends reminder emails to members before their membership expires. It integrates with the existing membership module and uses WordPress cron jobs for scheduling.

## Features
- **Automatic Reminders**: Sends emails 30, 14, 7, and 1 days before membership expiry
- **Customizable Templates**: Admin can customize email subject, heading, and message
- **Manual Control**: Admin can send reminders manually and view expiring memberships
- **Duplicate Prevention**: Prevents sending multiple reminders on the same day
- **Admin Dashboard**: Dedicated admin page to manage reminders

## Setup Instructions

### 1. Initialize the System
1. Go to WordPress Admin → Membership → Test Reminders
2. Click "Send Test Reminders" to initialize the system
3. Or visit: `yoursite.com/wp-admin/admin.php?page=test-reminders&setup_reminder_templates=1`

### 2. Configure Email Templates
1. Go to WordPress Admin → Membership → Membership Email Templates
2. Scroll down to "Membership Expiry Reminder" section
3. Customize the subject, heading, and message content
4. Use these placeholders:
   - `{user_name}` - Member's display name
   - `{membership_type}` - Type of membership (Individual/Corporate)
   - `{expiry_date}` - Membership expiry date
   - `{days_until_expiry}` - Number of days until expiry

### 3. Monitor Reminders
1. Go to WordPress Admin → Membership → Membership Reminders
2. View all memberships expiring in the next 30 days
3. Send manual reminders if needed
4. Check reminder history for each member

## How It Works

### Automatic Process
1. WordPress cron job runs daily
2. System checks all users with approved memberships
3. Calculates days until expiry for each member
4. Sends reminder if member is in reminder period (30, 14, 7, or 1 days)
5. Logs reminder as sent to prevent duplicates

### Manual Process
1. Admin can trigger reminders manually
2. Admin can send individual reminders to specific members
3. System shows real-time status of expiring memberships

## File Structure
```
membership/
├── functions.php (main functionality)
├── email_template.php (template management)
├── setup_reminder_templates.php (initialization)
├── test_reminder_system.php (testing tools)
└── REMINDER_SYSTEM_README.md (this file)
```

## Database Changes
The system uses existing user meta fields and adds:
- `last_reminder_sent` - Date when last reminder was sent
- `last_reminder_days` - Days until expiry when last reminder was sent

## Cron Job Details
- **Hook**: `membership_expiry_reminder_cron`
- **Frequency**: Daily
- **Function**: `send_membership_expiry_reminders()`

## Troubleshooting

### Reminders Not Sending
1. Check if cron job is scheduled: Go to Test Reminders page
2. Verify email templates are configured
3. Check WordPress cron is working: Install "WP Crontrol" plugin
4. Check error logs for any issues

### Email Issues
1. Verify SMTP settings in WordPress
2. Check spam folders
3. Test with a simple email first
4. Verify email templates have content

### Performance
- System only processes users with approved memberships
- Uses efficient database queries
- Prevents duplicate reminders automatically

## Customization

### Change Reminder Schedule
Edit the `$reminder_days` array in `functions.php`:
```php
$reminder_days = [30, 14, 7, 1]; // Change these numbers
```

### Add More Email Templates
Add new templates to the `$templates` array in `email_template.php`

### Modify Reminder Logic
Edit the `send_membership_expiry_reminders()` function in `functions.php`

## Security
- All functions require `manage_options` capability
- Input sanitization and output escaping
- Nonce verification for manual actions
- Proper WordPress coding standards

## Support
For issues or questions, check:
1. WordPress error logs
2. Test Reminders page for system status
3. Email template configuration
4. Cron job scheduling
