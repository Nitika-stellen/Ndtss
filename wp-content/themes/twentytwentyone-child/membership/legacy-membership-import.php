<?php
/**
 * Legacy Membership Import System
 * 
 * Allows admins to import legacy membership data from CSV files
 * Supports multiple CSV files (ordinary.csv, associate.csv, professional.csv, fellow.csv, corporate.csv)
 * 
 * @package SGNDT
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include helper functions
require_once get_stylesheet_directory() . '/includes/legacy-import-helpers.php';

// Add admin menu - integrated with membership module
add_action('admin_menu', 'legacy_membership_import_add_admin_menu', 20);
add_action('admin_enqueue_scripts', 'legacy_membership_import_enqueue_scripts');
add_action('wp_ajax_legacy_import_upload', 'handle_legacy_import_upload');
add_action('wp_ajax_legacy_import_preview', 'handle_legacy_import_preview');
add_action('wp_ajax_legacy_import_process', 'handle_legacy_import_process');
add_action('wp_ajax_legacy_import_use_theme_file', 'handle_legacy_import_use_theme_file');
add_action('wp_ajax_legacy_import_export_skipped', 'handle_legacy_import_export_skipped');
add_action('wp_ajax_legacy_import_export_imported', 'handle_legacy_import_export_imported');

/**
 * Add admin menu - under membership management
 */
function legacy_membership_import_add_admin_menu() {
    add_submenu_page(
        'membership-management',
        'Legacy Membership Import',
        'Legacy Import',
        'manage_options',
        'legacy-membership-import',
        'legacy_membership_import_admin_page'
    );
}

/**
 * Enqueue admin scripts and styles
 */
function legacy_membership_import_enqueue_scripts($hook) {
    if (strpos($hook, 'legacy-membership-import') === false) {
        return;
    }
    
    wp_enqueue_style(
        'legacy-import-css',
        get_stylesheet_directory_uri() . '/membership/css/legacy-membership-import.css',
        array(),
        '1.0.0'
    );
    
    wp_enqueue_script(
        'legacy-import-js',
        get_stylesheet_directory_uri() . '/membership/js/legacy-membership-import.js',
        array('jquery'),
        '1.0.0',
        true
    );
    
    wp_localize_script('legacy-import-js', 'legacyImport', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('legacy_import_nonce'),
        'max_file_size' => wp_max_upload_size()
    ));
}

/**
 * Main admin page
 */
function legacy_membership_import_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    ?>
    <div class="wrap legacy-import-wrap">
        <h1>Legacy Membership Import</h1>
        <p class="description">Import legacy membership data from CSV file. The system will create WordPress users, Gravity Forms entries, and assign appropriate roles.</p>
        
        <?php
        // Check for CSV files in theme directory
        $theme_dir = get_stylesheet_directory();
        $csv_files = glob($theme_dir . '/*.csv');
        if (!empty($csv_files)): ?>
        <div class="notice notice-info">
            <p><strong>CSV files found in theme directory:</strong></p>
            <ul>
                <?php foreach ($csv_files as $file): ?>
                <li><code><?php echo esc_html(basename($file)); ?></code></li>
                <?php endforeach; ?>
            </ul>
            <p>You can upload a file or use the files in the theme directory.</p>
        </div>
        <?php endif; ?>
        
        <div class="legacy-import-container">
            <!-- Step 1: File Upload -->
            <div class="import-step active" id="step-1">
                <h2>Step 1: Select CSV File</h2>
                <div class="upload-section">
                    <?php
                    // Check for CSV files in theme directory
                    $theme_dir = get_stylesheet_directory();
                    $csv_files = glob($theme_dir . '/*.csv');
                    if (!empty($csv_files)):
                    ?>
                    <div class="theme-file-option" style="margin-bottom: 20px; padding: 15px; background: #f0f8ff; border: 1px solid #2271b1; border-radius: 4px;">
                        <h3 style="margin-top: 0;">Use CSV File from Theme Directory</h3>
                        <p><strong>Available CSV files:</strong></p>
                        <ul style="list-style: none; padding-left: 0;">
                            <?php foreach ($csv_files as $csv_file): ?>
                            <li style="margin: 5px 0;">
                                <button type="button" class="button button-secondary use-csv-file" data-file-path="<?php echo esc_attr($csv_file); ?>" style="margin-right: 10px;">
                                    Use <?php echo esc_html(basename($csv_file)); ?>
                                </button>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <p style="text-align: center; margin: 20px 0; font-weight: bold;">OR</p>
                    <?php endif; ?>
                    
                    <h3>Upload New CSV File</h3>
                    <form id="legacy-upload-form" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('legacy_import_nonce', 'legacy_import_nonce'); ?>
                        <input type="file" name="legacy_file" id="legacy_file" accept=".csv" required>
                        <button type="submit" class="button button-primary">Upload CSV File</button>
                    </form>
                    <div id="upload-status"></div>
                </div>
                
                <div class="file-preview" id="file-preview" style="display:none;">
                    <h3>File Preview</h3>
                    <div id="preview-content"></div>
                    <button type="button" class="button button-primary" id="proceed-to-step2">Proceed to Step 2</button>
                </div>
            </div>
            
            <!-- Step 2: Import Configuration -->
            <div class="import-step" id="step-2" style="display:none;">
                <h2>Step 2: Import Configuration</h2>
                <div class="config-section">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="membership_type">Membership Type <span style="color: red;">*</span></label>
                            </th>
                            <td>
                                <select id="membership_type" name="membership_type" required style="width: 300px; padding: 5px;">
                                    <option value="">-- Select Membership Type --</option>
                                    <option value="individual">Individual (Form ID 5)</option>
                                    <option value="corporate">Corporate (Form ID 4)</option>
                                </select>
                                <p class="description">Select whether this file contains Individual or Corporate membership data. This determines which Gravity Form and field mapping will be used.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="dry_run">Dry Run Mode</label>
                            </th>
                            <td>
                                <input type="checkbox" id="dry_run" name="dry_run" checked>
                                <p class="description">Validate data without creating users. Recommended for first run.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="batch_size">Batch Size</label>
                            </th>
                            <td>
                                <input type="number" id="batch_size" name="batch_size" value="50" min="1" max="200">
                                <p class="description">Number of rows to process per batch (1-200).</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="create_gf_entries">Create Gravity Forms Entries</label>
                            </th>
                            <td>
                                <input type="checkbox" id="create_gf_entries" name="create_gf_entries" checked>
                                <p class="description">Create Gravity Forms entries for imported members.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="skip_duplicates">Skip Duplicate Emails</label>
                            </th>
                            <td>
                                <input type="checkbox" id="skip_duplicates" name="skip_duplicates" checked>
                                <p class="description">Skip rows with duplicate email addresses (keep first occurrence).</p>
                            </td>
                        </tr>
                    </table>
                    <button type="button" class="button button-primary" id="start-import">Start Import</button>
                </div>
            </div>
            
            <!-- Step 3: Import Progress -->
            <div class="import-step" id="step-3" style="display:none;">
                <h2>Step 3: Import Progress</h2>
                <div id="import-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill">0%</div>
                    </div>
                    <div id="import-status"></div>
                    <div id="import-stats" style="margin-top: 20px;"></div>
                </div>
                <div id="import-results" style="display:none; margin-top: 20px;"></div>
            </div>
        </div>
        
        <!-- Help Section -->
        <div class="import-help" style="margin-top: 40px;">
            <h3>Import Requirements</h3>
            <p><strong>Required Columns:</strong></p>
            <ul>
                <li>S/N</li>
                <li>Membership No.</li>
                <li>Name</li>
                <li>Email</li>
                <li>Mobile No.</li>
                <li>Membership Type (Individual/Corporate)</li>
                <li>Membership Category (Ordinary/Associate/Professional/Fellow/Corporate)</li>
                <li>Membership Status (Active/Expired)</li>
                <li>Start Date (DD/MM/YYYY format)</li>
                <li>Expiry Date (DD/MM/YYYY format)</li>
                <li>Period</li>
                <li>Price</li>
            </ul>
            <p><strong>CSV File Structure:</strong></p>
            <ul>
                <li>Upload one CSV file at a time (e.g., ordinary.csv, fellow.csv, etc.)</li>
                <li>First row should contain column headers</li>
                <li>File should be UTF-8 encoded</li>
            </ul>
            <p><strong>Important:</strong> Make sure to select the correct Membership Type (Individual or Corporate) in Step 2, as this determines which form and field mapping will be used.</p>
        </div>
    </div>
    <?php
}

/**
 * Handle file upload
 */
