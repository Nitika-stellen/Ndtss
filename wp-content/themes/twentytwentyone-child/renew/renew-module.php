<?php
if (!defined('ABSPATH')) { exit; }

require_once get_stylesheet_directory() . '/renew/renew-logger.php';

// Include email template functionality
require_once get_stylesheet_directory() . '/renew/renew-email-template.php';

// Include admin functionality
if (is_admin()) {
    require_once get_stylesheet_directory() . '/renew/renew-admin.php';
}

// Include test AJAX handler for debugging
require_once get_stylesheet_directory() . '/renew/test-ajax.php';

/**
 * Add renewal status columns to final certifications table
 */
function add_renewal_status_columns() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'sgndt_final_certifications';
    
    // Check if columns already exist
    $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'renewal_%'");
    
    if (empty($columns)) {
        $sql = "ALTER TABLE {$table_name} 
                ADD COLUMN renewal_status VARCHAR(50) DEFAULT NULL AFTER status,
                ADD COLUMN renewal_method VARCHAR(20) DEFAULT NULL AFTER renewal_status,
                ADD COLUMN renewal_submitted_date DATETIME DEFAULT NULL AFTER renewal_method,
                ADD COLUMN renewal_approved_date DATETIME DEFAULT NULL AFTER renewal_submitted_date,
                ADD COLUMN renewal_rejected_date DATETIME DEFAULT NULL AFTER renewal_approved_date,
                ADD COLUMN renewal_rejection_reason TEXT DEFAULT NULL AFTER renewal_rejected_date,
                ADD COLUMN renewal_approved_by INT DEFAULT NULL AFTER renewal_rejection_reason,
                ADD COLUMN renewal_rejected_by INT DEFAULT NULL AFTER renewal_approved_by,
                ADD COLUMN renewal_submission_id INT DEFAULT NULL AFTER renewal_rejected_by";
        
        $result = $wpdb->query($sql);
        
        if ($result !== false) {
            renew_log_info('Renewal status columns added to final certifications table');
        } else {
            renew_log_error('Failed to add renewal status columns', array('error' => $wpdb->last_error));
        }
    }
}

function renew_module_bootstrap() {
    add_shortcode('renew_certification', 'renew_shortcode_handler');
    // New shortcode for recertification flow (uses same router/templates with context)
    add_shortcode('recertification', 'recertification_shortcode_handler');
    add_action('init', 'renew_register_cpt');
    add_action('wp_enqueue_scripts', 'renew_enqueue_assets');
    add_action('wp_ajax_submit_cpd_form', 'renew_handle_cpd_submit');
    add_action('wp_ajax_nopriv_submit_cpd_form', 'renew_handle_cpd_submit');
    
    // Run database migration
    add_action('init', 'add_renewal_status_columns');
    
    // Schedule reminder emails
    add_action('wp', 'renew_schedule_reminders');
    add_action('renew_send_reminders', 'renew_send_reminder_emails');
    
    // Add hooks for Form 36 renewal method persistence
    add_filter('gform_confirmation_39', 'renew_form_36_confirmation_redirect', 10, 4);
    add_filter('gform_validation_39', 'renew_form_36_validation_redirect', 10, 1);
}
add_action('after_setup_theme', 'renew_module_bootstrap');

function renew_register_cpt() {
    register_post_type('cpd_submission', array(
        'label' => 'Renew/Recertification Submissions',
        'public' => false,
        'show_ui' => false,
        'show_in_menu' => false,
        'capability_type' => 'post',
        'supports' => array('title'),
    ));
}

function renew_enqueue_assets() {
    if (!is_page()) { return; }
    global $post; if (!$post) { return; }
    if (has_shortcode($post->post_content, 'renew_certification')) {
       wp_enqueue_style(
            'renew-frontend',
            get_stylesheet_directory_uri() . '/renew/css/renew-frontend.css?v=' . wp_rand(),
            array(),
            null
        );

        wp_enqueue_script(
            'renew-frontend',
            get_stylesheet_directory_uri() . '/renew/js/renew-frontend.js?v=' . wp_rand(),
            array('jquery'),
            null,
            true
        );

        
        // Enqueue Gravity Forms assets if available
        if (class_exists('GFForms')) {
            // Ensure Gravity Forms scripts are available for AJAX loading
            wp_enqueue_script('gform_gravityforms');
            wp_enqueue_script('gform_conditional_logic');
            wp_enqueue_script('gform_placeholder');
            wp_enqueue_style('gforms_css');
            wp_enqueue_style('gforms_ready_class_css');
            wp_enqueue_style('gforms_browsers_css');
        }
        
        wp_localize_script('renew-frontend', 'RenewAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('renew_nonce')
        ));
    }
}

function renew_shortcode_handler($atts) {
    ob_start();
    // Context variable used inside included templates
    $is_recertification = renew_get_context() === 'recertification';
    include get_stylesheet_directory() . '/renew/templates/renew-router.php';
    return ob_get_clean();
}

// Recertification shortcode handler - renders same UI with recertification context
function recertification_shortcode_handler($atts) {
    ob_start();
    // Context variable used inside included templates
    $is_recertification = true;
    include get_stylesheet_directory() . '/renew/templates/renew-router.php';
    return ob_get_clean();
}

/**
 * Get renewal/recertification context robustly
 */
function renew_get_context() {
    // Check URL parameter first
    if (isset($_GET['type']) && $_GET['type'] === 'recertification') {
        return 'recertification';
    }
    
    // Check if recertification shortcode is being used
    global $post;
    if ($post && has_shortcode($post->post_content, 'recertification')) {
        return 'recertification';
    }
    
    // Default to renewal
    return 'renewal';
}

