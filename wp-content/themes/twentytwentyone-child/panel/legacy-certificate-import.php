<?php
/**
 * Legacy Certificate Import System
 * 
 * Enhanced certificate import system for importing legacy certificate holder data
 * Handles multiple certificates per user (stored as separate columns)
 * Automatically registers users and links PDF files from Old-certificates directory
 * 
 * @package SGNDT
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include helper functions
require_once get_stylesheet_directory() . '/includes/legacy-import-helpers.php';

// Add admin menu (priority 20 to run after existing certificate import)
add_action('admin_menu', 'legacy_certificate_import_add_admin_menu', 20);
add_action('admin_enqueue_scripts', 'legacy_certificate_import_enqueue_scripts');
add_action('wp_ajax_legacy_cert_import_upload_csv', 'handle_legacy_cert_csv_upload');
add_action('wp_ajax_legacy_cert_import_process', 'handle_legacy_cert_csv_import');
add_action('wp_ajax_legacy_cert_import_upload_zip', 'handle_legacy_cert_zip_upload');
add_action('wp_ajax_legacy_cert_import_preview', 'handle_legacy_csv_preview');

/**
 * Add admin menu
 */
function legacy_certificate_import_add_admin_menu() {
    global $submenu, $menu;
    $parent_slug = '';
    
    // Check if certificate-renewals menu exists
    if (isset($submenu['certificate-renewals'])) {
        $parent_slug = 'certificate-renewals';
    }
    // Check if certificate-management menu already exists in submenu (created by other import system)
    elseif (isset($submenu['certificate-management'])) {
        $parent_slug = 'certificate-management';
    }
    // Check if certificate-management menu exists in main menu
    elseif (isset($menu)) {
        foreach ($menu as $menu_item) {
            if (isset($menu_item[2]) && $menu_item[2] === 'certificate-management') {
                $parent_slug = 'certificate-management';
                break;
            }
        }
    }
    
    // If neither exists, create the parent menu
    if (empty($parent_slug)) {
        add_menu_page(
            'Certificate Management',
            'Certificates',
            'manage_options',
            'certificate-management',
            '__return_empty_string',
            'dashicons-awards',
            30
        );
        $parent_slug = 'certificate-management';
    }
    
    add_submenu_page(
        $parent_slug,
        'Import Legacy Certificates',
        'Import Legacy Certificates',
        'manage_options',
        'legacy-certificate-import',
        'legacy_certificate_import_admin_page'
    );
}

/**
 * Enqueue admin scripts and styles
 */
function legacy_certificate_import_enqueue_scripts($hook) {
    if (strpos($hook, 'legacy-certificate-import') === false) {
        return;
    }
    
    wp_enqueue_style(
        'legacy-certificate-import-css',
        get_stylesheet_directory_uri() . '/panel/css/legacy-certificate-import.css',
        array(),
        '1.0.0'
    );
    
    wp_enqueue_script(
        'legacy-certificate-import-js',
        get_stylesheet_directory_uri() . '/panel/js/legacy-certificate-import.js',
        array('jquery'),
        '1.0.0',
        true
    );
    
    wp_localize_script('legacy-certificate-import-js', 'legacyCertImport', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('legacy_cert_import_nonce'),
        'max_file_size' => wp_max_upload_size()
    ));
}

/**
 * Main admin page
 */
function legacy_certificate_import_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    $old_certificates_dir = get_stylesheet_directory() . '/Old-certificates';
    $old_certificates_exists = file_exists($old_certificates_dir);
    $pdf_count = 0;
    if ($old_certificates_exists) {
        $pdf_files = glob($old_certificates_dir . '/*.pdf');
        $pdf_count = $pdf_files ? count($pdf_files) : 0;
    }
    
    ?>
    <div class="wrap legacy-certificate-import-wrap">
        <h1>Import Legacy Certificates</h1>
        <p class="description">Import legacy certificate holders from CSV/Excel files. The system will create WordPress users, certificate records, and link PDF files from the Old-certificates directory.</p>
        
        <div class="legacy-certificate-import-container">
            <!-- Step 1: CSV Upload -->
            <div class="import-step active" id="step-1">
                <h2>Step 1: Upload CSV File</h2>
                <div class="upload-section">
                    <form id="legacy-csv-upload-form" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('legacy_cert_import_nonce', 'legacy_cert_import_nonce'); ?>
                        <input type="file" name="csv_file" id="legacy_csv_file" accept=".csv,.xlsx,.xls" required>
                        <button type="submit" class="button button-primary">Upload CSV</button>
                    </form>
                    <div id="legacy-csv-upload-status"></div>
                </div>
                
                <div class="csv-preview" id="legacy-csv-preview" style="display:none;">
                    <h3>CSV Preview (First 5 rows)</h3>
                    <div id="legacy-preview-table"></div>
                    <button type="button" class="button" id="legacy-proceed-to-step2">Proceed to Step 2</button>
                </div>
            </div>
            
            <!-- Step 2: Certificate PDF Files Upload -->
            <div class="import-step" id="step-2" style="display:none;">
                <h2>Step 2: Upload Certificate PDF Files</h2>
                <p class="description">
                    Upload a ZIP file containing all certificate PDFs. The system will extract and link them to the imported certificates based on certificate numbers in the filenames.
                </p>
                <div class="upload-section">
                    <form id="legacy-pdf-upload-form" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('legacy_cert_import_nonce', 'legacy_cert_import_nonce'); ?>
                        <div class="file-upload-wrapper">
                            <input type="file" name="certificates_zip" id="legacy_certificates_zip" accept=".zip" required>
                            <label for="legacy_certificates_zip" class="file-upload-label">
                                <span class="dashicons dashicons-upload"></span>
                                <span class="file-upload-text">Choose ZIP file containing certificates</span>
                            </label>
                            <div class="file-upload-info">
                                <small>Maximum file size: 1000MB (1GB). ZIP file should contain PDF files named with certificate numbers.</small>
                            </div>
                        </div>
                        <button type="submit" class="button button-primary">Upload & Extract Certificates</button>
                    </form>
                    <div id="legacy-pdf-upload-status"></div>
                    <div id="legacy-pdf-list" style="display:none; margin-top: 20px;">
                        <h3>Extracted PDF Files:</h3>
                        <div id="legacy-pdf-list-content"></div>
                    </div>
                </div>
                <button type="button" class="button" id="legacy-skip-step2">Skip & Proceed to Import</button>
                <button type="button" class="button button-primary" id="legacy-proceed-to-step3" style="display:none;">Proceed to Import</button>
            </div>
            
            <!-- Step 3: Process Import -->
            <div class="import-step" id="step-3" style="display:none;">
                <h2>Step 3: Process Import</h2>
                <div id="legacy-import-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" id="legacy-progress-fill">0%</div>
                    </div>
                    <div id="legacy-import-status"></div>
                </div>
                <button type="button" class="button button-primary" id="legacy-start-import">Start Import</button>
                <div id="legacy-import-results" style="display:none;"></div>
            </div>
        </div>
        
        <!-- Help Section -->
        <div class="import-help">
            <h3>CSV Format Requirements</h3>
            <p><strong>Required Columns:</strong></p>
            <ul>
                <li>Name (or First Name + Last Name)</li>
                <li>Email</li>
                <li>Candidate Registration Number (optional - will be generated if missing)</li>
            </ul>
            <p><strong>Certificate Columns (can have multiple):</strong></p>
            <ul>
                <li>Method 1, Method 2, Method 3, etc. (the method type like MT, PT, UT, RT, etc.)</li>
                <li>Cert 1 No, Cert 2 No, etc. (certificate numbers)</li>
                <li>ISSUE DAT (issue date - format: DD/MM/YYYY. Expiry date will be automatically calculated as 5 years from issue date)</li>
                <li>Sector 1, Sector 2, etc. (or shared Sector column)</li>
                <li>Level 1, Level 2, etc. (or shared Level column - optional)</li>
                <li>Scope 1, Scope 2, etc. (or shared Scope column - optional)</li>
            </ul>
            <p><strong>Optional Columns:</strong></p>
            <ul>
                <li>Phone/Mobile</li>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * Handle CSV file upload
 */