function handle_legacy_import_upload() {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Don't display, but log
    
    try {
        check_ajax_referer('legacy_import_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (empty($_FILES['legacy_file'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $file = $_FILES['legacy_file'];
    
    // Validate file type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_ext !== 'csv') {
        wp_send_json_error('Invalid file type. Please upload a CSV file (.csv).');
    }
    
    // Validate file size (max 20MB)
    if ($file['size'] > 20 * 1024 * 1024) {
        wp_send_json_error('File size exceeds 20MB limit.');
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = wp_upload_dir();
    $import_dir = $upload_dir['basedir'] . '/legacy-imports';
    if (!file_exists($import_dir)) {
        wp_mkdir_p($import_dir);
    }
    
    // Generate unique filename
    $filename = 'legacy_import_' . time() . '_' . sanitize_file_name($file['name']);
    $file_path = $import_dir . '/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        wp_send_json_error('Failed to save uploaded file.');
    }
    
    // Store file path in transient (expires in 2 hours)
    set_transient('legacy_import_file_' . get_current_user_id(), $file_path, 2 * HOUR_IN_SECONDS);
    
    // Parse and preview file
    $preview = parse_legacy_csv_preview($file_path);
    
    if (is_wp_error($preview)) {
        $error_message = $preview->get_error_message();
        error_log('Legacy Import Upload Error: ' . $error_message);
        wp_send_json_error($error_message);
    }
    
    wp_send_json_success(array(
        'message' => 'CSV file uploaded successfully',
        'preview' => $preview,
        'filename' => $filename
    ));
    
    } catch (Exception $e) {
        $error_message = 'Upload failed: ' . $e->getMessage();
        error_log('Legacy Import Upload Exception: ' . $error_message);
        error_log('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
        error_log('Trace: ' . $e->getTraceAsString());
        wp_send_json_error($error_message);
    } catch (Error $e) {
        $error_message = 'Upload failed: ' . $e->getMessage();
        error_log('Legacy Import Upload Fatal Error: ' . $error_message);
        error_log('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
        error_log('Trace: ' . $e->getTraceAsString());
        wp_send_json_error($error_message);
    }
}

/**
 * Parse CSV file preview for legacy import
 */
function parse_legacy_csv_preview($file_path) {
    if (!file_exists($file_path)) {
        return new WP_Error('file_not_found', 'CSV file not found');
    }
    
    try {
        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            return new WP_Error('file_open_error', 'Could not open CSV file');
        }
        
        $preview_data = array(
            'tabs' => array(),
            'total_rows' => 0
        );
        
        // Read headers (first row)
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return new WP_Error('no_headers', 'CSV file has no headers');
        }
        
        // Clean headers
        $headers = array_map('trim', $headers);
        
        // Map headers to standardized names
        $header_map = map_column_headers($headers);
        
        // Read preview rows (max 5 rows)
        $rows = array();
        $row_count = 0;
        $preview_count = 0;
        
        while (($row = fgetcsv($handle)) !== false && $preview_count < 5) {
            // Skip empty rows (rows with no data)
            if (empty(array_filter($row, function($value) {
                return trim($value) !== '';
            }))) {
                continue; // Skip empty rows
            }
            
            $row_count++; // Only count non-empty rows
            
            // Map row data to headers
            $row_data = array();
            foreach ($headers as $index => $header) {
                $value = isset($row[$index]) ? $row[$index] : '';
                $row_data[$header] = trim($value);
            }
            
            // Normalize row data
            $normalized = normalize_row_data($row_data, $header_map);
            $rows[] = $normalized;
            $preview_count++;
        }
        
        // Count remaining non-empty rows
        while (($row = fgetcsv($handle)) !== false) {
            // Only count non-empty rows
            if (!empty(array_filter($row, function($value) {
                return trim($value) !== '';
            }))) {
                $row_count++;
            }
        }
        
        fclose($handle);
        
        // Determine category from filename
        $filename = basename($file_path);
        $category = 'Unknown';
        if (stripos($filename, 'ordinary') !== false) {
            $category = 'Ordinary';
        } elseif (stripos($filename, 'associate') !== false) {
            $category = 'Associate';
        } elseif (stripos($filename, 'professional') !== false) {
            $category = 'Professional';
        } elseif (stripos($filename, 'fellow') !== false) {
            $category = 'Fellow';
        } elseif (stripos($filename, 'corporate') !== false) {
            $category = 'Corporate';
        }
        
        $preview_data['tabs'][] = array(
            'name' => $category,
            'headers' => $headers,
            'header_map' => $header_map,
            'rows' => $rows,
            'total_rows' => $row_count
        );
        
        $preview_data['total_rows'] = $row_count;
        
        return $preview_data;
        
    } catch (Exception $e) {
        error_log('Legacy Import Preview Error: ' . $e->getMessage());
        error_log('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
        return new WP_Error('parse_error', 'Error parsing CSV file: ' . $e->getMessage());
    }
}

/**
 * Handle use theme file request
 */
function handle_legacy_import_use_theme_file() {
    check_ajax_referer('legacy_import_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $file_path = isset($_POST['file_path']) ? sanitize_text_field($_POST['file_path']) : '';
    
    if (empty($file_path)) {
        wp_send_json_error('File path not provided');
    }
    
    // Security check - ensure file is in theme directory
    $theme_dir = get_stylesheet_directory();
    $real_file_path = realpath($file_path);
    $real_theme_dir = realpath($theme_dir);
    
    if (!$real_file_path || strpos($real_file_path, $real_theme_dir) !== 0) {
        wp_send_json_error('Invalid file path. File must be in the child theme directory.');
    }
    
    if (!file_exists($file_path)) {
        wp_send_json_error('File not found: ' . basename($file_path));
    }
    
    // Validate file type
    $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    if ($file_ext !== 'csv') {
        wp_send_json_error('Invalid file type. Please select a CSV file (.csv).');
    }
    
    // Store file path in transient (expires in 2 hours)
    set_transient('legacy_import_file_' . get_current_user_id(), $file_path, 2 * HOUR_IN_SECONDS);
    
    // Parse and preview file
    $preview = parse_legacy_csv_preview($file_path);
    
    if (is_wp_error($preview)) {
        $error_message = $preview->get_error_message();
        error_log('Legacy Import Theme File Error: ' . $error_message);
        wp_send_json_error($error_message);
    }
    
    wp_send_json_success(array(
        'message' => 'CSV file loaded successfully from theme directory',
        'preview' => $preview,
        'filename' => basename($file_path)
    ));
}

/**
 * Handle preview request
 */
function handle_legacy_import_preview() {
    check_ajax_referer('legacy_import_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $file_path = get_transient('legacy_import_file_' . get_current_user_id());
    if (!$file_path || !file_exists($file_path)) {
        wp_send_json_error('CSV file not found. Please upload again.');
    }
    
    $preview = parse_legacy_csv_preview($file_path);
    
    if (is_wp_error($preview)) {
        wp_send_json_error($preview->get_error_message());
    }
    
    wp_send_json_success($preview);
}

/**
 * Handle import process
 */
function handle_legacy_import_process() {
    check_ajax_referer('legacy_import_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $file_path = get_transient('legacy_import_file_' . get_current_user_id());
    
    // If no file in transient, try to find file in theme directory
    if (!$file_path || !file_exists($file_path)) {
        // Try to find CSV file in child theme root
        $theme_dir = get_stylesheet_directory();
        $csv_files = glob($theme_dir . '/*.csv');
        
        if (!empty($csv_files)) {
            // Use the first CSV file found
            $file_path = $csv_files[0];
            // Store in transient for future use
            set_transient('legacy_import_file_' . get_current_user_id(), $file_path, 2 * HOUR_IN_SECONDS);
        } else {
            wp_send_json_error('CSV file not found. Please upload a file or ensure a CSV file exists in the child theme directory.');
        }
    }
    
    if (!file_exists($file_path)) {
        wp_send_json_error('CSV file not found at: ' . $file_path);
    }
    
    $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === 'true';
    $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;
    $create_gf_entries = isset($_POST['create_gf_entries']) && $_POST['create_gf_entries'] === 'true';
    $skip_duplicates = isset($_POST['skip_duplicates']) && $_POST['skip_duplicates'] === 'true';
    $batch_number = isset($_POST['batch_number']) ? intval($_POST['batch_number']) : 0;
    
    // Get membership type from POST or transient (for subsequent batches)
    $membership_type = isset($_POST['membership_type']) ? sanitize_text_field($_POST['membership_type']) : '';
    if (empty($membership_type) && $batch_number > 0) {
        // For subsequent batches, get from transient
        $membership_type = get_transient('legacy_import_membership_type_' . get_current_user_id());
    }
    
    // Validate membership type
    if (empty($membership_type) || !in_array($membership_type, array('individual', 'corporate'))) {
        wp_send_json_error('Please select a valid Membership Type (Individual or Corporate) in Step 2.');
    }
    
    // Store membership type in transient for subsequent batches
    set_transient('legacy_import_membership_type_' . get_current_user_id(), $membership_type, 2 * HOUR_IN_SECONDS);
    
    // Generate a consistent import batch ID that persists across all batches
    $import_session_key = 'legacy_import_batch_id_' . get_current_user_id();
    $import_batch_id = get_transient($import_session_key);
    
    if (empty($import_batch_id) || $batch_number === 0) {
        $import_batch_id = 'legacy_import_' . date('Ymd_His') . '_' . wp_generate_password(6, false);
        set_transient($import_session_key, $import_batch_id, 2 * HOUR_IN_SECONDS);
    }
    
    // Process import
    $result = process_legacy_import($file_path, array(
        'dry_run' => $dry_run,
        'batch_size' => $batch_size,
        'create_gf_entries' => $create_gf_entries,
        'skip_duplicates' => $skip_duplicates,
        'batch_number' => $batch_number,
        'membership_type' => $membership_type,
        'import_batch_id' => $import_batch_id
    ));
    
    // Clear batch ID and membership type if import is completed
    if (isset($result['completed']) && $result['completed'] === true) {
        delete_transient($import_session_key);
        delete_transient('legacy_import_membership_type_' . get_current_user_id());
    }
    
    if (isset($result['success']) && $result['success'] === false) {
        wp_send_json_error($result['message'] ?? 'Import failed');
    }
    
    wp_send_json_success($result);
}

/**
 * Process legacy import
 */
function process_legacy_import($file_path, $options = array()) {
    $dry_run = isset($options['dry_run']) ? $options['dry_run'] : false;
    $batch_size = isset($options['batch_size']) ? $options['batch_size'] : 50;
    $create_gf_entries = isset($options['create_gf_entries']) ? $options['create_gf_entries'] : true;
    $skip_duplicates = isset($options['skip_duplicates']) ? $options['skip_duplicates'] : true;
    $batch_number = isset($options['batch_number']) ? $options['batch_number'] : 0;
    $membership_type = isset($options['membership_type']) ? $options['membership_type'] : '';
    
    // Use provided import batch ID or generate new one
    $import_batch_id = isset($options['import_batch_id']) && !empty($options['import_batch_id']) 
        ? $options['import_batch_id'] 
        : 'legacy_import_' . date('Ymd_His') . '_' . wp_generate_password(6, false);
    
    // Initialize results
    $results = array(
        'skipped_entries' => array(),
        'imported_entries' => array(),
        'users_created' => 0,
        'users_updated' => 0,
        'users_skipped' => 0,
        'gf_entries_created' => 0,
        'errors' => array(),
        'warnings' => array(),
        'total_processed' => 0,
        'total_rows' => 0,
        'import_batch_id' => $import_batch_id,
        'completed' => false
    );
    
    // Initialize logging
    $log_file = wp_upload_dir()['basedir'] . '/legacy-import-logs/import_' . date('Ymd_His') . '.log';
    $log_dir = dirname($log_file);
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
    }
    
    $log_handle = fopen($log_file, 'a');
    if ($log_handle) {
        fwrite($log_handle, "=== Legacy Import Started ===\n");
        fwrite($log_handle, "Batch ID: {$import_batch_id}\n");
        fwrite($log_handle, "Dry Run: " . ($dry_run ? 'Yes' : 'No') . "\n");
        fwrite($log_handle, "File: {$file_path}\n");
        fwrite($log_handle, "Time: " . current_time('mysql') . "\n\n");
    }
    
    try {
        if (!file_exists($file_path)) {
            if ($log_handle) {
                fwrite($log_handle, "[FATAL ERROR] CSV file not found\n");
                fclose($log_handle);
            }
            return array(
                'success' => false,
                'message' => 'CSV file not found',
                'errors' => array('CSV file not found')
            );
        }
        
        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            if ($log_handle) {
                fwrite($log_handle, "[FATAL ERROR] Could not open CSV file\n");
                fclose($log_handle);
            }
            return array(
                'success' => false,
                'message' => 'Could not open CSV file',
                'errors' => array('Could not open CSV file')
            );
        }
        
        // Determine category from filename
        $filename = basename($file_path);
        $category = 'Unknown';
        if (stripos($filename, 'ordinary') !== false) {
            $category = 'Ordinary';
        } elseif (stripos($filename, 'associate') !== false) {
            $category = 'Associate';
        } elseif (stripos($filename, 'professional') !== false) {
            $category = 'Professional';
        } elseif (stripos($filename, 'fellow') !== false) {
            $category = 'Fellow';
        } elseif (stripos($filename, 'corporate') !== false) {
            $category = 'Corporate';
        }
        
        if ($log_handle) {
            fwrite($log_handle, "[INFO] Processing CSV file: {$filename}\n");
            fwrite($log_handle, "[INFO] Detected category: {$category}\n");
        }
        
        // Read headers (first row)
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            if ($log_handle) {
                fwrite($log_handle, "[FATAL ERROR] CSV file has no headers\n");
                fclose($log_handle);
            }
            return array(
                'success' => false,
                'message' => 'CSV file has no headers',
                'errors' => array('CSV file has no headers')
            );
        }
        
        // Clean headers
        $headers = array_map('trim', $headers);
        
        // Map headers to standardized names
        $header_map = map_column_headers($headers);
        
        if ($log_handle) {
            fwrite($log_handle, "[INFO] Headers found: " . implode(', ', $headers) . "\n");
            fwrite($log_handle, "[INFO] Header mapping: " . json_encode($header_map) . "\n");
            
            // Debug: Show which headers didn't map
            $unmapped = array();
            foreach ($header_map as $orig => $mapped) {
                if ($orig === $mapped && strtolower($orig) !== strtolower($mapped)) {
                    // Only show if it's truly unmapped (not already a standard name)
                    $unmapped[] = $orig . ' (normalized: ' . strtolower(trim($orig)) . ')';
                }
            }
            if (!empty($unmapped)) {
                fwrite($log_handle, "[INFO] Unmapped headers: " . implode(', ', $unmapped) . "\n");
            }
        }
        
        // Read all rows
        $all_rows = array();
        $csv_row_number = 1; // Track actual CSV row number (header is row 1, first data row is 2)
        
        while (($row = fgetcsv($handle)) !== false) {
            $csv_row_number++;
            
            // Skip empty rows (rows with no data)
            if (empty(array_filter($row, function($value) {
                return trim($value) !== '';
            }))) {
                if ($log_handle) {
                    fwrite($log_handle, "[INFO] Skipping empty row {$csv_row_number}\n");
                }
                continue;
            }
            
            // Map row data to headers
            $row_data = array();
            foreach ($headers as $index => $header) {
                $value = isset($row[$index]) ? trim($row[$index]) : '';
                $row_data[$header] = $value;
            }
            
            // Normalize row data using header mapping
            $normalized_row = normalize_row_data($row_data, $header_map);
            
            // Before normalization, check for alternative date fields in original data
            // Priority: Renewal Date > Member Since > Submitted Date
            if (empty($normalized_row['Start Date']) || trim($normalized_row['Start Date']) === '' || trim($normalized_row['Start Date']) === '-') {
                // First priority: Renewal Date
                if (!empty($row_data['Renewal Date']) && trim($row_data['Renewal Date']) !== '' && trim($row_data['Renewal Date']) !== '-') {
                    $normalized_row['Start Date'] = trim($row_data['Renewal Date']);
                }
                // Second priority: Member Since (if Renewal Date is empty)
                elseif (!empty($row_data['Member Since']) && trim($row_data['Member Since']) !== '' && trim($row_data['Member Since']) !== '-') {
                    $normalized_row['Start Date'] = trim($row_data['Member Since']);
                }
                // Third priority: Submitted Date (if both above are empty)
                elseif (!empty($row_data['Submitted Date']) && trim($row_data['Submitted Date']) !== '' && trim($row_data['Submitted Date']) !== '-') {
                    $normalized_row['Start Date'] = trim($row_data['Submitted Date']);
                }
            }
            
            $normalized_row['_tab_name'] = $category;
            $normalized_row['_row_number'] = $csv_row_number; // Actual CSV row number (for reference)
            $normalized_row['_original_data'] = $row_data; // Keep original for reference
            $all_rows[] = $normalized_row;
        }
        
        fclose($handle);
        
        $results['total_rows'] = count($all_rows);
        
        // Calculate batch boundaries - FIX: These variables were missing!
        $current_batch_start = $batch_number * $batch_size;
        $current_batch_end = $current_batch_start + $batch_size;
        $rows_processed = 0; // Initialize counter
        
        // Process batch
        $batch_rows = array_slice($all_rows, $current_batch_start, $batch_size);
        $is_last_batch = ($current_batch_end >= count($all_rows));
        
        foreach ($batch_rows as $row_data) {
            $rows_processed++;
            $results['total_processed']++;
            
            // Auto-derive Membership Status from Expiry Date if missing
            if (empty($row_data['Membership Status']) || trim($row_data['Membership Status']) === '' || trim($row_data['Membership Status']) === '-') {
                if (!empty($row_data['Expiry Date'])) {
                    $expiry_date = parse_legacy_date($row_data['Expiry Date']);
                    if ($expiry_date) {
                        $today = date('Y-m-d');
                        $row_data['Membership Status'] = (strtotime($expiry_date) >= strtotime($today)) ? 'Active' : 'Expired';
                    } else {
                        // If we can't parse expiry date, default to Active
                        $row_data['Membership Status'] = 'Active';
                    }
                } else {
                    // Default to Active if no expiry date
                    $row_data['Membership Status'] = 'Active';
                }
            }
            
            // Check original data for Start Date alternatives (fallback if still missing after normalization)
            // Priority: Renewal Date > Member Since > Submitted Date
            if ((empty($row_data['Start Date']) || trim($row_data['Start Date']) === '' || trim($row_data['Start Date']) === '-') && isset($row_data['_original_data'])) {
                $original = $row_data['_original_data'];
                
                // First priority: Renewal Date (original header name)
                if (!empty($original['Renewal Date']) && trim($original['Renewal Date']) !== '' && trim($original['Renewal Date']) !== '-') {
                    $row_data['Start Date'] = trim($original['Renewal Date']);
                    if ($log_handle) {
                        fwrite($log_handle, "[INFO] Row {$row_data['_row_number']}: Using 'Renewal Date' as Start Date: " . $row_data['Start Date'] . "\n");
                    }
                }
                // Second priority: Member Since (if Renewal Date is empty)
                elseif (!empty($original['Member Since']) && trim($original['Member Since']) !== '' && trim($original['Member Since']) !== '-') {
                    $row_data['Start Date'] = trim($original['Member Since']);
                    if ($log_handle) {
                        fwrite($log_handle, "[INFO] Row {$row_data['_row_number']}: Using 'Member Since' as Start Date: " . $row_data['Start Date'] . "\n");
                    }
                }
                // Third priority: Submitted Date (if both above are empty)
                elseif (!empty($original['Submitted Date']) && trim($original['Submitted Date']) !== '' && trim($original['Submitted Date']) !== '-') {
                    $row_data['Start Date'] = trim($original['Submitted Date']);
                    if ($log_handle) {
                        fwrite($log_handle, "[INFO] Row {$row_data['_row_number']}: Using 'Submitted Date' as Start Date: " . $row_data['Start Date'] . "\n");
                    }
                }
            }
            
            // Check original data for Member Since and Renewal Date BEFORE any fallbacks
            $original = isset($row_data['_original_data']) ? $row_data['_original_data'] : array();
            $has_member_since = !empty($original['Member Since']) && trim($original['Member Since']) !== '' && trim($original['Member Since']) !== '-';
            $has_renewal_date = !empty($original['Renewal Date']) && trim($original['Renewal Date']) !== '' && trim($original['Renewal Date']) !== '-';
            $has_start_date = !empty($row_data['Start Date']) && trim($row_data['Start Date']) !== '' && trim($row_data['Start Date']) !== '-';
            
            // Skip if BOTH Member Since AND Renewal Date are missing
            // This check happens BEFORE the Expiry Date fallback
            if (!$has_member_since && !$has_renewal_date && !$has_start_date) {
                // Determine reason for skipping
                $skip_reason = 'Missing Member Since and Renewal Date';
                
                // No valid dates found - skip this entry
                $skipped_entry = array(
                    'row_number' => $row_data['_row_number'],
                    'tab_name' => $row_data['_tab_name'],
                    'name' => isset($row_data['Name']) ? $row_data['Name'] : 'N/A',
                    'email' => isset($row_data['Email']) ? $row_data['Email'] : 'N/A',
                    'member_since' => isset($original['Member Since']) ? $original['Member Since'] : 'N/A',
                    'renewal_date' => isset($original['Renewal Date']) ? $original['Renewal Date'] : 'N/A',
                    'start_date' => isset($row_data['Start Date']) ? $row_data['Start Date'] : 'N/A',
                    'expiry_date' => isset($row_data['Expiry Date']) ? $row_data['Expiry Date'] : 'N/A',
                    'reason' => $skip_reason,
                    'data' => $row_data
                );
                
                if (!isset($results['skipped_entries'])) {
                    $results['skipped_entries'] = array();
                }
                $results['skipped_entries'][] = $skipped_entry;
                $results['users_skipped']++;
                
                if ($log_handle) {
                    fwrite($log_handle, "[SKIPPED] Row {$row_data['_row_number']} ({$row_data['_tab_name']}): {$skip_reason} - Email: " . (isset($row_data['Email']) ? $row_data['Email'] : 'N/A') . "\n");
                }
                continue; // Skip this row
            }
            
            // If Start Date is still missing, use Expiry Date minus 1 year as fallback
            // (Only if we have Member Since or Renewal Date)
            if (empty($row_data['Start Date']) || trim($row_data['Start Date']) === '' || trim($row_data['Start Date']) === '-') {
                if (!empty($row_data['Expiry Date'])) {
                    $expiry_date = parse_legacy_date($row_data['Expiry Date']);
                    if ($expiry_date) {
                        // Use Expiry Date minus 1 year as Start Date (default membership period)
                        $start_timestamp = strtotime($expiry_date . ' -1 year');
                        $row_data['Start Date'] = date('Y-m-d', $start_timestamp);
                        if ($log_handle) {
                            fwrite($log_handle, "[WARNING] Row {$row_data['_row_number']}: Start Date missing, using Expiry Date - 1 year: " . $row_data['Start Date'] . "\n");
                        }
                    }
                }
            }
            
            // Final validation: Check if Start Date can be parsed
            $start_date_parsed = !empty($row_data['Start Date']) && trim($row_data['Start Date']) !== '' && trim($row_data['Start Date']) !== '-' 
                ? parse_legacy_date(trim($row_data['Start Date'])) 
                : null;
            
            // Skip if Start Date exists but cannot be parsed
            if ($start_date_parsed === null && !empty($row_data['Start Date']) && trim($row_data['Start Date']) !== '' && trim($row_data['Start Date']) !== '-') {
                $skip_reason = 'Invalid Start Date format';
                
                $skipped_entry = array(
                    'row_number' => $row_data['_row_number'],
                    'tab_name' => $row_data['_tab_name'],
                    'name' => isset($row_data['Name']) ? $row_data['Name'] : 'N/A',
                    'email' => isset($row_data['Email']) ? $row_data['Email'] : 'N/A',
                    'member_since' => isset($original['Member Since']) ? $original['Member Since'] : 'N/A',
                    'renewal_date' => isset($original['Renewal Date']) ? $original['Renewal Date'] : 'N/A',
                    'start_date' => isset($row_data['Start Date']) ? $row_data['Start Date'] : 'N/A',
                    'expiry_date' => isset($row_data['Expiry Date']) ? $row_data['Expiry Date'] : 'N/A',
                    'reason' => $skip_reason,
                    'data' => $row_data
                );
                
                if (!isset($results['skipped_entries'])) {
                    $results['skipped_entries'] = array();
                }
                $results['skipped_entries'][] = $skipped_entry;
                $results['users_skipped']++;
                
                if ($log_handle) {
                    fwrite($log_handle, "[SKIPPED] Row {$row_data['_row_number']} ({$row_data['_tab_name']}): {$skip_reason} - Email: " . (isset($row_data['Email']) ? $row_data['Email'] : 'N/A') . "\n");
                }
                continue; // Skip this row
            }
            
            // Calculate membership period from Start Date and Expiry Date if Period is missing
            if ((empty($row_data['Period']) || trim($row_data['Period']) === '' || trim($row_data['Period']) === '-') && !empty($row_data['Start Date']) && !empty($row_data['Expiry Date'])) {
                $start_date = parse_legacy_date($row_data['Start Date']);
                $expiry_date = parse_legacy_date($row_data['Expiry Date']);
                if ($start_date && $expiry_date) {
                    $start_timestamp = strtotime($start_date);
                    $expiry_timestamp = strtotime($expiry_date);
                    $years = round(($expiry_timestamp - $start_timestamp) / (365.25 * 24 * 60 * 60), 1);
                    if ($years >= 99) {
                        $row_data['Period'] = 'Lifetime';
                    } elseif ($years >= 3) {
                        $row_data['Period'] = '3 Years';
                    } elseif ($years >= 2) {
                        $row_data['Period'] = '2 Years';
                    } elseif ($years >= 1) {
                        $row_data['Period'] = '1 Year';
                    } else {
                        $row_data['Period'] = '1 Year'; // Default to 1 year
                    }
                }
            }
            
            // Validate row
            $validation = validate_legacy_row($row_data);
            if (!$validation['valid']) {
                $error_msg = "Row {$row_data['_row_number']} ({$row_data['_tab_name']}): " . implode(', ', $validation['errors']);
                $results['errors'][] = $error_msg;
                $results['users_skipped']++;
                
                if ($log_handle) {
                    fwrite($log_handle, "[ERROR] {$error_msg}\n");
                }
                continue;
            }
            
            // Override membership type from selection if provided
            if (!empty($membership_type)) {
                $row_data['Membership Type'] = ucfirst($membership_type);
            }
            
            // Process row
            $row_result = process_legacy_row($row_data, array(
                'dry_run' => $dry_run,
                'create_gf_entries' => $create_gf_entries,
                'skip_duplicates' => $skip_duplicates,
                'import_batch_id' => $import_batch_id,
                'source_file' => basename($file_path),
                'membership_type' => $membership_type
            ));
            
            if (is_wp_error($row_result)) {
                $error_msg = "Row {$row_data['_row_number']} ({$row_data['_tab_name']}): " . $row_result->get_error_message();
                $results['errors'][] = $error_msg;
                $results['users_skipped']++;
                
                if ($log_handle) {
                    fwrite($log_handle, "[ERROR] {$error_msg}\n");
                }
            } else {
                if ($row_result['created']) {
                    $results['users_created']++;
                } else {
                    $results['users_updated']++;
                }
                
                if (isset($row_result['gf_entry_created']) && $row_result['gf_entry_created']) {
                    // Count actual number of entries created (1 for single entry, 2 for initial+renewal)
                    $entries_count = isset($row_result['gf_entries_count']) ? $row_result['gf_entries_count'] : 1;
                    $results['gf_entries_created'] += $entries_count;
                }
                
                // Track imported entry
                if (!isset($results['imported_entries'])) {
                    $results['imported_entries'] = array();
                }
                $imported_entry = array(
                    'row_number' => $row_data['_row_number'],
                    'tab_name' => $row_data['_tab_name'],
                    'name' => isset($row_data['Name']) ? $row_data['Name'] : 'N/A',
                    'email' => isset($row_data['Email']) ? $row_data['Email'] : 'N/A',
                    'action' => $row_result['created'] ? 'Created' : 'Updated',
                    'gf_entry_id' => isset($row_result['gf_entry_id']) ? $row_result['gf_entry_id'] : null,
                    'gf_entries_count' => isset($row_result['gf_entries_count']) ? $row_result['gf_entries_count'] : 0
                );
                $results['imported_entries'][] = $imported_entry;
                
                if ($log_handle) {
                    $action = $row_result['created'] ? 'CREATED' : 'UPDATED';
                    fwrite($log_handle, "[{$action}] User: {$row_data['Email']} (Row {$row_data['_row_number']}, Tab: {$row_data['_tab_name']})\n");
                }
            }
        }
        
        $results['completed'] = $is_last_batch;
        $results['next_batch'] = $is_last_batch ? null : ($batch_number + 1);
        $results['progress_percent'] = round(($results['total_processed'] / $results['total_rows']) * 100, 2);
        
        // Store skipped entries in transient for later processing (only on final batch)
        if ($is_last_batch && !empty($results['skipped_entries'])) {
            $skipped_transient_key = 'legacy_import_skipped_' . $import_batch_id;
            set_transient($skipped_transient_key, $results['skipped_entries'], 30 * DAY_IN_SECONDS);
        }
        
        if ($log_handle) {
            fwrite($log_handle, "\n=== Batch Summary ===\n");
            fwrite($log_handle, "Total Processed: {$results['total_processed']} / {$results['total_rows']}\n");
            fwrite($log_handle, "Users Created: {$results['users_created']}\n");
            fwrite($log_handle, "Users Updated: {$results['users_updated']}\n");
            fwrite($log_handle, "Users Skipped: {$results['users_skipped']}\n");
            fwrite($log_handle, "Skipped Entries (No Start Date): " . count($results['skipped_entries']) . "\n");
            fwrite($log_handle, "Imported Entries: " . count($results['imported_entries']) . "\n");
            fwrite($log_handle, "GF Entries Created: {$results['gf_entries_created']}\n");
            fwrite($log_handle, "Errors: " . count($results['errors']) . "\n");
            fwrite($log_handle, "Completed: " . ($is_last_batch ? 'Yes' : 'No') . "\n\n");
            fclose($log_handle);
        }
        
        return $results;
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $error_file = $e->getFile();
        $error_line = $e->getLine();
        $error_trace = $e->getTraceAsString();
        
        if ($log_handle) {
            fwrite($log_handle, "[FATAL ERROR] " . $error_message . "\n");
            fwrite($log_handle, "File: " . $error_file . "\n");
            fwrite($log_handle, "Line: " . $error_line . "\n");
            fwrite($log_handle, "Trace: " . $error_trace . "\n");
            fclose($log_handle);
        }
        
        // Log to WordPress error log with full details
        error_log('Legacy Import Error: ' . $error_message);
        error_log('File: ' . $error_file . ' Line: ' . $error_line);
        error_log('Trace: ' . $error_trace);
        
        // Return detailed error for AJAX response
        return array(
            'success' => false,
            'message' => 'Error processing import: ' . $error_message,
            'errors' => array($error_message),
            'file' => basename($error_file),
            'line' => $error_line,
            'trace' => $error_trace
        );
    }
}

/**
 * Process a single legacy row
 */
function process_legacy_row($row_data, $options = array()) {
    $dry_run = isset($options['dry_run']) ? $options['dry_run'] : false;
    $create_gf_entries = isset($options['create_gf_entries']) ? $options['create_gf_entries'] : true;
    $skip_duplicates = isset($options['skip_duplicates']) ? $options['skip_duplicates'] : true;
    $import_batch_id = isset($options['import_batch_id']) ? $options['import_batch_id'] : '';
    $source_file = isset($options['source_file']) ? $options['source_file'] : '';
    
    // Check for duplicate email if skip_duplicates is enabled
    if ($skip_duplicates) {
        $email = normalize_email($row_data['Email']);
        $existing_user = get_user_by('email', $email);
        
        if ($existing_user) {
            // Check if this user was already imported in this batch
            $existing_import_id = get_user_meta($existing_user->ID, 'legacy_import_id', true);
            if ($existing_import_id === $import_batch_id) {
                return new WP_Error('duplicate_in_batch', 'Duplicate email in same import batch');
            }
        }
    }
    
    if ($dry_run) {
        // Dry run - just validate, don't create
        return array(
            'created' => false,
            'dry_run' => true,
            'valid' => true
        );
    }
    
    // Create or update user
    $user_result = create_or_update_user_from_legacy($row_data, array(
        'import_id' => $import_batch_id,
        'timestamp' => current_time('mysql'),
        'source_file' => $source_file
    ));
    
    if (is_wp_error($user_result)) {
        return $user_result;
    }
    
    $user_id = $user_result['user_id'];
    
    // Set membership dates and role
    $membership_status = map_membership_status($row_data['Membership Status']);
    $role_result = set_membership_dates_and_role($user_id, $row_data, $membership_status);
    
    if (is_wp_error($role_result)) {
        return $role_result;
    }
    
    // Store additional legacy data
    mark_imported_row($user_id, $row_data, $import_batch_id);
    
    // Create GF entry if requested
    // Note: For Corporate memberships with Renewal Date, this may create two entries:
    // 1. Initial entry (Start Date to Renewal Date)
    // 2. Renewal entry (Renewal Date to Expiry Date)
    // The function returns the renewal entry ID (most recent) if both are created successfully
    $gf_entry_id = null;
    $gf_entries_count = 0;
    if ($create_gf_entries) {
        $selected_membership_type = isset($options['membership_type']) ? $options['membership_type'] : '';
        $gf_result = create_gf_entry_for_legacy_member($user_id, $row_data, $import_batch_id, $selected_membership_type);
        if (!is_wp_error($gf_result)) {
            $gf_entry_id = $gf_result;
            // Check if two entries were created (for corporate with renewal date)
            // We can check entry meta to see if this is a renewal entry with a linked initial entry
            $entry_type = gform_get_meta($gf_result, 'entry_type');
            if ($entry_type === 'renewal') {
                // Two entries were created (initial + renewal)
                $gf_entries_count = 2;
            } else {
                // Single entry created
                $gf_entries_count = 1;
            }
        } else {
            // Log GF entry creation error but don't fail the whole import
            error_log('GF Entry Creation Error for user ' . $user_id . ': ' . $gf_result->get_error_message());
        }
    }
    
    return array(
        'user_id' => $user_id,
        'created' => $user_result['created'],
        'gf_entry_id' => $gf_entry_id,
        'gf_entry_created' => !is_null($gf_entry_id),
        'gf_entries_count' => $gf_entries_count // Number of entries created (1 or 2)
    );
}

/**
 * Create or update user from legacy data
 */
function create_or_update_user_from_legacy($row_data, $import_meta) {
    $email = sanitize_email(trim($row_data['Email']));
    
    if (empty($email) || !is_email($email)) {
        return new WP_Error('invalid_email', 'Invalid email address');
    }
    
    // Check if user exists
    $user = get_user_by('email', $email);
    
    if ($user) {
        // Update existing user
        $user_id = $user->ID;
        $is_new = false;
    } else {
        // Create new user
        $username = sanitize_user(current(explode('@', $email)));
        $original_username = $username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $original_username . $counter;
            $counter++;
        }
        
        $password = wp_generate_password(12, false);
        
        // Split name
        $name_data = split_name($row_data['Name']);
        
        $user_data = array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'first_name' => $name_data['first_name'],
            'last_name' => $name_data['last_name'],
            'display_name' => trim($row_data['Name']),
            'role' => 'student' // Default role, will be updated based on status
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        $is_new = true;
    }
    
    // Update user meta
    $name_data = split_name($row_data['Name']);
    if (!empty($name_data['first_name'])) {
        update_user_meta($user_id, 'first_name', $name_data['first_name']);
        wp_update_user(array('ID' => $user_id, 'first_name' => $name_data['first_name']));
    }
    if (!empty($name_data['last_name'])) {
        update_user_meta($user_id, 'last_name', $name_data['last_name']);
        wp_update_user(array('ID' => $user_id, 'last_name' => $name_data['last_name']));
    }
    
    // Update display name
    wp_update_user(array('ID' => $user_id, 'display_name' => trim($row_data['Name'])));
    
    // Store import metadata
    update_user_meta($user_id, 'legacy_import_id', $import_meta['import_id']);
    update_user_meta($user_id, 'legacy_import_timestamp', $import_meta['timestamp']);
    update_user_meta($user_id, 'legacy_import_source_file', $import_meta['source_file']);
    if (isset($row_data['S/N'])) {
        update_user_meta($user_id, 'legacy_import_row_id', intval($row_data['S/N']));
    }
    if (isset($row_data['_tab_name'])) {
        update_user_meta($user_id, 'legacy_import_tab', sanitize_text_field($row_data['_tab_name']));
    }
    
    // Store additional legacy data
    if (isset($row_data['Mobile No.']) && !empty($row_data['Mobile No.'])) {
        update_user_meta($user_id, 'mobile', sanitize_text_field($row_data['Mobile No.']));
    }
    if (isset($row_data['NRIC/Passport No.']) && !empty($row_data['NRIC/Passport No.'])) {
        update_user_meta($user_id, 'nric_passport', sanitize_text_field($row_data['NRIC/Passport No.']));
    }
    if (isset($row_data['Membership No.']) && !empty($row_data['Membership No.'])) {
        update_user_meta($user_id, 'legacy_membership_number', sanitize_text_field($row_data['Membership No.']));
    }
    if (isset($row_data['Certificate No.']) && !empty($row_data['Certificate No.'])) {
        update_user_meta($user_id, 'legacy_certificate_number', sanitize_text_field($row_data['Certificate No.']));
    }
    if (isset($row_data['Remarks']) && !empty($row_data['Remarks']) && $row_data['Remarks'] !== '-') {
        update_user_meta($user_id, 'legacy_remarks', sanitize_textarea_field($row_data['Remarks']));
    }
    if (isset($row_data['Period']) && !empty($row_data['Period'])) {
        update_user_meta($user_id, 'membership_period', sanitize_text_field($row_data['Period']));
    }
    if (isset($row_data['Price']) && !empty($row_data['Price'])) {
        $price = normalize_price($row_data['Price']);
        update_user_meta($user_id, 'membership_price', $price);
    }
    if (isset($row_data['Payment Status']) && !empty($row_data['Payment Status'])) {
        update_user_meta($user_id, 'payment_status', sanitize_text_field($row_data['Payment Status']));
    }
    if (isset($row_data['Payment Date']) && !empty($row_data['Payment Date']) && $row_data['Payment Date'] !== '-') {
        $payment_date = parse_legacy_date($row_data['Payment Date']);
        if ($payment_date) {
            update_user_meta($user_id, 'payment_date', $payment_date);
        }
    }
    if (isset($row_data['Payment Method']) && !empty($row_data['Payment Method'])) {
        update_user_meta($user_id, 'payment_method', sanitize_text_field($row_data['Payment Method']));
    }
    
    // Store address fields
    if (isset($row_data['Residential Address']) && !empty($row_data['Residential Address']) && $row_data['Residential Address'] !== '-') {
        update_user_meta($user_id, 'residential_address', sanitize_textarea_field($row_data['Residential Address']));
    }
    if (isset($row_data['Company Address']) && !empty($row_data['Company Address']) && $row_data['Company Address'] !== '-') {
        update_user_meta($user_id, 'company_address', sanitize_textarea_field($row_data['Company Address']));
    }
    if (isset($row_data['Company Name']) && !empty($row_data['Company Name']) && $row_data['Company Name'] !== '-') {
        update_user_meta($user_id, 'company_name', sanitize_text_field($row_data['Company Name']));
    }
    if (isset($row_data['Designation']) && !empty($row_data['Designation']) && $row_data['Designation'] !== '-') {
        update_user_meta($user_id, 'designation', sanitize_text_field($row_data['Designation']));
    }
    if (isset($row_data['Qualification']) && !empty($row_data['Qualification']) && $row_data['Qualification'] !== '-') {
        update_user_meta($user_id, 'qualification', sanitize_text_field($row_data['Qualification']));
    }
    
    return array(
        'user_id' => $user_id,
        'created' => $is_new
    );
}

