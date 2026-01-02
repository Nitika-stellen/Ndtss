# Mail System Analysis - NDTSS Certification System

## Overview
This document provides a comprehensive analysis of the email notification system for the NDTSS (Non-Destructive Testing Society Singapore) certification workflow, specifically focusing on the "Save Assignment", "Generate Result Notification", and "Generate Final Certificate" functionalities.

---

## 1. Save Assignment Email Flow

### Trigger Point
- **File**: `center_module.php` (Line 196)
- **Button**: "Save Assignments"
- **AJAX Action**: `save_exam_assignments`
- **Handler Function**: `handle_exam_assignments_ajax()` in `functions.php` (Line 2273)

### Email Recipients & Content

#### 1.1 Examiner/Invigilator Notifications
**Recipients**: Each assigned examiner and invigilator
**File**: `functions.php` (Lines 2392-2430)

**Supported Forms**: 
- Form 15 (Initial Exam)
- Form 30 (Retest Exam)
- Form 39 (Renewal/Recertification Exam)

**Email Details**:
- **Subject**: `Assignment Notification: [Examiner/Invigilator] for [Exam Type] Order #[Order Number]`
- **Content Includes**:
  - User's display name
  - Role (Examiner/Invigilator)
  - Exam type (Initial Exam/Retest Exam)
  - Order number
  - **Method dates table** with:
    - Method name
    - Slot number
    - Date (formatted)
    - Time (formatted)
  - Login instructions
  - Admin contact email

**Code Location**:
```php
// Lines 2404-2415
$user_subject = "Assignment Notification: " . ucfirst($role) . " for {$exam_type} Order #{$field_789_value}";
$user_body = "...includes method_dates_html table...";
wp_mail($user_info->user_email, $user_subject, $user_body);
```

#### 1.2 Admin Summary Email
**Recipients**: 
- Center Admin (from exam center post meta `_center_admin_id`)
- AQB Admin (from exam center post meta `_aqb_admin_id`)
- All users with 'administrator' role

**File**: `functions.php` (Lines 2432-2491)

**Email Details**:
- **Subject**: `Assignment Summary: [Exam Type] Order #[Order Number]`
- **Content Includes**:
  - List of assigned examiners (with user IDs)
  - List of assigned invigilators (with user IDs)
  - **Method dates table** (same as above)
  - System notification signature

**Code Location**:
```php
// Lines 2433-2490
$admin_subject = "Assignment Summary: {$exam_type} Order #{$field_789_value}";
// Builds admin_body with user lists and method dates
wp_mail($admin_emails, $admin_subject, $admin_body);
```

#### 1.3 Candidate Confirmation Email
**Recipients**: Candidate email address
**File**: `functions.php` (Lines 2495-2516)

**Email Details**:
- **Subject**: `[Exam Type] Assignment Details: Order #[Order Number]`
- **Content Includes**:
  - Candidate name
  - Exam type and order number
  - **Center Information**:
    - Center name
    - Center location/address
  - **Method dates table** (same as above)
  - Instructions to arrive on time
  - Admin contact email

**Code Location**:
```php
// Lines 2496-2514
$candidate_subject = "{$exam_type} Assignment Details: Order #{$field_789_value}";
// Includes center info and method dates
wp_mail($candidate_email, $candidate_subject, $candidate_body);
```

---

## 2. Generate Result Notification Email Flow

### Trigger Point
- **File**: `center_module.php` (Line 435)
- **Button**: "Generate Result Notification"
- **AJAX Action**: `generate_notification`
- **Handler Function**: Anonymous function in `functions.php` (Line 3261)
- **PDF Generator**: `generate_exam_certificate_pdf()` in `pdf-cert-generator.php`

### Email Recipients & Content

#### 2.1 Candidate Result Notification
**Recipients**: 
- Candidate's user email
- Personal email (field 864.2)
- Employer email (field 864.1)
- Additional email (field 863)

**File**: `pdf-cert-generator.php` (Lines 692-726)
**Function**: `send_certification_notification()`