/**
 * Generate certificate number with enhanced logic
 */
function renew_generate_certificate_number($original_cert_number, $method) {
    $suffix = (strtoupper($method) === 'RECERT') ? '-2' : '-1';
    
    // Check if certificate number already has a suffix
    if (strpos($original_cert_number, '-') !== false) {
        // Extract base number and increment suffix
        $parts = explode('-', $original_cert_number);
        $base_number = $parts[0];
        $current_suffix = isset($parts[1]) ? intval($parts[1]) : 0;
        $new_suffix = $current_suffix + 1;
        
        return $base_number . '-' . $new_suffix;
    }
    
    return $original_cert_number . $suffix;
}

function renew_handle_cpd_submit() {
    try {
        // Log the request for debugging
        renew_log_info('CPD submit request received', array(
            'user_logged_in' => is_user_logged_in(),
            'post_data_keys' => array_keys($_POST),
            'files_data' => array_keys($_FILES),
            'cert_id_value' => isset($_POST['cert_id']) ? $_POST['cert_id'] : 'NOT_SET',
            'cert_number_value' => isset($_POST['cert_number']) ? $_POST['cert_number'] : 'NOT_SET'
        ));
        
        // Check if user is logged in first
        if (!is_user_logged_in()) {
            renew_log_warn('CPD submit failed - user not logged in');
            wp_send_json_error(array('message' => 'Login required'));
        }

        // Verify nonce
        if (!check_ajax_referer('renew_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        $user_id = get_current_user_id();

        // Sanitize input data
        $name   = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : '';
        $level  = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : '';
        $sector = isset($_POST['sector']) ? sanitize_text_field($_POST['sector']) : '';
        $cert_number = isset($_POST['cert_number']) ? sanitize_text_field($_POST['cert_number']) : '';
        $cert_id = isset($_POST['cert_id']) ? sanitize_text_field($_POST['cert_id']) : '';
        
        // Handle both old format (years[1][A1]) and new format (cpd_points[A1][1])
        $years = array();
        if (isset($_POST['years']) && is_array($_POST['years'])) {
            // Old format: years[1][A1] = value
            $years = $_POST['years'];
        } elseif (isset($_POST['cpd_points']) && is_array($_POST['cpd_points'])) {
            // New format: cpd_points[A1][1] = value
            // Convert to old format for backward compatibility
            $cpd_points = $_POST['cpd_points'];
            foreach ($cpd_points as $category => $year_data) {
                if (is_array($year_data)) {
                    foreach ($year_data as $year_num => $value) {
                        if (!isset($years[$year_num])) {
                            $years[$year_num] = array();
                        }
                        $years[$year_num][$category] = $value;
                    }
                }
            }
        }
        
        renew_log_info('CPD data format check', array(
            'has_years' => isset($_POST['years']),
            'has_cpd_points' => isset($_POST['cpd_points']),
            'years_converted' => $years
        ));

        // CPD Categories with their maximum points for validation
        $cpd_categories = array(
            'A1' => array('max_total' => 95, 'max_per_year' => 25),   // Performing NDT Activity
            'A2' => array('max_total' => 15, 'max_per_year' => 5),    // Theoretical Training
            'A3' => array('max_total' => 25, 'max_per_year' => 10),   // Practical Training
            'A4' => array('max_total' => 75, 'max_per_year' => 15),   // Delivery of Training
            'A5' => array('max_total' => 60, 'max_per_year' => 15),   // Research Activities
            '6' => array('max_total' => 10, 'max_per_year' => 2),     // Technical Seminar/Paper
            '7' => array('max_total' => 15, 'max_per_year' => 3),     // Presenting Technical Seminar
            '8' => array('max_total' => 5, 'max_per_year' => 2),      // Society Membership
            '9' => array('max_total' => 40, 'max_per_year' => 10),    // Technical Oversight
            '10' => array('max_total' => 20, 'max_per_year' => 4),    // Committee Participation
            '11' => array('max_total' => 40, 'max_per_year' => 10)    // Certification Body Role
        );

        $errors = array();
        
        // Basic validation
        if (empty($name)) { $errors['name'] = 'Name is required'; }
        // Allow both CPD (renewal) and RECERT (recertification)
        if (empty($method) || !in_array(strtoupper($method), array('CPD', 'RECERT'), true)) { $errors['method'] = 'Invalid method'; }
        if (empty($level)) { $errors['level'] = 'Level is required'; }
        if (empty($sector)) { $errors['sector'] = 'Sector is required'; }
        if (empty($years)) { $errors['years'] = 'CPD points are required'; }
        
        // Validate file uploads
        if (empty($_FILES['cpd_files']['name'][0])) {
            $errors['cpd_files'] = 'CPD Proof Documents are required';
        }
        if (empty($_FILES['previous_certificates']['name'][0])) {
            $errors['previous_certificates'] = 'Previous Certificates are required';
        }
        
        // Validate CPD points
        $total_cpd_points = 0;
        $has_any_points = false;
        $category_totals = array();
        
        // Initialize category totals
        foreach ($cpd_categories as $category => $limits) {
            $category_totals[$category] = 0;
        }
        
        if (!empty($years)) {
            foreach ($years as $year_num => $year_data) {
                if (is_array($year_data)) {
                    foreach ($cpd_categories as $category => $limits) {
                        if (isset($year_data[$category])) {
                            $value = floatval($year_data[$category]);
                            if ($value < 0) {
                                $errors['years'] = "Year $year_num - Category $category: Points cannot be negative";
                                break 2;
                            }
                            // Check per-year limit
                            if ($value > $limits['max_per_year']) {
                                $errors['years'] = "Year $year_num - Category $category: Maximum {$limits['max_per_year']} points allowed per year";
                                break 2;
                            }
                            if ($value > 0) {
                                $has_any_points = true;
                                $total_cpd_points += $value;
                                $category_totals[$category] += $value;
                            }
                        }
                    }
                }
            }
            
            // Check category total limits
            foreach ($category_totals as $category => $total) {
                $limits = $cpd_categories[$category];
                if ($total > $limits['max_total']) {
                    $errors['cpd_category_total'] = "Category $category: Total exceeds maximum {$limits['max_total']} points (Current: $total)";
                    break;
                }
            }
        }
        
        // Check minimum total points
        if (!$has_any_points) {
            $errors['cpd_total'] = 'At least some CPD points must be entered';
        } elseif ($total_cpd_points < 150) {
            $errors['cpd_total'] = "Minimum 150 total CPD points required over 5 years (Current: $total_cpd_points)";
        }
        
        // Level-based validation: Different levels require different minimum Part A points
        if (!empty($level)) {
            $level_normalized = strtoupper(trim($level));
            
            // Calculate total Part A points (A1 + A2 + A3 + A4 + A5)
            $part_a_categories = array('A1', 'A2', 'A3', 'A4', 'A5');
            $part_a_total = 0;
            
            foreach ($part_a_categories as $cat) {
                if (isset($category_totals[$cat])) {
                    $part_a_total += $category_totals[$cat];
                }
            }
            
            // Determine required minimum based on level
            $required_part_a = 0;
            $detected_level = '';
            
            // Check for Level 1 (minimum 75 points)
            if (preg_match('/^(LEVEL\s*1|L\s*1|1)$/i', $level_normalized) || $level_normalized === 'LEVEL 1' || $level_normalized === 'L1') {
                $required_part_a = 75;
                $detected_level = 'Level 1';
            }
            // Check for Level 2 (minimum 50 points)
            elseif (preg_match('/^(LEVEL\s*2|L\s*2|2)$/i', $level_normalized) || $level_normalized === 'LEVEL 2' || $level_normalized === 'L2') {
                $required_part_a = 50;
                $detected_level = 'Level 2';
            }
            // Check for Level 3 (minimum 50 points)
            elseif (preg_match('/^(LEVEL\s*3|L\s*3|3)$/i', $level_normalized) || $level_normalized === 'LEVEL 3' || $level_normalized === 'L3') {
                $required_part_a = 50;
                $detected_level = 'Level 3';
            }
            
            // Validate if a level requirement was detected
            if ($required_part_a > 0) {
                if ($part_a_total < $required_part_a) {
                    $errors['level_part_a'] = "$detected_level certification requires minimum $required_part_a points from Part A categories (A1-A5 combined). Current Part A total: " . number_format($part_a_total, 1) . " points";
                }
                
                renew_log_info("$detected_level Part A validation", array(
                    'level' => $level,
                    'detected_level' => $detected_level,
                    'required_part_a' => $required_part_a,
                    'part_a_total' => $part_a_total,
                    'part_a_breakdown' => array(
                        'A1' => isset($category_totals['A1']) ? $category_totals['A1'] : 0,
                        'A2' => isset($category_totals['A2']) ? $category_totals['A2'] : 0,
                        'A3' => isset($category_totals['A3']) ? $category_totals['A3'] : 0,
                        'A4' => isset($category_totals['A4']) ? $category_totals['A4'] : 0,
                        'A5' => isset($category_totals['A5']) ? $category_totals['A5'] : 0
                    ),
                    'validation_passed' => $part_a_total >= $required_part_a
                ));
            }
        }

        if ($errors) {
            renew_log_warn('Validation failed on CPD submit', array('user' => $user_id, 'errors' => $errors));
            wp_send_json_error(array('message' => 'Validation failed', 'errors' => $errors));
        }

        $post_id = wp_insert_post(array(
            'post_type' => 'cpd_submission',
            'post_title' => $name . ' - ' . $level . ' - ' . current_time('Y-m-d H:i'),
            'post_status' => 'publish'
        ));
          

        if (is_wp_error($post_id) || !$post_id) {
            renew_log_error('Failed to create renewal/recertification submission', array('user' => $user_id, 'error' => is_wp_error($post_id) ? $post_id->get_error_message() : 'Unknown error'));
            wp_send_json_error(array('message' => 'Could not save submission'));
        }

        // Save submission data
        update_post_meta($post_id, '_user_id', $user_id);
        update_post_meta($post_id, '_name', $name);
        update_post_meta($post_id, '_method', $method);
        update_post_meta($post_id, '_level', $level);
        update_post_meta($post_id, '_sector', $sector);
        update_post_meta($post_id, '_cert_number', $cert_number);
        update_post_meta($post_id, '_cert_id', $cert_id);
        update_post_meta($post_id, '_years', $years);
        update_post_meta($post_id, '_category_totals', $category_totals);
        update_post_meta($post_id, '_total_cpd_points', $total_cpd_points);
        update_post_meta($post_id, '_status', 'pending');
        update_post_meta($post_id, '_submission_date', current_time('Y-m-d H:i:s'));

        // Handle file uploads
        $uploaded_files = array();
        $file_types = array('cpd_files', 'previous_certificates', 'support_docs');
        
        foreach ($file_types as $key) {
            if (!empty($_FILES[$key]['name'][0])) {
                $files = renew_handle_file_uploads($_FILES[$key]);
                if (is_wp_error($files)) {
                    // Delete the post if file upload fails
                    wp_delete_post($post_id, true);
                    renew_log_error('File upload failed', array('key' => $key, 'error' => $files->get_error_message()));
                    wp_send_json_error(array('message' => 'Upload failed: ' . $files->get_error_message()));
                }
                $uploaded_files[$key] = $files;
            }
        }
        
        update_post_meta($post_id, '_uploads', $uploaded_files);

        // Send notification emails using new email template system
        $user = get_userdata($user_id);
        $submission_data = array(
            'name' => $name,
            'method' => $method,
            'level' => $level,
            'sector' => $sector,
            'total_cpd_points' => $total_cpd_points,
            'submission_id' => $post_id,
            'user_email' => $user ? $user->user_email : ''
        );
        
        renew_send_notification_emails($submission_data);

        // Update certificate status and create renewed certificate entry
        if (!empty($cert_id)) {
            renew_log_info('About to call renew_update_certificate_status', array(
                'cert_id' => $cert_id,
                'cert_number' => $cert_number,
                'user_id' => $user_id
            ));
            $reviewing_cert_id = renew_update_certificate_status($user_id, $method, $cert_id, $cert_number, $level, $sector, $total_cpd_points, $post_id);

            if ($reviewing_cert_id) {
                renew_log_info('Certificate status updated successfully, reviewing_cert_id stored', array(
                    'reviewing_cert_id' => $reviewing_cert_id,
                    'submission_id' => $post_id
                ));
            } else {
                renew_log_warn('Failed to update certificate status', array(
                    'cert_id' => $cert_id,
                    'cert_number' => $cert_number
                ));
            }
        } else {
            renew_log_warn('cert_id is empty, skipping certificate status update', array(
                'cert_id' => $cert_id,
                'cert_number' => $cert_number
            ));
        }

        renew_log_info('Renewal/Recertification submission saved successfully', array(
            'post_id' => $post_id, 
            'user' => $user_id, 
            'total_points' => $total_cpd_points,
            'cert_number' => $cert_number,
            'files_uploaded' => array_keys($uploaded_files)
        ));
        
        wp_send_json_success(array(
            'message' => 'Your renewal/recertification application has been submitted successfully! You will receive an email confirmation shortly.',
            'submission_id' => $post_id,
            'total_points' => $total_cpd_points,
            'hide_form' => true,
            'success_data' => array(
                'submission_id' => $post_id,
                'name' => $name,
                'level' => $level,
                'sector' => $sector,
                'total_points' => $total_cpd_points,
                'submission_date' => current_time('F j, Y g:i A')
            )
        ));
        
    } catch (Exception $e) {
        renew_log_error('Critical error in CPD submit', array(
            'user' => get_current_user_id(), 
            'error' => $e->getMessage(), 
            'trace' => $e->getTraceAsString()
        ));
        wp_send_json_error(array('message' => 'An unexpected error occurred. Please try again.'));
    }
}

function renew_handle_file_uploads($file_field) {
    try {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $results = array();
        $overrides = array(
            'test_form' => false,
            'upload_error_strings' => array(
                false,
                'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
                'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
                'The uploaded file was only partially uploaded.',
                'No file was uploaded.',
                'Missing a temporary folder.',
                'Failed to write file to disk.',
                'File upload stopped by extension.'
            )
        );
        
        // Allowed file types and max size
        $allowed_types = array(
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/jpg',
            'image/png'
        );
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if (is_array($file_field['name'])) {
            // Multiple files
            $count = count($file_field['name']);
            for ($i = 0; $i < $count; $i++) {
                // Skip empty files
                if (empty($file_field['name'][$i])) continue;
                
                // Check for upload errors
                if ($file_field['error'][$i] !== UPLOAD_ERR_OK) {
                    $error_msg = isset($overrides['upload_error_strings'][$file_field['error'][$i]]) 
                        ? $overrides['upload_error_strings'][$file_field['error'][$i]] 
                        : 'Unknown upload error';
                    return new WP_Error('upload_error', 'File "' . $file_field['name'][$i] . '": ' . $error_msg);
                }
                
                // Check file size
                if ($file_field['size'][$i] > $max_size) {
                    return new WP_Error('file_too_large', 'File "' . $file_field['name'][$i] . '" is too large (max 10MB)');
                }
                
                // Check file type
                if (!in_array($file_field['type'][$i], $allowed_types)) {
                    return new WP_Error('invalid_file_type', 'File "' . $file_field['name'][$i] . '" has invalid file type');
                }
                
                $file = array(
                    'name' => $file_field['name'][$i],
                    'type' => $file_field['type'][$i],
                    'tmp_name' => $file_field['tmp_name'][$i],
                    'error' => $file_field['error'][$i],
                    'size' => $file_field['size'][$i],
                );
                
                $movefile = wp_handle_upload($file, $overrides);
                if (isset($movefile['error'])) {
                    return new WP_Error('upload_error', $movefile['error']);
                }
                
                // Add additional file info
                $movefile['original_name'] = $file_field['name'][$i];
                $movefile['file_size'] = $file_field['size'][$i];
                $movefile['upload_date'] = current_time('Y-m-d H:i:s');
                
                $results[] = $movefile;
            }
        } else {
            // Single file
            if (empty($file_field['name'])) {
                return array(); // No file uploaded
            }
            
            // Check for upload errors
            if ($file_field['error'] !== UPLOAD_ERR_OK) {
                $error_msg = isset($overrides['upload_error_strings'][$file_field['error']]) 
                    ? $overrides['upload_error_strings'][$file_field['error']] 
                    : 'Unknown upload error';
                return new WP_Error('upload_error', 'File "' . $file_field['name'] . '": ' . $error_msg);
            }
            
            // Check file size
            if ($file_field['size'] > $max_size) {
                return new WP_Error('file_too_large', 'File "' . $file_field['name'] . '" is too large (max 10MB)');
            }
            
            // Check file type
            if (!in_array($file_field['type'], $allowed_types)) {
                return new WP_Error('invalid_file_type', 'File "' . $file_field['name'] . '" has invalid file type');
            }
            
            $movefile = wp_handle_upload($file_field, $overrides);
            if (isset($movefile['error'])) {
                return new WP_Error('upload_error', $movefile['error']);
            }
            
            // Add additional file info
            $movefile['original_name'] = $file_field['name'];
            $movefile['file_size'] = $file_field['size'];
            $movefile['upload_date'] = current_time('Y-m-d H:i:s');
            
            $results[] = $movefile;
        }
        
        return $results;
        
    } catch (Exception $e) {
        return new WP_Error('upload_error', 'File upload exception: ' . $e->getMessage());
    }
}

/**
 * Update certificate status and create renewed certificate entry in sgndt_final_certifications
 */
function renew_update_certificate_status($user_id, $method, $cert_id, $cert_number, $level, $sector, $total_cpd_points, $submission_id) {
    global $wpdb;
    
    // Add detailed debugging
    renew_log_info('renew_update_certificate_status called', array(
        'user_id' => $user_id,
        'cert_id' => $cert_id,
        'cert_number' => $cert_number,
        'level' => $level,
        'sector' => $sector,
        'submission_id' => $submission_id
    ));
    
    try {
        // Find the original certificate in sgndt_final_certifications using cert_id
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications 
             WHERE user_id = %d AND final_certification_id = %d AND status = 'issued'
             ORDER BY issue_date DESC LIMIT 1",
            $user_id, $cert_id
        );
        
        renew_log_info('Database query for original certificate', array(
            'query' => $query,
            'user_id' => $user_id,
            'cert_id' => $cert_id
        ));
        
        $original_cert = $wpdb->get_row($query);
        
        if (!$original_cert) {
            // Try to find certificate by certificate_number as fallback
            $fallback_query = $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications 
                 WHERE user_id = %d AND certificate_number = %s AND status = 'issued'
                 ORDER BY issue_date DESC LIMIT 1",
                $user_id, $cert_number
            );
            
            renew_log_info('Trying fallback query by certificate number', array(
                'query' => $fallback_query
            ));
            
            $original_cert = $wpdb->get_row($fallback_query);
        }
        
        if (!$original_cert) {
            renew_log_warn('Original certificate not found for renewal update', array(
                'user_id' => $user_id,
                'cert_id' => $cert_id,
                'cert_number' => $cert_number,
                'level' => $level,
                'sector' => $sector,
                'wpdb_last_error' => $wpdb->last_error
            ));
            return false;
        }
        
        renew_log_info('Original certificate found', array(
            'original_cert_id' => $original_cert->final_certification_id,
            'original_cert_number' => $original_cert->certificate_number,
            'original_status' => $original_cert->status
        ));
        
        // Generate renewed/recertified certificate number with enhanced logic
        $renewed_cert_number = renew_generate_certificate_number($cert_number, $method);
        
        // Calculate expiry date (5 years from current date)
        $current_date = current_time('Y-m-d');
        $expiry_date = date('Y-m-d', strtotime($current_date . ' +5 years'));
        
        // Check for existing renewed certificate
        $existing_renewed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sgndt_final_certifications
             WHERE certificate_number = %s AND user_id = %d",
            $renewed_cert_number, $user_id
        ));

        if ($existing_renewed > 0) {
            // Update existing certificate status instead of creating new one
            $update_result = $wpdb->update(
                $wpdb->prefix . 'sgndt_final_certifications',
                array('status' => 'reviewing'),
                array('certificate_number' => $renewed_cert_number, 'user_id' => $user_id),
                array('%s'),
                array('%s', '%d')
            );

            if ($update_result !== false) {
                renew_log_info('Updated existing renewed certificate status to reviewing', array(
                    'renewed_cert_number' => $renewed_cert_number,
                    'user_id' => $user_id,
                    'level' => $level,
                    'sector' => $sector,
                    'rows_updated' => $update_result
                ));
            } else {
                renew_log_warn('Failed to update existing renewed certificate status', array(
                    'renewed_cert_number' => $renewed_cert_number,
                    'user_id' => $user_id,
                    'wpdb_error' => $wpdb->last_error
                ));
            }
        } else {
            // Update the original certificate with renewal status instead of creating new row
            $renewal_method = strtoupper($method) === 'RECERT' ? 'RECERT' : 'CPD';
            
            $update_result = $wpdb->update(
                $wpdb->prefix . 'sgndt_final_certifications',
                array(
                    'renewal_status' => 'submitted',
                    'renewal_method' => $renewal_method,
                    'renewal_submitted_date' => current_time('mysql'),
                    'renewal_submission_id' => $submission_id
                ),
                array('final_certification_id' => $original_cert->final_certification_id),
                array('%s', '%s', '%s', '%d'),
                array('%d')
            );

            if ($update_result !== false) {
                renew_log_info('Updated original certificate with renewal status', array(
                    'cert_id' => $original_cert->final_certification_id,
                    'cert_number' => $original_cert->certificate_number,
                    'renewal_method' => $renewal_method,
                    'submission_id' => $submission_id
                ));
                
                // Store the original certificate ID in the submission post meta for admin access
                update_post_meta($submission_id, '_original_cert_id', $original_cert->final_certification_id);
                renew_log_info('Stored original certificate ID in submission', array(
                    'submission_id' => $submission_id,
                    'original_cert_id' => $original_cert->final_certification_id
                ));
            } else {
                renew_log_error('Failed to update original certificate with renewal status', array(
                    'cert_id' => $original_cert->final_certification_id,
                    'cert_number' => $original_cert->certificate_number,
                    'wpdb_error' => $wpdb->last_error
                ));
                return false;
            }
        }

        // Update user profile renewal status - using NEW consolidated status system
        $cert_status_key = 'cert_status_' . $cert_number;

        // Update status using the new professional system - ALWAYS set to 'submitted' initially
        $status_data = [
            'submission_method' => 'cpd_form',
            'submission_id' => $submission_id,
            'total_cpd_points' => $total_cpd_points,
            'renewed_cert_number' => $renewed_cert_number,
            'original_cert_id' => $cert_id,
            'reviewing_cert_id' => isset($new_cert_id) ? $new_cert_id : null,
            'level' => $level,
            'sector' => $sector
        ];

        $status_updated = update_certificate_status($user_id, $cert_number, 'submitted', $status_data);

        if ($status_updated) {
            renew_log_info('Certificate status updated using new system', array(
                'cert_number' => $cert_number,
                'new_status' => 'submitted',
                'user_id' => $user_id
            ));
        } else {
            renew_log_warn('Failed to update certificate status using new system', array(
                'cert_number' => $cert_number,
                'user_id' => $user_id
            ));
        }

        renew_log_info('Certificate renewal status updated successfully', array(
            'user_id' => $user_id,
            'cert_id' => $cert_id,
            'original_cert_number' => $cert_number,
            'renewed_cert_number' => $renewed_cert_number,
            'reviewing_cert_id' => isset($new_cert_id) ? $new_cert_id : null,
            'original_cert_status' => 'unchanged',
            'new_cert_status' => 'reviewing',
            'submission_id' => $submission_id,
            'original_cert_copied' => 'yes - located by cert_id, only cert number, dates, and status changed'
        ));

        return isset($new_cert_id) ? $new_cert_id : true; // Return the insert_id for admin use or true if updated
        
    } catch (Exception $e) {
        renew_log_error('Exception in certificate status update', array(
            'user_id' => $user_id,
            'cert_number' => $cert_number,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ));
        return false;
    }
}

