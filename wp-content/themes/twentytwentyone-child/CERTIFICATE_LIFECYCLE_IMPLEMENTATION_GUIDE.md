# Certificate Lifecycle Management System - Implementation Guide

## Overview

This document provides a comprehensive guide to the enhanced Certificate Lifecycle Management System implemented for the SGNDT (Society for General NDT) platform. The system manages the complete certificate lifecycle from initial issuance through renewal and recertification.

## System Architecture

### Core Components

1. **CertificateLifecycleManager** - Main class handling certificate lifecycle logic
2. **CertificateRenewalAdmin** - Admin dashboard for managing renewals/recertifications
3. **CertificateLifecycleTestSuite** - Comprehensive testing framework
4. **CertificateLifecycleMigration** - Database schema management

### Database Schema

#### Primary Tables

1. **wp_sgndt_final_certifications** - Main certificate storage
2. **wp_sgndt_certificate_status** - Certificate status tracking
3. **wp_sgndt_renewal_applications** - Renewal/recertification applications

## Certificate Lifecycle Flow

### 1. Initial Certificate (5 years)
- **Source**: Gravity Form 15
- **Validity**: 5 years from issue date
- **Certificate Number**: A1034 (base number)
- **Status**: Active

### 2. Renewal Certificate (5 years)
- **Eligibility Window**: 6 months before expiry
- **Methods**: CPD (Continuing Professional Development) or Exam
- **Validity**: 5 years from issue date
- **Certificate Number**: A1034-01
- **Status**: Renewed

### 3. Recertification Certificate (10 years)
- **Eligibility**: After 9 years from initial certificate
- **Methods**: CPD or Exam
- **Validity**: 10 years from issue date
- **Certificate Number**: A1034-02
- **Status**: Recertified

## Key Features

### User-Friendly Interface
- Clear status indicators with visual badges
- Intuitive action buttons based on eligibility
- Comprehensive certificate information display
- Responsive design for mobile devices

### Status Management
- Real-time status tracking
- Comprehensive status history
- Automated status transitions
- Email notifications for status changes

### Admin Dashboard
- Pending applications management
- Approval/rejection workflow
- Certificate generation controls
- Application history tracking

### Eligibility Logic
- Automatic eligibility calculation
- Renewal window management
- Recertification timing
- Expired certificate handling

## Implementation Details

### File Structure

```
wp-content/themes/twentytwentyone-child/
├── certificate-lifecycle-manager.php      # Core lifecycle management
├── certificate-renewal-admin.php          # Admin dashboard
├── certificate-lifecycle-test-suite.php   # Testing framework
├── certificate-lifecycle-migration.php    # Database migration
├── user-profile.php                       # Updated user profile
├── css/user-profile.css                   # Enhanced styling
├── renew/templates/renew-router.php       # Updated renewal forms
└── functions.php                         # Main integration
```

### Key Functions

#### Certificate Lifecycle Management
```php
// Get certificate lifecycle information
$lifecycle = get_certificate_lifecycle($user_id, $certificate_number);

// Update certificate status
update_certificate_lifecycle_status($user_id, $cert_number, $status, $additional_data);

// Get certificate status
$status_info = get_certificate_lifecycle_status($user_id, $certificate_number);

// Get status display
$display = get_certificate_lifecycle_display($status_info, $certificate_number);
```

#### Certificate Number Generation
```php
// Generate certificate number based on type
$new_number = CertificateLifecycleManager::getInstance()->generateCertificateNumber($base_number, $cert_type);

// Calculate expiry date
$expiry_date = CertificateLifecycleManager::getInstance()->calculateExpiryDate($issue_date, $cert_type);
```

### Status Flow

1. **Submitted** - Application received
2. **Reviewing** - Under admin review
3. **Approved** - Application approved
4. **Rejected** - Application rejected
5. **Renewed** - New certificate generated
6. **Expired** - Certificate expired

## Usage Instructions

### For Users

1. **View Certificates**: Access user profile to view all certificates
2. **Check Eligibility**: System automatically shows renewal/recertification eligibility
3. **Apply for Renewal**: Click appropriate action button when eligible
4. **Choose Method**: Select CPD or Exam renewal method
5. **Track Status**: Monitor application status in real-time