**Email Details**:
- **Subject**: `SGNDT Examination Result Notification`
- **Content Includes**:
  - Candidate name
  - Method
  - **Result** (PASS/FAIL)
  - Date of issue
  - **Download link** to result notification PDF
  - NDTSS Certification Team signature

**Code Location**:
```php
// Lines 696-713
$subject = 'SGNDT Examination Result Notification';
$body = "...includes result and download link...";
wp_mail($to, $subject, $message);
```

**Email Log**: Saved to entry meta `_result_notification_email_log_[method]`

#### 2.2 Admin Result Notification
**Recipients**:
- Center Admin
- All administrators
- All manager_admins

**File**: `pdf-cert-generator.php` (Lines 728-784)

**Email Details**:
- **Subject**: `📢 Candidate Result Notification – [Candidate Name]`
- **Content Includes**:
  - Candidate name
  - Method
  - **Result** (PASS/FAIL)
  - Exam center name
  - Date of issue
  - **Download link** to result notification PDF

**Code Location**:
```php
// Lines 756-771
$subject = '📢 Candidate Result Notification – ' . esc_html($candidate_name);
// Includes result details and download link
wp_mail($admin_emails, $subject, $message);
```

### Additional Data Stored
- **Database**: Certification data saved to `wp_sgndt_certifications` table
- **Subject Marks**: Saved to `wp_sgndt_subject_marks` table
- **Entry Meta**: `_notification_meta_[method]` contains:
  - URL, path, generated_at, exam_entry_id, marks_entry_id, method, issued_by

---

## 3. Generate Final Certificate Email Flow

### Trigger Point
- **File**: `center_module.php` (Line 555)
- **Button**: "Generate Final Certificate"
- **AJAX Action**: `generate_notification` (with `generate_final_certificate` parameter)
- **Handler Function**: Anonymous function in `functions.php` (Line 3292)
- **PDF Generator**: `generate_final_certificate_pdf()` in `pdf-final-cert-generator.php`

### Email Recipients & Content

#### 3.1 Candidate Final Certificate
**Recipients**:
- Candidate's user email
- Additional email (field 863)

**File**: `pdf-final-cert-generator.php` (Lines 541-565)
**Function**: `send_exam_certificate()`

**Email Details**:
- **Subject**: `SGNDT Final Certificate Issued`
- **Content Includes**:
  - Candidate name
  - Method
  - Date of issue
  - **Download link** to final certificate PDF
  - NDTSS Certification Team signature

**Code Location**:
```php
// Lines 544-557
$subject = 'SGNDT Final Certificate Issued';
$body = "...includes download link...";
wp_mail($to, $subject, $message);
```

**Email Log**: Saved to entry meta `_final_certificate_email_log_[method]`

#### 3.2 Admin Final Certificate Notification
**Recipients**:
- Center Admin
- All administrators

**File**: `pdf-final-cert-generator.php` (Lines 567-606)

**Email Details**:
- **Subject**: `📢 Candidate Final Certificate Issued – [Candidate Name]`
- **Content Includes**:
  - Candidate name
  - Method
  - Exam center name
  - Date of issue
  - **Download link** to final certificate PDF

**Code Location**:
```php
// Lines 584-594
$subject = '📢 Candidate Final Certificate Issued – ' . esc_html($candidate_name);
// Includes certificate details and download link
wp_mail($admin_emails, $subject, get_email_template($subject, $body));
```

### Additional Data Stored
- **Database**: Final certification data saved to `wp_sgndt_final_certifications` table
- **Entry Meta**: `_certification_meta_[method]` contains:
  - URL, path, generated_at, exam_entry_id, marks_entry_id, method, issued_by, final_certification_id

---

## 4. Manager Approval Email Flow

### Trigger Point
- **File**: `center_module.php` (Line 498)
- **Button**: "Approve for Final Certificate"
- **AJAX Action**: `generate_notification` (with `approve_certificate_step` parameter)
- **Handler Function**: Anonymous function in `functions.php` (Line 3328)