function handle_legacy_cert_csv_upload() {
    check_ajax_referer('legacy_cert_import_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    if (empty($_FILES['csv_file'])) {
        wp_send_json_error('No file uploaded');
    }
    
    $file = $_FILES['csv_file'];
    
    // Validate file type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = array('csv', 'xlsx', 'xls');
    if (!in_array($file_ext, $allowed_extensions)) {
        wp_send_json_error('Invalid file type. Please upload a CSV or Excel file.');
    }
    
    // Validate file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        wp_send_json_error('File size exceeds 10MB limit.');
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = wp_upload_dir();
    $import_dir = $upload_dir['basedir'] . '/legacy-certificate-imports';
    if (!file_exists($import_dir)) {
        wp_mkdir_p($import_dir);
    }
    
    // Generate unique filename
    $filename = 'legacy_cert_import_' . time() . '_' . sanitize_file_name($file['name']);
    $file_path = $import_dir . '/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        wp_send_json_error('Failed to save uploaded file.');
    }
    
    // Store file path in transient (expires in 1 hour)
    set_transient('legacy_cert_import_csv_' . get_current_user_id(), $file_path, HOUR_IN_SECONDS);
    
    // Parse and preview CSV
    $preview = parse_legacy_cert_csv_preview($file_path, 5);
    
    if (is_wp_error($preview)) {
        wp_send_json_error($preview->get_error_message());
    }
    
    wp_send_json_success(array(
        'message' => 'CSV file uploaded successfully',
        'preview' => $preview,
        'filename' => $filename
    ));
}

/**
 * Parse CSV preview for legacy certificate import
 */
function parse_legacy_cert_csv_preview($file_path, $rows = 5) {
    if (!file_exists($file_path)) {
        return new WP_Error('file_not_found', 'CSV file not found');
    }
    
    $handle = fopen($file_path, 'r');
    if ($handle === false) {
        return new WP_Error('file_open_error', 'Could not open CSV file');
    }
    
    $data = array();
    $headers = array();
    $row_count = 0;
    
    // Read headers
    $headers = fgetcsv($handle);
    if ($headers === false) {
        fclose($handle);
        return new WP_Error('invalid_csv', 'Invalid CSV format - no headers found');
    }
    
    // Clean headers
    $headers = array_map('trim', $headers);
    
    // Read preview rows
    while ($row_count < $rows && ($row = fgetcsv($handle)) !== false) {
        $data[] = $row;
        $row_count++;
    }
    
    fclose($handle);
    
    // Count total rows efficiently
    $total_rows = 0;
    $handle = fopen($file_path, 'r');
    if ($handle !== false) {
        // Skip header
        fgetcsv($handle);
        // Count remaining rows
        while (fgetcsv($handle) !== false) {
            $total_rows++;
        }
        fclose($handle);
    }
    
    return array(
        'headers' => $headers,
        'rows' => $data,
        'total_rows' => $total_rows
    );
}

/**
 * Handle CSV preview request
 */
function handle_legacy_csv_preview() {
    check_ajax_referer('legacy_cert_import_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $file_path = get_transient('legacy_cert_import_csv_' . get_current_user_id());
    if (!$file_path || !file_exists($file_path)) {
        wp_send_json_error('CSV file not found. Please upload again.');
    }
    
    $preview = parse_legacy_cert_csv_preview($file_path, 5);
    
    if (is_wp_error($preview)) {
        wp_send_json_error($preview->get_error_message());
    }
    
    wp_send_json_success($preview);
}

/**
 * Handle ZIP file upload containing certificate PDFs
 */
function handle_legacy_cert_zip_upload() {
    check_ajax_referer('legacy_cert_import_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    if (empty($_FILES['certificates_zip'])) {
        wp_send_json_error('No ZIP file uploaded');
    }
    
    $file = $_FILES['certificates_zip'];
    
    // Validate file type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_ext !== 'zip') {
        wp_send_json_error('Invalid file type. Please upload a ZIP file.');
    }
    
    // Validate file size (max 1000MB = 1GB)
    if ($file['size'] > 1000 * 1024 * 1024) {
        wp_send_json_error('File size exceeds 1000MB (1GB) limit.');
    }
    
    // Check if ZipArchive class exists
    if (!class_exists('ZipArchive')) {
        wp_send_json_error('ZIP extraction not supported on this server. Please contact your hosting provider.');
    }
    
    // Create upload directory for extracted certificates
    $upload_dir = wp_upload_dir();
    $extract_dir = $upload_dir['basedir'] . '/legacy-certificate-pdfs/' . time();
    if (!file_exists($extract_dir)) {
        wp_mkdir_p($extract_dir);
    }
    
    // Open and extract ZIP file
    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true) {
        wp_send_json_error('Could not open ZIP file. File may be corrupted.');
    }
    
    $pdf_list = array();
    $extracted_count = 0;
    
    // Extract only PDF files
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Skip directories and non-PDF files
        if (substr($filename, -1) === '/' || $file_ext !== 'pdf') {
            continue;
        }
        
        // Get just the filename without path
        $basename = basename($filename);
        $dest_path = $extract_dir . '/' . sanitize_file_name($basename);
        
        // Extract file
        $file_content = $zip->getFromIndex($i);
        if ($file_content !== false && file_put_contents($dest_path, $file_content) !== false) {
            $pdf_list[] = array(
                'filename' => $basename,
                'path' => $dest_path,
                'size' => filesize($dest_path),
                'metadata' => extract_pdf_metadata($basename)
            );
            $extracted_count++;
        }
    }
    
    $zip->close();
    
    if (empty($pdf_list)) {
        // Clean up empty directory
        @rmdir($extract_dir);
        wp_send_json_error('No PDF files found in the ZIP archive.');
    }
    
    // Store PDF list in transient
    set_transient('legacy_cert_import_pdfs_' . get_current_user_id(), $pdf_list, HOUR_IN_SECONDS);
    
    wp_send_json_success(array(
        'message' => $extracted_count . ' PDF file(s) extracted successfully',
        'pdfs' => $pdf_list,
        'extract_dir' => $extract_dir
    ));
}

