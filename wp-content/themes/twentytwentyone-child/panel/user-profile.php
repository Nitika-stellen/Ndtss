<?php
/**
 * User Profile Shortcode
 * Displays user profile with tabs for Basic Profile, Membership, Event, SGNDT Certificates, and Final Certificates.
 * Includes separate retest buttons for each failed subject and filter-based interface for final certificates.
 *
 * @package SGNDT
 * @version 1.0.6
 */

// Include certificate lifecycle manager
require_once get_stylesheet_directory() . '/certificate-lifecycle-manager.php';

// Constants for better maintainability
define('USER_PROFILE_CACHE_DURATION', 15 * MINUTE_IN_SECONDS);
define('RETEST_ADDITIONAL_FEE', 150);
// Certificate lifecycle constants as per user requirements
define('INITIAL_CERT_VALIDITY_YEARS', 5); // Initial certificate valid for 5 years
define('RENEWAL_CERT_VALIDITY_YEARS', 5); // Renewal valid for 5 years
define('RECERTIFICATION_CERT_VALIDITY_YEARS', 10); // Recertification valid for 10 years
define('RENEWAL_ELIGIBLE_MONTHS', 6); // Renewal window opens 6 months before expiry
define('RENEWAL_DEADLINE_MONTHS', 12); // Grace period after expiry for renewal
define('RECERTIFICATION_CYCLE_YEARS', 9); // Recertification after 9 years from initial issue

/**
 * Helper function to get certificate status display with enhanced clarity
 */
function get_certificate_status_display($effective_status, $formatted_status_date, $cert_issued_number = '', $formatted_cert_date = '', $renewal_method = '') {
    $display_text = get_renewal_status_display_text($effective_status);
    $method_badge = !empty($renewal_method) ? '<span class="renewal-method-badge">' . strtoupper($renewal_method) . '</span>' : '';
    echo $effective_status;
    switch ($effective_status) {
        case 'submitted':
        case 'under_review':
        case 'reviewing':
            // Determine status text based on renewal method
            $renewal_method_upper = !empty($renewal_method) ? strtoupper($renewal_method) : '';
            if ($renewal_method_upper === 'RECERT') {
                // Recertification application
                $status_text = 'Applied for recert';
            } elseif ($renewal_method_upper === 'CPD') {
                // CPD renewal application
                $status_text = 'Applied for renewal CPD';
            } else {
                // Exam-based renewal (default)
                $status_text = 'Applied for Renewal';
            }
            
            return '<div class="renewal-status-wrapper status-applied">' .
                   '<div class="status-header">' .
                   '<span class="status-icon">📝</span>' .
                   '<span class="status-text">' . esc_html($status_text) . '</span>' .
                   $method_badge .
                   '</div>' .
                   '<div class="status-details">' .
                   '<small>Submitted: ' . esc_html($formatted_status_date) . '</small><br>' .
                   '<small class="status-note">Your application is being reviewed</small>' .
                   '</div>' .
                   '</div>';
        case 'approved':
            return '<div class="renewal-status-wrapper status-approved">' .
                   '<div class="status-header">' .
                   '<span class="status-icon">✅</span>' .
                   '<span class="status-text">Approved</span>' .
                   $method_badge .
                   '</div>' .
                   '<div class="status-details">' .
                   '<small>Approved: ' . esc_html($formatted_status_date) . '</small><br>' .
                   '<small class="status-note">Certificate will be issued soon</small>' .
                   '</div>' .
                   '</div>';
        case 'rejected':
            return '<div class="renewal-status-wrapper status-rejected">' .
                   '<div class="status-header">' .
                   '<span class="status-icon">❌</span>' .
                   '<span class="status-text">Application Rejected</span>' .
                   $method_badge .
                   '</div>' .
                   '<div class="status-details">' .
                   '<small>Rejected: ' . esc_html($formatted_status_date) . '</small><br>' .
                   '<small class="status-note">Your CPD renewal was rejected. Please try renewal by exam.</small>' .
                   '</div>' .
                   '</div>';
        case 'renewed':
            // Check if this is a recertification certificate (-04) or renewal certificate (-03)
            $is_recert_cert = !empty($cert_issued_number) && preg_match('/-04$/', $cert_issued_number);
            $is_renewal_cert = !empty($cert_issued_number) && preg_match('/-03$/', $cert_issued_number);
            
            if ($is_recert_cert) {
                $status_text = 'Successfully Recertified';
                $status_icon = '🎓';
            } elseif ($is_renewal_cert) {
                $status_text = 'Successfully Renewed';
                $status_icon = '🔄';
            } else {
                // For mutual recognition certificates (-02) or any other case
                $status_text = 'Certificate Issued';
                $status_icon = '📜';
            }
            
            return '<div class="renewal-status-wrapper status-renewed">' .
                   '<div class="status-header">' .
                   '<span class="status-icon">' . $status_icon . '</span>' .
                   '<span class="status-text">' . esc_html($status_text) . '</span>' .
                   $method_badge .
                   '</div>' .
                   '<div class="status-details">' .
                   '<small>New Certificate: ' . esc_html($cert_issued_number ?: 'N/A') . '</small><br>' .
                   '<small>Issued: ' . esc_html($formatted_cert_date) . '</small>' .
                   '</div>' .
                   '</div>';
        case 'certificate_issued':
        case 'completed':
            return '<div class="renewal-status-wrapper status-issued">' .
                   '<div class="status-header">' .
                   '<span class="status-icon">🎓</span>' .
                   '<span class="status-text">Certificate Issued</span>' .
                   $method_badge .
                   '</div>' .
                   '<div class="status-details">' .
                   '<small>Certificate: ' . esc_html($cert_issued_number ?: 'N/A') . '</small><br>' .
                   '<small>Issued: ' . esc_html($formatted_cert_date) . '</small>' .
                   '</div>' .
                   '</div>';
        default:
            return '<div class="renewal-status-wrapper status-pending">' .
                   '<div class="status-header">' .
                   '<span class="status-icon">⏳</span>' .
                   '<span class="status-text">' . esc_html($display_text) . '</span>' .
                   '</div>' .
                   '<div class="status-details">' .
                   '<small>Status: ' . esc_html($formatted_status_date) . '</small>' .
                   '</div>' .
                   '</div>';
    }
}

/**
 * Helper function to determine if certificate is renewed or recertified
 * Suffix meanings: 
 * -01 = Initial
 * -02 = Mutual Recognition (treated like initial for renewal/recertification)
 * -03 = Renewal
 * -04 = Recertification
 */
function is_renewed_certificate($certificate_number) {
    // Check if certificate has renewal (-03) or recertification (-04) suffix
    // Note: -02 is treated as initial for renewal/recertification purposes
    return preg_match('/-(03|04)$/', $certificate_number);
}

/**
 * Helper function to get renewal method from status data
 */
function get_renewal_method_from_status($user_id, $cert_id, $cert_number) {
    // Check ID-based status first
    $cert_status_key = 'cert_status_id_' . $cert_id;
    $submission_method = get_user_meta($user_id, $cert_status_key . '_submission_method', true);
    
    if (!empty($submission_method)) {
        return $submission_method === 'cpd_form' ? 'CPD' : 'EXAM';
    }
    
    // Check legacy certificate number-based status
    $cert_status_key_legacy = 'cert_status_' . $cert_number;
    $submission_method_legacy = get_user_meta($user_id, $cert_status_key_legacy . '_submission_method', true);
    
    if (!empty($submission_method_legacy)) {
        return $submission_method_legacy === 'cpd_form' ? 'CPD' : 'EXAM';
    }
    
    return '';
}


/**
 * Calculate the combined subject price for a certificate based on stored exam data.
 *
 * @param string $cert_number Certificate number (e.g. A010101-01).
 * @param string $level       Certification level (e.g. Level 2).
 *
 * @return array{total: float, subjects: array<string,float>} Total and per-subject prices.
 */
function calculate_certificate_exam_price($cert_number, $level) {
    static $cache = [];

    $cert_number = trim((string) $cert_number);
    $level = trim((string) $level);

    if ($cert_number === '' || $level === '') {
        return ['total' => 0.0, 'subjects' => []];
    }

    $cache_key = md5($cert_number . '|' . $level);
    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    global $wpdb;

    $table_certifications = $wpdb->prefix . 'sgndt_certifications';
    $table_subject_marks   = $wpdb->prefix . 'sgndt_subject_marks';
    $table_subject_price   = $wpdb->prefix . 'sgndt_exam_prices';

    $subject_prices = [];
    $total_price    = 0.0;

    // Attempt to locate the most recent certification record for the given certificate number.
    $certification_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT certification_id FROM {$table_certifications}
             WHERE cert_number = %s
             ORDER BY issue_date DESC
             LIMIT 1",
            $cert_number
        )
    );

    if (!empty($certification_id)) {
        $subjects = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT subject_name
                 FROM {$table_subject_marks}
                 WHERE certification_id = %d
                 AND subject_name <> ''",
                $certification_id
            )
        );
    } else {
        $subjects = [];
    }

    // Fallback to all subjects defined for the level when specific marks are unavailable.
    if (empty($subjects)) {
        $subjects = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT subject
                 FROM {$table_subject_price}
                 WHERE level = %s
                 AND subject <> ''",
                $level
            )
        );
    }

    if (!empty($subjects)) {
        foreach ($subjects as $subject) {
            $price = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT price FROM {$table_subject_price}
                     WHERE level = %s AND subject = %s",
                    $level,
                    $subject
                )
            );

            if (is_numeric($price)) {
                $price_value = (float) $price;
                $subject_prices[$subject] = $price_value;
                $total_price += $price_value;
            }
        }
    }

    $result = [
        'total'    => $total_price,
        'subjects' => $subject_prices,
    ];

    $cache[$cache_key] = $result;

    return $result;
}

/**
 * Build query arguments for exam price data used in renewal/recertification flows.
 *
 * @param array $cert Certificate data array containing at least certificate_number and level.
 *
 * @return array<string,string> URL query arguments for price information.
 */
function build_exam_price_query_args(array $cert) {
    if (empty($cert['certificate_number']) || empty($cert['level'])) {
        return [];
    }

    $price_data = calculate_certificate_exam_price($cert['certificate_number'], $cert['level']);

    if (empty($price_data['total']) || $price_data['total'] <= 0) {
        return [];
    }

    $query_args = [
        'exam_price' => number_format((float) $price_data['total'], 2, '.', ''),
    ];

    if (!empty($price_data['subjects'])) {
        $breakdown_json = wp_json_encode($price_data['subjects']);
        if ($breakdown_json !== false) {
            $query_args['exam_price_breakdown'] = $breakdown_json;
        }
    }

    return $query_args;
}

/**
 * Helper function to get certificate validity status
 */
// function get_certificate_validity_status($cert) {
//     if (empty($cert['expiry_date'])) {
//         return ['status' => 'unknown', 'message' => 'Expiry date not available'];
//     }
    
//     $current_date = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
//     $expiry_date = new DateTime($cert['expiry_date'], new DateTimeZone('Asia/Kolkata'));
//     $renewal_eligible_date = clone $expiry_date;
//     $renewal_eligible_date->modify('-' . RENEWAL_ELIGIBLE_MONTHS . ' months');
//     $renewal_deadline_date = clone $expiry_date;
//     $renewal_deadline_date->modify('+' . RENEWAL_DEADLINE_MONTHS . ' months');
    
//     if ($current_date > $renewal_deadline_date) {
//         return ['status' => 'expired', 'message' => 'Certificate has expired'];
//     } elseif ($current_date >= $renewal_eligible_date) {
//         return ['status' => 'eligible', 'message' => 'Eligible for renewal'];
//     } else {
//         return ['status' => 'valid', 'message' => 'Certificate is valid'];
//     }
// }

/**
 * Check if a renewed/recertified certificate exists for the given certificate
 * Note: -02 certificates are treated as initial certificates for renewal/recertification purposes
 */
function has_renewed_certificate($cert, $user_id) {
    global $wpdb;
    
    // Extract base certificate number (remove any existing suffix like -01, -02, -03, -04)
    $base_cert_number = preg_replace('/-[0-9]+$/', '', $cert['certificate_number']);
    
    // Check if a renewed certificate (-03) or recertified certificate (-04) exists
    // Note: -02 certificates are treated as initial certificates, so we don't check for them here
    $renewal_cert_number = $base_cert_number . '-03';
    $recert_cert_number = $base_cert_number . '-04';
    
    $renewed_cert = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sgndt_final_certifications
         WHERE user_id = %d 
         AND (certificate_number = %s OR certificate_number = %s)
         AND method = %s
         AND level = %s
         AND sector = %s
         AND scope = %s
         AND status = 'issued'",
        $user_id,
        $renewal_cert_number,
        $recert_cert_number,
        $cert['method'],
        $cert['level'],
        $cert['sector'],
        $cert['scope']
    ));
    
    return $renewed_cert > 0;
}