### For Administrators

1. **Access Dashboard**: Navigate to "Certificate Renewals" in admin menu
2. **Review Applications**: Check pending applications
3. **Approve/Reject**: Make decisions on applications
4. **Generate Certificates**: Create new certificates for approved applications
5. **Monitor System**: Track all renewal activities

### Testing

1. **Access Test Suite**: Use shortcode `[test_certificate_lifecycle]` on any page
2. **Run Tests**: Execute comprehensive test scenarios
3. **Verify Functionality**: Ensure all components work correctly
4. **Check Database**: Verify data integrity

## Configuration

### Constants

```php
// Certificate validity periods
const INITIAL_CERT_VALIDITY_YEARS = 5;
const RENEWAL_CERT_VALIDITY_YEARS = 5;
const RECERTIFICATION_CERT_VALIDITY_YEARS = 10;

// Renewal windows
const RENEWAL_WINDOW_MONTHS = 6;
const RECERTIFICATION_WINDOW_MONTHS = 6;
const GRACE_PERIOD_MONTHS = 12;
```

### Form Integration

- **Gravity Form 15**: Initial certificate submission
- **Gravity Form 31**: Renewal by exam
- **CPD Form**: Renewal by CPD (existing custom form)

## Error Handling

### Common Issues

1. **Database Schema**: Run migration if tables don't exist
2. **Permission Errors**: Ensure proper user capabilities
3. **Form Integration**: Verify Gravity Forms is active
4. **Status Updates**: Check user meta data integrity

### Debugging

1. **Enable Logging**: Check WordPress error logs
2. **Use Test Suite**: Run comprehensive tests
3. **Check Database**: Verify data consistency
4. **Review Permissions**: Ensure proper access rights

## Security Considerations

### Data Protection
- User data sanitization
- SQL injection prevention
- XSS protection
- CSRF token validation

### Access Control
- Role-based permissions
- Admin-only functions
- User data isolation
- Secure AJAX endpoints

## Performance Optimization

### Caching
- User profile caching
- Status information caching
- Database query optimization

### Database
- Proper indexing
- Efficient queries
- Connection pooling
- Data archiving

## Maintenance

### Regular Tasks
1. **Database Cleanup**: Remove old status entries
2. **Log Rotation**: Manage error logs
3. **Backup Verification**: Ensure data integrity
4. **Performance Monitoring**: Track system performance

### Updates
1. **Version Control**: Track system changes
2. **Migration Scripts**: Handle schema updates
3. **Testing**: Verify updates don't break functionality
4. **Documentation**: Update implementation guides

## Troubleshooting

### Common Problems

1. **Status Not Updating**: Check user meta data
2. **Eligibility Issues**: Verify date calculations
3. **Form Submission**: Check Gravity Forms integration
4. **Admin Access**: Verify user permissions

### Solutions

1. **Clear Cache**: Refresh user profile cache
2. **Recheck Dates**: Verify certificate dates
3. **Test Forms**: Use test suite for validation
4. **Check Logs**: Review error logs for issues

## Future Enhancements

### Planned Features
1. **Bulk Operations**: Mass certificate management
2. **Advanced Reporting**: Detailed analytics
3. **Email Templates**: Customizable notifications
4. **API Integration**: External system connectivity

### Scalability
1. **Multi-site Support**: Network-wide management
2. **Load Balancing**: High-traffic handling
3. **Database Sharding**: Large-scale data management
4. **Microservices**: Modular architecture

## Support

### Documentation
- This implementation guide
- Code comments and inline documentation
- Test suite examples
- Admin dashboard help

### Contact
- Technical support through admin channels
- Issue reporting via error logs
- Feature requests through admin interface
- System monitoring and alerts

---

## Quick Start Checklist

- [ ] Database migration completed
- [ ] Admin dashboard accessible
- [ ] User profile updated
- [ ] Forms integrated
- [ ] Test suite functional
- [ ] Email notifications working
- [ ] Status tracking operational
- [ ] Certificate generation tested
- [ ] Error handling verified
- [ ] Security measures implemented

This implementation provides a robust, user-friendly certificate lifecycle management system that handles all aspects of certificate renewal and recertification with professional-grade functionality and maintainability.