/**
 * Extract metadata from PDF filename
 */
function extract_pdf_metadata($filename) {
    $metadata = array(
        'candidate_reg' => '',
        'name' => '',
        'issue_number' => '',
        'certificate_number' => '',
        'method' => ''
    );
    
    // Extract candidate registration number (A#### pattern)
    if (preg_match('/A\d{4}/', $filename, $matches)) {
        $metadata['candidate_reg'] = $matches[0];
    }
    
    // Extract issue number (Iss.1, Iss.2, Iss3, etc.)
    if (preg_match('/Iss\.?(\d+)/i', $filename, $matches)) {
        $metadata['issue_number'] = $matches[1];
    }
    
    // Extract name (between candidate reg and issue number, or after "Certification -")
    // Pattern: "Certification - A0004 Iss.2 Kim Kerh Chay.pdf"
    // Pattern: "Cert.R5 - A0016 - Ramarajan KASI_Iss3 Pg.1.pdf"
    if (preg_match('/(?:Certification\s*-\s*[A-Z]\d{4}\s*Iss\.?\d+\s*|Cert\.R\d+\s*-\s*[A-Z]\d{4}\s*-\s*|Certification\s*-\s*[A-Z]\d{4}\s*)([A-Z][A-Za-z\s]+?)(?:_|Iss|Pg|\.pdf)/i', $filename, $matches)) {
        $metadata['name'] = trim($matches[1]);
    }
    
    // Try alternative pattern for name extraction
    if (empty($metadata['name'])) {
        // Remove common prefixes and suffixes
        $clean_name = preg_replace('/^(Certification|Cert\.R\d+)\s*-\s*/i', '', $filename);
        $clean_name = preg_replace('/\s*Iss\.?\d+.*$/i', '', $clean_name);
        $clean_name = preg_replace('/\s*Pg\.\d+.*$/i', '', $clean_name);
        $clean_name = preg_replace('/\s*[A-Z]\d{4}\s*/', ' ', $clean_name);
        $clean_name = preg_replace('/\.pdf$/i', '', $clean_name);
        $clean_name = trim($clean_name);
        
        if (!empty($clean_name) && strlen($clean_name) > 2) {
            $metadata['name'] = $clean_name;
        }
    }
    
    return $metadata;
}

/**
 * Main import handler
 */
