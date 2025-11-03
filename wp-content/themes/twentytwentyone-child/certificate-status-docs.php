/**
 * === CERTIFICATE STATUS MANAGEMENT SYSTEM ===
 *
 * This system provides a professional, centralized approach to managing certificate
 * renewal and recertification statuses in the user profile system.
 *
 * FEATURES:
 * - Consolidated status tracking using certificate numbers as primary keys
 * - Status hierarchy validation to prevent invalid transitions
 * - Comprehensive logging and error tracking
 * - Professional status display with consistent formatting
 * - Utility functions for common operations
 * - Backward compatibility with existing data
 *
 * STATUS HIERARCHY:
 * none (0) → submitted (1) → under_review/reviewing (2) → approved (3) → certificate_issued (4) → completed (5)
 *
 * USAGE EXAMPLES:
 * ============================================================================
 */

/**
 * EXAMPLE 1: Basic Status Update
 * ----------------------------------------------------------------------------
 *
 * // Update a certificate status to submitted
 * $success = update_certificate_status($user_id, 'CERT-001', 'submitted', [
 *     'submission_method' => 'online_form',
 *     'ip_address' => $_SERVER['REMOTE_ADDR']
 * ]);
 *
 * if ($success) {
 *     echo "Status updated successfully!";
 * }
 */

/**
 * EXAMPLE 2: Get Status Information
 * ----------------------------------------------------------------------------
 *
 * $status_info = get_certificate_status_info($user_id, 'CERT-001');
 *
 * if ($status_info) {
 *     echo "Current Status: " . $status_info['effective_status'] . "\n";
 *     echo "Submitted Date: " . $status_info['formatted_status_date'] . "\n";
 *     echo "Is Completed: " . ($status_info['is_completed'] ? 'Yes' : 'No') . "\n";
 * }
 */

/**
 * EXAMPLE 3: Generate Status Display HTML
 * ----------------------------------------------------------------------------
 *
 * $status_info = get_certificate_status_info($user_id, 'CERT-001');
 * $status_html = generate_status_display_html($status_info);
 * echo $status_html; // Outputs formatted status display
 */

/**
 * EXAMPLE 4: Check Renewal Eligibility
 * ----------------------------------------------------------------------------
 *
 * $cert_data = [
 *     'certificate_number' => 'CERT-001',
 *     'issue_date' => '2023-01-15',
 *     'expiry_date' => '2026-01-15'
 * ];
 *
 * if (is_certificate_eligible_for_renewal($cert_data)) {
 *     echo "Certificate is eligible for renewal";
 * } else {
 *     echo "Certificate is not eligible for renewal";
 * }
 */

/**
 * EXAMPLE 5: Batch Status Update (Admin Function)
 * ----------------------------------------------------------------------------
 *
 * $user_ids = [1, 2, 3];
 * $cert_numbers = ['CERT-001', 'CERT-002', 'CERT-003'];
 * $results = batch_update_certificate_status($user_ids, $cert_numbers, 'approved', [
 *     'approved_by' => 'admin_user',
 *     'approval_method' => 'batch_process'
 * ]);
 *
 * echo "Updated: " . $results['summary']['successful_count'] . " certificates\n";
 * echo "Failed: " . $results['summary']['failed_count'] . " certificates\n";
 */

/**
 * EXAMPLE 6: Get All User Certificate Statuses
 * ----------------------------------------------------------------------------
 *
 * $all_statuses = get_user_certificate_statuses($user_id);
 *
 * foreach ($all_statuses as $cert_number => $status_info) {
 *     echo "Certificate: {$cert_number}\n";
 *     echo "Status: " . $status_info['effective_status'] . "\n";
 *     echo "Completed: " . ($status_info['is_completed'] ? 'Yes' : 'No') . "\n";
 *     echo "---\n";
 * }
 */

/**
 * EXAMPLE 7: Clean Up Old Status Keys (Migration)
 * ----------------------------------------------------------------------------
 *
 * $cleaned = cleanup_old_status_keys($user_id, 'CERT-001');
 * echo "Cleaned up {$cleaned} old status keys\n";
 */

/**
 * INTEGRATION WITH EXISTING CODE:
 * ============================================================================
 *
 * The new system is designed to work seamlessly with your existing code.
 * The main display logic in user-profile.php has been updated to use the
 * new status system, but old status keys are still supported for backward
 * compatibility.
 *
 * FORM HANDLERS:
 * - Renewal form submissions now use the new status system
 * - AJAX handlers have been updated to use consistent status keys
 * - Status validation ensures data integrity
 *
 * DISPLAY LOGIC:
 * - Status display automatically uses the new consolidated system
 * - Buttons are hidden for completed processes (approved/issued)
 * - Clear status messages with dates and certificate information
 *
 * ERROR HANDLING:
 * - All status transitions are logged
 * - Invalid transitions are prevented and logged
 * - Comprehensive error messages for debugging
 */

