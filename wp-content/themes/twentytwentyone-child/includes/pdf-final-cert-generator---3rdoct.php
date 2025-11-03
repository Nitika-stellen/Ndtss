<?php
require_once get_stylesheet_directory() . '/includes/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

function generate_final_certificate_pdf($exam_entry_id, $marks_entry_id, $method) {
    global $wpdb;

    // Validate inputs
    if (!is_numeric($exam_entry_id) || !is_numeric($marks_entry_id) || $exam_entry_id <= 0 || $marks_entry_id <= 0 || empty($method)) {
        $current_time = current_time('mysql', true); // Includes time for logging, but not stored
        error_log("Invalid input parameters at $current_time: exam_entry_id=$exam_entry_id, marks_entry_id=$marks_entry_id, method=$method, user_id=" . get_current_user_id());
        ob_end_clean();
        return false;
    }

    ob_start();

    // Fetch entries
    $timezone = wp_timezone(); // Use WordPress-configured timezone for date calculations
    $entry = GFAPI::get_entry($exam_entry_id);
    $marks_entry = GFAPI::get_entry($marks_entry_id);
    if (is_wp_error($entry) || empty($entry) || is_wp_error($marks_entry) || empty($marks_entry)) {
        $current_time = current_time('mysql', true);
        error_log("Entry not found at $current_time: exam_entry_id=$exam_entry_id, marks_entry_id=$marks_entry_id, user_id=" . get_current_user_id());
        ob_end_clean();
        return false;
    }

    $exam_form = GFAPI::get_form($entry['form_id']);
    if (is_wp_error($exam_form) || empty($exam_form)) {
        $current_time = current_time('mysql', true);
        error_log("Exam form not found at $current_time for form ID: {$entry['form_id']}, user_id=" . get_current_user_id());
        ob_end_clean();
        return false;
    }

    // Extract data
    $exam_level = strtolower(trim($marks_entry['1'] ?? ''));
    $sector = '';
    $level = '';
    $scope = [];
    $signature_path = '';

    // Get form ID to determine which fields to use
    $form_id = $entry['form_id'];

    if ($form_id == 31) {
        // Form 31 (Renewal/Recertification by Exam) - use specific field IDs
        $sector = $entry['4'] ?? ''; // Field 4 for sector
        $level = $entry['5'] ?? '';  // Field 5 for level
        
        // Field 3 for scope
        $scope_value = $entry['3'] ?? '';
        if (!empty($scope_value)) {
            // Check if it's a multi-select field
            if (is_array($scope_value)) {
                $scope = $scope_value;
            } else {
                $scope = array_filter(explode(',', $scope_value));
            }
        }
        
        // Field 29 for signature
        $signature_value = $entry['29'] ?? '';
        if (!empty($signature_value)) {
            $upload_dir = wp_upload_dir();
            $signature_url = $upload_dir['baseurl'] . '/gravity_forms/signatures/' . $signature_value;
            $signature_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $signature_url);
        }
    } else {
        // Form 15 (Initial Exam) - use CSS class and label-based detection
        foreach ($exam_form['fields'] as $field) {
            $field_id = $field->id;
            $label = trim($field->label);
            $value = $entry[$field_id] ?? '';

            if (strpos($field->cssClass, 'sector_' . strtolower($method)) !== false) {
                $sector = $value;
            }

            if (stripos($label, 'Level for ' . $method) !== false) {
                $level = $value;
            }

            if (strpos($field->cssClass, 'scope_' . strtolower($method)) !== false) {
                foreach ($field->inputs as $input) {
                    $input_id = $input['id'];
                    if (!empty($entry[$input_id])) {
                        $scope[] = $entry[$input_id];
                    }
                }
            }

            if ($field_id == 115 && !empty($value)) {
                $upload_dir = wp_upload_dir();
                $signature_url = $upload_dir['baseurl'] . '/gravity_forms/signatures/' . $value;
                $signature_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $signature_url);
            }
        }
    }
    

    // Process signature
    $sign = '';
