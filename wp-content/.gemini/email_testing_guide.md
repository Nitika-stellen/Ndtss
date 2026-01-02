# Email System Testing & Debugging Guide

## Quick Diagnostic Checklist

Before testing, verify these prerequisites:

### 1. WordPress Email Configuration
- [ ] WordPress can send emails (test with a password reset)
- [ ] SMTP is configured (if using SMTP plugin)
- [ ] `wp_mail()` function is working
- [ ] Check spam/junk folders

### 2. Debug Logging Enabled
Add to `wp-config.php` if not already present:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Log file location: `wp-content/debug.log`

### 3. Form Data Verification
- [ ] Form entries have valid data in required fields
- [ ] User accounts have valid email addresses
- [ ] Center posts exist and have location meta

---

## Testing Procedure

### Test 1: Form 30 (Retest) Assignment Email

#### Step 1: Verify Form Data
1. Go to: **Forms → Entries → Form 30**
2. Open an entry
3. Check these fields have values:
   - **Field 12**: Order number (should have a value)
   - **Field 19**: Candidate name (should have a value)
   - **Field 26**: Candidate email (check if valid email)
   - **Field 9**: Center name (should match an exam center)

#### Step 2: Verify User Email
1. Note the "Created By" user ID
2. Go to: **Users → All Users**
3. Find the user and verify they have a valid email address

#### Step 3: Assign and Save
1. Go back to the entry
2. Assign examiners and invigilators
3. Set method slots (date and time)
4. Click **"Save Assignments"**

#### Step 4: Check Results
1. **Success Message**: Should see "Assignments saved and emails sent successfully"
2. **Check Logs**: Open `wp-content/debug.log` and look for:
   ```
   Assignment notification sent to candidate: [email] for entry [id]
   ```
   OR
   ```
   Assignment notification: Using user email for entry [id], form 30: [email]
   ```

3. **Check Email**: 
   - Check the email inbox (field 26 or user email)
   - Check spam/junk folder
   - Subject should be: "**Retest Exam** Assignment Details: Order #..."
   - Body should show candidate name from field 19

#### Expected Results
✅ Email sent to field 26 (or user email if field 26 is empty)
✅ Subject contains "Retest Exam"
✅ Body shows correct candidate name, order number, center name
✅ Method dates table is populated

---

### Test 2: Form 39 (Renewal/Recertification) Assignment Email

#### Step 1: Verify Form Data
1. Go to: **Forms → Entries → Form 39**
2. Open an entry
3. Check these fields have values:
   - **Field 13**: Order number (should have a value)
   - **Field 1**: Candidate name (should have a value)
   - **Field 12**: Candidate email (check if valid email)
   - **Field 9**: Center name (should match an exam center)

#### Step 2: Verify User Email
1. Note the "Created By" user ID
2. Go to: **Users → All Users**
3. Find the user and verify they have a valid email address

#### Step 3: Assign and Save
1. Go back to the entry
2. Assign examiners and invigilators
3. Set method slots (date and time)
4. Click **"Save Assignments"**

#### Step 4: Check Results
1. **Success Message**: Should see "Assignments saved and emails sent successfully"
2. **Check Logs**: Open `wp-content/debug.log` and look for:
   ```
   Assignment notification sent to candidate: [email] for entry [id]
   ```

3. **Check Email**: 
   - Check the email inbox (field 12 or user email)
   - Check spam/junk folder
   - Subject should be: "**Renewal/Recertification Exam** Assignment Details: Order #..."
   - Body should show candidate name from field 1

#### Expected Results
✅ Email sent to field 12 (or user email if field 12 is empty)
✅ Subject contains "Renewal/Recertification Exam"
✅ Body shows correct candidate name, order number, center name
✅ Method dates table is populated

---

### Test 3: Form 15 (Initial) - Regression Test

#### Step 1: Verify Form Data
1. Go to: **Forms → Entries → Form 15**
2. Open an entry
3. Check these fields have values:
   - **Field 789**: Order number
   - **Field 840**: Candidate name
   - **Field 12**: Candidate email
   - **Field 833**: Center name

#### Step 2: Assign and Save
1. Assign examiners and invigilators
2. Set method slots
3. Click **"Save Assignments"**

#### Step 3: Check Results
✅ Email sent successfully
✅ Subject contains "Initial Exam"
✅ All existing functionality works
✅ No regression issues

---

## Common Issues & Solutions

### Issue 1: "Invalid candidate email" in logs

**Log Message**:
```
Invalid candidate email for entry 123, form 30: NULL
```

**Cause**: Both form field and user email are empty/invalid

**Solution**:
1. Check if field 26 (Form 30) or field 12 (Form 39) has a valid email
2. Check if the user account has a valid email
3. Update one of these with a valid email address

---

### Issue 2: "No valid email found" in logs

**Log Message**:
```
Assignment notification: No valid email found for entry 123, form 30
```

**Cause**: User account has no email or invalid email

**Solution**:
1. Go to Users → Edit User
2. Add/update the email address
3. Try saving assignments again

---

### Issue 3: Email sent but not received