function handle_legacy_cert_csv_import() {
    check_ajax_referer('legacy_cert_import_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $file_path = get_transient('legacy_cert_import_csv_' . get_current_user_id());
    if (!$file_path || !file_exists($file_path)) {
        wp_send_json_error('CSV file not found. Please upload again.');
    }
    
    $pdf_list = get_transient('legacy_cert_import_pdfs_' . get_current_user_id());
    if (!is_array($pdf_list)) {
        $pdf_list = array();
    }
    
    // Process import
    $result = process_legacy_certificate_import($file_path, $pdf_list);
    
    // Clean up transients
    delete_transient('legacy_cert_import_csv_' . get_current_user_id());
    delete_transient('legacy_cert_import_pdfs_' . get_current_user_id());
    
    wp_send_json_success($result);
}

/**
 * Process legacy certificate import
 */
function process_legacy_certificate_import($csv_path, $pdf_list = array()) {
    global $wpdb;
    
    $handle = fopen($csv_path, 'r');
    if ($handle === false) {
        return array(
            'success' => false,
            'message' => 'Could not open CSV file'
        );
    }
    
    // Read headers
    $headers_raw = fgetcsv($handle);
    if ($headers_raw === false) {
        fclose($handle);
        return array(
            'success' => false,
            'message' => 'Invalid CSV format'
        );
    }
    
    $headers_raw = array_map('trim', $headers_raw);
    $headers = array_map('strtolower', $headers_raw);
    
    // Detect certificate columns (pass both lowercase and original headers)
    $certificate_columns = detect_certificate_columns($headers, $headers_raw);
    
    // Map CSV columns
    $column_map = map_legacy_csv_columns($headers);
    
    // Debug: Log detected columns for first row only
    if (!isset($results['debug_columns_logged'])) {
        error_log("Legacy Cert Import - Detected Headers: " . implode(', ', $headers_raw));
        error_log("Legacy Cert Import - Column Map: " . json_encode($column_map));
        error_log("Legacy Cert Import - Certificate Columns: " . json_encode($certificate_columns));
        $results['debug_columns_logged'] = true;
    }
    
    // Add debug info about detected columns
    $results = array(
        'users_created' => 0,
        'users_updated' => 0,
        'certificates_created' => 0,
        'certificates_skipped' => 0,
        'files_linked' => 0,
        'errors' => array(),
        'warnings' => array(),
        'debug_info' => array(
            'total_headers' => count($headers),
            'detected_certificate_groups' => count($certificate_columns['numbered']),
            'sample_headers' => array_slice($headers_raw, 0, 10)
        )
    );
    
    // Add detailed info about detected columns
    if (empty($certificate_columns['numbered'])) {
        $results['warnings'][] = "No numbered certificate columns detected. Looking for patterns like 'Method 1', 'Cert 1 No', 'ISSUE DAT', etc. Found headers: " . implode(', ', array_slice($headers_raw, 0, 20));
    } else {
        // Show what was detected
        $detected_summary = array();
        foreach ($certificate_columns['numbered'] as $num => $fields) {
            $detected_summary[] = "Group $num: " . implode(', ', array_keys($fields));
        }
        $results['warnings'][] = "Detected certificate column groups: " . implode(' | ', array_slice($detected_summary, 0, 5));
    }
    
    $row_number = 1;
    
    // Process each row
    while (($row = fgetcsv($handle)) !== false) {
        $row_number++;
        
        // Combine headers with row data
        $row_data = array();
        foreach ($headers_raw as $index => $header) {
            // Normalize header keys: lowercase, trim, collapse whitespace to single underscores
            $normalized_key = preg_replace('/\s+/', '_', strtolower(trim($header)));
            $row_data[$normalized_key] = isset($row[$index]) ? $row[$index] : '';
        }
        
        // Check if any certificate columns were detected (do this once, not per row)
        if (empty($certificate_columns['numbered']) && $row_number == 2) {
            // Only show this error once for the first data row
            $detected_info = "Detected headers: " . implode(', ', array_slice($headers_raw, 0, 20));
            $detected_info .= " | Certificate groups found: " . count($certificate_columns['numbered']);
            if (!empty($certificate_columns['numbered'])) {
                $detected_info .= " | Sample groups: " . json_encode(array_keys($certificate_columns['numbered']));
            }
            $results['errors'][] = "Row $row_number: No certificate columns detected. " . $detected_info;
        }
        
        // Get or create user
        $user_result = create_or_get_legacy_certificate_user($row_data, $column_map);
        if (is_wp_error($user_result)) {
            $error_msg = $user_result->get_error_message();
            // Add more context to the error - show what was detected
            $debug_info = array();
            if (isset($column_map['name'])) $debug_info[] = "Name: " . $column_map['name'];
            if (isset($column_map['first_name'])) $debug_info[] = "First Name: " . $column_map['first_name'];
            if (isset($column_map['last_name'])) $debug_info[] = "Last Name: " . $column_map['last_name'];
            if (isset($column_map['email'])) $debug_info[] = "Email: " . $column_map['email'];
            if (isset($column_map['candidate_reg_number'])) $debug_info[] = "Reg No: " . $column_map['candidate_reg_number'];
            
            if (!empty($debug_info)) {
                $error_msg .= " | Detected: " . implode(', ', $debug_info);
            } else {
                $error_msg .= " | No matching columns found. Available columns: " . implode(', ', array_slice($headers_raw, 0, 10));
            }
            $results['errors'][] = "Row $row_number: " . $error_msg;
            $results['certificates_skipped']++;
            continue;
        }
        
        $user_id = $user_result['user_id'];
        if ($user_result['created']) {
            $results['users_created']++;
        } else {
            $results['users_updated']++;
        }
        
        // Skip certificate processing if no columns detected
        if (empty($certificate_columns['numbered'])) {
            $results['warnings'][] = "Row $row_number: User processed but no certificate columns detected to process certificates.";
            continue;
        }
    
    // Process multiple certificates for this user
    $cert_result = process_multiple_certificates_for_user($user_id, $row_data, $certificate_columns, $column_map, $pdf_list);
    
    if (is_wp_error($cert_result)) {
        $results['errors'][] = "Row $row_number (User ID: $user_id): " . $cert_result->get_error_message();
        $results['certificates_skipped']++;
        continue;
    }
    
    $results['certificates_created'] += $cert_result['count'];
    $results['files_linked'] += $cert_result['files_linked'];
    
    if (!empty($cert_result['warnings'])) {
        $results['warnings'] = array_merge($results['warnings'], $cert_result['warnings']);
    }
    
    // If no certificates were created but columns were detected, add a warning
    if ($cert_result['count'] == 0 && !empty($certificate_columns['numbered'])) {
        $total_cert_groups = count($certificate_columns['numbered']);
        $results['warnings'][] = "Row $row_number: User processed but no certificates were created. " .
            "Detected $total_cert_groups certificate column groups, but all were empty or missing required data.";
    }
    }
    
    fclose($handle);
    
    return $results;
}

/**
 * Detect certificate columns (Method1, Method2, etc.)
 */
function detect_certificate_columns($headers, $headers_raw = null) {
    // If headers_raw not provided, use headers (assume they're already lowercase)
    if ($headers_raw === null) {
        $headers_raw = $headers;
    }
    
    $certificate_columns = array();
    
    // Track ISSUE DAT columns to determine which is issue and which is expiry
    $issue_date_columns = array();
    
    // More flexible patterns to match numbered columns
    // Handles: Method 1, Method1, Method_1, Cert 1 No, Cert1No, etc.
    foreach ($headers as $index => $header) {
        $header_lower = strtolower(trim($header));
        $header_clean = preg_replace('/[\s_\-]+/', ' ', $header_lower);
        
        // Pattern 1: Method columns (Method 1, Method1, etc.)
        if (preg_match('/^method[\s_\-]*(\d+)$/i', $header_clean, $matches)) {
            $number = intval($matches[1]);
            if (!isset($certificate_columns[$number])) {
                $certificate_columns[$number] = array();
            }
            $certificate_columns[$number]['method'] = $header;
        }
        // Pattern 2: Certificate number columns (Cert 1 No, Cert1No, Cert_Number1, etc.)
        elseif (preg_match('/(cert|certificate)[\s_\-]*(\d+)[\s_\-]*(no|number)/i', $header_clean, $matches)) {
            $number = intval($matches[2]);
            if (!isset($certificate_columns[$number])) {
                $certificate_columns[$number] = array();
            }
            $certificate_columns[$number]['certnumber'] = $header;
        }
        // Pattern 3: Issue Date columns (ISSUE DAT, Issue Date 1, etc.)
        elseif (preg_match('/issue[\s_\-]*dat/i', $header_clean) || preg_match('/issue[\s_\-]*date[\s_\-]*(\d+)/i', $header_clean, $matches)) {
            // Track issue date columns with their position
            if (isset($matches[1])) {
                $number = intval($matches[1]);
            } else {
                // No number in header, need to infer from position
                $number = null;
            }
            $issue_date_columns[] = array(
                'header' => $header,
                'index' => $index,
                'number' => $number
            );
        }
        // Pattern 4: Expiry Date columns (Expiry Date 1, Expiration Date 1, etc.)
        elseif (preg_match('/(expir|expiration)[\s_\-]*date[\s_\-]*(\d+)/i', $header_clean, $matches)) {
            $number = intval($matches[2]);
            if (!isset($certificate_columns[$number])) {
                $certificate_columns[$number] = array();
            }
            $certificate_columns[$number]['expirydate'] = $header;
        }
        // Pattern 5: Sector columns (Sector 1, Sector1, etc.)
        elseif (preg_match('/^sector[\s_\-]*(\d+)$/i', $header_clean, $matches)) {
            $number = intval($matches[1]);
            if (!isset($certificate_columns[$number])) {
                $certificate_columns[$number] = array();
            }
            $certificate_columns[$number]['sector'] = $header;
        }
        // Pattern 6: Level columns (Level 1, Level1, etc.)
        elseif (preg_match('/^level[\s_\-]*(\d+)$/i', $header_clean, $matches)) {
            $number = intval($matches[1]);
            if (!isset($certificate_columns[$number])) {
                $certificate_columns[$number] = array();
            }
            $certificate_columns[$number]['level'] = $header;
        }
        // Pattern 7: Scope columns (Scope 1, Scope1, etc.)
        elseif (preg_match('/^scope[\s_\-]*(\d+)$/i', $header_clean, $matches)) {
            $number = intval($matches[1]);
            if (!isset($certificate_columns[$number])) {
                $certificate_columns[$number] = array();
            }
            $certificate_columns[$number]['scope'] = $header;
        }
    }
    
    // Process ISSUE DAT columns - they appear in pairs (issue, expiry) after each Method
    // Pattern: Method 1, ISSUE DAT (issue), ISSUE DAT (expiry), Sector 1, Cert 1 No, Level 1
    // Group ISSUE DAT columns by finding the Method column before them
    foreach ($issue_date_columns as $issue_idx => $issue_col) {
        $col_index = $issue_col['index'];
        $assigned = false;
        
        // Look backwards to find the nearest Method column
        $found_method_number = null;
        $found_method_index = -1;
        for ($i = $col_index - 1; $i >= 0 && $i >= $col_index - 10; $i--) {
            if (isset($headers[$i])) {
                $prev_header = strtolower(trim($headers[$i]));
                $prev_clean = preg_replace('/[\s_\-]+/', ' ', $prev_header);
                if (preg_match('/^method[\s_\-]*(\d+)$/i', $prev_clean, $matches)) {
                    $found_method_number = intval($matches[1]);
                    $found_method_index = $i;
                    break;
                }
            }
        }
        
        if ($found_method_number !== null) {
            if (!isset($certificate_columns[$found_method_number])) {
                $certificate_columns[$found_method_number] = array();
            }
            
            // Count how many ISSUE DAT columns we've already assigned to this method
            // Simply count how many we've stored for this method number
            $issue_count = 0;
            if (isset($certificate_columns[$found_method_number]['issuedate'])) {
                $issue_count++;
            }
            if (isset($certificate_columns[$found_method_number]['expirydate'])) {
                $issue_count++;
            }
            
            // Also count ISSUE DATs that appear between the Method and current position
            // that haven't been assigned yet but belong to this method
            for ($check_idx = $found_method_index + 1; $check_idx < $col_index; $check_idx++) {
                if (isset($headers[$check_idx])) {
                    $check_header_lower = strtolower(trim($headers[$check_idx]));
                    $check_header_clean = preg_replace('/[\s_\-]+/', ' ', $check_header_lower);
                    // Check if this is an ISSUE DAT column
                    if (preg_match('/issue[\s_\-]*dat/i', $check_header_clean)) {
                        // Check if it's already been assigned to this method
                        $already_assigned = false;
                        if (isset($certificate_columns[$found_method_number]['issuedate']) && 
                            $certificate_columns[$found_method_number]['issuedate'] === $headers[$check_idx]) {
                            $already_assigned = true;
                        }
                        if (isset($certificate_columns[$found_method_number]['expirydate']) && 
                            $certificate_columns[$found_method_number]['expirydate'] === $headers[$check_idx]) {
                            $already_assigned = true;
                        }
                        if (!$already_assigned) {
                            $issue_count++;
                        }
                    }
                }
            }
            
            // First ISSUE DAT after Method is issue date, second is expiry date
            if ($issue_count == 0) {
                $certificate_columns[$found_method_number]['issuedate'] = $issue_col['header'];
            } elseif ($issue_count == 1) {
                $certificate_columns[$found_method_number]['expirydate'] = $issue_col['header'];
            }
            $assigned = true;
        }
        
        // Fallback: if not assigned, try to find by looking for Cert X No after it
        if (!$assigned) {
            for ($i = $col_index + 1; $i < count($headers) && $i <= $col_index + 5; $i++) {
                if (isset($headers[$i])) {
                    $next_header = strtolower(trim($headers[$i]));
                    $next_clean = preg_replace('/[\s_\-]+/', ' ', $next_header);
                    if (preg_match('/(cert|certificate)[\s_\-]*(\d+)[\s_\-]*(no|number)/i', $next_clean, $matches)) {
                        $number = intval($matches[2]);
                        if (!isset($certificate_columns[$number])) {
                            $certificate_columns[$number] = array();
                        }
                        // Count existing issue/expiry dates for this number
                        $issue_count = 0;
                        if (isset($certificate_columns[$number]['issuedate'])) $issue_count++;
                        if (isset($certificate_columns[$number]['expirydate'])) $issue_count++;
                        
                        // Assign based on count - first is issue, second is expiry
                        if ($issue_count == 0) {
                            $certificate_columns[$number]['issuedate'] = $issue_col['header'];
                        } elseif ($issue_count == 1) {
                            $certificate_columns[$number]['expirydate'] = $issue_col['header'];
                        }
                        $assigned = true;
                        break;
                    }
                }
            }
        }
        
        // If still not assigned and we have ISSUE DAT columns, try to assign based on order
        // This handles cases where the pattern isn't clear
        if (!$assigned && !empty($issue_date_columns)) {
            // Find the index of this issue column in the array
            $current_issue_idx = null;
            foreach ($issue_date_columns as $idx => $col) {
                if ($col['header'] === $issue_col['header'] && $col['index'] === $issue_col['index']) {
                    $current_issue_idx = $idx;
                    break;
                }
            }
            
            // Try to find nearby Method columns to determine which group this belongs to
            if ($current_issue_idx !== null) {
                // Look for the nearest Method column before this ISSUE DAT
                for ($i = $col_index - 1; $i >= 0 && $i >= $col_index - 15; $i--) {
                    if (isset($headers[$i])) {
                        $prev_header = strtolower(trim($headers[$i]));
                        $prev_clean = preg_replace('/[\s_\-]+/', ' ', $prev_header);
                        if (preg_match('/^method[\s_\-]*(\d+)$/i', $prev_clean, $matches)) {
                            $number = intval($matches[1]);
                            if (!isset($certificate_columns[$number])) {
                                $certificate_columns[$number] = array();
                            }
                            
                            // Count how many ISSUE DATs we've seen for this method so far
                            $issue_count = 0;
                            foreach ($issue_date_columns as $idx => $col) {
                                if ($idx < $current_issue_idx && isset($certificate_columns[$number])) {
                                    if (isset($certificate_columns[$number]['issuedate']) && 
                                        $certificate_columns[$number]['issuedate'] === $col['header']) {
                                        $issue_count++;
                                    }
                                    if (isset($certificate_columns[$number]['expirydate']) && 
                                        $certificate_columns[$number]['expirydate'] === $col['header']) {
                                        $issue_count++;
                                    }
                                }
                            }
                            
                            if ($issue_count == 0) {
                                $certificate_columns[$number]['issuedate'] = $issue_col['header'];
                            } elseif ($issue_count == 1) {
                                $certificate_columns[$number]['expirydate'] = $issue_col['header'];
                            }
                            $assigned = true;
                            break;
                        }
                    }
                }
            }
        }
    }
    
    // Also check for shared columns (Level, Sector, Scope without numbers)
    $shared_columns = array();
    foreach ($headers as $header) {
        $header_lower = strtolower(trim($header));
        $header_clean = preg_replace('/[\s_\-]+/', ' ', $header_lower);
        // Check for exact matches
        if ($header_clean === 'level') {
            $shared_columns['level'] = $header;
        } elseif ($header_clean === 'sector') {
            $shared_columns['sector'] = $header;
        } elseif ($header_clean === 'scope') {
            $shared_columns['scope'] = $header;
        }
    }
    
    return array(
        'numbered' => $certificate_columns,
        'shared' => $shared_columns,
        'all_headers' => $headers_raw // Include original headers for matching
    );
}

/**
 * Map CSV columns to expected fields
 */
function map_legacy_csv_columns($headers) {
    $map = array();
    
    // Define possible column name variations
    $field_variations = array(
        'email' => array('email', 'e-mail', 'email address', 'user_email'),
        'first_name' => array('first name', 'firstname', 'fname', 'given name', 'candidate_name', 'candidate name'),
        'last_name' => array('last name', 'lastname', 'lname', 'surname', 'family name', 'candidate_name', 'candidate name'),
        // Use candidate_name as the primary \"name\" field too
        'name' => array('name', 'full name', 'fullname', 'candidate', 'candidate_name', 'candidate name'),
        'candidate_reg_number' => array('candidate registration number', 'sgndt number', 'registration number', 'reg number', 'candidate reg', 'sgndt', 'candidate_reg_number', 'candidate_reg', 'reg no', 'regno'),
        'phone' => array('phone', 'mobile', 'phone number', 'mobile number', 'contact number')
    );
    
    foreach ($field_variations as $field => $variations) {
        foreach ($headers as $index => $header) {
            if (in_array(strtolower(trim($header)), $variations)) {
                $map[$field] = $header;
                break;
            }
        }
    }
    
    return $map;
}

/**
 * Create or get user for legacy certificate
 */
function create_or_get_legacy_certificate_user($row_data, $column_map) {
    global $wpdb;
   
    // Get email
    $email_header = isset($column_map['email']) ? $column_map['email'] : '';
    $email = isset($row_data[$email_header]) ? sanitize_email(trim($row_data[$email_header])) : '';
    
    if (empty($email) || !is_email($email)) {
        return new WP_Error('invalid_email', 'Invalid or missing email address');
    }
    
    // Use candidate name as both first and last name (if provided)
    $candidate_name = '';
    if (isset($column_map['name'])) {
        $name_header = $column_map['name'];
        $candidate_name = isset($row_data['candidate_name']) ? trim($row_data['candidate_name']) : '';
    }
    
    // Check if user exists
    $user = get_user_by('email', $email);
    
    if ($user) {
        // User exists - update if needed
        $user_id = $user->ID;
       // echo $candidate_name;
      
       
        if (!empty($candidate_name)) {
            error_log('Updating user ' . $user_id . ' with candidate_name: ' . $candidate_name);
            update_user_meta($user_id, 'first_name', $candidate_name);
            update_user_meta($user_id, 'last_name', '');
            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => $candidate_name,
                'last_name' => '',
                'display_name' => $candidate_name,
                'user_nicename' => sanitize_title($candidate_name)
            ));
            // Also update given_name and candidate_name meta fields
            update_user_meta($user_id, 'given_name', $candidate_name);
            update_user_meta($user_id, 'candidate_name', $candidate_name);
          //  echo "Updated user " . $user_id . " with candidate_name: " . $candidate_name;
           // die;
        } else {
            error_log('NO candidate_name found for user: ' . $user_id . ', email: ' . $email);
        }
        
        // Update phone if provided
        if (isset($column_map['phone'])) {
            $phone_header = $column_map['phone'];
            $phone = isset($row_data[$phone_header]) ? trim($row_data[$phone_header]) : '';
            if (!empty($phone)) {
                update_user_meta($user_id, 'mobile', $phone);
            }
        }
        
        // Update candidate registration number if provided
        if (isset($column_map['candidate_reg_number'])) {
            $reg_header = $column_map['candidate_reg_number'];
            $reg_number = isset($row_data[$reg_header]) ? trim($row_data[$reg_header]) : '';
            if (!empty($reg_number)) {
                update_user_meta($user_id, 'candidate_reg_number', $reg_number);
            }
        }
        
        return array(
            'user_id' => $user_id,
            'created' => false
        );
    }
    
    // Create new user
    $first_name = $candidate_name;
    $last_name = $candidate_name;
    
    // Generate username from candidate name if provided, else from email
    if (!empty($candidate_name)) {
        $username = sanitize_user($candidate_name);
        if (empty($username)) {
            $username = sanitize_user(current(explode('@', $email)));
        }
    } else {
        $username = sanitize_user(current(explode('@', $email)));
    }
    
    // Ensure username is unique
    $original_username = $username;
    $counter = 1;
    while (username_exists($username)) {
        $username = $original_username . $counter;
        $counter++;
    }
    
    // Generate random password
    $password = wp_generate_password(12, false);
    
    // Create user
    $user_data = array(
        'user_login'    => $username,
        'user_email'    => $email,
        'user_pass'     => $password,
        'first_name'    => $first_name,
        'last_name'     => '',
        'display_name'  => trim($first_name . ' ' . ''),
        'user_nicename' => sanitize_title($first_name), // based on candidate_name
        'role'          => 'student'
    );
    
    $user_id = wp_insert_user($user_data);
    
    if (is_wp_error($user_id)) {
        return $user_id;
    }
    
    // Generate or set candidate registration number
    if (isset($column_map['candidate_reg_number'])) {
        $reg_header = $column_map['candidate_reg_number'];
        $reg_number = isset($row_data[$reg_header]) ? trim($row_data[$reg_header]) : '';
        if (!empty($reg_number)) {
            update_user_meta($user_id, 'candidate_reg_number', $reg_number);
        } else {
            // Generate if not provided
            if (function_exists('generate_candidate_reg_number')) {
                generate_candidate_reg_number($user_id);
            }
        }
    } else {
        // Generate candidate registration number
        if (function_exists('generate_candidate_reg_number')) {
            generate_candidate_reg_number($user_id);
        }
    }
    
    // Set phone if provided
    if (isset($column_map['phone'])) {
        $phone_header = $column_map['phone'];
        $phone = isset($row_data[$phone_header]) ? trim($row_data[$phone_header]) : '';
        if (!empty($phone)) {
            update_user_meta($user_id, 'mobile', $phone);
        }
    }
    
    return array(
        'user_id' => $user_id,
        'created' => true
    );
}