function get_certificate_validity_status($cert) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    
    // IMPORTANT: Check recertification eligibility FIRST (before checking if renewed)
    // If eligible for recertification (9+ years from initial), show "Eligible for recertification"
    // even if certificate has been renewed
    if (!empty($cert['issue_date'])) {
        $current_date = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $issue_date = new DateTime($cert['issue_date'], new DateTimeZone('Asia/Kolkata'));
        
        // Get the INITIAL certificate to check recertification eligibility
        // Extract base certificate number (remove any suffix)
        $base_cert_number = preg_replace('/-0[1-4]$/', '', $cert['certificate_number']);
        
        // Find the initial certificate (suffix -01, -02, or no suffix)
        // -01 = Initial, -02 = Mutual Recognition (treated as initial for renewal/recertification)
        $initial_cert = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications
             WHERE user_id = %d 
             AND (
                 certificate_number = %s 
                 OR certificate_number = %s
                 OR (certificate_number NOT LIKE '%%-02' AND certificate_number NOT LIKE '%%-03' AND certificate_number LIKE %s)
             )
             AND method = %s
             AND level = %s
             AND sector = %s
             AND scope = %s
             ORDER BY issue_date ASC
             LIMIT 1",
            $user_id,
            $base_cert_number,
            $base_cert_number . '-01',
            $base_cert_number . '%',
            $cert['method'],
            $cert['level'],
            $cert['sector'],
            $cert['scope']
        ), ARRAY_A);
        
        // Use initial certificate issue date for recertification eligibility check
        $cert_to_check = $initial_cert ?: $cert;
        $initial_issue_date = new DateTime($cert_to_check['issue_date'], new DateTimeZone('Asia/Kolkata'));
        $interval = $current_date->diff($initial_issue_date);
        $years_since_initial_issue = $interval->y + ($interval->m / 12);
        
        // Check if recertification is eligible (9+ years from INITIAL certificate issue)
        if ($years_since_initial_issue >= RECERTIFICATION_CYCLE_YEARS) {
            // Check if this is the initial certificate (not renewal or recertification)
            $is_initial_cert = ($cert_to_check['certificate_number'] === $cert['certificate_number']);
            
            if ($is_initial_cert) {
                // Check if a recertification certificate (-03) already exists
                $recert_cert_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}sgndt_final_certifications
                     WHERE user_id = %d 
                     AND certificate_number = %s
                     AND method = %s
                     AND level = %s
                     AND sector = %s
                     AND scope = %s
                     AND status = 'issued'",
                    $user_id,
                    $base_cert_number . '-03',
                    $cert['method'],
                    $cert['level'],
                    $cert['sector'],
                    $cert['scope']
                ));
                
                // Only show "Eligible for recertification" if recertification certificate doesn't exist yet
                if ($recert_cert_exists == 0) {
                    return ['status' => 'eligible', 'message' => 'Eligible for recertification'];
                } else {
                    // Recertification certificate exists, show "Certificate has been recertified"
                    return ['status' => 'renewed', 'message' => 'Certificate has been recertified'];
                }
            }
        }
    }
    
    // Then check if this certificate has been renewed/recertified
    if (has_renewed_certificate($cert, $user_id)) {
        // Check if it's recertified (-04) or renewed (-03)
        $base_cert_number = preg_replace('/-[0-9]+$/', '', $cert['certificate_number']);
        $recert_cert = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sgndt_final_certifications
             WHERE user_id = %d AND certificate_number = %s AND status = 'issued'",
            $user_id, $base_cert_number . '-04'
        ));
        
        if ($recert_cert > 0) {
            return ['status' => 'renewed', 'message' => 'Certificate has been recertified'];
        } else {
            // Check if it's a renewal (-03)
            $renewal_cert = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sgndt_final_certifications
                 WHERE user_id = %d AND certificate_number = %s AND status = 'issued'",
                $user_id, $base_cert_number . '-03'
            ));
            
            if ($renewal_cert > 0) {
                return ['status' => 'renewed', 'message' => 'Certificate has been renewed'];
            } else {
                // For mutual recognition (-02) or other cases
                return ['status' => 'valid', 'message' => 'Certificate is valid'];
            }
        }
    }
    
    if (empty($cert['expiry_date'])) {
        return ['status' => 'unknown', 'message' => 'Expiry date not available'];
    }
    
    $current_date = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $expiry_date = new DateTime($cert['expiry_date'], new DateTimeZone('Asia/Kolkata'));
    $renewal_eligible_date = clone $expiry_date;
    $renewal_eligible_date->modify('-' . RENEWAL_ELIGIBLE_MONTHS . ' months');
    $renewal_deadline_date = clone $expiry_date;
    $renewal_deadline_date->modify('+' . RENEWAL_DEADLINE_MONTHS . ' months');
    
    if ($current_date > $renewal_deadline_date) {
        return ['status' => 'expired', 'message' => 'Certificate has expired'];
    } elseif ($current_date >= $renewal_eligible_date) {
        // Check if this is renewal or recertification based on issue date
        if (!empty($cert['issue_date'])) {
            $issue_date = new DateTime($cert['issue_date'], new DateTimeZone('Asia/Kolkata'));
            $interval = $current_date->diff($issue_date);
            $years_since_issue = $interval->y + ($interval->m / 12);
            
            // Determine if this should be recertification (after 9+ years from initial issue)
            if ($years_since_issue >= RECERTIFICATION_CYCLE_YEARS) {
                // Check if a recertification certificate (-03) already exists
                $base_cert_number = preg_replace('/-[0-9]+$/', '', $cert['certificate_number']);
                $recert_cert_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}sgndt_final_certifications
                     WHERE user_id = %d 
                     AND certificate_number = %s
                     AND method = %s
                     AND level = %s
                     AND sector = %s
                     AND scope = %s
                     AND status = 'issued'",
                    $user_id,
                    $base_cert_number . '-03',
                    $cert['method'],
                    $cert['level'],
                    $cert['sector'],
                    $cert['scope']
                ));
                
                // Only show "Eligible for recertification" if recertification certificate doesn't exist yet
                if ($recert_cert_exists == 0) {
                    return ['status' => 'eligible', 'message' => 'Eligible for recertification'];
                } else {
                    // Recertification certificate exists, show "Certificate has been recertified"
                    return ['status' => 'renewed', 'message' => 'Certificate has been recertified'];
                }
            } else {
                return ['status' => 'eligible', 'message' => 'Eligible for renewal'];
            }
        } else {
            // Fallback if no issue date
            return ['status' => 'eligible', 'message' => 'Eligible for renewal'];
        }
    } else {
        return ['status' => 'valid', 'message' => 'Certificate is valid'];
    }
}

/**
 * Helper function to clear user profile cache
 */
function clear_user_profile_cache($user_id) {
    delete_transient("user_certifications_{$user_id}");
    delete_transient("user_final_certifications_{$user_id}");
}

/**
 * Helper function to get user-friendly status display text
 */
function get_renewal_status_display_text($status) {
    $status_map = [
        'submitted' => 'Applied for Renewal',
        'under_review' => 'Under Review',
        'reviewing' => 'Under Review',
        'approved' => 'Renewal Approved',
        'rejected' => 'Application Rejected',
        'certificate_issued' => 'Certificate Issued',
        'renewed' => 'Renewed',
        'completed' => 'Completed'
    ];
    
    return isset($status_map[$status]) ? $status_map[$status] : ucfirst(str_replace('_', ' ', $status));
}

/**
 * Helper function to format event duration
 */
function format_event_duration($start_time, $end_time) {
    if (empty($start_time) || empty($end_time)) {
        return 'N/A';
    }
    
    $start_timestamp = strtotime($start_time);
    $end_timestamp = strtotime($end_time);
    
    if ($start_timestamp === false || $end_timestamp === false || $end_timestamp <= $start_timestamp) {
        return 'Invalid duration';
    }
    
    $duration_seconds = $end_timestamp - $start_timestamp;
    $hours = floor($duration_seconds / 3600);
    $minutes = floor(($duration_seconds % 3600) / 60);
    
    if ($hours > 0) {
        return $hours . ' hr' . ($hours > 1 ? 's' : '') . ' ' . $minutes . ' min';
    } else {
        return $minutes . ' minutes';
    }
}

/**
 * Helper function to format attendance hours
 */
function format_attendance_hours($total_hours_attended) {
    if (empty($total_hours_attended) || !is_numeric($total_hours_attended)) {
        return 'N/A';
    }
    
    if ($total_hours_attended < 1) {
        $total_minutes_attended = round($total_hours_attended * 60);
        return $total_minutes_attended . ' minutes';
    } else {
        $hours_attended = floor($total_hours_attended);
        $minutes_attended = round(($total_hours_attended - $hours_attended) * 60);
        return $hours_attended . ' hr' . ($hours_attended > 1 ? 's' : '') . ' ' . $minutes_attended . ' min';
    }
}

/**
 * Helper function to get certificate action buttons based on proper lifecycle logic
 */
function get_certificate_action_buttons($cert, $current_date, $renewal_url, $recertification_url, $full_exam_url) {
    if (empty($cert['issue_date']) || empty($cert['expiry_date'])) {
        return '-';
    }

    $user_id = get_current_user_id();

    // Check if this is a renewal certificate (-02) - should NOT be eligible for renewal again
    if (preg_match('/-02$/', $cert['certificate_number'])) {
        // Renewed certificates cannot be renewed again - they need recertification
        // But recertification button should appear on ORIGINAL certificate, not renewal
        return '-';
    }

    // Check if this is a recertification certificate (-03) - no further actions
    if (preg_match('/-03$/', $cert['certificate_number'])) {
        return '-';  // Recertification certificate is final
    }

    try {
        global $wpdb;
        
        // Get the INITIAL certificate to check recertification eligibility
        // Extract base certificate number (remove any suffix)
        $base_cert_number = preg_replace('/-0[123]$/', '', $cert['certificate_number']);
        
        // Find the initial certificate (no suffix or -01)
        $initial_cert = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications
             WHERE user_id = %d 
             AND (
                 certificate_number = %s 
                 OR certificate_number = %s
                 OR (certificate_number NOT LIKE '%%-02' AND certificate_number NOT LIKE '%%-03' AND certificate_number LIKE %s)
             )
             AND method = %s
             AND level = %s
             AND sector = %s
             AND scope = %s
             ORDER BY issue_date ASC
             LIMIT 1",
            $user_id,
            $base_cert_number,
            $base_cert_number . '-01',
            $base_cert_number . '%',
            $cert['method'],
            $cert['level'],
            $cert['sector'],
            $cert['scope']
        ), ARRAY_A);

        // If we can't find initial certificate, use current certificate
        $cert_to_check = $initial_cert ?: $cert;
        
        $issue_datetime = new DateTime($cert_to_check['issue_date'], new DateTimeZone('Asia/Kolkata'));
        $expiry_datetime = new DateTime($cert['expiry_date'], new DateTimeZone('Asia/Kolkata'));

        // Calculate renewal window (6 months before expiry)
        $renewal_eligible_date = clone $expiry_datetime;
        $renewal_eligible_date->modify('-' . RENEWAL_ELIGIBLE_MONTHS . ' months');

        // Calculate grace period end (12 months after expiry for renewal)
        $renewal_deadline_date = clone $expiry_datetime;
        $renewal_deadline_date->modify('+' . RENEWAL_DEADLINE_MONTHS . ' months');

        // Calculate years since INITIAL certificate issue (for recertification eligibility)
        $interval = $current_date->diff($issue_datetime);
        $years_since_initial_issue = $interval->y + ($interval->m / 12);

        // Check if recertification is eligible (9+ years from INITIAL certificate issue)
        // Recertification button should ONLY appear on ORIGINAL/INITIAL certificate
        // IMPORTANT: Even if certificate has been renewed, recertification button should still appear if 9+ years have passed
        $is_recertification_eligible = $years_since_initial_issue >= RECERTIFICATION_CYCLE_YEARS;
        $is_initial_cert = ($cert_to_check['certificate_number'] === $cert['certificate_number']);
        $exam_price_query_args = build_exam_price_query_args($cert);
        
        // Check if a recertification certificate (-03) already exists
        $recert_cert_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sgndt_final_certifications
             WHERE user_id = %d 
             AND certificate_number = %s
             AND method = %s
             AND level = %s
             AND sector = %s
             AND scope = %s
             AND status = 'issued'",
            $user_id,
            $base_cert_number . '-03',
            $cert['method'],
            $cert['level'],
            $cert['sector'],
            $cert['scope']
        ));
        
        // Show recertification button ONLY on initial/original certificate when 9+ years have passed
        // BUT NOT if a recertification certificate (-03) already exists
        // IMPORTANT: Check recertification eligibility BEFORE checking if expired
        // If eligible for recertification, show button regardless of expiry status
        if ($is_recertification_eligible && $is_initial_cert && $recert_cert_exists == 0) {
            return '<div class="action-cell-wrapper">
                <div class="action-buttons recertification-buttons">
                    <a href="' . esc_url(add_query_arg(array_merge([
                        'cert_id' => $cert['final_certification_id'],
                        'cert_number' => $cert['certificate_number'],
                        'method' => $cert['method'],
                        'level' => $cert['level'],
                        'sector' => $cert['sector'],
                        'scope' => $cert['scope'],
                        'exam_entry_id' => $cert['exam_entry_id'],
                        'marks_entry_id' => $cert['marks_entry_id'],
                        'type' => 'recertification'
                    ], $exam_price_query_args), $recertification_url)) . '" class="action-button recertification-btn">Recertification</a>
                </div>
            </div>';
        } elseif ($is_recertification_eligible && $is_initial_cert && $recert_cert_exists > 0) {
            return '-'; // Hide button if recertification certificate already exists
        }

        // Check if certificate is expired (only if NOT eligible for recertification)
        // If eligible for recertification, we already showed the button above
        if (!$is_recertification_eligible && $current_date > $renewal_deadline_date) {
            return '<span class="status-expired">Expired</span>';
        }

        // Check if this certificate has already been renewed/recertified - block renewal button if so
        if (has_renewed_certificate($cert, $user_id)) {
            return '-';  // No renewal button if certificate has been renewed/recertified
        }

        // Show renewal button ONLY if:
        // 1. Not in recertification cycle (less than 9 years from initial)
        // 2. In renewal window (6 months before expiry to 12 months after expiry)
        // 3. This is the initial/original certificate (not renewal)
        // 4. Certificate has NOT been renewed yet
        if (!$is_recertification_eligible && $is_initial_cert && 
            $current_date >= $renewal_eligible_date && $current_date <= $renewal_deadline_date) {
            return '<div class="action-cell-wrapper">
                <div class="action-buttons renewal-buttons">
                    <a href="' . esc_url(add_query_arg(array_merge([
                        'cert_id' => $cert['final_certification_id'],
                        'cert_number' => $cert['certificate_number'],
                        'method' => $cert['method'],
                        'level' => $cert['level'],
                        'sector' => $cert['sector'],
                        'scope' => $cert['scope'],
                        'exam_entry_id' => $cert['exam_entry_id'],
                        'marks_entry_id' => $cert['marks_entry_id'],
                        'type' => 'renewal'
                    ], $exam_price_query_args), $renewal_url)) . '" class="action-button renewal-btn">Renew</a>
                </div>
            </div>';
        }

    } catch (Exception $e) {
        error_log("Error processing dates for certificate {$cert['certificate_number']}: " . $e->getMessage());
        return '<span class="status-error">Error</span>';
    }

    return '-';
}

/**
 * Check if user is a certificate holder (has at least one issued final certificate)
 *
 * @param int $user_id User ID
 * @return bool True if user has issued certificate(s)
 */
function is_certificate_holder($user_id) {
    global $wpdb;
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sgndt_final_certifications 
         WHERE user_id = %d AND status = 'issued'",
        $user_id
    ));
    return $count > 0;
}

/**
 * Check if user is a member (has membership roles)
 *
 * @param int $user_id User ID
 * @return bool True if user has membership role
 */
