# Form 39 Signature Display Fix - DOMPDF Path Issue

## Problem
Signatures were not displaying in Form 39 final certificates. The signature box appeared empty even though the signature was being processed.

## Root Cause
**DOMPDF requires absolute file paths, not URLs**, to embed images in PDFs.

The code was using:
```php
$sign = 'https://site.com/wp-content/uploads/certificates/cropped_signature_123.png'; // URL
```

But DOMPDF needs:
```php
$sign_path = '/path/to/wp-content/uploads/certificates/cropped_signature_123.png'; // Absolute path
```

## Solution Implemented

### File Modified
**File**: `includes/pdf-final-cert-generator.php`

### Changes Made

#### 1. Added `$sign_path` Variable (Line 150)

**Before**:
```php
// Process signature
$sign = '';
if (!empty($signature_path)) {
    if (file_exists($signature_path)) {
        $signature_cropped_path = $upload_dir['basedir'] . '/certificates/cropped_signature_' . $entry['id'] . '.png';
        
        if (function_exists('crop_signature_image')) {
            $cropped_url = crop_signature_image($signature_path, $signature_cropped_path);
            if ($cropped_url && !empty($cropped_url)) {
                $sign = $cropped_url; // URL - doesn't work with DOMPDF!
            }
        }
    }
}
```

**After**:
```php
// Process signature
$sign = '';
$sign_path = ''; // Store path for DOMPDF
if (!empty($signature_path)) {
    if (file_exists($signature_path)) {
        $signature_cropped_path = $upload_dir['basedir'] . '/certificates/cropped_signature_' . $entry['id'] . '.png';
        
        if (function_exists('crop_signature_image')) {
            $cropped_url = crop_signature_image($signature_path, $signature_cropped_path);
            if ($cropped_url && !empty($cropped_url)) {
                $sign = $cropped_url; // URL for reference
                // Convert URL to absolute file path for DOMPDF
                $sign_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $cropped_url);
                
                // Verify the cropped file exists
                if (file_exists($sign_path)) {
                    error_log("Signature cropped successfully: {$sign_path}");
                } else {
                    error_log("Cropped signature file not found: {$sign_path}");
                    $sign_path = ''; // Reset if file doesn't exist
                }
            }
        }
    }
}
```

---

#### 2. Updated HTML to Use File Path (Line 426)

**Before**:
```php
(!empty($sign) ? '<img src="' . $sign . '" style="height:50px;"/>' : '<div style="height:50px; width: 200px;"></div>')
```
**Problem**: Uses URL - DOMPDF can't load it!

**After**:
```php
(!empty($sign_path) ? '<img src="' . $sign_path . '" style="height:50px;"/>' : '<div style="height:50px; width: 200px;"></div>')
```
**Solution**: Uses absolute file path - DOMPDF can load it!

---

## URL vs Path Conversion

### Example:

**URL** (doesn't work with DOMPDF):
```
https://sistagging.com/ndtss/wp-content/uploads/certificates/cropped_signature_123.png
```

**Absolute Path** (works with DOMPDF):
```
C:/xampp/htdocs/NDTSS/wp-content/uploads/certificates/cropped_signature_123.png
```

### Conversion Code:
```php
$sign_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $cropped_url);
```

**Replaces**:
- `https://sistagging.com/ndtss/wp-content/uploads` (baseurl)

**With**:
- `C:/xampp/htdocs/NDTSS/wp-content/uploads` (basedir)

---

## Why This Happens

### DOMPDF Image Loading
DOMPDF has two ways to load images:

1. **Remote URLs** (requires `isRemoteEnabled` option):
   - Slower
   - May fail due to network issues
   - Security restrictions

2. **Local File Paths** (recommended):
   - Faster
   - More reliable
   - No network required

**Our fix uses local file paths for better reliability!**

---

## Testing

### Test Form 15 Certificate
1. Generate final certificate for Form 15
2. Check signature displays
3. **Expected**: ✅ Signature shows (was already working)

### Test Form 39 Certificate
1. Generate final certificate for Form 39
2. Check signature displays
3. **Expected**: ✅ Signature now shows!

### Verify in Debug Log
Check `wp-content/debug.log` for:

**✅ Success**:
```
Signature cropped successfully: C:/xampp/htdocs/NDTSS/wp-content/uploads/certificates/cropped_signature_123.png
```

**❌ File Not Found**:
```
Cropped signature file not found: C:/xampp/htdocs/NDTSS/wp-content/uploads/certificates/cropped_signature_123.png
```

---

## Common Issues

### Issue 1: Signature Still Not Showing

**Check**:
1. Is signature field being detected? (Check debug log for "Form 39: Signature found")
2. Does cropped file exist? (Check `/wp-content/uploads/certificates/cropped_signature_*.png`)
3. Are file permissions correct? (Should be 644)

**Solution**:
- Check debug log for specific error
- Verify signature file exists
- Check file permissions

---

### Issue 2: Signature Shows in Form 15 but Not Form 39

**Cause**: Signature field ID is different

**Solution**:
1. Check debug log for "Form 39 Entry Field" messages
2. Find field with `.png` filename
3. Add that field ID to signature detection (line 65)

---

### Issue 3: Cropped File Not Created

**Check**:
1. Does `crop_signature_image` function exist?
2. Is GD library installed?
3. Is `/wp-content/uploads/certificates/` writable?

**Solution**:
```bash
# Check GD library
php -m | grep -i gd

# Check folder permissions
chmod 755 /wp-content/uploads/certificates/
```

---

## File Path Examples

### Windows (XAMPP):
```
C:/xampp/htdocs/NDTSS/wp-content/uploads/certificates/cropped_signature_123.png
```

### Linux:
```
/var/www/html/wp-content/uploads/certificates/cropped_signature_123.png
```

### Both work with DOMPDF!

---

## Deployment

### File to Upload
```
wp-content/themes/twentytwentyone-child/includes/pdf-final-cert-generator.php
```

**Already in deployment list!**

---

## Summary

### What Changed
- ✅ Added `$sign_path` variable for absolute file path
- ✅ Convert URL to path using `str_replace()`
- ✅ Updated HTML to use `$sign_path` instead of `$sign`
- ✅ Added file existence verification

### Why It Works
- ✅ DOMPDF can access local files directly
- ✅ Faster than loading remote URLs
- ✅ More reliable
- ✅ No network dependencies

### Benefits
- ✅ Signatures now display in Form 39 certificates
- ✅ Works for both Form 15 and Form 39
- ✅ Better error logging
- ✅ More reliable image embedding

---

## Before & After

### Before (Empty Box):
```
┌─────────────────────────┐
│                         │  ← Empty signature box
│                         │
└─────────────────────────┘
```

### After (Signature Shows):
```
┌─────────────────────────┐
│   [Signature Image]     │  ← Signature displays!
│                         │
└─────────────────────────┘
```

---

**Status**: ✅ Fixed
**Date**: 2025-12-11
**Impact**: Signatures now display correctly in Form 39 final certificates
**Root Cause**: DOMPDF requires file paths, not URLs
**Solution**: Convert URL to absolute file path before embedding
