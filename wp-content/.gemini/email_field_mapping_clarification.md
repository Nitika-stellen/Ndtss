# Clarification: Candidate Confirmation Email Field Mapping

## Question
Should the fields in the candidate confirmation email be changed according to form type?

## Answer
**No changes needed!** The email is already using form-specific field values correctly.

---

## How It Works

### 1. Variables Are Set Based on Form Type (Lines 2283-2301)

The function `handle_exam_assignments_ajax()` sets all variables based on the form type **at the very beginning**:

```php
if ($entry['form_id'] == 30) {
    // FORM 30 (RETEST)
    $exam_type       = 'Retest Exam';
    $field_789_value = rgar($entry, '12');      // Order number from field 12
    $candidate_email = rgar($entry, '26');      // Email from field 26
    $candidate_name  = rgar($entry, '19');      // Name from field 19
    $center_name     = trim(rgar($entry, '9')); // Center from field 9
    
} elseif ($entry['form_id'] == 39) {
    // FORM 39 (RENEWAL/RECERTIFICATION)
    $exam_type       = 'Renewal/Recertification Exam';
    $field_789_value = rgar($entry, '13');      // Order number from field 13
    $candidate_email = rgar($entry, '12');      // Email from field 12
    $candidate_name  = rgar($entry, '1');       // Name from field 1
    $center_name     = trim(rgar($entry, '9')); // Center from field 9
    
} else {
    // FORM 15 (INITIAL)
    $exam_type       = 'Initial Exam';
    $field_789_value = rgar($entry, '789');     // Order number from field 789
    $candidate_email = rgar($entry, '12');      // Email from field 12
    $candidate_name  = rgar($entry, '840');     // Name from field 840
    $center_name     = trim(rgar($entry, '833')); // Center from field 833
}
```

### 2. Center Address Is Fetched (Line 2499)

```php
$center_address = get_post_meta($center_post->ID, 'location', true);
```

This uses `$center_post` which is created from `$center_name` (already set correctly above).

### 3. Email Template Uses These Variables (Lines 2502-2515)

```php
$candidate_subject = "{$exam_type} Assignment Details: Order #{$field_789_value}";
$candidate_body = "
    <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; padding: 20px;'>
        <h2>{$exam_type} Assignment Confirmation</h2>
        <p>Dear ".esc_html($candidate_name).",</p>
        <p>Your <strong>{$exam_type}</strong> (Order #{$field_789_value}) has been scheduled...</p>
        <h3>Center Information</h3>
        <p><strong>Name:</strong> ".esc_html($center_name)."<br>
        <strong>Location:</strong> ".esc_html($center_address)."</p>
        ...
    </div>";
```

---

## Variable Mapping by Form

| Variable | Form 15 Value | Form 30 Value | Form 39 Value |
|----------|--------------|--------------|--------------|
| `$exam_type` | "Initial Exam" | "Retest Exam" | "Renewal/Recertification Exam" |
| `$field_789_value` | Field 789 | Field 12 | Field 13 |
| `$candidate_name` | Field 840 | Field 19 | Field 1 |
| `$candidate_email` | Field 12 | Field 26 | Field 12 |
| `$center_name` | Field 833 | Field 9 | Field 9 |
| `$center_address` | From center post meta | From center post meta | From center post meta |

---

## Example Email Output

### Form 15 (Initial Exam)
```
Subject: Initial Exam Assignment Details: Order #NDTSS-12345

Dear: John Doe (from field 840)
Order: NDTSS-12345 (from field 789)
Center: Singapore Testing Center (from field 833)
```

### Form 30 (Retest Exam)
```
Subject: Retest Exam Assignment Details: Order #RT-67890

Dear: Jane Smith (from field 19)
Order: RT-67890 (from field 12)
Center: Malaysia Testing Center (from field 9)
```

### Form 39 (Renewal/Recertification Exam)
```
Subject: Renewal/Recertification Exam Assignment Details: Order #RN-11111

Dear: Bob Johnson (from field 1)
Order: RN-11111 (from field 13)
Center: Thailand Testing Center (from field 9)
```

---

## Why This Works

The email template **doesn't need to know** which form it's from because:

1. ✅ All form-specific field extraction happens **before** the email is built
2. ✅ Variables are set with the correct values for each form type
3. ✅ The email template just uses these **already-correct** variables
4. ✅ This is the **correct design pattern** - separation of data extraction and presentation

---

## Verification

To verify this is working correctly, you can:

### 1. Check the Email Subject
- Form 15 should say: "**Initial Exam** Assignment Details: Order #..."
- Form 30 should say: "**Retest Exam** Assignment Details: Order #..."
- Form 39 should say: "**Renewal/Recertification Exam** Assignment Details: Order #..."

### 2. Check the Candidate Name
- Form 15: Should show name from field 840
- Form 30: Should show name from field 19
- Form 39: Should show name from field 1

### 3. Check the Order Number
- Form 15: Should show order from field 789
- Form 30: Should show order from field 12
- Form 39: Should show order from field 13

### 4. Check the Center Name
- Form 15: Should show center from field 833
- Form 30: Should show center from field 9
- Form 39: Should show center from field 9

---

## Conclusion

**The email template is already correctly using form-specific field values!**

No changes are needed to the email body. The variables are dynamically populated based on form type, and the email template uses these pre-populated variables.

This is the **correct and maintainable** approach because:
- ✅ Field mapping logic is centralized at the top of the function
- ✅ Email template is clean and doesn't need conditional logic
- ✅ Easy to maintain - if field IDs change, update only one place
- ✅ Consistent behavior across all email types

---

**Status**: ✅ Already Working Correctly - No Action Required
