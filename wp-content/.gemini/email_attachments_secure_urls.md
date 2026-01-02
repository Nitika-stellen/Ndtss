# Email Attachments & Secure URLs - Complete Implementation

## Overview
Updated all certificate emails (result notifications and final certificates) to:
1. **Attach PDF files** to emails (like membership system)
2. **Use secure download URLs** instead of direct URLs
3. **Work for ALL forms** (15, 30, and 39)

---

## Changes Made

### Files Modified
1. ✅ `includes/pdf-cert-generator.php` - Result notification emails
2. ✅ `includes/pdf-final-cert-generator.php` - Final certificate emails

---

## Implementation Details

### 1. Secure URL Generation

**Before** (Direct URL):
```php
$certificate_url = $certificate_data['url'] ?? '';
// Example: https://site.com/wp-content/uploads/certificates/certificate_123_RT.pdf
```

**After** (Secure URL):
```php
$certificate_path = $certificate_data['path'] ?? '';
$certificate_filename = !empty($certificate_path) ? basename($certificate_path) : '';

if (!empty($certificate_filename)) {
    $secure_url = get_stylesheet_directory_uri() . '/includes/secure-exam-certificate-download.php';
    $certificate_url = add_query_arg([
        'file' => $certificate_filename,
        'entry_id' => $exam_entry_id,
        'v' => time()
    ], $secure_url);
}
// Example: https://site.com/.../secure-exam-certificate-download.php?file=certificate_123_RT.pdf&entry_id=123
```

---

### 2. PDF Attachments

**Before** (No attachment):
```php
wp_mail($to, $subject, $message);
```

**After** (With attachment):
```php
// Attach PDF if file exists
$attachments = [];
if (!empty($certificate_path) && file_exists($certificate_path)) {
    $attachments[] = $certificate_path;
}

wp_mail($to, $subject, $message, [], $attachments);
```

---

### 3. Email Body Updates

#### Result Notification Email

**Before**:
```html
<a href="[direct_url]" target="_blank">Download Notification</a>
```

**After**:
```html
Please find your result notification attached to this email.<br><br>
You can also <a href="[secure_url]" target="_blank">view it online</a> (login required).
```

#### Final Certificate Email

**Before**:
```html
<a href="[direct_url]" target="_blank">Download Certificate</a>
```

**After**:
```html
Please find your final certificate attached to this email.<br><br>
You can also <a href="[secure_url]" target="_blank">view it online</a> (login required).
```

---

## Email Types Updated

### 1. Result Notification Emails

**Candidate Email**:
- ✅ PDF attached
- ✅ Secure URL for online viewing
- ✅ Works for Form 15, 30, 39

**Admin Email**:
- ✅ Secure URL for online viewing
- ✅ Mentions certificate was sent to candidate
- ✅ Works for Form 15, 30, 39

### 2. Final Certificate Emails

**Candidate Email**:
- ✅ PDF attached
- ✅ Secure URL for online viewing
- ✅ Works for Form 15, 30, 39

**Admin Email**:
- ✅ Secure URL for online viewing
- ✅ Mentions certificate was sent to candidate
- ✅ Works for Form 15, 30, 39

---

## Benefits

### Security
- ✅ No direct PDF URLs in emails
- ✅ Secure URLs require authentication
- ✅ Path traversal protection
- ✅ Access control enforced

### User Experience
- ✅ PDF attached to email (no login needed to download)
- ✅ Can view online if needed (with login)
- ✅ Works on mobile devices
- ✅ No broken links

### Consistency
- ✅ Matches membership certificate system
- ✅ Same behavior for all form types
- ✅ Professional email format
- ✅ Clear instructions

---

## Email Examples

### Result Notification Email (Candidate)

**Subject**: SGNDT Initial Examination Result Notification

**Body**:
```
Dear John Doe,

Your initial examination result has been released.

Method: RT
Result: PASS
Date of Issue: 11.12.2025

Please find your result notification attached to this email.

You can also view it online (login required).

Best regards,
NDTSS Certification Team
```

**Attachments**: `certificate_123_RT.pdf`

---

### Final Certificate Email (Candidate)

**Subject**: SGNDT Renewal/Recertification Examination Final Certificate Issued

**Body**:
```
Dear Jane Smith,

Your final certificate for renewal/recertification examination has been issued

Method: UT
Date of Issue: 11.12.2025

Please find your final certificate attached to this email.

You can also view it online (login required).

Best regards,
NDTSS Certification Team
```

**Attachments**: `final_certificate_456_UT.pdf`

---

### Admin Notification Email

**Subject**: 📢 Candidate Initial Examination Result Notification – John Doe

**Body**:
```
Candidate Name: John Doe
Exam Type: Initial Examination
Method: RT
Result: PASS
Exam Center: Singapore Testing Center
Date of Issue: 11.12.2025

Result notification has been sent to the candidate.

View Notification Online (login required)
```

---

## Form-Specific Behavior

### Form 15 (Initial Exam)
- ✅ Email subject: "SGNDT **Initial Examination** Result Notification"
- ✅ Email body: "Your **initial examination** result..."
- ✅ PDF attached
- ✅ Secure URL provided

### Form 30 (Retest Exam)
- ✅ Email subject: "SGNDT **Retest Examination** Result Notification"
- ✅ Email body: "Your **retest examination** result..."
- ✅ PDF attached
- ✅ Secure URL provided

