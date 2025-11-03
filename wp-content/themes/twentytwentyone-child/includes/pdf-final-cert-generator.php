<?php
require_once get_stylesheet_directory() . '/includes/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

function generate_final_certificate_pdf($exam_entry_id, $marks_entry_id, $method) {
    global $wpdb;
    
    // Enhanced error logging function
    $log_error = function($message, $context = []) {
        $current_time = current_time('mysql', true);
        $user_id = get_current_user_id();
        $context_str = !empty($context) ? ' Context: ' . json_encode($context) : '';
        error_log("[PDF_GENERATOR] $message at $current_time, user_id=$user_id$context_str");
        
        // Also log to a custom log file for easier debugging
        $log_file = wp_upload_dir()['basedir'] . '/certificates/pdf_generator_errors.log';
        $log_entry = date('Y-m-d H:i:s') . " - $message" . $context_str . "\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    };

    $log_error("Function started", ['exam_entry_id' => $exam_entry_id, 'marks_entry_id' => $marks_entry_id, 'method' => $method]);

    // Validate inputs
    if (!is_numeric($exam_entry_id) || !is_numeric($marks_entry_id) || $exam_entry_id <= 0 || $marks_entry_id <= 0 || empty($method)) {
        $log_error("Invalid input parameters", ['exam_entry_id' => $exam_entry_id, 'marks_entry_id' => $marks_entry_id, 'method' => $method]);
        ob_end_clean();
        return false;
    }

    ob_start();

    // Fetch entries
    try {
        $timezone = wp_timezone(); // Use WordPress-configured timezone for date calculations
        $log_error("Fetching entries");
        
        $entry = GFAPI::get_entry($exam_entry_id);
        if (is_wp_error($entry)) {
            $log_error("Exam entry error", ['error' => $entry->get_error_message()]);
            ob_end_clean();
            return false;
        }
        if (empty($entry)) {
            $log_error("Exam entry is empty");
            ob_end_clean();
            return false;
        }
        
        $marks_entry = GFAPI::get_entry($marks_entry_id);
        if (is_wp_error($marks_entry)) {
            $log_error("Marks entry error", ['error' => $marks_entry->get_error_message()]);
            ob_end_clean();
            return false;
        }
        if (empty($marks_entry)) {
            $log_error("Marks entry is empty");
            ob_end_clean();
            return false;
        }
        
        $log_error("Entries fetched successfully", ['entry_form_id' => $entry['form_id'], 'marks_form_id' => $marks_entry['form_id']]);
    } catch (Exception $e) {
        $log_error("Exception while fetching entries", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        ob_end_clean();
        return false;
    }

    try {
        $exam_form = GFAPI::get_form($entry['form_id']);
        if (is_wp_error($exam_form)) {
            $log_error("Exam form error", ['form_id' => $entry['form_id'], 'error' => $exam_form->get_error_message()]);
            ob_end_clean();
            return false;
        }
        if (empty($exam_form)) {
            $log_error("Exam form is empty", ['form_id' => $entry['form_id']]);
            ob_end_clean();
            return false;
        }
        $log_error("Exam form fetched successfully", ['form_id' => $entry['form_id'], 'form_title' => $exam_form['title']]);
    } catch (Exception $e) {
        $log_error("Exception while fetching exam form", ['form_id' => $entry['form_id'], 'error' => $e->getMessage()]);
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

    if ($form_id == 39) {
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
    $upload_dir = wp_upload_dir(); // Define upload_dir here since it's used below
    
    try {
        $log_error("Processing signature", ['signature_path' => $signature_path, 'file_exists' => file_exists($signature_path)]);
        
        if (!empty($signature_path) && file_exists($signature_path)) {
            $signature_cropped_path = $upload_dir['basedir'] . '/certificates/cropped_signature_' . $entry['id'] . '.png';
            $signature_cropped_url  = $upload_dir['baseurl'] . '/certificates/cropped_signature_' . $entry['id'] . '.png';

            if (function_exists('crop_signature_image')) {
                $crop_result = crop_signature_image($signature_path, $signature_cropped_path);
                if ($crop_result) {
                    $sign = $signature_cropped_url; // store URL instead of path
                    $log_error("Signature cropped successfully", ['cropped_url' => $signature_cropped_url]);
                } else {
                    $log_error("Signature cropping failed");
                }
            } else {
                $log_error("crop_signature_image function not found");
            }
        } else {
            $log_error("No signature to process", ['signature_path' => $signature_path]);
        }
    } catch (Exception $e) {
        $log_error("Exception while processing signature", ['error' => $e->getMessage()]);
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
    } elseif ($form_id == 39) {
        // Check field ID 27 for renewal vs recertification (case-insensitive, robust matching)
        $field_27_raw = isset($entry['27']) ? $entry['27'] : '';
        $field_27_value = strtoupper(trim(is_array($field_27_raw) ? implode(' ', $field_27_raw) : $field_27_raw));

        if (strpos($field_27_value, 'RECERT') !== false || strpos($field_27_value, 'RECERTIFICATION') !== false) {
            // Recertification certificate - add -02 suffix
            $certificate_suffix = '-02';
        } elseif (strpos($field_27_value, 'RENEW') !== false || $field_27_value === 'RENEWAL') {
            // Renewal certificate - add -01 suffix
            $certificate_suffix = '-01';
        }
    }
    
    // Generate certificate number with appropriate suffix
    $certificate_number = $candidate_reg_number . $certificate_suffix;
    // echo $certificate_number;
    // die;

    gform_update_meta($marks_entry_id, '_final_certificate_number_' . sanitize_title($method), $certificate_number);

    // Generate dates with DateTime, storing only date
    // For renewal/recertification, issue date should be the expiry date of the previous certificate
    // BUT if current date is past the expiry date, use current date instead (late renewal)
    $current_datetime = new DateTime('now', $timezone);
    $issue_datetime = clone $current_datetime; // Default to now
    
    if ($form_id == 31 && !empty($certificate_suffix)) {
        // This is a renewal or recertification - get previous certificate's expiry date
        global $wpdb;
        $table = $wpdb->prefix . 'sgndt_final_certifications';
        
        if ($certificate_suffix === '-01') {
            // Renewal: Get original certificate's expiry date
            $previous_cert = $wpdb->get_row($wpdb->prepare(
                "SELECT expiry_date FROM {$table} 
                 WHERE user_id = %d AND certificate_number = %s 
                 ORDER BY issue_date DESC LIMIT 1",
                $user_id,
                $candidate_reg_number
            ));
            
            if ($previous_cert && !empty($previous_cert->expiry_date)) {
                $previous_expiry = new DateTime($previous_cert->expiry_date, $timezone);
                
                // If current date is past expiry, use current date (late renewal)
                // Otherwise use expiry date for continuous coverage
                if ($current_datetime > $previous_expiry) {
                    $issue_datetime = clone $current_datetime;
                    error_log("Renewal certificate: Late renewal detected. Using current date as issue date: " . $current_datetime->format('Y-m-d'));
                } else {
                    $issue_datetime = $previous_expiry;
                    error_log("Renewal certificate: On-time renewal. Using original certificate expiry date as issue date: {$previous_cert->expiry_date}");
                }
            }
        } elseif ($certificate_suffix === '-02') {
            // Recertification: Get renewal certificate's expiry date
            $previous_cert = $wpdb->get_row($wpdb->prepare(
                "SELECT expiry_date FROM {$table} 
                 WHERE user_id = %d AND certificate_number = %s 
                 ORDER BY issue_date DESC LIMIT 1",
                $user_id,
                $candidate_reg_number . '-01'
            ));
            
            if ($previous_cert && !empty($previous_cert->expiry_date)) {
                $previous_expiry = new DateTime($previous_cert->expiry_date, $timezone);
                
                // If current date is past expiry, use current date (late recertification)
                // Otherwise use expiry date for continuous coverage
                if ($current_datetime > $previous_expiry) {
                    $issue_datetime = clone $current_datetime;
                    error_log("Recertification certificate: Late recertification detected. Using current date as issue date: " . $current_datetime->format('Y-m-d'));
                } else {
                    $issue_datetime = $previous_expiry;
                    error_log("Recertification certificate: On-time recertification. Using renewal certificate expiry date as issue date: {$previous_cert->expiry_date}");
                }
            } else {
                // Fallback: try to get original certificate's expiry + 5 years
                $original_cert = $wpdb->get_row($wpdb->prepare(
                    "SELECT expiry_date FROM {$table} 
                     WHERE user_id = %d AND certificate_number = %s 
                     ORDER BY issue_date DESC LIMIT 1",
                    $user_id,
                    $candidate_reg_number
                ));
                
                if ($original_cert && !empty($original_cert->expiry_date)) {
                    $original_expiry = new DateTime($original_cert->expiry_date, $timezone);
                    $calculated_expiry = clone $original_expiry;
                    $calculated_expiry->modify('+5 years'); // Add 5 years if no renewal certificate exists
                    
                    // Check if calculated date is in the past
                    if ($current_datetime > $calculated_expiry) {
                        $issue_datetime = clone $current_datetime;
                        error_log("Recertification certificate: Calculated date is past. Using current date");
                    } else {
                        $issue_datetime = $calculated_expiry;
                        error_log("Recertification certificate: Using original certificate expiry + 5 years as issue date");
                    }
                }
            }
        }
    }
    
    $issue_date = $issue_datetime->format('d.m.Y');
    $issue_date_sql = $issue_datetime->format('Y-m-d'); // Date only
    
    // Calculate expiry date based on certificate type
    $expiry_datetime = clone $issue_datetime;
    if ($certificate_suffix === '-02') {
        // Recertification: 10 years validity
        $expiry_datetime->modify('+10 years');
    } else {
        // Initial and Renewal: 5 years validity
        $expiry_datetime->modify('+5 years');
    }
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
    try {
        $log_error("Starting PDF generation with DOMPDF", ['file_path' => $file_path]);
        
    $html = '<head>
    <style>
         @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");
    @page { margin: 20px; }
        body { margin: 0; padding: 15px; border: 2px solid #494949; width: 278mm; height: 190mm; box-sizing: border-box;  font-family: "Poppins", sans-serif; line-height: 1.1;}
        .able_new, .able_new th, .able_new td{border: 1px solid #eee;}

    </style>
    </head>
    <div style="position:relative; font-size:11pt;">
        <img src="' . get_stylesheet_directory_uri() . '/assets/logos/gvf-pdf.jpg" style="position:absolute; top: -00px; left:0; width:100%; opacity:0.5; height: 100%; z-index:-1;object-fit: contain;"/>
        <div style="text-align:center; margin-top:10px;">
            <table style="width:100%;"><tr><td style="text-align:left;"><img src="' . get_stylesheet_directory_uri() . '/assets/logos/logondtss.png" style="height:65px;"/></td><td style="text-align:right;"><img src="' . get_stylesheet_directory_uri() . '/assets/logos/icndt.jpg" style="height:65px;"/></td></tr></table>
            <div style="text-align:center; margin-top:60px;">
                <h2 style="color:#3453a5; margin-top: -30px; margin-bottom: 0; padding: 0; font-size: 32px;" class="main_title"> NON-DESTRUCTIVE TESTING SOCIETY(SINGAPORE)</h2>
                <p style="margin-bottom: 0; padding: 0;width: 100%; text-align: center;">SGNDT Number: <span style="color: #1712fd; padding-top: 10px;">' . $candidate_reg_number . ' Issue 0</span></p>
                <p style="margin-bottom: 0; padding: 0;margin-top: 12px;">This is to certify that</p>
                <h3 style="color: #0001fc; margin-top: 0; margin-bottom: 10px; padding-top: 3px; font-size: 28px; font-weight: 600;">' . strtoupper($candidate_name) . '</h3>
                <p style="border-top: 1px solid #444; margin-top: 0px; padding-top: 10px; text-align: center;width: fit-content; margin-inline: auto;">has met the established and published Requirements of NDTSS in accordance with ISO 9712:2021 <br>and certified in the following Non-destructive Testing Methods</p>
            </div>
            <div style="margin-top:-0px; text-align:center;">
                <p style="display: inline;"><i>Signature of Certified Individual</i></p>
                <img src="' . $sign . '" style="height:50px; margin-top:5px; border-bottom: 1px solid #e0e5ed; display: inline;"/>
            </div>
            <table class="table_new" style="width:100%; border-collapse:collapse; text-align: center; margin-top:30px; border-color: #bdbdbd;" border="1" cellpadding="4">
                <thead style="background: #f3f4f7;"><tr><th style="border:1px solid #e0e5ed;font-weight: 500;">Method</th><th style="border:1px solid #e0e5ed;font-weight: 500;">Cert No</th><th style="border:1px solid #e0e5ed;font-weight: 500;">Sector</th><th style="border:1px solid #e0e5ed;font-weight: 500;">Level</th><th style="border:1px solid #e0e5ed;font-weight: 500;">Scope</th><th style="border:1px solid #e0e5ed;font-weight: 500;">Issue Date</th><th style="border:1px solid #e0e5ed;font-weight: 500;">Expiry Date</th></tr></thead>
                <tbody><tr style="text-align: center;"><td style="color: #111;border:1px solid #e0e5ed">' . $method . '</td><td style="color: #111;border:1px solid #e0e5ed">' . $certificate_number . '</td><td style="color: #111;border:1px solid #e0e5ed">' . $sector . '</td><td style="color: #111;border:1px solid #e0e5ed">' . $exam_level . '</td><td style="color: #111;border:1px solid #e0e5ed">' . (!empty($scope) && is_array($scope) ? implode(', ', $scope) : '') . '</td><td style="color: #111;border:1px solid #e0e5ed">' . $issue_date . '</td><td style="color: #111;border:1px solid #e0e5ed">' . $expiry_date . '</td></tr></tbody>
            </table>
            <div style="position: absolute; right: 40px; bottom: 00px;">
                   <img src="' . get_stylesheet_directory_uri() . '/assets/logos/seal.png" 
     style="height:140px; width:140px; object-fit: contain; z-index: 99;" 
     alt="SGNDT Seals"/>
                </div>
            <div style="margin-top:40px; text-align:left; position: absolute; bottom: 0px;width:100%;">
                <table style="width:100%;"><tr><td style="text-align:left; width: 33%; padding: 15px;"><strong style="font-size: 12px;font-weight: 500;">CHAIRMAN / VICE CHAIRMAN</strong><br><strong style="font-size: 12px;font-weight: 500;">CERTIFICATION COMMITTEE</strong></td><td style="text-align:left; width: 34%;padding: 15px;"><strong style="font-size: 12px;font-weight: 500;">AUTHORIZED SIGNATORY</strong><br><strong style="font-size: 12px;font-weight: 500;">NDTSS</strong></td><td style="text-align:center; width: 33%;padding: 15px;">&nbsp;</td></tr><tr><td style="text-align:left;padding: 15px;">__________________</td><td style="text-align:left;padding: 15px;">__________________</td><td style="text-align:center;padding: 0px;"></td></tr></table>
                <div style="margin-top:60px; text-align:left; position: absolute; bottom: 0px;width:100%;"><table style="width:100%;"><tr><td style="text-align:left; width: 33%; padding: 15px; font-size: 12px;font-weight: 500;">Form No: NDTSS-QMS-FM-024</td><td style="text-align:center; width: 34%;padding: 15px; font-size: 12px;font-weight: 500;">Refer overleaf for Notes, details of certification sector and scope</td><td style="text-align:right; width: 33%; padding: 15px; font-size: 12px;"> Rev. 5 (' . date('d F Y') . ')</td></tr></table></div>
            </div>
        </div>
    </div>';

    $html_notes = '<div style="font-size:9pt; line-height: 10px;"><img src="' . get_stylesheet_directory_uri() . '/assets/logos/gvf-pdf.jpg" style="position:absolute; top: 5px; left:5px; width:calc(100% - 30px); opacity:0.5; height: calc(100% - 30px); z-index:-1; object-fit: contain;"/><div style="width: 100%; display: block; clear: both; margin-top: 10px;"><div style="width: 35%;float: left; padding-right: 10px;"><p style="text-align: left; font-size: 16px; font-weight: 600;font-weight: 500;">Abbreviation for Certification Sector</p><table border="1" cellpadding="4" style="width:100%; border-collapse:collapse; "><tr><th style="width: 65px; background: #f3f4f7;border: 1px solid #ddd;line-height: 10px;font-weight: 500;">Industry Sector</th><th style="background: #f3f4f7;border: 1px solid #ddd;font-weight: 500;">Details</th></tr><tr><td style="text-align: center;border: 1px solid #ddd">s</td><td style="border: 1px solid #ddd;line-height: 10px;">Pre- & In-service Inspection which includes Manufacturing</td></tr><tr><td style="text-align: center;border: 1px solid #ddd;">a</td><td style="border: 1px solid #ddd">Aerospace</td></tr><tr><td style="text-align: center;border: 1px solid #ddd">r</td><td style="border: 1px solid #ddd">Railway Maintenance</td></tr><tr><td style="text-align: center;border: 1px solid #ddd">m</td><td style="border: 1px solid #ddd">Manufacturing</td></tr><tr><td style="text-align: center;border: 1px solid #ddd">ci, me, el</td><td style="border: 1px solid #ddd">Civil, Mechanical, Electrical (TT)</td></tr></table><table border="1" cellpadding="4" style="width:100%; border-collapse:collapse;"><tr><th style="text-align: center;width: 65px;background: #f3f4f7;border: 1px solid #ddd;line-height: 10px;font-weight: 500;">Product Sector</th><th style="background: #f3f4f7;border: 1px solid #ddd;font-weight: 500;">Details</th></tr><tr><td style="text-align: center;border: 1px solid #ddd">w</td><td style="border: 1px solid #ddd;">Welds</td></tr><tr><td style="text-align: center;border: 1px solid #ddd">c</td><td style="border: 1px solid #ddd">Castings</td></tr><tr><td style="text-align: center;border: 1px solid #ddd">wp</td><td style="border: 1px solid #ddd">Wrought Products </td></tr><tr><td style="text-align: center;border: 1px solid #ddd">t</td><td style="border: 1px solid #ddd">Tubes and Pipes</td></tr><tr><td style="text-align: center;border: 1px solid #ddd">f</td><td style="border: 1px solid #ddd">Forgings</td></tr><tr><td style="text-align: center;border: 1px solid #ddd">frp</td><td style="border: 1px solid #ddd">Reinforced Plastics</td></tr></table></div><div style="width: 62%;float: right; padding-left: 10px;"><p style="text-align: left; font-size: 16px; padding-inline: 15px;font-weight: 600;font-weight: 500;">Abbreviation for Scope / Technique</p><table border="1" cellpadding="4" style="width:100%; border-collapse:collapse;"><tr><th style="text-align: left;background: #f3f4f7;border: 1px solid #ddd;font-weight: 500;">Scope</th><th style="text-align: left;background: #f3f4f7;border: 1px solid #ddd;font-weight: 500;">Details</th></tr><tr><td style="border: 1px solid #ddd">F / P / L / ML</td><td style="border: 1px solid #ddd">Fixed / Portable Equipment / Line System / Magnetic Flux Leakage</td></tr><tr><td style="border: 1px solid #ddd">X / G / DR / CR</td><td style="border: 1px solid #ddd">X-ray / Gamma-ray / Digital Radiography / Computed Radiography</td></tr><tr><td style="border: 1px solid #ddd">PL / P / T / N / NZ / PAUT / TOFD / AUT</td><td style="border: 1px solid #ddd">Plate / Pipe / T Joint / Node / Nozzle Weld, Phased Array, Time of Flight, Auto UT</td></tr><tr><td style="border: 1px solid #ddd">S / W / Fe / NFe / FP</td><td style="border: 1px solid #ddd">Seamless, Welded, Ferrous, Non-Ferrous, Flat Plate</td></tr><tr><td style="border: 1px solid #ddd">Tu</td><td style="border: 1px solid #ddd">Tubes (ET)</td></tr><tr><td style="border: 1px solid #ddd">D / R</td><td style="border: 1px solid #ddd">Direct / Remote (VT)</td></tr><tr><td style="border: 1px solid #ddd">V / FL</td><td style="border: 1px solid #ddd">Visible / Fluorescent (PT / MT)</td></tr><tr><td style="border: 1px solid #ddd">TT / LM</td><td style="border: 1px solid #ddd">Thickness Testing / Lamination (UT)</td></tr><tr><td style="border: 1px solid #ddd">Pa / Ac</td><td style="border: 1px solid #ddd">Passive / Active (Thermal Infrared Testing)</td></tr></table></div></div><div style="clear: both; width: 100%; display: block;"></div><table style="width:100%;"><tr><td><div style="width: 100%; display: block; max-width: 100%;"><h4 style="margin-top:20px; display: block; width: 100%; font-size: 20px; margin-bottom: 8px;font-weight: 500;">Notes:</h4><ol style="padding-left: 15px; margin-top: 0;"><li style="margin-bottom: 8px;">Candidate appearing in Industrial Sector “s” will be given 3 specimens with a mixture of welding and casting or forging or wrought products as per ISO 9712:2021.</li><li style="margin-bottom: 8px;">For UT, scope applies to product sector welds only.</li><li style="margin-bottom: 8px;">The SAC accreditation mark indicates accreditation certificate number PC-2017-03.</li><li style="margin-bottom: 8px;">This certificate is property of NDTSS and not valid without SGNDT seal.</li><li style="margin-bottom: 8px;">NDTSS is accredited by SAC under ISO/IEC 17024:2012.</li><li style="margin-bottom: 8px;">This certificate is issued as per NDTSS/SGNDT OM-001 and ISO 9712:2021.</li></ol></div></td></tr></table><div style="text-align:right; font-style:italic; font-size:9pt; margin-top:20px;">Form No: NDTSS-QMS-FM-024  Rev. 5 (' . date('d F Y') . ')</div></div>';

    $full_html = $html . '<div style="page-break-after: always;"></div>' . $html_notes;

        $log_error("HTML content prepared", ['html_length' => strlen($full_html)]);
        
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'landscape');
        
        $log_error("DOMPDF initialized, loading HTML");
        $dompdf->loadHtml($full_html);
        
        $log_error("HTML loaded, starting render");
        $dompdf->render();
        $log_error("DOMPDF render completed successfully");
        
    } catch (Exception $e) {
        $log_error("DOMPDF processing failed", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        ob_end_clean();
        return false;
    }

    // Save PDF to file
    try {
        $log_error("Saving PDF to file", ['file_path' => $file_path, 'file_exists' => file_exists($file_path)]);
        
        if (file_exists($file_path)) {
            unlink($file_path);
            $log_error("Existing file removed");
        }
        
        $pdf_output = $dompdf->output();
        $log_error("PDF output generated", ['output_size' => strlen($pdf_output)]);
        
        $bytes_written = file_put_contents($file_path, $pdf_output);
        $log_error("File write attempt", ['bytes_written' => $bytes_written, 'file_exists_after' => file_exists($file_path)]);
        
        if (!file_exists($file_path)) {
            throw new Exception("File not created at $file_path");
        }
        
        $log_error("PDF file saved successfully", ['file_size' => filesize($file_path)]);
        
    } catch (Exception $e) {
        $log_error("Failed to save PDF file", ['error' => $e->getMessage(), 'file_path' => $file_path]);
        ob_end_clean();
        return false;
    }

    // Save certificate data to wp_sgndt_final_certifications
    try {
        $table_final_certifications = $wpdb->prefix . 'sgndt_final_certifications';
        $user_id = $entry['created_by'] ?? get_current_user_id();
        
        $log_error("Saving certificate data to database", ['table' => $table_final_certifications, 'user_id' => $user_id]);

        if (!$wpdb->get_var("SHOW TABLES LIKE '$table_final_certifications'")) {
            $log_error("Database table does not exist", ['table' => $table_final_certifications]);
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
        'validity_period' => (
            $form_id == 15
                ? 'initial'
                : ((strpos($field_27_value, 'RECERT') !== false || strpos($field_27_value, 'RECERTIFICATION') !== false)
                    ? 'recertification'
                    : ((strpos($field_27_value, 'RENEW') !== false || $field_27_value === 'RENEWAL')
                        ? 'renewal'
                        : 'initial'))
        )
    ];
    $insert_formats = ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

        $log_error("Inserting certificate data", ['insert_data' => $insert_data]);
        
        $insert_result = $wpdb->insert($table_final_certifications, $insert_data, $insert_formats);
        if ($insert_result === false) {
            $log_error("Database insert failed", ['error' => $wpdb->last_error, 'insert_data' => $insert_data]);
            ob_end_clean();
            return false;
        }
        
        $log_error("Certificate data inserted successfully", ['insert_id' => $wpdb->insert_id]);
        
    } catch (Exception $e) {
        $log_error("Exception while saving certificate data", ['error' => $e->getMessage()]);
        ob_end_clean();
        return false;
    }

    $final_certification_id = $wpdb->insert_id;

    // Trigger action for certificate generation (for renewal status updates)
    try {
        $log_error("Triggering certificate_generated action", ['final_certification_id' => $final_certification_id, 'certificate_number' => $certificate_number]);
        do_action('certificate_generated', $final_certification_id, $certificate_number, $user_id, $certificate_data, $exam_entry_id);
        $log_error("certificate_generated action triggered successfully");
    } catch (Exception $e) {
        $log_error("Exception while triggering certificate_generated action", ['error' => $e->getMessage()]);
    }

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
    
    $log_error("Certificate metadata prepared", ['certificate_data' => $certificate_data]);
    gform_update_meta($marks_entry_id, '_certification_meta_' . sanitize_title($method), $certificate_data);

    // Send notification with fallback
    $center_name = $entry['833'] ?? 'N/A';
    $center_post = get_page_by_title($center_name, OBJECT, 'exam_center');
    if ($center_post) {
        gform_update_meta($exam_entry_id, '_linked_exam_center', $center_post->ID);
        if (function_exists('send_exam_certificate')) {
            try {
                $log_error("Sending exam certificate notification");
                send_exam_certificate($exam_entry_id, $marks_entry_id, $center_post, $certificate_data, $method, '');
                $log_error("Exam certificate notification sent successfully");
            } catch (Exception $e) {
                $log_error("Exception while sending exam certificate", ['error' => $e->getMessage()]);
            }
        } else {
            $log_error("send_exam_certificate function not found");
        }
    } else {
        $current_time = current_time('mysql', true);
        error_log("Exam center not found at $current_time for name: $center_name, user_id=" . get_current_user_id());
    }

    $log_error("PDF generation completed successfully", ['certificate_number' => $certificate_number, 'file_url' => $file_url]);
    ob_end_clean();
    return true; // Success
}

function send_exam_certificate($entry_id, $marks_entry_id, $center_post, $certificate_data, $method, $result = '') {
    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry)) return;

    $log = [];

    $user_id = $entry['created_by'];
    $user_data = get_userdata($user_id);
    $candidate_name = $user_data ? $user_data->display_name : 'N/A';
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

