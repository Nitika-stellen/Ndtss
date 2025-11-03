<?php
require_once get_stylesheet_directory() . '/includes/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

function generate_exam_certificate_pdf_dompdf($exam_entry_id, $marks_entry_id, $method) {
    global $wpdb;

    // Validate inputs
    if (!is_numeric($exam_entry_id) || !is_numeric($marks_entry_id) || empty($method)) {
        error_log("Invalid input parameters: exam_entry_id=$exam_entry_id, marks_entry_id=$marks_entry_id, method=$method");
        return false;
    }

    ob_start();

    // Fetch entries with error handling
    $exam_entry = GFAPI::get_entry($exam_entry_id);
    if (is_wp_error($exam_entry) || empty($exam_entry)) {
        ob_end_clean();
        error_log("Exam entry not found or invalid for ID: $exam_entry_id");
        return false;
    }

    $exam_form = GFAPI::get_form($exam_entry['form_id']);
    if (is_wp_error($exam_form) || empty($exam_form)) {
        ob_end_clean();
        error_log("Exam form not found or invalid for form ID: {$exam_entry['form_id']}");
        return false;
    }

    $marks_entry = GFAPI::get_entry($marks_entry_id);
    if (is_wp_error($marks_entry) || empty($marks_entry)) {
        ob_end_clean();
        error_log("Marks entry not found or invalid for ID: $marks_entry_id");
        return false;
    }

    // Get candidate registration number from user meta
    $user_id = $exam_entry['created_by'] ?? get_current_user_id();
    
    $user_data = get_userdata($user_id);
    $candidate_name = $user_data ? $user_data->display_name : 'N/A';
    $candidate_reg_number = get_user_meta($user_id, 'candidate_reg_number', true);
    if (empty($candidate_reg_number)) {
        error_log("Candidate registration number not found for user ID: $user_id");
        $candidate_reg_number = 'N/A';
    }

    // Ensure critical fields exist
    $exam_level = isset($marks_entry['1']) ? strtolower(trim($marks_entry['1'])) : '';
    if (empty($exam_level)) {
        error_log("Exam level not found in marks entry ID: $marks_entry_id");
        $exam_level = 'unknown';
    }

    $sector = '';
    $exam_status = 'Initial';
    $scope = [];
    foreach ($exam_form['fields'] as $field) {
        $field_id = $field->id;
        $label = trim($field->label);
        $value = $exam_entry[$field_id] ?? '';

        if (stripos($label, 'sector for ' . strtolower(trim($method))) !== false) {
            $sector = $value;
        }
        if (strpos($field->cssClass, 'scope_' . strtolower($method)) !== false) {
            foreach ($field->inputs as $input) {
                $input_id = $input['id'];
                if (!empty($exam_entry[$input_id])) {
                    $scope[] = $exam_entry[$input_id];
                }
            }
        }

        if ($sector && $exam_status) {
            break;
        }
    }

    $method_slug = sanitize_title($method);
    $issue_date = date('d.m.Y');
    $issue_date_sql = current_time('mysql');

    // Generate unique cert_number with a maximum attempt limit
    $table_certifications = $wpdb->prefix . 'sgndt_certifications';
    $base_cert_number = 'SGNDT-' . $candidate_reg_number . '-' . strtoupper($method_slug);
    $cert_number = $base_cert_number;
    $suffix = 1;
    $max_attempts = 100;

    // Verify table exists to avoid runtime errors
    if (!$wpdb->get_var("SHOW TABLES LIKE '$table_certifications'")) {
        error_log("Table $table_certifications does not exist");
        ob_end_clean();
        return false;
    }

    while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_certifications WHERE cert_number = %s", $cert_number)) && $suffix <= $max_attempts) {
        $cert_number = $base_cert_number . '-' . $suffix;
        $suffix++;
    }

    if ($suffix > $max_attempts || $wpdb->last_error) {
        error_log("Error generating unique cert_number or max attempts exceeded: " . $wpdb->last_error);
        ob_end_clean();
        return false;
    }

    // Build address
    $address_parts = array_filter([
        $exam_entry['728'] ?? '',
        $exam_entry['787.1'] ?? '',
        $exam_entry['787.2'] ?? '',
        $exam_entry['731'] ?? '',
        $exam_entry['788'] ?? '',
    ]);
    $full_address = !empty($address_parts) ? implode(', ', $address_parts) : 'N/A';

    // Fetch examiners
    $assigned_examiners = (array) gform_get_meta($exam_entry_id, '_assigned_examiners', true);
    $examiner_names = [];
    foreach ($assigned_examiners as $examiner_id) {
        $user = get_userdata($examiner_id);
        if ($user) {
            $first_name = get_user_meta($examiner_id, 'first_name', true);
            $last_name = get_user_meta($examiner_id, 'last_name', true);
            $full_name = trim("{$first_name} {$last_name}");
            $examiner_names[] = $full_name ?: $user->display_name;
        }
    }
    $examiner_list = !empty($examiner_names) ? implode(', ', $examiner_names) : 'N/A';

    // Fetch invigilators
    $assigned_invigilators = (array) gform_get_meta($exam_entry_id, '_assigned_invigilators', true);
    $invigilator_names = [];
    foreach ($assigned_invigilators as $invigilator_id) {
        $user = get_userdata($invigilator_id);
        if ($user) {
            $first_name = get_user_meta($invigilator_id, 'first_name', true);
            $last_name = get_user_meta($invigilator_id, 'last_name', true);
            $full_name = trim("{$first_name} {$last_name}");
            $invigilator_names[] = $full_name ?: $user->display_name;
        }
    }
    $invigilator_list = !empty($invigilator_names) ? implode(', ', $invigilator_names) : 'N/A';

    // Get exam date
    $invigilator_data = gform_get_meta($exam_entry_id, '_invigilator_update_record', true);
    $exam_dates = get_exam_dates_by_method($invigilator_data, $method);
    $exam_date = $exam_dates[0] ?? 'N/A';

    // Get previous passed subjects (within 2 years)
    $table_subject_marks = $wpdb->prefix . 'sgndt_subject_marks';
    $passed_subjects = [];
    $previous_results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT sm.subject_name, sm.marks_obtained, sm.marks_total, sm.percentage
             FROM $table_subject_marks sm
             JOIN $table_certifications c ON sm.certification_id = c.certification_id
             WHERE c.exam_entry_id = %d AND c.marks_entry_id != %d AND c.method = %s AND sm.percentage >= 70
             AND c.issue_date >= DATE_SUB(NOW(), INTERVAL 2 YEAR)",
            $exam_entry_id, $marks_entry_id, $method
        ),
        ARRAY_A
    );
    if ($wpdb->last_error) {
        error_log("Error fetching previous passed subjects: " . $wpdb->last_error);
        ob_end_clean();
        return false;
    }
    foreach ($previous_results as $result) {
        $passed_subjects[$result['subject_name']] = [
            'name' => $result['subject_name'],
            'obtained' => floatval($result['marks_obtained']),
            'total' => floatval($result['marks_total']),
            'percent' => floatval($result['percentage'])
        ];
    }

    // Determine if this is a retest
    $is_retest = ($exam_status === 'Retest');
    $marks_result = [];
    
    // Check if helper functions exist, if not define them
    if (!function_exists('generate_level_2_table')) {
        include_once get_stylesheet_directory() . '/functions.php';
    }
    
    if (in_array($exam_level, ['level 1', 'level 2'])) {
        $marks_result = generate_level_2_table($marks_entry, $passed_subjects, $is_retest);
    } elseif ($exam_level === 'level 3') {
        $marks_result = generate_level_3_table($marks_entry, $passed_subjects, $is_retest);
    } else {
        $marks_result = [
            'table_html' => '<p>No marks structure available for this level.</p>',
            'overall_result' => 'N/A',
            'retest' => 'N/A',
            'retest_details' => 'N/A',
            'overall_percent' => 'N/A',
            'subjects' => [],
            'failed_subjects' => []
        ];
        error_log("Unknown exam level: $exam_level for marks_entry_id: $marks_entry_id");
    }

    // Validate marks_result
    if (empty($marks_result) || !isset($marks_result['table_html']) || !isset($marks_result['overall_result'])) {
        ob_end_clean();
        error_log("Invalid marks_result data for exam_entry_id: $exam_entry_id, marks_entry_id: $marks_entry_id");
        return false;
    }

    $marks_html = $marks_result['table_html'];
    $overall_result = $marks_result['overall_result'];

    // Save certification data
    $user_id = $exam_entry['created_by'] ?? get_current_user_id();
    $retest_flag = (strtoupper($overall_result) === 'FAIL') ? 'yes' : 'no';
    $renewal_flag = 'no';
    $certificate_link = '';
    $status = (strtoupper($overall_result) === 'PASS') ? 'issued' : 'pending';
    $expiry_date_sql = (strtoupper($overall_result) === 'PASS') ? date('Y-m-d H:i:s', strtotime($issue_date_sql . ' +5 years')) : null;
    $retest_eligible_date_sql = (strtoupper($overall_result) === 'FAIL') ? date('Y-m-d H:i:s', strtotime($issue_date_sql . ' +1 month')) : null;

    // Calculate attempt_number before insertion
    $attempt_number = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_certifications WHERE exam_entry_id = %d AND method = %s",
            $exam_entry_id, $method
        )
    ) + 1;
    if ($wpdb->last_error) {
        error_log("Error calculating attempt_number: " . $wpdb->last_error);
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
        'result' => $overall_result,
        'issue_date' => $issue_date_sql,
        'expiry_date' => $expiry_date_sql,
        'retest_flag' => $retest_flag,
        'renewal_flag' => $renewal_flag,
        'certificate_link' => $certificate_link,
        'status' => $status,
        'cert_number' => $cert_number,
        'exam_order_no' => $exam_entry['789'],
        'retest_eligible_date' => $retest_eligible_date_sql
    ];
    
    $insert_formats = [
        '%d', // user_id
        '%d', // exam_entry_id
        '%d', // marks_entry_id
        '%s', // method
        '%s', // level
        '%s', // sector
        '%s', // scope
        '%s', // result
        '%s', // issue_date
        '%s', // expiry_date
        '%s', // retest_flag
        '%s', // renewal_flag
        '%s', // certificate_link
        '%s', // status
        '%s', // cert_number
        '%s', // exam_order_no
        '%s'  // retest_eligible_date
    ];
    
    $insert_result = $wpdb->insert($table_certifications, $insert_data, $insert_formats);

    if ($insert_result === false) {
        error_log("Database insertion failed: " . $wpdb->last_error);
        ob_end_clean();
        return false;
    }

    $certification_id = $wpdb->insert_id;

    // Save subject marks (skip blank subjects and avoid duplicates)
    $processed_subjects = [];
    foreach ($marks_result['subjects'] as $subject) {
        if ($subject['obtained'] == 0 && $subject['total'] == 0) {
            continue;
        }

        // Handle "Basic" section separately based on combined percentage
        if ($subject['name'] === 'Basic') {
            $basic_percent = $subject['percent'];
            if ($basic_percent >= 70) {
                $pass_status = 'Pass';
                $insert_subject = $wpdb->insert(
                    $table_subject_marks,
                    [
                        'certification_id' => $certification_id,
                        'subject_name' => $subject['name'],
                        'marks_obtained' => $subject['obtained'],
                        'marks_total' => $subject['total'],
                        'percentage' => $subject['percent'],
                        'attempt_number' => $attempt_number,
                        'pass_status' => $pass_status
                    ],
                    ['%d', '%s', '%f', '%f', '%f', '%d', '%s']
                );
                if ($insert_subject === false) {
                    error_log("Failed to save subject marks for certification_id $certification_id, subject {$subject['name']}: " . $wpdb->last_error);
                    ob_end_clean();
                    return false;
                }
                $processed_subjects[] = $subject['name'];
            } else {
                $basic_parts = ['Basic Part A', 'Basic Part B', 'Basic Part C'];
                foreach ($basic_parts as $part_name) {
                    if (isset($marks_result['subjects'][$part_name])) {
                        $part_subject = $marks_result['subjects'][$part_name];
                        $pass_status = ($part_subject['percent'] >= 70) ? 'Pass' : 'Fail';
                        $insert_subject = $wpdb->insert(
                            $table_subject_marks,
                            [
                                'certification_id' => $certification_id,
                                'subject_name' => $part_subject['name'],
                                'marks_obtained' => $part_subject['obtained'],
                                'marks_total' => $part_subject['total'],
                                'percentage' => $part_subject['percent'],
                                'attempt_number' => $attempt_number,
                                'pass_status' => $pass_status
                            ],
                            ['%d', '%s', '%f', '%f', '%f', '%d', '%s']
                        );
                        if ($insert_subject === false) {
                            error_log("Failed to save subject marks for certification_id $certification_id, subject {$part_subject['name']}: " . $wpdb->last_error);
                            ob_end_clean();
                            return false;
                        }
                        $processed_subjects[] = $part_subject['name'];
                    }
                }
                continue;
            }
        } else {
            if (!in_array($subject['name'], $processed_subjects)) {
                $pass_status = ($subject['percent'] >= 70) ? 'Pass' : 'Fail';
                $insert_subject = $wpdb->insert(
                    $table_subject_marks,
                    [
                        'certification_id' => $certification_id,
                        'subject_name' => $subject['name'],
                        'marks_obtained' => $subject['obtained'],
                        'marks_total' => $subject['total'],
                        'percentage' => $subject['percent'],
                        'attempt_number' => $attempt_number,
                        'pass_status' => $pass_status
                    ],
                    ['%d', '%s', '%f', '%f', '%f', '%d', '%s']
                );
                if ($insert_subject === false) {
                    error_log("Failed to save subject marks for certification_id $certification_id, subject {$subject['name']}: " . $wpdb->last_error);
                    ob_end_clean();
                    return false;
                }
                $processed_subjects[] = $subject['name'];
            }
        }
    }

    // Initialize DomPDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'Times-Roman');
    $options->set('isRemoteEnabled', true);
    $options->set('isPhpEnabled', false);
    $pdf = new Dompdf($options);
    $pdf->setPaper('A4', 'portrait');

    // Image paths
    $ndtss_logo_path = get_stylesheet_directory() . '/assets/logos/ndtss_logo.png';
    $sgndt_logo_path = get_stylesheet_directory() . '/assets/logos/sgndt_logo.png';
    $ndtss_logo_url = esc_url(get_stylesheet_directory_uri() . '/assets/logos/ndtss_logo.png');
    $sgndt_logo_url = esc_url(get_stylesheet_directory_uri() . '/assets/logos/sgndt_logo.png');

    // Process signature
    $marks_form = GFAPI::get_form($marks_entry['form_id']);
    $signature_path = '';
    $signature_url = '';
    foreach ($marks_form['fields'] as $field) {
        $field_id = $field->id;
        $value = $marks_entry[$field_id] ?? '';
        if ($field_id == 18 && !empty($value)) {
            $upload_dir = wp_upload_dir();
            $signature_url = $upload_dir['baseurl'] . '/gravity_forms/signatures/' . $value;
            $signature_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $signature_url);
        }
    }

    // Build HTML content for DomPDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page { 
                size: A4; 
                margin: 10mm 8mm 8mm 8mm; 
            }
            body { 
                font-family: Times, serif; 
                font-size: 10pt; 
                line-height: 1.4; 
                margin: 0; 
                padding: 0;
            }
            .header { 
                text-align: center; 
                margin-bottom: 15px; 
                position: relative;
            }
            .logo-sgndt { 
                position: absolute; 
                top: 0; 
                left: 0; 
                width: 30mm; 
                height: auto;
            }
            .logo-ndtss { 
                position: absolute; 
                top: 20mm; 
                left: 50%; 
                transform: translateX(-50%); 
                width: 120mm; 
                height: auto; 
                opacity: 0.05;
            }
            .title { 
                color: #CC0000; 
                font-size: 14pt; 
                font-weight: bold; 
                margin: 0;
            }
            .subtitle { 
                font-size: 10pt; 
                margin: 2px 0;
            }
            .main-title { 
                font-size: 12pt; 
                font-weight: bold; 
                margin: 8px 0;
            }
            .section { 
                margin: 8px 0; 
                page-break-inside: avoid;
            }
            .section-title { 
                font-size: 11pt; 
                font-weight: bold; 
                margin: 6px 0 4px 0;
                background-color: #f0f0f0;
                padding: 2px 4px;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 4px 0;
                font-size: 9pt;
            }
            th, td { 
                border: 1px solid #000; 
                padding: 3px; 
                text-align: left; 
                vertical-align: top;
            }
            th { 
                background-color: #f0f0f0; 
                font-weight: bold;
                width: 30%;
            }
            .results-table th, .results-table td {
                font-size: 8pt;
                padding: 2px;
            }
            .footer-note { 
                font-size: 7pt; 
                font-style: italic; 
                margin-top: 8px;
            }
            .signature-section {
                margin-top: 10px;
                text-align: left;
            }
            .signature-line {
                border-top: 1px solid #000;
                width: 200px;
                margin: 5px 0;
            }
            .page-break {
                page-break-before: always;
            }
            .compact {
                margin: 2px 0;
                font-size: 9pt;
            }
            .interpretation {
                font-size: 9pt;
                line-height: 1.3;
            }
            .interpretation ul {
                margin: 4px 0;
                padding-left: 15px;
            }
            .interpretation li {
                margin: 2px 0;
            }
        </style>
    </head>
    <body>
        <!-- Page 1 -->
        <div class="header">
            <img src="' . $sgndt_logo_url . '" class="logo-sgndt" alt="SGNDT Logo">
            <img src="' . $ndtss_logo_url . '" class="logo-ndtss" alt="NDTSS Logo">
            <div class="title">NON-DESTRUCTIVE TESTING SOCIETY (SINGAPORE)</div>
            <div class="subtitle">(SGNDT SCHEME IN ACCORDANCE WITH ISO 9712:2021)</div>
            <div class="main-title">NOTIFICATION OF SGNDT EXAMINATION RESULTS</div>
        </div>

        <div class="section">
            <div class="section-title">CANDIDATE INFORMATION</div>
            <table>
                <tr><th>Name</th><td>' . esc_html($candidate_name) . '</td></tr>
                <tr><th>ID of Candidate</th><td>' . esc_html($exam_entry['789'] ?? 'N/A') . '</td></tr>
                <tr><th>Certificate Number</th><td>' . esc_html($cert_number) . '</td></tr>
                <tr><th>Date of Birth</th><td>' . esc_html($exam_entry['3'] ?? 'N/A') . '</td></tr>
                <tr><th>Result Ref. No.</th><td>' . esc_html($marks_entry['21'] ?? 'N/A') . '</td></tr>
                <tr><th>Organization</th><td>' . esc_html($exam_entry['17'] ?? 'N/A') . '</td></tr>
                <tr><th>Address</th><td>' . esc_html($full_address) . '</td></tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">EXAMINATION DETAILS</div>
            <table>
                <tr><th>Date of Exam</th><td>' . esc_html($exam_date) . '</td></tr>
                <tr><th>Exam Center</th><td>' . esc_html($exam_entry['833'] ?? 'N/A') . '</td></tr>
                <tr><th>Method</th><td>' . esc_html($method) . '</td></tr>
                <tr><th>Level / Sector</th><td>' . esc_html($marks_entry['1'] ?? 'N/A') . ' / ' . esc_html($sector) . '</td></tr>
                <tr><th>Initial / Retest</th><td>' . esc_html($exam_status) . '</td></tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">RESULTS OF EXAMINATION</div>
            ' . $marks_html . '
        </div>

        <div class="section">
            <div class="section-title">EXAMINATION AUTHORITY</div>
            <div class="compact"><strong>EXAMINATION AUTHORITY:</strong> NDTSS CERTIFICATION BODY</div>
            <div class="compact"><strong>EXAMINER:</strong> ' . esc_html($examiner_list) . '</div>
            <div class="compact"><strong>INVIGILATOR:</strong> ' . esc_html($invigilator_list) . '</div>
            <div class="signature-section">
                <div class="signature-line"></div>
                <div class="compact">____________________ (Authorized Signatory)</div>
                <div class="compact"><strong>DATE OF ISSUE:</strong> ' . esc_html($issue_date) . '</div>
            </div>
            ' . (!empty($signature_url) ? '<img src="' . esc_url($signature_url) . '" style="width: 50mm; height: auto; margin-top: 5px;">' : '') . '
        </div>

        <div class="footer-note">
            This is a notification of Results only. An official Certificate bearing SGNDT Logo & Accreditation Mark will be issued within 30 days from this notification for successful candidates.
        </div>

        <!-- Page 2 -->
        <div class="page-break">
            <div class="header">
                <img src="' . $sgndt_logo_url . '" class="logo-sgndt" alt="SGNDT Logo">
                <img src="' . $ndtss_logo_url . '" class="logo-ndtss" alt="NDTSS Logo">
            </div>
        </div>';

    // Add employer authorization if passed
    if (strtoupper($overall_result) === 'PASS') {
        $html .= '
        <div class="section">
            <div class="section-title">EMPLOYER AUTHORIZATION</div>
            <div class="compact">The Employer shall authorize the holder of NDTSS SGNDT certificate to carry out testing on his behalf...</div>
            <table style="margin-top: 8px;">
                <tr>
                    <th style="width:25%">Name of Employer</th>
                    <th style="width:25%">Authorization</th>
                    <th style="width:25%">Signature</th>
                    <th style="width:25%">Date</th>
                </tr>';
        for ($i = 0; $i < 5; $i++) {
            $html .= '<tr><td style="height: 15px;"></td><td></td><td></td><td></td></tr>';
        }
        $html .= '</table></div>';
    }

    // Add interpretation section
    $html .= '
        <div class="section">
            <div class="section-title" style="text-align: center;">INTERPRETATION OF EXAMINATION RESULTS</div>
            <div class="interpretation">
                <ul>
                    <li>To be eligible for certification, the candidate shall obtain a minimum grade of 70% in each examination part, and a minimum composite grade of 70%.</li>
                    <li>Candidate shall be able to score 70% in Instruction writing and examination specimens in order to pass the practical examination.</li>
                    <li>Candidate shall obtain a minimum pass in parent metal sample in order to pass the ultrasonic practical examination.</li>
                    <li>Failure to obtain the minimum 70% in one specimen shall be retested for all specimens in that sector. Candidates appearing in the industrial sector will attempt a mixture of weld(s), casting, and/or forged specimens; failure in any one specimen would require reappearing for all the specimens. The product sector would require passing a minimum of two specimens in the sector.</li>
                    <li>Failure to detect a mandatory defect in practical will lead to failure in the entire practical examination.</li>
                    <li>Candidates should pass Level 2 practical before appearing for basic and method examinations. A candidate is eligible to appear for reexamination on the failed part from one month of the examination date up to a maximum of two years from the examination date.</li>
                    <li>The results of the passed parts will be valid only for two years from the examination date.</li>
                </ul>
            </div>
        </div>
    </body>
    </html>';

    try {
        $pdf->loadHtml($html);
        $pdf->render();

        // Save PDF
        $upload_dir = wp_upload_dir();
        $dir = $upload_dir['basedir'] . '/certificates';
        if (!wp_mkdir_p($dir)) {
            error_log("Failed to create directory: $dir");
            ob_end_clean();
            return false;
        }
        
        $file_name = "certificate_{$exam_entry_id}_{$method_slug}.pdf";
        $file_path = "{$dir}/{$file_name}";
        $file_url = $upload_dir['baseurl'] . "/certificates/{$file_name}?v=" . time();
        
        $certificate_data = [
            'url' => $file_url,
            'path' => $file_path,
            'generated_at' => $issue_date_sql,
            'exam_entry_id' => $exam_entry_id,
            'marks_entry_id' => $marks_entry_id,
            'method' => $method,
            'issued_by' => get_current_user_id(),
        ];
        
        gform_update_meta($marks_entry_id, '_notification_meta_' . $method, $certificate_data);

        // Update certificate link in database
        $update_result = $wpdb->update(
            $table_certifications,
            ['certificate_link' => $file_url],
            ['certification_id' => $certification_id],
            ['%s'],
            ['%d']
        );
        
        if ($update_result === false) {
            error_log("Failed to update certificate_link for certification_id $certification_id: " . $wpdb->last_error);
            ob_end_clean();
            return false;
        }

        // Save PDF to file
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        file_put_contents($file_path, $pdf->output());
        
        if (!file_exists($file_path)) {
            error_log("Failed to save PDF to $file_path");
            ob_end_clean();
            return false;
        }

        ob_end_clean();

        // Send notification
        $center_name = $exam_entry['833'] ?? 'N/A';
        $center_post = get_page_by_title($center_name, OBJECT, 'exam_center');
        if ($center_post) {
            gform_update_meta($exam_entry_id, '_linked_exam_center', $center_post->ID);
            // Include the notification function from the original file
            if (!function_exists('send_certification_notification')) {
                include_once get_stylesheet_directory() . '/includes/pdf-cert-generator.php';
            }
            if (function_exists('send_certification_notification')) {
                send_certification_notification($exam_entry_id, $marks_entry_id, $center_post, $certificate_data, $method, $overall_result);
            } else {
                error_log("send_certification_notification function not found");
            }
        } else {
            error_log("Exam center not found for name: $center_name");
        }

        return true;

    } catch (Exception $e) {
        error_log("DomPDF error for exam_entry_id $exam_entry_id: " . $e->getMessage());
        ob_end_clean();
        return false;
    }
}

