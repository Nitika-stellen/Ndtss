<?php
/**
 * Enhanced Certificate Lifecycle Management System
 * 
 * This system manages the complete certificate lifecycle:
 * 1. Initial Certificate (5 years) - Generated from Gravity Form 15
 * 2. Renewal Certificate (5 years) - Generated after 6 months before expiry
 * 3. Recertification Certificate (10 years) - Generated after 9 years from initial
 * 
 * Certificate Number Pattern:
 * - Initial: A1034
 * - Renewal: A1034-01  
 * - Recertification: A1034-02
 * 
 * @package SGNDT
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CertificateLifecycleManager {
    
    // Certificate lifecycle constants
    const INITIAL_CERT_VALIDITY_YEARS = 5;
    const RENEWAL_CERT_VALIDITY_YEARS = 5;
    const RECERTIFICATION_CERT_VALIDITY_YEARS = 10;
    const RENEWAL_WINDOW_MONTHS = 6;
    const RECERTIFICATION_WINDOW_MONTHS = 6;
    const GRACE_PERIOD_MONTHS = 12;
    
    // Certificate types
    const CERT_TYPE_INITIAL = 'initial';
    const CERT_TYPE_RENEWAL = 'renewal';
    const CERT_TYPE_RECERTIFICATION = 'recertification';
    
    // Status constants
    const STATUS_ISSUED = 'issued';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REVIEWING = 'reviewing';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_EXPIRED = 'expired';
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initHooks();
    }
    
    private function initHooks() {
        // Hook into certificate generation
        add_action('certificate_generated', array($this, 'handleCertificateGeneration'), 10, 5);
        
        // Hook into form submissions
        add_action('gform_after_submission_15', array($this, 'handleInitialCertificateSubmission'), 10, 2);
        add_action('gform_after_submission_31', array($this, 'handleRenewalByExamSubmission'), 10, 2);
        
        // Hook into CPD form submissions
        add_action('wp_ajax_submit_cpd_form', array($this, 'handleCPDRenewalSubmission'), 5);
        add_action('wp_ajax_nopriv_submit_cpd_form', array($this, 'handleCPDRenewalSubmission'), 5);
        
        // Admin approval hooks
        add_action('wp_ajax_form_31_approve_entry_ajax', array($this, 'handleForm31Approval'), 5);
        add_action('wp_ajax_approve_cpd_renewal', array($this, 'handleCPDApproval'), 5);
        
        // Certificate status update hooks
        add_action('wp_ajax_update_certificate_status', array($this, 'handleStatusUpdate'), 5);
    }
    
    /**
     * Get certificate lifecycle information for a user
     */
    public function getCertificateLifecycle($user_id, $certificate_number = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sgndt_final_certifications';
        
        if ($certificate_number) {
            // Clean the certificate number to prevent any encoding issues
            $certificate_number = sanitize_text_field($certificate_number);
            $base_number = $this->getBaseCertificateNumber($certificate_number);
            
            // Get specific certificate lifecycle
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} 
                 WHERE user_id = %d 
                 AND (certificate_number = %s OR certificate_number = %s OR certificate_number LIKE %s)
                 ORDER BY issue_date ASC",
                $user_id,
                $certificate_number,  // Exact match first
                $base_number,        // Base number match
                $base_number . '-%'  // Suffix pattern match
            );
        } else {
            // Get all certificates for user
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} 
                 WHERE user_id = %d 
                 ORDER BY issue_date ASC",
                $user_id
            );
        }
        
        $certificates = $wpdb->get_results($query, ARRAY_A);
        
        // Debug logging
        error_log("=== CERTIFICATE LIFECYCLE DEBUG ===");
        error_log("User ID: {$user_id}");
        error_log("Certificate Number: {$certificate_number}");
        error_log("Base Number: {$base_number}");
        error_log("Query: {$query}");
        error_log("Certificates found: " . count($certificates));
        if (!empty($certificates)) {
            foreach ($certificates as $cert) {
                error_log("Certificate: {$cert['certificate_number']} (ID: {$cert['final_certification_id']}) - Issue: {$cert['issue_date']}, Expiry: {$cert['expiry_date']}");
            }
        }
        
        return $this->organizeCertificateLifecycle($certificates);
    }
    
    /**
     * Organize certificates into lifecycle structure
     */
    private function organizeCertificateLifecycle($certificates) {
        $lifecycle = array(
            'initial' => null,
            'renewal' => null,
            'recertification' => null,
            'current_status' => 'none',
            'next_action' => null,
            'eligibility' => array()
        );
        
        error_log("=== ORGANIZING CERTIFICATES ===");
        error_log("Total certificates to organize: " . count($certificates));
        
        foreach ($certificates as $cert) {
            $cert_type = $this->getCertificateType($cert['certificate_number']);
            
            error_log("Processing certificate: {$cert['certificate_number']} (ID: {$cert['final_certification_id']}) - Type: {$cert_type}");
            
            switch ($cert_type) {
                case self::CERT_TYPE_INITIAL:
                    $lifecycle['initial'] = $cert;
                    error_log("Set as initial certificate");
                    break;
                case self::CERT_TYPE_RENEWAL:
                    $lifecycle['renewal'] = $cert;
                    error_log("Set as renewal certificate");
                    break;
                case self::CERT_TYPE_RECERTIFICATION:
                    $lifecycle['recertification'] = $cert;
                    error_log("Set as recertification certificate");
                    break;
            }
        }
        
        // Determine current status and next action
        $lifecycle['current_status'] = $this->determineCurrentStatus($lifecycle);
        $lifecycle['next_action'] = $this->determineNextAction($lifecycle);
        $lifecycle['eligibility'] = $this->checkEligibility($lifecycle);
        
        error_log("=== FINAL LIFECYCLE STRUCTURE ===");
        error_log("Initial: " . ($lifecycle['initial'] ? $lifecycle['initial']['certificate_number'] : 'null'));
        error_log("Renewal: " . ($lifecycle['renewal'] ? $lifecycle['renewal']['certificate_number'] : 'null'));
        error_log("Recertification: " . ($lifecycle['recertification'] ? $lifecycle['recertification']['certificate_number'] : 'null'));
        error_log("Current status: {$lifecycle['current_status']}");
        error_log("Next action: {$lifecycle['next_action']}");
        error_log("Eligibility - Renewal: " . ($lifecycle['eligibility']['renewal'] ? 'true' : 'false'));
        error_log("Eligibility - Recertification: " . ($lifecycle['eligibility']['recertification'] ? 'true' : 'false'));
        
        return $lifecycle;
    }
    
    /**
     * Get certificate type from certificate number
     */
    private function getCertificateType($certificate_number) {
        // Check for recertification first (most specific)
        if (preg_match('/-02$/', $certificate_number)) {
            return self::CERT_TYPE_RECERTIFICATION;
        }
        // Check for renewal (second most specific)  
        elseif (preg_match('/-01$/', $certificate_number)) {
            return self::CERT_TYPE_RENEWAL;
        }
        // Default to initial for everything else
        else {
            return self::CERT_TYPE_INITIAL;
        }
    }
    
    /**
     * Get base certificate number (without suffix)
     */
    private function getBaseCertificateNumber($certificate_number) {
        // Remove renewal/recertification suffixes
        $base_number = preg_replace('/-0[12]$/', '', $certificate_number);
        
        // Log for debugging
        error_log("Getting base number from: {$certificate_number} -> {$base_number}");
        
        return $base_number;
    }
    
    /**
     * Determine current certificate status
     */
    private function determineCurrentStatus($lifecycle) {
        $current_date = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        
        // Check if recertification exists
        if ($lifecycle['recertification']) {
            $expiry = new DateTime($lifecycle['recertification']['expiry_date']);
            if ($current_date > $expiry) {
                return 'expired';
            }
            return 'recertified';
        }
        
        // Check if renewal exists
        if ($lifecycle['renewal']) {
            $expiry = new DateTime($lifecycle['renewal']['expiry_date']);
            if ($current_date > $expiry) {
                return 'expired';
            }
            return 'renewed';
        }
        
        // Check initial certificate
        if ($lifecycle['initial']) {
            $expiry = new DateTime($lifecycle['initial']['expiry_date']);
            if ($current_date > $expiry) {
                return 'expired';
            }
            return 'active';
        }
        
        return 'none';
    }
    
    /**
     * Determine next action for user
     */
    private function determineNextAction($lifecycle) {
        $current_date = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        
        // If expired, no action available
        if ($lifecycle['current_status'] === 'expired') {
            return null;
        }
        
        // Check recertification eligibility (9 years from initial issue)
        if ($lifecycle['initial'] && !$lifecycle['recertification']) {
            $issue_date = new DateTime($lifecycle['initial']['issue_date']);
            $years_since_issue = $current_date->diff($issue_date)->y;
            
            error_log("Recertification check - Years since initial issue: {$years_since_issue}, Required: 9");
            
            if ($years_since_issue >= 9) {
                error_log("Recertification eligible - 9+ years from initial issue");
                return 'recertification';
            }
        }
        
        // Check renewal eligibility for active certificate
        $active_cert = $lifecycle['recertification'] ?: $lifecycle['renewal'] ?: $lifecycle['initial'];
        if ($active_cert) {
            $expiry = new DateTime($active_cert['expiry_date']);
            $renewal_window = clone $expiry;
            $renewal_window->modify('-' . self::RENEWAL_WINDOW_MONTHS . ' months');
            $grace_period = clone $expiry;
            $grace_period->modify('+' . self::GRACE_PERIOD_MONTHS . ' months');
            
            error_log("Renewal check for certificate: {$active_cert['certificate_number']}");
            error_log("Current date: " . $current_date->format('Y-m-d'));
            error_log("Renewal window starts: " . $renewal_window->format('Y-m-d'));
            error_log("Grace period ends: " . $grace_period->format('Y-m-d'));
            
            // Check if within renewal window (including grace period)
            if ($current_date >= $renewal_window && $current_date <= $grace_period) {
                error_log("Renewal window is OPEN - eligible for renewal");
                return 'renewal';
            } else {
                error_log("Renewal window is CLOSED");
            }
        }
        
        return null;
    }
    
    /**
     * Check eligibility for renewal/recertification
     */
    private function checkEligibility($lifecycle) {
        $current_date = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $eligibility = array(
            'renewal' => false,
            'recertification' => false,
            'renewal_window_open' => false,
            'recertification_window_open' => false,
            'expired' => false
        );
        
        $active_cert = $lifecycle['recertification'] ?: $lifecycle['renewal'] ?: $lifecycle['initial'];
        
        if (!$active_cert) {
            error_log("No active certificate found for eligibility check");
            return $eligibility;
        }
        
        error_log("Checking eligibility for active certificate: {$active_cert['certificate_number']}");
        
        $expiry = new DateTime($active_cert['expiry_date']);
        $grace_period = clone $expiry;
        $grace_period->modify('+' . self::GRACE_PERIOD_MONTHS . ' months');
        
        // Check if expired
        if ($current_date > $grace_period) {
            $eligibility['expired'] = true;
            error_log("Certificate is EXPIRED");
            return $eligibility;
        }
        
        // Check renewal eligibility
        $renewal_window = clone $expiry;
        $renewal_window->modify('-' . self::RENEWAL_WINDOW_MONTHS . ' months');
        
        error_log("Renewal window check:");
        error_log("Current date: " . $current_date->format('Y-m-d'));
        error_log("Renewal window start: " . $renewal_window->format('Y-m-d'));
        error_log("Grace period end: " . $grace_period->format('Y-m-d'));
        
        if ($current_date >= $renewal_window && $current_date <= $grace_period) {
            $eligibility['renewal'] = true;
            $eligibility['renewal_window_open'] = true;
            error_log("RENEWAL ELIGIBLE - window is open");
        } else {
            error_log("RENEWAL NOT ELIGIBLE - window is closed");
        }
        
        // Check recertification eligibility
        if ($lifecycle['initial'] && !$lifecycle['recertification']) {
            $issue_date = new DateTime($lifecycle['initial']['issue_date']);
            $years_since_issue = $current_date->diff($issue_date)->y;
            
            error_log("Recertification check - Years since initial issue: {$years_since_issue}");
            
            if ($years_since_issue >= 9) {
                $eligibility['recertification'] = true;
                $eligibility['recertification_window_open'] = true;
                error_log("RECERTIFICATION ELIGIBLE - 9+ years from initial issue");
            } else {
                error_log("RECERTIFICATION NOT ELIGIBLE - only {$years_since_issue} years from initial issue");
            }
        } else {
            error_log("RECERTIFICATION NOT ELIGIBLE - no initial certificate or recertification already exists");
        }
        
        return $eligibility;
    }
    
    /**
     * Generate new certificate number based on type
     */
    public function generateCertificateNumber($base_number, $cert_type) {
        switch ($cert_type) {
            case self::CERT_TYPE_RENEWAL:
                return $base_number . '-01';
            case self::CERT_TYPE_RECERTIFICATION:
                return $base_number . '-02';
            default:
                return $base_number;
        }
    }
    
    /**
     * Calculate expiry date for new certificate with proper date continuity
     */
    public function calculateExpiryDate($issue_date, $cert_type) {
        $issue_datetime = new DateTime($issue_date);
        
        switch ($cert_type) {
            case self::CERT_TYPE_RENEWAL:
                $issue_datetime->modify('+' . self::RENEWAL_CERT_VALIDITY_YEARS . ' years');
                break;
            case self::CERT_TYPE_RECERTIFICATION:
                $issue_datetime->modify('+' . self::RECERTIFICATION_CERT_VALIDITY_YEARS . ' years');
                break;
            default:
                $issue_datetime->modify('+' . self::INITIAL_CERT_VALIDITY_YEARS . ' years');
        }
        
        return $issue_datetime->format('Y-m-d');
    }
    
    /**
     * Get issue date for renewal/recertification certificate (should be previous cert's expiry date)
     */
    public function getNextCertificateIssueDate($user_id, $certificate_number, $cert_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'sgndt_final_certifications';
        
        if ($cert_type === self::CERT_TYPE_RENEWAL) {
            // Renewal: Get original certificate's expiry date
            $previous_cert = $wpdb->get_row($wpdb->prepare(
                "SELECT expiry_date FROM {$table} 
                 WHERE user_id = %d AND certificate_number = %s 
                 ORDER BY issue_date DESC LIMIT 1",
                $user_id,
                $certificate_number
            ));
            
            if ($previous_cert && !empty($previous_cert->expiry_date)) {
                return $previous_cert->expiry_date;
            }
        } elseif ($cert_type === self::CERT_TYPE_RECERTIFICATION) {
            // Recertification: Get renewal certificate's expiry date
            $renewal_number = $certificate_number . '-01';
            $previous_cert = $wpdb->get_row($wpdb->prepare(
                "SELECT expiry_date FROM {$table} 
                 WHERE user_id = %d AND certificate_number = %s 
                 ORDER BY issue_date DESC LIMIT 1",
                $user_id,
                $renewal_number
            ));
            
            if ($previous_cert && !empty($previous_cert->expiry_date)) {
                return $previous_cert->expiry_date;
            }
            
            // Fallback: Get original certificate's expiry + 5 years
            $original_cert = $wpdb->get_row($wpdb->prepare(
                "SELECT expiry_date FROM {$table} 
                 WHERE user_id = %d AND certificate_number = %s 
                 ORDER BY issue_date DESC LIMIT 1",
                $user_id,
                $certificate_number
            ));
            
            if ($original_cert && !empty($original_cert->expiry_date)) {
                $expiry_datetime = new DateTime($original_cert->expiry_date);
                $expiry_datetime->modify('+5 years');
                return $expiry_datetime->format('Y-m-d');
            }
        }
        
        // Default: use current date
        return current_time('mysql', true);
    }
    
    /**
     * Handle initial certificate submission (Form 15)
     */
    public function handleInitialCertificateSubmission($entry, $form) {
        if ($form['id'] != 15) {
            return;
        }
        
        $user_id = rgar($entry, 'created_by');
        $certificate_number = rgar($entry, 'certificate_number_field'); // Adjust field ID as needed
        
        // Log initial certificate submission
        error_log("Initial certificate submission for user {$user_id}, cert number: {$certificate_number}");
        
        // Update certificate status
        $this->updateCertificateStatus($user_id, $certificate_number, self::STATUS_SUBMITTED, array(
            'submission_method' => 'initial_form',
            'form_entry_id' => rgar($entry, 'id'),
            'cert_type' => self::CERT_TYPE_INITIAL
        ));
    }
    
    /**
     * Handle renewal by exam submission (Form 31)
     */
    public function handleRenewalByExamSubmission($entry, $form) {
        if ($form['id'] != 31) {
            return;
        }
        
        $user_id = rgar($entry, 'created_by');
        $original_cert_id = rgar($entry, '28'); // Certificate ID field
        $certificate_number = rgar($entry, '13'); // Certificate number field
        
        error_log("Renewal by exam submission for user {$user_id}, original cert ID: {$original_cert_id}");
        
        // Update certificate status
        $this->updateCertificateStatus($user_id, $certificate_number, self::STATUS_SUBMITTED, array(
            'submission_method' => 'exam_form',
            'form_entry_id' => rgar($entry, 'id'),
            'original_cert_id' => $original_cert_id,
            'cert_type' => self::CERT_TYPE_RENEWAL
        ));
    }
    
    /**
     * Handle CPD renewal submission
     */
    public function handleCPDRenewalSubmission() {
       
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        $user_id = get_current_user_id();
        $certificate_number = sanitize_text_field($_POST['certificate_number']);
        $original_cert_id = sanitize_text_field($_POST['original_cert_id']);
        
        error_log("CPD renewal submission for user {$user_id}, cert number: {$certificate_number}");
        
        // Update certificate status
        $this->updateCertificateStatus($user_id, $certificate_number, self::STATUS_SUBMITTED, array(
            'submission_method' => 'cpd_form',
            'original_cert_id' => $original_cert_id,
            'cert_type' => self::CERT_TYPE_RENEWAL
        ));
        
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
    
    /**
     * Handle certificate generation
     */
    public function handleCertificateGeneration($final_certification_id, $certificate_number, $user_id, $cert_data, $exam_entry_id) {
        // Check if this is a renewal/recertification certificate
        if (preg_match('/-0[12]$/', $certificate_number)) {
            $base_number = $this->getBaseCertificateNumber($certificate_number);
            $cert_type = $this->getCertificateType($certificate_number);
            
            error_log("Generated {$cert_type} certificate: {$certificate_number} for user {$user_id}");
            
            // Update original certificate status
            $this->updateCertificateStatus($user_id, $base_number, 'renewed', array(
                'renewed_cert_number' => $certificate_number,
                'renewed_cert_id' => $final_certification_id,
                'issue_date' => current_time('mysql'),
                'cert_type' => $cert_type
            ));
        }
    }
    
    /**
     * Update certificate status with comprehensive tracking
     */
    public function updateCertificateStatus($user_id, $certificate_number, $status, $additional_data = array()) {
        if (!$user_id || !$certificate_number || !$status) {
            return false;
        }
        
        // Get certificate ID if not provided
        if (empty($additional_data['cert_id'])) {
            global $wpdb;
            $cert_record = $wpdb->get_row($wpdb->prepare(
                "SELECT final_certification_id FROM {$wpdb->prefix}sgndt_final_certifications 
                 WHERE user_id = %d AND certificate_number = %s 
                 ORDER BY issue_date DESC LIMIT 1",
                $user_id, $certificate_number
            ));
            
            if ($cert_record) {
                $additional_data['cert_id'] = $cert_record->final_certification_id;
            }
        }
        
        // Update status using ID-based system
        if (!empty($additional_data['cert_id'])) {
            $cert_id = $additional_data['cert_id'];
            $status_key = 'cert_status_id_' . $cert_id;
            
            update_user_meta($user_id, $status_key, $status);
            update_user_meta($user_id, $status_key . '_date', current_time('mysql'));
            
            // Update additional data
            foreach ($additional_data as $key => $value) {
                if ($key !== 'cert_id') {
                    update_user_meta($user_id, $status_key . '_' . $key, $value);
                }
            }
            
            error_log("Certificate status updated: ID {$cert_id}, Status: {$status}, User: {$user_id}");
            return true;
        }
        
        return false;
    }
    
    /**
     * Get certificate status information - Now supports both cert_number and cert_id
     */
    public function getCertificateStatus($user_id, $certificate_identifier) {
        global $wpdb;
        
        // Check if identifier is numeric (cert_id) or string (cert_number)
        if (is_numeric($certificate_identifier)) {
            // Direct cert_id lookup
            $cert_id = intval($certificate_identifier);
            $cert_record = $wpdb->get_row($wpdb->prepare(
                "SELECT renewal_status, renewal_method, renewal_submitted_date, renewal_approved_date, renewal_rejected_date, renewal_rejection_reason 
                 FROM {$wpdb->prefix}sgndt_final_certifications 
                 WHERE final_certification_id = %d AND user_id = %d",
                $cert_id, $user_id
            ));
        } else {
            // Get certificate data from certificate number
            $cert_record = $wpdb->get_row($wpdb->prepare(
                "SELECT final_certification_id, renewal_status, renewal_method, renewal_submitted_date, renewal_approved_date, renewal_rejected_date, renewal_rejection_reason 
                 FROM {$wpdb->prefix}sgndt_final_certifications 
                 WHERE user_id = %d AND certificate_number = %s 
                 ORDER BY issue_date DESC LIMIT 1",
                $user_id, $certificate_identifier
            ));
            
            if (!$cert_record) {
                return false;
            }
            
            $cert_id = $cert_record->final_certification_id;
        }
        
        if (!$cert_record) {
            return false;
        }
        
        // Determine the status and date based on renewal status
        $status = $cert_record->renewal_status;
        $status_date = null;
        
        // If no active renewal status, check if there was a recent rejection
        if (empty($status) && !empty($cert_record->renewal_rejected_date)) {
            // Check if rejection was within last 24 hours to show rejection message
            $rejection_time = strtotime($cert_record->renewal_rejected_date);
            $current_time = current_time('timestamp');
            
            if (($current_time - $rejection_time) < (24 * 60 * 60)) { // 24 hours
                $status = 'recently_rejected';
                $status_date = $cert_record->renewal_rejected_date;
            }
        } else {
            switch ($status) {
                case 'submitted':
                    $status_date = $cert_record->renewal_submitted_date;
                    break;
                case 'approved':
                    $status_date = $cert_record->renewal_approved_date;
                    break;
                case 'rejected':
                    $status_date = $cert_record->renewal_rejected_date;
                    break;
            }
        }
        
        return array(
            'status' => $status,
            'status_date' => $status_date,
            'cert_id' => $cert_id,
            'renewal_method' => $cert_record->renewal_method,
            'rejection_reason' => $cert_record->renewal_rejection_reason
        );
    }
    
    /**
     * Get user-friendly status display
     */
    public function getStatusDisplay($status_info, $certificate_number) {
        if (!$status_info || empty($status_info['status'])) {
            return $this->getActionButton($certificate_number);
        }
        
        $status = $status_info['status'];
        $status_date = $status_info['status_date'];
        $renewal_method = isset($status_info['renewal_method']) ? $status_info['renewal_method'] : '';
        $formatted_date = $status_date ? date('d/m/Y', strtotime($status_date)) : 'N/A';
        $method_badge = !empty($renewal_method) ? '<span class="renewal-method-badge">' . strtoupper($renewal_method) . '</span>' : '';
        
        switch ($status) {
            case 'submitted':
            case 'reviewing':
                return '<div class="renewal-status-wrapper status-applied">
                    <div class="status-header">
                        <span class="status-icon">📝</span>
                        <span class="status-text">Applied for Renewal</span>
                        ' . $method_badge . '
                    </div>
                    <div class="status-details">
                        <small>Submitted: ' . esc_html($formatted_date) . '</small><br>
                        <small class="status-note">Your application is being reviewed</small>
                    </div>
                </div>';
                
            case 'approved':
                return '<div class="renewal-status-wrapper status-approved">
                    <div class="status-header">
                        <span class="status-icon">✅</span>
                        <span class="status-text">Renewal Approved</span>
                        ' . $method_badge . '
                    </div>
                    <div class="status-details">
                        <small>Approved: ' . esc_html($formatted_date) . '</small><br>
                        <small class="status-note">Certificate will be issued soon</small>
                    </div>
                </div>';
                
            case 'rejected':
                return '<div class="renewal-status-wrapper status-rejected">
                    <div class="status-header">
                        <span class="status-icon">❌</span>
                        <span class="status-text">Application Rejected</span>
                        ' . $method_badge . '
                    </div>
                    <div class="status-details">
                        <small>Rejected: ' . esc_html($formatted_date) . '</small><br>
                        <small class="status-note">Your ' . strtolower($renewal_method) . ' renewal was rejected. Please try renewal by exam.</small>
                    </div>
                </div>';
                
            case 'recently_rejected':
                $rejection_reason = isset($status_info['rejection_reason']) ? $status_info['rejection_reason'] : 'No reason provided';
                return '<div class="renewal-status-wrapper status-rejected">
                    <div class="status-header">
                        <span class="status-icon">❌</span>
                        <span class="status-text">Recent Application Rejected</span>
                    </div>
                    <div class="status-details">
                        <small>Rejected: ' . esc_html($formatted_date) . '</small><br>
                        <small class="status-note">Reason: ' . esc_html($rejection_reason) . '</small><br>
                        <small class="status-note">You can now apply for renewal using a different method.</small>
                    </div>
                </div>' . $this->getActionButton($certificate_number);
                
            case 'renewed':
                return '<div class="renewal-status-wrapper status-renewed">
                    <div class="status-header">
                        <span class="status-icon">🔄</span>
                        <span class="status-text">Successfully Renewed</span>
                        ' . $method_badge . '
                    </div>
                    <div class="status-details">
                        <small>Renewed: ' . esc_html($formatted_date) . '</small>
                    </div>
                </div>';
                
            default:
                return $this->getActionButton($certificate_number);
        }
    }
    
    /**
     * Get action button based on certificate eligibility
     */
    // private function getActionButton($certificate_number) {
    //     $lifecycle = $this->getCertificateLifecycle(get_current_user_id(), $certificate_number);
        
    //     // if (!$lifecycle['next_action']) {
    //     //     return '<span class="status-fail">No Action Available</span>';
    //     // }
    //      $action_type = $lifecycle['next_action'];
    //     $button_text = ucfirst($action_type);
    //     $button_class = 'action-button ' . $action_type . '-button';
        
    //     $url = $action_type === 'renewal' ? 
    //         home_url('/renew-recertification') : 
    //         home_url('/renew-recertification');
            
    //     $url = add_query_arg(array(
    //         'cert_number' => $certificate_number,
    //         'type' => $action_type
    //     ), $url);
    //     if (!$lifecycle['next_action'] && !$lifecycle['eligibility']['renewal']) {
    //         return '<span class="status-fail"><div class="action-cell-wrapper">
    //         <div class="action-buttons">
    //             <a href="' . esc_url($url) . '" class="' . esc_attr($button_class) . '">' . esc_html($button_text) . '</a>
    //         </div>
    //     </div></span>';
    //     }
       
        
    //     return '<div class="action-cell-wrapper">
    //         <div class="action-buttons">
    //             <a href="' . esc_url($url) . '" class="' . esc_attr($button_class) . '">' . esc_html($button_text) . '</a>
    //         </div>
    //     </div>';
    // }
    private function getActionButton($certificate_number) {
        global $wpdb;
        
        // Get certificate data from database
        $cert = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications 
             WHERE certificate_number = %s AND user_id = %d",
            $certificate_number,
            get_current_user_id()
        ), ARRAY_A);
        
        if (!$cert || empty($cert['issue_date']) || empty($cert['expiry_date'])) {
            return '<span class="status-fail">Certificate data not available</span>';
        }
        
        try {
            $current_date = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
            $issue_datetime = new DateTime($cert['issue_date'], new DateTimeZone('Asia/Kolkata'));
            $expiry_datetime = new DateTime($cert['expiry_date'], new DateTimeZone('Asia/Kolkata'));

            // Calculate renewal window (6 months before expiry)
            $renewal_eligible_date = clone $expiry_datetime;
            $renewal_eligible_date->modify('-6 months'); // RENEWAL_ELIGIBLE_MONTHS

            // Calculate grace period end (12 months after expiry)
            $renewal_deadline_date = clone $expiry_datetime;
            $renewal_deadline_date->modify('+12 months'); // RENEWAL_DEADLINE_MONTHS

            $interval = $current_date->diff($issue_datetime);
            $years_since_issue = $interval->y + ($interval->m / 12);

            // Check if certificate is expired
            if ($current_date > $renewal_deadline_date) {
                return '<span class="status-expired">Expired</span>';
            }

            // Check if in renewal window (6 months before expiry to 12 months after expiry)
            if ($current_date >= $renewal_eligible_date && $current_date <= $renewal_deadline_date) {
                // Determine if this should be recertification (after 9+ years from initial issue)
                if ($years_since_issue >= 9) { // RECERTIFICATION_CYCLE_YEARS
                    $action_type = 'recertification';
                    $button_text = 'Recertify';
                    $button_class = 'action-button recertification-btn';
                } else {
                    $action_type = 'renewal';
                    $button_text = 'Renew';
                    $button_class = 'action-button renewal-btn';
                }
                
                $url = add_query_arg(array(
                    'cert_id' => $cert['id'],
                    'cert_number' => $certificate_number,
                    'method' => $cert['method'],
                    'level' => $cert['level'],
                    'sector' => $cert['sector'],
                    'scope' => $cert['scope'],
                    'exam_entry_id' => $cert['exam_entry_id'],
                    'marks_entry_id' => $cert['marks_entry_id'],
                    'type' => $action_type
                ), home_url('/renew-recertification'));
                
                return '<div class="action-cell-wrapper">
                    <div class="action-buttons">
                        <a href="' . esc_url($url) . '" class="' . esc_attr($button_class) . '">' . esc_html($button_text) . '</a>
                    </div>
                </div>';
            }

        } catch (Exception $e) {
            error_log("Error processing dates for certificate {$certificate_number}: " . $e->getMessage());
            return '<span class="status-error">Error</span>';
        }

        return '<span class="status-valid">Not eligible yet</span>';
    }
}

// Initialize the certificate lifecycle manager
CertificateLifecycleManager::getInstance();

/**
 * Helper functions for backward compatibility
 */
function get_certificate_lifecycle($user_id, $certificate_number = null) {
    return CertificateLifecycleManager::getInstance()->getCertificateLifecycle($user_id, $certificate_number);
}

function update_certificate_lifecycle_status($user_id, $certificate_number, $status, $additional_data = array()) {
    return CertificateLifecycleManager::getInstance()->updateCertificateStatus($user_id, $certificate_number, $status, $additional_data);
}

function get_certificate_lifecycle_status($user_id, $certificate_number) {
    return CertificateLifecycleManager::getInstance()->getCertificateStatus($user_id, $certificate_number);
}

function get_certificate_lifecycle_display($status_info, $certificate_number) {
    return CertificateLifecycleManager::getInstance()->getStatusDisplay($status_info, $certificate_number);
}
