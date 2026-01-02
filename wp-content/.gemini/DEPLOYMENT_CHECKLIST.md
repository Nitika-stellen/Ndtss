# Production Deployment Checklist - Security & Email Fixes

## 📋 Files to Upload to Live Server

### ✅ **REQUIRED FILES** (Must Upload)

#### 1. **Secure Download Script** (NEW FILE)
```
wp-content/themes/twentytwentyone-child/includes/secure-exam-certificate-download.php
```
**Purpose**: Handles secure certificate downloads with authentication
**Critical**: ⚠️ YES - Without this, certificate viewing will not work

---

#### 2. **Center Module** (MODIFIED)
```
wp-content/themes/twentytwentyone-child/panel/center_module.php
```
**Changes**: 
- Lines 301-336: Updated to use secure download URLs
- Certificate and notification URLs now use secure script

**Critical**: ⚠️ YES - Required for "View Result Notification" to work

---

#### 3. **Functions.php** (MODIFIED)
```
wp-content/themes/twentytwentyone-child/functions.php
```
**Changes**:
- Lines 2283-2301: Form 39 field mapping for assignments
- Lines 2517-2546: Candidate confirmation email with fallback logic

**Critical**: ⚠️ YES - Required for Form 30/39 assignment emails

---

#### 4. **PDF Certificate Generator** (MODIFIED)
```
wp-content/themes/twentytwentyone-child/includes/pdf-cert-generator.php
```
**Changes**:
- Lines 636-641: Form-specific center name extraction
- Lines 666-684: Form-specific email field mapping
- Lines 708-737: Dynamic exam type labels in emails
- Lines 762-784: Admin email with exam type

**Critical**: ⚠️ YES - Required for Form 30/39 result notifications

---

#### 5. **PDF Final Certificate Generator** (MODIFIED)
```
wp-content/themes/twentytwentyone-child/includes/pdf-final-cert-generator.php
```
**Changes**:
- Lines 489-496: Form-specific center name extraction
- Lines 519-533: Form-specific email field mapping
- Lines 545-567: Dynamic exam type labels in emails
- Lines 584-606: Admin email with exam type

**Critical**: ⚠️ YES - Required for Form 30/39 final certificates

---

## 📂 **File Upload Summary**

### **Total Files to Upload: 5**

| # | File | Status | Location |
|---|------|--------|----------|
| 1 | `secure-exam-certificate-download.php` | **NEW** | `includes/` |
| 2 | `center_module.php` | **MODIFIED** | `panel/` |
| 3 | `functions.php` | **MODIFIED** | Root theme folder |
| 4 | `pdf-cert-generator.php` | **MODIFIED** | `includes/` |
| 5 | `pdf-final-cert-generator.php` | **MODIFIED** | `includes/` |

---

## 🚀 **Deployment Steps**

### **Step 1: Backup Current Files**
Before uploading, backup these files from live server:
```
✓ functions.php
✓ panel/center_module.php
✓ includes/pdf-cert-generator.php
✓ includes/pdf-final-cert-generator.php
```

### **Step 2: Upload Files via FTP/SFTP**

**Connect to your server and navigate to**:
```
/wp-content/themes/twentytwentyone-child/
```

**Upload these files**:

1. **Upload NEW file**:
   ```
   includes/secure-exam-certificate-download.php
   ```

2. **Replace existing files**:
   ```
   functions.php
   panel/center_module.php
   includes/pdf-cert-generator.php
   includes/pdf-final-cert-generator.php
   ```

### **Step 3: Set File Permissions**

Ensure proper permissions (usually 644 for files):
```bash
chmod 644 functions.php
chmod 644 panel/center_module.php
chmod 644 includes/secure-exam-certificate-download.php
chmod 644 includes/pdf-cert-generator.php
chmod 644 includes/pdf-final-cert-generator.php
```

### **Step 4: Verify Upload**

Check that all files are present:
```
✓ /includes/secure-exam-certificate-download.php (NEW)
✓ /functions.php (MODIFIED)
✓ /panel/center_module.php (MODIFIED)
✓ /includes/pdf-cert-generator.php (MODIFIED)
✓ /includes/pdf-final-cert-generator.php (MODIFIED)
```

---

## 🧪 **Post-Deployment Testing**

### **Test 1: Secure Download Script**
1. Generate a certificate on live site
2. Click "View Result Notification"
3. **Expected**: PDF opens in browser ✅

### **Test 2: Form 30 Assignment Email**
1. Open a Form 30 entry
2. Assign examiners/invigilators
3. Click "Save Assignments"
4. **Expected**: Candidate receives email ✅

### **Test 3: Form 39 Assignment Email**
1. Open a Form 39 entry
2. Assign examiners/invigilators
3. Click "Save Assignments"
4. **Expected**: Candidate receives email ✅

### **Test 4: Form 30 Result Notification**
1. Generate result notification for Form 30
2. **Expected**: Email sent with "Retest Examination" label ✅
3. Click "View Result Notification"
4. **Expected**: PDF opens ✅

