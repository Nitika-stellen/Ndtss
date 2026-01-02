# Certificate Expiry Date Logic - Renewal vs Recertification

## Requirement
When generating final certificates for Form 39:
- **Renewal** certificates should have **5 years** validity
- **Recertification** certificates should have **10 years** validity

## Implementation

### Certificate Type Identification

Certificates are identified by their suffix:

| Certificate Type | Suffix | Validity Period | Example |
|-----------------|--------|-----------------|---------|
| **Initial** | `-01` | 5 years | `SGNDT12301-01` |
| **Form 15 Special** | `-02` | 5 years | `SGNDT12301-02` |
| **Renewal** | `-03` | **5 years** | `SGNDT12301-03` |
| **Recertification** | `-04` | **10 years** | `SGNDT12301-04` |

### Logic Flow

**File**: `includes/pdf-final-cert-generator.php` (Lines 337-357)

```php
// Extract certificate suffix
$cert_suffix = substr($certificate_number, -3); // e.g., '-03', '-04'

if ($cert_suffix === '-04' || $validity_period === 'recertification') {
    // Recertification: 10 years validity
    $expiry_datetime->modify('+10 years');
    
} elseif ($cert_suffix === '-03' || $validity_period === 'renew') {
    // Renewal: 5 years validity
    $expiry_datetime->modify('+5 years');
    
} else {
    // Initial and others: 5 years validity
    $expiry_datetime->modify('+5 years');
}
```

### How Suffix is Determined

**For Form 39** (Renewal/Recertification):

The system checks field 27 and previous certificates to determine the type:

```php
// Check if this is recertification or renewal
if ($renewal_cert > 0 || strpos($renewal_type, 'RECERT') !== false) {
    // This is RECERTIFICATION
    $validity_period = 'recertification';
    $suffix = '-04';  // → 10 years validity
} else {
    // This is RENEWAL
    $validity_period = 'renew';
    $suffix = '-03';  // → 5 years validity
}
```

**Criteria for Recertification** (`-04` suffix):
1. Candidate has a previous renewal certificate (with `-03` suffix), OR
2. Field 27 contains "RECERT" or "RECERTIFICATION"

**Criteria for Renewal** (`-03` suffix):
1. Candidate has a previous initial certificate (with `-01` suffix)
2. Field 27 does NOT contain "RECERT"

---

## Examples

### Example 1: Renewal Certificate

**Scenario**:
- Candidate has initial certificate: `SGNDT12301-01` (issued 2020, expires 2025)
- Candidate applies for Form 39 (first time renewal)
- Field 27: "Renewal"

**Result**:
- New certificate: `SGNDT12301-03`
- Issue date: 2025-12-11
- **Expiry date: 2030-12-11** (5 years)

---

### Example 2: Recertification Certificate

**Scenario**:
- Candidate has initial certificate: `SGNDT12301-01` (issued 2015, expires 2020)
- Candidate has renewal certificate: `SGNDT12301-03` (issued 2020, expires 2025)
- Candidate applies for Form 39 (recertification)
- Field 27: "Recertification"

**Result**:
- New certificate: `SGNDT12301-04`
- Issue date: 2025-12-11
- **Expiry date: 2035-12-11** (10 years)

---

### Example 3: Initial Certificate

**Scenario**:
- Candidate has no previous certificates
- Candidate applies for Form 15 (initial exam)

**Result**:
- New certificate: `SGNDT12301-01`
- Issue date: 2025-12-11
- **Expiry date: 2030-12-11** (5 years)

---

## Validity Period Summary

| Certificate Suffix | Type | Validity | Use Case |
|-------------------|------|----------|----------|
| `-01` | Initial | **5 years** | First certification |
| `-02` | Form 15 Special | **5 years** | Form 15 with field 62 = 'yes' |
| `-03` | **Renewal** | **5 years** | First renewal after initial |
| `-04` | **Recertification** | **10 years** | Renewal after renewal |

---

## Database Storage

The expiry date is stored in the `sgndt_final_certifications` table:

```sql
CREATE TABLE sgndt_final_certifications (
    ...
    issue_date DATE,
    expiry_date DATE,  -- Calculated based on certificate type
    certificate_number VARCHAR(50),
    ...
);
```

**Example Records**:

| certificate_number | issue_date | expiry_date | validity_years |
|-------------------|------------|-------------|----------------|
| SGNDT12301-01 | 2020-01-01 | 2025-01-01 | 5 |
| SGNDT12301-03 | 2025-01-01 | 2030-01-01 | 5 (Renewal) |
| SGNDT12301-04 | 2030-01-01 | 2040-01-01 | 10 (Recert) |

---

## Logging

The system logs which validity period is applied:

```
Certificate SGNDT12301-03: Renewal - 5 years validity
Certificate SGNDT12301-04: Recertification - 10 years validity
Certificate SGNDT12301-01: Initial/Other - 5 years validity
```

Check `wp-content/debug.log` to verify correct validity periods are being applied.

---

## Testing

### Test 1: Renewal Certificate (5 years)

1. Generate a Form 39 certificate for a candidate with initial certificate
2. Ensure field 27 = "Renewal" (or empty)
3. **Expected**:
   - Certificate number ends with `-03`
   - Expiry date = Issue date + 5 years
   - Log shows: "Renewal - 5 years validity"

### Test 2: Recertification Certificate (10 years)

1. Generate a Form 39 certificate for a candidate with renewal certificate
2. Ensure field 27 = "Recertification"
3. **Expected**:
   - Certificate number ends with `-04`
   - Expiry date = Issue date + 10 years
   - Log shows: "Recertification - 10 years validity"

### Test 3: Initial Certificate (5 years)

1. Generate a Form 15 certificate for new candidate
2. **Expected**:
   - Certificate number ends with `-01`
   - Expiry date = Issue date + 5 years
   - Log shows: "Initial/Other - 5 years validity"

---

## Verification Checklist

After generating a certificate:

- [ ] Check certificate number suffix
- [ ] Verify expiry date on PDF
- [ ] Check database `expiry_date` field
- [ ] Review debug log for validity period message
- [ ] Confirm matches expected validity (5 or 10 years)

---

## File Modified

**File**: `includes/pdf-final-cert-generator.php`
**Lines**: 337-357
**Changes**: Updated expiry date calculation logic to properly distinguish between renewal (5 years) and recertification (10 years)

---

## Deployment

This file needs to be uploaded to live server:

```
wp-content/themes/twentytwentyone-child/includes/pdf-final-cert-generator.php
```

**Already in deployment list**: Yes (File #5 in deployment checklist)

---

**Status**: ✅ Complete
**Date**: 2025-12-11
**Impact**: Ensures correct validity periods for renewal vs recertification certificates
