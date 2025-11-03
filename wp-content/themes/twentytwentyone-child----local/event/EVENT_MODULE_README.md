# Event Module - Comprehensive Event Management System

## Overview
The Event Module is a complete, secure, and optimized event management system built specifically for WordPress. It provides comprehensive event registration, approval workflows, CPD tracking, and administrative tools with advanced security, error handling, and performance optimizations.

## Features

### 🎯 Core Functionality
- **Event Registration**: Seamless event registration with Gravity Forms integration
- **Approval Workflow**: Admin approval/rejection system with email notifications
- **QR Code Generation**: Automatic QR code generation for event verification
- **CPD Tracking**: Continuing Professional Development points management
- **Check-in/Check-out**: Time tracking for event attendance
- **Member Pricing**: Different pricing for members vs non-members

### 🔒 Security Features
- **Nonce Verification**: All AJAX requests protected with WordPress nonces
- **Input Sanitization**: All user inputs properly sanitized and validated
- **Capability Checks**: Proper permission checks for all admin functions
- **SQL Injection Protection**: Prepared statements for all database queries
- **XSS Prevention**: Output escaping for all displayed data

### 📊 Admin Management
- **Event Registrations**: View and manage all event registrations
- **Attendee Management**: CPD points management and reporting
- **Event Logs**: Comprehensive logging system for debugging
- **Bulk Operations**: Bulk approve/reject registrations
- **PDF Export**: Generate CPD reports in PDF format

### 🚀 Performance Optimizations
- **Caching**: Intelligent caching for frequently accessed data
- **Database Optimization**: Optimized queries with proper indexing
- **Lazy Loading**: Efficient data loading strategies
- **Background Processing**: Heavy operations handled asynchronously

### 🎨 User Experience
- **Loading States**: Visual feedback during operations
- **Real-time Updates**: Dynamic UI updates without page refresh
- **Responsive Design**: Mobile-friendly admin interface
- **Error Handling**: User-friendly error messages and recovery

## File Structure

```
event/
├── event_module.php          # Main module file
├── event-functions.php       # Core event functionality
├── event-ajax.php           # AJAX handlers with security
├── event-admin.php          # Admin interface
├── event-logger.php         # Comprehensive logging system
├── event-templates.php      # Email templates and PDF generation
├── event-migration.php      # Migration utilities
├── js/
│   ├── event-frontend.js    # Frontend JavaScript
│   └── event-admin.js       # Admin JavaScript
├── css/
│   ├── event-frontend.css   # Frontend styles
│   └── event-admin.css      # Admin styles
├── logs/                    # Log files directory
└── EVENT_MODULE_README.md   # This documentation
```

## Installation

### 1. Automatic Installation
The event module is automatically loaded when the theme is activated. No additional installation steps are required.

### 2. Manual Verification
To verify the module is loaded correctly:
1. Go to **WordPress Admin → Events → Event Migration**
2. Run the functionality tests
3. Check that all tests pass

## Configuration

### 1. Event Pricing
- Go to **Events → Add New Event**
- Set regular price in the "Event Cost" field
- Set member price in the "Member Price" meta box
- Members will automatically see discounted pricing

### 2. Email Templates
Email templates are automatically configured with placeholders:
- `{user_name}` - User's display name
- `{event_name}` - Event title
- `{event_start}` - Event start date/time
- `{event_end}` - Event end date/time
- `{cpd_points}` - CPD points awarded

### 3. CPD Points
- CPD points are automatically calculated based on attendance
- Admins can manually adjust points in the Attendee Management page
- Points are tracked per event and totaled per user

## Usage

### For Users

#### Registering for Events
1. Visit any event page
2. Click "Join Event" button
3. Fill out the registration form
4. Wait for admin approval
5. Receive QR code via email when approved

#### Checking In/Out
1. Use QR code at event entrance
2. Check out when leaving
3. CPD points automatically calculated

### For Administrators

#### Managing Registrations
1. Go to **Events → Event Registrations**
2. View all pending registrations
3. Approve or reject with reason
4. Monitor registration status

#### Managing Attendees
1. Go to **Events → Attendee Management**
2. View all attendees and their CPD points
3. Edit CPD points for specific events
4. Export CPD reports as PDF

#### Monitoring System
1. Go to **Events → Event Logs**
2. View system logs and errors
3. Monitor event operations
4. Debug issues