/**
 * Process multiple certificates for a user
 * Flexible system that handles 1-5 certificates per user
 */
function process_multiple_certificates_for_user($user_id, $row_data, $certificate_columns, $column_map, $pdf_list = array()) {
    $results = array(
        'count' => 0,
        'files_linked' => 0,
        'warnings' => array()
    );
    
    $numbered_columns = $certificate_columns['numbered'];
    $shared_columns = $certificate_columns['shared'];
    $all_headers = isset($certificate_columns['all_headers']) ? $certificate_columns['all_headers'] : array();
    
    // Get user's candidate registration number
    $candidate_reg_number = get_user_meta($user_id, 'candidate_reg_number', true);
    
    // Helper function to find value in row_data by trying multiple key variations
    $find_in_row_data = function($header_name) use ($row_data) {
        // Normalize: trim, lowercase, normalize spaces (multiple spaces to single)
        $normalized = preg_replace('/\s+/', ' ', strtolower(trim($header_name)));
        
        // Try exact match first
        if (isset($row_data[$normalized])) {
            return $row_data[$normalized];
        }
        
        // Try variations
        $variations = array(
            $normalized,
            trim($normalized),
            str_replace(' ', '', $normalized),
            str_replace(' ', '_', $normalized),
            str_replace(' ', '-', $normalized),
        );
        
        foreach ($variations as $var) {
            if (isset($row_data[$var])) {
                return $row_data[$var];
            }
        }
        
        // Last resort: search all keys for partial match
        foreach ($row_data as $key => $value) {
            $key_normalized = preg_replace('/\s+/', ' ', strtolower(trim($key)));
            if ($key_normalized === $normalized || 
                (strlen($normalized) > 5 && strpos($key_normalized, $normalized) !== false) ||
                (strlen($key_normalized) > 5 && strpos($normalized, $key_normalized) !== false)) {
                return $value;
            }
        }
        
        return null;
    };
    
    // STEP 1: Collect all issue dates in order from the CSV
    $issue_dates_ordered = array();
    if (!empty($all_headers)) {
        // Use original headers to maintain column order
        foreach ($all_headers as $header) {
            $header_key = strtolower(trim($header));
            $header_clean = preg_replace('/\s+/', ' ', $header_key);
            
            // Check if this is an issue date column
            if ((strpos($header_clean, 'issue') !== false && (strpos($header_clean, 'date') !== false || strpos($header_clean, 'dat') !== false)) &&
                isset($row_data[$header_key]) && !empty(trim($row_data[$header_key]))) {
                $issue_dates_ordered[] = trim($row_data[$header_key]);
            }
        }
    } else {
        // Fallback: search row_data keys (less reliable for order)
        foreach ($row_data as $key => $value) {
            $key_lower = strtolower(trim($key));
            if ((strpos($key_lower, 'issue') !== false && (strpos($key_lower, 'date') !== false || strpos($key_lower, 'dat') !== false)) &&
                !empty(trim($value))) {
                $issue_dates_ordered[] = trim($value);
            }
        }
    }
    
    // STEP 2: Process each certificate group (1-5) and match with issue dates by position
    $certificate_counter = 0; // Track actual certificates with data (0-based for issue_dates_ordered)
    
    foreach ($numbered_columns as $cert_index => $cert_fields) {
        // Extract certificate data from this certificate group
        $method = '';
        $cert_number = '';
        $level = '';
        $sector = '';
        $scope = '';
        
        // Find method field
        foreach ($cert_fields as $field_type => $header_name) {
            if (strpos($field_type, 'method') !== false) {
                $value = $find_in_row_data($header_name);
                if ($value !== null && !empty(trim($value))) {
                    $method = trim($value);
                }
            }
            // Find certificate number field
            if ($field_type === 'certnumber' || strpos($field_type, 'cert') !== false || strpos($field_type, 'number') !== false) {
                $value = $find_in_row_data($header_name);
                if ($value !== null && !empty(trim($value))) {
                    $cert_number = trim($value);
                }
            }
            // Find level, sector, scope
            if (strpos($field_type, 'level') !== false) {
                $value = $find_in_row_data($header_name);
                if ($value !== null && !empty(trim($value))) {
                    $level = trim($value);
                }
            }
            if (strpos($field_type, 'sector') !== false) {
                $value = $find_in_row_data($header_name);
                if ($value !== null && !empty(trim($value))) {
                    $sector = trim($value);
                }
            }
            if (strpos($field_type, 'scope') !== false) {
                $value = $find_in_row_data($header_name);
                if ($value !== null && !empty(trim($value))) {
                    $scope = trim($value);
                }
            }
        }
        
        // Check shared columns if not found in numbered columns
        if (empty($level) && !empty($shared_columns['level'])) {
            $value = $find_in_row_data($shared_columns['level']);
            if ($value !== null && !empty(trim($value))) {
                $level = trim($value);
            }
        }
        if (empty($sector) && !empty($shared_columns['sector'])) {
            $value = $find_in_row_data($shared_columns['sector']);
            if ($value !== null && !empty(trim($value))) {
                $sector = trim($value);
            }
        }
        if (empty($scope) && !empty($shared_columns['scope'])) {
            $value = $find_in_row_data($shared_columns['scope']);
            if ($value !== null && !empty(trim($value))) {
                $scope = trim($value);
            }
        }
        
        // Check if this certificate group has any data (method or cert_number)
        // If both are empty, this is an empty certificate slot - skip silently
        if (empty($method) && empty($cert_number)) {
            // This is an empty certificate slot (e.g., user has 3 certificates but CSV has columns for 5)
            // Skip silently - no warning needed
            continue;
        }
        
        // Match issue date by position: first certificate with data gets first issue date, second gets second, etc.
        $issue_date = '';
        $expiry_date = '';
        
        if (!empty($issue_dates_ordered)) {
            // Use the certificate_counter to get the corresponding issue date
            if (isset($issue_dates_ordered[$certificate_counter])) {
                $issue_date = $issue_dates_ordered[$certificate_counter];
            } elseif (count($issue_dates_ordered) == 1) {
                // If there's only one issue date, use it for all certificates
                $issue_date = $issue_dates_ordered[0];
            }
        }
        
        // If still no issue date, try to find it from cert_fields (fallback)
        if (empty($issue_date)) {
            foreach ($cert_fields as $field_type => $header_name) {
                if ($field_type === 'issuedate' || (strpos($field_type, 'issue') !== false && strpos($field_type, 'date') !== false)) {
                    $value = $find_in_row_data($header_name);
                    if ($value !== null && !empty(trim($value))) {
                        $issue_date = trim($value);
                        break;
                    }
                }
            }
        }
        
        // Validate required fields
        // Note: Expiry date is not required - will be calculated automatically
        $missing_fields = array();
        if (empty($method)) $missing_fields[] = 'Method';
        if (empty($cert_number)) $missing_fields[] = 'Certificate Number';
        if (empty($issue_date)) $missing_fields[] = 'Issue Date';
        
        if (!empty($missing_fields)) {
            // Show warning with helpful info
            $debug_info = array();
            if (!empty($method)) $debug_info[] = "Method: '$method'";
            if (!empty($cert_number)) $debug_info[] = "Cert: '$cert_number'";
            if (!empty($issue_date)) $debug_info[] = "Issue: '$issue_date'";
            
            $results['warnings'][] = "Certificate $cert_index skipped: Missing " . implode(', ', $missing_fields) . 
                " | Found: " . (empty($debug_info) ? 'none' : implode('; ', $debug_info)) .
                " | Issue dates available: " . count($issue_dates_ordered) .
                " | Certificate position: " . ($certificate_counter + 1);
            continue;
        }
        
        // Calculate expiry date automatically if not provided (5 years from issue date)
        if (empty($expiry_date) && !empty($issue_date)) {
            $issue_date_parsed = parse_legacy_date($issue_date);
            if ($issue_date_parsed) {
                // Calculate 5 years from issue date
                $expiry_date_obj = new DateTime($issue_date_parsed);
                $expiry_date_obj->modify('+5 years');
                $expiry_date = $expiry_date_obj->format('Y-m-d');
            }
        }
        
        // Create certificate record
        $cert_result = create_legacy_certificate_record($user_id, $cert_number, $method, $level, $sector, $scope, $issue_date, $expiry_date, $pdf_list, $candidate_reg_number, $cert_index);
        
        // Increment certificate counter for next certificate with data
        $certificate_counter++;
        
        if (is_wp_error($cert_result)) {
            $results['warnings'][] = "Certificate $cert_index: " . $cert_result->get_error_message();
            continue;
        }
        
        $results['count']++;
        if ($cert_result['file_linked']) {
            $results['files_linked']++;
        }
    }
    
    return $results;
}

