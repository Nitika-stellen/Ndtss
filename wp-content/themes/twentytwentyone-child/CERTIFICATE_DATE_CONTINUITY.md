# Certificate Date Continuity Logic

## Overview
This document explains the certificate date continuity system implemented to ensure proper certificate lifecycle management.

## Date Calculation Rules

### 1. Initial Certificate (No Suffix)
- **Issue Date:** Actual submission/approval date
- **Expiry Date:** Issue Date + 5 years
- **Example:**
  ```
  Certificate Number: A0134
  Issue Date: 2021-04-01
  Expiry Date: 2026-04-01 (2021-04-01 + 5 years)
  ```

### 2. Renewal Certificate (-01 Suffix)
- **Issue Date:** Original certificate's expiry date OR current date (if renewed late) ✅
- **Expiry Date:** Issue Date + 5 years
- **Example (On-time renewal):**
  ```
  Certificate Number: A0134-01
  Issue Date: 2026-04-01 (original certificate's expiry date)
  Expiry Date: 2031-04-01 (2026-04-01 + 5 years)
  ```
- **Example (Late renewal - after expiry):**
  ```
  Certificate Number: A0134-01
  Original expiry: 2026-04-01
  Renewal date: 2026-08-15 (late renewal)
  Issue Date: 2026-08-15 (current date, not past expiry date)
  Expiry Date: 2031-08-15 (2026-08-15 + 5 years)
  ```

### 3. Recertification Certificate (-02 Suffix)
- **Issue Date:** Renewal certificate's expiry date OR current date (if recertified late) ✅
- **Expiry Date:** Issue Date + 10 years
- **Example (On-time recertification):**
  ```
  Certificate Number: A0134-02
  Issue Date: 2031-04-01 (renewal certificate's expiry date)
  Expiry Date: 2041-04-01 (2031-04-01 + 10 years)
  ```
- **Example (Late recertification - after expiry):**
  ```
  Certificate Number: A0134-02
  Renewal expiry: 2031-04-01
  Recertification date: 2032-01-10 (late recertification)
  Issue Date: 2032-01-10 (current date, not past expiry date)
  Expiry Date: 2042-01-10 (2032-01-10 + 10 years)
  ```

## Complete Certificate Lifecycle Example

### Scenario: Certificate A0134

#### Phase 1: Initial Certificate
```
Certificate: A0134
Issue Date: 2021-04-01
Expiry Date: 2026-04-01
Validity: 5 years
Status: Active from 2021-04-01 to 2026-04-01
```

#### Phase 2: Renewal Window
```
Renewal Window Opens: 2025-10-01 (6 months before expiry)
Grace Period Ends: 2027-04-01 (12 months after expiry)
User can renew: 2025-10-01 to 2027-04-01
```

#### Phase 3: Renewal Certificate Issued
```
Certificate: A0134-01
Issue Date: 2026-04-01 ← Previous certificate's expiry date
Expiry Date: 2031-04-01
Validity: 5 years
Status: Active from 2026-04-01 to 2031-04-01
```

#### Phase 4: Recertification Eligibility
```
Recertification Eligible: 2030-04-01 (9 years from initial issue: 2021-04-01 + 9 years)
```

#### Phase 5: Recertification Certificate Issued
```
Certificate: A0134-02
Issue Date: 2031-04-01 ← Renewal certificate's expiry date
Expiry Date: 2041-04-01
Validity: 10 years
Status: Active from 2031-04-01 to 2041-04-01
```

## Timeline Visualization

```
2021-04-01: Initial Certificate Issued (A0134)
     |
     | [5 years validity]
     |
2025-10-01: Renewal window opens
     |
2026-04-01: Initial expires, Renewal issued (A0134-01) ← Issue date = prev expiry
     |
     | [5 years validity]
     |
2030-04-01: Recertification eligible (9 years from initial)
     |
2031-04-01: Renewal expires, Recertification issued (A0134-02) ← Issue date = prev expiry
     |
     | [10 years validity]
     |
2041-04-01: Recertification expires
     |
     | [No further renewals - max 19 years total]
```

## Implementation Details

### Files Modified:
1. **pdf-final-cert-generator.php**
   - Added logic to fetch previous certificate's expiry date
   - Set issue date = previous expiry date for renewals/recertifications
   - Calculate expiry based on certificate type (5 years for renewal, 10 years for recertification)

2. **certificate-lifecycle-manager.php**
   - Added `getNextCertificateIssueDate()` helper function
   - Enhanced `calculateExpiryDate()` to handle different certificate types
   - Proper date continuity throughout the lifecycle

### Database Queries:
- For Renewal (-01): Query original certificate's expiry date
- For Recertification (-02): Query renewal certificate's expiry date (fallback to original + 5 years)

## Benefits

1. **Continuous Coverage:** No gaps between certificates
2. **Clear Audit Trail:** Easy to track certificate history
3. **Compliance:** Meets regulatory requirements for certification continuity
4. **User-Friendly:** Clear progression visible in certificate dates
5. **Prevents Errors:** System automatically calculates correct dates

## Important Notes

- ✅ **On-time renewals:** Certificate starts when the previous expires (continuous coverage)
- ✅ **Late renewals:** Certificate starts on current date (no backdating)
- ✅ All dates are timezone-aware (Asia/Kolkata)
- ✅ Dates are stored in both display format (d.m.Y) and SQL format (Y-m-d)
- ✅ The system prevents date tampering by re-fetching from database
- ✅ **No certificates with past dates** - if renewed after expiry, issue date = current date

## Late Renewal Handling

### Scenario: Certificate Renewed After Expiry

**Original Certificate:**
- Expiry Date: 2026-04-01

**Late Renewal (within grace period):**
- User renews on: 2026-08-15 (4 months after expiry)
- New certificate issue date: 2026-08-15 (current date, NOT 2026-04-01)
- New certificate expiry: 2031-08-15 (issue date + 5 years)

**Why?**
- Cannot issue certificates with past dates
- Current date ensures certificate is valid from issuance
- User "loses" the coverage gap period (April to August)
- Encourages timely renewal to maintain continuous coverage

## Testing Checklist

### On-time Renewals
- [ ] Initial certificate shows correct issue and expiry dates
- [ ] Renewal certificate issue date = original expiry date (if renewed before/on expiry)
- [ ] Renewal certificate expiry = issue date + 5 years
- [ ] Recertification certificate issue date = renewal expiry date (if recertified before/on expiry)
- [ ] Recertification certificate expiry = issue date + 10 years

### Late Renewals (After Expiry)
- [ ] Late renewal: Issue date = current date (not past expiry date)
- [ ] Late renewal: Expiry = current date + 5 years
- [ ] Late recertification: Issue date = current date (not past expiry date)
- [ ] Late recertification: Expiry = current date + 10 years

### General Tests
- [ ] PDF displays correct dates
- [ ] Database stores correct dates
- [ ] User profile shows correct certificate timeline
- [ ] No certificates are issued with past dates
- [ ] Works for both CPD and Exam renewal methods
