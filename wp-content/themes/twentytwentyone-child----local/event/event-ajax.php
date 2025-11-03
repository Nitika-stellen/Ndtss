<?php
/**
 * Event AJAX Handlers - Secure AJAX operations for events
 * Handles all AJAX requests for event management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle event approval AJAX request
 */
function event_handle_approve_entry_ajax() {
    try {
        // Verify nonce
        if (!check_ajax_referer('approve_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        // Validate and sanitize input
        $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $approver_id = get_current_user_id();
        
        if (!$entry_id || !$user_id || !$event_id) {
            wp_send_json_error(['message' => 'Missing required parameters']);
        }
        
        event_log_info('Event approval process started', [
            'entry_id' => $entry_id,
            'user_id' => $user_id,
            'event_id' => $event_id,
            'approver_id' => $approver_id
        ]);
        
        // Process approval
        $result = event_process_approval($entry_id, $user_id, $event_id, $approver_id);
        
        if ($result['success']) {
            event_log_info('Event approval completed successfully', [
                'entry_id' => $entry_id,
                'user_id' => $user_id,
                'event_id' => $event_id
            ]);
            wp_send_json_success($result['data']);
        } else {
            event_log_error('Event approval failed', [
                'entry_id' => $entry_id,
                'user_id' => $user_id,
                'event_id' => $event_id,
                'error' => $result['error']
            ]);
            wp_send_json_error(['message' => $result['error']]);
        }
        
    } catch (Exception $e) {
        event_log_error('Event approval exception', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        wp_send_json_error(['message' => 'An unexpected error occurred']);
    }
}

/**
 * Process event approval
 */
function event_process_approval($entry_id, $user_id, $event_id, $approver_id) {
    try {
        // Include QR code library
        $library_path = get_stylesheet_directory() . '/phpqrcode/qrlib.php';
        if (!file_exists($library_path)) {
            throw new Exception('QR code library not found');
        }
        require_once $library_path;
        
        // Get entry and validate
        $entry = GFAPI::get_entry($entry_id);
        if (is_wp_error($entry)) {
            throw new Exception('Invalid entry');
        }
        
        // Get user and event info
        $user_info = get_userdata($user_id);
        if (!$user_info) {
            throw new Exception('Invalid user');
        }
        
        $event_name = get_the_title($event_id);
        if (!$event_name) {
            throw new Exception('Invalid event');
        }
        
        // Get event details
        $event_start = get_post_meta($event_id, '_EventStartDate', true);
        $event_end = get_post_meta($event_id, '_EventEndDate', true);
        $event_start_time = get_post_meta($event_id, '_EventStartTime', true);
        $event_end_time = get_post_meta($event_id, '_EventEndTime', true);
        
        // Format event times
        $event_start_datetime = date('F j, Y, g:i a', strtotime($event_start . ' ' . $event_start_time));
        $event_end_datetime = date('F j, Y, g:i a', strtotime($event_end . ' ' . $event_end_time));
        
        // Generate verification token
        $verification_token = wp_generate_password(20, false);
        
        // Prepare QR code data
        $verification_page_url = site_url('/verify-qr-code');
        $qr_data = add_query_arg([
            'user_id' => $user_id,
            'event_id' => $event_id,
            'token' => $verification_token,
            'starttime' => urlencode($event_start_datetime),
            'endtime' => urlencode($event_end_datetime),
            'nocache' => wp_generate_password(20, false) . rand(1000, 9999)
        ], $verification_page_url);
        
        // Generate QR code
        $upload_dir = wp_upload_dir();
        $qr_code_path = $upload_dir['path'] . '/qr_code_' . $entry_id . '.png';
        QRcode::png($qr_data, $qr_code_path, QR_ECLEVEL_M);
        
        if (!file_exists($qr_code_path)) {
            throw new Exception('QR code generation failed');
        }
        
        // Store verification token
        $hashed_token = wp_hash_password($verification_token);
        update_user_meta($user_id, 'event_' . $event_id . '_verification_token', $hashed_token);
        
        // Update approval status
        update_user_meta($user_id, 'event_' . $event_id . '_approval_status', 'approved');
        update_user_meta($user_id, 'event_' . $event_id . '_approved_by', $approver_id);
        update_user_meta($user_id, 'event_' . $event_id . '_approval_date', current_time('mysql'));
        
        // Send notifications
        $email_result = event_send_approval_notifications($user_info, $event_id, $event_name, $event_start_datetime, $event_end_datetime, $qr_code_path);
        
        // Clear cache
        event_clear_cache($event_id);
        
        return [
            'success' => true,
            'data' => [
                'message' => 'Event registration approved successfully',
                'qr_code_url' => $verification_page_url,
                'email_sent' => $email_result
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Send approval notifications
 */
function event_send_approval_notifications($user_info, $event_id, $event_name, $event_start_datetime, $event_end_datetime, $qr_code_path) {
    try {
        $user_email = $user_info->user_email;
        $admin_email = get_option('admin_email');
        
        // User notification
        $user_subject = 'Your Event Registration has been Approved - ' . $event_name;
        $user_message = event_get_email_template('user_approval', [
            'user_name' => $user_info->display_name,
            'event_name' => $event_name,
            'event_start' => $event_start_datetime,
            'event_end' => $event_end_datetime
        ]);
        
        $headers = [];
        $attachments = [$qr_code_path];
        
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        $user_sent = wp_mail($user_email, $user_subject, $user_message, $headers, $attachments);
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        // Admin notification
        $admin_subject = 'User Registration Approved for Event: ' . $event_name;
        $admin_message = event_get_email_template('admin_approval', [
            'user_name' => $user_info->display_name,
            'user_email' => $user_email,
            'event_name' => $event_name,
            'event_start' => $event_start_datetime,
            'event_end' => $event_end_datetime,
            'approver_id' => get_current_user_id()
        ]);
        
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        $admin_sent = wp_mail($admin_email, $admin_subject, $admin_message);
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        return $user_sent && $admin_sent;
        
    } catch (Exception $e) {
        event_log_error('Failed to send approval notifications', [
            'user_id' => $user_info->ID,
            'event_id' => $event_id,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Handle event rejection AJAX request
 */
function event_handle_reject_entry_ajax() {
    try {
        // Verify nonce
        if (!check_ajax_referer('reject_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        // Validate and sanitize input
        $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $reject_reason = isset($_POST['reject_reason']) ? sanitize_text_field($_POST['reject_reason']) : '';
        
        if (!$entry_id || !$user_id || !$event_id || !$reject_reason) {
            wp_send_json_error(['message' => 'Missing required parameters']);
        }
        
        event_log_info('Event rejection process started', [
            'entry_id' => $entry_id,
            'user_id' => $user_id,
            'event_id' => $event_id,
            'reject_reason' => $reject_reason
        ]);
        
        // Process rejection
        $result = event_process_rejection($entry_id, $user_id, $event_id, $reject_reason);
        
        if ($result['success']) {
            event_log_info('Event rejection completed successfully', [
                'entry_id' => $entry_id,
                'user_id' => $user_id,
                'event_id' => $event_id
            ]);
            wp_send_json_success($result['data']);
        } else {
            event_log_error('Event rejection failed', [
                'entry_id' => $entry_id,
                'user_id' => $user_id,
                'event_id' => $event_id,
                'error' => $result['error']
            ]);
            wp_send_json_error(['message' => $result['error']]);
        }
        
    } catch (Exception $e) {
        event_log_error('Event rejection exception', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        wp_send_json_error(['message' => 'An unexpected error occurred']);
    }
}

/**
 * Process event rejection
 */
function event_process_rejection($entry_id, $user_id, $event_id, $reject_reason) {
    try {
        // Get user and event info
        $user_info = get_userdata($user_id);
        if (!$user_info) {
            throw new Exception('Invalid user');
        }
        
        $event_name = get_the_title($event_id);
        if (!$event_name) {
            throw new Exception('Invalid event');
        }
        
        // Update user meta
        update_user_meta($user_id, 'event_' . $event_id . '_approval_status', 'rejected');
        update_user_meta($user_id, 'event_' . $event_id . '_reject_reason', $reject_reason);
        update_user_meta($user_id, 'event_' . $event_id . '_rejection_date', current_time('mysql'));
        
        // Send notifications
        $email_result = event_send_rejection_notifications($user_info, $event_id, $event_name, $reject_reason);
        
        // Clear cache
        event_clear_cache($event_id);
        
        return [
            'success' => true,
            'data' => [
                'message' => 'Event registration rejected successfully',
                'email_sent' => $email_result
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Send rejection notifications
 */
function event_send_rejection_notifications($user_info, $event_id, $event_name, $reject_reason) {
    try {
        $user_email = $user_info->user_email;
        $admin_email = get_option('admin_email');
        
        // User notification
        $user_subject = 'Your Event Registration has been Rejected - ' . $event_name;
        $user_message = event_get_email_template('user_rejection', [
            'user_name' => $user_info->display_name,
            'event_name' => $event_name,
            'reject_reason' => $reject_reason
        ]);
        
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        $user_sent = wp_mail($user_email, $user_subject, $user_message);
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        // Admin notification
        $admin_subject = 'User Registration Rejected for Event: ' . $event_name;
        $admin_message = event_get_email_template('admin_rejection', [
            'user_name' => $user_info->display_name,
            'user_email' => $user_email,
            'event_name' => $event_name,
            'reject_reason' => $reject_reason
        ]);
        
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        $admin_sent = wp_mail($admin_email, $admin_subject, $admin_message);
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        return $user_sent && $admin_sent;
        
    } catch (Exception $e) {
        event_log_error('Failed to send rejection notifications', [
            'user_id' => $user_info->ID,
            'event_id' => $event_id,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Handle event checkout AJAX request
 */
function event_handle_checkout_ajax() {
    try {
        // Verify nonce
        if (!check_ajax_referer('event_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
        
        // Validate and sanitize input
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        
        if (!$user_id || !$event_id) {
            wp_send_json_error(['message' => 'Invalid user or event ID']);
        }
        
        // Check if user is checking out their own event
        if (get_current_user_id() !== $user_id && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        event_log_info('Event checkout process started', [
            'user_id' => $user_id,
            'event_id' => $event_id
        ]);
        
        // Process checkout
        $result = event_process_checkout($user_id, $event_id);
        
        if ($result['success']) {
            event_log_info('Event checkout completed successfully', [
                'user_id' => $user_id,
                'event_id' => $event_id
            ]);
            wp_send_json_success($result['data']);
        } else {
            event_log_error('Event checkout failed', [
                'user_id' => $user_id,
                'event_id' => $event_id,
                'error' => $result['error']
            ]);
            wp_send_json_error(['message' => $result['error']]);
        }
        
    } catch (Exception $e) {
        event_log_error('Event checkout exception', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        wp_send_json_error(['message' => 'An unexpected error occurred']);
    }
}

/**
 * Process event checkout
 */
function event_process_checkout($user_id, $event_id) {
    try {
        // Check if user has checked in
        $check_in_time = get_user_meta($user_id, 'event_' . $event_id . '_check_in_time', true);
        if (!$check_in_time) {
            throw new Exception('User has not checked in for this event');
        }
        
        // Check if already checked out
        $check_out_time = get_user_meta($user_id, 'event_' . $event_id . '_check_out_time', true);
        if ($check_out_time) {
            throw new Exception('User has already checked out of this event');
        }
        
        // Calculate attendance time
        $check_out_time = current_time('mysql');
        $check_in_timestamp = strtotime($check_in_time);
        $check_out_timestamp = strtotime($check_out_time);
        $total_seconds = $check_out_timestamp - $check_in_timestamp;
        $total_hours_attended = round($total_seconds / 3600, 2);
        
        // Get event duration
        $event_start = get_post_meta($event_id, '_EventStartDate', true);
        $event_end = get_post_meta($event_id, '_EventEndDate', true);
        $event_duration_hours = 0;
        
        if (!empty($event_start) && !empty($event_end)) {
            $start_time = strtotime($event_start);
            $end_time = strtotime($event_end);
            if ($end_time > $start_time) {
                $event_duration_hours = round(($end_time - $start_time) / 3600, 2);
            }
        }
        
        // Calculate CPD points
        $cpd_points = 0;
        if ($event_duration_hours > 0) {
            $attendance_percentage = ($total_hours_attended / $event_duration_hours) * 100;
            
            if ($attendance_percentage > 0 && $attendance_percentage <= 50) {
                $cpd_points = 0.5;
            } elseif ($attendance_percentage > 50) {
                $cpd_points = 1;
            }
        }
        
        // Update user meta
        update_user_meta($user_id, 'event_' . $event_id . '_check_out_time', $check_out_time);
        update_user_meta($user_id, 'event_' . $event_id . '_cpd_points', $cpd_points);
        update_user_meta($user_id, 'event_' . $event_id . '_attendance_hours', $total_hours_attended);
        
        // Update total CPD points
        $all_cpd_points = get_user_meta($user_id, 'total_cpd_points', true);
        if (!$all_cpd_points) {
            $all_cpd_points = 0;
        }
        $all_cpd_points += $cpd_points;
        update_user_meta($user_id, 'total_cpd_points', $all_cpd_points);
        
        // Clear cache
        event_clear_cache($event_id);
        
        return [
            'success' => true,
            'data' => [
                'message' => 'Checkout completed successfully',
                'cpd_points' => $cpd_points,
                'attendance_hours' => $total_hours_attended,
                'total_cpd_points' => $all_cpd_points
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Handle CPD points addition AJAX request
 */
function event_handle_add_cpd_points() {
    try {
        // Verify nonce
        if (!check_ajax_referer('cpd_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        // Validate and sanitize input
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $cpd_points = isset($_POST['cpd_points']) ? floatval($_POST['cpd_points']) : 0;
        
        if (!$user_id || !$event_id || $cpd_points < 0) {
            wp_send_json_error(['message' => 'Invalid data provided']);
        }
        
        event_log_info('CPD points addition started', [
            'user_id' => $user_id,
            'event_id' => $event_id,
            'cpd_points' => $cpd_points
        ]);
        
        // Process CPD points addition
        $result = event_process_cpd_points_addition($user_id, $event_id, $cpd_points);
        
        if ($result['success']) {
            event_log_info('CPD points addition completed successfully', [
                'user_id' => $user_id,
                'event_id' => $event_id,
                'cpd_points' => $cpd_points
            ]);
            wp_send_json_success($result['data']);
        } else {
            event_log_error('CPD points addition failed', [
                'user_id' => $user_id,
                'event_id' => $event_id,
                'cpd_points' => $cpd_points,
                'error' => $result['error']
            ]);
            wp_send_json_error(['message' => $result['error']]);
        }
        
    } catch (Exception $e) {
        event_log_error('CPD points addition exception', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        wp_send_json_error(['message' => 'An unexpected error occurred']);
    }
}

/**
 * Process CPD points addition
 */
function event_process_cpd_points_addition($user_id, $event_id, $cpd_points) {
    try {
        // Get user and event info
        $user_info = get_userdata($user_id);
        if (!$user_info) {
            throw new Exception('Invalid user');
        }
        
        $event_name = get_the_title($event_id);
        if (!$event_name) {
            throw new Exception('Invalid event');
        }
        
        // Update CPD points
        update_user_meta($user_id, 'event_' . $event_id . '_cpd_points', $cpd_points);
        
        // Send notifications
        $email_result = event_send_cpd_notifications($user_info, $event_id, $event_name, $cpd_points);
        
        // Clear cache
        event_clear_cache($event_id);
        
        return [
            'success' => true,
            'data' => [
                'message' => 'CPD points updated successfully',
                'cpd_points' => $cpd_points,
                'email_sent' => $email_result
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Send CPD notifications
 */
function event_send_cpd_notifications($user_info, $event_id, $event_name, $cpd_points) {
    try {
        $user_email = $user_info->user_email;
        $admin_email = get_option('admin_email');
        
        // User notification
        $user_subject = 'CPD Points Awarded - ' . $event_name;
        $user_message = event_get_email_template('user_cpd_points', [
            'user_name' => $user_info->display_name,
            'event_name' => $event_name,
            'cpd_points' => $cpd_points
        ]);
        
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        $user_sent = wp_mail($user_email, $user_subject, $user_message);
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        // Admin notification
        $admin_subject = 'CPD Points Updated - ' . $event_name;
        $admin_message = event_get_email_template('admin_cpd_points', [
            'user_name' => $user_info->display_name,
            'user_email' => $user_email,
            'event_name' => $event_name,
            'cpd_points' => $cpd_points
        ]);
        
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        $admin_sent = wp_mail($admin_email, $admin_subject, $admin_message);
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        return $user_sent && $admin_sent;
        
    } catch (Exception $e) {
        event_log_error('Failed to send CPD notifications', [
            'user_id' => $user_info->ID,
            'event_id' => $event_id,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Handle CPD PDF generation AJAX request
 */
function event_handle_generate_cpd_pdf() {
    try {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        event_log_info('CPD PDF generation started');
        
        // Generate PDF
        $result = event_generate_cpd_pdf();
        
        if ($result['success']) {
            event_log_info('CPD PDF generation completed successfully');
            wp_send_json_success($result['data']);
        } else {
            event_log_error('CPD PDF generation failed', [
                'error' => $result['error']
            ]);
            wp_send_json_error(['message' => $result['error']]);
        }
        
    } catch (Exception $e) {
        event_log_error('CPD PDF generation exception', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        wp_send_json_error(['message' => 'An unexpected error occurred']);
    }
}