if (!empty($signature_path) && file_exists($signature_path)) {
    $signature_cropped_path = $upload_dir['basedir'] . '/certificates/cropped_signature_' . $entry['id'] . '.png';
    $signature_cropped_url  = $upload_dir['baseurl'] . '/certificates/cropped_signature_' . $entry['id'] . '.png';

    if (function_exists('crop_signature_image')) {
        if (crop_signature_image($signature_path, $signature_cropped_path)) {
            $sign = $signature_cropped_url; // store URL instead of path
        }
    } else {
        $current_time = current_time('mysql', true);
        error_log("crop_signature_image function not found at $current_time, user_id=" . get_current_user_id());
    }
}

    // echo $sign;
    // die;

    // Get user data first
    $user_id = $entry['created_by'] ?? get_current_user_id();
    $user_data = get_userdata($user_id);
    $candidate_name = $user_data ? $user_data->display_name : 'N/A';
    $candidate_reg_number = get_user_meta($user_id, 'candidate_reg_number', true);
    if (empty($candidate_reg_number)) {
        error_log("Candidate registration number not found for user ID: $user_id");
        $candidate_reg_number = 'N/A';
    }

    // Determine certificate type and suffix based on form ID and field values
    $certificate_suffix = '';
    $form_id = $entry['form_id'];
    
    if ($form_id == 15) {
        // Initial certificate - no suffix
        $certificate_suffix = '';
    } elseif ($form_id == 31) {
        // Check field ID 27 for renewal vs recertification
        $field_27_value = strtoupper(trim($entry['27'] ?? ''));
        
        if ($field_27_value === 'renewal') {
            // Renewal certificate - add -01 suffix
            $certificate_suffix = '-01';
        } elseif ($field_27_value === 'RECERT') {
            // Recertification certificate - add -02 suffix
            $certificate_suffix = '-02';
        }
    }
    
    // Generate certificate number with appropriate suffix
    $certificate_number = $candidate_reg_number . $certificate_suffix;
    // echo $certificate_number;
    // die;

    gform_update_meta($marks_entry_id, '_final_certificate_number_' . sanitize_title($method), $certificate_number);

    // Generate dates with DateTime, storing only date
    $issue_datetime = new DateTime('now', $timezone); // Timezone for calculation consistency
    $issue_date = $issue_datetime->format('d.m.Y');
    $issue_date_sql = $issue_datetime->format('Y-m-d'); // Date only
    $expiry_datetime = clone $issue_datetime;
    $expiry_datetime->modify('+5 years');
    $expiry_date = $expiry_datetime->format('d.m.Y');
    $expiry_date_sql = $expiry_datetime->format('Y-m-d'); // Date only

    // Prepare PDF file
    $upload_dir = wp_upload_dir();
    $dir = $upload_dir['basedir'] . '/certificates';
    wp_mkdir_p($dir);
    if (!is_writable($dir)) {
        $current_time = current_time('mysql', true);
        error_log("Directory not writable at $current_time: $dir, user_id=" . get_current_user_id());
        ob_end_clean();
        return false;
    }
    $file_name = "final_certificate_{$exam_entry_id}_{$marks_entry_id}_" . sanitize_title($method) . ".pdf";
    $file_path = "$dir/$file_name";
    $file_url = $upload_dir['baseurl'] . "/certificates/$file_name?v=" . time();


    // Generate PDF with DOMPDF
    $html = '<head>
    <style>
        @page { margin: 20px; }
        body { margin: 0; padding: 15px; border: 2px solid #494949; width: 278mm; height: 190mm; box-sizing: border-box; }
    </style>
    </head>
    <div style="position:relative; font-size:11pt;">
        <img src="' . get_stylesheet_directory_uri() . '/assets/logos/gvf-pdf.jpg" style="position:absolute; top: -40px; left:0; width:100%; opacity:0.6; height: 100%; z-index:-1;"/>
        <div style="text-align:center; margin-top:10px;">
            <table style="width:100%;"><tr><td style="text-align:left;"><img src="' . get_stylesheet_directory_uri() . '/assets/logos/logondtss.png" style="height:56px;"/></td><td style="text-align:right;"><img src="' . get_stylesheet_directory_uri() . '/assets/logos/icndt.jpg" style="height:56px;"/></td></tr></table>
            <div style="text-align:center; margin-top:60px;">
                <h2 style="color:#3453a5; margin-top: -30px; margin-bottom: 0; padding: 0;" class="main_title"> NON-DESTRUCTIVE TESTING SOCIETY(SINGAPORE)</h2>
                <p style="margin-bottom: 0; padding: 0;width: 100%; text-align: center;">SGNDT Number: <span style="color: #1712fd; padding-top: 10px;">' . $candidate_reg_number . ' Issue 0</span></p>
                <p style="margin-bottom: 0; padding: 0;margin-top: 15px;">This is to certify that</p>
                <h3 style="color: #0001fc; margin-top: 0; padding-top: 5px; font-size: 28px; font-weight: 600;">' . strtoupper($candidate_name) . '</h3>
                <p style="border-top: 1px solid #444; margin-top: 0px; padding-top: 15px; text-align: center;width: fit-content; margin-inline: auto;">has met the established and published Requirements of NDTSS in accordance with ISO 9712:2021 <br>and certified in the following Non-destructive Testing Methods</p>
            </div>
            <div style="margin-top:-0px; text-align:center;">
                <p style="display: inline;"><i>Signature of Certified Individual</i></p>
                <img src="' . $sign . '" style="height:50px; margin-top:5px; border-bottom: 1px solid #000; display: inline;"/>
            </div>
            <table style="width:100%; border-collapse:collapse; text-align: center; margin-top:30px; border-color: #bdbdbd;" border="1" cellpadding="4">
                <thead style="background: #f7f7f7;"><tr><th>Method</th><th>Cert No</th><th>Sector</th><th>Level</th><th>Scope</th><th>Issue Date</th><th>Expiry Date</th></tr></thead>
                <tbody><tr style="text-align: center;"><td style="color: #1712fd;">' . $method . '</td><td style="color: #1712fd;">' . $certificate_number . '</td><td style="color: #1712fd;">' . $sector . '</td><td style="color: #1712fd;">' . $exam_level . '</td><td style="color: #1712fd;">' . (!empty($scope) && is_array($scope) ? implode(', ', $scope) : '') . '</td><td style="color: #1712fd;">' . $issue_date . '</td><td style="color: #1712fd;">' . $expiry_date . '</td></tr></tbody>
            </table>
            <div style="position: absolute; right: 50px; bottom: 30px;">
                   <img src="' . get_stylesheet_directory_uri() . '/assets/logos/seal.png" 
     style="height:160px; width:160px; object-fit: contain; z-index: 99;" 
     alt="SGNDT Seals"/>
                </div>
            <div style="margin-top:40px; text-align:left; position: absolute; bottom: 80px;width:100%;">
                <table style="width:100%;"><tr><td style="text-align:left; width: 33%; padding: 15px;"><strong style="font-size: 12px;">CHAIRMAN / VICE CHAIRMAN</strong><br><strong style="font-size: 12px;">CERTIFICATION COMMITTEE</strong></td><td style="text-align:left; width: 34%;padding: 15px;"><strong style="font-size: 12px;">AUTHORIZED SIGNATORY</strong><br><strong style="font-size: 12px;">NDTSS</strong></td><td style="text-align:center; width: 33%;padding: 15px;">&nbsp;</td></tr><tr><td style="text-align:left;padding: 15px;">__________________</td><td style="text-align:left;padding: 15px;">__________________</td><td style="text-align:center;padding: 0px;"></td></tr></table>
                <div style="margin-top:60px; text-align:left; position: absolute; bottom: 0px;width:100%;"><table style="width:100%;"><tr><td style="text-align:left; width: 33%; padding: 15px; font-size: 12px;">Form No: NDTSS-QMS-FM-024</td><td style="text-align:center; width: 34%;padding: 15px; font-size: 12px;">Refer overleaf for Notes, details of certification sector and scope</td><td style="text-align:right; width: 33%; padding: 15px; font-size: 12px;"> Rev. 5 (' . date('d F Y') . ')</td></tr></table></div>
            </div>
        </div>
    </div>';

    $html_notes = '<div style="font-size:10pt;"><img src="' . get_stylesheet_directory_uri() . '/assets/logos/gvf-pdf.jpg" style="position:absolute; top: -40px; left:0; width:100%; opacity:0.6; height: 100%; z-index:-1;"/><div style="width: 100%; display: block; clear: both; margin-top: 30px;"><div style="width: 35%;float: left; padding-right: 10px;"><p style="text-align: left; font-size: 16px; font-weight: 600;">Abbreviation for Certification Sector</p><table border="1" cellpadding="4" style="width:100%; border-collapse:collapse;"><tr><th style="width: 65px; background: #f7f7f7;">Industry<br> Sector</th><th style="background: #f7f7f7;">Details</th></tr><tr><td style="text-align: center;">s</td><td>Pre- & In-service Inspection which includes Manufacturing</td></tr><tr><td style="text-align: center;">a</td><td>Aerospace</td></tr><tr><td style="text-align: center;">r</td><td>Railway Maintenance</td></tr><tr><td style="text-align: center;">m</td><td>Manufacturing</td></tr><tr><td style="text-align: center;">ci, me, el</td><td>Civil, Mechanical, Electrical (TT)</td></tr></table><table border="1" cellpadding="4" style="width:100%; border-collapse:collapse;"><tr><th style="text-align: center;width: 65px;background: #f7f7f7;">Product <br>Sector</th><th style="background: #f7f7f7;">Details</th></tr><tr><td style="text-align: center;">w</td><td>Welds</td></tr><tr><td style="text-align: center;">c</td><td>Castings</td></tr><tr><td style="text-align: center;">wp</td><td>Wrought Products </td></tr><tr><td style="text-align: center;">t</td><td>Tubes and Pipes</td></tr><tr><td style="text-align: center;">f</td><td>Forgings</td></tr><tr><td style="text-align: center;">frp</td><td>Reinforced Plastics</td></tr></table></div><div style="width: 62%;float: right; padding-left: 10px;"><p style="text-align: left; font-size: 16px; padding-inline: 15px;font-weight: 600;">Abbreviation for Scope / Technique</p><table border="1" cellpadding="4" style="width:100%; border-collapse:collapse;"><tr><th style="text-align: left;background: #f7f7f7;">Scope</th><th style="text-align: left;background: #f7f7f7;">Details</th></tr><tr><td>F / P / L / ML</td><td>Fixed / Portable Equipment / Line System / Magnetic Flux Leakage</td></tr><tr><td>X / G / DR / CR</td><td>X-ray / Gamma-ray / Digital Radiography / Computed Radiography</td></tr><tr><td>PL / P / T / N / NZ / PAUT / TOFD / AUT</td><td>Plate / Pipe / T Joint / Node / Nozzle Weld, Phased Array, Time of Flight, Auto UT</td></tr><tr><td>S / W / Fe / NFe / FP</td><td>Seamless, Welded, Ferrous, Non-Ferrous, Flat Plate</td></tr><tr><td>Tu</td><td>Tubes (ET)</td></tr><tr><td>D / R</td><td>Direct / Remote (VT)</td></tr><tr><td>V / FL</td><td>Visible / Fluorescent (PT / MT)</td></tr><tr><td>TT / LM</td><td>Thickness Testing / Lamination (UT)</td></tr><tr><td>Pa / Ac</td><td>Passive / Active (Thermal Infrared Testing)</td></tr></table></div></div><div style="clear: both; width: 100%; display: block;"></div><table style="width:100%;"><tr><td><div style="width: 100%; display: block; max-width: 100%;"><h4 style="margin-top:20px; display: block; width: 100%; font-size: 20px; margin-bottom: 8px;">Notes:</h4><ol style="padding-left: 15px; margin-top: 0;"><li style="margin-bottom: 8px;">Candidate appearing in Industrial Sector “s” will be given 3 specimens with a mixture of welding and casting or forging or wrought products as per ISO 9712:2021.</li><li style="margin-bottom: 8px;">For UT, scope applies to product sector welds only.</li><li style="margin-bottom: 8px;">The SAC accreditation mark indicates accreditation certificate number PC-2017-03.</li><li style="margin-bottom: 8px;">This certificate is property of NDTSS and not valid without SGNDT seal.</li><li style="margin-bottom: 8px;">NDTSS is accredited by SAC under ISO/IEC 17024:2012.</li><li style="margin-bottom: 8px;">This certificate is issued as per NDTSS/SGNDT OM-001 and ISO 9712:2021.</li></ol></div></td></tr></table><div style="text-align:right; font-style:italic; font-size:9pt; margin-top:20px;">Form No: NDTSS-QMS-FM-024  Rev. 5 (' . date('d F Y') . ')</div></div>';

    $full_html = $html . '<div style="page-break-after: always;"></div>' . $html_notes;

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->loadHtml($full_html);

    try {
        $dompdf->render();
    } catch (Exception $e) {
        $current_time = current_time('mysql', true);
        error_log("DOMPDF rendering failed at $current_time: " . $e->getMessage() . ", user_id=" . get_current_user_id());
        ob_end_clean();
        return false;
    }

    // Save PDF to file
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    try {
        file_put_contents($file_path, $dompdf->output());
        if (!file_exists($file_path)) {
            throw new Exception("File not created at $file_path");
        }
    } catch (Exception $e) {
        $current_time = current_time('mysql', true);
        error_log("Failed to save PDF to $file_path at $current_time: " . $e->getMessage() . ", user_id=" . get_current_user_id());
        ob_end_clean();
        return false;
    }

    // Save certificate data to wp_sgndt_final_certifications
    $table_final_certifications = $wpdb->prefix . 'sgndt_final_certifications';
    $user_id = $entry['created_by'] ?? get_current_user_id();

    if (!$wpdb->get_var("SHOW TABLES LIKE '$table_final_certifications'")) {
        $current_time = current_time('mysql', true);
        error_log("Table $table_final_certifications does not exist at $current_time, user_id=" . get_current_user_id());
        ob_end_clean();
        return false;
    }

    $insert_data = [
        'user_id' => $user_id,
        'exam_entry_id' => $exam_entry_id,
        'marks_entry_id' => $marks_entry_id,
        'method' => $method,
        'level' => $exam_level,
        'sector' => $sector,
        'scope' => (!empty($scope) && is_array($scope) ? implode(', ', $scope) : ''),
        'certificate_number' => $certificate_number,
        'issue_date' => $issue_date_sql,
        'expiry_date' => $expiry_date_sql,
        'certificate_link' => $file_url,
        'status' => 'issued',
        'validity_period' => ($form_id == 15) ? 'initial' : (($field_27_value === 'RENEWAL') ? 'renewal' : (($field_27_value === 'RECERT') ? 'recertification' : 'initial'))
    ];
    $insert_formats = ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

    $insert_result = $wpdb->insert($table_final_certifications, $insert_data, $insert_formats);
    if ($insert_result === false) {
        $current_time = current_time('mysql', true);
        error_log("Failed to insert final certificate data at $current_time: " . $wpdb->last_error . ", user_id=" . get_current_user_id());
        ob_end_clean();
        return false;
    }

    $final_certification_id = $wpdb->insert_id;

    // Trigger action for certificate generation (for renewal status updates)
    do_action('certificate_generated', $final_certification_id, $certificate_number, $user_id, $cert, $exam_entry_id);

    // Save certificate metadata
    $certificate_data = [
        'url' => $file_url,
        'path' => $file_path,
        'generated_at' => $issue_date_sql, // Using date only for consistency
        'exam_entry_id' => $exam_entry_id,
        'marks_entry_id' => $marks_entry_id,
        'method' => $method,
        'issued_by' => $user_id,
        'final_certification_id' => $final_certification_id
    ];
    gform_update_meta($marks_entry_id, '_certification_meta_' . sanitize_title($method), $certificate_data);

    // Send notification with fallback
    $center_name = $entry['833'] ?? 'N/A';
    $center_post = get_page_by_title($center_name, OBJECT, 'exam_center');
    if ($center_post) {
        gform_update_meta($exam_entry_id, '_linked_exam_center', $center_post->ID);
        if (function_exists('send_exam_certificate')) {
            send_exam_certificate($exam_entry_id, $marks_entry_id, $center_post, $certificate_data, $method, '', 'certificate');
        } else {
            $current_time = current_time('mysql', true);
            error_log("send_exam_certificate function not found at $current_time, user_id=" . get_current_user_id());
        }
    } else {
        $current_time = current_time('mysql', true);
        error_log("Exam center not found at $current_time for name: $center_name, user_id=" . get_current_user_id());
    }

    ob_end_clean();
    return true; // Success
}

