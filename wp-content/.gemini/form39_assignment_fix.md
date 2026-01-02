# Fix: Assignment Notification Email Support for Form 39

## Issue Description
Assignment notification emails were only working for Form 15 (Initial Exam) and Form 30 (Retest Exam), but not for Form 39 (Renewal/Recertification Exam).

## Root Cause
The `handle_exam_assignments_ajax()` function in `functions.php` (line 2273) only had conditional logic for Form 30, with all other forms defaulting to Form 15's field mappings. Form 39 was not explicitly handled, causing incorrect field values to be used for:
- Candidate name
- Candidate email
- Order number
- Center name

## Solution
Added explicit handling for Form 39 with correct field mappings based on the pattern used in the `exam_handle_approve_entry_ajax()` function.

### Field Mappings by Form

| Field | Form 15 (Initial) | Form 30 (Retest) | Form 39 (Renewal) |
|-------|------------------|------------------|-------------------|
| **Order Number** | Field 789 | Field 12 | Field 13 |
| **Candidate Email** | Field 12 | Field 26 | Field 12 |
| **Candidate Name** | Field 840 | Field 19 | Field 1 |
| **Center Name** | Field 833 | Field 9 | Field 9 |
| **Exam Type** | "Initial Exam" | "Retest Exam" | "Renewal/Recertification Exam" |

## Code Changes

**File**: `c:\xampp\htdocs\NDTSS\wp-content\themes\twentytwentyone-child\functions.php`

**Location**: Lines 2283-2295

**Before**:
```php
if ($entry['form_id'] == 30) {
    $exam_type       = 'Retest Exam';
    $field_789_value = rgar($entry, '12');
    $candidate_email = rgar($entry, '26');
    $candidate_name  = rgar($entry, '19');
    $center_name     = trim(rgar($entry, '9'));
} else {
    $exam_type       = 'Initial Exam';
    $field_789_value = rgar($entry, '789');
    $candidate_email = rgar($entry, '12');
    $candidate_name  = rgar($entry, '840'); 
    $center_name     = trim(rgar($entry, '833')); 
}
```

**After**:
```php
if ($entry['form_id'] == 30) {
    $exam_type       = 'Retest Exam';
    $field_789_value = rgar($entry, '12');
    $candidate_email = rgar($entry, '26');
    $candidate_name  = rgar($entry, '19');
    $center_name     = trim(rgar($entry, '9'));
} elseif ($entry['form_id'] == 39) {
    $exam_type       = 'Renewal/Recertification Exam';
    $field_789_value = rgar($entry, '13');
    $candidate_email = rgar($entry, '12');
    $candidate_name  = rgar($entry, '1');
    $center_name     = trim(rgar($entry, '9'));
} else {
    $exam_type       = 'Initial Exam';
    $field_789_value = rgar($entry, '789');
    $candidate_email = rgar($entry, '12');
    $candidate_name  = rgar($entry, '840'); 
    $center_name     = trim(rgar($entry, '833')); 
}
```

## Impact

### Emails Now Working for Form 39:

1. **Examiner/Invigilator Notifications**
   - Recipients: Each assigned examiner and invigilator
   - Subject: "Assignment Notification: [Examiner/Invigilator] for Renewal/Recertification Exam Order #[Order Number]"
   - Content: Includes method dates table, exam details, and login instructions

2. **Admin Summary Email**
   - Recipients: Center Admin, AQB Admin, Super Admins
   - Subject: "Assignment Summary: Renewal/Recertification Exam Order #[Order Number]"
   - Content: Lists all assigned staff and method dates

3. **Candidate Confirmation Email**
   - Recipients: Candidate email address
   - Subject: "Renewal/Recertification Exam Assignment Details: Order #[Order Number]"
   - Content: Center information, method dates table, arrival instructions

## Testing Recommendations

1. **Test Form 39 Assignment**:
   - Create or use an existing Form 39 entry
   - Assign examiners and invigilators
   - Set method slots
   - Click "Save Assignments"
   - Verify all three email types are sent with correct information

2. **Verify Field Values**:
   - Check that the order number (field 13) appears correctly
   - Verify candidate name (field 1) is displayed properly
   - Confirm candidate email (field 12) receives the email
   - Ensure center name (field 9) is correct

3. **Check Existing Forms Still Work**:
   - Test Form 15 (Initial) assignments
   - Test Form 30 (Retest) assignments
   - Ensure no regression in existing functionality

## Related Files

- `functions.php` (Line 2273): Main AJAX handler
- `center_module.php` (Lines 50-54): Form 39 field configuration
- `gravity_functions.php` (Lines 662-668): Form 39 field reference in approval function

## Notes

- This fix aligns the assignment notification logic with the approval notification logic
- Form 39 shares some field IDs with Form 30 (fields 9, 12) but differs in others (field 13 vs 12 for order, field 1 vs 19 for name)
- The exam type is now properly labeled as "Renewal/Recertification Exam" for Form 39

---

**Fix Applied**: 2025-12-11
**Fixed By**: AI Assistant
**Status**: ✅ Complete
