# Membership Module Logging System

## Overview
This comprehensive logging system provides detailed error tracking and monitoring for all membership module operations. All logs are stored in the `membership/logs/` directory with automatic rotation and cleanup.

## Features

### 1. **Dedicated Log Files**
- **Location:** `wp-content/themes/twentytwentyone-child/membership/logs/`
- **Format:** `membership-errors-YYYY-MM.log`
- **Rotation:** Automatic monthly rotation
- **Cleanup:** Automatic cleanup of old logs (keeps 5 files max)

### 2. **Log Levels**
- **ERROR:** Critical errors that need immediate attention
- **WARNING:** Issues that should be monitored
- **INFO:** General information about operations
- **DEBUG:** Detailed debugging information (only when WP_DEBUG is enabled)

### 3. **Rich Context Data**
Each log entry includes:
- Timestamp
- Log level
- User information (ID, name)
- IP address
- Detailed context data
- Error messages

## File Structure

```
membership/
├── logs/                          # Log directory
│   ├── .htaccess                  # Security protection
│   ├── membership-errors-2024-01.log
│   ├── membership-errors-2024-02.log
│   └── ...
├── membership-logger.php          # Core logging class
├── membership-log-viewer.php      # Admin interface
├── test-logging-system.php        # Testing tools
└── LOGGING_SYSTEM_README.md       # This documentation
```

## Usage

### Basic Logging Functions

```php
// Log an error
membership_log_error('Database connection failed', [
    'host' => $host,
    'database' => $database,
    'error' => $error_message
]);

// Log a warning
membership_log_warning('Email template not found', [
    'template_name' => $template,
    'user_id' => $user_id
]);

// Log information
membership_log_info('User registration completed', [
    'user_id' => $user_id,
    'email' => $user_email,
    'membership_type' => $type
]);

// Log debug information
membership_log_debug('Processing form data', [
    'form_id' => $form_id,
    'fields' => $form_fields
]);
```

### Advanced Usage

```php
// Direct class usage
MembershipLogger::error('Custom error message', $context);
MembershipLogger::warning('Custom warning', $context);
MembershipLogger::info('Custom info', $context);
MembershipLogger::debug('Custom debug', $context);

// Get log statistics
$stats = MembershipLogger::get_log_stats();

// Retrieve logs
$recent_logs = MembershipLogger::get_logs(100, 'ERROR');

// Clear logs
MembershipLogger::clear_logs();
```

## Logged Operations

### 1. **Form Submissions**
- Form submission start/completion
- Email sending success/failure
- User data validation errors
- Database update errors

### 2. **Membership Approval/Rejection**
- Approval process start/completion
- Rejection process with reasons
- Email notification success/failure
- Role assignment success/failure

### 3. **Reminder System**
- Reminder process start/completion
- Individual reminder sending
- Email template issues
- User data retrieval errors

### 4. **Certificate Generation**
- Certificate generation start/completion
- PDF generation errors
- File permission issues
- Database update errors

### 5. **Email Operations**
- Email sending attempts
- SMTP configuration issues
- Template rendering errors
- Recipient validation errors

## Admin Interface

### Access Logs
1. Go to **WordPress Admin → Membership → Membership Logs**
2. View recent log entries
3. Filter by log level
4. Adjust number of entries shown

### Log Management
- **View Logs:** Browse recent entries with color coding
- **Filter by Level:** Show only ERROR, WARNING, INFO, or DEBUG logs
- **Clear Logs:** Remove all log files (with confirmation)
- **Statistics:** View log file statistics and sizes

### Testing Tools
1. Go to **WordPress Admin → Membership → Test Logging**
2. Run comprehensive tests
3. Check system status
4. Test performance and integration

## Log Format

### Standard Log Entry
```
[2024-01-15 14:30:25] [ERROR] [User: Admin User (ID: 1)] [IP: 192.168.1.100] Database connection failed | Context: {"host":"localhost","database":"ndtss","error":"Connection refused"}
```

### Context Data Examples
```json
{
    "user_id": 123,
    "form_id": 5,
    "entry_id": 456,
    "membership_type": "individual",
    "error": "Email sending failed",
    "email": "user@example.com",
    "template": "approval_notification"
}
```

## Configuration

### Log File Settings
```php
// Maximum log file size (10MB)
private static $max_log_size = 10485760;

// Maximum number of log files to keep (5)
private static $max_log_files = 5;

// Log directory
private static $log_dir = '/membership/logs/';
```

### Security
- Log files are protected by `.htaccess`
- Direct access to log files is denied
- Only admin users can view logs
- Sensitive data is sanitized before logging

## Monitoring and Maintenance

### Regular Checks
1. **Monitor Error Logs:** Check for recurring errors
2. **Review Warnings:** Address potential issues
3. **Check Log Sizes:** Ensure logs aren't growing too large
4. **Clean Old Logs:** Remove outdated log files

### Performance Considerations
- Logs are written asynchronously
- Minimal impact on page load times
- Automatic log rotation prevents disk space issues
- Debug logs only enabled when WP_DEBUG is on

## Troubleshooting

### Common Issues

1. **Log Directory Not Writable**
   ```bash
   chmod 755 wp-content/themes/twentytwentyone-child/membership/logs/
   ```

2. **No Logs Being Created**
   - Check file permissions
   - Verify WordPress can write to the directory
   - Check for PHP errors

3. **Logs Not Displaying**
   - Check admin permissions
   - Verify log files exist
   - Check for JavaScript errors

4. **Performance Issues**
   - Reduce log retention period
   - Increase log rotation frequency
   - Disable debug logging in production

### Debug Mode
Enable WordPress debug mode to see debug logs:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Integration with Other Systems

### WordPress Error Log
- Falls back to WordPress error log if file writing fails
- Integrates with existing WordPress debugging

### Monitoring Tools
- Logs can be monitored by external tools
- JSON context data is machine-readable
- Timestamps are standardized

### Backup Systems
- Log files should be included in backups
- Consider log retention policies
- Archive old logs if needed

## Best Practices

### 1. **Log Appropriately**
- Use ERROR for critical issues
- Use WARNING for potential problems
- Use INFO for important events
- Use DEBUG for detailed troubleshooting

### 2. **Include Context**
- Always include relevant data
- Use consistent field names
- Sanitize sensitive information
- Include user and request information

### 3. **Monitor Regularly**
- Check logs daily in production
- Set up alerts for ERROR level logs
- Review WARNING logs weekly
- Clean up old logs monthly

### 4. **Security**
- Never log passwords or sensitive data
- Sanitize user input before logging
- Protect log files from direct access
- Regular security audits

## Support and Maintenance

### Regular Tasks
- [ ] Check log file sizes weekly
- [ ] Review error logs daily
- [ ] Clean old logs monthly
- [ ] Monitor disk space usage
- [ ] Test logging system monthly

### Emergency Procedures
- [ ] Clear logs if disk space is full
- [ ] Disable debug logging in production
- [ ] Check file permissions if logs stop working
- [ ] Restore from backup if logs are corrupted

## Conclusion

The membership logging system provides comprehensive error tracking and monitoring capabilities. It helps identify issues quickly, monitor system health, and maintain reliable membership operations. Regular monitoring and maintenance ensure optimal performance and security.
