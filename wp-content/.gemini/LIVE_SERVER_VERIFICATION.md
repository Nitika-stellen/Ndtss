# Live Server Deployment Verification

## ✅ Files You MUST Upload to Live Server

### **Critical Files Checklist**

Check if ALL these files are uploaded to your live server:

---

### **1. Secure Download Script** ⚠️ MOST IMPORTANT
**Local Path**:
```
c:\xampp\htdocs\NDTSS\wp-content\themes\twentytwentyone-child\includes\secure-exam-certificate-download.php
```

**Live Server Path**:
```
/wp-content/themes/twentytwentyone-child/includes/secure-exam-certificate-download.php
```

**How to Check**:
- Visit this URL in browser (replace with your domain):
  ```
  https://yourdomain.com/wp-content/themes/twentytwentyone-child/includes/secure-exam-certificate-download.php
  ```
- Should show: "Access Denied: Please log in" (if not logged in)
- Should NOT show: 404 error

**If Missing**: This is likely the problem! Upload this file immediately.

---

### **2. Center Module** ⚠️ REQUIRED
**Local Path**:
```
c:\xampp\htdocs\NDTSS\wp-content\themes\twentytwentyone-child\panel\center_module.php
```

**Live Server Path**:
```
/wp-content/themes/twentytwentyone-child/panel/center_module.php
```

**How to Verify**:
1. Download the file from live server
2. Open it in text editor
3. Search for: "Use secure download URL for notification"
4. Should find it around line 305

**If Not Found**: Upload the updated file!

---

### **3. Functions.php** (For Email Fixes)
**Local Path**:
```
c:\xampp\htdocs\NDTSS\wp-content\themes\twentytwentyone-child\functions.php
```

**Live Server Path**:
```
/wp-content/themes/twentytwentyone-child/functions.php
```

**How to Verify**:
1. Download from live server
2. Search for: "Assignment notification: Using user email"
3. Should find it around line 2524

---

### **4. PDF Certificate Generator** (For Form 30/39 Emails)
**Local Path**:
```
c:\xampp\htdocs\NDTSS\wp-content\themes\twentytwentyone-child\includes\pdf-cert-generator.php
```

**Live Server Path**:
```
/wp-content/themes/twentytwentyone-child/includes/pdf-cert-generator.php
```

**How to Verify**:
1. Download from live server
2. Search for: "Retest Examination"
3. Should find it around line 715

---

### **5. PDF Final Certificate Generator** (For Form 30/39 Emails)
**Local Path**:
```
c:\xampp\htdocs\NDTSS\wp-content\themes\twentytwentyone-child\includes\pdf-final-cert-generator.php
```

**Live Server Path**:
```
/wp-content/themes/twentytwentyone-child/includes/pdf-final-cert-generator.php
```

**How to Verify**:
1. Download from live server
2. Search for: "Renewal/Recertification Examination"
3. Should find it around line 554

---

## 🔍 Quick Verification Commands

### **Via FTP/File Manager**:

Check these files exist on live server:

```
✓ /wp-content/themes/twentytwentyone-child/includes/secure-exam-certificate-download.php
✓ /wp-content/themes/twentytwentyone-child/panel/center_module.php
✓ /wp-content/themes/twentytwentyone-child/functions.php
✓ /wp-content/themes/twentytwentyone-child/includes/pdf-cert-generator.php
✓ /wp-content/themes/twentytwentyone-child/includes/pdf-final-cert-generator.php
```

### **Via SSH** (if you have access):

```bash
cd /path/to/wp-content/themes/twentytwentyone-child/

# Check if secure download script exists
ls -la includes/secure-exam-certificate-download.php

# Check file dates (should be recent)
ls -la --time-style=long-iso includes/secure-exam-certificate-download.php
ls -la --time-style=long-iso panel/center_module.php
ls -la --time-style=long-iso functions.php
ls -la --time-style=long-iso includes/pdf-cert-generator.php
ls -la --time-style=long-iso includes/pdf-final-cert-generator.php
```

---

## 🎯 Most Likely Missing File

Based on "working locally but not on live", the most likely issue is:

### **❌ Missing: `secure-exam-certificate-download.php`**

This is a **NEW file** that didn't exist before. You need to:

1. **Create the file** on live server
2. **Upload the content** from your local version
3. **Set permissions** to 644

**Location on Live**:
```
/wp-content/themes/twentytwentyone-child/includes/secure-exam-certificate-download.php
```

---

## 📋 Step-by-Step Upload Guide

### **Using FTP (FileZilla, etc.)**:

1. **Connect to live server**
2. **Navigate to**: `/wp-content/themes/twentytwentyone-child/`
3. **Upload these files**:
   - `includes/secure-exam-certificate-download.php` (NEW - most important!)
   - `panel/center_module.php` (REPLACE)
   - `functions.php` (REPLACE)
   - `includes/pdf-cert-generator.php` (REPLACE)
   - `includes/pdf-final-cert-generator.php` (REPLACE)

### **Using cPanel File Manager**:

