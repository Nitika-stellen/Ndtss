<?php
require_once get_stylesheet_directory() . '/includes/vendor/autoload.php';
require_once get_stylesheet_directory() . '/includes/certificate-signature-helpers.php';
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
    $scope = [];
    $signature_path = '';
    $upload_dir = wp_upload_dir();
    $form_id = $entry['form_id'];

    // Handle Form 39 (Renewal/Recertification by Exam) - specific field extraction
    if ($form_id == 39) {
        // Get sector from field 4
        $sector = rgar($entry, '4');
        
        // Get scope from field 3 (can be array for multi-select or comma-separated string)
        $scope_value = rgar($entry, '3');
        if (!empty($scope_value)) {
            if (is_array($scope_value)) {
                $scope = $scope_value;
            } else {
                // Handle comma-separated values
                $scope = array_filter(array_map('trim', explode(',', $scope_value)));
            }
        }
        
        // Get signature from field (check common signature field IDs)
        $signature_value = rgar($entry, '115') ?: rgar($entry, '29');
        if (!empty($signature_value)) {
            $signature_url = $upload_dir['baseurl'] . '/gravity_forms/signatures/' . $signature_value;
            $signature_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $signature_url);
        }
        
        // If sector or scope is empty, try to get from original certificate
        if (empty($sector) || empty($scope)) {
            $cert_id = rgar($entry, '28'); // Field 28 contains the original certificate ID
            if (!empty($cert_id)) {
                $user_id_temp = $entry['created_by'] ?? get_current_user_id();
                $original_cert = $wpdb->get_row($wpdb->prepare(
                    "SELECT sector, scope FROM {$wpdb->prefix}sgndt_final_certifications 
                     WHERE final_certification_id = %d AND user_id = %d",
                    $cert_id, $user_id_temp
                ));
                
                if ($original_cert) {
                    if (empty($sector) && !empty($original_cert->sector)) {
                        $sector = $original_cert->sector;
                        error_log("Form 39: Sector retrieved from original certificate: {$sector}");
                    }
                    if (empty($scope) && !empty($original_cert->scope)) {
                        // Scope is stored as comma-separated string in database
                        $scope = array_filter(array_map('trim', explode(',', $original_cert->scope)));
                        error_log("Form 39: Scope retrieved from original certificate: " . implode(', ', $scope));
                    }
                } else {
                    error_log("Form 39: Original certificate not found for cert_id: {$cert_id}");
                }
            } else {
                error_log("Form 39: No cert_id found in field 28, cannot retrieve sector/scope from original certificate");
            }
        }
        
        // Ensure scope is always an array for consistency
        if (!is_array($scope) && !empty($scope)) {
            $scope = array($scope);
        }
        
        // Log final sector and scope values
        error_log("Form 39: Final sector value: " . ($sector ?: 'EMPTY'));
        error_log("Form 39: Final scope value: " . (is_array($scope) && !empty($scope) ? implode(', ', $scope) : 'EMPTY'));
    } else {
        // For other forms (Form 15, Form 31, etc.) - use CSS class-based detection
        // Define helper function to extract field values (same as notification PDF)
        $get_field_values = function ($field) use ($entry) {
            $values = [];
            $field_id = isset($field->id) ? (string) $field->id : (isset($field['id']) ? (string) $field['id'] : '');
            $field_inputs = isset($field->inputs) ? $field->inputs : (isset($field['inputs']) ? $field['inputs'] : []);

            if (!empty($field_inputs) && is_array($field_inputs)) {
                foreach ($field_inputs as $input) {
                    $input_id = is_array($input) && isset($input['id']) ? (string) $input['id'] : (string) $input;
                    if ($input_id === '') {
                        continue;
                    }
                    $entry_value = $entry[$input_id] ?? null;
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
                $entry_value = $entry[$field_id] ?? null;
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
            $field_id = $field->id;
            $label = isset($field->label) ? trim($field->label) : '';
            $css_class = isset($field->cssClass) ? $field->cssClass : (isset($field['cssClass']) ? $field['cssClass'] : '');
            $field_values = $get_field_values($field);

            // Extract sector using field_values
            if (empty($sector) && !empty($field_values) && !empty($css_class) && strpos($css_class, 'sector_' . strtolower($method)) !== false) {
                $sector = implode(', ', $field_values);
            }

            // Extract scope
            if (!empty($field_values) && !empty($css_class) && strpos($css_class, 'scope_' . strtolower($method)) !== false) {
                $scope = array_merge($scope, $field_values);
            }

            if ($field_id == 115 && !empty($entry[$field_id])) {
                $signature_url = $upload_dir['baseurl'] . '/gravity_forms/signatures/' . $entry[$field_id];
                $signature_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $signature_url);
            }
        }
    }
     // Extract only sector suffixes for final certificate (e.g., "w, c, f" instead of "Welds, Castings, Forgings")
     if (!empty($sector)) {
        $sector_parts = array_map('trim', explode(',', $sector));
        $suffix_parts = array_map(function($part) {
            // Extract suffix after " -" (e.g., "Welds - w" -> "w")
            if (preg_match('/\s*-\s*([a-z]+)$/i', $part, $matches)) {
                return $matches[1];
            }
            return $part; // If no suffix found, return original
        }, $sector_parts);
        $sector = implode(', ', $suffix_parts);
    }



    

    // Process signature
    $sign = '';
    if (!empty($signature_path)) {
        // Fix path for Windows/Dompdf
        $signature_path = str_replace('\\', '/', $signature_path);
    }
    
    if (!empty($signature_path) && file_exists($signature_path)) {
        $signature_cropped_path = $upload_dir['basedir'] . '/certificates/cropped_signature_' . $entry['id'] . '.png';
        $signature_cropped_path = str_replace('\\', '/', $signature_cropped_path);
        
        // Ensure directory exists
        if (!file_exists(dirname($signature_cropped_path))) {
            wp_mkdir_p(dirname($signature_cropped_path));
        }

        $final_path_to_use = $signature_path;

        if (function_exists('crop_signature_image')) {
            $cropped_url = crop_signature_image($signature_path, $signature_cropped_path);
            if (file_exists($signature_cropped_path)) {
                $final_path_to_use = $signature_cropped_path;
            }
        }

        // Convert to Base64 to bypass URL permission issues
        if (file_exists($final_path_to_use)) {
            $type = pathinfo($final_path_to_use, PATHINFO_EXTENSION);
            $data = file_get_contents($final_path_to_use);
            if ($data !== false) {
                $base64 = base64_encode($data);
                $sign = 'data:image/' . $type . ';base64,' . $base64;
            }
        }
    }

    // Generate certificate number
    $user_id = $entry['created_by'] ?? get_current_user_id();
    $user_data = get_userdata($user_id);
    $candidate_name = $user_data ? $user_data->display_name : 'N/A';
    $candidate_reg_number = get_user_meta($user_id, 'candidate_reg_number', true);
    if (empty($candidate_reg_number)) {
        error_log("Candidate registration number not found for user ID: $user_id");
        ob_end_clean();
        return false; // Cannot create certificate without registration number
    }
    
    // Determine validity period and certificate number suffix based on form ID
    $validity_period = 'initial';
    $certificate_number = '';
    $table_final_certifications = $wpdb->prefix . 'sgndt_final_certifications';
    
    if ($form_id == 39) {
        // Form 39: Renewal/Recertification by Exam
        // Get cert_id from field 28 to find original certificate
        $cert_id = rgar($entry, '28');
        
        // Determine if this is renewal or recertification
        // Method 1: Check field 27 (if it exists in Form 39)
        $renewal_type = rgar($entry, '27');
        
        // Method 2: Check if there's already a renewal certificate to determine if this is recertification
        $is_recertification = false;
        $suffix = '-03'; // Default to renewal
        $validity_period = 'renewal';
        
        if (!empty($cert_id)) {
            // Get original certificate
            $original_cert = $wpdb->get_row($wpdb->prepare(
                "SELECT certificate_number, issue_date FROM {$wpdb->prefix}sgndt_final_certifications 
                 WHERE final_certification_id = %d AND user_id = %d",
                $cert_id, $user_id
            ));
            
            if ($original_cert && !empty($original_cert->certificate_number)) {
                // Extract base number (remove any existing suffix like -01, -02, -03)
                $base_number = preg_replace('/-[0-9]+$/', '', $original_cert->certificate_number);
                
                // Check if there's already a renewal certificate (-03) for this base number
                $renewal_cert = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}sgndt_final_certifications 
                     WHERE user_id = %d AND certificate_number = %s AND status = 'issued'",
                    $user_id, $base_number . '-03'
                ));
                
                // Also check field 27 for explicit type
                $renewal_type_normalized = !empty($renewal_type) ? strtoupper(trim($renewal_type)) : '';
                
                if ($renewal_cert > 0 || strpos($renewal_type_normalized, 'RECERT') !== false || strpos($renewal_type_normalized, 'RECERTIFICATION') !== false) {
                    // This is recertification - use -04 suffix
                    $is_recertification = true;
                    $validity_period = 'recertification';
                    $suffix = '-04';
                } else {
                    // This is renewal - use -03 suffix
                    $validity_period = 'renewal';
                    $suffix = '-03';
                }
                
                $certificate_number = $base_number . $suffix;
                error_log("Form 39 certificate number generated: {$certificate_number} (base: {$base_number}, suffix: {$suffix}, type: {$validity_period}, field_27: {$renewal_type})");
            } else {
                // Fallback: use registration number with suffix
                // Default to renewal if we can't determine
                $certificate_number = $candidate_reg_number . $suffix;
                error_log("Form 39 certificate number fallback: {$certificate_number} (original cert not found, defaulting to renewal)");
            }
        } else {
            // Fallback: use registration number with suffix
            $certificate_number = $candidate_reg_number . $suffix;
            error_log("Form 39 certificate number fallback: {$certificate_number} (no cert_id found, defaulting to renewal)");
        }
    } else {
        // For other forms (Form 15, Form 31, etc.)
        // Determine validity period (initial, renew, or recertification)
        if ($form_id == 15 && isset($marks_entry['62']) && strtolower(trim($marks_entry['62'])) === 'yes') {
            // Special case for Form 15 when field 62 is 'yes'
            $validity_period = 'form15_yes';
        } else if (isset($entry['validity_period'])) {
            $validity_period = strtolower(trim($entry['validity_period']));
        }
        
        // Map validity period to sequence number
        $validity_map = [
            'initial' => '01',
            'form15_yes' => '02',  // For Form 15 with field 62 = 'yes'
            'renew' => '03',
            'recertification' => '04'
        ];
        $validity_seq = isset($validity_map[$validity_period]) ? $validity_map[$validity_period] : '01';
        
        // Get the certificate sequence number for this candidate
        $sequence_count = 1; // Default to 1 for first certificate
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_final_certifications'")) {
            // Count existing certificates for this user
            $cert_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_final_certifications WHERE user_id = %d",
                $user_id
            ));
            $sequence_count = intval($cert_count) + 1;
        }
        
        // Format: REG_NUMBER + 2-digit sequence + '-' + validity sequence
        // Example: SGNDT12301-01 (first initial cert), SGNDT12302-02 (second cert, renewal)
        $certificate_number = $candidate_reg_number . str_pad($sequence_count, 2, '0', STR_PAD_LEFT) . '-' . $validity_seq;
    }

    gform_update_meta($marks_entry_id, '_final_certificate_number_' . sanitize_title($method), $certificate_number);

    // Generate dates with DateTime, storing only date
    $issue_datetime = new DateTime('now', $timezone); // Timezone for calculation consistency
    $current_date = new DateTime('now', $timezone);
    
    // For Form 39 recertification (-03 suffix), use renewal certificate's expiry date
    // Also check by suffix to be more reliable
    $is_recert_by_suffix = (substr($certificate_number, -3) === '-03');
    if ($form_id == 39 && ($is_recert_by_suffix || (isset($is_recertification) && $is_recertification)) && !empty($cert_id)) {
        // Get the renewal certificate (with -02 suffix) to use its expiry date
        $base_cert_number = preg_replace('/-[0-9]+$/', '', $certificate_number);
        $renewal_cert = $wpdb->get_row($wpdb->prepare(
            "SELECT expiry_date, issue_date FROM {$wpdb->prefix}sgndt_final_certifications
             WHERE user_id = %d AND certificate_number = %s AND status = 'issued'
             ORDER BY issue_date DESC LIMIT 1",
            $user_id, $base_cert_number . '-02'
        ));
        
        if ($renewal_cert && !empty($renewal_cert->expiry_date)) {
            // Use renewal certificate's expiry date as the recertification issue date
            $renewal_expiry = new DateTime($renewal_cert->expiry_date, $timezone);
            
            // Use renewal expiry date if not past, otherwise current date
            if ($renewal_expiry < $current_date) {
                $issue_datetime = clone $current_date;
                error_log("Form 39 Recertification: Renewal expiry is past, using current date");
            } else {
                $issue_datetime = $renewal_expiry;
                error_log("Form 39 Recertification: Using renewal certificate expiry date as issue date: " . $renewal_cert->expiry_date);
            }
        } else {
            // Fallback: If renewal certificate doesn't exist, use initial certificate's expiry date
            $initial_cert = $wpdb->get_row($wpdb->prepare(
                "SELECT issue_date, expiry_date FROM {$wpdb->prefix}sgndt_final_certifications
                 WHERE user_id = %d 
                 AND (
                     certificate_number = %s 
                     OR certificate_number = %s
                     OR (certificate_number NOT LIKE '%%-02' AND certificate_number NOT LIKE '%%-03' AND certificate_number LIKE %s)
                 )
                 AND method = %s
                 AND level = %s
                 AND sector = %s
                 ORDER BY issue_date ASC
                 LIMIT 1",
                $user_id,
                $base_cert_number,
                $base_cert_number . '-01',
                $base_cert_number . '%',
                $method,
                $exam_level,
                $sector
            ));
            
            if ($initial_cert && !empty($initial_cert->expiry_date)) {
                // Use initial certificate's expiry date as the recertification issue date
                $initial_expiry = new DateTime($initial_cert->expiry_date, $timezone);
                
                if ($initial_expiry < $current_date) {
                    $issue_datetime = clone $current_date;
                    error_log("Form 39 Recertification: Initial expiry is past, using current date");
                } else {
                    $issue_datetime = $initial_expiry;
                    error_log("Form 39 Recertification: Renewal cert not found, using initial certificate expiry date: " . $initial_cert->expiry_date);
                }
            } else {
                error_log("Form 39 Recertification: Could not find initial or renewal certificate, using current date");
            }
        }
    }
    
    $issue_date = $issue_datetime->format('d.m.Y');
    $issue_date_sql = $issue_datetime->format('Y-m-d'); // Date only
    $expiry_datetime = clone $issue_datetime;
    
    // Set validity period based on certificate type
    // Check certificate suffix to determine validity period
    $cert_suffix = substr($certificate_number, -3); // Get last 3 characters (e.g., '-03', '-04')
    
    if ($cert_suffix === '-04' || (isset($validity_period) && $validity_period === 'recertification')) {
        // Recertification (-04 suffix): 10 years validity
        $expiry_datetime->modify('+10 years');
        error_log("Certificate {$certificate_number}: Recertification - 10 years validity");
    } elseif ($cert_suffix === '-03' || (isset($validity_period) && $validity_period === 'renew')) {
        // Renewal (-03 suffix): 5 years validity
        $expiry_datetime->modify('+5 years');
        error_log("Certificate {$certificate_number}: Renewal - 5 years validity");
    } else {
        // Initial (-01, -02) and others: 5 years validity
        $expiry_datetime->modify('+5 years');
        error_log("Certificate {$certificate_number}: Initial/Other - 5 years validity");
    }
    
    $expiry_date = $expiry_datetime->format('d.m.Y');
    $expiry_date_sql = $expiry_datetime->format('Y-m-d'); // Date only

    // Prepare PDF file
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
        <img src="' . get_stylesheet_directory_uri() . '/assets/logos/gvf-pdf.jpg" style="position:absolute;left: 2.8%; top: 0; left:0; width:95%; opacity:0.6; height: 90%; z-index:-1;"/>
        <div style="text-align:center; margin-top:10px;">
            <table style="width:100%;"><tr><td style="text-align:left;"><img src="' . get_stylesheet_directory_uri() . '/assets/logos/logondtss-n.png" style="height:76px;"/></td><td style="text-align:right;"><img src="' . get_stylesheet_directory_uri() . '/assets/logos/icndt.jpg" style="height:52px;"/></td></tr></table>
            <div style="text-align:center; margin-top:60px;">
                <h1 style="color:#3453a5; margin-top: -28px; margin-bottom: 0; padding: 0;" class="main_title"> NON-DESTRUCTIVE TESTING SOCIETY(SINGAPORE)</h1>
                <p style="margin-bottom: 0; padding: 0;width: 100%; text-align: center;">SGNDT Number: <span style="color: #1712fd; padding-top: 10px;">' . $candidate_reg_number . ' Issue 0</span></p>
                <p style="margin-bottom: 0; padding: 0;margin-top: 15px;">This is to certify that</p>
                <h3 style="color: #0001fc; margin-top: 0; padding-top: 5px; font-size: 28px; font-weight: 600;">' . strtoupper($candidate_name) . '</h3>
                <p style="border-top: 1px solid #444; margin-top: 0px; padding-top: 15px; text-align: center;width: fit-content; margin-inline: auto;">has met the established and published Requirements of NDTSS in accordance with ISO 9712:2021 <br>and certified in the following Non-destructive Testing Methods</p>
            </div>
            <div style="margin-top:-0px; text-align:center;">
                <p style="display: inline;"><i>Signature of Certified Individual</i></p>' . 
                (!empty($sign) ? '<img src="' . $sign . '" style="height:50px; margin-top:5px; border-bottom: 1px solid #000; display: inline;"/>' : '<div style="height:50px; margin-top:5px; border-bottom: 1px solid #000; display: inline-block; width: 200px;"></div>') . '
            </div>
            <table style="width:100%; border-collapse:collapse; text-align: center; margin-top:30px; border-color: #bdbdbd;" border="1" cellpadding="4">
                <thead style="background: #f7f7f7;"><tr><th>Method</th><th>Cert No</th><th>Sector</th><th>Level</th><th>Scope</th><th>Issue Date</th><th>Expiry Date</th></tr></thead>
                <tbody><tr style="text-align: center;"><td style="color: #1712fd;">' . $method . '</td><td style="color: #1712fd;">' . $certificate_number . '</td><td style="color: #1712fd;">' . (!empty($sector) ? $sector : '-') . '</td><td style="color: #1712fd;">' . $exam_level . '</td><td style="color: #1712fd;">' . (!empty($scope) && is_array($scope) ? implode(', ', $scope) : '-') . '</td><td style="color: #1712fd;">' . $issue_date . '</td><td style="color: #1712fd;">' . $expiry_date . '</td></tr></tbody>
            </table>
            <div style="position: absolute; right: 50px; bottom: 00px;">
                   <img src="' . get_stylesheet_directory_uri() . '/assets/logos/seal.png" 
     style="height:160px; width:160px; object-fit: contain; z-index: 99;" 
     alt="SGNDT Seals"/>
                </div>
            <div style="margin-top:40px; text-align:left; position: absolute; bottom: 00px;width:100%;">';
    
    // Get dynamic signature data
    $chairman_sig = cert_get_chairman_signature();
    $signatory_sig = cert_get_signatory_signature();
    
    $html .= '<table style="width:100%; border-collapse: collapse;">
                <tr>
                    <td style="text-align:left; width: 40%; padding: 10px 15px; vertical-align: top;">
                        <div style="margin-bottom: 5px;">
                            <strong style="font-size: 12px; display: block;">' . esc_html($chairman_sig['title']) . '</strong>
                            <strong style="font-size: 12px; display: block;">CERTIFICATION COMMITTEE</strong>
                        </div>';
    
    // Add chairman signature image if available
    if (!empty($chairman_sig['signature'])) {
        $html .= '<div style="margin: 8px 0;">
                    <img src="' . $chairman_sig['signature'] . '" style="height:50px;object-fit: contain; max-width:180px; display: block;" alt="Chairman Signature"/>
                  </div>';
    }
    
    $html .= '<div style="margin-top: 8px; border-top: 1px solid #000; width: 200px; padding-top: 3px;">';
    
    // Add chairman name if available
    if (!empty($chairman_sig['name'])) {
        $html .= '<span style="font-size: 11px; font-weight: 600; display: block;">' . esc_html($chairman_sig['name']) . '</span>';
    }
    
    $html .= '</div>
                    </td>
                    <td style="text-align:left; width: 40%; padding: 10px 15px; vertical-align: top;">
                        <div style="margin-bottom: 5px;">
                            <strong style="font-size: 12px; display: block;">' . esc_html($signatory_sig['title']) . '</strong>
                            <strong style="font-size: 12px; display: block;">NDTSS</strong>
                        </div>';
    
    // Add signatory signature image if available
    if (!empty($signatory_sig['signature'])) {
        $html .= '<div style="margin: 8px 0;">
                    <img src="' . $signatory_sig['signature'] . '" style="height:50px;object-fit: contain; max-width:180px; display: block;" alt="Signatory Signature"/>
                  </div>';
    }
    
    $html .= '<div style="margin-top: 8px; border-top: 1px solid #000; width: 200px; padding-top: 3px;">';
    
    // Add signatory name if available
    if (!empty($signatory_sig['name'])) {
        $html .= '<span style="font-size: 11px; font-weight: 600; display: block;">' . esc_html($signatory_sig['name']) . '</span>';
    }
    
    $html .= '</div>
                    </td>
                    <td style="text-align:center; width: 20%; padding: 10px 15px;">&nbsp;</td>
                </tr>
              </table>
                <div style="margin-top:60px; text-align:left; position: absolute; bottom: 0px;width:100%;"><table style="width:100%;"><tr><td style="text-align:left; width: 33%; padding: 15px; font-size: 12px;">Form No: NDTSS-QMS-FM-024</td><td style="text-align:center; width: 34%;padding: 15px; font-size: 12px;">Refer overleaf for Notes, details of certification sector and scope</td><td style="text-align:right; width: 33%; padding: 15px; font-size: 12px;"> Rev. 5 (' . date('d F Y') . ')</td></tr></table></div>
            </div>
        </div>
    </div>';

    $html_notes = '<div style="font-size:10pt;"><img src="' . get_stylesheet_directory_uri() . '/assets/logos/gvf-pdf.jpg" style="position:absolute;left: 2.8%; top: 0; left:0; width:95%; opacity:0.6; height: 90%; z-index:-1;"/><div style="width: 100%; display: block; clear: both; margin-top: 30px;"><div style="width: 35%;float: left; padding-right: 10px;"><p style="text-align: left; font-size: 16px; font-weight: 600;">Abbreviation for Certification Sector</p><table border="1" cellpadding="4" style="width:100%; border-collapse:collapse;"><tr><th style="width: 65px; background: #f7f7f7;">Industry<br> Sector</th><th style="background: #f7f7f7;">Details</th></tr><tr><td style="text-align: center;">s</td><td>Pre- & In-service Inspection which includes Manufacturing</td></tr><tr><td style="text-align: center;">a</td><td>Aerospace</td></tr><tr><td style="text-align: center;">r</td><td>Railway Maintenance</td></tr><tr><td style="text-align: center;">m</td><td>Manufacturing</td></tr><tr><td style="text-align: center;">ci, me, el</td><td>Civil, Mechanical, Electrical (TT)</td></tr></table><table border="1" cellpadding="4" style="width:100%; border-collapse:collapse;"><tr><th style="text-align: center;width: 65px;background: #f7f7f7;">Product <br>Sector</th><th style="background: #f7f7f7;">Details</th></tr><tr><td style="text-align: center;">w</td><td>Welds</td></tr><tr><td style="text-align: center;">c</td><td>Castings</td></tr><tr><td style="text-align: center;">wp</td><td>Wrought Products </td></tr><tr><td style="text-align: center;">t</td><td>Tubes and Pipes</td></tr><tr><td style="text-align: center;">f</td><td>Forgings</td></tr><tr><td style="text-align: center;">frp</td><td>Reinforced Plastics</td></tr></table></div><div style="width: 62%;float: right; padding-left: 10px;"><p style="text-align: left; font-size: 16px; padding-inline: 15px;font-weight: 600;">Abbreviation for Scope / Technique</p><table border="1" cellpadding="4" style="width:100%; border-collapse:collapse;"><tr><th style="text-align: left;background: #f7f7f7;">Scope</th><th style="text-align: left;background: #f7f7f7;">Details</th></tr><tr><td>F / P / L / ML</td><td>Fixed / Portable Equipment / Line System / Magnetic Flux Leakage</td></tr><tr><td>X / G / DR / CR</td><td>X-ray / Gamma-ray / Digital Radiography / Computed Radiography</td></tr><tr><td>PL / P / T / N / NZ / PAUT / TOFD / AUT</td><td>Plate / Pipe / T Joint / Node / Nozzle Weld, Phased Array, Time of Flight, Auto UT</td></tr><tr><td>S / W / Fe / NFe / FP</td><td>Seamless, Welded, Ferrous, Non-Ferrous, Flat Plate</td></tr><tr><td>Tu</td><td>Tubes (ET)</td></tr><tr><td>D / R</td><td>Direct / Remote (VT)</td></tr><tr><td>V / FL</td><td>Visible / Fluorescent (PT / MT)</td></tr><tr><td>TT / LM</td><td>Thickness Testing / Lamination (UT)</td></tr><tr><td>Pa / Ac</td><td>Passive / Active (Thermal Infrared Testing)</td></tr></table></div></div><div style="clear: both; width: 100%; display: block;"></div><table style="width:100%;"><tr><td><div style="width: 100%; display: block; max-width: 100%;"><h4 style="margin-top:20px; display: block; width: 100%; font-size: 20px; margin-bottom: 8px;">Notes:</h4><ol style="padding-left: 15px; margin-top: 0;"><li style="margin-bottom: 8px;">Candidate appearing in Industrial Sector “s” will be given 3 specimens with a mixture of welding and casting or forging or wrought products as per ISO 9712:2021.</li><li style="margin-bottom: 8px;">For UT, scope applies to product sector welds only.</li><li style="margin-bottom: 8px;">The SAC accreditation mark indicates accreditation certificate number PC-2017-03.</li><li style="margin-bottom: 8px;">This certificate is property of NDTSS and not valid without SGNDT seal.</li><li style="margin-bottom: 8px;">NDTSS is accredited by SAC under ISO/IEC 17024:2012.</li><li style="margin-bottom: 8px;">This certificate is issued as per NDTSS/SGNDT OM-001 and ISO 9712:2021.</li></ol></div></td></tr></table><div style="text-align:right; font-style:italic; font-size:9pt; margin-top:20px;">Form No: NDTSS-QMS-FM-024  Rev. 5 (' . date('d F Y') . ')</div></div>';

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
        'validity_period' => $validity_period
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

    // Send notification with fallback - Get center name based on form type
    $form_id = $entry['form_id'];
    if ($form_id == 30 || $form_id == 39) {
        $center_name = $entry['9'] ?? 'N/A';
    } else {
        $center_name = $entry['833'] ?? 'N/A';
    }
    $center_post = get_page_by_title($center_name, OBJECT, 'exam_center');
    if ($center_post) {
        gform_update_meta($exam_entry_id, '_linked_exam_center', $center_post->ID);
        if (function_exists('send_exam_certificate')) {
            send_exam_certificate($exam_entry_id, $marks_entry_id, $center_post, $certificate_data, $method, '');
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
    $candidate_name = $user_data ? $user_data->display_name : 'N/A';
    $candidate_email = $user_data && is_email($user_data->user_email) ? $user_data->user_email : '';
    
    // Get email fields based on form type
    $form_id = $entry['form_id'];
    if ($form_id == 30) {
        // Retest form - field 26 is candidate email
        $field_863_value = rgar($entry, '26');
    } elseif ($form_id == 39) {
        // Renewal form - field 12 is candidate email
        $field_863_value = rgar($entry, '12');
    } else {
        // Initial form (15)
        $field_863_value = rgar($entry, '863');
    }
    // Generate secure download URL
    $certificate_path = $certificate_data['path'] ?? '';
    $certificate_filename = !empty($certificate_path) ? basename($certificate_path) : '';
    
    if (!empty($certificate_filename)) {
        $secure_url = get_stylesheet_directory_uri() . '/includes/secure-exam-certificate-download.php';
        $certificate_url = add_query_arg([
            'file' => $certificate_filename,
            'entry_id' => $entry_id,
            'v' => time()
        ], $secure_url);
    } else {
        $certificate_url = '';
    }
    
    $issue_date = date('d.m.Y', strtotime($certificate_data['generated_at'] ?? 'now'));

    $center_name = get_the_title($center_post->ID);
    $center_admin_id = get_post_meta($center_post->ID, '_center_admin_id', true);
    $center_admin = get_userdata($center_admin_id);

    $admin_users = get_users([
        'role'   => 'administrator',
        'fields' => ['user_email'],
    ]);

    $to = [];
    if (is_email($candidate_email)) {
        $to[] = sanitize_email($candidate_email);
    }
    if (is_email($field_863_value)) {
        $to[] = sanitize_email($field_863_value);
    }
    $to = array_unique($to);

    // Candidate Email
    $sent_to_candidate = false;
    
    // Check if notification is enabled
    $enable_notification = get_option('ndtss_enable_final_cert_notification_email', 1);
    
    if ($enable_notification && !empty($to)) {
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
        
        // Get User Profile URL
        $profile_page = get_page_by_title('User Profile');
        $profile_url = $profile_page ? get_permalink($profile_page->ID) : home_url('/user-profile');
        
        $subject = 'SGNDT ' . $exam_type_label . ' Final Certificate Issued';
        $body = '
        Dear ' . esc_html($candidate_name) . ',<br><br>
        Your final certificate for ' . strtolower($exam_type_label) . ' has been issued<br><br>
        <strong>Method:</strong> ' . esc_html($method) . '<br>
        <strong>Date of Issue:</strong> ' . esc_html($issue_date) . '<br><br>
        Please find your final certificate attached to this email.<br><br>
        You can also view and download it from your <a href="' . esc_url($profile_url) . '">User Profile</a>.<br>
        Direct Link: <a href="' . esc_url($certificate_url) . '" target="_blank">Download Certificate</a> (login required).<br><br>
        Best regards,<br>
        NDTSS Certification Team
        ';
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
        
        if (!$sent_to_candidate) {
            error_log("Failed to send final certificate email to: " . implode(', ', $to));
        }

        $log['candidate_email'] = [
            'email' => $to,
            'status' => $sent_to_candidate ? 'sent' : 'failed',
            'timestamp' => current_time('mysql'),
        ];
    } else {
         $log['candidate_email'] = [
            'status' => 'skipped (disabled or no email)',
            'timestamp' => current_time('mysql'),
        ];
        if (!$enable_notification) {
             error_log("Final certificate email disabled in settings. Skipping.");
        }
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
        
        $subject = '📢 Candidate ' . $exam_type_label . ' Final Certificate Issued – ' . esc_html($candidate_name);
        $body = '
        <p><strong>Candidate Name:</strong> ' . esc_html($candidate_name) . '</p>
        <p><strong>Exam Type:</strong> ' . esc_html($exam_type_label) . '</p>
        <p><strong>Method:</strong> ' . esc_html($method) . '</p>
        <p><strong>Exam Center:</strong> ' . esc_html($center_name) . '</p>
        <p><strong>Date of Issue:</strong> ' . esc_html($issue_date) . '</p>
        <p>Final certificate has been sent to the candidate.</p>
        <p><a href="' . esc_url($certificate_url) . '" target="_blank">View Certificate Online</a> (login required)</p>
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

