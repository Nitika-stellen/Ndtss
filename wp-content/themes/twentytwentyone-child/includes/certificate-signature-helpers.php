<?php
/**
 * Certificate Signature Helper Functions
 * 
 * Helper functions to retrieve signature data for use in certificate generation
 * 
 * @package SGNDT
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get chairman signature data
 * 
 * @return array Array containing name, title, and signature image (base64 encoded)
 */
function cert_get_chairman_signature() {
    $name = get_option('cert_chairman_name', '');
    $title = get_option('cert_chairman_title', 'CHAIRMAN / VICE CHAIRMAN');
    $signature_url = get_option('cert_chairman_signature', '');
    
    $signature_base64 = '';
    if (!empty($signature_url)) {
        $signature_base64 = cert_convert_image_to_base64($signature_url);
    }
    
    return array(
        'name' => $name,
        'title' => $title,
        'signature' => $signature_base64,
        'signature_url' => $signature_url
    );
}

/**
 * Get authorized signatory signature data
 * 
 * @return array Array containing name, title, and signature image (base64 encoded)
 */
function cert_get_signatory_signature() {
    $name = get_option('cert_signatory_name', '');
    $title = get_option('cert_signatory_title', 'AUTHORIZED SIGNATORY');
    $signature_url = get_option('cert_signatory_signature', '');
    
    $signature_base64 = '';
    if (!empty($signature_url)) {
        $signature_base64 = cert_convert_image_to_base64($signature_url);
    }
    
    return array(
        'name' => $name,
        'title' => $title,
        'signature' => $signature_base64,
        'signature_url' => $signature_url
    );
}

/**
 * Convert image URL to base64 data URI
 * 
 * @param string $image_url The image URL to convert
 * @return string Base64 encoded data URI or empty string on failure
 */
function cert_convert_image_to_base64($image_url) {
    if (empty($image_url)) {
        return '';
    }
    
    // Convert URL to file path if it's a local file
    $upload_dir = wp_upload_dir();
    if (strpos($image_url, $upload_dir['baseurl']) !== false) {
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
    } else {
        $file_path = $image_url;
    }
    
    // Fix path for Windows if needed
    $file_path = str_replace('\\', '/', $file_path);
    
    // Check if file exists
    if (!file_exists($file_path)) {
        return '';
    }
    
    // Get file contents and convert to base64
    $type = pathinfo($file_path, PATHINFO_EXTENSION);
    $data = file_get_contents($file_path);
    
    if ($data === false) {
        return '';
    }
    
    $base64 = base64_encode($data);
    return 'data:image/' . $type . ';base64,' . $base64;
}

/**
 * Get signature HTML for certificate
 * 
 * @param string $type Either 'chairman' or 'signatory'
 * @param string $position Either 'left' or 'right'
 * @return string HTML for signature section
 */
function cert_get_signature_html($type = 'chairman', $position = 'left') {
    if ($type === 'chairman') {
        $sig_data = cert_get_chairman_signature();
        $subtitle = 'CERTIFICATION COMMITTEE';
    } else {
        $sig_data = cert_get_signatory_signature();
        $subtitle = 'NDTSS';
    }
    
    $html = '<td style="text-align:left; width: 33%; padding: 15px;">';
    $html .= '<strong style="font-size: 12px;">' . esc_html($sig_data['title']) . '</strong><br>';
    $html .= '<strong style="font-size: 12px;">' . esc_html($subtitle) . '</strong>';
    
    // Add signature image if available
    if (!empty($sig_data['signature'])) {
        $html .= '<div style="margin: 10px 0;">';
        $html .= '<img src="' . $sig_data['signature'] . '" style="max-height:50px; max-width:150px;" alt="Signature"/>';
        $html .= '</div>';
    }
    
    $html .= '</td>';
    $html .= '<td style="text-align:left;padding: 15px;">__________________';
    
    // Add name if available
    if (!empty($sig_data['name'])) {
        $html .= '<br><span style="font-size: 11px; font-weight: 600;">' . esc_html($sig_data['name']) . '</span>';
    }
    
    $html .= '</td>';
    
    return $html;
}
