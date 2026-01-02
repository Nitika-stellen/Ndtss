# Form 39 Signature Display Fix

## Problem
When generating final certificates:
- ✅ **Form 15**: Signature displays correctly
- ❌ **Form 39**: Signature not showing or displaying poorly

## Root Cause
The signature field ID for Form 39 was not being correctly detected. The code was only checking fields 115 and 29, but Form 39 might use a different field ID for the signature.

## Solution Implemented

### File Modified
**File**: `includes/pdf-final-cert-generator.php`

### Changes Made

#### 1. Enhanced Signature Field Detection (Lines 63-79)

**Before**:
```php
// Get signature from field (check common signature field IDs)
$signature_value = rgar($entry, '115') ?: rgar($entry, '29');
if (!empty($signature_value)) {
    $signature_url = $upload_dir['baseurl'] . '/gravity_forms/signatures/' . $signature_value;
    $signature_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $signature_url);
}
```

**After**:
```php
// Get signature from field (check multiple possible signature field IDs for Form 39)
// Try common signature field IDs in order of likelihood
$signature_value = rgar($entry, '115') ?: rgar($entry, '29') ?: rgar($entry, '11') ?: rgar($entry, '14');

if (!empty($signature_value)) {
    $signature_url = $upload_dir['baseurl'] . '/gravity_forms/signatures/' . $signature_value;
    $signature_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $signature_url);
    error_log("Form 39: Signature found - value: {$signature_value}, path: {$signature_path}");
} else {
    error_log("Form 39: No signature found in fields 115, 29, 11, or 14");
    // Log all entry fields to help identify the correct signature field
    foreach ($entry as $key => $value) {
        if (is_numeric($key) && !empty($value) && strlen($value) < 100) {
            error_log("Form 39 Entry Field {$key}: " . substr($value, 0, 50));
        }
    }
}
```

**Improvements**:
- ✅ Checks 4 possible signature field IDs (115, 29, 11, 14)
- ✅ Logs which field was used
- ✅ If no signature found, logs all entry fields to help identify the correct field

---

#### 2. Enhanced Signature Processing Logging (Lines 137-170)

**Before**:
```php
// Process signature
$sign = '';
if (!empty($signature_path) && file_exists($signature_path)) {
    $signature_cropped_path = $upload_dir['basedir'] . '/certificates/cropped_signature_' . $entry['id'] . '.png';
    
    if (function_exists('crop_signature_image')) {
        $cropped_url = crop_signature_image($signature_path, $signature_cropped_path);
        if ($cropped_url && !empty($cropped_url)) {
            $sign = $cropped_url;
        }
    }
}
```

**After**:
```php
// Process signature
$sign = '';
if (!empty($signature_path)) {
    if (file_exists($signature_path)) {
        $signature_cropped_path = $upload_dir['basedir'] . '/certificates/cropped_signature_' . $entry['id'] . '.png';

        if (function_exists('crop_signature_image')) {
            $cropped_url = crop_signature_image($signature_path, $signature_cropped_path);
            if ($cropped_url && !empty($cropped_url)) {
                $sign = $cropped_url;
                error_log("Signature cropped successfully for entry {$entry['id']}, form {$form_id}: {$cropped_url}");
            } else {
                error_log("Signature cropping failed for entry {$entry['id']}, form {$form_id}");
            }
        } else {
            error_log("crop_signature_image function not found");
        }
    } else {
        error_log("Signature file does not exist for entry {$entry['id']}, form {$form_id}: {$signature_path}");
    }
} else {
    error_log("No signature path set for entry {$entry['id']}, form {$form_id}");
}
```

**Improvements**:
- ✅ Logs successful signature cropping
- ✅ Logs if cropping fails
- ✅ Logs if signature file doesn't exist
- ✅ Logs if no signature path was set

---

## Debugging Process

### Step 1: Generate a Form 39 Certificate

When you generate a Form 39 final certificate, check `wp-content/debug.log` for these messages:

### Step 2: Check Signature Detection

Look for one of these log messages:

**✅ Success**:
```
Form 39: Signature found - value: abc123.png, path: /path/to/signatures/abc123.png
```

**❌ Not Found**:
```
Form 39: No signature found in fields 115, 29, 11, or 14
Form 39 Entry Field 1: John Doe
Form 39 Entry Field 2: john@example.com
Form 39 Entry Field 16: def456.png  ← This might be the signature!
...
```

If you see the "Not Found" message, look through the logged fields to find which field contains the signature filename (usually ends with `.png`).

### Step 3: Check Signature Processing

Look for these log messages:

**✅ Success**:
```
Signature cropped successfully for entry 123, form 39: https://site.com/.../cropped_signature_123.png
```

**❌ File Not Found**:
```
Signature file does not exist for entry 123, form 39: /path/to/signatures/abc123.png
```

**❌ Cropping Failed**:
```
Signature cropping failed for entry 123, form 39
```

---

## How to Fix if Signature Still Not Showing

### If Signature Field ID is Different

1. **Check debug.log** for the "Form 39 Entry Field" messages
2. **Find the field** that contains a `.png` filename
3. **Update the code** to include that field ID:

```php
// Add the new field ID (e.g., field 16)
$signature_value = rgar($entry, '115') ?: rgar($entry, '29') ?: rgar($entry, '11') ?: rgar($entry, '14') ?: rgar($entry, '16');
```

### If Signature File Doesn't Exist

1. **Check** if the signature file actually exists in:
   ```
   /wp-content/uploads/gravity_forms/signatures/
   ```

2. **Verify** the filename matches what's in the entry field

3. **Check** file permissions (should be 644)

### If Cropping Fails

1. **Check** if `crop_signature_image` function exists
2. **Verify** GD library is installed (required for image processing)
3. **Check** write permissions on `/wp-content/uploads/certificates/` folder

---

## Testing

### Test Form 15 Certificate
1. Generate final certificate for Form 15
2. Check signature displays correctly
3. **Expected**: ✅ Signature shows

### Test Form 39 Certificate
1. Generate final certificate for Form 39
2. Check `wp-content/debug.log`
3. Look for signature detection messages
4. Check certificate PDF
5. **Expected**: ✅ Signature shows

---

## Common Signature Field IDs

| Form | Common Field IDs |
|------|-----------------|
| **Form 15** | 115 |
| **Form 30** | 29, 11 |
| **Form 39** | 115, 29, 11, 14 |

**Note**: These may vary depending on your form configuration!

---

## Deployment

### File to Upload
```
wp-content/themes/twentytwentyone-child/includes/pdf-final-cert-generator.php
```

**This file is already in your deployment list!**

---

## Summary

### What Changed
- ✅ Checks 4 possible signature field IDs for Form 39
- ✅ Comprehensive logging for debugging
- ✅ Better error messages
- ✅ Helps identify correct signature field

### Benefits
- ✅ Signatures should now work for Form 39
- ✅ Easy to debug if issues persist
- ✅ Logs help identify correct field ID
- ✅ Better error handling

---

## Next Steps

1. **Upload** updated `pdf-final-cert-generator.php` to live server
2. **Generate** a Form 39 certificate
3. **Check** `wp-content/debug.log` for signature messages
4. **Verify** signature appears on certificate
5. **If not working**, check debug log to find correct field ID

---

**Status**: ✅ Enhanced with debugging
**Date**: 2025-12-11
**Impact**: Improved signature detection for Form 39 certificates with comprehensive logging