/**
 * Check if a renewal has already been submitted for a certificate
 */
function renew_check_existing_renewal($user_id, $cert_number) {
    global $wpdb;
    
    // Check if there's already a renewed certificate with -1 suffix
    $renewed_cert_number = $cert_number . '-1';
    $existing_cert = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications 
         WHERE user_id = %d AND certificate_number = %s",
        $user_id, $renewed_cert_number
    ));
    
    if ($existing_cert) {
        return array(
            'status' => $existing_cert->status,
            'certificate_number' => $existing_cert->certificate_number,
            'issue_date' => $existing_cert->issue_date,
            'certificate_link' => $existing_cert->certificate_link
        );
    }
    
    // Also check NEW consolidated status system
    $cert_status_key = 'cert_status_' . $cert_number;
    $cert_status = get_user_meta($user_id, $cert_status_key, true);

    if (!empty($cert_status)) {
        $status_date = get_user_meta($user_id, $cert_status_key . '_date', true);
        $cert_issued_number = get_user_meta($user_id, $cert_status_key . '_cert_number', true);

        return array(
            'status' => $cert_status,
            'submission_date' => $status_date,
            'certificate_number' => $cert_issued_number ?: $cert_number . '-1',
            'status_source' => 'new_system'
        );
    }
    
    return false;
}

