<?php
/**
 * Secure Exam Certificate Download Handler
 * 
 * Security Features:
 * - Requires user login
 * - Admins can download any certificate
 * - Certificate owners can download their own certificates
 * - Path traversal protection
 * - File existence validation
 * 
 * Usage: /includes/secure-exam-certificate-download.php?file=certificate_123_RT.pdf&entry_id=123
 */

// Load WordPress - try multiple paths
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
    status_header(500);
    die('Error: WordPress environment could not be loaded.');
}

// Check if user is logged in
if (!is_user_logged_in()) {
    status_header(403);
    wp_die(
        '<h1>Access Denied</h1><p>Please <a href="' . wp_login_url(add_query_arg(null, null)) . '">log in</a> to access certificates.</p>',
        'Access Denied',
        ['response' => 403]
    );
}

// Get and validate parameters
$cert_file = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
$entry_id = isset($_GET['entry_id']) ? absint($_GET['entry_id']) : 0;

if (empty($cert_file)) {
    status_header(400);
    wp_die('Error: Invalid certificate request. Missing file parameter.', 'Invalid Request', ['response' => 400]);
}

// Build the full path to the certificate
$upload_dir = wp_upload_dir();
$cert_dir = $upload_dir['basedir'] . '/certificates/';
$cert_path = $cert_dir . $cert_file;

// Security check: Path traversal protection
$real_cert_path = realpath($cert_path);
$real_cert_dir = realpath($cert_dir);

if (!$real_cert_path || !$real_cert_dir || strpos($real_cert_path, $real_cert_dir) !== 0) {
    error_log("Certificate download attempt with invalid path: $cert_file");
    status_header(404);
    wp_die('Error: Certificate not found or invalid path.', 'Not Found', ['response' => 404]);
}

// Check if file exists
if (!file_exists($cert_path)) {
    error_log("Certificate file not found: $cert_path");
    status_header(404);
    wp_die('Error: Certificate file does not exist.', 'Not Found', ['response' => 404]);
}

// Permission check
$current_user_id = get_current_user_id();
$has_access = false;
$access_reason = '';

// Check if user is an administrator (full access)
if (current_user_can('manage_options')) {
    $has_access = true;
    $access_reason = 'Administrator';
}

// Check if user is AQB admin (full access)
if (!$has_access && current_user_can('custom_aqb')) {
    $has_access = true;
    $access_reason = 'AQB Admin';
}

// Check if user is center admin (full access to their center's certificates)
if (!$has_access && current_user_can('custom_center_admin')) {
    $has_access = true;
    $access_reason = 'Center Admin';
}

// Check if user is manager admin
if (!$has_access && function_exists('is_custom_super_admin') && is_custom_super_admin()) {
    $has_access = true;
    $access_reason = 'Manager Admin';
}

// Check if user owns the certificate (if entry_id provided)
if (!$has_access && $entry_id > 0) {
    if (class_exists('GFAPI')) {
        $entry = GFAPI::get_entry($entry_id);
        if (!is_wp_error($entry) && isset($entry['created_by'])) {
            if ($entry['created_by'] == $current_user_id) {
                $has_access = true;
                $access_reason = 'Certificate Owner';
            }
        }
    }
}

// Deny access if no permission
if (!$has_access) {
    error_log("Certificate download denied for user $current_user_id: $cert_file");
    status_header(403);
    wp_die(
        '<h1>Access Denied</h1><p>You do not have permission to access this certificate.</p>',
        'Access Denied',
        ['response' => 403]
    );
}

// Log successful access
error_log("Certificate downloaded by user $current_user_id ($access_reason): $cert_file");

// Serve the file
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($cert_path) . '"');
header('Content-Length: ' . filesize($cert_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('X-Robots-Tag: noindex, nofollow');

// Clear any previous output
if (ob_get_level()) {
    ob_end_clean();
}

// Output the file
readfile($cert_path);
exit;