### Email Recipients & Content

#### 4.1 Super Admin Approval Notification
**Recipients**: Super Admin (site admin email from `get_option('admin_email')`)

**File**: `functions.php` (Lines 3367-3393)

**Email Details**:
- **Subject**: `Final Certificate Approval Notification`
- **Content Includes**:
  - Candidate name
  - Candidate email
  - Order number
  - Examination center
  - Method
  - Approval status message
  - NDTSS Certification System signature

**Code Location**:
```php
// Lines 3368-3392
$subject = 'Final Certificate Approval Notification';
$body = "...includes candidate and approval details...";
wp_mail($super_admin_email, $subject, $message);
```

**Approval Status**: Saved to entry meta `_manager_approval_status_[method]` = 'approved'

---

## 5. Email Template Function

All emails use a common template wrapper:

**Function**: `get_email_template($subject, $body)`
**File**: `functions.php` (Line 2521)

**Template Structure**:
```html
<!DOCTYPE html>
<html>
<head><title>{$subject}</title></head>
<body style="margin: 0; padding: 0; background-color: #f7f7f7;">
  <div style="background-color: #ffffff; padding: 20px; margin: 20px auto; max-width: 600px; border: 1px solid #ddd;">
    {$body}
  </div>
</body>
</html>
```

---

## 6. Summary of Email Types

| Action | Email Type | Recipients | Key Information |
|--------|-----------|-----------|----------------|
| **Save Assignment** | Examiner/Invigilator | Assigned staff | Role, exam details, method dates table |
| **Save Assignment** | Admin Summary | Center/AQB/Super admins | Staff assignments, method dates table |
| **Save Assignment** | Candidate Confirmation | Candidate | Center info, method dates table |
| **Generate Result Notification** | Candidate Result | Candidate (multiple emails) | Result (PASS/FAIL), PDF download link |
| **Generate Result Notification** | Admin Result | Center/Super/Manager admins | Result, PDF download link |
| **Generate Final Certificate** | Candidate Certificate | Candidate | Final certificate PDF download link |
| **Generate Final Certificate** | Admin Certificate | Center/Super admins | Final certificate PDF download link |
| **Manager Approval** | Super Admin Approval | Super Admin | Approval confirmation, candidate details |

---

## 7. Key Observations

### Strengths
1. **Comprehensive Coverage**: All stakeholders receive appropriate notifications
2. **Detailed Information**: Method dates table provides clear scheduling information
3. **Multiple Recipient Support**: Candidates can receive emails at multiple addresses
4. **Email Logging**: Email status is logged to entry meta for tracking
5. **HTML Formatting**: All emails use HTML with proper styling

### Potential Improvements
1. **Email Delivery Confirmation**: No verification that emails were successfully delivered
2. **Retry Mechanism**: No automatic retry for failed email sends
3. **BCC for Privacy**: Multiple candidate emails are sent as TO instead of BCC
4. **Email Queue**: All emails sent synchronously, could impact performance
5. **Customization**: Email templates are hardcoded, no admin interface for customization

---

## 8. File References

### Main Files
- `center_module.php` - UI and AJAX triggers
- `functions.php` - AJAX handlers and email logic
- `pdf-cert-generator.php` - Result notification PDF and emails
- `pdf-final-cert-generator.php` - Final certificate PDF and emails

### Database Tables
- `wp_sgndt_certifications` - Result notification records
- `wp_sgndt_final_certifications` - Final certificate records
- `wp_sgndt_subject_marks` - Subject-wise marks

### Entry Meta Keys
- `_notification_meta_[method]` - Result notification metadata
- `_certification_meta_[method]` - Final certificate metadata
- `_result_notification_email_log_[method]` - Result email log
- `_final_certificate_email_log_[method]` - Final certificate email log
- `_manager_approval_status_[method]` - Manager approval status

---

**Document Generated**: <?php echo date('Y-m-d H:i:s'); ?>
**Analysis Scope**: Save Assignment, Generate Result Notification, Generate Final Certificate