function is_member($user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    $membership_roles = ['member', 'individual_member', 'corporate_member'];
    $user_roles = $user->roles;
    
    foreach ($membership_roles as $role) {
        if (in_array($role, $user_roles)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if user has access to CPD and Final Certificates tabs
 * Access granted to certificate holders or members
 *
 * @param int $user_id User ID
 * @return bool True if user has access
 */
function has_cpd_certificate_access($user_id) {
    return is_certificate_holder($user_id) || is_member($user_id);
}

function user_profile_shortcode() {
    global $wpdb;
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view your profile.</p>';
    }

    // Enqueue required scripts and styles for user profile
    wp_enqueue_script('jquery');
    wp_enqueue_script(
        'profile-ajax',
        get_stylesheet_directory_uri() . '/js/profile-ajax.js',
        array('jquery'),
        filemtime(get_stylesheet_directory() . '/js/profile-ajax.js'),
        true
    );
    
    // Enqueue external CSS file for better performance
    wp_enqueue_style(
        'user-profile-styles',
        get_stylesheet_directory_uri() . '/css/user-profile.css',
        array(),
        filemtime(get_stylesheet_directory() . '/css/user-profile.css')
    );
    
    // Localize script with AJAX URL
    wp_localize_script('profile-ajax', 'profileAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('profile_ajax_nonce')
    ));
    
    // Enqueue SweetAlert2 for user notifications
    wp_enqueue_script(
        'sweetalert2-profile',
        get_stylesheet_directory_uri() . '/js/sweetalert2.all.min.js',
        array('jquery'),
        '11.0.0',
        true
    );

    $current_user = wp_get_current_user();

    ob_start();
    ?>
    <div class="user-profile-header">
				<?php
				$current_user = wp_get_current_user();
				$photo_url = get_user_meta($current_user->ID, 'custom_profile_photo', true);
				if (!empty($photo_url)) {
					echo '<img src="' . esc_url($photo_url) . '" alt="Profile Photo" style="max-width:150px; border-radius: 8px;">';
				} else {
					echo '<div style="width:150px; height:150px; background:#ccc; display:flex; align-items:center; justify-content:center; border-radius:8px;">No Photo</div>';
				}
				?>
				<div class="user-profile-header-info">
                    <h2><?php echo esc_html($current_user->display_name); ?>'s Profile</h2>
				<p class="user-profile-email"><?php echo esc_html($current_user->user_email); ?></p>
                </div>
			</div>	
    <div class="user-profile-container">
        <div class="user-profile-tabs" role="tablist">
            <ul>
                <li><a href="#basic-profile-section" class="tab-link active-tab" role="tab" aria-selected="true" aria-controls="basic-profile-section">Basic Profile</a></li>
                <li><a href="#membership-section" class="tab-link" role="tab" aria-selected="false" aria-controls="membership-section">Membership</a></li>
                <li><a href="#event-participation-section" class="tab-link" role="tab" aria-selected="false" aria-controls="event-participation-section">Event</a></li>
                <li><a href="#certificate-section" class="tab-link" role="tab" aria-selected="false" aria-controls="certificate-section">SGNDT Certificates</a></li>
                <?php if (has_cpd_certificate_access($current_user->ID)): ?>
                <li><a href="#final-certificate-section" class="tab-link" role="tab" aria-selected="false" aria-controls="final-certificate-section">Final Certificates</a></li>
                <li><a href="#cpd-management-section" class="tab-link" role="tab" aria-selected="false" aria-controls="cpd-management-section">CPD Management</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="user-profile-content">		

            <!-- Basic Profile Section (unchanged) -->
            <div id="basic-profile-section" class="user-profile-tab-content" role="tabpanel" aria-labelledby="basic-profile-tab">
                <div class="main-profile">
                    <h3>Basic Profile</h3>
				
                    <p><strong>First Name:</strong> <?php echo esc_attr($current_user->first_name); ?></p>
                    <p><strong>Last Name:</strong> <?php echo esc_attr($current_user->last_name); ?></p>
                    <p><strong>Email:</strong> <?php echo esc_attr($current_user->user_email); ?></p>
                </div>
                <?php echo do_shortcode('[user_address_section]'); ?>
                <div class="upload-ce-section">
                    <?php echo get_gravity_forms_certificates($current_user->ID); ?>
                </div>
            </div>

            <!-- Membership Section (unchanged) -->
            <div id="membership-section" class="user-profile-tab-content" style="display:none;" role="tabpanel" aria-labelledby="membership-tab">
                <h3 class="section-title">Membership</h3>
                <?php
                $membership_status = get_user_meta($current_user->ID, 'membership_approval_status', true);
                $membership_type = get_user_meta($current_user->ID, 'membership_type', true);
                $approval_date = get_user_meta($current_user->ID, 'membership_approval_date', true);
                $membership_entry = get_user_meta($current_user->ID, 'ind_member_form_entry', true);

                $membership_labels = [
                    'individual' => 'Individual Membership',
                    'corporate' => 'Corporate Membership'
                ];

                if ($membership_status || $membership_type) {
                    echo '<p>You have applied for <span class="highlight-text">' . esc_html($membership_labels[$membership_type] ?? 'Unknown Membership') . '</span></p>';
                    echo '<p>Membership Status: <span class="status-text status-' . esc_html($membership_status) . '">' . esc_html($membership_status) . '</span></p>';

                    if ($membership_status === 'pending') {
                        echo '<p>Your membership application is pending approval.</p>';
                    }
                } else {
                    echo '<p>You have not applied for any membership.</p>';
                }

                if ($membership_entry && is_numeric($membership_entry) && $membership_entry > 0) {
                    $entry = GFAPI::get_entry(absint($membership_entry));
                    if (is_wp_error($entry) || empty($entry)) {
                        echo '<p>Error retrieving membership data.</p>';
                    } else {
                        $form_id = isset($entry['form_id']) ? $entry['form_id'] : '';
                      //  $membership_types = '';

                        // if ($form_id == 5 && isset($entry[27])) {
                        //     $membership_types = explode('|', $entry[27])[0];
                        // } elseif ($form_id == 4 && isset($entry[31])) {
                        //     $membership_types = explode('|', $entry[31])[0];
                        // }

                        // if ($membership_types === "Annual") {
                        //     $membership_types = 1;
                        // }


// Replace the hardcoded field IDs with dynamic detection
$membership_types = '';

// Get the form to access field properties
$form = GFAPI::get_form($form_id);
if ($form) {
    foreach ($form['fields'] as $field) {
        // Look for product fields that have a value
        if ($field->type === 'product' && !empty($entry[$field->id])) {
            $product_value = $entry[$field->id];
            if (!empty($product_value)) {
                // Extract the membership type (e.g., "1 Year" or "Annual")
                $membership_types = $product_value;
                
                // If the format is "Name|Price", extract just the name
                $parts = explode('|', $product_value);
                if (count($parts) > 1) {
                    $membership_types = $parts[0];
                }
                break; // Stop at the first found product with a value
            }
        }
    }
}

// Handle "Annual" case
if (strtolower($membership_types) === "annual") {
    $membership_types = 1;
} 
// If it's in format "X Year(s)", extract the number
elseif (preg_match('/(\d+)\s*(?:Year|Yr)s?/i', $membership_types, $matches)) {
    $membership_types = intval($matches[1]);
}
// Default to 1 if no valid duration found
else {
    $membership_types = 1;
}

                        if ($membership_status === 'approved' && $approval_date) {
                            try {
                                $start_date = new DateTime($approval_date);
                                $expiry_date = $start_date->modify('+' . (int)$membership_types . ' years');
                                echo '<p>Membership Expiry Date: <strong>' . $expiry_date->format('d/m/Y') . '</strong></p>';

                                $current_date = new DateTime();
                                if ($current_date > $expiry_date) {
                                    echo '<p>Your membership expired on <strong>' . $expiry_date->format('d/m/Y') . '</strong>.</p>';
                                    echo '<p>Please renew your membership by filling out this <a href="' . esc_url(home_url('/individual-membership/')) . '" target="_blank">renewal form</a>.</p>';
                                }
                            } catch (Exception $e) {
                                error_log("Invalid membership approval date for user ID {$current_user->ID}: {$approval_date} - Error: " . $e->getMessage());
                                echo '<p>Error displaying membership expiry date.</p>';
                            }
                        }
                    }
                }
                ?>
            </div>

            <!-- SGNDT Certificates Section -->
            <div id="certificate-section" class="user-profile-tab-content" style="display:none;" role="tabpanel" aria-labelledby="certificate-tab">
                <?php
                global $wpdb;
                $user_id = get_current_user_id();
                $table_certifications = $wpdb->prefix . 'sgndt_certifications';
                $table_subject_marks = $wpdb->prefix . 'sgndt_subject_marks';
                $table_subject_price = $wpdb->prefix .'sgndt_exam_prices';

                // Optimized query to avoid N+1 problem using JOIN instead of subquery
                $certifications = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT c.* FROM {$table_certifications} c
                         INNER JOIN (
                             SELECT user_id, method, level, sector, MAX(issue_date) as max_date
                             FROM {$table_certifications}
                             WHERE user_id = %d
                             GROUP BY user_id, method, level, sector
                         ) latest ON c.user_id = latest.user_id 
                         AND c.method = latest.method 
                         AND c.level = latest.level 
                         AND c.sector = latest.sector 
                         AND c.issue_date = latest.max_date
                         ORDER BY c.method ASC, c.level ASC, c.scope ASC, c.issue_date DESC",
                        $user_id
                    ),
                    ARRAY_A
                );

                if ($certifications) {
                    echo '<table class="certificate-table" role="grid">
                    <thead><tr><th>Method</th><th>Level</th><th>Sector</th><th>Scope</th><th>Notification Issue Date</th><th>Status</th><th>Failed Subjects</th></tr></thead><tbody>';

                    $base_retest_url = home_url('/retest-form');

                    foreach ($certifications as $cert) {
                        $cert_id = $cert['certification_id'];
                        $marks_entry_id = $cert['marks_entry_id'];
                        $latest_attempt = $wpdb->get_var(
                            $wpdb->prepare("SELECT MAX(attempt_number) FROM {$table_subject_marks} WHERE certification_id = %d", $cert_id)
                        );

                        $failed_subjects_raw = $wpdb->get_results(
                            $wpdb->prepare(
                                "SELECT subject_name, pass_status, status, percentage, marks_obtained, marks_total
                                FROM {$table_subject_marks}
                                WHERE certification_id = %d AND attempt_number = %d AND pass_status = 'Fail'",
                                $cert_id,
                                $latest_attempt
                            ),
                            ARRAY_A
                        );

                        $failed_subjects = [];
                        $show_retest_links = [];
                        $subject_details_by_name = [];

                        foreach ($failed_subjects_raw as $subj) {
                            $name = $subj['subject_name'];
                            $marks_obtained = isset($subj['marks_obtained']) ? floatval($subj['marks_obtained']) : 0;
                            $marks_total = isset($subj['marks_total']) ? floatval($subj['marks_total']) : 0;
                            if ($marks_total <= 0 && $marks_obtained > 0) {
                                $marks_total = 100;
                            }
                            $calculated_percent = percent($marks_obtained, $marks_total);

                            if ($calculated_percent >= 70) {
                                $wpdb->update(
                                    $table_subject_marks,
                                    [
                                        'pass_status' => 'Pass',
                                        'marks_total' => $marks_total,
                                        'percentage' => $calculated_percent,
                                    ],
                                    [
                                        'certification_id' => $cert_id,
                                        'subject_name' => $name,
                                        'attempt_number' => $latest_attempt,
                                    ],
                                    ['%s', '%f', '%f'],
                                    ['%d', '%s', '%d']
                                );
                                continue;
                            }

                            $failed_subjects[] = $name;
                            $subj['marks_total'] = $marks_total;
                            $subj['percentage'] = $calculated_percent;
                            $subject_details_by_name[$name] = $subj;

                            if (empty($subj['status']) || strtolower($subj['status']) !== 'pending') {
                                $show_retest_links[] = $name;
                            }
                        }

                        $status_value = empty($failed_subjects) ? 'Pass' : 'Fail';
                        $status_display = ($status_value === 'Pass') ? '<span class="status-pass">Pass</span>' : '<span class="status-fail">Fail</span>';

                        $failed_subjects_display = '-';
                        if (!empty($failed_subjects)) {
                            $failed_subjects_display = '<span class="failed-subjects"><ul>';
                            foreach ($failed_subjects as $subject_name) {
                                $detail = $subject_details_by_name[$subject_name] ?? null;
                                $tooltip = '';
                                if ($detail) {
                                    $tooltip = $subject_name . ': ' . 'Marks: ' . number_format($detail['marks_obtained'], 2) . '/' . number_format($detail['marks_total'], 2)
                                    . '<br>Percentage: ' . number_format($detail['percentage'], 2) . '%'
                                    . '<br>Status: ' . esc_html($detail['pass_status']);
                                }

                                $failed_subjects_display .= '<li style="position:relative;">' . esc_html($subject_name);

                                if (!in_array($subject_name, $show_retest_links)) {
                                    $failed_subjects_display .= ' <span class="status-fail">Retest Applied</span>';
                                } else {
                                     $base_price = $wpdb->get_var(
                                        $wpdb->prepare(
                                            "SELECT price FROM {$table_subject_price} WHERE level = %s AND subject = %s",
                                            sanitize_text_field($cert['level']),
                                            sanitize_text_field($subject_name)
                                        )
                                    );
                                    
                                    // Ensure base_price is valid before calculation
                                    $base_price = is_numeric($base_price) ? floatval($base_price) : 0;
                                    $final_price = $base_price + RETEST_ADDITIONAL_FEE;
                                   
                                    $redirect_url = add_query_arg([
                                        'orginal_exam_no' => $cert['exam_order_no'],
                                        'cert_number' => $cert['cert_number'],
                                        'retest_method' => $cert['method'],
                                        'retest_sector' => $cert['sector'],
                                        'retest_level' => $cert['level'],
                                        'retest_scope' => $cert['scope'],
                                        'retest_parts' => urlencode($subject_name),
                                        'marks_entry_id' => $marks_entry_id,
                                        'retest_price' => $final_price,
                                    ], $base_retest_url);

                                    $failed_subjects_display .= ' <a href="' . esc_url($redirect_url) . '" class="retest-btn">Apply Retest</a>';
                                }

                                $failed_subjects_display .= '<span class="tooltip">' . $tooltip . '</span></li>';
                            }
                            $failed_subjects_display .= '</ul></span>';
                        }

                        $result_notification_link = !empty($cert['certificate_link']) ? '<a class="result_link" href="' . esc_url($cert['certificate_link']) . '" target="_blank">View</a>' : '-';
                        $notification_issue_date = !empty($cert['issue_date']) ? date('d/m/Y', strtotime($cert['issue_date'])) : '-';
                        $certification_issue_date = '-';
                        $expiry_display = '-';
                        $action_display = ($status_value === 'Pass') ? 'Not Issued Yet' : 'Not Eligible';

                        if (empty($cert['cert_number'])) {
                            $cert['cert_number'] = 'N/A';
                        }

                        // echo '<tr>
                        // <td>' . esc_html($cert['method']) . '</td>
                        // <td>' . esc_html($cert['level']) . '</td>
                        // <td>' . esc_html($cert['sector']) . '</td>
                        // <td>' . esc_html($cert['scope']) . '</td>
                        // <td>' . $notification_issue_date . '</td>
                        // <td>' . $result_notification_link . '</td>
                        // <td>' . $status_display . '</td>
                        // <td>' . $failed_subjects_display . '</td>
                        // </tr>';
                        echo '<tr>
                        <td>' . esc_html($cert['method']) . '</td>
                        <td>' . esc_html($cert['level']) . '</td>
                        <td>' . esc_html($cert['sector']) . '</td>
                        <td>' . esc_html($cert['scope']) . '</td>
                        <td>' . $notification_issue_date . '</td>
                        <td>' . $status_display . '</td>
                        <td>' . $failed_subjects_display . '</td>
                        </tr>';
                    }

                    echo '</tbody></table>';
                } else {
                    echo '<p>No certifications found.</p>';
                }
                ?>
            </div>

              <!-- Final Certificates Section -->
            <?php if (has_cpd_certificate_access($current_user->ID)): ?>
            <div id="final-certificate-section" class="user-profile-tab-content" style="display:none;" role="tabpanel" aria-labelledby="final-certificate-tab">
                
                <h3>Final Certificates</h3>
                <div class="certificate-lifecycle-info">
                    <p class="lifecycle-description">
                        <strong>Certificate Lifecycle:</strong> Initial certificates are valid for 5 years. 
                        Renewal window opens 6 months before expiry. After 9 years from initial issue, 
                        recertification is required for 10-year validity.
                    </p>
                </div>
                <div class="filter-container">
                    <select id="method-filter" name="method_filter" aria-label="Filter by Method">
                        <option value="">All Methods</option>
                        <?php
                        global $wpdb;
                        $user_id = get_current_user_id();
                        $methods = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT method FROM {$wpdb->prefix}sgndt_final_certifications WHERE user_id = %d", $user_id));
                        foreach ($methods as $method) {
                            echo '<option value="' . esc_attr($method) . '">' . esc_html($method) . '</option>';
                        }
                        ?>
                    </select>
                    <select id="level-filter" name="level_filter" aria-label="Filter by Level">
                        <option value="">All Levels</option>
                        <?php
                        $levels = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT level FROM {$wpdb->prefix}sgndt_final_certifications WHERE user_id = %d", $user_id));
                        foreach ($levels as $level) {
                            echo '<option value="' . esc_attr($level) . '">' . esc_html($level) . '</option>';
                        }
                        ?>
                    </select>
                    <select id="status-filter" name="status_filter" aria-label="Filter by Status">
                        <option value="">All Statuses</option>
                        <option value="issued">Issued</option>
                        <option value="pending">Pending</option>
                    </select>
                    <button id="reset-filters" style="padding: 8px; font-size: 14px; border-radius: 4px; background-color: #d9534f; color: white;">Reset Filters</button>
                </div>
                <div aria-live="polite" id="filter-status" style="display:none;"></div>
               <div class="certificat_table-wrapper">
                 <table class="certificate-table" role="grid" id="final-certificate-table">
                    <thead>
                        <tr>
                            <th>Certificate Number</th>
                            <th>Method</th>
                            <th>Level</th>
                            <th>Sector</th>
                            <th>Scope</th>
                            <th>Issue Date</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Certificate Link</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="final-certificate-tbody">
                <?php
                // Order certificates so original certificates appear first, then renewals, then recertifications
                // Certificate type ordering: Initial (-01 or no suffix) = 1, Renewal (-02) = 2, Recertification (-03) = 3
                // Within each type, order by issue_date ASC so original comes before renewal
                $final_certifications = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT *,
                        CASE 
                            WHEN RIGHT(certificate_number, 4) = '-03' THEN 3  -- Recertification
                            WHEN RIGHT(certificate_number, 4) = '-02' THEN 2  -- Renewal
                            ELSE 1  -- Initial (no suffix or -01)
                        END AS cert_type_order
                        FROM {$wpdb->prefix}sgndt_final_certifications 
                        WHERE user_id = %d 
                        ORDER BY method ASC, level ASC, scope ASC, cert_type_order ASC, issue_date ASC",
                        $user_id
                    ),
                    ARRAY_A
                );
                

                if ($final_certifications) {
                    $current_date = new DateTime('now', new DateTimeZone('Asia/Kolkata')); // 06:29 PM IST, August 08, 2025
                    $renewal_url = home_url('/renew-recertification');
                    $recertification_url = home_url('/renew-recertification');
                    $full_exam_url = home_url('/application-form');

                    foreach ($final_certifications as $cert) {
                        $issue_date = !empty($cert['issue_date']) ? date('d/m/Y', strtotime($cert['issue_date'])) : '-';
                        $expiry_date = !empty($cert['expiry_date']) ? date('d/m/Y', strtotime($cert['expiry_date'])) : '-';
                        $certificate_link = !empty($cert['certificate_link']) ? 
                            '<a href="' . esc_url(add_query_arg(array('download_renewed_cert' => '1', 'cert_id' => $cert['final_certification_id']), $cert['certificate_link'])) . '" class="download-btn" target="_blank">Download</a>' : 
                            '-';
                        $cert_number = !empty($cert['certificate_number']) ? esc_html($cert['certificate_number']) : 'N/A';
                        $status = !empty($cert['status']) ? esc_html($cert['status']) : 'N/A';
                        
                        // Add visual indicator based on certificate type suffix
                        // 01 = Initial, 02 = Mutual Recognition, 03 = Renewal, 04 = Recertification
                        $cert_type_badge = '';
                        if (preg_match('/-02$/', $cert['certificate_number'])) {
                            // Mutual Recognition certificate (suffix -02)
                            $cert_type_badge = '<span class="renewal-badge mutual-recognition">MUTUAL RECOGNITION</span>';
                        } elseif (preg_match('/-03$/', $cert['certificate_number'])) {
                            // Renewal certificate (suffix -03)
                            $cert_type_badge = '<span class="renewal-badge renewed">RENEWED</span>';
                        } elseif (preg_match('/-04$/', $cert['certificate_number'])) {
                            // Recertification certificate (suffix -04)
                            $cert_type_badge = '<span class="renewal-badge recertified">RECERTIFIED</span>';
                        }
                        
                        // Show badge for renewal/recertification, validity status for initial certificates
                        if (!empty($cert_type_badge)) {
                            // Renewal or Recertification - show badge
                            $cert_number = '<div class="certificate-number-display">' .
                                          '<span class="cert-number">' . $cert_number . '</span>' .
                                          $cert_type_badge .
                                          '</div>';
                            
                            $status = '<span class="status-badge status-' . esc_attr($cert['status']) . '">' . 
                                     ucfirst($cert['status']) . '</span>';
                        } else {
                            // Initial certificate (suffix -01 or no suffix) - show validity status
                            $validity = get_certificate_validity_status($cert);
                            $validity_class = 'validity-' . $validity['status'];
                            
                            // Determine icon based on status
                            if ($validity['status'] === 'expired') {
                                $validity_icon = '❌';
                            } elseif ($validity['status'] === 'renewed') {
                                $validity_icon = '🔄';
                            } elseif ($validity['status'] === 'eligible') {
                                // Use different icon for recertification vs renewal
                                if (strpos($validity['message'], 'recertification') !== false) {
                                    $validity_icon = '🎓'; // Graduation cap for recertification
                                } else {
                                    $validity_icon = '⚠️'; // Warning for renewal
                                }
                            } else {
                                $validity_icon = '✅';
                            }
                            
                            $cert_number = '<div class="certificate-number-display">' .
                                          '<span class="cert-number">' . $cert_number . '</span>' .
                                          '<span class="validity-indicator ' . $validity_class . '">' . 
                                          $validity_icon . ' ' . $validity['message'] . '</span>' .
                                          '</div>';
                        }
                        $level = !empty($cert['level']) ? esc_html($cert['level']) : '';
                        $exam_entry_id = !empty($cert['exam_entry_id']) ? esc_html($cert['exam_entry_id']) : '-';
                        $marks_entry_id = !empty($cert['marks_entry_id']) ? esc_html($cert['marks_entry_id']) : '-';
                        $marks_entry = GFAPI::get_entry($marks_entry_id);
                        // Initialize action variable
                        $action = '-';
                        
                        // Use new certificate lifecycle system with error handling
                        $lifecycle = null;
                        $status_info = null;
                        $action = '-';

                        try {
                            // Use certificate ID for unique tracking instead of certificate number
                            $cert_id = $cert['final_certification_id'];
                            $lifecycle = get_certificate_lifecycle($user_id, $cert['certificate_number']);
                            
                            // IMPORTANT: Check for existing status FIRST (before showing button)
                            // If status exists (submitted, approved, rejected), show status instead of button
                            
                            // First, check database directly for status (fallback method)
                            global $wpdb;
                            
                            // Try multiple query methods to find the record
                            $cert_record = null;
                            $found_by_id = false;
                            
                            // Method 1: Query by ID and user_id
                            $cert_record = $wpdb->get_row($wpdb->prepare(
                                "SELECT renewal_status, renewal_method, renewal_approved_date, renewal_submitted_date, renewal_rejected_date, renewal_generated_date, user_id
                                 FROM {$wpdb->prefix}sgndt_final_certifications 
                                 WHERE final_certification_id = %d AND user_id = %d",
                                $cert_id, $user_id
                            ));
                            
                            if ($cert_record) {
                                $found_by_id = true;
                            }
                            
                            // Method 2: If not found, try by ID only (in case user_id is wrong)
                            if (!$cert_record || empty($cert_record->renewal_status)) {
                                $cert_record = $wpdb->get_row($wpdb->prepare(
                                    "SELECT renewal_status, renewal_method, renewal_approved_date, renewal_submitted_date, renewal_rejected_date, renewal_generated_date, user_id
                                     FROM {$wpdb->prefix}sgndt_final_certifications 
                                     WHERE final_certification_id = %d",
                                    $cert_id
                                ));
                                
                                if ($cert_record) {
                                    $found_by_id = true;
                                    // Update user_id if it was different
                                    if ($cert_record->user_id != $user_id) {
                                        $user_id = $cert_record->user_id;
                                    }
                                }
                            }
                            
                            // Method 3: If still not found, try by certificate number and user_id
                            if (!$cert_record || empty($cert_record->renewal_status)) {
                                $cert_record = $wpdb->get_row($wpdb->prepare(
                                    "SELECT renewal_status, renewal_method, renewal_approved_date, renewal_submitted_date, renewal_rejected_date, renewal_generated_date, user_id
                                     FROM {$wpdb->prefix}sgndt_final_certifications 
                                     WHERE certificate_number = %s AND user_id = %d
                                     ORDER BY issue_date DESC LIMIT 1",
                                    $cert['certificate_number'], $user_id
                                ));
                            }
                            
                            // Method 4: Last resort - try by certificate number only
                            if (!$cert_record || empty($cert_record->renewal_status)) {
                                $cert_record = $wpdb->get_row($wpdb->prepare(
                                    "SELECT renewal_status, renewal_method, renewal_approved_date, renewal_submitted_date, renewal_rejected_date, renewal_generated_date, user_id
                                     FROM {$wpdb->prefix}sgndt_final_certifications 
                                     WHERE certificate_number = %s
                                     ORDER BY issue_date DESC LIMIT 1",
                                    $cert['certificate_number']
                                ));
                                
                                if ($cert_record && $cert_record->user_id != $user_id) {
                                    $user_id = $cert_record->user_id;
                                }
                            }
                            
                            // SIMPLIFIED LOGIC: Direct database check and display
                            $action = '-'; // Default
                            
                            
                            if ($cert_record && !empty($cert_record->renewal_status)) {
                                // We have a status in the database
                                $renewal_method = $cert_record->renewal_method;
                                $is_recertification = (strtoupper($renewal_method) === 'RECERT');
                                
                                // Check if the target certificate exists
                                $base_cert_number = preg_replace('/-0[123]$/', '', $cert['certificate_number']);
                                $target_suffix = $is_recertification ? '-03' : '-02';
                                $target_cert_exists = $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->prefix}sgndt_final_certifications
                                     WHERE user_id = %d 
                                     AND certificate_number = %s
                                     AND status = 'issued'",
                                    $user_id,
                                    $base_cert_number . $target_suffix
                                ));
                                
                                // Only show status if target certificate doesn't exist yet
                                if ($target_cert_exists == 0) {
                                    $status = $cert_record->renewal_status;
                                    $status_date = $cert_record->renewal_approved_date ?: $cert_record->renewal_submitted_date ?: $cert_record->renewal_rejected_date;
                                    $formatted_date = $status_date ? date('d/m/Y', strtotime($status_date)) : 'N/A';
                                    $method_badge = !empty($renewal_method) ? '<span class="renewal-method-badge">' . strtoupper($renewal_method) . '</span>' : '';
                                    
                                    if ($status === 'approved') {
                                        $action = '<div class="renewal-status-wrapper status-approved">
                                            <div class="status-header">
                                                <span class="status-icon">✅</span>
                                                <span class="status-text">Approved</span>
                                                ' . $method_badge . '
                                            </div>
                                            <div class="status-details">
                                                <small>Approved: ' . esc_html($formatted_date) . '</small><br>
                                                <small class="status-note">Certificate will be issued soon</small>
                                            </div>
                                        </div>';
                                    } elseif ($status === 'submitted') {
                                        $status_text = $is_recertification ? 'Applied for recert' : 'Applied for Renewal';
                                        $action = '<div class="renewal-status-wrapper status-applied">
                                            <div class="status-header">
                                                <span class="status-icon">📝</span>
                                                <span class="status-text">' . esc_html($status_text) . '</span>
                                                ' . $method_badge . '
                                            </div>
                                            <div class="status-details">
                                                <small>Submitted: ' . esc_html($formatted_date) . '</small><br>
                                                <small class="status-note">Your application is being reviewed</small>
                                            </div>
                                        </div>';
                                    } elseif ($status === 'rejected') {
                                        $rejection_reason = $cert_record->renewal_rejection_reason ?: 'No reason provided';
                                        $action = '<div class="renewal-status-wrapper status-rejected">
                                            <div class="status-header">
                                                <span class="status-icon">❌</span>
                                                <span class="status-text">Application Rejected</span>
                                                ' . $method_badge . '
                                            </div>
                                            <div class="status-details">
                                                <small>Rejected: ' . esc_html($formatted_date) . '</small><br>
                                                <small class="status-note">Reason: ' . esc_html($rejection_reason) . '</small>
                                            </div>
                                        </div>';
                                    }
                                }
                            }
                            
                            // If no status from database, try lifecycle system
                            if ($action === '-') {
                                $status_info = get_certificate_lifecycle_status($user_id, $cert_id);
                                
                                if ($status_info && !empty($status_info['status'])) {
                                    // Status exists (submitted, approved, rejected) - show status instead of button
                                    $action = get_certificate_lifecycle_display($status_info, $cert['certificate_number']);
                                    
                                    // If display function returns empty, use fallback
                                    if (empty($action) || $action === '-') {
                                        $status = $status_info['status'];
                                        $renewal_method_display = isset($status_info['renewal_method']) ? $status_info['renewal_method'] : '';
                                        $status_date = isset($status_info['status_date']) ? date('d/m/Y', strtotime($status_info['status_date'])) : 'N/A';
                                        $method_badge = !empty($renewal_method_display) ? '<span class="renewal-method-badge">' . strtoupper($renewal_method_display) . '</span>' : '';
                                        
                                        if ($status === 'approved') {
                                            $action = '<div class="renewal-status-wrapper status-approved">
                                                <div class="status-header">
                                                    <span class="status-icon">✅</span>
                                                    <span class="status-text">Approved</span>
                                                    ' . $method_badge . '
                                                </div>
                                                <div class="status-details">
                                                    <small>Approved: ' . esc_html($status_date) . '</small><br>
                                                    <small class="status-note">Certificate will be issued soon</small>
                                                </div>
                                            </div>';
                                        } elseif ($status === 'submitted') {
                                            $status_text = (strtoupper($renewal_method_display) === 'RECERT') ? 'Applied for recert' : 'Applied for Renewal';
                                            $action = '<div class="renewal-status-wrapper status-applied">
                                                <div class="status-header">
                                                    <span class="status-icon">📝</span>
                                                    <span class="status-text">' . esc_html($status_text) . '</span>
                                                    ' . $method_badge . '
                                                </div>
                                                <div class="status-details">
                                                    <small>Submitted: ' . esc_html($status_date) . '</small><br>
                                                    <small class="status-note">Your application is being reviewed</small>
                                                </div>
                                            </div>';
                                        }
                                    }
                                } else {
                                    // No status exists - show action button (recertification or renewal)
                                    // BUT: Only if there's no status in database either
                                    // Double-check database one more time before showing button
                                    if (!$cert_record || empty($cert_record->renewal_status)) {
                                        // Get recertification/renewal button based on eligibility
                                        $action = get_certificate_action_buttons($cert, $current_date, $renewal_url, $recertification_url, $full_exam_url);
                                    } else {
                                        // Database has status but we didn't catch it - show it now
                                        $renewal_method = $cert_record->renewal_method;
                                        $is_recertification = (strtoupper($renewal_method) === 'RECERT');
                                        $base_cert_number = preg_replace('/-0[123]$/', '', $cert['certificate_number']);
                                        $target_suffix = $is_recertification ? '-03' : '-02';
                                        $target_cert_exists = $wpdb->get_var($wpdb->prepare(
                                            "SELECT COUNT(*) FROM {$wpdb->prefix}sgndt_final_certifications
                                             WHERE user_id = %d 
                                             AND certificate_number = %s
                                             AND status = 'issued'",
                                            $user_id,
                                            $base_cert_number . $target_suffix
                                        ));
                                        
                                        if ($target_cert_exists == 0) {
                                            $status = $cert_record->renewal_status;
                                            $status_date = $cert_record->renewal_approved_date ?: $cert_record->renewal_submitted_date ?: $cert_record->renewal_rejected_date;
                                            $formatted_date = $status_date ? date('d/m/Y', strtotime($status_date)) : 'N/A';
                                            $method_badge = !empty($renewal_method) ? '<span class="renewal-method-badge">' . strtoupper($renewal_method) . '</span>' : '';
                                            
                                            if ($status === 'approved') {
                                                $action = '<div class="renewal-status-wrapper status-approved">
                                                    <div class="status-header">
                                                        <span class="status-icon">✅</span>
                                                        <span class="status-text">Approved</span>
                                                        ' . $method_badge . '
                                                    </div>
                                                    <div class="status-details">
                                                        <small>Approved: ' . esc_html($formatted_date) . '</small><br>
                                                        <small class="status-note">Certificate will be issued soon</small>
                                                    </div>
                                                </div>';
                                            }
                                        }
                                    }
                                }
                            }
                            
                        } catch (Exception $e) {
                            error_log("Error in certificate lifecycle system for certificate {$cert['certificate_number']} (ID: {$cert['final_certification_id']}), user {$user_id}: " . $e->getMessage());
                            // Fallback to basic action button logic
                            $action = get_certificate_action_buttons($cert, $current_date, $renewal_url, $recertification_url, $full_exam_url);
                        }

                        // Determine row class based on certificate type
                        $row_class = '';
                        if (preg_match('/-02$/', $cert['certificate_number'])) {
                            $row_class = 'renewed-certificate';
                        } elseif (preg_match('/-03$/', $cert['certificate_number'])) {
                            $row_class = 'recertified-certificate';
                        } elseif (preg_match('/-01$/', $cert['certificate_number'])) {
                            $row_class = 'initial-certificate';
                        }
                        
                        echo '<tr class="' . $row_class . '" data-method="' . esc_attr($cert['method']) . '" data-level="' . esc_attr($cert['level']) . '" data-status="' . esc_attr($cert['status']) . '">';
                            echo '<td>' . $cert_number . '</td>';
                            echo '<td>' . esc_html($cert['method']) . '</td>';
                            echo '<td>' . esc_html($cert['level']) . '</td>';
                            echo '<td>' . esc_html($cert['sector']) . '</td>';
                            echo '<td>' . esc_html($cert['scope']) . '</td>';
                            echo '<td>' . $issue_date . '</td>';
                            echo '<td>' . $expiry_date . '</td>';
                            echo '<td>' . $status . '</td>';
                            echo '<td>' . $certificate_link . '</td>';
                            echo '<td>' . $action . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="10">No final certificates found.</td></tr>';
                }
                ?>
            </tbody>
                </table>
               </div>
            </div>
            <?php endif; ?>

            <!-- Event Participation Section (unchanged) -->
            <div id="event-participation-section" class="user-profile-tab-content" style="display:none;">
                <?php
                $user_id = get_current_user_id();
                $registered_events = get_user_meta($user_id, 'registered_event_ids', false);

                if (!empty($registered_events)) {
                    echo '<h3 class="event-section-title">Your Registered Events</h3>';
                    echo '<div class="event-container">';

                    foreach ($registered_events as $event_id) {
                        $event_title = get_the_title($event_id);
                        $event_link = get_permalink($event_id);
                        $event_status = get_user_meta($user_id, 'event_' . $event_id . '_approval_status', true);
                        $event_status = !empty($event_status) ? ucfirst($event_status) : 'Pending';
                        $event_start = get_post_meta($event_id, '_EventStartDate', true);
                        $event_end = get_post_meta($event_id, '_EventEndDate', true);
                        $event_duration_text = format_event_duration($event_start, $event_end);

                        $check_in_status = get_user_meta($user_id, 'event_' . $event_id . '_check_in_status', true);
                        $check_in_time = get_user_meta($user_id, 'event_' . $event_id . '_check_in_time', true);
                        $check_out_time = get_user_meta($user_id, 'event_' . $event_id . '_check_out_time', true);
                        $total_hours_attended = get_user_meta($user_id, 'event_' . $event_id . '_total_hours', true);
                        $total_hours_text = format_attendance_hours($total_hours_attended);                        
                        $cpd_points = get_user_meta($user_id, 'event_' . $event_id . '_cpd_points', true); $event_end_timestamp = !empty($event_end) ? strtotime($event_end) : 0;
                        $current_timestamp = strtotime(current_time('mysql'));
                        $event_has_ended = ($event_end_timestamp > 0 && $current_timestamp > $event_end_timestamp);
                        
                        $check_in_status_text = '';
                        $check_in_time_text = 'N/A';
                        $check_out_time_text = 'N/A';
                        $check_out_button = '';

                        if ($check_in_status === 'checked_in' && !$event_has_ended) {
                            $check_in_status_text = '<span class="status badge blue">Checked-In</span>';
                            $check_in_time_text = !empty($check_in_time) ? date("d/m/Y, h:i A", strtotime($check_in_time)) : 'N/A';
                            $check_out_time_text = 'Not Checked-Out';
                            $check_out_button = '<button class="checkout-button" 
                            data-user-id="' . esc_attr($user_id) . '" 
                            data-event-id="' . esc_attr($event_id) . '">
                            Check-Out
                            </button>';
                        } elseif ($check_in_status === 'checked_out') {
                            $check_in_status_text = '<span class="status badge green">Checked-Out</span>';
                            $check_in_time_text = !empty($check_in_time) ? date("d/m/Y, h:i A", strtotime($check_in_time)) : 'N/A';
                            $check_out_time_text = !empty($check_out_time) ? date("d/m/Y, h:i A", strtotime($check_out_time)) : 'N/A';
                        } elseif ($check_in_status === 'checked_in' && $event_has_ended) {
                            $check_in_status_text = '<span class="status badge grey">Checked-In (Event Ended)</span>';
                            $check_in_time_text = !empty($check_in_time) ? date("d/m/Y, h:i A", strtotime($check_in_time)) : 'N/A';
                            $check_out_time_text = 'Not Checked-Out';
                        } else {
                            $check_in_status_text = '<span class="status badge red">Not Checked-In</span>';
                        }
                        
                        ?>
                        <div class="event-card">
                            <h4><a href="<?php echo esc_url($event_link); ?>"><?php echo esc_html($event_title); ?></a></h4>
                            <p><strong>Status:</strong> <?php echo $event_status; ?></p>
                            <p><strong>Event Duration:</strong> <?php echo $event_duration_text; ?></p>
                            <p><strong>Check-In Status:</strong> <?php echo $check_in_status_text; ?></p>
                            <p><strong>Check-In Time:</strong> <?php echo $check_in_time_text; ?></p>
                            <p><strong>Check-Out Time:</strong> <?php echo $check_out_time_text; ?></p>
                            <p><strong>Total Hours Attended:</strong> <?php echo $total_hours_text; ?></p>
                            <p><strong>CPD Points Earned:</strong> <?php echo !empty($cpd_points) ? esc_html($cpd_points) : 'N/A'; ?></p>
                            <?php echo $check_out_button; ?>
                        </div>
                        <?php
                    }
                    echo '</div>';
                } else {
                    echo '<p>You have not registered for any events yet.</p>';
                }
                ?>
            </div>

            <!-- CPD Management Section -->
            <?php if (has_cpd_certificate_access($current_user->ID)): ?>
            <div id="cpd-management-section" class="user-profile-tab-content" style="display:none;" role="tabpanel" aria-labelledby="cpd-management-tab">
                <h3 class="section-title">CPD Management</h3>
                
                <div class="cpd-profile-section">
                    <div class="cpd-profile-tabs">
                        <ul class="cpd-inner-tabs">
                            <li><a href="#cpd-summary-tab" class="cpd-tab-link active" data-tab="summary">My CPD Summary</a></li>
                            <li><a href="#cpd-upload-tab" class="cpd-tab-link" data-tab="upload">Upload CPD Entry</a></li>
                        </ul>
                    </div>
                    
                    <div class="cpd-profile-content">
                        <div id="cpd-summary-tab" class="cpd-tab-content active">
                            <h4>My CPD Summary</h4>
                            <?php echo do_shortcode('[cpd_summary]'); ?>
                        </div>
                        
                        <div id="cpd-upload-tab" class="cpd-tab-content" style="display:none;">
                            <h4>Upload CPD Entry</h4>
                            <?php echo do_shortcode('[cpd_upload_form]'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
    /* CPD Management Section Styles */
    .cpd-profile-section {
        margin-top: 20px;
    }
    
    .cpd-profile-tabs ul.cpd-inner-tabs {
        list-style: none;
        padding: 0;
        margin: 0 0 20px 0;
        display: flex;
        border-bottom: 2px solid #ddd;
    }
    
    .cpd-profile-tabs ul.cpd-inner-tabs li {
        margin: 0;
    }
    
    .cpd-profile-tabs ul.cpd-inner-tabs li a.cpd-tab-link {
        display: block;
        padding: 12px 24px;
        text-decoration: none;
        color: #666;
        background: #f5f5f5;
        border: 1px solid #ddd;
        border-bottom: none;
        margin-right: 5px;
        border-radius: 4px 4px 0 0;
        transition: all 0.3s ease;
    }
    
    .cpd-profile-tabs ul.cpd-inner-tabs li a.cpd-tab-link:hover {
        background: #e9e9e9;
        color: #0073aa;
    }
    
    .cpd-profile-tabs ul.cpd-inner-tabs li a.cpd-tab-link.active {
        background: #fff;
        color: #0073aa;
        font-weight: 600;
        border-bottom: 2px solid #fff;
        margin-bottom: -2px;
        position: relative;
        z-index: 1;
    }
    
    .cpd-tab-content {
        padding: 20px;
        background: #fff;
        border: 1px solid #ddd;
        border-top: none;
        border-radius: 0 4px 4px 4px;
    }
    
    .cpd-tab-content h4 {
        margin-top: 0;
        color: #0073aa;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
        margin-bottom: 20px;
    }
    </style>

    <script>
    // CPD Inner Tab Functionality
    jQuery(document).ready(function($) {
        $('.cpd-tab-link').on('click', function(e) {
            e.preventDefault();
            var $this = $(this);
            var targetTab = $this.data('tab');
            
            // Remove active class from all tabs
            $('.cpd-tab-link').removeClass('active');
            $('.cpd-tab-content').hide();
            
            // Add active class to clicked tab
            $this.addClass('active');
            
            // Show corresponding content
            if (targetTab === 'summary') {
                $('#cpd-summary-tab').show();
            } else if (targetTab === 'upload') {
                $('#cpd-upload-tab').show();
            }
        });
        
        // Handle hash navigation for CPD section
        if (window.location.hash === '#cpd-management-section') {
            // If coming to CPD section, show summary tab by default
            $('.cpd-tab-link[data-tab="summary"]').click();
        }
    });
    </script>

    <script>
    // Function to update renewal status after successful form submission
    function updateRenewalStatus(certNumber) {
        jQuery.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'update_renewal_status',
                cert_number: certNumber,
                nonce: '<?php echo wp_create_nonce('update_renewal_status'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Refresh the page to show updated status
                    location.reload();
                }
            }
        });
    }

