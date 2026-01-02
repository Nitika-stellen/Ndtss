# Troubleshooting: Old URL Still Showing

## Problem
After updating `center_module.php`, the "View Result Notification" link still shows the old direct URL instead of the secure download URL.

## Possible Causes & Solutions

### 1. Browser Cache (Most Common)
**Symptom**: Page shows old URL even after file update

**Solution**:
- **Hard refresh** the page: `Ctrl + F5` (Windows) or `Cmd + Shift + R` (Mac)
- **Clear browser cache** completely
- **Open in incognito/private window** to test

---

### 2. PHP OpCode Cache
**Symptom**: Changes to PHP files not reflected

**Solution**:
- **Restart Apache/Nginx**:
  ```bash
  # For XAMPP
  Stop and start Apache from XAMPP Control Panel
  
  # For Linux
  sudo service apache2 restart
  # OR
  sudo service nginx restart
  sudo service php-fpm restart
  ```

- **Clear PHP OpCode Cache** (if using OPcache):
  ```php
  // Add this temporarily to wp-config.php
  opcache_reset();
  ```

---

### 3. WordPress Object Cache
**Symptom**: Metadata still returns old values

**Solution**:
- **Clear WordPress cache**:
  ```php
  // Add this temporarily to functions.php
  wp_cache_flush();
  ```

- **If using caching plugin** (W3 Total Cache, WP Super Cache, etc.):
  - Go to plugin settings
  - Click "Clear All Caches"

---

### 4. Page Not Refreshed
**Symptom**: Still looking at old page load

**Solution**:
- **Navigate away** from the page
- **Come back** to the entry
- **Reload** the page completely

---

### 5. Wrong File Updated
**Symptom**: Changes not appearing at all

**Solution**:
- **Verify file location**:
  ```
  wp-content/themes/twentytwentyone-child/panel/center_module.php
  ```

- **Check file timestamp**:
  - Right-click file → Properties
  - Verify "Modified" date is recent

- **View file contents**:
  - Open the file
  - Search for "Use secure download URL for notification"
  - Should be around line 305

---

## Quick Verification Steps

### Step 1: Check the Code
Open `center_module.php` and verify lines 305-316 look like this:

```php
// Use secure download URL for notification
if ($is_notification_generated && !empty($notification_meta['path'])) {
    $notification_filename = basename($notification_meta['path']);
    $secure_url = get_stylesheet_directory_uri() . '/includes/secure-exam-certificate-download.php';
    $notification_url = add_query_arg([
        'file' => $notification_filename,
        'entry_id' => $entry_id,
        'v' => time()
    ], $secure_url);
} else {
    $notification_url = '';
}
```

### Step 2: Check the Output
1. **Right-click** on "View Result Notification" button
2. **Inspect Element** or **Copy Link Address**
3. **Check the URL** - should look like:
   ```
   https://yoursite.com/wp-content/themes/twentytwentyone-child/includes/secure-exam-certificate-download.php?file=certificate_123_RT.pdf&entry_id=123&v=1234567890
   ```

### Step 3: Force Refresh
1. **Close the browser tab**
2. **Clear browser cache**
3. **Restart Apache** (if on XAMPP)
4. **Open the entry again**
5. **Check the URL again**

---

## Debug: Add Temporary Logging

Add this code temporarily to `center_module.php` after line 313:

```php
// TEMPORARY DEBUG - Remove after testing
error_log("DEBUG: Notification URL generated: " . $notification_url);
error_log("DEBUG: Notification filename: " . $notification_filename);
error_log("DEBUG: Entry ID: " . $entry_id);
```

Then:
1. Reload the page
2. Check `wp-content/debug.log`
3. Look for the DEBUG messages
4. Verify the URL is correct

**Remove this debug code after testing!**

---

## Check if Secure Download Script Exists

Verify the secure download script is in place:

**File should exist**:
```
wp-content/themes/twentytwentyone-child/includes/secure-exam-certificate-download.php
```

**Test directly**:
1. Copy a certificate filename (e.g., `certificate_123_RT.pdf`)
2. Visit this URL in browser:
   ```
   https://yoursite.com/wp-content/themes/twentytwentyone-child/includes/secure-exam-certificate-download.php?file=certificate_123_RT.pdf&entry_id=123
   ```
3. Should either:
   - Show the PDF (if logged in with permission)
   - Show "Access Denied" (if not logged in)
   - Show "Certificate not found" (if file doesn't exist)

---

## Still Not Working?

### Check for Multiple Instances

Search for ALL occurrences of `$notification_url` in `center_module.php`:

```bash
# In terminal/command prompt
cd wp-content/themes/twentytwentyone-child/panel
grep -n "notification_url" center_module.php
```

**Should find**:
- Line ~309: Where it's SET (secure URL)
- Line ~510: Where it's USED (in the link)
- Line ~535: Another usage (for center admin view)

**Make sure ALL instances use the variable, not hardcoded URLs**

---

## Alternative: View Page Source

1. **Load the entry page**
2. **Right-click** → **View Page Source**
3. **Search for** (Ctrl+F): `View Result Notification`
4. **Check the href** attribute
5. **Should be**: `secure-exam-certificate-download.php?file=...`
6. **Should NOT be**: `/wp-content/uploads/certificates/...`

---

## Nuclear Option: Clear Everything

If nothing else works:

```php
// Add to wp-config.php temporarily
define('WP_CACHE', false);
opcache_reset();
wp_cache_flush();
```

Then:
1. Restart web server
2. Clear browser cache
3. Close all browser tabs
4. Open in incognito window
5. Navigate to the entry

---

## Expected vs Actual

### ❌ OLD URL (Should NOT see this):
```
https://yoursite.com/wp-content/uploads/certificates/certificate_123_RT.pdf?v=1234567890
```

### ✅ NEW URL (Should see this):
```
https://yoursite.com/wp-content/themes/twentytwentyone-child/includes/secure-exam-certificate-download.php?file=certificate_123_RT.pdf&entry_id=123&v=1234567890
```

---

## Quick Fix Commands

### For XAMPP (Windows):
1. Stop Apache from XAMPP Control Panel
2. Start Apache from XAMPP Control Panel
3. Press `Ctrl + F5` in browser

### For Linux Server:
```bash
sudo service apache2 restart
# OR
sudo systemctl restart nginx
sudo systemctl restart php7.4-fpm  # adjust PHP version
```

### Clear WordPress Cache (via WP-CLI):
```bash
wp cache flush
```

---

## If Still Showing Old URL

**The issue is likely**:
1. Looking at cached page
2. PHP file not actually updated on server
3. Looking at wrong entry/method

**Try this**:
1. Generate a **NEW** certificate for a different method
2. Check if that one shows the secure URL
3. If yes → old certificates need regeneration
4. If no → file not updated correctly

---

## Contact Support

If none of the above works, provide:
1. Screenshot of the "View Result Notification" link (right-click → Inspect)
2. Contents of lines 305-316 from `center_module.php`
3. Whether `secure-exam-certificate-download.php` exists
4. Server type (XAMPP, Apache, Nginx, etc.)
5. Any error messages from `wp-content/debug.log`