/**
 * Update certificate status in renewal system (called by admin when approving)
 */
function renew_admin_update_status($cert_number, $user_id, $new_status, $additional_data = []) {
    // Use the new professional status management system
    $status_data = array_merge([
        'updated_by' => 'admin',
        'update_date' => current_time('mysql'),
        'admin_action' => true
    ], $additional_data);

    $success = update_certificate_status($user_id, $cert_number, $new_status, $status_data);

    if ($success) {
        renew_log_info('Admin updated certificate status using new system', array(
            'cert_number' => $cert_number,
            'user_id' => $user_id,
            'new_status' => $new_status,
            'additional_data' => $additional_data
        ));
    } else {
        renew_log_error('Admin failed to update certificate status', array(
            'cert_number' => $cert_number,
            'user_id' => $user_id,
            'new_status' => $new_status
        ));
    }

    return $success;
}

/**
 * Enhanced function to approve renewal/recertification applications
 */
function renew_approve_application($submission_id, $cert_number, $user_id, $approved_by = 'admin') {
    // Update status using new system
    $status_data = [
        'approved_by' => $approved_by,
        'approval_date' => current_time('mysql'),
        'submission_id' => $submission_id
    ];

    $success = update_certificate_status($user_id, $cert_number, 'approved', $status_data);

    if ($success) {
        // Update the database entry status as well
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'sgndt_final_certifications',
            array('status' => 'approved'),
            array('certificate_number' => $cert_number . '-1', 'user_id' => $user_id),
            array('%s'),
            array('%s', '%d')
        );

        renew_log_info('Renewal application approved using new system', array(
            'submission_id' => $submission_id,
            'cert_number' => $cert_number,
            'user_id' => $user_id,
            'approved_by' => $approved_by
        ));
    }

    return $success;
}

