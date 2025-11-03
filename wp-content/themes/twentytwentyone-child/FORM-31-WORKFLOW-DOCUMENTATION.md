# Form 31 (Exam Renewal/Recertification) Workflow Implementation

## Overview

This document outlines the complete Form 31 workflow that has been implemented to replicate the same functionality as Form 15, but specifically for exam renewal/recertification processes. The implementation is contained in a separate file to avoid affecting existing Form 15 functionality.

## New Feature: URL Parameter Auto-Fill

### Description
Form 31 now supports automatic field population from URL parameters. This allows external systems or links to pre-fill form fields with specific values.

### Supported Parameters
- `method`: NDT method (UT, RT, PT, MT, ET, VT, TT, PAUT, TOFD)
- `level`: Certification level (1, 2, 3)
- `sector.level` or `sector_level`: Sector level (General, Specific, etc.)
- `scope`: Certification scope (Full, Limited, etc.)

### Usage Examples
```
# Single parameter
/form-31-page/?method=UT

# Multiple parameters
/form-31-page/?method=UT&level=2&scope=Limited

# Using sector.level
/form-31-page/?method=RT&sector.level=General&scope=Full

# Alternative format
/form-31-page/?method=PT&sector_level=Specific&level=1
```

### Configuration
1. **Field ID Mapping**: Update the `$field_mapping` array in `populate_form_31_from_url_params()` function with actual Form 31 field IDs
2. **Debug Mode**: Enable `debug_form_31_fields()` filter to log field structure and identify correct IDs
3. **Field Types**: Supports text, select, radio, and checkbox fields

### Implementation Files
- **Auto-fill Function**: `forms/renew-recert-by-exam.php` - `populate_form_31_from_url_params()`
- **Debug Helper**: `forms/renew-recert-by-exam.php` - `debug_form_31_fields()`
- **Form Loading**: `shortcodes.php` - `load_cpd_form_shortcode()`
- **Workflow Management**: `panel/form-31-workflow.php` - Admin interface and assignment handling

## File Structure

- **Main Workflow File**: `panel/form-31-workflow.php` - Admin interface, approval/rejection, assignment handling
- **Form Functions**: `forms/renew-recert-by-exam.php` - URL auto-fill, submission protection, script enqueuing
- **Integration**: Added to `functions.php` includes array
- **Form ID**: 31 (Gravity Form for Exam Renewal/Recertification)

## Workflow Components

### 1. Form Submission Process
- Form 31 submissions are handled similarly to Form 15
- Automatic order number generation
- User meta updates for tracking
- Email notifications to candidates and admins

### 2. Approval/Rejection Process

#### Approval Workflow (`form_31_handle_approve_entry_ajax`)
- **Trigger**: AJAX action `form_31_approve_entry_ajax`
- **Security**: Nonce verification with `approve_nonce`
- **Process**:
  - Validates entry exists and belongs to Form 31
  - Updates approval status metadata
  - Logs approval history with timestamp and approver ID
  - Sends approval email notification to candidate
  - Returns success response

#### Rejection Workflow (`form_31_handle_reject_entry_ajax`)
- **Trigger**: AJAX action `form_31_reject_entry_ajax`
- **Security**: Nonce verification with `reject_nonce`
- **Process**:
  - Validates entry and rejection reason
  - Updates rejection status and reason metadata
  - Sends rejection email with reason to candidate
  - Returns success response

### 3. Examiner/Invigilator Assignment

#### Assignment Interface (`add_form_31_examiner_assignment_to_entry`)
- **Trigger**: Gravity Forms hook `gform_entry_detail_sidebar_middle`
- **Conditions**: 
  - Only displays for Form 31 entries
  - Entry must be approved first
  - Exam center must be linked
- **Features**:
  - Checkbox selection for examiners and invigilators
  - Method slot scheduling (date/time for each exam method)
  - Validation for future dates only
  - Disabled state when marks already entered

#### Assignment Saving (`handle_form_31_assignments_ajax`)
- **Trigger**: AJAX action `save_form_31_assignments`
- **Security**: Nonce verification with `assign_users_nonce_31`
- **Process**:
  - Validates Form 31 entry
  - Sanitizes and validates method slots
  - Saves assignments to entry metadata
  - Updates user assigned entries lists
  - Returns success response

### 4. Method Slot Scheduling