/**
 * Create legacy certificate record
 */
function create_legacy_certificate_record($user_id, $certificate_number, $method, $level, $sector, $scope, $issue_date, $expiry_date, $pdf_list = array(), $candidate_reg_number = '', $cert_index = 0) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'sgndt_final_certifications';
    
    // Check for duplicate certificate number for this user
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND certificate_number = %s",
        $user_id, $certificate_number
    ));
    
    if ($existing > 0) {
        return new WP_Error('duplicate_certificate', "Certificate number $certificate_number already exists for this user");
    }
    
    // Parse issue date
    $issue_date_parsed = parse_legacy_date($issue_date);
    
    if (!$issue_date_parsed) {
        return new WP_Error('invalid_date', 'Invalid issue date format');
    }
    
    // Parse expiry date if provided, otherwise calculate it (5 years from issue date)
    if (!empty($expiry_date)) {
        $expiry_date_parsed = parse_legacy_date($expiry_date);
        if (!$expiry_date_parsed) {
            return new WP_Error('invalid_date', 'Invalid expiry date format');
        }
    } else {
        // Calculate expiry date as 5 years from issue date
        $expiry_date_obj = new DateTime($issue_date_parsed);
        $expiry_date_obj->modify('+5 years');
        $expiry_date_parsed = $expiry_date_obj->format('Y-m-d');
    }
    
    // Determine status
    $status = 'issued';
    $expiry_timestamp = strtotime($expiry_date_parsed);
    if ($expiry_timestamp < time()) {
        $status = 'expired';
    }
    
    // Find and link PDF file
    $certificate_link = '';
    $file_linked = false;
    
    if (!empty($pdf_list)) {
        $pdf_match = match_pdf_to_certificate($certificate_number, $candidate_reg_number, $user_id, $pdf_list, $method);
        if ($pdf_match) {
            $certificate_link = $pdf_match;
            $file_linked = true;
        }
    }
    
    // Insert certificate record
    $insert_data = array(
        'user_id' => $user_id,
        'certificate_number' => sanitize_text_field($certificate_number),
        'issue_date' => $issue_date_parsed,
        'expiry_date' => $expiry_date_parsed,
        'status' => $status,
        'method' => sanitize_text_field($method),
        'level' => sanitize_text_field($level),
        'sector' => sanitize_text_field($sector),
        'scope' => sanitize_text_field($scope),
        'certificate_link' => $certificate_link,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    );
    
    $result = $wpdb->insert($table_name, $insert_data);
    
    if ($result === false) {
        return new WP_Error('db_error', 'Failed to insert certificate record: ' . $wpdb->last_error);
    }
    
    return array(
        'certificate_id' => $wpdb->insert_id,
        'file_linked' => $file_linked
    );
}

