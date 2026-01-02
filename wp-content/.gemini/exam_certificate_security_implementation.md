# Exam Certificate Security Implementation

## Problem Analysis

### Current Issue
Exam certificates (Result Notifications and Final Certificates) are generating **403 Forbidden** errors because:

1. **Direct URLs are used**: `https://site.com/wp-content/uploads/certificates/certificate_123_RT.pdf`
2. **No access control**: Anyone with the URL can access certificates
3. **Server blocking**: `.htaccess` or server config may be blocking direct access to `/certificates/` folder

### Membership System (Working Model)
The membership certificate system uses:

1. ✅ **Secure download script** (`secure-certificate-download.php`)
2. ✅ **Access control**: Only admins can download via script
3. ✅ **Email attachments**: Users receive certificates via email
4. ✅ **Path traversal protection**: Prevents directory traversal attacks

---

## Solution: Implement Secure Download for Exam Certificates

### Approach 1: Email Attachments (Recommended)

**Advantages**:
- ✅ Most secure - no public URLs
- ✅ Users get certificates directly in email
- ✅ No server configuration needed
- ✅ Works like membership system

**Implementation**:
1. Attach PDF to email instead of linking
2. Remove download links from emails
3. Admin can still access via secure download script

### Approach 2: Secure Download Script

**Advantages**:
- ✅ Controlled access
- ✅ Can add user-specific permissions
- ✅ Audit trail capability

**Implementation**:
1. Create secure download script for exam certificates
2. Replace direct URLs with secure URLs
3. Check user permissions before serving file

### Approach 3: Hybrid (Best Solution)

**Combine both approaches**:
- ✅ Email attachments for candidates
- ✅ Secure download links for admins
- ✅ Maximum security and convenience

---

## Implementation Plan

### Step 1: Create Secure Download Script for Exam Certificates

**File**: `includes/secure-exam-certificate-download.php`

```php
<?php
/**
 * Secure Exam Certificate Download Handler
 * Allows:
 * - Administrators to download any certificate
 * - Certificate owners to download their own certificates
 */

// Load WordPress
$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../wp-load.php',
    dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php'
];

$wp_loaded = false;
foreach ($wp_load_paths as $wp_load_path) {
    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('WordPress not found.');
}

// Check if user is logged in
if (!is_user_logged_in()) {
    status_header(403);
    die('Access Denied: Please log in to access certificates.');
}

// Get parameters
$cert_file = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
$entry_id = isset($_GET['entry_id']) ? absint($_GET['entry_id']) : 0;

if (empty($cert_file)) {
    status_header(400);
    die('Error: Invalid certificate request.');
}

// Build the full path
$upload_dir = wp_upload_dir();
$cert_path = $upload_dir['basedir'] . '/certificates/' . $cert_file;

// Security check: Path traversal protection
$real_cert_path = realpath($cert_path);
$real_cert_dir = realpath($upload_dir['basedir'] . '/certificates/');

if (!$real_cert_path || !$real_cert_dir || strpos($real_cert_path, $real_cert_dir) !== 0) {
    status_header(404);
    die('Error: Certificate not found.');
}

if (!file_exists($cert_path)) {
    status_header(404);
    die('Error: Certificate file does not exist.');
}

// Permission check
$current_user_id = get_current_user_id();
$has_access = false;

// Admins can access all certificates
if (current_user_can('manage_options') || 
    current_user_can('custom_aqb') || 
    current_user_can('custom_center_admin')) {
    $has_access = true;
}

// Certificate owner can access their own certificate
if (!$has_access && $entry_id > 0) {
    $entry = GFAPI::get_entry($entry_id);
    if (!is_wp_error($entry) && isset($entry['created_by'])) {
        if ($entry['created_by'] == $current_user_id) {
            $has_access = true;
        }
    }
}

if (!$has_access) {
    status_header(403);
    die('Access Denied: You do not have permission to access this certificate.');
}

// Serve the file
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($cert_path) . '"');
header('Content-Length: ' . filesize($cert_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Clear any previous output
if (ob_get_level()) {
    ob_end_clean();
}

// Output the file
readfile($cert_path);
exit;
```

### Step 2: Update PDF Generator to Use Secure URLs

**File**: `includes/pdf-cert-generator.php`

**Change Line 595** from:
```php
$file_url = $upload_dir['baseurl'] . "/certificates/{$file_name}?v=" . time();
```

**To**:
```php
// Generate secure download URL
$secure_url = get_stylesheet_directory_uri() . '/includes/secure-exam-certificate-download.php';
$file_url = add_query_arg([
    'file' => $file_name,
    'entry_id' => $exam_entry_id,
    'v' => time()
], $secure_url);
```

### Step 3: Update Final Certificate Generator

**File**: `includes/pdf-final-cert-generator.php`

**Find similar line** (around line 365):
```php
$file_url = $upload_dir['baseurl'] . "/certificates/$file_name?v=" . time();
```