// Improved event delegation for better performance with dynamic content
document.addEventListener('DOMContentLoaded', function() {
    // Use event delegation on document for dynamic content
    document.addEventListener('click', function(e) {
        // Handle action buttons
        if (e.target.matches('.action-button') || e.target.closest('.action-button')) {
            const button = e.target.matches('.action-button') ? e.target : e.target.closest('.action-button');
            e.preventDefault();
            
            const cell = button.closest('td');
            if (cell) {
                const examIdEl = cell.querySelector('[data-exam-id]');
                const marksIdEl = cell.querySelector('[data-marks-id]');
                const methodEl = cell.querySelector('[data-method]');
                const actionTypeEl = cell.querySelector('[data-type]');
                
                const examId = examIdEl ? examIdEl.getAttribute('data-exam-id') : '';
                const marksId = marksIdEl ? marksIdEl.getAttribute('data-marks-id') : '';
                const method = methodEl ? methodEl.getAttribute('data-method') : '';
                const actionType = actionTypeEl ? actionTypeEl.getAttribute('data-type') : '';
                const renewMethod = button.getAttribute('data-action') || '';
                
                console.log(`Clicked: ${actionType} by ${renewMethod}, Exam ID: ${examId}, Marks ID: ${marksId}, Method: ${method}`);
            }
            
            // Follow the link
            if (button.href) {
                window.location.href = button.href;
            }
        }
    });
});


    jQuery(document).ready(function($) {
        function initializeTabs() {
        var initialHash = window.location.hash;
        var $initialTabLink = initialHash ? $('.tab-link[href="' + initialHash + '"]') : $();

        $('.user-profile-tab-content').hide();
        $('.tab-link').removeClass('active-tab').attr('aria-selected', 'false');

        if ($initialTabLink.length && $(initialHash).length) {
            $(initialHash).show();
            $initialTabLink.addClass('active-tab').attr('aria-selected', 'true');
            console.log('Tabs initialized from hash: ' + initialHash);
            if (initialHash === '#final-certificate-section') {
                applyFilters();
            } else if (initialHash === '#cpd-management-section') {
                // Initialize CPD inner tabs
                $('.cpd-tab-link[data-tab="summary"]').click();
            }
        } else {
            $('#basic-profile-section').show();
        $('.tab-link[href="#basic-profile-section"]').addClass('active-tab').attr('aria-selected', 'true');
        console.log('Tabs initialized: Basic Profile shown');
        }
    }

    // Tab click handler
    $('.tab-link').on('click', function(e) {
        e.preventDefault();
        var $this = $(this);
        var target = $this.attr('href');

        if (!$(target).length) {
            console.error('Tab target not found: ' + target);
            return;
        }

        $('.tab-link').removeClass('active-tab').attr('aria-selected', 'false');
        $this.addClass('active-tab').attr('aria-selected', 'true');
        $('.user-profile-tab-content').hide();
        $(target).show();

        // update URL hash so refresh keeps current tab
        if (history.pushState) {
            history.pushState(null, '', target);
        } else {
            window.location.hash = target;
        }

        console.log('Switched to tab: ' + target);
        if (target === '#final-certificate-section') {
            applyFilters();
        } else if (target === '#cpd-management-section') {
            // Initialize CPD inner tabs when CPD section is opened
            $('.cpd-tab-link[data-tab="summary"]').click();
        }
    });

    // Filter handler for Final Certificates
    function applyFilters() {
        var methodFilter = $('#method-filter').val();
        var levelFilter = $('#level-filter').val();
        var statusFilter = $('#status-filter').val();

        var $tbody = $('#final-certificate-tbody');
        var $originalRows = $tbody.data('originalRows');
        if (!$originalRows) {
            $originalRows = $tbody.html();
            $tbody.data('originalRows', $originalRows);
        }

        // Show all rows if no filters are applied
        if (!methodFilter && !levelFilter && !statusFilter) {
            $tbody.html($originalRows);
            $('#filter-status').text('All certificates displayed.');
            return;
        }

        // Apply filters
        var $rows = $tbody.find('tr').filter(function() {
            var $row = $(this);
            var method = $row.data('method') || '';
            var level = $row.data('level') || '';
            var status = $row.data('status') || '';

            var showRow = true;
            if (methodFilter && method !== methodFilter) showRow = false;
            if (levelFilter && level !== levelFilter) showRow = false;
            if (statusFilter && status !== statusFilter) showRow = false;

            return showRow;
        });

        // Update table body
        $tbody.html($rows.length > 0 ? $rows : '<tr><td colspan="10">No certificates match the selected filters.</td></tr>');

        // Update ARIA live region
        var visibleRows = $rows.length;
        $('#filter-status').text(visibleRows > 0 ? visibleRows + ' certificates found.' : 'No certificates match the selected filters.');
    }

    // Filter change handler
    $('#method-filter, #level-filter, #status-filter').on('change', applyFilters);

    // Reset filters
    $('#reset-filters').on('click', function() {
        $('#method-filter').val('');
        $('#level-filter').val('');
        $('#status-filter').val('');
        applyFilters();
    });

    // Initialize tabs and filters
    initializeTabs();

            

        // Checkout button handler (unchanged)
        $('.checkout-button').on('click', function(e) {
            e.preventDefault();
            var userId = $(this).data('user-id');
            var eventId = $(this).data('event-id');

            Swal.fire({
                title: 'Are you sure?',
                text: "Do you want to check-out for this event?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, check-out!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'event_checkout_ajax',
                            user_id: userId,
                            event_id: eventId,
                        },
                        beforeSend: function() {
                            Swal.fire({
                                title: 'Processing...',
                                text: 'Please wait while we process your request.',
                                showConfirmButton: false,
                                allowOutsideClick: false,
                                icon: 'info'
                            });
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: 'Checked-Out Successfully!',
                                    text: response.data.message,
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    var eventId = response.data.event_id;
                                    var totalHours = response.data.total_hours;
                                    $('.event-' + eventId + '-status').html('Checked-Out - Total Time Spent: ' + totalHours + ' hours');
                                    $('.event-' + eventId + '-checkout-button').remove();
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error!',
                                    text: response.data.message,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Checkout AJAX error: ' + error);
                            Swal.fire({
                                title: 'Error!',
                                text: 'An unexpected error occurred: ' + error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
            });
        });

        // Membership form handler (unchanged)
        $('#membership-form').on('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#membership-result').html('<p class="success-message">' + response.data.message + '</p>');
                    location.reload();
                },
                error: function(response) {
                    console.error('Membership form submission error: ' + response.statusText);
                    $('#membership-result').html('<p class="error-message">Error uploading CPD certificate.</p>');
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Disable header and footer for popup views (unchanged)
 */
function disable_header_footer_for_popup() {
    if (isset($_GET['popup']) && $_GET['popup'] == '1') {
        remove_all_actions('get_header');
        remove_all_actions('get_footer');
    }
}
add_action('template_redirect', 'disable_header_footer_for_popup');

add_shortcode('user_profile', 'user_profile_shortcode');


/**
 * Handle Form 31 (Renewal by Exam) submission to update certificate status
 */
add_action('gform_after_submission_31', 'handle_form_31_renewal_submission', 12, 2);
add_action('gform_after_submission_39', 'handle_form_31_renewal_submission', 12, 2); // Form 39 also uses same handler
function handle_form_31_renewal_submission($entry, $form) {
    // Handle both Form 31 and Form 39 (renewal by exam forms)
    $user_id = rgar($entry, 'created_by');
    $cert_number = rgar($entry, '13'); // Certificate number for logging
    
    $form_id = rgar($entry, 'form_id');
    
    // Get certificate ID from field 28 (hidden field)
    $cert_id = rgar($entry, '28'); // Field 28 contains the final_certification_id
    
    // If no cert_id found, try to find it by certificate number in database
    if (empty($cert_id) && !empty($cert_number) && !empty($user_id)) {
        global $wpdb;
        $cert_record = $wpdb->get_row($wpdb->prepare(
            "SELECT final_certification_id FROM {$wpdb->prefix}sgndt_final_certifications 
             WHERE user_id = %d AND certificate_number = %s 
             ORDER BY issue_date DESC LIMIT 1",
            $user_id, $cert_number
        ));
        
        if ($cert_record) {
            $cert_id = $cert_record->final_certification_id;
        }
    }
    
    if (empty($cert_id) || empty($user_id)) {
        return;
    }
    
    // Update the original certificate with renewal status - Same as CPD method
    global $wpdb;
    
    // Detect if this is recertification (Form 39 only)
    $is_recertification = false;
    if ($form_id == 39) {
        // Check field 27 for explicit recertification type
        $renewal_type = rgar($entry, '27');
        $renewal_type_normalized = !empty($renewal_type) ? strtoupper(trim($renewal_type)) : '';
        
        // Also check if there's already a renewal certificate (-02) to determine if this is recertification
        $cert_data_temp = $wpdb->get_row($wpdb->prepare(
            "SELECT certificate_number FROM {$wpdb->prefix}sgndt_final_certifications 
             WHERE final_certification_id = %d AND user_id = %d",
            $cert_id, $user_id
        ));
        
        if ($cert_data_temp && !empty($cert_data_temp->certificate_number)) {
            $base_number = preg_replace('/-[0-9]+$/', '', $cert_data_temp->certificate_number);
            $renewal_cert_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sgndt_final_certifications 
                 WHERE user_id = %d AND certificate_number = %s AND status = 'issued'",
                $user_id, $base_number . '-02'
            ));
            
            if ($renewal_cert_count > 0 || strpos($renewal_type_normalized, 'RECERT') !== false || strpos($renewal_type_normalized, 'RECERTIFICATION') !== false) {
                $is_recertification = true;
            }
        }
    }
    
    $renewal_method = $is_recertification ? 'RECERT' : 'EXAM'; // RECERT for recertification, EXAM for renewal
    
    // Get certificate details for status update
    $cert_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications 
         WHERE final_certification_id = %d AND user_id = %d",
        $cert_id, $user_id
    ));
    
    if (!$cert_data) {
        return;
    }
    
    // Ensure cert_number is from database to prevent tampering
    $cert_number = $cert_data->certificate_number;
    $level = $cert_data->level;
    $sector = $cert_data->sector;
    
    // Update database table with renewal status - Same as CPD method
    $update_result = $wpdb->update(
        $wpdb->prefix . 'sgndt_final_certifications',
        array(
            'renewal_status' => 'submitted',
            'renewal_method' => $renewal_method,
            'renewal_submitted_date' => current_time('mysql'),
            'renewal_submission_id' => rgar($entry, 'id')
        ),
        array('final_certification_id' => $cert_id),
        array('%s', '%s', '%s', '%d'),
        array('%d')
    );
    
    // Update certificate status using cert_number - Same as CPD method
    // This ensures both methods use the same status management system
    $status_data = [
        'submission_method' => 'exam_form',
        'form_entry_id' => rgar($entry, 'id'),
        'submission_type' => $is_recertification ? 'recertification_by_exam' : 'renewal_by_exam',
        'original_cert_id' => $cert_id,
        'level' => $level,
        'sector' => $sector,
        'renewal_method' => $renewal_method // Include renewal_method for status display
    ];
    
    $status_updated = update_certificate_status($user_id, $cert_number, 'submitted', $status_data);
    
    // Also update by ID for backward compatibility
    update_certificate_status_by_id($user_id, $cert_id, 'submitted', array_merge($status_data, [
        'cert_number' => $cert_number
    ]));
    
    // Store original certificate ID in entry meta for admin access (similar to CPD submission)
    gform_update_meta(rgar($entry, 'id'), 'original_cert_id', $cert_id);
}

