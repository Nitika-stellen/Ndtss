<?php
/**
 * Certificate Renewal/Recertification Admin Dashboard
 * 
 * Provides admin interface for managing certificate renewals and recertifications
 * 
 * @package SGNDT
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CertificateRenewalAdmin {
    
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
        add_action('admin_menu', array($this, 'addAdminMenu'));
        add_action('wp_ajax_approve_certificate_renewal', array($this, 'handleApproval'));
        add_action('wp_ajax_reject_certificate_renewal', array($this, 'handleRejection'));
        add_action('wp_ajax_generate_renewal_certificate', array($this, 'handleCertificateGeneration'));
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        add_menu_page(
            'Certificate Renewals',
            'Certificate Renewals',
            'manage_options',
            'certificate-renewals',
            array($this, 'adminPage'),
            'dashicons-awards',
            30
        );
        
        add_submenu_page(
            'certificate-renewals',
            'Pending Applications',
            'Pending Applications',
            'manage_options',
            'certificate-renewals',
            array($this, 'adminPage')
        );
        
        add_submenu_page(
            'certificate-renewals',
            'Approved Applications',
            'Approved Applications',
            'manage_options',
            'certificate-renewals-approved',
            array($this, 'approvedPage')
        );
        
        add_submenu_page(
            'certificate-renewals',
            'Rejected Applications',
            'Rejected Applications',
            'manage_options',
            'certificate-renewals-rejected',
            array($this, 'rejectedPage')
        );
    }
    
    /**
     * Main admin page - Pending applications
     */
    public function adminPage() {
        $applications = $this->getPendingApplications();
        
        ?>
        <div class="wrap">
            <h1>Certificate Renewal/Recertification Applications</h1>
            
            <div class="admin-notice">
                <p><strong>Instructions:</strong> Review applications below and approve or reject them. 
                Approved applications will be processed for certificate generation.</p>
            </div>
            
            <?php if (empty($applications)): ?>
                <div class="no-applications">
                    <p>No pending applications found.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Certificate Number</th>
                            <th>Type</th>
                            <th>Method</th>
                            <th>Level</th>
                            <th>Sector</th>
                            <th>Application Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $user = get_userdata($app['user_id']);
                                    echo esc_html($user->display_name);
                                    ?>
                                    <br><small><?php echo esc_html($user->user_email); ?></small>
                                </td>
                                <td><?php echo esc_html($app['certificate_number']); ?></td>
                                <td>
                                    <?php 
                                    $type = $app['cert_type'] === 'recertification' ? 'Recertification' : 'Renewal';
                                    echo esc_html($type);
                                    ?>
                                </td>
                                <td><?php echo esc_html($app['submission_method']); ?></td>
                                <td><?php echo esc_html($app['level']); ?></td>
                                <td><?php echo esc_html($app['sector']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($app['status_date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($app['status']); ?>">
                                        <?php echo esc_html(ucfirst($app['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="button button-primary approve-btn" 
                                                data-user-id="<?php echo $app['user_id']; ?>"
                                                data-cert-number="<?php echo esc_attr($app['certificate_number']); ?>"
                                                data-cert-id="<?php echo $app['cert_id']; ?>">
                                            Approve
                                        </button>
                                        <button class="button button-secondary reject-btn" 
                                                data-user-id="<?php echo $app['user_id']; ?>"
                                                data-cert-number="<?php echo esc_attr($app['certificate_number']); ?>"
                                                data-cert-id="<?php echo $app['cert_id']; ?>">
                                            Reject
                                        </button>
                                        <button class="button view-details-btn" 
                                                data-app-id="<?php echo $app['cert_id']; ?>">
                                            View Details
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <style>
        .admin-notice {
            background: #f0f8ff;
            border: 1px solid #0073aa;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .no-applications {
            text-align: center;
            padding: 40px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.status-submitted {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.status-reviewing {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .action-buttons .button {
            font-size: 12px;
            padding: 4px 8px;
            height: auto;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.approve-btn').on('click', function() {
                var userId = $(this).data('user-id');
                var certNumber = $(this).data('cert-number');
                var certId = $(this).data('cert-id');
                
                if (confirm('Are you sure you want to approve this application?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'approve_certificate_renewal',
                            user_id: userId,
                            cert_number: certNumber,
                            cert_id: certId,
                            nonce: '<?php echo wp_create_nonce('cert_renewal_admin'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Application approved successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + response.data);
                            }
                        }
                    });
                }
            });
            
            $('.reject-btn').on('click', function() {
                var userId = $(this).data('user-id');
                var certNumber = $(this).data('cert-number');
                var certId = $(this).data('cert-id');
                var reason = prompt('Please provide a reason for rejection:');
                
                if (reason !== null && reason.trim() !== '') {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'reject_certificate_renewal',
                            user_id: userId,
                            cert_number: certNumber,
                            cert_id: certId,
                            reason: reason,
                            nonce: '<?php echo wp_create_nonce('cert_renewal_admin'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Application rejected successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + response.data);
                            }
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Approved applications page
     */
    public function approvedPage() {
        $applications = $this->getApprovedApplications();
        
        ?>
        <div class="wrap">
            <h1>Approved Applications</h1>
            
            <?php if (empty($applications)): ?>
                <div class="no-applications">
                    <p>No approved applications found.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Certificate Number</th>
                            <th>Type</th>
                            <th>Method</th>
                            <th>Approved Date</th>
                            <th>Certificate Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $user = get_userdata($app['user_id']);
                                    echo esc_html($user->display_name);
                                    ?>
                                </td>
                                <td><?php echo esc_html($app['certificate_number']); ?></td>
                                <td>
                                    <?php 
                                    $type = $app['cert_type'] === 'recertification' ? 'Recertification' : 'Renewal';
                                    echo esc_html($type);
                                    ?>
                                </td>
                                <td><?php echo esc_html($app['submission_method']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($app['status_date'])); ?></td>
                                <td>
                                    <?php if ($app['certificate_generated']): ?>
                                        <span class="status-badge status-generated">Generated</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">Pending Generation</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$app['certificate_generated']): ?>
                                        <button class="button button-primary generate-cert-btn" 
                                                data-user-id="<?php echo $app['user_id']; ?>"
                                                data-cert-number="<?php echo esc_attr($app['certificate_number']); ?>"
                                                data-cert-id="<?php echo $app['cert_id']; ?>">
                                            Generate Certificate
                                        </button>
                                    <?php else: ?>
                                        <span class="generated-text">Certificate Generated</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.generate-cert-btn').on('click', function() {
                var userId = $(this).data('user-id');
                var certNumber = $(this).data('cert-number');
                var certId = $(this).data('cert-id');
                
                if (confirm('Generate certificate for this approved application?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'generate_renewal_certificate',
                            user_id: userId,
                            cert_number: certNumber,
                            cert_id: certId,
                            nonce: '<?php echo wp_create_nonce('cert_renewal_admin'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Certificate generated successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + response.data);
                            }
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Rejected applications page
     */
    public function rejectedPage() {
        $applications = $this->getRejectedApplications();
        
        ?>
        <div class="wrap">
            <h1>Rejected Applications</h1>
            
            <?php if (empty($applications)): ?>
                <div class="no-applications">
                    <p>No rejected applications found.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Certificate Number</th>
                            <th>Type</th>
                            <th>Method</th>
                            <th>Rejected Date</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $user = get_userdata($app['user_id']);
                                    echo esc_html($user->display_name);
                                    ?>
                                </td>
                                <td><?php echo esc_html($app['certificate_number']); ?></td>
                                <td>
                                    <?php 
                                    $type = $app['cert_type'] === 'recertification' ? 'Recertification' : 'Renewal';
                                    echo esc_html($type);
                                    ?>
                                </td>
                                <td><?php echo esc_html($app['submission_method']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($app['status_date'])); ?></td>
                                <td><?php echo esc_html($app['rejection_reason']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Get pending applications
     */
    private function getPendingApplications() {
        global $wpdb;
        
        $applications = array();
        
        // Get applications with submitted/reviewing status
        $meta_keys = $wpdb->get_results(
            "SELECT user_id, meta_key, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'cert_status_id_%' 
             AND meta_value IN ('submitted', 'reviewing')
             ORDER BY meta_key"
        );
        
        foreach ($meta_keys as $meta) {
            $cert_id = str_replace('cert_status_id_', '', $meta->meta_key);
            
            // Get certificate details
            $cert = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications 
                 WHERE final_certification_id = %d",
                $cert_id
            ));
            
            if ($cert) {
                $status_date = get_user_meta($meta->user_id, $meta->meta_key . '_date', true);
                $submission_method = get_user_meta($meta->user_id, $meta->meta_key . '_submission_method', true);
                $cert_type = get_user_meta($meta->user_id, $meta->meta_key . '_cert_type', true);
                
                $applications[] = array(
                    'user_id' => $meta->user_id,
                    'cert_id' => $cert_id,
                    'certificate_number' => $cert->certificate_number,
                    'level' => $cert->level,
                    'sector' => $cert->sector,
                    'status' => $meta->meta_value,
                    'status_date' => $status_date,
                    'submission_method' => $submission_method,
                    'cert_type' => $cert_type
                );
            }
        }
        
        return $applications;
    }
    
    /**
     * Get approved applications
     */
    private function getApprovedApplications() {
        global $wpdb;
        
        $applications = array();
        
        // Get applications with approved status
        $meta_keys = $wpdb->get_results(
            "SELECT user_id, meta_key, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'cert_status_id_%' 
             AND meta_value = 'approved'
             ORDER BY meta_key"
        );
        
        foreach ($meta_keys as $meta) {
            $cert_id = str_replace('cert_status_id_', '', $meta->meta_key);
            
            // Get certificate details
            $cert = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications 
                 WHERE final_certification_id = %d",
                $cert_id
            ));
            
            if ($cert) {
                $status_date = get_user_meta($meta->user_id, $meta->meta_key . '_date', true);
                $submission_method = get_user_meta($meta->user_id, $meta->meta_key . '_submission_method', true);
                $cert_type = get_user_meta($meta->user_id, $meta->meta_key . '_cert_type', true);
                $certificate_generated = get_user_meta($meta->user_id, $meta->meta_key . '_certificate_generated', true);
                
                $applications[] = array(
                    'user_id' => $meta->user_id,
                    'cert_id' => $cert_id,
                    'certificate_number' => $cert->certificate_number,
                    'status_date' => $status_date,
                    'submission_method' => $submission_method,
                    'cert_type' => $cert_type,
                    'certificate_generated' => $certificate_generated
                );
            }
        }
        
        return $applications;
    }
    
    /**
     * Get rejected applications
     */
    private function getRejectedApplications() {
        global $wpdb;
        
        $applications = array();
        
        // Get applications with rejected status
        $meta_keys = $wpdb->get_results(
            "SELECT user_id, meta_key, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'cert_status_id_%' 
             AND meta_value = 'rejected'
             ORDER BY meta_key"
        );
        
        foreach ($meta_keys as $meta) {
            $cert_id = str_replace('cert_status_id_', '', $meta->meta_key);
            
            // Get certificate details
            $cert = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications 
                 WHERE final_certification_id = %d",
                $cert_id
            ));
            
            if ($cert) {
                $status_date = get_user_meta($meta->user_id, $meta->meta_key . '_date', true);
                $submission_method = get_user_meta($meta->user_id, $meta->meta_key . '_submission_method', true);
                $cert_type = get_user_meta($meta->user_id, $meta->meta_key . '_cert_type', true);
                $rejection_reason = get_user_meta($meta->user_id, $meta->meta_key . '_rejection_reason', true);
                
                $applications[] = array(
                    'user_id' => $meta->user_id,
                    'cert_id' => $cert_id,
                    'certificate_number' => $cert->certificate_number,
                    'status_date' => $status_date,
                    'submission_method' => $submission_method,
                    'cert_type' => $cert_type,
                    'rejection_reason' => $rejection_reason
                );
            }
        }
        
        return $applications;
    }
    
    /**
     * Handle approval
     */
    public function handleApproval() {
        if (!check_ajax_referer('cert_renewal_admin', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id']);
        $cert_number = sanitize_text_field($_POST['cert_number']);
        $cert_id = intval($_POST['cert_id']);
        
        // Update certificate status to approved
        $status_updated = update_certificate_lifecycle_status($user_id, $cert_number, 'approved', array(
            'approved_by' => get_current_user_id(),
            'approval_date' => current_time('mysql'),
            'cert_id' => $cert_id
        ));
        
        if ($status_updated) {
            // Send approval email to user
            $this->sendApprovalEmail($user_id, $cert_number);
            
            wp_send_json_success('Application approved successfully');
        } else {
            wp_send_json_error('Failed to update status');
        }
    }
    
    /**
     * Handle rejection
     */
    public function handleRejection() {
        if (!check_ajax_referer('cert_renewal_admin', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id']);
        $cert_number = sanitize_text_field($_POST['cert_number']);
        $cert_id = intval($_POST['cert_id']);
        $reason = sanitize_text_field($_POST['reason']);
        
        // Update certificate status to rejected
        $status_updated = update_certificate_lifecycle_status($user_id, $cert_number, 'rejected', array(
            'rejected_by' => get_current_user_id(),
            'rejection_date' => current_time('mysql'),
            'rejection_reason' => $reason,
            'cert_id' => $cert_id
        ));
        
        if ($status_updated) {
            // Send rejection email to user
            $this->sendRejectionEmail($user_id, $cert_number, $reason);
            
            wp_send_json_success('Application rejected successfully');
        } else {
            wp_send_json_error('Failed to update status');
        }
    }
    
    /**
     * Handle certificate generation
     */
    public function handleCertificateGeneration() {
        if (!check_ajax_referer('cert_renewal_admin', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id']);
        $cert_number = sanitize_text_field($_POST['cert_number']);
        $cert_id = intval($_POST['cert_id']);
        
        // Generate new certificate
        $new_cert_number = CertificateLifecycleManager::getInstance()->generateCertificateNumber($cert_number, 'renewal');
        $issue_date = current_time('Y-m-d');
        $expiry_date = CertificateLifecycleManager::getInstance()->calculateExpiryDate($issue_date, 'renewal');
        
        // Insert new certificate record
        global $wpdb;
        $table = $wpdb->prefix . 'sgndt_final_certifications';
        
        $result = $wpdb->insert($table, array(
            'user_id' => $user_id,
            'certificate_number' => $new_cert_number,
            'issue_date' => $issue_date,
            'expiry_date' => $expiry_date,
            'status' => 'issued',
            'method' => 'Renewal',
            'level' => 'Level II',
            'sector' => 'General',
            'scope' => 'All'
        ));
        
        if ($result) {
            $new_cert_id = $wpdb->insert_id;
            
            // Update original certificate status
            update_certificate_lifecycle_status($user_id, $cert_number, 'renewed', array(
                'renewed_cert_number' => $new_cert_number,
                'renewed_cert_id' => $new_cert_id,
                'certificate_generated' => true,
                'cert_id' => $cert_id
            ));
            
            // Send certificate email to user
            $this->sendCertificateEmail($user_id, $new_cert_number);
            
            wp_send_json_success('Certificate generated successfully');
        } else {
            wp_send_json_error('Failed to generate certificate');
        }
    }
    
    /**
     * Send approval email
     */
    private function sendApprovalEmail($user_id, $cert_number) {
        $user = get_userdata($user_id);
        $subject = 'Certificate Renewal Application Approved';
        
        $message = "
        <h2>Application Approved</h2>
        <p>Dear {$user->display_name},</p>
        <p>Your certificate renewal application for certificate number <strong>{$cert_number}</strong> has been approved.</p>
        <p>Your new certificate will be generated and sent to you shortly.</p>
        <p>Thank you for your patience.</p>
        <p>Best regards,<br>SGNDT Team</p>
        ";
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send rejection email
     */
    private function sendRejectionEmail($user_id, $cert_number, $reason) {
        $user = get_userdata($user_id);
        $subject = 'Certificate Renewal Application Rejected';
        
        $message = "
        <h2>Application Rejected</h2>
        <p>Dear {$user->display_name},</p>
        <p>Your certificate renewal application for certificate number <strong>{$cert_number}</strong> has been rejected.</p>
        <p><strong>Reason:</strong> {$reason}</p>
        <p>Please review the requirements and submit a new application if eligible.</p>
        <p>Best regards,<br>SGNDT Team</p>
        ";
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send certificate email
     */
    private function sendCertificateEmail($user_id, $cert_number) {
        $user = get_userdata($user_id);
        $subject = 'New Certificate Generated';
        
        $message = "
        <h2>Certificate Generated</h2>
        <p>Dear {$user->display_name},</p>
        <p>Your new certificate <strong>{$cert_number}</strong> has been generated successfully.</p>
        <p>You can download your certificate from your profile page.</p>
        <p>Best regards,<br>SGNDT Team</p>
        ";
        
        wp_mail($user->user_email, $subject, $message);
    }
}

// Initialize admin dashboard
if (is_admin()) {
    CertificateRenewalAdmin::getInstance();
}