/**
 * Set membership dates and role
 */
function set_membership_dates_and_role($user_id, $row_data, $membership_status) {
    // Parse dates
    $start_date = parse_legacy_date($row_data['Start Date']);
    $expiry_date = parse_legacy_date($row_data['Expiry Date']);
    
    if (!$start_date || !$expiry_date) {
        return new WP_Error('invalid_date', 'Invalid date format');
    }
    
    // Validate date logic
    if (strtotime($expiry_date) < strtotime($start_date)) {
        return new WP_Error('invalid_date_range', 'Expiry date before start date');
    }
    
    // Check if user has existing membership with later expiry
    $existing_expiry = get_user_meta($user_id, 'membership_expiry_date', true);
    if ($existing_expiry && strtotime($existing_expiry) > strtotime($expiry_date)) {
        // Keep existing expiry if it's later
        $expiry_date = $existing_expiry;
    }
    
    // Get original data for Member Since
    $original = isset($row_data['_original_data']) ? $row_data['_original_data'] : array();
    $csv_member_since = null;
    
    // Check for Member Since column (P)
    if (!empty($original['Member Since']) && trim($original['Member Since']) !== '-' && trim($original['Member Since']) !== '') {
        $csv_member_since = parse_legacy_date(trim($original['Member Since']));
    }
    
    // Update user meta (capture member since explicitly)
    update_user_meta($user_id, 'membership_approval_date', $start_date);
    
    // Use CSV member since if available, otherwise fallback to Start Date
    if ($csv_member_since) {
        update_user_meta($user_id, 'member_since', $csv_member_since);
    } else {
        update_user_meta($user_id, 'member_since', $start_date);
    }
    
    update_user_meta($user_id, 'membership_expiry_date', $expiry_date);
    update_user_meta($user_id, 'membership_approval_status', $membership_status);
    
    // Mark as CSV import
    update_user_meta($user_id, 'import_source', 'csv_import');
    
    // Get original data for renewal date
    $original = isset($row_data['_original_data']) ? $row_data['_original_data'] : array();
    $renewal_date = null;
    
    // Store renewal date if available
    if (!empty($original['Renewal Date']) && trim($original['Renewal Date']) !== '-' && trim($original['Renewal Date']) !== '') {
        $renewal_date = parse_legacy_date(trim($original['Renewal Date']));
        if ($renewal_date) {
            update_user_meta($user_id, 'legacy_renewal_date', $renewal_date);
        }
    }
    
    // Calculate membership duration
    // Priority: Use renewal date if available, otherwise use member_since (start_date)
    $duration_start = !empty($renewal_date) ? $renewal_date : $start_date;
    $duration_years = 0;
    
    // Always calculate years from dates first
    if ($duration_start && $expiry_date) {
        $start_ts = strtotime($duration_start);
        $expiry_ts = strtotime($expiry_date);
        $duration_years = round(($expiry_ts - $start_ts) / (365.25 * 24 * 60 * 60), 1);
    }

    $membership_period = '';
    
    // Check strict CSV value for Period (don't rely on potentially stale user meta)
    $csv_period = '';
    if (isset($row_data['Period']) && !empty($row_data['Period'])) {
        $csv_period = sanitize_text_field($row_data['Period']);
    }
    
    // Logic: 
    // 1. If calculated duration is massive (>10 years), force Lifetime.
    // 2. Else if CSV has a specific period label in THIS import, use it.
    // 3. Else calculate label from years.
    
    // Logic: 
    // 1. Prioritize date-based calculation for all standard tiers (fresh from dates).
    // 2. Only use CSV Period if dates don't result in a standard tier.
    
    if ($duration_years > 10) {
        $membership_period = 'Lifetime';
    } elseif ($duration_years >= 5) {
        $membership_period = '5 Years';
    } elseif ($duration_years >= 3) {
        $membership_period = '3 Years';
    } elseif ($duration_years >= 2) {
        $membership_period = '2 Years';
    } elseif ($duration_years >= 1) {
        $membership_period = '1 Year';
    } elseif (!empty($csv_period)) {
        $membership_period = $csv_period;
    } else {
        $membership_period = round($duration_years, 1) . ' Years';
    }
    
    update_user_meta($user_id, 'membership_duration_years', $duration_years);
    update_user_meta($user_id, 'membership_period', $membership_period);
    
    // Set membership type and category
    $membership_type = strtolower(trim($row_data['Membership Type']));
    $membership_category = trim($row_data['Membership Category']);
    
    update_user_meta($user_id, 'membership_type', $membership_type);
    update_user_meta($user_id, 'member_type', $membership_category);
    
    // Handle role assignment
    $user = new WP_User($user_id);
    
    // Remove all membership roles first
    $membership_roles = array('member', 'ordinary_member', 'associate_member', 'professional_member', 'fellow_member', 'corporate_member');
    foreach ($membership_roles as $role) {
        $user->remove_role($role);
    }
    
    // Assign roles based on status
    if ($membership_status === 'approved') {
        $user->add_role('member');
        $category_role = get_category_role($membership_category);
        $user->add_role($category_role);
    } else {
        // Expired or pending - assign student role
        if (!in_array('student', $user->roles)) {
            $user->add_role('student');
        }
    }
    
    return true;
}