/**
 * Match PDF file to certificate
 */
function match_pdf_to_certificate($certificate_number, $candidate_reg_number, $user_id, $pdf_list, $method = '') {
    $user = get_userdata($user_id);
    $user_name = '';
    if ($user) {
        $user_name = strtolower(trim($user->display_name));
    }
    
    $best_match = null;
    $best_score = 0;
    
    foreach ($pdf_list as $pdf) {
        $metadata = isset($pdf['metadata']) ? $pdf['metadata'] : extract_pdf_metadata($pdf['filename']);
        $score = 0;
        
        // Match by candidate registration number (highest priority)
        if (!empty($candidate_reg_number) && !empty($metadata['candidate_reg'])) {
            if (strtoupper($candidate_reg_number) === strtoupper($metadata['candidate_reg'])) {
                $score += 100;
            }
        }
        
        // Match by certificate number in filename
        if (strpos(strtolower($pdf['filename']), strtolower($certificate_number)) !== false) {
            $score += 50;
        }
        
        // Match by user name
        if (!empty($user_name) && !empty($metadata['name'])) {
            $pdf_name = strtolower(trim($metadata['name']));
            similar_text($user_name, $pdf_name, $similarity);
            if ($similarity > 70) {
                $score += 30;
            }
        }
        
        if ($score > $best_score) {
            $best_score = $score;
            $best_match = $pdf;
        }
    }
    
    // If we found a good match, rename and move the file
    if ($best_match && $best_score >= 50) {
        return rename_and_link_certificate_pdf($best_match, $certificate_number, $candidate_reg_number, $method);
    }
    
    return false;
}

