# Fix: User Profile Certificate Downloads

## Problem
Users logged into the frontend are getting **403 Forbidden** errors when trying to download their certificates from the user profile page.

## Root Cause
The user profile page is likely using:
1. **Direct PDF URLs** (blocked by server), OR
2. **Secure URLs without `entry_id`** parameter (can't verify ownership)

## Solution

The secure download script **already supports** certificate owners downloading their own certificates! We just need to ensure the user profile page uses the correct URL format.

---

## Required URL Format

### ✅ Correct Format (Works for Users):
```
https://yoursite.com/wp-content/themes/twentytwentyone-child/includes/secure-exam-certificate-download.php?file=certificate_123_RT.pdf&entry_id=123&v=1234567890
```

**Key Parameters**:
- `file` - Certificate filename
- `entry_id` - Entry ID (required for ownership verification)
- `v` - Cache buster (optional)

### ❌ Wrong Format (403 Error):
```
https://yoursite.com/wp-content/uploads/certificates/certificate_123_RT.pdf
```
(Direct URL - blocked)

```
https://yoursite.com/.../secure-exam-certificate-download.php?file=certificate_123_RT.pdf
```
(Missing `entry_id` - can't verify ownership)

---

## How to Fix User Profile Page

### Option 1: Update Certificate URLs in User Profile

**Find where certificates are displayed** in `user-profile.php` and update the URL generation:

**Before** (Direct URL):
```php
$cert_url = $upload_dir['baseurl'] . '/certificates/' . $cert_filename;
```

**After** (Secure URL):
```php
$secure_url = get_stylesheet_directory_uri() . '/includes/secure-exam-certificate-download.php';
$cert_url = add_query_arg([
    'file' => $cert_filename,
    'entry_id' => $entry_id,  // IMPORTANT: Must include entry_id
    'v' => time()
], $secure_url);
```

---

### Option 2: Use AJAX Download (Recommended)

Instead of direct links, use AJAX to download certificates:

**1. Add Download Button**:
```php
<button class="download-cert-btn" 
        data-file="<?= esc_attr($cert_filename) ?>" 
        data-entry-id="<?= esc_attr($entry_id) ?>">
    Download Certificate
</button>
```

**2. Add JavaScript**:
```javascript
jQuery('.download-cert-btn').on('click', function(e) {
    e.preventDefault();
    var file = jQuery(this).data('file');
    var entryId = jQuery(this).data('entry-id');
    
    var url = '<?= get_stylesheet_directory_uri() ?>/includes/secure-exam-certificate-download.php';
    url += '?file=' + encodeURIComponent(file);
    url += '&entry_id=' + entryId;
    url += '&v=' + Date.now();
    
    // Open in new tab
    window.open(url, '_blank');
});
```

---

### Option 3: Server-Side Download (Most Secure)

Create an AJAX endpoint that serves the file:

**1. Add to functions.php**:
```php
add_action('wp_ajax_download_my_certificate', 'handle_user_certificate_download');

function handle_user_certificate_download() {
    // Verify user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in to download certificates.');
    }
    
    $file = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
    $entry_id = isset($_GET['entry_id']) ? absint($_GET['entry_id']) : 0;
    
    if (empty($file) || !$entry_id) {
        wp_send_json_error('Invalid request.');
    }
    
    // Verify user owns this certificate
    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry)) {
        wp_send_json_error('Certificate not found.');
    }
    
    $current_user_id = get_current_user_id();
    if ($entry['created_by'] != $current_user_id) {
        wp_send_json_error('You do not have permission to download this certificate.');
    }
    
    // Serve the file
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . '/certificates/' . $file;
    
    if (!file_exists($file_path)) {
        wp_send_json_error('Certificate file not found.');
    }
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
}
```

**2. Call from JavaScript**:
```javascript
var url = ajaxurl + '?action=download_my_certificate&file=' + file + '&entry_id=' + entryId;
window.open(url, '_blank');
```

---

## Quick Fix: Update Existing Links

If you just want to fix existing certificate links in user profile:

### Find This Pattern:
```php
// Look for something like this:
<a href="<?= $certificate_url ?>">Download</a>
```

### Replace With:
```php
<?php
$secure_url = get_stylesheet_directory_uri() . '/includes/secure-exam-certificate-download.php';
$cert_download_url = add_query_arg([
    'file' => basename($certificate_path),
    'entry_id' => $entry_id,
    'v' => time()
], $secure_url);
?>
<a href="<?= esc_url($cert_download_url) ?>" target="_blank">Download Certificate</a>
```

---

## Verification Steps

### 1. Check Secure Download Script Permissions

The script should already allow users to download their own certificates. Verify this code exists in `secure-exam-certificate-download.php` (around line 60):

```php
// Certificate owner can access their own certificate
if (!$has_access && $entry_id > 0) {
    $entry = GFAPI::get_entry($entry_id);
    if (!is_wp_error($entry) && isset($entry['created_by'])) {
        if ($entry['created_by'] == $current_user_id) {
            $has_access = true;  // ← This allows owners to download
        }
    }
}
```

✅ This code is already in the secure download script!

### 2. Test User Download

1. **Log in as a regular user** (not admin)
2. **Go to user profile page**
3. **Click download certificate**
4. **Should work** if URL includes `entry_id`

---

## Common Issues & Solutions

### Issue 1: Still Getting 403

**Cause**: URL doesn't include `entry_id` parameter

**Solution**: Update URL generation to include `entry_id`:
```php
$cert_url = add_query_arg([
    'file' => $filename,
    'entry_id' => $entry_id,  // ← Must include this!
], $secure_url);
```

---

### Issue 2: "Certificate not found"

**Cause**: Entry ID doesn't match or is wrong

**Solution**: Verify `entry_id` is the correct Gravity Forms entry ID

---

### Issue 3: User sees admin's certificates

**Cause**: Not filtering by user ID

**Solution**: Only show certificates where `created_by` matches current user:
```php
$current_user_id = get_current_user_id();
// Filter certificates by user
foreach ($certificates as $cert) {
    $entry = GFAPI::get_entry($cert['entry_id']);
    if ($entry['created_by'] == $current_user_id) {
        // Show this certificate
    }
}
```

---

## Example: Complete Certificate Display

```php
<?php
// Get current user's certificates
$current_user_id = get_current_user_id();
$certificates = get_user_certificates($current_user_id); // Your function

foreach ($certificates as $cert) {
    $entry_id = $cert['entry_id'];
    $cert_filename = basename($cert['path']);
    
    // Generate secure download URL
    $secure_url = get_stylesheet_directory_uri() . '/includes/secure-exam-certificate-download.php';
    $download_url = add_query_arg([
        'file' => $cert_filename,
        'entry_id' => $entry_id,
        'v' => time()
    ], $secure_url);
    ?>
    
    <div class="certificate-item">
        <h4><?= esc_html($cert['method']) ?> Certificate</h4>
        <p>Issue Date: <?= esc_html($cert['issue_date']) ?></p>
        <p>Status: <?= esc_html($cert['status']) ?></p>
        <a href="<?= esc_url($download_url) ?>" 
           target="_blank" 
           class="btn btn-primary">
            Download Certificate
        </a>
    </div>
    
    <?php
}
?>
```

---

## Testing Checklist

- [ ] User can log in to frontend
- [ ] User can see their certificates in profile
- [ ] User can click download button
- [ ] PDF opens in new tab (not 403 error)
- [ ] User cannot download other users' certificates
- [ ] Admin can download all certificates

---

## Summary

**The secure download script already works for users!** You just need to:

1. ✅ Use secure download URL (not direct PDF URL)
2. ✅ Include `entry_id` parameter
3. ✅ Ensure user is logged in

**No changes needed to secure download script** - it already allows certificate owners to download their own certificates!

---

## Need Help Finding the Code?

If you can't find where certificates are displayed in user profile:

1. Search for: `certificates` in `user-profile.php`
2. Look for: `<a href=` with PDF links
3. Check for: Database queries fetching certificates
4. Look in: SGNDT Certificates tab section

Provide the code snippet and I can help update it!