/**
 * Helper function to get all field IDs from a Gravity Form (for debugging/configuration)
 */
function get_form_field_ids($form_id) {
    if (!class_exists('GFAPI')) {
        return array();
    }
    
    $form = GFAPI::get_form($form_id);
    if (is_wp_error($form)) {
        return array();
    }
    
    $fields = array();
    foreach ($form['fields'] as $field) {
        $fields[] = array(
            'id' => $field->id,
            'label' => $field->label,
            'type' => $field->type
        );
    }
    
    return $fields;
}

/**
 * Get field mapping configuration for CSV columns to Gravity Forms field IDs
 */
function get_legacy_import_field_mapping($form_id) {
    // Default mappings for Individual form (Form ID 5)
    $form_5_mapping = array(
        'Name' => '1',
        'Company Name' => '10',
        'Designation' => '14',
        'Academic Qualifications' => '14', // Falls back to 14 if Designation not available
        'Experience in Yrs' => '15',
        'Professional Qualifications' => '16',
        'Membership Category' => '24',
    );
    
    // Mappings for Corporate form (Form ID 4)
    // Field mappings based on user requirements:
    // - Name: field id 1 (Company/Organization Name)
    // - Tel1: field id 12 (Telephone 1 - handled separately with multiple column name variations)
    // - Mobile: Field id 7 (Mobile Phone)
    // - Main Contact Person: field id 10 (if empty, uses user's display name)
    // - Designation: Field id 13
    // - Experience in Yrs: 16
    // - Email, Address, and other fields mapped dynamically
    // - Member Since: Uses Renewal Date if available, otherwise Member Since (handled in date logic)
    // - Expiry Date: Maps to expiry date field
    $form_4_mapping = array(
        'Name' => '1', // Company/Organization Name
        'Mobile No.' => '7', // Mobile Phone
        'Main Contact Person' => '10', // Main Contact Person (special handling - uses user display name if empty)
        'Designation' => '13', // Designation/Position
        'Experience in Yrs' => '16', // Experience in Years
        // Note: Tel1 (field 12) is handled separately below to check multiple column name variations
        // Note: Email, Address, and other fields will be mapped dynamically like individual membership
    );
    
    // Return appropriate mapping based on form ID
    if ($form_id == 4) {
        return $form_4_mapping;
    } else {
        return $form_5_mapping;
    }
}