/**
 * Handle Form 31/39 approval to update certificate status - Same flow as CPD method
 * Hook into the existing Form 31/39 approval AJAX handlers
 */
add_action('wp_ajax_form_31_approve_entry_ajax', 'update_certificate_status_on_form_31_approval', 5);
add_action('wp_ajax_exam_approve_entry_ajax', 'update_certificate_status_on_form_39_approval', 5); // Also handle Form 39 via exam approval handler

/**
 * Handle Form 31/39 rejection to update certificate status - Same flow as CPD method
 * Hook into the existing Form 31/39 rejection AJAX handler
 */
add_action('wp_ajax_form_31_reject_entry_ajax', 'update_certificate_status_on_form_31_rejection', 5);
/**
 * Update certificate status for Form 39 approval via exam_approve_entry_ajax
 * This handles Form 39 when approved through the exam approval handler
 */
function update_certificate_status_on_form_39_approval() {
    $entry_id = intval($_POST['entry_id']);
    $user_id = intval($_POST['user_id']);
    
    if (!$entry_id || !$user_id) {
        return; // Let the original function handle the error
    }
    
    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry) || $entry['form_id'] != 39) {
        return; // Only handle Form 39 here
    }
    
    // Call the same approval handler logic
    update_certificate_status_on_approval($entry_id, $user_id, $entry);
}

