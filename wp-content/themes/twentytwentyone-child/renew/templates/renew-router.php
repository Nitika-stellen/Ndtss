<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="renew-container">
    <?php
    // Get certification details from URL (passed from user profile)
    $cert_id = isset($_GET['cert_id']) ? intval($_GET['cert_id']) : 0;
    $cert_method = isset($_GET['cert_method']) ? sanitize_text_field($_GET['cert_method']) : (isset($_GET['method']) ? sanitize_text_field($_GET['method']) : '');
    $cert_number = isset($_GET['cert_number']) ? sanitize_text_field($_GET['cert_number']) : '';
    $name   = isset($_GET['name']) ? sanitize_text_field($_GET['name']) : '';
    $level  = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
    $sector = isset($_GET['sector']) ? sanitize_text_field($_GET['sector']) : '';
    
    // Get renewal method selection (CPD or EXAM) - this should be dynamic
    $renewal_method = isset($_GET['renewal_method']) ? sanitize_text_field($_GET['renewal_method']) : '';
    
    // Check if this is a recertification request
    $is_recertification = isset($_GET['type']) && $_GET['type'] === 'recertification';
    
    // Get certificate lifecycle information
    $user_id = get_current_user_id();
    
    // Validate that cert_id is provided
    if (empty($cert_id)) {
        echo '<div class="eligibility-error">';
        echo '<h3>Invalid Request</h3>';
        echo '<p>Certificate ID is required. Please return to your profile and try again.</p>';
        echo '<a href="' . home_url('/user-profile') . '" class="btn btn-primary">Back to Profile</a>';
        echo '</div>';
        return;
    }
    
    // Get certificate data from database using unique cert_id
    global $wpdb;
    $cert_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications 
         WHERE final_certification_id = %d AND user_id = %d",
        $cert_id,
        $user_id
    ), ARRAY_A);
    
    if (!$cert_data) {
        echo '<div class="eligibility-error">';
        echo '<h3>Certificate Not Found</h3>';
        echo '<p>The requested certificate could not be found or does not belong to you.</p>';
        echo '<a href="' . home_url('/user-profile') . '" class="btn btn-primary">Back to Profile</a>';
        echo '</div>';
        return;
    }
    
    // Override parameters with actual database values to prevent tampering
    $cert_number = $cert_data['certificate_number'];
    $cert_method = $cert_data['method'];
    $level = $cert_data['level'];
    $sector = $cert_data['sector'];
    
    $lifecycle = get_certificate_lifecycle($user_id, $cert_number);
    
    // Debug logging
    error_log("=== RENEWAL ROUTER DEBUG ===");
    error_log("Certificate ID: {$cert_id}");
    error_log("Certificate Number: {$cert_number}");
    error_log("Certificate Method: {$cert_method}");
    error_log("Lifecycle data: " . print_r($lifecycle, true));
    
    // Validate eligibility - Re-enabled with proper logic
    // if (!$lifecycle['eligibility']['renewal'] && !$lifecycle['eligibility']['recertification']) {
    //     error_log("ELIGIBILITY CHECK FAILED:");
    //     error_log("Renewal eligibility: " . ($lifecycle['eligibility']['renewal'] ? 'true' : 'false'));
    //     error_log("Recertification eligibility: " . ($lifecycle['eligibility']['recertification'] ? 'true' : 'false'));
        
    //     $renewal_window_start = date('d/m/Y', strtotime($cert_data['expiry_date'] . ' -6 months'));
    //     $grace_period_end = date('d/m/Y', strtotime($cert_data['expiry_date'] . ' +12 months'));
        
    //     echo '<div class="eligibility-error">';
    //     echo '<h3>Not Eligible</h3>';
    //     echo '<p>Certificate <strong>' . esc_html($cert_number) . '</strong> (' . esc_html($cert_method) . ') is not currently eligible for renewal or recertification.</p>';
    //     echo '<hr>';
    //     echo '<p><strong>Certificate Details:</strong></p>';
    //     echo '<ul>';
    //     echo '<li><strong>Certificate ID:</strong> ' . esc_html($cert_id) . '</li>';
    //     echo '<li><strong>Issue Date:</strong> ' . date('d/m/Y', strtotime($cert_data['issue_date'])) . '</li>';
    //     echo '<li><strong>Expiry Date:</strong> ' . date('d/m/Y', strtotime($cert_data['expiry_date'])) . '</li>';
    //     echo '</ul>';
    //     echo '<hr>';
    //     echo '<p><strong>Renewal Window:</strong></p>';
    //     echo '<ul>';
    //     echo '<li><strong>Window Opens:</strong> ' . $renewal_window_start . ' (6 months before expiry)</li>';
    //     echo '<li><strong>Window Closes:</strong> ' . $grace_period_end . ' (12 months after expiry)</li>';
    //     echo '<li><strong>Today\'s Date:</strong> ' . date('d/m/Y') . '</li>';
    //     echo '</ul>';
    //     echo '<hr>';
    //     echo '<p><strong>Debug Info:</strong></p>';
    //     echo '<ul>';
    //     echo '<li><strong>Next Action:</strong> ' . ($lifecycle['next_action'] ? $lifecycle['next_action'] : 'none') . '</li>';
    //     echo '<li><strong>Current Status:</strong> ' . $lifecycle['current_status'] . '</li>';
    //     echo '<li><strong>Renewal Eligible:</strong> ' . ($lifecycle['eligibility']['renewal'] ? 'Yes' : 'No') . '</li>';
    //     echo '<li><strong>Recertification Eligible:</strong> ' . ($lifecycle['eligibility']['recertification'] ? 'Yes' : 'No') . '</li>';
    //     echo '</ul>';
    //     echo '<a href="' . home_url('/user-profile#final-certificate-section') . '" class="btn btn-primary">Back to Profile</a>';
    //     echo '</div>';
    //     return;
    // }
    
    // Determine the correct action type
    $action_type = $lifecycle['next_action'];
    if ($action_type === 'recertification') {
        $is_recertification = true;
    }
    
    // echo '<div class="certificate-info-header">';
    // echo '<h2>' . ($is_recertification ? 'Recertification' : 'Renewal') . ' Application</h2>';
    // echo '<div class="certificate-details">';
    // echo '<p><strong>Certificate Number:</strong> ' . esc_html($cert_number) . '</p>';
    // echo '<p><strong>Method:</strong> ' . esc_html($cert_method) . '</p>';
    // echo '<p><strong>Level:</strong> ' . esc_html($level) . '</p>';
    // echo '<p><strong>Sector:</strong> ' . esc_html($sector) . '</p>';
    // echo '</div>';
    // echo '</div>';

    // Handle AJAX form loading
    if (isset($_GET['ajax_load']) && $_GET['ajax_load'] === 'cpd_form') {
        include get_stylesheet_directory() . '/renew/templates/renew-form-cpd.php';
        exit;
    }

    // Always show method selection options
    include get_stylesheet_directory() . '/renew/templates/renew-options.php';
    
    // Get current renewal method from URL parameter, default to CPD
    $current_renewal_method = isset($_GET['renewal_method']) ? strtoupper(sanitize_text_field($_GET['renewal_method'])) : 'CPD';
    
    // Ensure valid method
    if (!in_array($current_renewal_method, ['CPD', 'EXAM'])) {
        $current_renewal_method = 'CPD';
    }
    
    // Show form section (always visible)
    echo '<div class="renew-form-section" id="renewal-form-section">';
    
    // CPD Form Section - Show based on current method
    echo '<div id="cpd-form-section" class="renewal-method-section" style="' . ($current_renewal_method === 'CPD' ? '' : 'display:none;') . '">';
    include get_stylesheet_directory() . '/renew/templates/renew-form-cpd.php';
    echo '</div>';
    
    // Exam Form Section - Show based on current method
    echo '<div id="exam-form-section" class="renewal-method-section" style="' . ($current_renewal_method === 'EXAM' ? '' : 'display:none;') . '">';
    echo '<div class="exam-form-wrapper">';
    echo '<div class="exam-form-header">';
    // Title dynamically reflects renew or recertification context
    $exam_title = $is_recertification ? 'Recertification by Examination' : 'Renewal by Examination';
    echo '<h3><i class="dashicons dashicons-welcome-learn-more"></i> ' . esc_html($exam_title) . '</h3>';
        $exam_description = $is_recertification ? 'Complete the examination form below to recertify your certification through testing.' : 'Complete the examination form below to renew your certification through testing.';
        echo '<p class="exam-description">' . esc_html($exam_description) . '</p>';
    echo '<div class="exam-requirements">';
    echo '<h4><i class="dashicons dashicons-info"></i> Examination Requirements</h4>';
    echo '<ul>';
    echo '<li>Valid identification required during examination</li>';
    echo '<li>Examination fee must be paid before scheduling</li>';
    echo '<li>Pass mark: 70% or higher</li>';
    echo '<li>Examination duration: 2 hours</li>';
    echo '<li>Results will be available within 5 business days</li>';
    echo '</ul>';
    echo '</div>';
    echo '</div>';
    
    // Check if Gravity Forms is active and load form by default
    if (class_exists('GFForms')) {
        // Use Gravity Form ID 39 for exam renewals - loaded by default
        // Disable AJAX to prevent "Another submission is already in progress" error
        $exam_form_id = 39;
        
        // Ensure Gravity Forms scripts are enqueued to prevent gf_global errors
        gravity_form_enqueue_scripts($exam_form_id, false);
        wp_enqueue_script('gform_conditional_logic');
        wp_enqueue_script('gform_gravityforms');
        
        echo '<div class="gravity-form-container">';
        
        // Debug: Log the cert_id being passed
        error_log("Renewal router: Loading Form 39 with cert_id: {$cert_id}, cert_number: {$cert_number}, renewal_method: {$current_renewal_method}");
        
        // Add hidden input to maintain renewal method selection across form submissions
        echo '<input type="hidden" id="renewal_method_persistence" value="' . esc_attr($current_renewal_method) . '">';
        
        // The form will automatically populate field 28 (hidden field) with cert_id from URL parameter
        echo do_shortcode('[gravityform id="' . $exam_form_id . '" title="false" description="false" ajax="false"]');
        echo '</div>';
    } else {
        echo '<div class="gravity-forms-missing">';
        echo '<i class="dashicons dashicons-warning"></i>';
        $gravity_forms_message = $is_recertification ? 'Gravity Forms plugin is required for exam recertifications. Please contact the administrator.' : 'Gravity Forms plugin is required for exam renewals. Please contact the administrator.';
        echo '<p>' . esc_html($gravity_forms_message) . '</p>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
    ?>
</div>