/**
 * Enhanced function to issue renewed certificates
 */
function renew_issue_certificate($cert_number, $user_id, $new_cert_number, $issued_by = 'admin', $final_certification_id = null) {
    global $wpdb;
    
    // Debug logging
    renew_log_info('Attempting to issue certificate', array(
        'original_cert_number' => $cert_number,
        'new_cert_number' => $new_cert_number,
        'user_id' => $user_id,
        'final_certification_id' => $final_certification_id,
        'issued_by' => $issued_by
    ));

    // Update status using new system
    $status_data = [
        'cert_number' => $new_cert_number,
        'issue_date' => current_time('mysql'),
        'issued_by' => $issued_by
    ];

    $success = update_certificate_status($user_id, $cert_number, 'certificate_issued', $status_data);

    if ($success) {
        renew_log_info('Certificate status updated to certificate_issued successfully', array(
            'cert_number' => $cert_number,
            'user_id' => $user_id
        ));

        if ($final_certification_id) {
            $original_cert = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications
                 WHERE final_certification_id = %d",
                $final_certification_id
            ));

            if ($original_cert) {
                // Update the certificate status to issued
                $update_result = $wpdb->update(
                    $wpdb->prefix . 'sgndt_final_certifications',
                    array(
                        'status' => 'issued',
                        'certificate_number' => $new_cert_number,
                        'issue_date' => current_time('mysql')
                    ),
                    array('final_certification_id' => $final_certification_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );

                if ($update_result !== false) {
                    renew_log_info('Certificate issued successfully', array(
                        'final_certification_id' => $final_certification_id,
                        'new_cert_number' => $new_cert_number,
                        'user_id' => $user_id
                    ));
                } else {
                    renew_log_error('Failed to update certificate to issued', array(
                        'final_certification_id' => $final_certification_id,
                        'wpdb_error' => $wpdb->last_error
                    ));
                    return false;
                }
            } else {
                renew_log_error('Original certificate not found for issuing', array(
                    'final_certification_id' => $final_certification_id,
                    'user_id' => $user_id
                ));
                return false;
            }
        }
    } else {
        renew_log_error('Failed to update certificate status to certificate_issued', array(
            'cert_number' => $cert_number,
            'user_id' => $user_id
        ));
        return false;
    }

    return $success;
}