function update_certificate_status_on_form_31_approval() {
    // This runs before the existing form_31_handle_approve_entry_ajax function
    $entry_id = intval($_POST['entry_id']);
    $user_id = intval($_POST['user_id']);
    
    if (!$entry_id || !$user_id) {
        return; // Let the original function handle the error
    }
    
    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry) || !in_array($entry['form_id'], [31, 39])) {
        return; // Let the original function handle the error - support both Form 31 and 39
    }
    
    // Call shared approval logic
    update_certificate_status_on_approval($entry_id, $user_id, $entry);
}

/**
 * Shared function to update certificate status on approval (used by both handlers)
 */
function update_certificate_status_on_approval($entry_id, $user_id, $entry) {
    if (is_wp_error($entry) || !in_array($entry['form_id'], [31, 39])) {
        return;
    }
    
    $form_id = $entry['form_id'];
    $cert_number = rgar($entry, '13'); // Certificate number field
    $cert_id = rgar($entry, '28'); // Certificate ID from field 28 (hidden field)
    
    error_log("Form {$form_id} approval: Starting certificate status update - entry_id: {$entry_id}, user_id: {$user_id}, cert_number: {$cert_number}, cert_id from field 28: {$cert_id}");
    
    // Fallback 1: get cert_id from entry meta if field 28 is empty
    if (empty($cert_id)) {
        $cert_id = gform_get_meta($entry_id, 'original_cert_id');
        error_log("Form {$form_id} approval: cert_id from entry meta: {$cert_id}");
    }
    
    // Fallback 2: Look up cert_id by certificate number if still not found
    if (empty($cert_id) && !empty($cert_number) && !empty($user_id)) {
        global $wpdb;
        $cert_record = $wpdb->get_row($wpdb->prepare(
            "SELECT final_certification_id, certificate_number FROM {$wpdb->prefix}sgndt_final_certifications 
             WHERE user_id = %d AND certificate_number = %s 
             ORDER BY issue_date DESC LIMIT 1",
            $user_id, $cert_number
        ));
        
        if ($cert_record) {
            $cert_id = $cert_record->final_certification_id;
            // Ensure cert_number matches exactly what's in database
            if (!empty($cert_record->certificate_number)) {
                $cert_number = $cert_record->certificate_number;
            }
            error_log("Form {$form_id} approval: Found cert_id {$cert_id} by certificate number lookup");
        }
    }
    
    if (empty($cert_id)) {
        error_log("Form {$form_id} approval: ERROR - Could not find cert_id for entry {$entry_id}, user {$user_id}, cert_number {$cert_number}");
        return;
    }
    
    // Get certificate details to ensure we have correct cert_number
    global $wpdb;
    $cert_data = $wpdb->get_row($wpdb->prepare(
        "SELECT certificate_number FROM {$wpdb->prefix}sgndt_final_certifications 
         WHERE final_certification_id = %d AND user_id = %d",
        $cert_id, $user_id
    ));
    
    if ($cert_data && !empty($cert_data->certificate_number)) {
        $cert_number = $cert_data->certificate_number;
    }
    
    if (empty($cert_number)) {
        error_log("Form {$form_id} approval: ERROR - Could not find certificate number for cert_id {$cert_id}");
        return;
    }
    
    // Update the original certificate with renewal approval status - Same as CPD method
    $update_result = $wpdb->update(
        $wpdb->prefix . 'sgndt_final_certifications',
        array(
            'renewal_status' => 'approved',
            'renewal_approved_date' => current_time('mysql'),
            'renewal_approved_by' => get_current_user_id()
        ),
        array('final_certification_id' => $cert_id),
        array('%s', '%s', '%d'),
        array('%d')
    );
    
    if ($update_result === false) {
        error_log("Form {$form_id} approval: ERROR - Database update failed: " . $wpdb->last_error);
    } else {
        error_log("Form {$form_id} approval: Database updated - {$update_result} row(s) affected");
    }
    
    // Update certificate status using cert_number - Same as CPD method
    $cert_status_key = 'cert_status_' . $cert_number;
    $meta_updated = update_user_meta($user_id, $cert_status_key, 'approved');
    update_user_meta($user_id, $cert_status_key . '_date', current_time('mysql'));
    update_user_meta($user_id, $cert_status_key . '_approved_by', get_current_user_id());
    update_user_meta($user_id, $cert_status_key . '_submission_method', 'exam_form');
    update_user_meta($user_id, $cert_status_key . '_approval_entry_id', $entry_id);
    
    if ($meta_updated) {
        error_log("Form {$form_id} approval: SUCCESS - Certificate status updated to 'approved' (same as CPD method) for cert_id {$cert_id}, cert_number {$cert_number}, user {$user_id}, status key: {$cert_status_key}");
    } else {
        error_log("Form {$form_id} approval: WARNING - User meta may not have been updated (might already be 'approved')");
    }
    
    // Also update by ID for backward compatibility
    update_certificate_status_by_id($user_id, $cert_id, 'approved', [
        'approval_method' => 'admin_approval',
        'approved_by' => get_current_user_id(),
        'approval_entry_id' => $entry_id,
        'cert_number' => $cert_number
    ]);
}

/**
 * Handle Form 31 rejection to update certificate status
 */
function update_certificate_status_on_form_31_rejection() {
    // This runs before the existing form_31_handle_reject_entry_ajax function
    $entry_id = intval($_POST['entry_id']);
    $user_id = intval($_POST['user_id']);
    
    if (!$entry_id || !$user_id) {
        return; // Let the original function handle the error
    }
    
    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry) || !in_array($entry['form_id'], [31, 39])) {
        return; // Let the original function handle the error - support both Form 31 and 39
    }
    
    $cert_number = rgar($entry, '13'); // Certificate number for logging
    $cert_id = rgar($entry, '28'); // Certificate ID from field 28 (hidden field)
    
    // Fallback: get cert_id from entry meta if field 28 is empty
    if (empty($cert_id)) {
        $cert_id = gform_get_meta($entry_id, 'original_cert_id');
    }
    
    error_log("Form 31 rejection: cert_id from field 28 or meta: {$cert_id}");
    
    if (!empty($cert_id)) {
        // Get certificate details to ensure we have correct cert_number
        global $wpdb;
        $cert_data = $wpdb->get_row($wpdb->prepare(
            "SELECT certificate_number FROM {$wpdb->prefix}sgndt_final_certifications 
             WHERE final_certification_id = %d AND user_id = %d",
            $cert_id, $user_id
        ));
        
        if ($cert_data) {
            $cert_number = $cert_data->certificate_number;
        }
        
        // Update the original certificate with renewal rejection status - Same as CPD method
        $rejection_reason = sanitize_textarea_field($_POST['reject_reason']);
        
        $wpdb->update(
            $wpdb->prefix . 'sgndt_final_certifications',
            array(
                'renewal_status' => NULL, // Reset renewal status so user can try again
                'renewal_method' => NULL,
                'renewal_submitted_date' => NULL,
                'renewal_approved_date' => NULL,
                'renewal_rejected_date' => current_time('mysql'),
                'renewal_rejected_by' => get_current_user_id(),
                'renewal_rejection_reason' => $rejection_reason,
                'renewal_submission_id' => NULL
            ),
            array('final_certification_id' => $cert_id),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d'),
            array('%d')
        );
        
        // Update certificate status using cert_number - Same as CPD method
        if (!empty($cert_number)) {
            $cert_status_key = 'cert_status_' . $cert_number;
            update_user_meta($user_id, $cert_status_key, 'rejected');
            update_user_meta($user_id, $cert_status_key . '_date', current_time('mysql'));
            update_user_meta($user_id, $cert_status_key . '_rejected_by', get_current_user_id());
            update_user_meta($user_id, $cert_status_key . '_rejection_reason', $rejection_reason);
            update_user_meta($user_id, $cert_status_key . '_submission_method', 'exam_form');
            
            error_log("Form {$entry['form_id']} rejection: Certificate status updated to 'rejected' (same as CPD method) for cert_id {$cert_id}, cert_number {$cert_number}, user {$user_id}");
        }
        
        // Also update by ID for backward compatibility
        update_certificate_status_by_id($user_id, $cert_id, 'rejected', [
            'rejection_method' => 'admin_rejection',
            'rejected_by' => get_current_user_id(),
            'rejection_entry_id' => $entry_id,
            'cert_number' => $cert_number,
            'rejection_reason' => $rejection_reason
        ]);
    }
}