/**
 * TESTING THE SYSTEM:
 * ============================================================================
 *
 * You can test the new system by calling the test function:
 *
 * // Add this to your theme's functions.php or a test page:
 * test_certificate_status_system();
 *
 * This will run a comprehensive test of all status management functions
 * and display results in your browser.
 */

/**
 * MIGRATION GUIDE:
 * ============================================================================
 *
 * If you have existing data using old status keys, you can migrate it:
 *
 * 1. Old keys: renewal_status_{cert_number}, renewal_submission_{cert_number}
 * 2. New keys: cert_status_{cert_number}, cert_submission_{cert_number}
 *
 * Migration steps:
 * 1. Run the test function to verify the new system works
 * 2. Gradually migrate existing data using cleanup_old_status_keys()
 * 3. Update any custom code that directly accesses old status keys
 * 4. Monitor error logs for any issues
 *
 * BACKWARD COMPATIBILITY:
 * - Old status keys are still read by the system
 * - New system prioritizes new keys over old ones
 * - Existing functionality remains intact
 */

/**
 * TROUBLESHOOTING:
 * ============================================================================
 *
 * COMMON ISSUES AND SOLUTIONS:
 *
 * 1. "Status not updating"
 *    - Check if user is logged in
 *    - Verify certificate number format
 *    - Check error logs for validation failures
 *
 * 2. "Buttons still showing for completed certificates"
 *    - Ensure status is set to 'approved', 'certificate_issued', or 'completed'
 *    - Check that status priority is working correctly
 *    - Verify the status display logic is using the new system
 *
 * 3. "Invalid status transition"
 *    - Status hierarchy prevents backward transitions
 *    - Check error logs for specific transition errors
 *    - Use validate_status_transition() to test transitions
 *
 * 4. "Status not displaying correctly"
 *    - Verify get_certificate_status_info() returns correct data
 *    - Check generate_status_display_html() output
 *    - Ensure status date formatting is correct
 */

/**
 * API REFERENCE:
 * ============================================================================
 */

/**
 * update_certificate_status($user_id, $cert_number, $new_status, $additional_data)
 *
 * Updates certificate status with validation and logging.
 *
 * @param int $user_id User ID
 * @param string $cert_number Certificate number
 * @param string $new_status New status value
 * @param array $additional_data Additional metadata (optional)
 * @return bool Success/failure
 */

/**
 * get_certificate_status_info($user_id, $cert_number)
 *
 * Gets comprehensive status information for a certificate.
 *
 * @param int $user_id User ID
 * @param string $cert_number Certificate number
 * @return array|false Status information or false on error
 */

/**
 * generate_status_display_html($status_info)
 *
 * Generates professional HTML display for status information.
 *
 * @param array $status_info Status information from get_certificate_status_info()
 * @return string HTML output
 */

/**
 * validate_status_transition($current_status, $new_status)
 *
 * Validates if a status transition is allowed.
 *
 * @param string $current_status Current status
 * @param string $new_status New status
 * @return bool True if transition is valid
 */

/**
 * is_certificate_eligible_for_renewal($cert_data)
 *
 * Checks if a certificate is eligible for renewal.
 *
 * @param array $cert_data Certificate data with issue_date and expiry_date
 * @return bool True if eligible
 */

/**
 * get_user_certificate_statuses($user_id)
 *
 * Gets all certificate statuses for a user.
 *
 * @param int $user_id User ID
 * @return array Array of certificate status information
 */

/**
 * cleanup_old_status_keys($user_id, $cert_number)
 *
 * Cleans up old status keys (migration utility).
 *
 * @param int $user_id User ID
 * @param string $cert_number Certificate number
 * @return int Number of keys cleaned up
 */

/**
 * batch_update_certificate_status($user_ids, $cert_numbers, $new_status, $additional_data)
 *
 * Batch updates multiple certificate statuses.
 *
 * @param array $user_ids Array of user IDs
 * @param array $cert_numbers Array of certificate numbers
 * @param string $new_status New status for all certificates
 * @param array $additional_data Additional metadata (optional)
 * @return array Results summary
 */

/**
 * test_certificate_status_system()
 *
 * Runs comprehensive tests of the status management system.
 *
 * @return void Outputs test results
 */

/**
 * ============================================================================
 * END OF DOCUMENTATION
 * ============================================================================
 */