**Possible Causes**:
1. Email in spam/junk folder
2. Email server blocking WordPress emails
3. Invalid email address
4. SMTP configuration issue

**Solutions**:
1. Check spam/junk folders
2. Test WordPress email with password reset
3. Install and configure WP Mail SMTP plugin
4. Check email server logs

---

### Issue 4: "Failed to send assignment notification"

**Log Message**:
```
Failed to send assignment notification to candidate: user@example.com for entry 123
```

**Cause**: `wp_mail()` function returned false

**Solutions**:
1. Check if WordPress can send emails at all
2. Install WP Mail SMTP plugin
3. Check email server configuration
4. Verify email address is valid
5. Check for email sending limits

---

### Issue 5: Wrong candidate name in email

**Symptoms**: Email shows wrong name or "N/A"

**Cause**: Field mapping issue or empty field

**Debug Steps**:
1. Check which form type it is (15, 30, or 39)
2. Verify the correct field has the name:
   - Form 15: Field 840
   - Form 30: Field 19
   - Form 39: Field 1
3. Check if field has a value in the entry

**Solution**: Update the entry with the correct name in the appropriate field

---

### Issue 6: Wrong center name or "N/A"

**Symptoms**: Email shows wrong center or "N/A"

**Cause**: Center name doesn't match any exam center post

**Debug Steps**:
1. Check the center name in the entry:
   - Form 15: Field 833
   - Form 30: Field 9
   - Form 39: Field 9
2. Go to Exam Centers → All Centers
3. Verify a center exists with the exact same name

**Solution**: 
- Update the entry with the exact center name, OR
- Create a new exam center with the name from the entry

---

## Advanced Debugging

### Enable Detailed Email Logging

Add this to `functions.php` temporarily:

```php
add_action('phpmailer_init', function($phpmailer) {
    error_log('Email being sent to: ' . implode(', ', (array)$phpmailer->getToAddresses()));
    error_log('Email subject: ' . $phpmailer->Subject);
});

add_filter('wp_mail_failed', function($error) {
    error_log('Email failed: ' . $error->get_error_message());
});
```

### Test WordPress Email Function

Create a test page with this code:

```php
$to = 'your-email@example.com';
$subject = 'Test Email';
$message = 'This is a test email from WordPress';
$sent = wp_mail($to, $subject, $message);

if ($sent) {
    echo 'Email sent successfully!';
} else {
    echo 'Email failed to send!';
}
```

---

## Log Analysis Guide

### Success Pattern
```
Assignment notification sent to candidate: user@example.com for entry 123
```
✅ Email was sent successfully

### Fallback Pattern
```
Assignment notification: Using user email for entry 123, form 30: user@example.com
Assignment notification sent to candidate: user@example.com for entry 123
```
✅ Form field was empty, used user email instead

### Failure Pattern
```
Invalid candidate email for entry 123, form 30: NULL
```
❌ No valid email found anywhere

### Send Failure Pattern
```
Assignment notification sent to candidate: user@example.com for entry 123
Failed to send assignment notification to candidate: user@example.com for entry 123
```
❌ Email address was valid but wp_mail() failed

---

## Email Content Verification

### What to Check in Received Email

1. **Subject Line**:
   - Form 15: "Initial Exam Assignment Details: Order #..."
   - Form 30: "Retest Exam Assignment Details: Order #..."
   - Form 39: "Renewal/Recertification Exam Assignment Details: Order #..."

2. **Candidate Name**:
   - Should match the name from the correct field
   - Should not be "N/A" or empty

3. **Order Number**:
   - Should match the order number from the correct field
   - Should not be empty

4. **Center Information**:
   - Center name should match an exam center
   - Location should be populated (not empty)

5. **Method Dates Table**:
   - Should show all assigned methods
   - Should show slot 1 date and time
   - Should show slot 2 if configured
   - Dates should be formatted: "December 11, 2025"
   - Times should be formatted: "2:30 PM"

---

## Quick Reference: Field Mappings

| Data | Form 15 | Form 30 | Form 39 |
|------|---------|---------|---------|
| Order Number | 789 | 12 | 13 |
| Candidate Name | 840 | 19 | 1 |
| Candidate Email | 12 | 26 | 12 |
| Center Name | 833 | 9 | 9 |

---

## Next Steps After Testing

### If All Tests Pass ✅
- Document which email addresses received emails
- Archive test emails for reference
- Mark system as production-ready

### If Tests Fail ❌
1. Note which specific test failed
2. Check the debug logs for error messages
3. Follow the troubleshooting guide above
4. Share the log messages for further assistance

---

## Support Information

### Files to Check
- `wp-content/debug.log` - Error logs
- `functions.php` line 2273 - Assignment handler
- `functions.php` line 2502 - Candidate email

### Information to Provide if Issues Persist
1. Form type (15, 30, or 39)
2. Entry ID
3. Log messages from debug.log
4. Whether WordPress can send other emails (password reset)
5. Email addresses being used (form field and user account)

---

**Ready to Test!** Follow the procedures above and let me know the results.
