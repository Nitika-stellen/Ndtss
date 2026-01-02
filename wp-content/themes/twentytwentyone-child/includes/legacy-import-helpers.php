<?php
/**
 * Legacy Membership Import Helper Functions
 * 
 * Helper functions for importing legacy membership data from Excel
 * 
 * @package SGNDT
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parse legacy date format (DD/MM/YYYY) to MySQL format (YYYY-MM-DD)
 * 
 * @param string $date_string Date string in various formats
 * @return string|false MySQL date format or false on failure
 */
function parse_legacy_date($date_string) {
    if (empty($date_string) || trim($date_string) === '-' || trim($date_string) === '') {
        return false;
    }
    
    $date_string = trim($date_string);
    
    // Try different date formats
    $formats = array(
        'd/m/Y',           // 13/09/2024 (DD/MM/YYYY)
        'd-m-Y',           // 13-09-2024
        'd.m.Y',           // 13.09.2024
        'Y-m-d',           // 2024-09-13 (already normalized)
        'Y/m/d',           // 2024/09/13
        'm/d/Y',           // 09/13/2024 (US format)
        'd/m/y',           // 13/09/24 (2-digit year)
    );
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $date_string);
        if ($date !== false) {
            // Validate the parsed date matches the input
            if ($date->format($format) === $date_string) {
                return $date->format('Y-m-d');
            }
        }
    }
    
    // Try strtotime as fallback
    $timestamp = strtotime($date_string);
    if ($timestamp !== false) {
        $parsed = date('Y-m-d', $timestamp);
        // Basic validation - check if year is reasonable (1900-2100)
        $year = (int)date('Y', $timestamp);
        if ($year >= 1900 && $year <= 2100) {
            return $parsed;
        }
    }
    
    return false;
}

/**
 * Normalize membership period string to numeric value
 * 
 * @param string $period_string Period string (e.g., "1 Year", "2 Years", "Lifetime")
 * @return int|string Numeric value or "Lifetime" string
 */
function normalize_period($period_string) {
    if (empty($period_string)) {
        return 1; // Default to 1 year
    }
    
    $period_string = trim($period_string);
    
    // Check for lifetime
    if (stripos($period_string, 'lifetime') !== false) {
        return 'Lifetime';
    }
    
    // Extract numeric value
    if (preg_match('/(\d+)\s*(?:Year|Yr|Y)/i', $period_string, $matches)) {
        return intval($matches[1]);
    }
    
    // If no match, try to extract any number
    if (preg_match('/(\d+)/', $period_string, $matches)) {
        return intval($matches[1]);
    }
    
    return 1; // Default to 1 year
}

/**
 * Normalize price string to float
 * 
 * @param string $price_string Price string (may contain currency symbols, commas)
 * @return float Normalized price value
 */
function normalize_price($price_string) {
    if (empty($price_string)) {
        return 0.00;
    }
    
    // Remove currency symbols, commas, spaces
    $price = preg_replace('/[^\d.]/', '', $price_string);
    
    return floatval($price);
}

/**
 * Map membership status from CSV to WordPress status
 * 
 * @param string $csv_status Status from CSV (Active, Expired, Pending)
 * @return string WordPress status (approved, expired, pending)
 */
function map_membership_status($csv_status) {
    $status = strtolower(trim($csv_status));
    
    switch ($status) {
        case 'active':
            return 'approved';
        case 'expired':
            return 'expired';
        case 'pending':
            return 'pending';
        default:
            return 'pending'; // Default to pending for unknown statuses
    }
}

/**
 * Get category role name from membership category
 * 
 * @param string $category Membership category (Ordinary, Associate, Professional, Fellow, Corporate)
 * @return string Role name (ordinary_member, associate_member, etc.)
 */
function get_category_role($category) {
    $category = strtolower(trim($category));
    
    $role_map = array(
        'ordinary' => 'ordinary_member',
        'associate' => 'associate_member',
        'professional' => 'professional_member',
        'fellow' => 'fellow_member',
        'corporate' => 'corporate_member'
    );
    
    foreach ($role_map as $key => $role) {
        if (strpos($category, $key) !== false) {
            return $role;
        }
    }
    
    return 'ordinary_member'; // Default
}

/**
 * Split full name into first and last name
 * 
 * @param string $full_name Full name string
 * @return array Array with 'first_name' and 'last_name' keys
 */