#### Slot Structure
```php
$method_slots = [
    'ET' => [
        'slot_1' => ['date' => '2024-01-15', 'time' => '09:00'],
        'slot_2' => ['date' => '2024-01-16', 'time' => '14:00'] // Optional
    ],
    // ... other methods
];
```

#### Validation Rules
- Slot 1 date and time are required for each method
- All slots must be in the future
- Date format: Y-m-d
- Time format: H:i

### 5. Submitted Forms Listing

#### Display Function (`display_form_31_entries_page`)
- **Location**: Admin menu under Exam Centers
- **Features**:
  - Paginated table of all Form 31 entries
  - Sortable columns (Order No, Name, Email, Center, Methods, Status, Date)
  - Status badges (Approved, Rejected, Pending)
  - Quick view links to entry details
  - Method display (shows selected exam methods)

#### Admin Menu Integration (`add_form_31_admin_menu`)
- **Path**: `edit.php?post_type=exam_center` → `Form 31 - Renewal Exams`
- **Permission**: `manage_options`
- **Page Slug**: `form-31-entries`

## Field Mappings

### Form 31 Field Configuration
```php
$field_map = [
    'methods' => [
        '188.1' => 'ET', '188.2' => 'MT', '188.3' => 'PT', '188.4' => 'UT', 
        '188.5' => 'RT', '188.6' => 'VT', '188.7' => 'TT', '188.8' => 'PAUT', '188.9' => 'TOFD'
    ],
    'exam_order_no' => '789',
    'candidate_name' => '1',
    'user_email' => '12',
    'prefer_center' => '833'
];
```

**⚠️ Important**: These field IDs need to be updated based on the actual Form 31 structure in Gravity Forms.

## Database Storage

### Entry Metadata
- `approval_status`: 'pending', 'approved', 'rejected'
- `approved_by`: User ID of approver
- `rejected_by`: User ID of rejecter
- `approval_time`: MySQL datetime of approval
- `rejection_reason`: Text reason for rejection
- `_method_slots`: Serialized array of scheduled slots
- `_assigned_examiners`: Array of examiner user IDs
- `_assigned_invigilators`: Array of invigilator user IDs
- `_linked_exam_center`: Exam center post ID

### User Metadata
- `_assigned_entries_examiner`: Array of assigned entry IDs
- `_assigned_entries_invigilator`: Array of assigned entry IDs

## Integration with Existing System

### JavaScript Integration
- Uses existing SweetAlert2 for confirmations
- AJAX handling with WordPress admin-ajax.php
- Form validation and submission handling
- Real-time slot management (add/remove slot 2)

### Email System Integration
- Uses existing email templates if available
- HTML email content type
- Notification system for approvals/rejections
- Assignment notifications to examiners/invigilators

### User Role Integration
- Respects existing permission system
- Administrator-only approval/rejection buttons
- Examiner/invigilator role assignments
- Center admin associations

## Differences from Form 15

### Customizations for Renewal Process
1. **Form Identification**: Specifically checks for Form ID 31
2. **Email Templates**: Renewal-specific messaging
3. **Workflow Labels**: "Renewal" instead of "Initial" exam
4. **Separate AJAX Actions**: Unique action names to avoid conflicts
5. **Independent Menu**: Separate admin page for Form 31 entries
6. **Isolated Metadata**: Uses same meta keys but isolated by form

### Maintained Compatibility
- Same assignment interface structure
- Same slot scheduling system
- Same approval workflow logic
- Compatible with existing examiner/invigilator dashboards

## Installation & Configuration

### 1. File Placement
```
wp-content/themes/twentytwentyone-child/panel/form-31-workflow.php
wp-content/themes/twentytwentyone-child/forms/renew-recert-by-exam.php
```

### 2. Integration (Already Done)
The file is automatically included via the updated functions.php includes array.

### 3. Field ID Configuration
Update the field IDs in the `$field_map` arrays throughout the file to match your actual Form 31 field structure.

### 4. Testing Checklist
- [ ] Form 31 submissions create proper entries
- [ ] Approval/rejection buttons appear on Form 31 entries only
- [ ] Email notifications are sent correctly
- [ ] Assignment interface appears after approval
- [ ] Method slot scheduling works
- [ ] Assignment saving functions properly
- [ ] Form 31 entries appear in admin listing
- [ ] No interference with existing Form 15 functionality
- [ ] **NEW**: URL parameter auto-fill works correctly
- [ ] **NEW**: Field mapping matches actual Form 31 structure