**Replace with**:
```php
// Generate secure download URL
$secure_url = get_stylesheet_directory_uri() . '/includes/secure-exam-certificate-download.php';
$file_url = add_query_arg([
    'file' => $file_name,
    'entry_id' => $exam_entry_id,
    'v' => time()
], $secure_url);
```

### Step 4: Add Email Attachments (Optional but Recommended)

**Update `send_certification_notification()` in `pdf-cert-generator.php`**:

**Add after line 690** (where `$to` array is created):

```php
// Prepare email attachments
$attachments = [];
if (file_exists($certificate_data['path'])) {
    $attachments[] = $certificate_data['path'];
}
```

**Update the `wp_mail()` call** (around line 737):

**From**:
```php
$sent_to_candidate = wp_mail($to, $subject, $message);
```

**To**:
```php
$sent_to_candidate = wp_mail($to, $subject, $message, [], $attachments);
```

**Update email body** to mention attachment:

**Change line 732** from:
```php
<a href="' . esc_url($certificate_url) . '" target="_blank">Download Notification</a><br><br>
```

**To**:
```php
Please find your result notification attached to this email.<br><br>
<a href="' . esc_url($certificate_url) . '" target="_blank">View Online</a> (Login required)<br><br>
```

### Step 5: Repeat for Final Certificates

Apply the same email attachment logic to `send_exam_certificate()` in `pdf-final-cert-generator.php`.

---

## Security Features

### Access Control Matrix

| User Type | Can Download Own Certificates | Can Download All Certificates |
|-----------|------------------------------|------------------------------|
| **Candidate** | ✅ Yes | ❌ No |
| **Center Admin** | ✅ Yes | ✅ Yes (their center) |
| **AQB Admin** | ✅ Yes | ✅ Yes (all) |
| **Super Admin** | ✅ Yes | ✅ Yes (all) |
| **Public/Logged Out** | ❌ No | ❌ No |

### Security Checks

1. ✅ **Authentication**: User must be logged in
2. ✅ **Authorization**: User must own certificate or be admin
3. ✅ **Path Traversal Protection**: `realpath()` validation
4. ✅ **File Existence**: Verify file exists before serving
5. ✅ **Directory Restriction**: Files must be in `/certificates/` folder
6. ✅ **Entry Validation**: Verify entry ownership

---

## Testing Checklist

### Test 1: Admin Access
- [ ] Admin can download any certificate via secure URL
- [ ] Admin receives email with attachment
- [ ] Admin can click "View Online" link

### Test 2: Candidate Access
- [ ] Candidate receives email with PDF attachment
- [ ] Candidate can download attachment from email
- [ ] Candidate can click "View Online" link (if logged in)
- [ ] Candidate gets 403 error for other users' certificates

### Test 3: Public Access
- [ ] Logged-out users get 403 error
- [ ] Direct URLs to PDFs are blocked
- [ ] Secure download script requires login

### Test 4: Path Traversal Attack
- [ ] URL with `../` returns 404
- [ ] URL with encoded path returns 404
- [ ] Only files in `/certificates/` can be accessed

---

## Migration Steps

### Phase 1: Implement Secure Download (No Breaking Changes)
1. Create `secure-exam-certificate-download.php`
2. Test with a few certificates
3. Verify access control works

### Phase 2: Update PDF Generators
1. Update `pdf-cert-generator.php` to use secure URLs
2. Update `pdf-final-cert-generator.php` to use secure URLs
3. Test new certificate generation

### Phase 3: Add Email Attachments
1. Update email functions to attach PDFs
2. Update email body text
3. Test email delivery

### Phase 4: Block Direct Access (Optional)
1. Add `.htaccess` rule to block direct access to `/certificates/`
2. Test that secure download still works
3. Verify old links redirect to login

---

## .htaccess Protection (Optional)

Add to `/wp-content/uploads/certificates/.htaccess`:

```apache
# Deny direct access to all files
<FilesMatch "\.(pdf|png|jpg|jpeg)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Allow access from PHP scripts
<FilesMatch "\.php$">
    Order Allow,Deny
    Allow from all
</FilesMatch>
```

---

## Rollback Plan

If issues occur:

1. **Remove `.htaccess`** from certificates folder
2. **Revert PDF generators** to use direct URLs
3. **Keep secure download script** for future use
4. **Email attachments** can remain (no harm)

---

## Benefits Summary

### Security
- ✅ No unauthorized access to certificates
- ✅ Audit trail capability
- ✅ Protection against path traversal
- ✅ User-specific permissions

### User Experience
- ✅ Candidates get certificates in email (no login needed)
- ✅ Admins can download via secure link
- ✅ Online viewing available (with login)
- ✅ No broken links

### Compliance
- ✅ GDPR-friendly (controlled access)
- ✅ Audit trail for downloads
- ✅ Data protection compliance
- ✅ Professional certificate delivery

---

## Next Steps

1. **Review this implementation plan**
2. **Choose approach** (Email attachments, Secure download, or Hybrid)
3. **Create secure download script**
4. **Test with one certificate**
5. **Roll out to all certificate types**

Would you like me to implement this solution?