/**
 * Handle certificate generation to update renewal status
 */
add_action('certificate_generated', 'update_certificate_status_on_generation', 10, 5);
function update_certificate_status_on_generation($final_certification_id, $certificate_number, $user_id, $cert_data, $exam_entry_id) {
    // Check if this is a renewed certificate (has suffix -02 for renewal or -03 for recertification)
    if (preg_match('/-0[23]$/', $certificate_number)) {
        // Get the Form 31 entry to find the cert_id (field 28)
        $entry = GFAPI::get_entry($exam_entry_id);
        if (is_wp_error($entry)) {
            return;
        }
        
        // Get the cert_id from field 28 (the original certificate's final_certification_id)
        $original_cert_id = rgar($entry, '28');
        
        if (empty($original_cert_id)) {
            // Fallback: derive original certificate by stripping suffix and looking up by certificate_number
            global $wpdb;
            $base_cert_number = preg_replace('/-0[12]$/', '', $certificate_number);
            $maybe_original = $wpdb->get_var($wpdb->prepare(
                "SELECT final_certification_id FROM {$wpdb->prefix}sgndt_final_certifications 
                 WHERE user_id = %d AND certificate_number = %s ORDER BY issue_date DESC LIMIT 1",
                $user_id,
                $base_cert_number
            ));
            if ($maybe_original) {
                $original_cert_id = intval($maybe_original);
            } else {
                return;
            }
        }
        
        // Clear renewal_status in database table (so "approved" status doesn't show after certificate is generated)
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'sgndt_final_certifications',
            array(
                'renewal_status' => NULL,
                'renewal_method' => NULL,
                'renewal_submitted_date' => NULL,
                'renewal_approved_date' => NULL,
                'renewal_submission_id' => NULL
            ),
            array('final_certification_id' => $original_cert_id),
            array('%s', '%s', '%s', '%s', '%d'),
            array('%d')
        );
        
        // Update the original certificate status to 'renewed' (user meta)
        $status_updated = update_certificate_status_by_id($user_id, $original_cert_id, 'renewed', [
            'renewed_cert_number' => $certificate_number,
            'renewed_cert_id' => $final_certification_id,
            'issue_date' => current_time('mysql')
        ]);
        
    }
}


/**
 * Handle renewal status update via AJAX
 */
add_action('wp_ajax_update_renewal_status', 'handle_renewal_status_update');
function handle_renewal_status_update() {
    if (!check_ajax_referer('update_renewal_status', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }

    $cert_number = isset($_POST['cert_number']) ? sanitize_text_field($_POST['cert_number']) : '';
    if (empty($cert_number)) {
        wp_send_json_error('Certificate number not provided');
        return;
    }

    // Update the submission status using new certificate-based key system
    $cert_status_key = 'cert_status_' . $cert_number;
    $submission_key = 'cert_submission_' . $cert_number;
    
    // Set both status systems for backward compatibility
    update_user_meta($user_id, $cert_status_key, 'submitted');
    update_user_meta($user_id, $cert_status_key . '_date', current_time('mysql'));
    update_user_meta($user_id, $submission_key, 'submitted');
    update_user_meta($user_id, $submission_key . '_date', current_time('mysql'));

    wp_send_json_success('Status updated successfully');
}

/**
 * Handle CPD certificate upload via AJAX (unchanged)
 */
add_action('wp_ajax_nopriv_update_cpd_certificate', 'update_cpd_certificate');
function update_cpd_certificate() {
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'You must be logged in to upload a CPD certificate.'));
    }

    $current_user = wp_get_current_user();

    if (isset($_FILES['cpd_cert']) && $_FILES['cpd_cert']['error'] == UPLOAD_ERR_OK) {
        $uploaded_file = wp_handle_upload($_FILES['cpd_cert'], array('test_form' => false));
        if (isset($uploaded_file['url'])) {
            update_user_meta($current_user->ID, 'cpd_cert', esc_url($uploaded_file['url']));
            wp_send_json_success(array('message' => 'CPD Certificate uploaded successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Error uploading file.'));
        }
    } else {
        wp_send_json_error(array('message' => 'No file uploaded.'));
    }
}


/**
 * Update certificate status by final_certification_id (for unique certificate tracking)
 */
function update_certificate_status_by_id($user_id, $cert_id, $new_status, $additional_data = []) {
    if (!$user_id || !$cert_id || !$new_status) {
        error_log("Invalid parameters for update_certificate_status_by_id: user_id={$user_id}, cert_id={$cert_id}, status={$new_status}");
        return false;
    }

    $cert_status_key = 'cert_status_id_' . $cert_id;

    // Get current status
    $current_status = get_user_meta($user_id, $cert_status_key, true);

    // Validate status transition
    $valid_transition = validate_status_transition($current_status, $new_status);

    error_log("  - Transition valid: " . ($valid_transition ? 'YES' : 'NO'));

    if (!$valid_transition) {
        error_log("Invalid status transition from '{$current_status}' to '{$new_status}' for certificate ID {$cert_id}");
        return false;
    }

    // Update status
    update_user_meta($user_id, $cert_status_key, $new_status);
    update_user_meta($user_id, $cert_status_key . '_date', current_time('mysql'));

    // Update additional data if provided
    if (!empty($additional_data)) {
        foreach ($additional_data as $meta_key => $meta_value) {
            update_user_meta($user_id, $cert_status_key . '_' . $meta_key, $meta_value);
        }
    }

    // Log status change
    error_log("Certificate ID {$cert_id} status updated: {$current_status} -> {$new_status} for user {$user_id}");

    return true;
}

/**
 * Update certificate status with proper validation and logging (legacy function)
 */
function update_certificate_status($user_id, $cert_number, $new_status, $additional_data = []) {
    if (!$user_id || !$cert_number || !$new_status) {
        error_log("Invalid parameters for update_certificate_status: user_id={$user_id}, cert_number={$cert_number}, status={$new_status}");
        return false;
    }

    $cert_status_key = 'cert_status_' . $cert_number;

    // Get current status
    $current_status = get_user_meta($user_id, $cert_status_key, true);

    // Debug logging
    error_log("DEBUG: Certificate {$cert_number} status update attempt:");
    error_log("  - Current status: '{$current_status}'");
    error_log("  - New status: '{$new_status}'");

    // Validate status transition
    $valid_transition = validate_status_transition($current_status, $new_status);

    error_log("  - Transition valid: " . ($valid_transition ? 'YES' : 'NO'));

    if (!$valid_transition) {
        error_log("Invalid status transition from '{$current_status}' to '{$new_status}' for certificate {$cert_number}");
        return false;
    }

    // Update status
    update_user_meta($user_id, $cert_status_key, $new_status);
    update_user_meta($user_id, $cert_status_key . '_date', current_time('mysql'));

    // Update additional data if provided
    if (!empty($additional_data)) {
        foreach ($additional_data as $meta_key => $meta_value) {
            update_user_meta($user_id, $cert_status_key . '_' . $meta_key, $meta_value);
        }
    }

    // Log status change
    error_log("Certificate {$cert_number} status updated: {$current_status} -> {$new_status} for user {$user_id}");

    return true;
}

/**
 * Validate status transition to prevent invalid state changes
 */
function validate_status_transition($current_status, $new_status) {
    $status_hierarchy = [
        'none' => 0,
        'submitted' => 1,
        'under_review' => 2,
        'reviewing' => 2,
        'approved' => 3,
        'certificate_issued' => 4,
        'renewed' => 4,
        'completed' => 5
    ];

    $current_priority = isset($status_hierarchy[$current_status]) ? $status_hierarchy[$current_status] : 0;
    $new_priority = isset($status_hierarchy[$new_status]) ? $status_hierarchy[$new_status] : 0;

    // Allow forward transitions and same status updates
    return $new_priority >= $current_priority;
}

/**
 * Get certificate status information by final_certification_id
 */
function get_certificate_status_info_by_id($user_id, $cert_id) {
    if (!$user_id || !$cert_id) {
        return false;
    }

    $cert_status_key = 'cert_status_id_' . $cert_id;
    $cert_status = get_user_meta($user_id, $cert_status_key, true);

    if (empty($cert_status)) {
        return false;
    }

    // Get status date
    $status_date = get_user_meta($user_id, $cert_status_key . '_date', true);
    $formatted_status_date = $status_date ? date('d/m/Y', strtotime($status_date)) : 'N/A';

    // Get additional status information
    $renewed_cert_number = get_user_meta($user_id, $cert_status_key . '_renewed_cert_number', true);
    $renewed_cert_id = get_user_meta($user_id, $cert_status_key . '_renewed_cert_id', true);
    $cert_number = get_user_meta($user_id, $cert_status_key . '_cert_number', true);
    
    // Get renewal method (submission_method) and convert to display format
    $submission_method = get_user_meta($user_id, $cert_status_key . '_submission_method', true);
    $renewal_method = '';
    if (!empty($submission_method)) {
        $renewal_method = ($submission_method === 'cpd_form') ? 'CPD' : 'EXAM';
    }

    return [
        'status' => $cert_status,
        'effective_status' => $cert_status,
        'status_source' => 'cert_status_id',
        'status_date' => $status_date,
        'formatted_status_date' => $formatted_status_date,
        'renewed_cert_number' => $renewed_cert_number,
        'renewed_cert_id' => $renewed_cert_id,
        'cert_number' => $cert_number,
        'renewal_method' => $renewal_method
    ];
}

/**
 * Get comprehensive certificate status information (legacy function)
 */
function get_certificate_status_info($user_id, $cert_number) {
    if (!$user_id || !$cert_number) {
        return false;
    }

    $cert_status_key = 'cert_status_' . $cert_number;
    $submission_key = 'cert_submission_' . $cert_number;

    $cert_status = get_user_meta($user_id, $cert_status_key, true);
    $submission_status = get_user_meta($user_id, $submission_key, true);

    // Determine effective status (prioritize certificate status over submission status)
   
    $effective_status = !empty($cert_status) ? $cert_status : $submission_status;
    $status_source = !empty($cert_status) ? 'cert_status' : 'submission_status';

    // Get status date
    $status_date_key = $status_source === 'cert_status' ? $cert_status_key . '_date' : $submission_key . '_date';
    $status_date = get_user_meta($user_id, $status_date_key, true);
    $formatted_status_date = $status_date ? date('d/m/Y', strtotime($status_date)) : 'N/A';

    // Get additional status information
    $cert_issued_number = get_user_meta($user_id, $cert_status_key . '_cert_number', true);
    $cert_issued_date = get_user_meta($user_id, $cert_status_key . '_issue_date', true);
    $formatted_cert_date = $cert_issued_date ? date('d/m/Y', strtotime($cert_issued_date)) : 'N/A';

    return [
        'effective_status' => $effective_status,
        'status_source' => $status_source,
        'status_date' => $status_date,
        'formatted_status_date' => $formatted_status_date,
        'cert_issued_number' => $cert_issued_number,
        'cert_issued_date' => $cert_issued_date,
        'formatted_cert_date' => $formatted_cert_date,
        'is_completed' => in_array($effective_status, ['approved', 'certificate_issued', 'completed'])
    ];
}

/**
 * Check if certificate renewal/recertification is eligible
 */