### **Test 5: Form 39 Final Certificate**
1. Generate final certificate for Form 39
2. **Expected**: Email sent with "Renewal/Recertification Examination" label ✅
3. Click "View Final Certificate"
4. **Expected**: PDF opens ✅

---

## ⚠️ **Important Notes**

### **1. File Paths**
The secure download script uses relative paths to load WordPress. If your server structure is different, you may need to adjust the paths in:
```php
// In secure-exam-certificate-download.php (lines 8-12)
$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../wp-load.php',
    dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php'
];
```

### **2. Server Configuration**
- Ensure PHP version is 7.4 or higher
- Ensure `allow_url_fopen` is enabled
- Ensure `wp_mail()` function works

### **3. Email Testing**
- Test with real email addresses
- Check spam folders
- Verify SMTP is configured (if using SMTP plugin)

### **4. Certificate Directory**
Ensure the certificates directory exists and is writable:
```
/wp-content/uploads/certificates/
```

---

## 🔄 **Rollback Plan**

If issues occur after deployment:

### **Quick Rollback**:
1. Restore backed-up files from Step 1
2. Remove `secure-exam-certificate-download.php`
3. Clear any caches

### **Partial Rollback** (if only one feature has issues):
- **Certificate viewing issues**: Restore `center_module.php` only
- **Email issues**: Restore `functions.php` and PDF generators only

---

## 📊 **What Each File Does**

### **1. secure-exam-certificate-download.php**
- ✅ Authenticates users
- ✅ Checks permissions
- ✅ Serves PDF files securely
- ✅ Logs access attempts

### **2. center_module.php**
- ✅ Generates secure URLs for certificates
- ✅ Displays "View Result Notification" button
- ✅ Displays "View Final Certificate" button

### **3. functions.php**
- ✅ Handles Form 30/39 field mapping
- ✅ Sends assignment confirmation emails
- ✅ Includes email fallback logic

### **4. pdf-cert-generator.php**
- ✅ Generates result notification PDFs
- ✅ Sends result notification emails
- ✅ Uses form-specific field mappings
- ✅ Includes exam type in email subject/body

### **5. pdf-final-cert-generator.php**
- ✅ Generates final certificate PDFs
- ✅ Sends final certificate emails
- ✅ Uses form-specific field mappings
- ✅ Includes exam type in email subject/body

---

## 🔐 **Security Considerations**

### **Before Going Live**:

1. **Test on Staging First** (if available)
   - Upload to staging environment
   - Run all tests
   - Verify everything works

2. **Enable Debug Logging** (temporarily)
   ```php
   // In wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

3. **Monitor Error Logs**
   - Check `wp-content/debug.log` after deployment
   - Look for any PHP errors or warnings

4. **Disable Debug After Testing**
   ```php
   define('WP_DEBUG', false);
   define('WP_DEBUG_LOG', false);
   ```

---

## 📝 **Deployment Checklist**

### **Pre-Deployment**:
- [ ] Backup all 4 existing files
- [ ] Test all changes on local/staging
- [ ] Verify file paths are correct
- [ ] Check PHP version compatibility

### **Deployment**:
- [ ] Upload `secure-exam-certificate-download.php` (NEW)
- [ ] Upload `functions.php` (REPLACE)
- [ ] Upload `center_module.php` (REPLACE)
- [ ] Upload `pdf-cert-generator.php` (REPLACE)
- [ ] Upload `pdf-final-cert-generator.php` (REPLACE)
- [ ] Set correct file permissions (644)

### **Post-Deployment**:
- [ ] Test certificate viewing (Form 15, 30, 39)
- [ ] Test assignment emails (Form 15, 30, 39)
- [ ] Test result notifications (Form 15, 30, 39)
- [ ] Test final certificates (Form 15, 30, 39)
- [ ] Check error logs for issues
- [ ] Verify emails are received
- [ ] Test with different user roles

---

## 🎯 **Quick Reference**

### **Upload These 5 Files**:
```
1. includes/secure-exam-certificate-download.php (NEW)
2. functions.php (MODIFIED)
3. panel/center_module.php (MODIFIED)
4. includes/pdf-cert-generator.php (MODIFIED)
5. includes/pdf-final-cert-generator.php (MODIFIED)
```

### **Test These Features**:
```
✓ View Result Notification
✓ View Final Certificate
✓ Assignment Emails (Form 30/39)
✓ Result Notification Emails (Form 30/39)
✓ Final Certificate Emails (Form 30/39)
```

---

## 📞 **Support**

If you encounter issues after deployment:

1. **Check error logs**: `wp-content/debug.log`
2. **Verify file uploads**: Ensure all 5 files are uploaded
3. **Test file permissions**: Should be 644
4. **Check PHP version**: Should be 7.4+
5. **Rollback if needed**: Restore backed-up files

---

**Ready to Deploy!** 🚀

Follow the steps above and you'll have all security and email fixes live on your production server.
