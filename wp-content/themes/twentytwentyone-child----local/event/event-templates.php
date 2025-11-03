<?php
/**
 * Event Templates - Email templates and PDF generation for events
 * Handles email notifications and CPD report generation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get email template
 */
function event_get_email_template($template_name, $placeholders = []) {
    $templates = event_get_email_templates();
    
    if (!isset($templates[$template_name])) {
        event_log_error('Email template not found', [
            'template_name' => $template_name
        ]);
        return 'Template not found';
    }
    
    $template = $templates[$template_name];
    $content = $template['content'];
    
    // Replace placeholders
    foreach ($placeholders as $key => $value) {
        $content = str_replace('{' . $key . '}', $value, $content);
    }
    
    return $content;
}

/**
 * Get all email templates
 */
function event_get_email_templates() {
    return [
        'user_registration' => [
            'subject' => 'Event Registration Confirmation - {event_name}',
            'content' => '
                <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <h2 style="color: #0073aa;">Event Registration Confirmation</h2>
                    <p>Dear <strong>{user_name}</strong>,</p>
                    <p>Thank you for registering for the event: <strong>{event_name}</strong></p>
                    <p>Your registration is currently pending approval. You will receive a notification once your registration has been reviewed.</p>
                    <p><strong>Event Details:</strong></p>
                    <ul>
                        <li><strong>Event Name:</strong> {event_name}</li>
                        <li><strong>Event ID:</strong> {event_id}</li>
                    </ul>
                    <p>If you have any questions, please contact us.</p>
                    <p>Best regards,<br>Event Management Team</p>
                </div>
            '
        ],
        'admin_registration' => [
            'subject' => 'New Event Registration - {event_name}',
            'content' => '
                <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <h2 style="color: #d35400;">New Event Registration</h2>
                    <p>A new user has registered for an event:</p>
                    <ul>
                        <li><strong>User Name:</strong> {user_name}</li>
                        <li><strong>User Email:</strong> {user_email}</li>
                        <li><strong>Event Name:</strong> {event_name}</li>
                        <li><strong>Event ID:</strong> {event_id}</li>
                        <li><strong>Entry ID:</strong> {entry_id}</li>
                    </ul>
                    <p>Please review the registration in the admin panel.</p>
                    <p>Best regards,<br>Event Management System</p>
                </div>
            '
        ],
        'user_approval' => [
            'subject' => 'Event Registration Approved - {event_name}',
            'content' => '
                <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <h2 style="color: #46b450;">Event Registration Approved</h2>
                    <p>Dear <strong>{user_name}</strong>,</p>
                    <p>Congratulations! Your registration for the event <strong>{event_name}</strong> has been approved.</p>
                    <p><strong>Event Details:</strong></p>
                    <ul>
                        <li><strong>Event Name:</strong> {event_name}</li>
                        <li><strong>Event Start:</strong> {event_start}</li>
                        <li><strong>Event End:</strong> {event_end}</li>
                    </ul>
                    <p>Please find your QR code attached. Use it at the event entrance for verification.</p>
                    <p>We look forward to seeing you at the event!</p>
                    <p>Best regards,<br>Event Management Team</p>
                </div>
            '
        ],
        'admin_approval' => [
            'subject' => 'User Registration Approved - {event_name}',
            'content' => '
                <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <h2 style="color: #46b450;">User Registration Approved</h2>
                    <p>The following user has been approved for the event:</p>
                    <ul>
                        <li><strong>User Name:</strong> {user_name}</li>
                        <li><strong>User Email:</strong> {user_email}</li>
                        <li><strong>Event Name:</strong> {event_name}</li>
                        <li><strong>Event Start:</strong> {event_start}</li>
                        <li><strong>Event End:</strong> {event_end}</li>
                        <li><strong>Approved By:</strong> User ID {approver_id}</li>
                    </ul>
                    <p>Best regards,<br>Event Management System</p>
                </div>
            '
        ],
        'user_rejection' => [
            'subject' => 'Event Registration Rejected - {event_name}',
            'content' => '
                <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <h2 style="color: #dc3232;">Event Registration Rejected</h2>
                    <p>Dear <strong>{user_name}</strong>,</p>
                    <p>We regret to inform you that your registration for the event <strong>{event_name}</strong> has been rejected.</p>
                    <p><strong>Reason for rejection:</strong> {reject_reason}</p>
                    <p>If you have any questions or would like to discuss this further, please contact us.</p>
                    <p>Best regards,<br>Event Management Team</p>
                </div>
            '
        ],
        'admin_rejection' => [
            'subject' => 'User Registration Rejected - {event_name}',
            'content' => '
                <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <h2 style="color: #dc3232;">User Registration Rejected</h2>
                    <p>The following user registration has been rejected:</p>
                    <ul>
                        <li><strong>User Name:</strong> {user_name}</li>
                        <li><strong>User Email:</strong> {user_email}</li>
                        <li><strong>Event Name:</strong> {event_name}</li>
                        <li><strong>Reason:</strong> {reject_reason}</li>
                    </ul>
                    <p>Best regards,<br>Event Management System</p>
                </div>
            '
        ],
        'user_cpd_points' => [
            'subject' => 'CPD Points Awarded - {event_name}',
            'content' => '
                <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <h2 style="color: #0073aa;">CPD Points Awarded</h2>
                    <p>Dear <strong>{user_name}</strong>,</p>
                    <p>We are pleased to inform you that your CPD points have been successfully updated to a total of <strong>{cpd_points}</strong> in recognition of your participation in the following event:</p>
                    <h3 style="color: #0073aa;">{event_name}</h3>
                    <p>These points have been successfully added to your profile.</p>
                    <p><strong>Next Steps:</strong></p>
                    <ul>
                        <li>📌 Review your CPD points in your profile</li>
                        <li>📅 Keep track of upcoming events for more learning opportunities</li>
                    </ul>
                    <p>Thank you for your active participation. We look forward to your continued engagement!</p>
                    <p>Best regards,<br>Event Management Team</p>
                </div>
            '
        ],
        'admin_cpd_points' => [
            'subject' => 'CPD Points Updated - {event_name}',
            'content' => '
                <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                    <h2 style="color: #d35400;">CPD Points Updated</h2>
                    <p><strong>User:</strong> {user_name}</p>
                    <p><strong>Event:</strong> {event_name}</p>
                    <p><strong>CPD Points Awarded:</strong> {cpd_points}</p>
                    <p>The CPD points have been successfully updated in the system.</p>
                    <p>Best regards,<br>Event Management System</p>
                </div>
            '
        ]
    ];
}

