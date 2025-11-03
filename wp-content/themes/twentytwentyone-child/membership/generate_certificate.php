<?php
// Check if vendor autoload exists
$autoload_path = get_stylesheet_directory() . '/includes/vendor/autoload.php';
if (!file_exists($autoload_path)) {
    wp_die('DomPDF library not found. Please install composer dependencies.');
}

require_once $autoload_path;

// Include membership logger functions
require_once get_stylesheet_directory() . '/membership/membership-logger.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Generate membership number with dynamic prefix based on member type
function ul_generate_membership_number($user_id, $entry_id, $membership_type = '') {
    // Get member type if not provided
    if (empty($membership_type)) {
        $membership_type = get_user_meta($user_id, 'member_type', true) ?: 'Corporate';
    }

    // Generate prefix based on membership type
    $prefix = 'M'; // Default
    $membership_type_lower = strtolower($membership_type);

    if (strpos($membership_type_lower, 'corporate') !== false) {
        $prefix = 'C';
    } elseif (strpos($membership_type_lower, 'ordinary') !== false) {
        $prefix = 'O';
    } elseif (strpos($membership_type_lower, 'professional') !== false) {
        $prefix = 'P';
    } elseif (strpos($membership_type_lower, 'fellow') !== false) {
        $prefix = 'F';
    } elseif (strpos($membership_type_lower, 'student') !== false) {
        $prefix = 'S';
    } elseif (strpos($membership_type_lower, 'individual') !== false) {
        $prefix = 'I';
    }

    // Generate 5-digit serial number
    $serial = str_pad($entry_id, 5, '0', STR_PAD_LEFT);

    return $prefix . '-' . $serial;
}

// Fix certificate URLs that may contain old path structures
function ul_fix_certificate_url($url, $correct_base_url) {
    // Handle full URLs with year/month structure like /wp-content/uploads/2025/09/certificates/filename.pdf
    if (strpos($url, '/wp-content/uploads/') !== false && strpos($url, '/certificates/') !== false) {
        $url_parts = explode('/certificates/', $url);
        if (count($url_parts) > 1) {
            $filename = end($url_parts);
            return $correct_base_url . '/' . $filename;
        }
    }

    // Handle URLs with year/month structure like /uploads/certificates/2025/9/filename.pdf
    if (strpos($url, '/uploads/certificates/') !== false) {
        $url_parts = explode('/certificates/', $url);
        if (count($url_parts) > 1) {
            $filename = end($url_parts);
            return $correct_base_url . '/' . $filename;
        }
    }

    // Handle URLs that already have the correct structure but might need updating
    if (strpos($url, '/uploads/certificates/') === 0) {
        return $url; // Already correct
    }

    // Handle full URLs that need to be corrected
    if (strpos($url, 'wp-content/uploads/certificates/') !== false) {
        $url_parts = explode('/certificates/', $url);
        if (count($url_parts) > 1) {
            $filename = end($url_parts);
            return $correct_base_url . '/' . $filename;
        }
    }

    // Handle URLs that start with /wp-content/uploads/ but don't have /certificates/ (malformed)
    if (strpos($url, '/wp-content/uploads/') === 0) {
        // Extract filename from the end of the URL
        $filename = basename($url);
        return $correct_base_url . '/' . $filename;
    }

    // If URL doesn't need fixing, return as-is
    return $url;
}

// Format date to Singapore format (DD/MM/YYYY)
function ul_format_singapore_date($date) {
    if (empty($date)) {
        return date('d/m/Y');
    }

    // Handle different date formats
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date('d/m/Y', $timestamp);
}

add_action('wp_ajax_generate_member_certificate', 'handle_generate_member_certificate');
// Remove wp_ajax_nopriv_ unless non-logged-in users need access
// add_action('wp_ajax_nopriv_generate_member_certificate', 'handle_generate_member_certificate');

