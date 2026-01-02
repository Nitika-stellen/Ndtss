<?php
require_once get_stylesheet_directory() . '/TCPDF/tcpdf.php';

function generate_exam_certificate_pdf($exam_entry_id, $marks_entry_id, $method) {
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
    $scope_values = [];

    $get_field_values = function ($field) use ($exam_entry) {
        $values = [];
        $field_id = isset($field->id) ? (string) $field->id : (isset($field['id']) ? (string) $field['id'] : '');
        $field_inputs = isset($field->inputs) ? $field->inputs : (isset($field['inputs']) ? $field['inputs'] : []);

        if (!empty($field_inputs) && is_array($field_inputs)) {
            foreach ($field_inputs as $input) {
                $input_id = is_array($input) && isset($input['id']) ? (string) $input['id'] : (string) $input;
                if ($input_id === '') {
                    continue;
                }
                $entry_value = $exam_entry[$input_id] ?? null;
                if (is_array($entry_value)) {
                    foreach ($entry_value as $val) {
                        if ($val !== '' && $val !== null) {
                            $values[] = trim((string) $val);
                        }
                    }
                } elseif ($entry_value !== '' && $entry_value !== null) {
                    $values[] = trim((string) $entry_value);
                }
            }
        }

        if ($field_id !== '') {
            $entry_value = $exam_entry[$field_id] ?? null;
            if (is_array($entry_value)) {
                foreach ($entry_value as $val) {
                    if ($val !== '' && $val !== null) {
                        $values[] = trim((string) $val);
                    }
                }
            } elseif ($entry_value !== '' && $entry_value !== null) {
                $values[] = trim((string) $entry_value);
            }
        }

        $values = array_values(array_filter($values, static function ($val) {
            return $val !== '';
        }));

        return array_values(array_unique($values));
    };

    foreach ($exam_form['fields'] as $field) {
        $label = isset($field->label) ? trim($field->label) : '';
        $css_class = isset($field->cssClass) ? $field->cssClass : (isset($field['cssClass']) ? $field['cssClass'] : '');
        $field_values = $get_field_values($field);

        // if (empty($sector) && !empty($field_values) && stripos($label, 'sector') !== false) {
        //     $sector = implode(', ', $field_values);
        // }

        if (!empty($field_values) && ((stripos($label, 'scope') !== false) || (!empty($css_class) && strpos($css_class, 'scope_' . strtolower($method)) !== false))) {
            $scope_values = array_merge($scope_values, $field_values);
        }

        if (!empty($field_values) && (
            stripos($label, 'exam status') !== false ||
            (stripos($label, 'initial') !== false && stripos($label, 'retest') !== false) ||
            (!empty($css_class) && strpos($css_class, 'exam_status') !== false)
        )) {
            $exam_status = $field_values[0];
        }

        if (empty($sector) && !empty($field_values) && !empty($css_class) && strpos($css_class, 'sector_' . strtolower($method)) !== false) {
            $sector = implode(', ', $field_values);
        }
    }

    // Clean sector value - remove suffix after " -" from each comma-separated value
    if (!empty($sector)) {
        $sector_parts = array_map('trim', explode(',', $sector));
        $cleaned_parts = array_map(function($part) {
            return preg_replace('/\s*-\s*[a-z]+$/i', '', trim($part));
        }, $sector_parts);
        $sector = implode(', ', $cleaned_parts);
    }

$scope_values = array_values(array_unique(array_filter($scope_values, static function ($val) {
        return $val !== '';
    })));
    $scope = $scope_values;
    $scope_text = !empty($scope_values) ? implode(', ', $scope_values) : '';

    $method_slug = sanitize_title($method);
    $issue_date = date('d.m.Y');
    $issue_date_sql = current_time('mysql');

    // Generate unique cert_number with a maximum attempt limit
    // Validation: Check for AQB Signature before processing
    $marks_form = GFAPI::get_form($marks_entry['form_id']);
    $signature_path = '';
    foreach ($marks_form['fields'] as $field) {
        $field_id = $field->id;
        $value = $marks_entry[$field_id] ?? '';
        if ($field_id == 18) {
             // Re-fetch the entry to get the latest saved signature value
             $updated_marks_entry = GFAPI::get_entry($marks_entry_id);
             $value = $updated_marks_entry[$field_id] ?? '';
             
             if (!empty($value)) {
                $upload_dir = wp_upload_dir();
                $signature_url = $upload_dir['baseurl'] . '/gravity_forms/signatures/' . $value;
                $signature_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $signature_url);
             }
        }
    }

    if (empty($signature_path) || !file_exists($signature_path)) {
       ob_end_clean();
       throw new Exception("AQB signature not found in marks form. Please add the signature and try again.");
    }

    $table_certifications = $wpdb->prefix . 'sgndt_certifications'; // Re-declare to ensure scope
    $base_cert_number = 'SGNDT-' . $candidate_reg_number . '-' . strtoupper($method_slug);
    $cert_number = $base_cert_number;
    $suffix = 1;
    $max_attempts = 100; // Prevent infinite loop

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
    $is_retest = (stripos($exam_status, 'retest') !== false);
    $exam_status_display = $exam_status !== '' ? $exam_status : 'Initial';
    $sector_display = $sector !== '' ? $sector : 'N/A';
    $scope_display = $scope_text !== '' ? $scope_text : 'N/A';
    $marks_result = [];
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
        'scope' => $scope_text,
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
    $processed_subjects = []; // Track processed subject names to avoid duplicates
    foreach ($marks_result['subjects'] as $subject) {
        if ($subject['obtained'] == 0 && $subject['total'] == 0) {
            continue; // Skip subjects with blank/zero marks
        }

        // Handle "Basic" section separately based on combined percentage
        if ($subject['name'] === 'Basic') {
            $basic_percent = $subject['percent'];
            if ($basic_percent >= 70) {
                // Save only the combined "Basic" result if pass
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
                $processed_subjects[] = $subject['name']; // Mark "Basic" as processed
            } else {
                // If "Basic" fails (combined percent < 70), save individual parts
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
                        $processed_subjects[] = $part_subject['name']; // Mark individual parts as processed
                    }
                }
                // Skip saving the combined "Basic" entry to avoid duplication
                continue;
            }
        } else {
            // Save other subjects individually, but skip if already processed
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
                $processed_subjects[] = $subject['name']; // Mark as processed
            }
        }
    }

    // Initialize TCPDF
    if (!class_exists('TCPDF')) {
        ob_end_clean();
        error_log("TCPDF class not found");
        return false;
    }
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetMargins(10, 5, 10);
    $pdf->SetAutoPageBreak(true, 8);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetCreator('NDTSS');
    $pdf->SetAuthor('NDTSS Certification Body');
    $pdf->SetTitle('SGNDT Examination Certificate');
    $pdf->SetFont('times', '', 12);
    $pdf->AddPage();

    // Add logos
    $ndtss_logo_path = get_stylesheet_directory() . '/assets/logos/ndtss_logo.png';
    if (file_exists($ndtss_logo_path)) {
        $pdf->SetAlpha(0.05);
        $pdf->Image($ndtss_logo_path, 40, 80, 120, 120, '', '', '', false, 300);
        $pdf->SetAlpha(1);
    }
    $sgndt_logo_path = get_stylesheet_directory() . '/assets/logos/sgndt_logo.png';
    if (file_exists($sgndt_logo_path)) {
        $pdf->Image($sgndt_logo_path, 12, 5, 30);
    }

    $pdf->Ln(5);
    $pdf->SetTextColor(255, 0, 0);
    $pdf->Cell(0, 5, 'NON-DESTRUCTIVE TESTING SOCIETY (SINGAPORE)', 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 4, '(SGNDT SCHEME IN ACCORDANCE WITH ISO 9712:2021)', 0, 1, 'C');
    $pdf->Cell(0, 5, 'NOTIFICATION OF SGNDT EXAMINATION RESULTS', 0, 1, 'C');
    $pdf->Ln(2);



    // Process signature image for embedding
    $final_signature_path = '';
    if (!empty($signature_path) && file_exists($signature_path) && is_readable($signature_path)) {
        $signature_cropped_path = str_replace('.png', '_cropped.png', $signature_path);
        try {
            $cropped_ready = true;
            if (function_exists('crop_signature_image')) {
                $cropped_ready = crop_signature_image($signature_path, $signature_cropped_path);
            }
            if ($cropped_ready && file_exists($signature_cropped_path)) {
                $final_signature_path = $signature_cropped_path;
            } else {
                // Fallback to original if crop fails but file exists
                $final_signature_path = $signature_path;
            }
        } catch (Exception $e) {
            error_log("Error processing signature image: " . $e->getMessage());
            $final_signature_path = $signature_path; // Fallback
        }
    }

    // CSS for styling
    $css = "<style>
    body { font-family: Times, serif; font-size: 10pt; line-height: 1.3; }
    p { margin: 1px 0; }
    h1 { font-size: 14pt; font-weight: bold; }
    h2 { font-size: 13pt; }
    h3 { font-size: 10pt; margin: 1px 0; }
    th, td { padding: 1px; text-align: left; vertical-align: middle; }
    .footer-note { font-size: 7pt; font-style: italic; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #000; }
    th { background: #f0f0f0; }
    img { border: none !important; }
    </style>";

    // Get candidate photo from Exam Entry (Field 861)
    $photo_url = rgar($exam_entry, '861');
    $photo_html = '';
    $has_photo = false;
    
    if (!empty($photo_url)) {
        $upload_dir = wp_upload_dir();
        $photo_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $photo_url);
        if (file_exists($photo_path)) {
            // Remove height to preserve aspect ratio
            $photo_html = '<img src="' . $photo_path . '" width="110" style="border: none !important; padding: 0 !important;" />';
            $has_photo = true;
        }
    }

     if (!$has_photo) {
        $profile_photo_url = get_user_meta($user_id, 'custom_profile_photo', true);
        if (!empty($profile_photo_url)) {
            $upload_dir = wp_upload_dir();
            $profile_photo_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $profile_photo_url);
            if (file_exists($profile_photo_path)) {
                // Remove height to preserve aspect ratio
                $photo_html = '<img src="' . $profile_photo_path . '" width="110" style="border: none !important; padding: 0 !important;" />';
                $has_photo = true;
            }
        }
    }

    // Build PDF content
    $content = $css;
    
    // Column Widths
    $col1_w = '30%';
    $col2_w = $has_photo ? '45%' : '70%';
    $col3_w = '25%';

    $content .= "<h3 style='margin: 0px 0; padding: 1px 0; font-size: 8pt;'>CANDIDATE INFORMATION</h3><table style='font-size: 10pt;'>";
    
    // ROW 1: Name + Photo (Rowspan)
    $content .= '<tr>
        <th width="' . $col1_w . '">NAME</th>
        <td width="' . $col2_w . '"><strong>' . (isset($candidate_name) ? strtoupper(esc_html($candidate_name)) : 'N/A') . '</strong></td>';
    
    if ($has_photo) {
        $content .= '<td width="' . $col3_w . '" rowspan="7" align="center" style="vertical-align: middle; border: none !important; padding: 2px;">' . $photo_html . '</td>';
    }
    $content .= '</tr>';
    
    // Remaining Rows
    $content .= '<tr><th>ID OF CANDIDATE</th><td>' . (isset($exam_entry['789']) ? esc_html($exam_entry['789']) : 'N/A') . '</td></tr>';
    // $content .= '<tr><th>CERTIFICATE NUMBER</th><td>' . esc_html($cert_number) . '</td></tr>';
    
    // Format Date of Birth to DD/MM/YYYY
    $dob_display = 'N/A';
    if (isset($exam_entry['3']) && !empty($exam_entry['3'])) {
        $dob_timestamp = strtotime($exam_entry['3']);
        if ($dob_timestamp !== false) {
            $dob_display = date('d/m/Y', $dob_timestamp);
        } else {
            $dob_display = esc_html($exam_entry['3']);
        }
    }
    $content .= '<tr><th>DATE OF BIRTH</th><td>' . $dob_display . '</td></tr>';
    $content .= '<tr><th>RESULT REF. NO.</th><td>' . (isset($marks_entry['21']) ? esc_html($marks_entry['21']) : 'N/A') . '</td></tr>';
    $content .= '<tr><th>ORGANIZATION</th><td>' . (isset($exam_entry['17']) ? esc_html($exam_entry['17']) : 'N/A') . '</td></tr>';
    $content .= '<tr><th>ADDRESS</th><td>' . esc_html($full_address) . '</td></tr>';
    
    $content .= '</table>';

    $content .= "<h3 style='margin: 0px 0; padding: 1px 0; font-size: 8pt;'>EXAMINATION DETAILS</h3><table style='font-size: 10pt;'>";
    $content .= "<tr><th>DATE OF EXAM</th><td>" . esc_html($exam_date) . "</td></tr>";
    $content .= "<tr><th>EXAM CENTER</th><td>" . (isset($exam_entry['833']) ? esc_html($exam_entry['833']) : 'N/A') . "</td></tr>";
    $content .= "<tr><th>METHOD</th><td>" . esc_html($method) . "</td></tr>";
    $content .= "<tr><th>LEVEL / SECTOR</th><td>" . esc_html($marks_entry['1'] ?? 'N/A') . " / " . esc_html($sector_display) . "</td></tr>";
    $content .= "<tr><th>SCOPE</th><td>" . esc_html($scope_display) . "</td></tr>";
    $content .= "<tr><th>INITIAL / RETEST</th><td>" . esc_html($exam_status_display) . "</td></tr></table>";

    $content .= "<h3 style='margin: 1px 0; font-size: 12pt;'>RESULTS OF EXAMINATION</h3>" . $marks_html . '<div style="height: 15px;"></div>';


    $content .= "<p style='margin: 1px 0; font-size: 8pt;'><strong>EXAMINATION AUTHORITY:</strong> NDTSS CERTIFICATION BODY</p>";
    $content .= "<p style='margin: 1px 0; font-size: 8pt;'><strong>EXAMINER:</strong> " . esc_html($examiner_list) . "</p>";
    $content .= "<p style='margin: 1px 0; font-size: 8pt;'><strong>INVIGILATOR:</strong> " . esc_html($invigilator_list) . "</p>";
    
    // Add spacing or signature
    if (!empty($final_signature_path)) {
        // Embed signature image inline
        $content .= '<div><img src="' . $final_signature_path . '" width="120" height="30" border="0" alt="Signature" /></div>';
    } else {
        // Placeholder space if no signature
        $content .= '<br>';
    }
    
    // Signature Line - Restore text based line for design consistency
    $content .= "<p>____________________ (Authorized Signatory)</p>";
    
    $content .= "<p><strong>DATE OF ISSUE:</strong> " . esc_html($issue_date) . "</p>";
    $content .= "<p class='footer-note'>This is a notification of Results only. An official Certificate bearing SGNDT Logo & Accreditation Mark will be issued within 30 days from this notification for successful candidates.</p>";

    $pdf->writeHTML($content, true, false, true, false, '');

    // Page 2 – Employer Auth + Interpretation
    $pdf->AddPage();
    $pdf->SetAlpha(0.05);
    if (file_exists($ndtss_logo_path)) {
        $pdf->Image($ndtss_logo_path, 40, 80, 120, 120, '', '', '', false, 300);
    }
    $pdf->SetAlpha(1);
    if (file_exists($sgndt_logo_path)) {
        $pdf->Image($sgndt_logo_path, 12, 5, 30);
    }
    $pdf->Ln(10);
    $pdf->SetFont('times', '', 12);

    $employer_auth = '';
    if (strtoupper($overall_result) === 'PASS') {
        $employer_auth = "<h3>EMPLOYER AUTHORIZATION</h3>
        <p style='margin-bottom: 2px;'>The Employer shall authorize the holder of NDTSS SGNDT certificate to carry out testing on his behalf...</p>";
        for ($i = 0; $i < 5; $i++) {
            $employer_auth .= "<table><tr><th style='width:25%'>Name of Employer</th><th style='width:25%'>Authorization</th><th style='width:25%'>Signature</th><th style='width:25%'>Date</th></tr><tr><td height='20'></td><td></td><td></td><td></td></tr></table><br>";
        }
    }

    $interpretation = "
    <h3 style='text-align:center;'>INTERPRETATION OF EXAMINATION RESULTS</h3>
    <ul style='padding-left: 20px; font-size: 11pt;'>
    <li>To be eligible for certification, the candidate shall obtain a minimum grade of 70% in each examination part, and a minimum composite grade of 70%.</li>
    <li>Candidate shall be able to score 70% in Instruction writing and examination specimens in order to pass the practical examination.</li>
    <li>Candidate shall obtain a minimum pass in parent metal sample in order to pass the ultrasonic practical examination.</li>
    <li>Failure to obtain the minimum 70% in one specimen shall be retested for all specimens in that sector. Candidates appearing in the industrial sector will attempt a mixture of weld(s), casting, and/or forged specimens; failure in any one specimen would require reappearing for all the specimens. The product sector would require passing a minimum of two specimens in the sector.</li>
    <li>Failure to detect a mandatory defect in practical will lead to failure in the entire practical examination.</li>
    <li>Candidates should pass Level 2 practical before appearing for basic and method examinations. A candidate is eligible to appear for reexamination on the failed part from one month of the examination date up to a maximum of two years from the examination date.</li>
    <li>The results of the passed parts will be valid only for two years from the examination date.</li>
    </ul>";

    $pdf->writeHTML($css . $employer_auth . $interpretation, true, false, true, false, '');

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
        unlink($file_path); // Remove existing file to avoid conflicts
    }
    try {
        $pdf->Output($file_path, 'F');
    } catch (Exception $e) {
        error_log("Failed to save PDF to $file_path: " . $e->getMessage());
        ob_end_clean();
        return false;
    }

    ob_end_clean();

    // Send notification - Get center name based on form type
    $form_id = $exam_entry['form_id'];
    if ($form_id == 30 || $form_id == 39) {
        $center_name = $exam_entry['9'] ?? 'N/A';
    } else {
        $center_name = $exam_entry['833'] ?? 'N/A';
    }
    $center_post = get_page_by_title($center_name, OBJECT, 'exam_center');
    if ($center_post) {
        gform_update_meta($exam_entry_id, '_linked_exam_center', $center_post->ID);
        if (function_exists('send_certification_notification')) {
            send_certification_notification($exam_entry_id, $marks_entry_id, $center_post, $certificate_data, $method, $overall_result);
        } else {
            error_log("send_certification_notification function not found");
        }
    } else {
        error_log("Exam center not found for name: $center_name");
        // Not critical, so continue
    }

    return true; // Success
}