/**
 * Send reminder emails for expiring certificates
 */
function renew_send_reminder_emails() {
    global $wpdb;

    // Define reminder intervals (days before expiry)
    $reminder_intervals = array(90, 60, 30, 14, 7);

    foreach ($reminder_intervals as $days) {
        $target_date = date('Y-m-d', strtotime("+{$days} days"));

        // Query certificates expiring on target date
        $certificates = $wpdb->get_results($wpdb->prepare(
            "SELECT fc.*, u.user_email, u.display_name
             FROM {$wpdb->prefix}sgndt_final_certifications fc
             JOIN {$wpdb->users} u ON fc.user_id = u.ID
             WHERE fc.status = 'issued'
             AND DATE(fc.expiry_date) = %s
             AND fc.certificate_number NOT REGEXP '-[0-9]+$'",
            $target_date
        ));

        foreach ($certificates as $cert) {
            // Check if user has already submitted renewal/recertification
            $existing_submission = renew_check_existing_renewal($cert->user_id, $cert->certificate_number);

            if (!$existing_submission) {
                // Determine if this should be renewal or recertification based on issue date
                $issue_date = new DateTime($cert->issue_date);
                $current_date = new DateTime();
                $years_since_issue = $current_date->diff($issue_date)->y;

                $type = ($years_since_issue >= 10) ? 'recertification' : 'renewal';
                $type_label = ($type === 'recertification') ? 'Recertification' : 'Renewal';

                // Send reminder email
                renew_send_expiry_reminder($cert, $days, $type, $type_label);
            }
        }
    }
}

