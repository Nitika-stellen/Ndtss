<?php
/**
 * Secure Certificate Download Handler
 * Only administrators can download certificates
 */

// Load WordPress - adjust path based on your structure
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
    die('WordPress not found. Please check the path.');
}

// Check if user is logged in and is an administrator
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    status_header(403);
    die('Access Denied: You do not have permission to access this certificate.');
}

// Get certificate filename from query parameter
$cert_file = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';

if (empty($cert_file)) {
    status_header(400);
    die('Error: Invalid certificate request.');
}

// Build the full path to the certificate
$upload_dir = wp_upload_dir();
$cert_path = $upload_dir['basedir'] . '/certificates/' . $cert_file;

// Security check: Ensure the file exists and is within the certificates directory
$real_cert_path = realpath($cert_path);
$real_cert_dir = realpath($upload_dir['basedir'] . '/certificates/');

if (!$real_cert_path || !$real_cert_dir || strpos($real_cert_path, $real_cert_dir) !== 0) {
    status_header(404);
    die('Error: Certificate not found.');
}

if (!file_exists($cert_path)) {
    status_header(404);
    die('Error: Certificate file does not exist.');
}

// Serve the file
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($cert_path) . '"');
header('Content-Length: ' . filesize($cert_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Clear any previous output
if (ob_get_level()) {
    ob_end_clean();
}

// Output the file
readfile($cert_path);
exit;