/**
 * Create Gravity Forms entry for legacy member
 * For Corporate memberships with Renewal Date, creates two entries:
 * 1. Start Date to Renewal Date
 * 2. Renewal Date to Expiry Date
 */
function create_gf_entry_for_legacy_member($user_id, $row_data, $import_batch_id = '', $selected_membership_type = '') {
    // Determine form ID based on selected membership type or row data
    if (!empty($selected_membership_type)) {
        $membership_type = strtolower(trim($selected_membership_type));
    } else {
        $membership_type = strtolower(trim($row_data['Membership Type']));
    }
    $form_id = ($membership_type === 'corporate') ? 4 : 5;
    
    // Get form to understand field structure
    $form = GFAPI::get_form($form_id);
    if (is_wp_error($form)) {
        return new WP_Error('form_not_found', 'Gravity Form not found');
    }
    
    // Parse dates
    $start_date = parse_legacy_date($row_data['Start Date']);
    $expiry_date = parse_legacy_date($row_data['Expiry Date']);
    $original = isset($row_data['_original_data']) ? $row_data['_original_data'] : array();
    
    // Get Renewal Date from original data
    $renewal_date = null;
    if (!empty($original['Renewal Date']) && trim($original['Renewal Date']) !== '-' && trim($original['Renewal Date']) !== '') {
        $renewal_date = parse_legacy_date(trim($original['Renewal Date']));
    }
    
    // For Corporate (Form 4): Check if we need to create two entries
    if ($form_id == 4 && $renewal_date && $start_date && $expiry_date) {
        // Validate date order: Start Date < Renewal Date < Expiry Date
        $start_timestamp = strtotime($start_date);
        $renewal_timestamp = strtotime($renewal_date);
        $expiry_timestamp = strtotime($expiry_date);
        
        if ($start_timestamp < $renewal_timestamp && $renewal_timestamp < $expiry_timestamp) {
            // Create two entries
            $entry1_result = create_single_gf_entry($user_id, $row_data, $form_id, $form, $start_date, $renewal_date, $start_date, $import_batch_id, 'initial');
            $entry2_result = create_single_gf_entry($user_id, $row_data, $form_id, $form, $renewal_date, $expiry_date, $renewal_date, $import_batch_id, 'renewal');
            
            // Return the renewal entry ID (most recent) or first entry if renewal failed
            if (!is_wp_error($entry2_result)) {
                return $entry2_result;
            } elseif (!is_wp_error($entry1_result)) {
                return $entry1_result;
            } else {
                return $entry1_result; // Return error from first entry
            }
        }
    }
    
    // Single entry (default behavior or if renewal date logic doesn't apply)
    $entry_start_date = $start_date ? $start_date : (current_time('Y-m-d'));
    $entry_expiry_date = $expiry_date ? $expiry_date : (date('Y-m-d', strtotime('+1 year')));
    
    // Determine submitted date: Renewal Date > Member Since > Start Date
    $submitted_date = current_time('mysql');
    if ($renewal_date) {
        $submitted_date = $renewal_date . ' ' . date('H:i:s');
    } elseif (!empty($original['Member Since']) && trim($original['Member Since']) !== '-') {
        $member_since = parse_legacy_date(trim($original['Member Since']));
        if ($member_since) {
            $submitted_date = $member_since . ' ' . date('H:i:s');
        }
    } elseif ($start_date) {
        $submitted_date = $start_date . ' ' . date('H:i:s');
    }
    
    return create_single_gf_entry($user_id, $row_data, $form_id, $form, $entry_start_date, $entry_expiry_date, $submitted_date, $import_batch_id, 'single');
}