/**
 * Send expiry reminder email to user
 */
function renew_send_expiry_reminder($certificate, $days_until_expiry, $type, $type_label) {
    $user_email = $certificate->user_email;
    $user_name = $certificate->display_name;
    $cert_number = $certificate->certificate_number;
    $level = $certificate->level;
    $sector = $certificate->sector;
    $expiry_date = date('F j, Y', strtotime($certificate->expiry_date));

    // Get appropriate email template
    $template_key = ($type === 'recertification') ? 'user_recertification_reminder' : 'user_renewal_reminder';

    $subject = get_option($template_key . '_subject', "{$type_label} Reminder - Certificate Expiring Soon");
    $heading = get_option($template_key . '_heading', "Certificate Expiry Reminder");
    $message = get_option($template_key . '_message',
        "Dear {user_name},\n\nYour {certification_level} certificate in {certification_sector} (Certificate #: {certificate_number}) will expire on {expiry_date}.\n\nYou have {days_remaining} days to submit your {$type} application.\n\nPlease visit your profile to submit your {$type} application.\n\nBest regards,\nNDTSS Team"
    );

    // Replace placeholders
    $placeholders = array(
        '{user_name}' => $user_name,
        '{certification_level}' => $level,
        '{certification_sector}' => $sector,
        '{certificate_number}' => $cert_number,
        '{expiry_date}' => $expiry_date,
        '{days_remaining}' => $days_until_expiry
    );

    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
    $message = str_replace(array_keys($placeholders), array_values($placeholders), $message);

    // Add heading if provided
    if (!empty($heading)) {
        $message = '<h2>' . esc_html($heading) . '</h2>' . nl2br($message);
    }

    // Send email
    $sent = send_formatted_email($user_email, $subject, $message);

    if ($sent) {
        renew_log_info('Expiry reminder sent', array(
            'user_id' => $certificate->user_id,
            'certificate_number' => $cert_number,
            'days_until_expiry' => $days_until_expiry,
            'type' => $type
        ));
    } else {
        renew_log_error('Failed to send expiry reminder', array(
            'user_id' => $certificate->user_id,
            'certificate_number' => $cert_number,
            'days_until_expiry' => $days_until_expiry,
            'type' => $type
        ));
    }
}

