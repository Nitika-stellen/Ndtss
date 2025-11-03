<?php
require_once get_stylesheet_directory() . '/includes/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Define the missing function
function ul_generate_membership_number($user_id, $entry_id) {
    return 'A-' . $entry_id ;
}

add_action('wp_ajax_generate_member_certificate', 'handle_generate_member_certificate');
// Remove wp_ajax_nopriv_ unless non-logged-in users need access
// add_action('wp_ajax_nopriv_generate_member_certificate', 'handle_generate_member_certificate');

function handle_generate_member_certificate() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'generate_certificate_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    $membership_type = isset($_POST['membership_type']) ? sanitize_text_field($_POST['membership_type']) : '';

    if (!$user_id || !$member_id) {
        wp_send_json_error('Invalid user or member ID');
    }

    if (!current_user_can('manage_options') && get_current_user_id() !== $user_id) {
        wp_send_json_error('Insufficient permissions');
    }

    $user = get_userdata($user_id);
    if (!$user) {
        wp_send_json_error('User not found');
    }

    // Fallback to user meta if membership_type not provided
    $membership_type = $membership_type ?: get_user_meta($user_id, 'member_type', true) ?: 'Corporate';
    $member_since = get_user_meta($user_id, 'membership_approval_date', true) ?: date('d F Y');
    $expiry_date = get_user_meta($user_id, 'membership_expiry_date', true) ?: date('d F Y', strtotime('+1 year'));
    $member_name = strtoupper(esc_html($user->display_name));
    $membership_no = 'A-'.esc_html($member_id); // Using member_id as per your code; customize if needed

    // Image paths and URLs
    $logo_path = get_stylesheet_directory() . '/assets/logos/ndtss-logo.png';
    $seal_path = get_stylesheet_directory() . '/assets/logos/seal.png';
    $logo_url = esc_url(get_stylesheet_directory_uri() . '/assets/logos/ndtss-logo.png');
    $seal_url = esc_url(get_stylesheet_directory_uri() . '/assets/logos/seal.png');
    $pdf_border_url = esc_url(get_stylesheet_directory_uri() . '/assets/logos/pdf-border.jpg'); // Fixed filename

    // Check if images exist
    if (!file_exists($logo_path)) {
        error_log('Dompdf: Logo not found at ' . $logo_path);
        wp_send_json_error('Logo image not found');
    }
    if (!file_exists($seal_path)) {
        error_log('Dompdf: Seal not found at ' . $seal_path);
        wp_send_json_error('Seal image not found');
    }

    // PDF setup
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'Times-Roman');
    $options->set('isRemoteEnabled', true);
    $options->set('isPhpEnabled', false);
    $pdf = new Dompdf($options);
    $pdf->setPaper('A4', 'landscape');
    $pdf_border = esc_url(get_stylesheet_directory_uri() . '/assets/logos/pdf-bordern.jpg');
  $html = '
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
<style>
    @page { size: A4 landscape; margin: 0; }
    body {
        margin: 0;
        padding: 0;
          font-family: "Poppins", sans-serif;
        position: relative;width: 87%;
    }
    body *{  font-family: "Poppins", sans-serif !important;}
    .certificate {
        background-repeat: no-repeat; 
        background-size: cover;
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

<div class="certificate" style="background: url(' . $pdf_border . ') no-repeat top / 100%;">
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
                <div class="sig-title">M.S.VETRISELVAN CHAIRMAN-MEMBERSHIP (NDTSS)</div>
            </td>
            <td class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-title">BABU SAJEESH KUMAR PRESIDENT (NDTSS)</div>
            </td>
            <td class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-title">P.PUGALENDHI HONORARY SECRETARY (NDTSS)</div>
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
</div>';

    try {
        $pdf->loadHtml($html);
        $pdf->render();

        // Save PDF
        $upload_dir = wp_upload_dir();
        $cert_dir   = trailingslashit($upload_dir['basedir']) . 'certificates/';
        $cert_url   = trailingslashit($upload_dir['baseurl']) . 'certificates/';
        // $cert_dir = $upload_dir['path'] . '/certificates';
        // $cert_url = $upload_dir['url'] . '/certificates';
        if (!file_exists($cert_dir)) {
            if (!wp_mkdir_p($cert_dir)) {
                error_log('Failed to create certificates directory: ' . $cert_dir);
                wp_send_json_error('Failed to create certificates directory');
            }
        }

        $certificate_filename = 'user-' . $user_id . '-member-' . $member_id . '.pdf';
        $certificate_path = $cert_dir . '/' . $certificate_filename;
        $certificate_url = $cert_url . '/' . $certificate_filename;

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
                wp_send_json_success([
                    'certificate_url' => $membership['certificate_url'],
                    'membership_number' => $membership['membership_number'],
                ]);
            }
        }

        // Generate membership number
        $membership_number = ul_generate_membership_number($user_id, $member_id);

        // Update membership data
        $membership_data[] = [
            'membership_number' => $membership_number,
            'membership_type' => $membership_type,
            'certificate_url' => $certificate_url,
            'entry_id' => $member_id,
            'expiry_date' => $expiry_date,
        ];

        update_user_meta($user_id, 'membership_data', $membership_data);

        wp_send_json_success([
            'certificate_url' => $certificate_url,
            'membership_number' => $membership_number,
        ]);

    } catch (Exception $e) {
        error_log('Dompdf error for user ' . $user_id . ': ' . $e->getMessage());
        wp_send_json_error('PDF generation failed: ' . $e->getMessage());
    }
}