function is_certificate_eligible_for_renewal($cert_data) {
    if (empty($cert_data['issue_date']) || empty($cert_data['expiry_date'])) {
        return false;
    }

    try {
        $current_date = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $expiry_datetime = new DateTime($cert_data['expiry_date'], new DateTimeZone('Asia/Kolkata'));
        $issue_datetime = new DateTime($cert_data['issue_date'], new DateTimeZone('Asia/Kolkata'));

        $renewal_eligible_date = clone $expiry_datetime;
        $renewal_eligible_date->modify('-6 months');
        $renewal_deadline_date = clone $expiry_datetime;
        $renewal_deadline_date->modify('+12 months');

        $interval = $current_date->diff($issue_datetime);
        $years_since_issue = $interval->y + ($interval->m / 12);
        $is_recertification_cycle = $years_since_issue >= 10;

        if ($is_recertification_cycle) {
            return $current_date >= $renewal_eligible_date && $current_date <= $renewal_deadline_date;
        } else {
            return $current_date >= $renewal_eligible_date && $current_date <= $renewal_deadline_date;
        }
    } catch (Exception $e) {
        error_log("Error checking renewal eligibility for certificate {$cert_data['certificate_number']}: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate professional status display HTML
 */
function generate_status_display_html($status_info) {
    if (!$status_info || empty($status_info['effective_status'])) {
        return '<span class="status-pending">Status Unknown</span>';
    }

    $status = $status_info['effective_status'];
    $formatted_date = $status_info['formatted_status_date'];
    $cert_number = $status_info['cert_issued_number'];
    $cert_date = $status_info['formatted_cert_date'];

    switch ($status) {
        case 'submitted':
        case 'under_review':
        case 'reviewing':
            return '<div class="renewal-status-wrapper">' .
                   '<span class="status-reviewing">Under Review</span><br>' .
                   '<small>Submitted: ' . $formatted_date . '</small>' .
                   '</div>';

        case 'approved':
            return '<div class="renewal-status-wrapper">' .
                   '<span class="status-approved">✓ Renewal Approved</span><br>' .
                   '<small>Approved: ' . $formatted_date . '</small>' .
                   '</div>';

        case 'certificate_issued':
        case 'completed':
            return '<div class="renewal-status-wrapper">' .
                   '<span class="status-approved">✓ Certificate Issued</span><br>' .
                   '<small>New Cert: ' . ($cert_number ?: 'N/A') . '</small><br>' .
                   '<small>Issued: ' . $cert_date . '</small>' .
                   '</div>';

        default:
            return '<div class="renewal-status-wrapper">' .
                   '<span class="status-pending">' . ucfirst(str_replace('_', ' ', $status)) . '</span><br>' .
                   '<small>Status: ' . $formatted_date . '</small>' .
                   '</div>';
    }
}

/**
 * Display uploaded Gravity Forms certificates (unchanged)
 */
function get_gravity_forms_certificates($user_id) {
    $entry_id = get_user_meta($user_id, 'form_15_entry_id', true);

    if (!$entry_id) {
        return '<p>No certificates uploaded.</p>';
    }

    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry)) {
        return '<p>Error retrieving certificates.</p>';
    }

    $education_cert_field_id = 139;
    $eye_fitness_cert_field_id = 140;
    $experience_cert_field_id = 141;
    $training_cert_field_id = 142;

    $education_cert = rgar($entry, $education_cert_field_id);
    $experience_cert = rgar($entry, $experience_cert_field_id);
    $eye_fitness_cert = rgar($entry, $eye_fitness_cert_field_id);
    $training_cert = rgar($entry, $training_cert_field_id);

    ob_start();
    ?>
		<h4>Uploaded Certificates</h4>
		<p><strong>Education Certificate:</strong> 
			<?php echo $education_cert ? '<a href="' . esc_url($education_cert) . '" target="_blank">Download</a>' : 'Not Uploaded'; ?>
		</p>
		<p><strong>Experience Certificate:</strong> 
			<?php echo $experience_cert ? '<a href="' . esc_url($experience_cert) . '" target="_blank">Download</a>' : 'Not Uploaded'; ?>
		</p>
		<p><strong>Eye Fitness Certificate:</strong> 
			<?php echo $eye_fitness_cert ? '<a href="' . esc_url($eye_fitness_cert) . '" target="_blank">Download</a>' : 'Not Uploaded'; ?>
		</p>
		<p><strong>Training Certificate:</strong> 
			<?php echo $training_cert ? '<a href="' . esc_url($training_cert) . '" target="_blank">Download</a>' : 'Not Uploaded'; ?>
		</p>
		<?php
		return ob_get_clean();
}

add_action('wp_ajax_event_checkout_ajax', 'handle_event_checkout_ajax');
function handle_event_checkout_ajax() {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    if (!$user_id || !$event_id) {
        wp_send_json_error(['message' => 'Invalid user or event ID.']);
    }

    $check_in_time = get_user_meta($user_id, 'event_' . $event_id . '_check_in_time', true);
    if (!$check_in_time) {
        wp_send_json_error(['message' => 'User has not checked in for this event.']);
    }

    $check_out_time = current_time('mysql');
    $check_in_timestamp = strtotime($check_in_time);
    $check_out_timestamp = strtotime($check_out_time);
    $total_seconds = $check_out_timestamp - $check_in_timestamp;
    $total_hours_attended = round($total_seconds / 3600, 2);
    $total_minutes = round(($total_seconds % 3600) / 60);

    $time_spent_display = ($total_hours_attended >= 1) ? "{$total_hours_attended} hours" : "{$total_minutes} minutes";

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
    $tolerance = 0.01;

   if ($event_duration_hours > 0) {
    $attendance_percentage = ($total_hours_attended / $event_duration_hours) * 100;

    if ($attendance_percentage > 0 && $attendance_percentage <= 50) {
        $cpd_points = 0.5;
    } elseif ($attendance_percentage > 50) {
        $cpd_points = 1;
    } else {
        $cpd_points = 0; // In case no attendance
    }
}


    // Update CPD points
    $all_cpd_points = get_user_meta($user_id, 'total_cpd_points', true);
    if (!$all_cpd_points) {
        $all_cpd_points = 0;
    }
    $all_cpd_points += $cpd_points;

    update_user_meta($user_id, 'event_' . $event_id . '_check_out_time', $check_out_time);
    update_user_meta($user_id, 'event_' . $event_id . '_check_in_status', 'checked_out');
    update_user_meta($user_id, 'event_' . $event_id . '_total_hours', $total_hours_attended);
    update_user_meta($user_id, 'total_cpd_points', $all_cpd_points);
    update_user_meta($user_id, 'event_' . $event_id . '_cpd_points', $cpd_points);

    // Get user details for email
    $user_info = get_userdata($user_id);
    $user_email = $user_info->user_email;
    $user_name = $user_info->display_name;
    $event_name = get_the_title($event_id) ?: "Unknown Event";
    $admin_email = get_option('admin_email');

    // Company details
    $company_name = get_bloginfo('name');
    $profile_link = site_url('/user-profile');
    $admin_user_link = admin_url('/edit.php?post_type=tribe_events&page=attendee-management');

    // Email Subject & Message for User
    $user_subject = "CPD Points Awarded: $event_name";

    $user_message = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <h2 style="color: #0073aa;">CPD Points Awarded</h2>
            <p>Dear <strong>' . $user_name . '</strong>,</p>
            <p>We are pleased to inform you that you have been awarded <strong>' . $cpd_points . ' CPD points</strong> for your participation in:</p>
            <h3 style="color: #0073aa;">' . esc_html($event_name) . '</h3>
            <p>You attended for: <strong>' . esc_html($time_spent_display) . '</strong>.</p>
            <p>Your CPD points have been successfully updated.</p>
            <p><strong>Next Steps:</strong></p>
            <ul>
                <li>📌 <a href="' . esc_url($profile_link) . '" style="color: #0073aa; text-decoration: none;">Review your CPD points</a></li>
                <li>📅 Stay updated with upcoming events for more learning opportunities.</li>
            </ul>
            <p>Thank you for your participation. We look forward to seeing you at future events!</p>
            <p>Best regards,</p>
            <p><strong>' . $company_name . ' Team</strong></p>
        </div>
    ';

    // Email Subject & Message for Admin
    $admin_subject = "CPD Points Update: $user_name - $event_name";

    $admin_message = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <h2 style="color: #d35400;">CPD Points Awarded</h2>
            <p><strong>User:</strong> ' . esc_html($user_name) . '</p>
            <p><strong>Event:</strong> ' . esc_html($event_name) . '</p>
            <p><strong>Check-in Time:</strong> ' . esc_html($check_in_time) . '</p>
            <p><strong>Check-out Time:</strong> ' . esc_html($check_out_time) . '</p>
            <p><strong>Total Time Attended:</strong> ' . esc_html($time_spent_display) . '</p>
            <p><strong>CPD Points Earned:</strong> ' . $cpd_points . '</p>
            <p>The CPD points have been successfully recorded in the system.</p>
            <p><strong>Quick Actions:</strong></p>
            <ul>
                <li>👤 <a href="' . esc_url($admin_user_link) . '" style="color: #d35400; text-decoration: none;">View User Profile</a></li>
            </ul>
            <p>Best regards,</p>
            <p><strong>' . $company_name . ' Admin Team</strong></p>
        </div>
    ';

    // Get email templates
    $user_data = get_email_template($user_subject, $user_message);
    $admin_data = get_email_template($admin_subject, $admin_message);

    // Send Emails
    add_filter('wp_mail_content_type', function() { return 'text/html'; });
    wp_mail($user_email, $user_subject, $user_data);
    wp_mail($admin_email, $admin_subject, $admin_data);
    remove_filter('wp_mail_content_type', function() { return 'text/html'; });

    wp_send_json_success([
        'message' => 'Check-Out successful! Total time spent: ' . $time_spent_display . '. Earned CPD Points: ' . $cpd_points,
        'total_hours' => $time_spent_display,
        'cpd_points' => $cpd_points,
        'total_cpd_points' => $all_cpd_points
    ]);
}

/**
 * === UTILITY FUNCTIONS FOR CERTIFICATE STATUS MANAGEMENT ===
 */

/**
 * Quick test function - call this in your browser console or add to a test page
 * Usage: test_certificate_status_system();
 */
function test_certificate_status_system() {
    $user_id = get_current_user_id();
    $test_cert_number = 'TEST-001'; // Replace with actual certificate number

    if (!$user_id) {
        echo "❌ Please log in to test the status system.\n";
        return;
    }

    echo "🧪 === Certificate Status Management System Test ===\n\n";

    // Test 1: Get current status
    echo "1️⃣ Getting current status for certificate {$test_cert_number}:\n";
    $status_info = get_certificate_status_info($user_id, $test_cert_number);
    if ($status_info) {
        echo "   ✅ Effective Status: " . ($status_info['effective_status'] ?: 'None') . "\n";
        echo "   📊 Status Source: " . $status_info['status_source'] . "\n";
        echo "   📅 Status Date: " . $status_info['formatted_status_date'] . "\n";
        echo "   🎯 Is Completed: " . ($status_info['is_completed'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "   ❌ No status information found\n";
    }

    // Test 2: Update status to submitted
    echo "\n2️⃣ Updating status to 'submitted':\n";
    $success = update_certificate_status($user_id, $test_cert_number, 'submitted', [
        'submission_method' => 'test_form',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    echo "   " . ($success ? '✅' : '❌') . " Update successful: " . ($success ? 'Yes' : 'No') . "\n";

    // Test 3: Check status after update
    echo "\n3️⃣ Status after update:\n";
    $status_info = get_certificate_status_info($user_id, $test_cert_number);
    if ($status_info) {
        echo "   ✅ Effective Status: " . $status_info['effective_status'] . "\n";
        echo "   📅 Status Date: " . $status_info['formatted_status_date'] . "\n";
    }

    // Test 4: Try invalid status transition (should fail)
    echo "\n4️⃣ Testing invalid status transition (should fail):\n";
    $invalid_success = update_certificate_status($user_id, $test_cert_number, 'none');
    echo "   " . ($invalid_success ? '❌' : '✅') . " Invalid transition blocked: " . ($invalid_success ? 'No (ERROR)' : 'Yes (CORRECT)') . "\n";

    // Test 5: Valid forward transition to approved
    echo "\n5️⃣ Testing valid forward transition to 'approved':\n";
    $valid_success = update_certificate_status($user_id, $test_cert_number, 'approved', [
        'approved_by' => 'test_admin',
        'approval_date' => current_time('mysql')
    ]);
    echo "   " . ($valid_success ? '✅' : '❌') . " Valid transition successful: " . ($valid_success ? 'Yes' : 'No') . "\n";

    // Test 6: Final transition to certificate_issued
    echo "\n6️⃣ Testing certificate_issued transition:\n";
    $issue_success = update_certificate_status($user_id, $test_cert_number, 'certificate_issued', [
        'cert_number' => $test_cert_number . '-1',
        'issue_date' => current_time('mysql'),
        'issued_by' => 'test_admin'
    ]);
    echo "   " . ($issue_success ? '✅' : '❌') . " Certificate issued successfully: " . ($issue_success ? 'Yes' : 'No') . "\n";

    echo "\n🎯 === Test Complete ===\n";
    echo "📋 Check error logs for detailed status change information.\n";
    echo "🔍 Check user profile to see if status displays correctly.\n";


    // Test 1: Get current status
    echo "1. Getting current status for certificate {$test_cert_number}:\n";
    $status_info = get_certificate_status_info($user_id, $test_cert_number);
    if ($status_info) {
        echo "   - Effective Status: " . ($status_info['effective_status'] ?: 'None') . "\n";
        echo "   - Status Source: " . $status_info['status_source'] . "\n";
        echo "   - Status Date: " . $status_info['formatted_status_date'] . "\n";
        echo "   - Is Completed: " . ($status_info['is_completed'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "   - No status information found\n";
    }

    // Test 2: Update status to submitted
    echo "\n2. Updating status to 'submitted':\n";
    $success = update_certificate_status($user_id, $test_cert_number, 'submitted', [
        'submission_method' => 'form',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    echo "   - Update successful: " . ($success ? 'Yes' : 'No') . "\n";

    // Test 3: Check status after update
    echo "\n3. Status after update:\n";
    $status_info = get_certificate_status_info($user_id, $test_cert_number);
    if ($status_info) {
        echo "   - Effective Status: " . $status_info['effective_status'] . "\n";
        echo "   - Status Date: " . $status_info['formatted_status_date'] . "\n";
    }

    // Test 4: Try invalid status transition (should fail)
    echo "\n4. Testing invalid status transition (should fail):\n";
    $invalid_success = update_certificate_status($user_id, $test_cert_number, 'none');
    echo "   - Invalid transition allowed: " . ($invalid_success ? 'Yes (ERROR)' : 'No (CORRECT)') . "\n";

    // Test 5: Valid forward transition
    echo "\n5. Testing valid forward transition to 'approved':\n";
    $valid_success = update_certificate_status($user_id, $test_cert_number, 'approved', [
        'approved_by' => 'admin',
        'approval_date' => current_time('mysql')
    ]);
    echo "   - Valid transition successful: " . ($valid_success ? 'Yes' : 'No') . "\n";

    echo "\n=== Test Complete ===\n";
    echo "Check error logs for detailed status change information.\n";
}


/**
 * Utility function to get all certificate statuses for a user
 */
function get_user_certificate_statuses($user_id) {
    if (!$user_id) {
        return [];
    }

    $all_certificates = [];
    $user_meta = get_user_meta($user_id);

    foreach ($user_meta as $meta_key => $meta_value) {
        // Find certificate status keys
        if (strpos($meta_key, 'cert_status_') === 0 && !strpos($meta_key, '_date') && !strpos($meta_key, '_cert_number')) {
            $cert_number = str_replace('cert_status_', '', $meta_key);
            $status_info = get_certificate_status_info($user_id, $cert_number);

            if ($status_info) {
                $all_certificates[$cert_number] = $status_info;
            }
        }
    }

    return $all_certificates;
}

/**
 * Utility function to clean up old status keys (for migration purposes)
 */
function cleanup_old_status_keys($user_id, $cert_number) {
    if (!$user_id || !$cert_number) {
        return false;
    }

    $old_keys = [
        'renewal_status_' . $cert_number,
        'renewal_submission_' . $cert_number,
        'cert_submission_' . $cert_number
    ];

    $cleaned_count = 0;
    foreach ($old_keys as $old_key) {
        if (delete_user_meta($user_id, $old_key)) {
            $cleaned_count++;
        }
        if (delete_user_meta($user_id, $old_key . '_date')) {
            $cleaned_count++;
        }
    }

    error_log("Cleaned up {$cleaned_count} old status keys for certificate {$cert_number} and user {$user_id}");
    return $cleaned_count;
}

/**
 * Get certificate renewal eligibility summary
 */
function get_certificate_renewal_summary($user_id) {
    if (!$user_id) {
        return [];
    }

    // This would need to be implemented based on your certificate data structure
    // For now, returning a placeholder structure
    return [
        'total_certificates' => 0,
        'eligible_for_renewal' => 0,
        'eligible_for_recertification' => 0,
        'expired' => 0,
        'pending_renewal' => 0
    ];
}

/**
 * Batch update certificate statuses (useful for admin operations)
 */
function batch_update_certificate_status($user_ids, $cert_numbers, $new_status, $additional_data = []) {
    $results = [
        'successful' => [],
        'failed' => [],
        'errors' => []
    ];

    foreach ($user_ids as $user_id) {
        foreach ($cert_numbers as $cert_number) {
            $success = update_certificate_status($user_id, $cert_number, $new_status, $additional_data);

            if ($success) {
                $results['successful'][] = [
                    'user_id' => $user_id,
                    'cert_number' => $cert_number,
                    'status' => $new_status
                ];
            } else {
                $results['failed'][] = [
                    'user_id' => $user_id,
                    'cert_number' => $cert_number
                ];
            }
        }
    }

    $results['summary'] = [
        'total_attempted' => count($user_ids) * count($cert_numbers),
        'successful_count' => count($results['successful']),
        'failed_count' => count($results['failed'])
    ];

    error_log("Batch status update completed: " . print_r($results['summary'], true));
    return $results;
}

/**
 * === END OF UTILITY FUNCTIONS ===
 */

?>