function split_name($full_name) {
    $full_name = trim($full_name);
    
    if (empty($full_name)) {
        return array(
            'first_name' => '',
            'last_name' => ''
        );
    }
    
    $name_parts = explode(' ', $full_name, 2);
    
    return array(
        'first_name' => $name_parts[0],
        'last_name' => isset($name_parts[1]) ? $name_parts[1] : ''
    );
}

/**
 * Normalize email address for duplicate detection
 * 
 * @param string $email Email address
 * @return string Normalized email (lowercase, trimmed)
 */
function normalize_email($email) {
    return strtolower(trim($email));
}

/**
 * Check if two emails are similar (fuzzy match)
 * 
 * @param string $email1 First email
 * @param string $email2 Second email
 * @return bool True if emails are similar
 */
function emails_are_similar($email1, $email2) {
    $email1 = normalize_email($email1);
    $email2 = normalize_email($email2);
    
    if ($email1 === $email2) {
        return true;
    }
    
    // Check for common typos
    $common_typos = array(
        'gmail.com' => array('gmail.co', 'gmial.com', 'gmail.con'),
        'yahoo.com' => array('yahoo.co', 'yaho.com'),
        'hotmail.com' => array('hotmail.co', 'hotmial.com'),
    );
    
    foreach ($common_typos as $correct => $typos) {
        if (strpos($email1, $correct) !== false && strpos($email2, $typos[0]) !== false) {
            $email1_domain = substr($email1, strpos($email1, '@'));
            $email2_domain = substr($email2, strpos($email2, '@'));
            if ($email1_domain === '@' . $correct && $email2_domain === '@' . $typos[0]) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Normalize phone number for matching
 * 
 * @param string $phone Phone number string
 * @return string Normalized phone (digits only)
 */
function normalize_phone($phone) {
    return preg_replace('/[^\d]/', '', $phone);
}

/**
 * Map Excel column headers to standardized field names
 * Handles variations in column names (case, spacing, etc.)
 * 
 * @param array $headers Array of header names from Excel
 * @return array Array mapping original headers to standardized names
 */
function map_column_headers($headers) {
    $header_map = array();
    
    // Define possible variations for each field
    $field_variations = array(
        'Email' => array('email', 'e-mail', 'email address', 'email addr', 'e mail', 'email id1', 'email id', 'emailid', 'emailid1', 'email id 1', 'email id1', 'emailid 1'),
        'Name' => array('name', 'full name', 'member name', 'fullname'),
        'S/N' => array('s/n', 'sn', 'serial number', 'serial no', 'serial no.', 's. n.', 's n', 's/ no', 's/ no.'),
        'Membership No.' => array('membership no.', 'membership no', 'membership number', 'member no.', 'member no', 'member number', 'mem no.', 'mem no', 'mem number'),
        'NRIC/Passport No.' => array('nric/passport no.', 'nric/passport no', 'nric/passport number', 'nric', 'passport no.', 'passport no', 'passport number', 'id number', 'id no.'),
        'Mobile No.' => array('mobile no.', 'mobile no', 'mobile number', 'phone', 'phone no.', 'phone no', 'phone number', 'mobile', 'contact no.', 'contact no', 'contact number', 'mobile1'),
        'Tel1' => array('tel1', 'tel 1', 'telephone 1', 'telephone1', 'tel', 'telephone', 'phone1', 'phone 1'),
        'Main Contact Person' => array('main contact person', 'main contact', 'contact person', 'primary contact', 'contact name'),
        'Company Address' => array('company address', 'company addr', 'business address', 'office address', 'work address'),
        'Residential Address' => array('residential address', 'residential addr', 'home address', 'address', 'mailing address'),
        'Membership Type' => array('membership type', 'member type', 'type', 'mem type'),
        'Membership Category' => array('membership category', 'member category', 'category', 'mem category', 'classification'),
        'Membership Status' => array('membership status', 'member status', 'status', 'mem status'),
        'Start Date' => array('start date', 'approval date', 'membership start', 'start', 'date started', 'joined date', 'member since', 'since', 'membership start date', 'renewal date', 'submitted date', 'submission date', 'date submitted'),
        'Expiry Date' => array('expiry date', 'expiration date', 'expires', 'expiry', 'end date', 'membership expiry'),
        'Period' => array('period', 'duration', 'membership period', 'term', 'membership term'),
        'Price' => array('price', 'amount', 'fee', 'membership fee', 'cost', 'payment amount'),
        'Payment Status' => array('payment status', 'pay status', 'paid status', 'payment'),
        'Payment Date' => array('payment date', 'paid date', 'date paid', 'payment received'),
        'Payment Method' => array('payment method', 'pay method', 'method', 'payment type'),
        'Remarks' => array('remarks', 'remark', 'notes', 'note', 'comments', 'comment'),
        'Certificate No.' => array('certificate no.', 'certificate no', 'certificate number', 'cert no.', 'cert no', 'cert number'),
        'Certificate Issue Date' => array('certificate issue date', 'cert issue date', 'certificate issued', 'cert issued'),
        'Certificate Expiry Date' => array('certificate expiry date', 'cert expiry date', 'certificate expires', 'cert expires'),
        'Company Name' => array('company name', 'company', 'organisation', 'organization', 'employer', 'company/organisation'),
        'Designation' => array('designation', 'position', 'job title', 'title', 'role', 'job position'),
        'Qualification' => array('qualification', 'qualifications', 'education', 'degree', 'academic qualification', 'highest qualification'),
        'Academic Qualifications' => array('academic qualifications', 'academic qualification', 'academic qual', 'education', 'degree', 'academic degree'),
        'Professional Qualifications' => array('professional qualifications', 'professional qualification', 'professional qual', 'professional cert', 'professional certification'),
        'Experience in Yrs' => array('experience in yrs', 'experience in years', 'experience', 'years of experience', 'yrs of experience', 'experience yrs', 'experience years')
    );
    
    // Create normalized lookup
    $normalized_lookup = array();
    foreach ($field_variations as $standard_name => $variations) {
        foreach ($variations as $variation) {
            $normalized_lookup[strtolower(trim($variation))] = $standard_name;
        }
        // Also add the standard name itself
        $normalized_lookup[strtolower(trim($standard_name))] = $standard_name;
    }
    
    // Map each header
    foreach ($headers as $original_header) {
        $normalized = strtolower(trim($original_header));
        if (isset($normalized_lookup[$normalized])) {
            $header_map[$original_header] = $normalized_lookup[$normalized];
        } else {
            // Keep original if no match found
            $header_map[$original_header] = $original_header;
        }
    }
    
    return $header_map;
}

/**
 * Normalize row data using header mapping
 * 
 * @param array $row_data Original row data with original headers
 * @param array $header_map Header mapping from map_column_headers()
 * @return array Normalized row data with standardized field names
 */
function normalize_row_data($row_data, $header_map) {
    $normalized = array();
    
    foreach ($row_data as $original_header => $value) {
        if (isset($header_map[$original_header])) {
            $standard_name = $header_map[$original_header];
            $normalized[$standard_name] = $value;
        } else {
            // Keep original if not mapped
            $normalized[$original_header] = $value;
        }
    }
    
    return $normalized;
}

/**
 * Validate required fields in row data
 * 
 * @param array $row_data Row data array (should be normalized)
 * @return array Array with 'valid' (bool) and 'errors' (array) keys
 */
function validate_legacy_row($row_data) {
    $errors = array();
    
    $required_fields = array(
        'Email' => 'Email address',
        'Name' => 'Name',
        'Membership Status' => 'Membership Status',
        'Start Date' => 'Start Date',
        'Expiry Date' => 'Expiry Date'
    );
    
    foreach ($required_fields as $field => $label) {
        $value = isset($row_data[$field]) ? trim($row_data[$field]) : '';
        if (empty($value) || $value === '-') {
            $errors[] = "Missing required field: {$label}";
        }
    }
    
    // Validate email format
    if (!empty($row_data['Email'])) {
        $email = trim($row_data['Email']);
        if (!is_email($email)) {
            $errors[] = "Invalid email format: {$email}";
        }
    }
    
    // Validate dates
    if (!empty($row_data['Start Date'])) {
        $start_date = parse_legacy_date($row_data['Start Date']);
        if (!$start_date) {
            $errors[] = "Invalid Start Date format: " . $row_data['Start Date'];
        }
    }
    
    if (!empty($row_data['Expiry Date'])) {
        $expiry_date = parse_legacy_date($row_data['Expiry Date']);
        if (!$expiry_date) {
            $errors[] = "Invalid Expiry Date format: " . $row_data['Expiry Date'];
        }
        
        // Check if expiry is after start date
        if (!empty($row_data['Start Date'])) {
            $start_date = parse_legacy_date($row_data['Start Date']);
            if ($start_date && $expiry_date && strtotime($expiry_date) < strtotime($start_date)) {
                $errors[] = "Expiry Date is before Start Date";
            }
        }
    }
    
    return array(
        'valid' => empty($errors),
        'errors' => $errors
    );
}