/**
 * Rename and link certificate PDF
 */
function rename_and_link_certificate_pdf($pdf_file, $certificate_number, $candidate_reg_number, $method = '') {
    $old_path = $pdf_file['path'];
    $old_filename = $pdf_file['filename'];
    
    // Create method slug
    $method_slug = '';
    if (!empty($method)) {
        $method_slug = sanitize_title($method);
    } else {
        // Try to extract from filename
        $metadata = extract_pdf_metadata($old_filename);
        if (!empty($metadata['method'])) {
            $method_slug = sanitize_title($metadata['method']);
        }
    }
    
    // Generate new filename
    $candidate_slug = !empty($candidate_reg_number) ? sanitize_file_name($candidate_reg_number) : 'unknown';
    $cert_slug = sanitize_file_name($certificate_number);
    
    if (!empty($method_slug)) {
        $new_filename = "certificate_{$candidate_slug}_{$cert_slug}_{$method_slug}.pdf";
    } else {
        $new_filename = "certificate_{$candidate_slug}_{$cert_slug}.pdf";
    }
    
    // Get upload directory
    $upload_dir = wp_upload_dir();
    $cert_dir = $upload_dir['basedir'] . '/certificates';
    
    if (!file_exists($cert_dir)) {
        wp_mkdir_p($cert_dir);
    }
    
    $new_path = $cert_dir . '/' . $new_filename;
    
    // Handle duplicate filenames
    $counter = 1;
    while (file_exists($new_path)) {
        $path_info = pathinfo($new_filename);
        $new_filename = $path_info['filename'] . '_' . $counter . '.' . $path_info['extension'];
        $new_path = $cert_dir . '/' . $new_filename;
        $counter++;
    }
    
    // Copy file (don't move, keep original)
    if (copy($old_path, $new_path)) {
        $cert_url = $upload_dir['baseurl'] . '/certificates/' . $new_filename;
        return $cert_url;
    }
    
    return false;
}