/**
 * Create a single Gravity Forms entry with specified date range
 * 
 * @param int $user_id User ID
 * @param array $row_data Row data from CSV
 * @param int $form_id Form ID (4 or 5)
 * @param array $form Gravity Form object
 * @param string $entry_start_date Start date for this entry (YYYY-MM-DD)
 * @param string $entry_expiry_date Expiry date for this entry (YYYY-MM-DD)
 * @param string $submitted_date Date created for entry (YYYY-MM-DD HH:MM:SS)
 * @param string $import_batch_id Import batch ID
 * @param string $entry_type Type of entry: 'initial', 'renewal', or 'single'
 * @return int|WP_Error Entry ID or error
 */
function create_single_gf_entry($user_id, $row_data, $form_id, $form, $entry_start_date, $entry_expiry_date, $submitted_date, $import_batch_id = '', $entry_type = 'single') {
    // Only check for duplicates if we have a batch ID (for single entries)
    if (!empty($import_batch_id) && $entry_type === 'single') {
        $existing_entry_id = get_user_meta($user_id, 'ind_member_form_entry', true);
        if ($existing_entry_id && class_exists('GFAPI')) {
            $entry_batch_id = gform_get_meta($existing_entry_id, 'legacy_import_batch_id');
            if ($entry_batch_id === $import_batch_id) {
                return $existing_entry_id;
            }
            $existing_entry = GFAPI::get_entry($existing_entry_id);
            if (!is_wp_error($existing_entry) && isset($existing_entry['form_id']) && $existing_entry['form_id'] == $form_id) {
                // Entry exists for same form
            }
        }
    }
    
    // Prepare entry data
    $entry = array(
        'form_id' => $form_id,
        'created_by' => $user_id,
        'date_created' => $submitted_date,
        'is_fulfilled' => '1',
        'status' => 'active'
    );
    
    // Get field mapping for this form
    $field_mapping = get_legacy_import_field_mapping($form_id);
    
    // Apply direct field mappings from configuration
    foreach ($field_mapping as $csv_column => $field_id) {
        // Handle special cases with priority
        if ($csv_column === 'Designation' && $form_id == 5) {
            // For form 5, Designation takes priority over Academic Qualifications
            if (isset($row_data['Designation']) && !empty($row_data['Designation']) && trim($row_data['Designation']) !== '-') {
                $entry[$field_id] = trim($row_data['Designation']);
                continue;
            } elseif (isset($row_data['Academic Qualifications']) && !empty($row_data['Academic Qualifications']) && trim($row_data['Academic Qualifications']) !== '-') {
                $entry[$field_id] = trim($row_data['Academic Qualifications']);
                continue;
            }
        }
        
        // Special handling for Main Contact Person (Form 4, Field 10)
        if ($csv_column === 'Main Contact Person' && $form_id == 4) {
            $main_contact = '';
            if (isset($row_data['Main Contact Person']) && !empty($row_data['Main Contact Person']) && trim($row_data['Main Contact Person']) !== '-') {
                $main_contact = trim($row_data['Main Contact Person']);
            } else {
                // If empty, use the user's display name (user is already created/updated at this point)
                // $user_id is available in this function scope
                $contact_user = get_userdata($user_id);
                if ($contact_user) {
                    // Use display name if available, otherwise use name from row data
                    $main_contact = !empty($contact_user->display_name) ? $contact_user->display_name : (isset($row_data['Name']) ? trim($row_data['Name']) : '');
                } else {
                    // Fallback to Name from row data
                    $main_contact = isset($row_data['Name']) ? trim($row_data['Name']) : '';
                }
            }
            if (!empty($main_contact)) {
                $entry[$field_id] = $main_contact;
            }
            continue;
        }
        
        // Standard mapping
        if (isset($row_data[$csv_column]) && !empty($row_data[$csv_column]) && trim($row_data[$csv_column]) !== '-') {
            $entry[$field_id] = trim($row_data[$csv_column]);
        }
    }
    
    // Special handling for Form 4: Map Tel1 field (field 12)
    if ($form_id == 4) {
        // Check for Tel1 in various possible column names
        $tel1_value = '';
        if (isset($row_data['Tel1']) && !empty($row_data['Tel1']) && trim($row_data['Tel1']) !== '-') {
            $tel1_value = trim($row_data['Tel1']);
        } elseif (isset($row_data['Tel']) && !empty($row_data['Tel']) && trim($row_data['Tel']) !== '-') {
            $tel1_value = trim($row_data['Tel']);
        } elseif (isset($row_data['Telephone']) && !empty($row_data['Telephone']) && trim($row_data['Telephone']) !== '-') {
            $tel1_value = trim($row_data['Telephone']);
        } elseif (isset($row_data['Phone']) && !empty($row_data['Phone']) && trim($row_data['Phone']) !== '-') {
            $tel1_value = trim($row_data['Phone']);
        }
        if (!empty($tel1_value)) {
            $entry['12'] = $tel1_value;
        }
    }
    
    // Map CSV data to GF fields dynamically for fields not in direct mapping
    $mapped_field_ids = array_values($field_mapping);
    foreach ($form['fields'] as $field) {
        $field_label = strtolower($field->label);
        $field_id = (string)$field->id;
        
        // Skip fields we've already mapped directly
        if (in_array($field_id, $mapped_field_ids)) {
            continue;
        }
        
        // Map common fields that aren't in the direct mapping
        if (strpos($field_label, 'name') !== false && $field->type === 'name' && !isset($entry[$field_id])) {
            if (isset($row_data['Name']) && !empty($row_data['Name'])) {
                $entry[$field_id] = $row_data['Name'];
            }
        } elseif (strpos($field_label, 'email') !== false && $field->type === 'email' && !isset($entry[$field_id])) {
            if (isset($row_data['Email']) && !empty($row_data['Email'])) {
                $entry[$field_id] = $row_data['Email'];
            }
        } elseif ((strpos($field_label, 'phone') !== false || strpos($field_label, 'mobile') !== false || strpos($field_label, 'tel') !== false) && !isset($entry[$field_id])) {
            // For Form 4, Mobile is field 7, Tel1 is field 12 (already handled above)
            // For other phone fields, try Mobile No. first
            if (isset($row_data['Mobile No.']) && !empty($row_data['Mobile No.'])) {
                $entry[$field_id] = $row_data['Mobile No.'];
            } elseif ($form_id == 4 && isset($row_data['Tel1']) && !empty($row_data['Tel1'])) {
                $entry[$field_id] = trim($row_data['Tel1']);
            }
        } elseif (strpos($field_label, 'nric') !== false || strpos($field_label, 'passport') !== false || strpos($field_label, 'ic') !== false) {
            // Map NRIC/Passport field
            if (isset($row_data['NRIC/Passport No.']) && !empty($row_data['NRIC/Passport No.']) && $row_data['NRIC/Passport No.'] !== '-') {
                $entry[$field_id] = $row_data['NRIC/Passport No.'];
            }
        } elseif (strpos($field_label, 'company') !== false && strpos($field_label, 'address') === false) {
            // Map Company Name field (but not company address)
            if (isset($row_data['Company Name']) && !empty($row_data['Company Name']) && $row_data['Company Name'] !== '-') {
                $entry[$field_id] = $row_data['Company Name'];
            }
        } elseif (strpos($field_label, 'designation') !== false || strpos($field_label, 'position') !== false || strpos($field_label, 'job title') !== false) {
            // Map Designation/Position field
            if (isset($row_data['Designation']) && !empty($row_data['Designation']) && $row_data['Designation'] !== '-') {
                $entry[$field_id] = $row_data['Designation'];
            }
        } elseif (strpos($field_label, 'qualification') !== false || strpos($field_label, 'education') !== false) {
            // Map Qualification field
            if (isset($row_data['Qualification']) && !empty($row_data['Qualification']) && $row_data['Qualification'] !== '-') {
                $entry[$field_id] = $row_data['Qualification'];
            }
        } elseif (strpos($field_label, 'certificate') !== false && strpos($field_label, 'number') !== false) {
            // Map Certificate Number field
            if (isset($row_data['Certificate No.']) && !empty($row_data['Certificate No.']) && $row_data['Certificate No.'] !== '-') {
                $entry[$field_id] = $row_data['Certificate No.'];
            }
        } elseif (strpos($field_label, 'membership') !== false && strpos($field_label, 'number') !== false) {
            // Map Membership Number field
            if (isset($row_data['Membership No.']) && !empty($row_data['Membership No.']) && $row_data['Membership No.'] !== '-') {
                $entry[$field_id] = $row_data['Membership No.'];
            }
        } elseif ($field_id === '24' && $form_id == 5) {
            // Membership category field for Individual form
            $entry[$field_id] = $row_data['Membership Category'];
        } elseif ($field->type === 'address') {
            // Map address fields
            // Priority: Residential Address > Company Address
            $address_value = '';
            if (!empty($row_data['Residential Address']) && trim($row_data['Residential Address']) !== '-') {
                $address_value = trim($row_data['Residential Address']);
            } elseif (!empty($row_data['Company Address']) && trim($row_data['Company Address']) !== '-') {
                $address_value = trim($row_data['Company Address']);
            }
            
            if (!empty($address_value)) {
                // Gravity Forms address fields have sub-fields: 1=Street, 2=Line2, 3=City, 4=State, 5=ZIP, 6=Country
                // Try to parse the address or put it all in Street Address
                $address_parts = explode(',', $address_value);
                $address_parts = array_map('trim', $address_parts);
                
                // Put full address in Street Address (field.1)
                $entry[$field_id . '.1'] = $address_value;
                
                // If we have multiple parts, try to parse them
                if (count($address_parts) > 1) {
                    // Last part might be ZIP or City
                    $last_part = end($address_parts);
                    // Second to last might be State or City
                    if (count($address_parts) >= 2) {
                        $second_last = $address_parts[count($address_parts) - 2];
                        // Try to identify ZIP (usually numeric or alphanumeric)
                        if (preg_match('/^\d+[A-Za-z]?$/', $last_part)) {
                            $entry[$field_id . '.5'] = $last_part; // ZIP
                            if (count($address_parts) >= 3) {
                                $entry[$field_id . '.4'] = $second_last; // State
                                $entry[$field_id . '.3'] = $address_parts[count($address_parts) - 3]; // City
                                $entry[$field_id . '.1'] = implode(', ', array_slice($address_parts, 0, -3)); // Street
                            } else {
                                $entry[$field_id . '.3'] = $second_last; // City
                                $entry[$field_id . '.1'] = implode(', ', array_slice($address_parts, 0, -2)); // Street
                            }
                        } else {
                            // No clear ZIP, put last as City
                            $entry[$field_id . '.3'] = $last_part; // City
                            $entry[$field_id . '.1'] = implode(', ', array_slice($address_parts, 0, -1)); // Street
                        }
                    }
                }
            }
        } elseif ($field->type === 'product') {
            // Product field (membership period)
            // Calculate period based on entry date range
            $entry_start_ts = strtotime($entry_start_date);
            $entry_expiry_ts = strtotime($entry_expiry_date);
            $years = round(($entry_expiry_ts - $entry_start_ts) / (365.25 * 24 * 60 * 60), 1);
            
            // Determine period from calculated years
            if ($years >= 99) {
                $period = 'Lifetime';
            } elseif ($years >= 3) {
                $period = 3;
            } elseif ($years >= 2) {
                $period = 2;
            } elseif ($years >= 1) {
                $period = 1;
            } else {
                // Fallback to original period from CSV if calculation is less than 1 year
                $period = normalize_period($row_data['Period']);
            }
            
            $price = normalize_price($row_data['Price']);
            
            // For two entries, split the price proportionally
            if ($entry_type === 'initial' || $entry_type === 'renewal') {
                // Calculate proportional price based on duration
                $total_start = strtotime($row_data['Start Date'] ? parse_legacy_date($row_data['Start Date']) : $entry_start_date);
                $total_expiry = strtotime($row_data['Expiry Date'] ? parse_legacy_date($row_data['Expiry Date']) : $entry_expiry_date);
                $total_years = ($total_expiry - $total_start) / (365.25 * 24 * 60 * 60);
                $entry_years = ($entry_expiry_ts - $entry_start_ts) / (365.25 * 24 * 60 * 60);
                
                if ($total_years > 0) {
                    $price = $price * ($entry_years / $total_years);
                }
            }
            
            if ($period === 'Lifetime') {
                $entry[$field_id] = 'Lifetime|0.00';
            } else {
                $entry[$field_id] = $period . ' Year' . ($period > 1 ? 's' : '') . '|' . number_format($price, 2);
            }
        }
    }
    
    // Create entry
    $entry_id = GFAPI::add_entry($entry);
    
    if (is_wp_error($entry_id)) {
        return $entry_id;
    }
    
    // Link entry to user (only for single entry or renewal entry)
    if ($entry_type === 'single' || $entry_type === 'renewal') {
        update_user_meta($user_id, 'ind_member_form_entry', $entry_id);
    }
    
    // Mark entry as approved (since legacy members are already approved)
    gform_update_meta($entry_id, 'approval_status', 'approved');
    gform_update_meta($entry_id, 'approved_by', get_current_user_id());
    gform_update_meta($entry_id, 'approval_time', current_time('mysql'));
    
    // Store entry dates in entry meta
    gform_update_meta($entry_id, 'entry_start_date', $entry_start_date);
    gform_update_meta($entry_id, 'entry_expiry_date', $entry_expiry_date);
    gform_update_meta($entry_id, 'entry_type', $entry_type); // 'initial', 'renewal', or 'single'
    
    // Store import batch ID in entry meta to track duplicates
    if (!empty($import_batch_id)) {
        gform_update_meta($entry_id, 'legacy_import_batch_id', $import_batch_id);
    }
    
    return $entry_id;
}