// Keep the existing helper functions
function get_exam_dates_by_method($schedule_data, $method) {
    $dates = [];
    foreach ((array) $schedule_data as $key => $slot) {
        if (strpos($key, $method . '|') === 0 && !empty($slot['checkin_time']) &&
            ($date = DateTime::createFromFormat('Y-m-d', substr($slot['checkin_time'], 0, 10)))) {
            $dates[] = $date->format('d/m/Y');
        }
    }
    sort($dates);
    return $dates;
}

function crop_signature_image($source_path, $target_path) {
    if (!extension_loaded('gd')) {
        error_log("GD extension not loaded for signature cropping: $source_path");
        return false;
    }

    if (!file_exists($source_path) || !is_readable($source_path)) {
        error_log("Signature image not found or not readable: $source_path");
        return false;
    }

    $image = imagecreatefrompng($source_path);
    if (!$image) {
        error_log("Failed to load PNG image: $source_path");
        return false;
    }

    imagesavealpha($image, true);
    imagealphablending($image, false);

    $width = imagesx($image);
    $height = imagesy($image);

    $top = $left = 0;
    $bottom = $height;
    $right = $width;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;
            if ($alpha < 127) {
                $top = $y;
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

    if ($crop_width <= 0 || $crop_height <= 0) {
        error_log("Invalid crop dimensions for signature: width=$crop_width, height=$crop_height");
        imagedestroy($image);
        return false;
    }

    $new_img = imagecreatetruecolor($crop_width, $crop_height);
    imagesavealpha($new_img, true);
    $trans_colour = imagecolorallocatealpha($new_img, 0, 0, 0, 127);
    imagefill($new_img, 0, 0, $trans_colour);

    imagecopy($new_img, $image, 0, 0, $left, $top, $crop_width, $crop_height);
    $result = imagepng($new_img, $target_path, 9);

    imagedestroy($image);
    imagedestroy($new_img);

    if (!$result) {
        error_log("Failed to save cropped signature: $target_path");
    }

    return $result ? $target_path : false;
}

?>