## API Reference

### Core Functions

#### Event Functions
```php
// Check if user can join event
event_can_user_join($user_id, $event_id);

// Get user registration status
event_get_user_registration_status($user_id, $event_id);

// Get event attendees with caching
event_get_attendees_cached($event_id);

// Clear event cache
event_clear_cache($event_id);
```

#### Logging Functions
```php
// Log different levels
event_log_error($message, $context);
event_log_warning($message, $context);
event_log_info($message, $context);
event_log_debug($message, $context);
```

#### Email Templates
```php
// Get email template with placeholders
event_get_email_template($template_name, $placeholders);
```

### AJAX Endpoints

#### Event Approval
```javascript
// Approve event registration
$.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'event_approve_entry_ajax',
        entry_id: entryId,
        user_id: userId,
        event_id: eventId,
        nonce: approve_nonce
    }
});
```

#### Event Rejection
```javascript
// Reject event registration
$.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'event_reject_entry_ajax',
        entry_id: entryId,
        user_id: userId,
        event_id: eventId,
        reject_reason: reason,
        nonce: reject_nonce
    }
});
```

## Security

### Nonce Verification
All AJAX requests include nonce verification:
```php
if (!check_ajax_referer('approve_nonce', 'nonce', false)) {
    wp_send_json_error(['message' => 'Security check failed']);
}
```

### Input Sanitization
All inputs are properly sanitized:
```php
$entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
$reject_reason = sanitize_text_field($_POST['reject_reason']);
```

### Capability Checks
All admin functions check capabilities:
```php
if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => 'Insufficient permissions']);
}
```

## Performance

### Caching Strategy
- Event attendees cached for 1 hour
- Cache automatically cleared on updates
- Efficient database queries with proper indexing

### Database Optimization
- Prepared statements for all queries
- Optimized JOIN operations
- Proper indexing on meta keys

### Memory Management
- Efficient data processing
- Minimal memory footprint
- Background processing for heavy operations

## Troubleshooting

### Common Issues

#### 1. AJAX Requests Failing
**Symptoms**: Buttons not working, JavaScript errors
**Solution**: 
- Check browser console for errors
- Verify nonce values are correct
- Ensure user has proper permissions

#### 2. Emails Not Sending
**Symptoms**: No email notifications received
**Solution**:
- Check WordPress mail configuration
- Verify SMTP settings
- Check spam folders

#### 3. CPD Points Not Calculating
**Symptoms**: Points not updating after checkout
**Solution**:
- Verify check-in time is recorded
- Check event duration settings
- Review CPD calculation logic

#### 4. QR Codes Not Generating
**Symptoms**: QR code generation fails
**Solution**:
- Check QR library is installed
- Verify upload directory permissions
- Check PHP memory limits

### Debug Mode
Enable debug mode to see detailed logs:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Log Analysis
Check event logs for detailed error information:
1. Go to **Events → Event Logs**
2. Filter by ERROR level
3. Review error messages and context

## Migration

### From Old System
If migrating from the old event system:
1. Go to **Events → Event Migration**
2. Run migration steps in order
3. Test functionality after each step
4. Verify all features work correctly

### Backup
Always backup before migration:
- Database backup
- File system backup
- Test on staging environment first

## Maintenance

### Regular Tasks
- [ ] Monitor event logs weekly
- [ ] Check cache performance monthly
- [ ] Review CPD calculations quarterly
- [ ] Update email templates as needed

### Performance Monitoring
- Monitor database query performance
- Check memory usage during peak times
- Review error logs regularly
- Optimize based on usage patterns

## Support

### Documentation
- This README file
- Inline code comments
- WordPress Codex references
- Event logs for debugging

### Error Reporting
When reporting issues, include:
- Error messages from logs
- Steps to reproduce
- User roles and permissions
- WordPress and theme versions

## Changelog

### Version 1.0.0
- Initial release
- Complete event management system
- Security improvements
- Performance optimizations
- Comprehensive logging
- User experience enhancements

## License

This module is part of the NDTSS theme and follows the same licensing terms.

## Contributing

When contributing to this module:
1. Follow WordPress coding standards
2. Include proper documentation
3. Add error handling
4. Test thoroughly
5. Update this README if needed

---

**Note**: This module is designed to work seamlessly with the existing membership system and other theme modules. All functionality has been tested and optimized for production use.