/**
 * Mark imported row with import metadata
 */
function mark_imported_row($user_id, $row_data, $import_batch_id) {
    // Additional metadata already stored in create_or_update_user_from_legacy
    // This function is kept for consistency with the plan
    return true;
}

/**
 * Export skipped entries to CSV
 */
function handle_legacy_import_export_skipped() {
    check_ajax_referer('legacy_import_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $batch_id = isset($_POST['batch_id']) ? sanitize_text_field($_POST['batch_id']) : '';
    $transient_key = 'legacy_import_skipped_' . $batch_id;
    $skipped_entries = get_transient($transient_key);
    
    if (empty($skipped_entries) || !is_array($skipped_entries)) {
        wp_send_json_error('No skipped entries found for this import batch.');
    }
    
    // Generate CSV
    $filename = 'skipped_entries_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    $headers = array(
        'Row Number',
        'Tab Name',
        'Name',
        'Email',
        'Member Since',
        'Renewal Date',
        'Start Date',
        'Expiry Date',
        'Reason',
        'Mobile No.',
        'Membership Type',
        'Membership Category',
        'Membership Status',
        'Period',
        'Price'
    );
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($skipped_entries as $entry) {
        $data = isset($entry['data']) ? $entry['data'] : array();
        $row = array(
            $entry['row_number'] ?? 'N/A',
            $entry['tab_name'] ?? 'N/A',
            $entry['name'] ?? 'N/A',
            $entry['email'] ?? 'N/A',
            $entry['member_since'] ?? 'N/A',
            $entry['renewal_date'] ?? 'N/A',
            $entry['start_date'] ?? 'N/A',
            $entry['expiry_date'] ?? 'N/A',
            $entry['reason'] ?? 'N/A',
            isset($data['Mobile No.']) ? $data['Mobile No.'] : 'N/A',
            isset($data['Membership Type']) ? $data['Membership Type'] : 'N/A',
            isset($data['Membership Category']) ? $data['Membership Category'] : 'N/A',
            isset($data['Membership Status']) ? $data['Membership Status'] : 'N/A',
            isset($data['Period']) ? $data['Period'] : 'N/A',
            isset($data['Price']) ? $data['Price'] : 'N/A'
        );
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * Export imported entries to CSV
 */
function handle_legacy_import_export_imported() {
    check_ajax_referer('legacy_import_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $imported_entries = isset($_POST['imported_entries']) ? json_decode(stripslashes($_POST['imported_entries']), true) : array();
    
    if (empty($imported_entries) || !is_array($imported_entries)) {
        wp_send_json_error('No imported entries data provided.');
    }
    
    // Generate CSV
    $filename = 'imported_entries_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    $headers = array(
        'Row Number',
        'Tab Name',
        'Name',
        'Email',
        'Action',
        'GF Entry ID',
        'GF Entries Count'
    );
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($imported_entries as $entry) {
        $row = array(
            $entry['row_number'] ?? 'N/A',
            $entry['tab_name'] ?? 'N/A',
            $entry['name'] ?? 'N/A',
            $entry['email'] ?? 'N/A',
            $entry['action'] ?? 'N/A',
            $entry['gf_entry_id'] ?? 'N/A',
            $entry['gf_entries_count'] ?? '0'
        );
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