/**
 * Schedule reminder emails
 */
function renew_schedule_reminders() {
    if (!wp_next_scheduled('renew_send_reminders')) {
        wp_schedule_event(time(), 'daily', 'renew_send_reminders');
        renew_log_info('Scheduled daily reminder emails');
    }
}

/**
 * Remove duplicate reviewing entries for the same user, method, level, and sector
 */
function renew_remove_duplicate_reviewing_entries($user_id, $method, $level, $sector) {
    global $wpdb;

    $reviewing_entries = $wpdb->get_results($wpdb->prepare(
        "SELECT final_certification_id FROM {$wpdb->prefix}sgndt_final_certifications
         WHERE user_id = %d AND method = %s AND level = %s AND sector = %s AND status = 'reviewing'",
        $user_id, $method, $level, $sector
    ));

    if (!empty($reviewing_entries)) {
        foreach ($reviewing_entries as $entry) {
            $wpdb->delete(
                $wpdb->prefix . 'sgndt_final_certifications',
                array('final_certification_id' => $entry->final_certification_id),
                array('%d')
            );

            renew_log_info('Removed duplicate reviewing entry', array(
                'removed_cert_id' => $entry->final_certification_id,
                'user_id' => $user_id,
                'method' => $method,
                'level' => $level,
                'sector' => $sector
            ));
        }
        return count($reviewing_entries);
    }

    return 0;
}

/**
 * Handle Form 31 confirmation redirect to maintain renewal method selection
 * 
 * This function ensures that when Form 31 is submitted successfully,
 * the thank you message displays while maintaining the EXAM method selection.
 */
function renew_form_36_confirmation_redirect($confirmation, $form, $entry, $ajax) {
    // Get current URL parameters
    $cert_id = isset($_GET['cert_id']) ? intval($_GET['cert_id']) : 0;
    $cert_number = isset($_GET['cert_number']) ? sanitize_text_field($_GET['cert_number']) : '';
    $name = isset($_GET['name']) ? sanitize_text_field($_GET['name']) : '';
    $level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
    $sector = isset($_GET['sector']) ? sanitize_text_field($_GET['sector']) : '';
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    
    // Build URL parameters array
    $url_params = array(
        'cert_id' => $cert_id,
        'cert_number' => $cert_number,
        'name' => $name,
        'level' => $level,
        'sector' => $sector,
        'renewal_method' => 'EXAM' // Ensure EXAM method is maintained
    );
    
    // Add type parameter if it exists (for recertification)
    if (!empty($type)) {
        $url_params['type'] = $type;
    }
    
    // Remove empty parameters
    $url_params = array_filter($url_params, function($value) {
        return !empty($value);
    });
    
    // If this is not an AJAX submission, we can modify the confirmation
    if (!$ajax) {
        // Add JavaScript to ensure the exam form stays visible after confirmation
        $confirmation .= '<script type="text/javascript">
            jQuery(document).ready(function($) {
                // Ensure exam method stays selected
                setTimeout(function() {
                    $("#exam-form-section").show();
                    $("#cpd-form-section").hide();
                    
                    // Update method selection UI
                    $(".method-card").removeClass("active");
                    $(".method-button").removeClass("button-primary").text(function() {
                        return $(this).data("method") === "CPD" ? "Choose CPD" : "Choose Exam";
                    });
                    
                    var $examCard = $(".method-card[data-method=\"EXAM\"]");
                    var $examButton = $examCard.find(".renewal-method-btn");
                    $examCard.addClass("active");
                    $examButton.addClass("button-primary").text("Selected");
                    
                    // Store method in localStorage
                    localStorage.setItem("renewal_method_" + "' . esc_js($cert_id) . '", "EXAM");
                    
                    console.log("Form 36 confirmation: Exam method maintained");
                }, 100);
            });
        </script>';
    }
    
    return $confirmation;
}

/**
 * Handle Form 31 validation redirect to maintain renewal method selection
 * 
 * This function ensures that when Form 31 has validation errors,
 * the form reloads with the EXAM method still selected.
 */
function renew_form_36_validation_redirect($validation_result) {
    // Check if there are validation errors
    if (!$validation_result['is_valid']) {
        // Get current URL parameters
        $cert_id = isset($_GET['cert_id']) ? intval($_GET['cert_id']) : 0;
        
        // Add JavaScript to maintain exam method selection on validation errors
        add_action('wp_footer', function() use ($cert_id) {
            echo '<script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Ensure exam method stays selected on validation errors
                    setTimeout(function() {
                        $("#exam-form-section").show();
                        $("#cpd-form-section").hide();
                        
                        // Update method selection UI
                        $(".method-card").removeClass("active");
                        $(".method-button").removeClass("button-primary").text(function() {
                            return $(this).data("method") === "CPD" ? "Choose CPD" : "Choose Exam";
                        });
                        
                        var $examCard = $(".method-card[data-method=\"EXAM\"]");
                        var $examButton = $examCard.find(".renewal-method-btn");
                        $examCard.addClass("active");
                        $examButton.addClass("button-primary").text("Selected");
                        
                        // Store method in localStorage
                        localStorage.setItem("renewal_method_' . esc_js($cert_id) . '", "EXAM");
                        
                        // Update URL to maintain method
                        var params = new URLSearchParams(window.location.search);
                        params.set("renewal_method", "EXAM");
                        var newUrl = window.location.pathname + "?" + params.toString();
                        if (window.history && window.history.replaceState) {
                            window.history.replaceState({}, "", newUrl);
                        }
                        
                        console.log("Form 36 validation error: Exam method maintained");
                    }, 200);
                });
            </script>';
        });
    }
    
    return $validation_result;
}