1. **Login to cPanel**
2. **Go to File Manager**
3. **Navigate to**: `public_html/wp-content/themes/twentytwentyone-child/`
4. **Upload files**:
   - Click "Upload"
   - Select all 5 files
   - Upload and overwrite

### **Using WP File Manager Plugin**:

1. **Install** "File Manager" plugin (if not already)
2. **Navigate to**: `wp-content/themes/twentytwentyone-child/`
3. **Upload files** one by one

---

## 🧪 Test After Upload

### **Test 1: Check Secure Download Script Exists**

Visit this URL (replace with your domain):
```
https://yourdomain.com/wp-content/themes/twentytwentyone-child/includes/secure-exam-certificate-download.php
```

**Expected Results**:
- ✅ If logged out: "Access Denied: Please log in"
- ✅ If logged in: "Error: Invalid certificate request"
- ❌ If 404: File not uploaded!

### **Test 2: Check Center Module Updated**

1. Go to any exam entry with certificates
2. Right-click "View Result Notification"
3. Copy link address
4. Should contain: `secure-exam-certificate-download.php`
5. Should NOT contain: `/wp-content/uploads/certificates/`

### **Test 3: Click View Result Notification**

1. Click "View Result Notification"
2. **Expected**: PDF opens in browser
3. **If 404**: `secure-exam-certificate-download.php` not uploaded
4. **If 403**: File uploaded but permissions wrong

---

## 🔧 File Permissions

After uploading, set correct permissions:

### **Via FTP**:
- Right-click each file → File Permissions → Set to **644**

### **Via SSH**:
```bash
cd /path/to/wp-content/themes/twentytwentyone-child/
chmod 644 includes/secure-exam-certificate-download.php
chmod 644 panel/center_module.php
chmod 644 functions.php
chmod 644 includes/pdf-cert-generator.php
chmod 644 includes/pdf-final-cert-generator.php
```

---

## 🚨 Common Upload Mistakes

### **1. Uploaded to Wrong Directory**
❌ Wrong: `/wp-content/themes/twentytwentyone/includes/` (parent theme)
✅ Correct: `/wp-content/themes/twentytwentyone-child/includes/` (child theme)

### **2. File Name Typo**
❌ Wrong: `secure-exam-certificate-download.php.txt`
✅ Correct: `secure-exam-certificate-download.php`

### **3. Uploaded Old Version**
- Make sure you're uploading the LATEST version from local
- Check file modification date

### **4. Binary Mode Upload**
- Ensure FTP is set to "Auto" or "Binary" mode
- Not "ASCII" mode (can corrupt files)

---

## 📊 File Comparison

### **Compare File Sizes** (approximate):

| File | Approx Size |
|------|-------------|
| `secure-exam-certificate-download.php` | ~4 KB |
| `center_module.php` | ~85 KB |
| `functions.php` | ~200 KB |
| `pdf-cert-generator.php` | ~39 KB |
| `pdf-final-cert-generator.php` | ~36 KB |

If file sizes are very different, you may have uploaded wrong version.

---

## 🔍 Debug: Check What's on Live Server

### **Download and Compare**:

1. **Download** `center_module.php` from live server
2. **Open** in text editor
3. **Search** for: "Use secure download URL for notification"
4. **If NOT found**: File not uploaded or wrong version uploaded

### **Check Line Numbers**:

Open downloaded `center_module.php` and check:
- Line 305: Should have comment "// Use secure download URL for notification"
- Line 308: Should have `get_stylesheet_directory_uri() . '/includes/secure-exam-certificate-download.php'`

---

## ✅ Complete Upload Checklist

Before testing, verify ALL of these:

- [ ] `secure-exam-certificate-download.php` exists in `includes/` folder
- [ ] `center_module.php` updated in `panel/` folder
- [ ] `functions.php` updated in root theme folder
- [ ] `pdf-cert-generator.php` updated in `includes/` folder
- [ ] `pdf-final-cert-generator.php` updated in `includes/` folder
- [ ] All files have 644 permissions
- [ ] Files uploaded to child theme, not parent theme
- [ ] File names are exact (no .txt extension)
- [ ] Cleared server cache (if any)
- [ ] Hard refreshed browser

---

## 🎯 Quick Fix

**Most likely you're missing**:
```
/wp-content/themes/twentytwentyone-child/includes/secure-exam-certificate-download.php
```

**To fix**:
1. Upload this file from your local to live server
2. Set permissions to 644
3. Test by visiting the URL directly
4. Then test "View Result Notification"

---

## 📞 Still Not Working?

If you've uploaded all files and it's still not working:

1. **Check error logs**: `wp-content/debug.log`
2. **Check server error logs**: Usually in cPanel or `/var/log/apache2/error.log`
3. **Verify PHP version**: Should be 7.4 or higher
4. **Check file ownership**: Should match other theme files

**Provide this info**:
- Which file is missing (check with FTP)
- Error message when clicking "View Result Notification"
- Server type (Apache, Nginx, etc.)
- Any errors in debug.log