function send_exam_certificate($entry_id, $marks_entry_id, $center_post, $certificate_data, $method, $result) {
    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry)) return;

    $log = [];

    $user_id = $entry['created_by'];
    $user_data = get_userdata($user_id);
   $candidate_email = $user_data && is_email($user_data->user_email) ? $user_data->user_email : '';
    $employer_email  = rgar($entry, '864.1');
    $personal_email  = rgar($entry, '864.2');
    $field_863_value = rgar($entry, '863');
    $certificate_url = $certificate_data['url'] ?? '';
    $issue_date = date('d.m.Y', strtotime($certificate_data['generated_at'] ?? 'now'));

    $center_name = get_the_title($center_post->ID);
    $center_admin_id = get_post_meta($center_post->ID, '_center_admin_id', true);
    $center_admin = get_userdata($center_admin_id);

    $admin_users = get_users([
        'role'   => 'administrator',
        'fields' => ['user_email'],
    ]);

    $to = [];
    if (!empty($personal_email) && is_email($candidate_email)) {
        $to[] = sanitize_email($candidate_email);
    }
    if (!empty($employer_email) && is_email($field_863_value)) {
        $to[] = sanitize_email($field_863_value);
    }
    $to = array_unique($to);

    // Candidate Email
    $sent_to_candidate = false;
    if (!empty($to)) {
        $subject = 'SGNDT Final Certificate Issued';
        $body = '
        Dear ' . esc_html($candidate_name) . ',<br><br>
        Your final certificate has been issued<br><br>
        <strong>Method:</strong> ' . esc_html($method) . '<br>
        <strong>Date of Issue:</strong> ' . esc_html($issue_date) . '<br><br>
        <a href="' . esc_url($certificate_url) . '" target="_blank">Download Certificate</a><br><br>
        Best regards,<br>
        NDTSS Certification Team
        ';
        $message = get_email_template($subject, $body);

        add_filter('wp_mail_content_type', function () { return 'text/html'; });
        $sent_to_candidate = wp_mail($to, $subject, $message);
        remove_filter('wp_mail_content_type', function () { return 'text/html'; });

        $log['candidate_email'] = [
            'email' => $to,
            'status' => $sent_to_candidate ? 'sent' : 'failed',
            'timestamp' => current_time('mysql'),
        ];
    }

    // -------------------------
    // 2. Admin Emails
    // -------------------------
    $admin_emails = [];
    if ($center_admin && is_email($center_admin->user_email)) {
        $admin_emails[] = $center_admin->user_email;
    }
    foreach ($admin_users as $admin_user) {
        if (is_email($admin_user->user_email)) {
            $admin_emails[] = $admin_user->user_email;
        }
    }

    $admin_emails = array_unique($admin_emails);
    $sent_admin_emails = [];

    if (!empty($admin_emails)) {
        $subject = '📢 Candidate Final Certificate Issued – ' . esc_html($candidate_name);
        $body = '
        <p><strong>Candidate Name:</strong> ' . esc_html($candidate_name) . '</p>
        <p><strong>Method:</strong> ' . esc_html($method) . '</p>
        <p><strong>Exam Center:</strong> ' . esc_html($center_name) . '</p>
        <p><strong>Date of Issue:</strong> ' . esc_html($issue_date) . '</p>
        <p><a href="' . esc_url($certificate_url) . '" target="_blank">Download Certificate</a></p>
        ';

        add_filter('wp_mail_content_type', function () { return 'text/html'; });
        $sent_to_admins = wp_mail($admin_emails, $subject, get_email_template($subject, $body));
        remove_filter('wp_mail_content_type', function () { return 'text/html'; });

        foreach ($admin_emails as $email) {
            $sent_admin_emails[] = [
                'email' => $email,
                'status' => $sent_to_admins ? 'sent' : 'failed',
                'timestamp' => current_time('mysql'),
            ];
        }

        $log['admin_emails'] = $sent_admin_emails;
    }

    // -------------------------
    // 3. Save Mail Log to Entry Meta
    // -------------------------
    $meta_key = '_final_certificate_email_log_' . sanitize_title($method);
    gform_update_meta($marks_entry_id, $meta_key, $log);
}