/**
 * Generate CPD PDF report
 */
function event_generate_cpd_pdf() {
    try {
        @ini_set('display_errors', 0);
        require_once(ABSPATH . '/wp-content/themes/twentytwentyone-child/TCPDF/tcpdf.php');
        
        global $wpdb;
        
        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Event Management System');
        $pdf->SetTitle('CPD Points Report');
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        
        // Fetch data
        $users = $wpdb->get_results("
            SELECT um1.user_id, um1.meta_key AS event_checkin, um1.meta_value AS checkin_time, 
                   um2.meta_value AS cpd_points,
                   p.post_title AS event_name,
                   p.ID as event_id
            FROM {$wpdb->usermeta} um1
            LEFT JOIN {$wpdb->usermeta} um2 
                ON REPLACE(um1.meta_key, '_check_in_time', '_cpd_points') = um2.meta_key 
                AND um1.user_id = um2.user_id
            LEFT JOIN {$wpdb->posts} p 
                ON p.ID = REPLACE(REPLACE(um1.meta_key, 'event_', ''), '_check_in_time', '') 
            WHERE um1.meta_key LIKE 'event_%_check_in_time'
        ");
        
        $processed_data = [];
        foreach ($users as $user) {
            $user_id = $user->user_id;
            $event_name = $user->event_name ?: "Unknown Event";
            $event_id = $user->event_id ?: 0;
            $cpd = floatval($user->cpd_points);
            
            if (!isset($processed_data[$user_id])) {
                $processed_data[$user_id] = [
                    'user_id' => $user_id,
                    'events' => [],
                    'total_cpd' => 0
                ];
            }
            
            $processed_data[$user_id]['events'][$event_id] = $event_name;
            $processed_data[$user_id]['total_cpd'] += $cpd;
        }
        
        // Build table HTML
        $html = '
        <style>
            table {
                border-collapse: collapse;
                width: 100%;
                font-size: 12px;
            }
            th {
                background-color: #4CAF50;
                color: white;
                text-align: left;
                padding: 8px;
            }
            td {
                border: 1px solid #ddd;
                padding: 8px;
            }
        </style>
        <h2 style="text-align:center;">CPD Points Report</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Attended Events</th>
                    <th>Total CPD Points</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($processed_data as $user_id => $data) {
            $user_info = get_userdata($user_id);
            $user_name = $user_info ? $user_info->display_name : 'Unknown';
            $user_email = $user_info ? $user_info->user_email : 'Unknown';
            
            // List all events
            $events = '';
            foreach ($data['events'] as $event_id => $event_name) {
                $cpd_point = get_user_meta($user_id, 'event_'.$event_id.'_cpd_points', true);
                $cpd_point = $cpd_point === '' ? '0' : $cpd_point;
                $events .= htmlspecialchars($event_name) . '<br><span>' . $cpd_point . '</span> Points<br><br>';
            }
            
            $html .= '<tr>
                <td>' . htmlspecialchars($user_name) . '</td>
                <td>' . $user_email . '</td>   
                <td>' . $events . '</td>
                <td>' . $data['total_cpd'] . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        if (ob_get_length()) ob_end_clean();
        
        $pdf->Output('cpd_points_report.pdf', 'D');
        exit;
        
    } catch (Exception $e) {
        event_log_error('CPD PDF generation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    
    return [
        'success' => true,
        'data' => ['message' => 'PDF generated successfully']
    ];
}