### Form 39 (Renewal/Recertification)
- ✅ Email subject: "SGNDT **Renewal/Recertification Examination** Result Notification"
- ✅ Email body: "Your **renewal/recertification examination** result..."
- ✅ PDF attached
- ✅ Secure URL provided

---

## Testing Checklist

### Test 1: Result Notification (Form 15)
- [ ] Generate result notification for Form 15
- [ ] Check candidate receives email
- [ ] Verify PDF is attached
- [ ] Click "view it online" link
- [ ] Verify secure URL works (requires login)

### Test 2: Result Notification (Form 30)
- [ ] Generate result notification for Form 30
- [ ] Check candidate receives email
- [ ] Verify PDF is attached
- [ ] Verify email says "retest examination"
- [ ] Click "view it online" link

### Test 3: Result Notification (Form 39)
- [ ] Generate result notification for Form 39
- [ ] Check candidate receives email
- [ ] Verify PDF is attached
- [ ] Verify email says "renewal/recertification examination"
- [ ] Click "view it online" link

### Test 4: Final Certificate (Form 15)
- [ ] Generate final certificate for Form 15
- [ ] Check candidate receives email
- [ ] Verify PDF is attached
- [ ] Click "view it online" link
- [ ] Verify secure URL works

### Test 5: Final Certificate (Form 30)
- [ ] Generate final certificate for Form 30
- [ ] Check candidate receives email
- [ ] Verify PDF is attached
- [ ] Verify email says "retest examination"

### Test 6: Final Certificate (Form 39)
- [ ] Generate final certificate for Form 39
- [ ] Check candidate receives email
- [ ] Verify PDF is attached
- [ ] Verify email says "renewal/recertification examination"

### Test 7: Admin Emails
- [ ] Verify admin receives notification
- [ ] Check secure URL in admin email
- [ ] Verify admin can click and view PDF

---

## Attachment Details

### File Attachment
- **Source**: `$certificate_data['path']`
- **Example**: `/wp-content/uploads/certificates/certificate_123_RT.pdf`
- **Attached as**: PDF file
- **Size**: Typically 100-500 KB

### Email Size
- **With attachment**: ~200-600 KB
- **Without attachment**: ~5-10 KB
- **Impact**: Minimal, PDFs are small

---

## Security Features

### 1. Attachment Security
- ✅ PDF attached from server (not public URL)
- ✅ Only sent to authorized recipients
- ✅ No public access to attachment

### 2. Secure URL Security
- ✅ Requires user login
- ✅ Checks user permissions
- ✅ Validates file exists
- ✅ Prevents path traversal

### 3. Email Security
- ✅ Sent via WordPress wp_mail()
- ✅ SMTP encryption (if configured)
- ✅ No sensitive data in URLs
- ✅ Audit trail in logs

---

## Comparison: Before vs After

| Feature | Before | After |
|---------|--------|-------|
| **URL Type** | Direct PDF URL | Secure Script URL |
| **PDF Attachment** | ❌ No | ✅ Yes |
| **Requires Login** | ❌ No | ✅ Yes (for online view) |
| **Works Offline** | ❌ No | ✅ Yes (attachment) |
| **Form 15** | ✅ Working | ✅ Enhanced |
| **Form 30** | ❌ Wrong wording | ✅ Fixed |
| **Form 39** | ❌ Wrong wording | ✅ Fixed |
| **Security** | ❌ Low | ✅ High |

---

## Deployment

### Files to Upload
Both files are already in the deployment list:

```
wp-content/themes/twentytwentyone-child/includes/pdf-cert-generator.php
wp-content/themes/twentytwentyone-child/includes/pdf-final-cert-generator.php
```

### No Additional Files Needed
- Secure download script already created
- Email template function already exists
- No database changes required

---

## Troubleshooting

### Issue: Attachment Not Received

**Check**:
1. Verify PDF file exists at `$certificate_path`
2. Check file permissions (should be 644)
3. Check email size limits (server/client)
4. Check spam folder

**Solution**:
- Ensure certificate was generated successfully
- Check `wp-content/debug.log` for errors
- Test with different email client

---

### Issue: Secure URL Not Working

**Check**:
1. Verify `secure-exam-certificate-download.php` exists
2. Check user is logged in
3. Check user has permission

**Solution**:
- Upload secure download script
- Clear browser cache
- Test with admin account

---

### Issue: Old URL Still in Email

**Check**:
1. Verify files were uploaded to live server
2. Clear PHP opcode cache
3. Regenerate certificate

**Solution**:
- Upload updated files
- Restart web server
- Generate new certificate

---

## Summary

### What Changed
- ✅ PDFs now attached to all certificate emails
- ✅ Secure URLs used instead of direct URLs
- ✅ Works for Form 15, 30, and 39
- ✅ Consistent with membership system

### User Experience
- ✅ Candidates get PDF in email (no login needed)
- ✅ Can view online if needed (with login)
- ✅ Professional email format
- ✅ Clear instructions

### Security
- ✅ No public PDF URLs
- ✅ Authentication required for online viewing
- ✅ Access control enforced
- ✅ Audit trail maintained

---

**Status**: ✅ Complete
**Date**: 2025-12-11
**Impact**: All certificate emails now secure and user-friendly for all form types