function crop_signature_image($source_path, $target_path) {
    if (!extension_loaded('gd')) return false;

    $image = imagecreatefrompng($source_path);
    imagesavealpha($image, true);
    imagealphablending($image, false);

    $width = imagesx($image);
    $height = imagesy($image);

    $top = $left = 0;
    $bottom = $height;
    $right = $width;

    // Get bounds
    $found = false;
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;
            if ($alpha < 127) {
                $top = $y;
                $found = true;
                break 2;
            }
        }
    }

    for ($y = $height - 1; $y >= 0; $y--) {
        for ($x = 0; $x < $width; $x++) {
            $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;
            if ($alpha < 127) {
                $bottom = $y;
                break 2;
            }
        }
    }

    for ($x = 0; $x < $width; $x++) {
        for ($y = $top; $y <= $bottom; $y++) {
            $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;
            if ($alpha < 127) {
                $left = $x;
                break 2;
            }
        }
    }

    for ($x = $width - 1; $x >= 0; $x--) {
        for ($y = $top; $y <= $bottom; $y++) {
            $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;
            if ($alpha < 127) {
                $right = $x;
                break 2;
            }
        }
    }

    $crop_width = $right - $left + 1;
    $crop_height = $bottom - $top + 1;

    $new_img = imagecreatetruecolor($crop_width, $crop_height);
    imagesavealpha($new_img, true);
    $trans_colour = imagecolorallocatealpha($new_img, 0, 0, 0, 127);
    imagefill($new_img, 0, 0, $trans_colour);

    imagecopy($new_img, $image, 0, 0, $left, $top, $crop_width, $crop_height);
    imagepng($new_img, $target_path, 9);

    imagedestroy($image);
    imagedestroy($new_img);

    // Return public URL instead of file path:
    $upload_dir = wp_upload_dir();
    $public_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $target_path);

    return $public_url;
}