function handle_generate_member_certificate() {
    try {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'generate_certificate_nonce')) {
            membership_log_error('Invalid nonce for certificate generation');
            wp_send_json_error('Invalid nonce');
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
        $membership_type = isset($_POST['membership_type']) ? sanitize_text_field($_POST['membership_type']) : '';

        membership_log_info('Certificate generation started', [
            'user_id' => $user_id,
            'member_id' => $member_id,
            'membership_type' => $membership_type
        ]);

        if (!$user_id || !$member_id) {
            membership_log_error('Invalid user or member ID for certificate generation', [
                'user_id' => $user_id,
                'member_id' => $member_id
            ]);
            wp_send_json_error('Invalid user or member ID');
        }

        if (!current_user_can('manage_options') && get_current_user_id() !== $user_id) {
            membership_log_error('Insufficient permissions for certificate generation', [
                'user_id' => $user_id,
                'current_user_id' => get_current_user_id()
            ]);
            wp_send_json_error('Insufficient permissions');
        }

        $user = get_userdata($user_id);
        if (!$user) {
            membership_log_error('User not found for certificate generation', [
                'user_id' => $user_id
            ]);
            wp_send_json_error('User not found');
        }

        // Fallback to user meta if membership_type not provided
        $membership_type = $membership_type ?: get_user_meta($user_id, 'member_type', true) ?: 'Corporate';
        $member_since_raw = get_user_meta($user_id, 'membership_approval_date', true) ?: date('Y-m-d');
        $expiry_date_raw = get_user_meta($user_id, 'membership_expiry_date', true) ?: date('Y-m-d', strtotime('+1 year'));

        // Format dates to Singapore format (DD/MM/YYYY)
        $member_since = ul_format_singapore_date($member_since_raw);
        $expiry_date = ul_format_singapore_date($expiry_date_raw);

        $member_name = strtoupper(esc_html($user->display_name));

        // Generate dynamic membership number
        $membership_no = ul_generate_membership_number($user_id, $member_id, $membership_type);

        // Get dynamic signature information
        $chairman_name = get_option('certificate_chairman_name', 'M.S.VETRISELVAN');
        $chairman_title = get_option('certificate_chairman_title', 'CHAIRMAN-MEMBERSHIP (NDTSS)');
        $president_name = get_option('certificate_president_name', 'BABU SAJEESH KUMAR');
        $president_title = get_option('certificate_president_title', 'PRESIDENT (NDTSS)');
        $secretary_name = get_option('certificate_secretary_name', 'P.PUGALENDHI');
        $secretary_title = get_option('certificate_secretary_title', 'HONORARY SECRETARY (NDTSS)');

        // Image paths and URLs
        $logo_path = get_stylesheet_directory() . '/assets/logos/ndtss-logo.png';
        $seal_path = get_stylesheet_directory() . '/assets/logos/seal.png';
        $logo_url = esc_url(get_stylesheet_directory_uri() . '/assets/logos/ndtss-logo.png');
        $seal_url = esc_url(get_stylesheet_directory_uri() . '/assets/logos/seal.png');

        // Check if images exist
        if (!file_exists($logo_path)) {
            membership_log_error('Logo image not found', ['logo_path' => $logo_path]);
            wp_send_json_error('Logo image not found at: ' . $logo_path);
        }
        if (!file_exists($seal_path)) {
            membership_log_error('Seal image not found', ['seal_path' => $seal_path]);
            wp_send_json_error('Seal image not found at: ' . $seal_path);
        }

        // Validate all required variables
        if (empty($member_name) || empty($membership_type) || empty($member_since) || empty($expiry_date)) {
            membership_log_error('Missing required certificate data', [
                'member_name' => $member_name,
                'membership_type' => $membership_type,
                'member_since' => $member_since,
                'expiry_date' => $expiry_date
            ]);
            wp_send_json_error('Missing required certificate data');
        }

        // Increase limits for PDF generation
        ini_set('memory_limit', '256M');
        ini_set('max_execution_time', 300);

        // PDF setup
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Times-Roman');
        $options->set('isRemoteEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('isJavascriptEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'landscape');

        $pdf_border = esc_url(get_stylesheet_directory_uri() . '/assets/logos/pdf-bordern.jpg');
        $html = '
        <html>
        <head>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            @page { size: A4 landscape; margin: 0; }
            body {
                margin: 0;
                padding: 0;
                font-family: "Poppins", sans-serif !important;
                position: relative;
                width: 87%;
            }
            body *{ font-family: "Poppins", sans-serif !important; }
            .certificate {
                width: 100%;
                height: 710px;
                padding: 40px;
                padding-left: 100px;
                box-sizing: border-box;
                text-align: center;
                position: relative;
                page-break-inside: avoid;
            }
        .logo {
            height: 90px;
            margin-bottom: 10px;
        }
        .seal {
            position: absolute;
            bottom: 90px;
            right: 95px;
            height: 150px; width: auto;
        }
        .org-name {
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 10px;
            margin-top: 50px;
            padding-left: 20px;
        }
        .certify-text {
            font-size: 30px;
            margin-top: -10px;
        }
        .member-name {
            font-size: 28px;
            font-weight: bold;
            margin: 12px 0;
        }
        .member-type {
            font-size: 26px;
            margin: 10px 0;
        }
        .date-info {
            font-size: 16px;
            margin: 15px 0;
        }
        .signatures {
            width: 80%;
            margin-top: 40px;
            page-break-inside: avoid;
            padding-left: 30px;
        }
        .sig-block {
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }
        .sig-line {
            border-top: 1px solid #000;
            margin: 20px auto 8px;
            width: 100%;
        }
        .sig-title {
            font-size: 12px;text-transform: uppercase;
            text-align: left;
            line-height: 0.6;
            padding-right: 80px;
        }
        .membership-info {
            font-size: 13px;
            margin-top: 12px;
            position: absolute;
            bottom: 98px;
            border-top: 1px solid #ccc;
            padding-top: 5px;
            page-break-inside: avoid;
            width: 70%;
            left: 140px;
        }
        .membership-info strong{color: #0d7dc1; font-weight: 600;}
        </style>
        </head>
        <body>
        <div class="certificate" style="background-image: url(\'' . $pdf_border . '\'); background-repeat: no-repeat; background-size: cover;">
        <img src="'.$logo_url.'" class="logo" alt="Logo" style="position: absolute; left: 110px; top: 100px;" />

        <div class="org-name" style="color: #0d7dc1;">NON-DESTRUCTIVE TESTING SOCIETY (SINGAPORE)</div>
        <div class="certify-text" style="margin-top: -17px;">This is to certify that</div>
        <div class="member-name" style="color: #0d7dc1; margin-top: -8px;">' . $member_name . '</div>
        <div class="certify-text" style="margin-top: -18px;">has been admitted as an</div>
        <div class="member-name" style="color: #0d7dc1; margin-top: -8px;">' . $membership_type . ' Member</div>
        <div class="certify-text" style="margin-top: -20px;">of the Non-Destructive Testing Society</div>
        <div class="certify-text" style="margin-top: -15px;">(Singapore) on</div>
        <div class="certify-text" style="margin-top: -8px;">' . $member_since . '</div>
        <div class="date-info" style="font-size: 11px; margin-top: -8px;">Given under our hand and Seal</div>

        <table class="signatures">
            <tr>
                <td class="sig-block">
                    <div class="sig-line"></div>
                    <div class="sig-title">' . strtoupper(esc_html($chairman_name)) . ' ' . strtoupper(esc_html($chairman_title)) . '</div>
                </td>
                <td class="sig-block">
                    <div class="sig-line"></div>
                    <div class="sig-title">' . strtoupper(esc_html($president_name)) . ' ' . strtoupper(esc_html($president_title)) . '</div>
                </td>
                <td class="sig-block">
                    <div class="sig-line"></div>
                    <div class="sig-title">' . strtoupper(esc_html($secretary_name)) . ' ' . strtoupper(esc_html($secretary_title)) . '</div>
                </td>
            </tr>
        </table>

        <div class="membership-info">
            <table style="border: 0; width: 100%;">
                <tr>
                    <td>Membership No: <strong>' . $membership_no . '</strong></td>
                    <td>Since: <strong> ' . $member_since . '</strong></td>
                    <td>Expiry: <strong>' . $expiry_date . ' </strong></td>
                </tr>
            </table>
        </div>

        <img src="'.$seal_url.'" class="seal" alt="Seal" />
        </div>
        </body>
        </html>';

        $pdf->loadHtml($html);
        $pdf->render();

        // Save PDF
        $upload_dir = wp_upload_dir();
        $cert_dir   = $upload_dir['basedir'] . '/certificates/';
        $cert_url   = $upload_dir['baseurl'] . '/certificates/';
        if (!file_exists($cert_dir)) {
            if (!wp_mkdir_p($cert_dir)) {
                membership_log_error('Failed to create certificates directory', ['cert_dir' => $cert_dir]);
                wp_send_json_error('Failed to create certificates directory: ' . $cert_dir);
            }
        }

        $certificate_filename = 'user-' . $user_id . '-member-' . $member_id . '.pdf';
        $certificate_path = $cert_dir . '/' . $certificate_filename;
        $certificate_url = $cert_url . '/' . $certificate_filename;

        membership_log_info('Certificate paths generated', [
            'cert_dir' => $cert_dir,
            'cert_url' => $cert_url,
            'certificate_path' => $certificate_path,
            'certificate_url' => $certificate_url,
            'certificate_filename' => $certificate_filename
        ]);

        if (!is_writable($cert_dir)) {
            error_log('Certificates directory not writable: ' . $cert_dir);
            wp_send_json_error('Certificates directory not writable');
        }

        file_put_contents($certificate_path, $pdf->output());
        if (!file_exists($certificate_path)) {
            error_log('Failed to save PDF at: ' . $certificate_path);
            wp_send_json_error('Failed to save PDF file');
        }

        // Retrieve and update membership data
        $membership_data = get_user_meta($user_id, 'membership_data', true);
        $membership_data = is_array($membership_data) ? $membership_data : [];

        // Check if certificate already exists
        foreach ($membership_data as $membership) {
            if (($membership['entry_id'] ?? 0) == $member_id && !empty($membership['certificate_url'])) {
                $existing_url = $membership['certificate_url'];

                // Fix old URLs that contain year/month structure or incorrect paths
                $existing_url = ul_fix_certificate_url($existing_url, $cert_url);

                wp_send_json_success([
                    'certificate_url' => $existing_url,
                    'membership_number' => $membership['membership_number'],
                ]);
            }
        }

        // Generate membership number with membership type
        $membership_number = ul_generate_membership_number($user_id, $member_id, $membership_type);

        // Update membership data
        $membership_data[] = [
            'membership_number' => $membership_number,
            'membership_type' => $membership_type,
            'certificate_url' => $certificate_url,
            'entry_id' => $member_id,
            'expiry_date' => $expiry_date,
        ];

        update_user_meta($user_id, 'membership_data', $membership_data);

        membership_log_info('Certificate generated successfully', [
            'user_id' => $user_id,
            'member_id' => $member_id,
            'membership_type' => $membership_type,
            'certificate_url' => $certificate_url,
            'membership_number' => $membership_number
        ]);

        wp_send_json_success([
            'certificate_url' => $certificate_url,
            'membership_number' => $membership_number,
        ]);

    } catch (Exception $e) {
        membership_log_error('Certificate generation fatal error', [
            'error' => $e->getMessage(),
            'user_id' => isset($user_id) ? $user_id : 'unknown',
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        wp_send_json_error('Certificate generation failed: ' . $e->getMessage());
    }
}