function send_certification_notification($entry_id, $marks_entry_id, $center_post, $certificate_data, $method, $result) {
    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry)) {
        error_log("Failed to retrieve entry for ID: $entry_id");
        return;
    }
    $log = [];
    $user_id = $entry['created_by'];
    $user_data = get_userdata($user_id);
    $candidate_name = $user_data ? $user_data->display_name : 'N/A';
    $candidate_email = $user_data && is_email($user_data->user_email) ? $user_data->user_email : '';
    
    // Get email fields based on form type
    $form_id = $entry['form_id'];
    if ($form_id == 30) {
        // Retest form - field 26 is candidate email
        $employer_email  = '';
        $personal_email  = rgar($entry, '26');
        $field_863_value = '';
    } elseif ($form_id == 39) {
        // Renewal form - field 12 is candidate email
        $employer_email  = '';
        $personal_email  = rgar($entry, '12');
        $field_863_value = '';
    } else {
        // Initial form (15)
        $employer_email  = rgar($entry, '864.1');
        $personal_email  = rgar($entry, '864.2');
        $field_863_value = rgar($entry, '863');
    }
   
    // Get certificate file path for email attachment
    $certificate_path = $certificate_data['path'] ?? '';
    
    $issue_date = date('d.m.Y', strtotime($certificate_data['generated_at'] ?? 'now'));
    $center_name = get_the_title($center_post->ID);
    $center_admin_id = get_post_meta($center_post->ID, '_center_admin_id', true);
    $center_admin = get_userdata($center_admin_id);
    $admin_users = get_users([
        'role'   => 'administrator',
        'fields' => ['user_email'],
    ]);

    $recipient_candidates = [
        $candidate_email,
        $personal_email,
        $employer_email,
        $field_863_value,
    ];

    $to = [];
    foreach ($recipient_candidates as $recipient_email) {
        if (!empty($recipient_email) && is_email($recipient_email)) {
            $to[] = sanitize_email($recipient_email);
        }
    }
    $to = array_unique($to);

    // Candidate Email
    $sent_to_candidate = false;
    
    // Check if result notification email is enabled in settings
    $enable_notification_email = get_option('ndtss_enable_result_notification_email', 1);
    
    if ($enable_notification_email && !empty($to)) {
        error_log("Result for candidate: " . $result);
        
        // Determine exam type for email
        $form_id = $entry['form_id'];
        $exam_type_label = 'Examination';
        if ($form_id == 30) {
            $exam_type_label = 'Retest Examination';
        } elseif ($form_id == 39) {
            $exam_type_label = 'Renewal/Recertification Examination';
        } else {
            $exam_type_label = 'Initial Examination';
        }
        
        // Get subject and body from settings or use default
        $subject = get_option('ndtss_result_notification_subject', 'SGNDT ' . $exam_type_label . ' Result Notification');
        $body_template = get_option('ndtss_result_notification_body', '');
        
        if (empty($body_template)) {
            $body_template = "Dear {candidate_name},\n\nYour result has been released.\n\nMethod: {method}\nResult: {result}\nDate of Issue: {date}\n\nPlease find your result notification attached to this email as a PDF file.\n\nBest regards,\nNDTSS Certification Team";
        }

        // Replace placeholders
        $body = str_replace(
            ['{candidate_name}', '{method}', '{result}', '{date}'],
            [esc_html($candidate_name), esc_html($method), esc_html($result), esc_html($issue_date)],
            $body_template
        );
        $body = nl2br($body);
        
        $message = function_exists('get_email_template') ? get_email_template($subject, $body) : "<html><body>$body</body></html>";

        // Attach PDF if file exists
        $attachments = [];
        if (!empty($certificate_path) && file_exists($certificate_path)) {
            $attachments[] = $certificate_path;
        }

        $content_type_callback = static function () {
            return 'text/html';
        };
        add_filter('wp_mail_content_type', $content_type_callback);
        $sent_to_candidate = wp_mail($to, $subject, $message, [], $attachments);
        remove_filter('wp_mail_content_type', $content_type_callback);

        $log['candidate_email'] = [
            'email' => $to,
            'status' => $sent_to_candidate ? 'sent' : 'failed',
            'timestamp' => current_time('mysql'),
        ];
        if (!$sent_to_candidate) {
            error_log("Failed to send email to candidate: " . implode(', ', $to));
        }
    } else {
        if (!$enable_notification_email) {
            $log['candidate_email'] = [
                'status' => 'skipped (disabled in settings)',
                'timestamp' => current_time('mysql'),
            ];
            error_log("Result notification email disabled in settings. Skipping email to candidate.");
        } else {
             error_log("Invalid or missing candidate email for entry ID: $entry_id");
        }
    }

    // Admin Emails
    $admin_emails = [];
    if ($center_admin && is_email($center_admin->user_email)) {
        $admin_emails[] = $center_admin->user_email;
    }

    foreach ($admin_users as $admin_user) {
        if (is_email($admin_user->user_email)) {
            $admin_emails[] = $admin_user->user_email;
        }
    }

    $manager_admins = get_users([
        'role'   => 'manager_admin',
        'fields' => ['user_email'],
    ]);

    foreach ($manager_admins as $manager_admin) {
        if (is_email($manager_admin->user_email)) {
            $admin_emails[] = $manager_admin->user_email;
        }
    }

    $admin_emails = array_unique($admin_emails);

    $sent_admin_emails = [];
    error_log("Result for candidate: " . $result);
    if (!empty($admin_emails)) {
        // Determine exam type for admin email
        $form_id = $entry['form_id'];
        $exam_type_label = 'Examination';
        if ($form_id == 30) {
            $exam_type_label = 'Retest Examination';
        } elseif ($form_id == 39) {
            $exam_type_label = 'Renewal/Recertification Examination';
        } else {
            $exam_type_label = 'Initial Examination';
        }
        
        $subject = '📢 Candidate ' . $exam_type_label . ' Result Notification – ' . esc_html($candidate_name);
        $body = '
        <p><strong>Candidate Name:</strong> ' . esc_html($candidate_name) . '</p>
        <p><strong>Exam Type:</strong> ' . esc_html($exam_type_label) . '</p>
        <p><strong>Method:</strong> ' . esc_html($method) . '</p>
        <p><strong>Result:</strong> ' . esc_html($result) . '</p>
        <p><strong>Exam Center:</strong> ' . esc_html($center_name) . '</p>
        <p><strong>Date of Issue:</strong> ' . esc_html($issue_date) . '</p>
        ';
        $message = function_exists('get_email_template') ? get_email_template($subject, $body) : "<html><body>$body</body></html>";

        // Attach PDF if file exists
        $attachments = [];
        if (!empty($certificate_path) && file_exists($certificate_path)) {
            $attachments[] = $certificate_path;
        }

        $content_type_callback_admin = static function () {
            return 'text/html';
        };
        add_filter('wp_mail_content_type', $content_type_callback_admin);
        $sent_to_admins = wp_mail($admin_emails, $subject, $message, [], $attachments);
        remove_filter('wp_mail_content_type', $content_type_callback_admin);

        foreach ($admin_emails as $email) {
            $sent_admin_emails[] = [
                'email' => $email,
                'status' => $sent_to_admins ? 'sent' : 'failed',
                'timestamp' => current_time('mysql'),
            ];
        }
        if (!$sent_to_admins) {
            error_log("Failed to send admin emails for entry ID: $entry_id");
        }
    }

    // Save email log
    $meta_key = '_result_notification_email_log_' . sanitize_title($method);
    gform_update_meta($marks_entry_id, $meta_key, $log);
}

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