## URL Parameter Auto-Fill Configuration Guide

### Step 1: Identify Form 31 Field IDs
1. Enable debug mode by uncommenting this line in `forms/renew-recert-by-exam.php`:
   ```php
   add_filter('gform_pre_render_31', 'debug_form_31_fields');
   ```

2. Load Form 31 in your browser
3. Check WordPress debug log for field structure output
4. Note down the field IDs for method, level, sector.level, and scope fields

### Step 2: Update Field Mapping
In `forms/renew-recert-by-exam.php`, locate the `populate_form_31_from_url_params()` function and update the `$field_mapping` array:

```php
$field_mapping = [
    'method' => 188,        // Replace with actual Method field ID
    'level' => 189,         // Replace with actual Level field ID  
    'sector_level' => 190,  // Replace with actual Sector.Level field ID
    'scope' => 191,         // Replace with actual Scope field ID
];
```

### Step 3: Test Auto-Fill Functionality
Test with URLs like:
- `/your-form-page/?method=UT&level=2`
- `/your-form-page/?method=RT&scope=Limited`
- `/your-form-page/?method=PT&sector.level=General`

### Step 4: Disable Debug Mode
After configuration, comment out the debug filter:
```php
// add_filter('gform_pre_render_31', 'debug_form_31_fields');
```

### Field Type Handling
- **Text Fields**: Direct value assignment to `defaultValue`
- **Select/Radio**: Sets the selected option via `defaultValue`
- **Checkbox**: Automatically checks options that match the parameter value

### Troubleshooting
1. **Values not appearing**: Check field IDs in the mapping array
2. **Partial matches**: Ensure parameter values exactly match field choices
3. **Multiple values**: Use multiple URL parameters for different fields

## Maintenance Notes

### Regular Updates Required
1. **Field ID Verification**: Ensure field IDs match actual Form 31 structure
2. **Email Template Updates**: Modify email content as needed for renewal process
3. **Permission Checks**: Verify user role permissions are appropriate
4. **Database Cleanup**: Monitor metadata for orphaned entries

### Monitoring Points
- Email delivery success rates
- Assignment completion rates
- Error logs for AJAX failures
- User feedback on workflow usability

## Security Considerations

- All AJAX actions use nonce verification
- User permissions checked before critical operations
- Input sanitization on all form data
- SQL injection prevention through WordPress APIs
- XSS prevention through proper escaping

## Troubleshooting

### Common Issues
1. **Field ID Mismatches**: Update field mappings if form structure changes
2. **Email Delivery**: Check WordPress mail configuration
3. **JavaScript Errors**: Verify SweetAlert2 is loaded
4. **Permission Errors**: Confirm user roles and capabilities
5. **Database Errors**: Check for proper metadata keys
6. **"Another submission is already in progress" Error**: This has been fixed by:
   - Disabling AJAX on Form 31 loading (`ajax="false"`)
   - Adding JavaScript submission protection
   - Implementing server-side rate limiting with transients
7. **"gf_global is not defined" Error**: This has been fixed by:
   - Manually enqueuing Gravity Forms scripts in shortcodes and templates
   - Adding JavaScript fallback for missing gf_global object
   - Ensuring form initialization scripts are loaded

### Form 31 Submission Protection
Implemented multiple layers of protection against duplicate submissions:

1. **AJAX Disabled**: Forms load with `ajax="false"` to prevent submission conflicts
2. **JavaScript Protection**: Client-side flag prevents rapid successive submissions  
3. **Server-side Rate Limiting**: Transients prevent submissions within 10 seconds
4. **Database Validation**: Additional check for recent entries from same user
5. **Script Enqueuing**: Proper Gravity Forms script loading to prevent gf_global errors
6. **JavaScript Fallbacks**: Compatibility layer for missing Gravity Forms objects

### Debug Mode
Add this to wp-config.php for debugging:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Future Enhancements

### Potential Improvements
1. **Bulk Operations**: Approve/reject multiple entries
2. **Advanced Filtering**: Filter by center, method, date range
3. **Export Functionality**: CSV export of Form 31 data
4. **Dashboard Widgets**: Quick stats for Form 31 submissions
5. **Automated Reminders**: Email reminders for pending approvals
6. **Integration APIs**: REST API endpoints for external systems

This implementation provides a complete, isolated workflow for Form 31 that mirrors Form 15 functionality while maintaining system integrity and avoiding conflicts with existing processes.