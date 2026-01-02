<?php
/**
 * CPD Management System
 * 
 * Handles Continuous Professional Development (CPD) attendance records,
 * point allocation, annual reports, and reminders.
 * 
 * @package CPD_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include email logger
require_once get_stylesheet_directory() . '/cpd-management/cpd-email-logger.php';

class CPD_Manager {
    
    private static $instance = null;
    private $table_name;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sgndt_cpd_entries';
        
        // Initialize hooks
        $this->init_hooks();
        
        // Create database table on init (will check if exists)
        add_action('init', array($this, 'create_table_once'));
    }
    
    /**
     * Create table once (safe to call multiple times)
     */
    public function create_table_once() {
        // Only create if table doesn't exist
        global $wpdb;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") !== $this->table_name) {
            $this->create_table();
        }
    }
    
    /**
     * Add yearly schedule for cron (if needed)
     */
    public function add_yearly_schedule($schedules) {
        if (!isset($schedules['yearly'])) {
            $schedules['yearly'] = array(
                'interval' => YEAR_IN_SECONDS,
                'display' => __('Once Yearly')
            );
        }
        return $schedules;
    }
    
    /**
     * Check if current user can access CPD management
     * Only administrators and super admin (by email) can access
     */
    public function user_can_access_cpd_admin() {
        // Check if user is administrator
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Check if user's email matches super admin email in option
        $current_user = wp_get_current_user();
        if ($current_user && $current_user->user_email) {
            // Check both admin_email (WordPress default) and cpd_super_admin_email (custom)
            $super_admin_email = get_option('admin_email', '');
            $cpd_super_admin_email = get_option('cpd_super_admin_email', '');
            
            if ($current_user->user_email === $super_admin_email || 
                $current_user->user_email === $cpd_super_admin_email) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add yearly schedule for cron (if needed)
        add_filter('cron_schedules', array($this, 'add_yearly_schedule'));
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // AJAX handlers - Frontend (logged in users)
        add_action('wp_ajax_cpd_upload_entry', array($this, 'handle_upload_entry'));
        add_action('wp_ajax_cpd_delete_entry', array($this, 'handle_delete_entry'));
        add_action('wp_ajax_cpd_get_user_entries', array($this, 'handle_get_user_entries'));
        
        // AJAX handlers - Admin
        add_action('wp_ajax_cpd_update_points', array($this, 'handle_update_points'));
        
        // Annual report cron job - check daily if it's January 1st
        add_action('cpd_annual_report_cron', array($this, 'check_and_generate_annual_reports'));
        
        // Shortcode for user upload form
        add_shortcode('cpd_upload_form', array($this, 'render_upload_form'));
        
        // Shortcode for user CPD summary
        add_shortcode('cpd_summary', array($this, 'render_user_summary'));
    }
    
    /**
     * Create database table
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            cpd_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            activity_title VARCHAR(255) NOT NULL,
            activity_date DATE NOT NULL,
            activity_type VARCHAR(100) DEFAULT NULL,
            activity_category VARCHAR(50) DEFAULT NULL,
            description TEXT,
            uploaded_file_url VARCHAR(500) DEFAULT NULL,
            points_requested DECIMAL(10,2) DEFAULT 0.00,
            points_allocated DECIMAL(10,2) DEFAULT 0.00,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            reviewed_by BIGINT(20) UNSIGNED DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            review_notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (cpd_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY activity_date (activity_date),
            KEY activity_category (activity_category)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create annual report log table
        $report_table = $wpdb->prefix . 'sgndt_cpd_annual_reports';
        $report_sql = "CREATE TABLE IF NOT EXISTS {$report_table} (
            report_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            report_year YEAR NOT NULL,
            total_points DECIMAL(10,2) DEFAULT 0.00,
            points_needed DECIMAL(10,2) DEFAULT 150.00,
            report_generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            report_sent_at DATETIME DEFAULT NULL,
            PRIMARY KEY (report_id),
            UNIQUE KEY user_year (user_id, report_year),
            KEY user_id (user_id),
            KEY report_year (report_year)
        ) $charset_collate;";
        
        dbDelta($report_sql);
        
        // Schedule annual report cron if not exists - check daily
        if (!wp_next_scheduled('cpd_annual_report_cron')) {
            // Run daily at 9:00 AM (will check if it's January 1st)
            $next_run = strtotime('tomorrow 09:00');
            wp_schedule_event($next_run, 'daily', 'cpd_annual_report_cron');
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Only show menu to administrators and super admin
        if (!$this->user_can_access_cpd_admin()) {
            return;
        }
        
        add_menu_page(
            'CPD Management',
            'CPD Management',
            'read', // Changed to 'read' since we check access in user_can_access_cpd_admin
            'cpd-management',
            array($this, 'render_admin_dashboard'),
            'dashicons-clipboard',
            26
        );
        
        add_submenu_page(
            'cpd-management',
            'Pending Reviews',
            'Pending Reviews',
            'read',
            'cpd-pending-reviews',
            array($this, 'render_pending_reviews')
        );
        
        add_submenu_page(
            'cpd-management',
            'All Entries',
            'All Entries',
            'read',
            'cpd-all-entries',
            array($this, 'render_all_entries')
        );
        
        add_submenu_page(
            'cpd-management',
            'Annual Reports',
            'Annual Reports',
            'read',
            'cpd-annual-reports',
            array($this, 'render_annual_reports')
        );
        
        add_submenu_page(
            'cpd-management',
            'CPD Settings',
            'Settings',
            'read',
            'cpd-settings',
            array($this, 'render_settings')
        );
        
        add_submenu_page(
            'cpd-management',
            'Email Templates',
            'Email Templates',
            'read',
            'cpd-email-templates',
            array($this, 'render_email_templates')
        );
        
        add_submenu_page(
            'cpd-management',
            'Email Logs',
            'Email Logs',
            'read',
            'cpd-email-logs',
            array($this, 'render_email_logs')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Check if it's a CPD management page
        $cpd_pages = [
            'toplevel_page_cpd-management',
            'cpd-management_page_cpd-pending-reviews',
            'cpd-management_page_cpd-all-entries',
            'cpd-management_page_cpd-annual-reports',
            'cpd-management_page_cpd-settings',
            'cpd-management_page_cpd-email-templates',
            'cpd-management_page_cpd-email-logs'
        ];
        
        if (!in_array($hook, $cpd_pages) && strpos($hook, 'cpd-') === false) {
            return;
        }
        
        // Enqueue SweetAlert2
        wp_enqueue_style(
            'sweetalert2-css',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css',
            array(),
            '11'
        );
        
        wp_enqueue_script(
            'sweetalert2-js',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11',
            array(),
            '11',
            true
        );
        
        wp_enqueue_style(
            'cpd-admin-css',
            get_stylesheet_directory_uri() . '/cpd-management/css/cpd-admin.css',
            array(),
            filemtime(get_stylesheet_directory() . '/cpd-management/css/cpd-admin.css')
        );
        
        wp_enqueue_script(
            'cpd-admin-js',
            get_stylesheet_directory_uri() . '/cpd-management/js/cpd-admin.js',
            array('jquery', 'sweetalert2-js'),
            filemtime(get_stylesheet_directory() . '/cpd-management/js/cpd-admin.js'),
            true
        );
        
        wp_localize_script('cpd-admin-js', 'cpdAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cpd_admin_nonce'),
            'strings' => array(
                'confirmDelete' => 'Are you sure you want to delete this entry?',
                'confirmReject' => 'Are you sure you want to reject this entry?',
                'confirmRejectTitle' => 'Reject Entry?',
                'confirmDeleteTitle' => 'Delete Entry?',
                'saving' => 'Saving...',
                'saved' => 'Saved successfully!',
                'error' => 'An error occurred. Please try again.',
                'successTitle' => 'Success!',
                'errorTitle' => 'Error!',
                'warningTitle' => 'Warning!',
                'infoTitle' => 'Information'
            )
        ));
        
        // Enqueue email template assets
        if ($hook === 'cpd-management_page_cpd-email-templates') {
            wp_enqueue_style(
                'cpd-email-templates-css',
                get_stylesheet_directory_uri() . '/cpd-management/css/cpd-email-templates.css',
                array(),
                filemtime(get_stylesheet_directory() . '/cpd-management/css/cpd-email-templates.css')
            );
            
            wp_enqueue_script(
                'cpd-email-templates-js',
                get_stylesheet_directory_uri() . '/cpd-management/js/cpd-email-templates.js',
                array(),
                filemtime(get_stylesheet_directory() . '/cpd-management/js/cpd-email-templates.js'),
                true
            );
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (is_user_logged_in()) {
            wp_enqueue_style(
                'cpd-frontend-css',
                get_stylesheet_directory_uri() . '/cpd-management/css/cpd-frontend.css',
                array(),
                filemtime(get_stylesheet_directory() . '/cpd-management/css/cpd-frontend.css')
            );
            
            wp_enqueue_script(
                'cpd-frontend-js',
                get_stylesheet_directory_uri() . '/cpd-management/js/cpd-frontend.js',
                array('jquery'),
                filemtime(get_stylesheet_directory() . '/cpd-management/js/cpd-frontend.js'),
                true
            );
            
            wp_localize_script('cpd-frontend-js', 'cpdFrontend', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cpd_frontend_nonce'),
                'strings' => array(
                    'uploading' => 'Uploading...',
                    'uploaded' => 'Uploaded successfully!',
                    'error' => 'An error occurred. Please try again.',
                    'confirmDelete' => 'Are you sure you want to delete this entry?'
                )
            ));
        }
    }
    
    /**
     * Render admin dashboard
     */
    public function render_admin_dashboard() {
        if (!$this->user_can_access_cpd_admin()) {
            wp_die('You do not have permission to access this page.');
        }
        require_once get_stylesheet_directory() . '/cpd-management/templates/admin-dashboard.php';
    }
    
    /**
     * Render pending reviews page
     */
    public function render_pending_reviews() {
        if (!$this->user_can_access_cpd_admin()) {
            wp_die('You do not have permission to access this page.');
        }
        require_once get_stylesheet_directory() . '/cpd-management/templates/pending-reviews.php';
    }
    
    /**
     * Render all entries page
     */
    public function render_all_entries() {
        if (!$this->user_can_access_cpd_admin()) {
            wp_die('You do not have permission to access this page.');
        }
        require_once get_stylesheet_directory() . '/cpd-management/templates/all-entries.php';
    }
    
    /**
     * Render annual reports page
     */
    public function render_annual_reports() {
        if (!$this->user_can_access_cpd_admin()) {
            wp_die('You do not have permission to access this page.');
        }
        require_once get_stylesheet_directory() . '/cpd-management/templates/annual-reports.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings() {
        if (!$this->user_can_access_cpd_admin()) {
            wp_die('You do not have permission to access this page.');
        }
        require_once get_stylesheet_directory() . '/cpd-management/templates/settings.php';
    }
    
    /**
     * Render email templates page
     */
    public function render_email_templates() {
        if (!$this->user_can_access_cpd_admin()) {
            wp_die('You do not have permission to access this page.');
        }
        require_once get_stylesheet_directory() . '/cpd-management/templates/email-templates.php';
    }
    
    /**
     * Render email logs page
     */
    public function render_email_logs() {
        if (!$this->user_can_access_cpd_admin()) {
            wp_die('You do not have permission to access this page.');
        }
        require_once get_stylesheet_directory() . '/cpd-management/templates/email-logs.php';
    }
    
    /**
     * Render upload form shortcode
     */
    public function render_upload_form($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to upload CPD entries.</p>';
        }
        
        ob_start();
        require_once get_stylesheet_directory() . '/cpd-management/templates/upload-form.php';
        return ob_get_clean();
    }
    
    /**
     * Render user summary shortcode
     */
    public function render_user_summary($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your CPD summary.</p>';
        }
        
        $user_id = get_current_user_id();
        // Check for year in GET parameter first, then shortcode attribute, then default to current year
        $year = isset($_GET['year']) ? intval($_GET['year']) : (isset($atts['year']) ? intval($atts['year']) : date('Y'));
        
        ob_start();
        require_once get_stylesheet_directory() . '/cpd-management/templates/user-summary.php';
        return ob_get_clean();
    }
    
    /**
     * Handle upload entry AJAX
     */
    public function handle_upload_entry() {
        check_ajax_referer('cpd_frontend_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }
        
        $user_id = get_current_user_id();
        $activity_date = sanitize_text_field($_POST['activity_date'] ?? '');
        $activity_category = sanitize_text_field($_POST['activity_category'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $points_requested = floatval($_POST['points_requested'] ?? 0);
        
        // Validate required fields
        if (empty($activity_date)) {
            wp_send_json_error(array('message' => 'Activity date is required.'));
        }
        
        // Handle file upload
        $file_url = '';
        $file_name = '';
        if (!empty($_FILES['cpd_document']['name'])) {
            $upload = $this->handle_file_upload('cpd_document', $user_id);
            if (is_wp_error($upload)) {
                wp_send_json_error(array('message' => $upload->get_error_message()));
            }
            $file_url = $upload['url'];
            $file_name = basename($upload['file']);
        }
        
        // Generate activity title from file name or use default
        $activity_title = '';
        if (!empty($file_name)) {
            // Remove file extension and use as title
            $activity_title = pathinfo($file_name, PATHINFO_FILENAME);
        } elseif (!empty($description)) {
            // Use first 50 characters of description as title
            $activity_title = wp_trim_words($description, 8, '...');
        } else {
            // Default title based on date and category
            $activity_title = 'CPD Entry - ' . date('M j, Y', strtotime($activity_date));
            if (!empty($activity_category)) {
                $activity_title .= ' (' . $activity_category . ')';
            }
        }
        
        // Insert into database
        global $wpdb;
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'user_id' => $user_id,
                'activity_title' => $activity_title,
                'activity_date' => $activity_date,
                'activity_type' => '', // Removed - no longer used
                'activity_category' => $activity_category,
                'description' => $description,
                'uploaded_file_url' => $file_url,
                'points_requested' => $points_requested,
                'status' => 'pending'
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Failed to save entry.'));
        }
        
        $entry_id = $wpdb->insert_id;
        
        // Send notifications to admins
        $this->send_upload_notification($entry_id, $user_id);
        
        // Send acknowledgement to user if enabled
        $user_ack_enabled = get_option('user_entry_submitted_enabled', 'yes');
        if ($user_ack_enabled === 'yes') {
            $this->send_user_submission_acknowledgement($entry_id, $user_id);
        } else {
            // Log skipped email
            $user = get_userdata($user_id);
            if ($user) {
                cpd_log_email_skipped('user_entry_submitted', $user->user_email, $user->display_name, 'CPD Entry Submitted Successfully', 'Email notification disabled', [
                    'entry_id' => $entry_id,
                    'user_id' => $user_id
                ]);
            }
        }
        
        wp_send_json_success(array(
            'message' => 'CPD entry uploaded successfully!',
            'entry_id' => $entry_id
        ));
    }
    
    /**
     * Send submission acknowledgement to user
     */
    private function send_user_submission_acknowledgement($entry_id, $user_id) {
        global $wpdb;
        
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE cpd_id = %d",
            $entry_id
        ));
        
        if (!$entry) {
            return;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        // Get email template
        $template = array(
            'subject' => get_option('cpd_user_entry_submitted_subject', 'CPD Entry Submitted Successfully'),
            'heading' => get_option('cpd_user_entry_submitted_heading', 'CPD Entry Submitted'),
            'message' => get_option('cpd_user_entry_submitted_message', '')
        );
        
        // Replace placeholders
        $replacements = $this->get_email_replacements($entry, $user);
        $subject = $this->replace_placeholders($template['subject'], $replacements);
        $heading = $this->replace_placeholders($template['heading'], $replacements);
        $message = $this->replace_placeholders($template['message'], $replacements);
        
        // If no custom message, use default
        if (empty($message)) {
            $message = $this->format_email_template(
                $heading ?: 'CPD Entry Submitted',
                '<p>Dear {user_name},</p><p>Your CPD entry has been submitted successfully and is pending review.</p><p>We will notify you once your entry has been reviewed.</p>'
            );
            $message = $this->replace_placeholders($message, $replacements);
        } else {
            $message = $this->format_email_template($heading, $message);
        }
        
        // If no custom subject, use default
        if (empty($subject)) {
            $subject = 'CPD Entry Submitted Successfully';
        }
        
        // Capture wp_mail errors
        $wp_mail_error = null;
        $error_handler = function($wp_error) use (&$wp_mail_error) {
            if (is_wp_error($wp_error)) {
                $wp_mail_error = array(
                    'message' => $wp_error->get_error_message(),
                    'data' => $wp_error->get_error_data()
                );
            } else {
                $wp_mail_error = $wp_error;
            }
        };
        add_action('wp_mail_failed', $error_handler);
        
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        $mail_result = wp_mail($user->user_email, $subject, $message);
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
        remove_action('wp_mail_failed', $error_handler);
        
        // Get actual error message if available
        $error_message = 'wp_mail returned false';
        if (!$mail_result && $wp_mail_error) {
            if (is_array($wp_mail_error)) {
                $error_message = $wp_mail_error['message'] ?: $error_message;
                if (!empty($wp_mail_error['data'])) {
                    $error_message .= ' | Data: ' . wp_json_encode($wp_mail_error['data']);
                }
            } else {
                $error_message = $wp_mail_error;
            }
        } elseif (!$mail_result) {
            global $phpmailer;
            if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                $error_message = $phpmailer->ErrorInfo;
            } else {
                $error_message = 'wp_mail returned false (likely localhost/email configuration issue)';
            }
        }
        
        // Log email
        if ($mail_result) {
            cpd_log_email_sent('user_entry_submitted', $user->user_email, $user->display_name, $subject, [
                'entry_id' => $entry_id,
                'user_id' => $user_id,
                'activity_title' => $entry->activity_title,
                'activity_date' => $entry->activity_date
            ]);
        } else {
            cpd_log_email_failed('user_entry_submitted', $user->user_email, $user->display_name, $subject, $error_message, [
                'entry_id' => $entry_id,
                'user_id' => $user_id
            ]);
        }
    }
    
    /**
     * Handle file upload
     */
    private function handle_file_upload($field_name, $user_id) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $uploadedfile = $_FILES[$field_name];
        $upload_overrides = array('test_form' => false);
        
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            return $movefile;
        } else {
            return new WP_Error('upload_error', $movefile['error']);
        }
    }
    
    /**
     * Send upload notification to admins
     */
    private function send_upload_notification($entry_id, $user_id) {
        global $wpdb;
        
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE cpd_id = %d",
            $entry_id
        ));
        
        if (!$entry) {
            return;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        // Check if admin notification is enabled
        $admin_enabled = get_option('admin_new_entry_enabled', 'yes');
        if ($admin_enabled !== 'yes') {
            // Log skipped email
            cpd_log_email_skipped('admin_new_entry', '', 'All Admins', 'New CPD Entry Notification', 'Email notification disabled', [
                'entry_id' => $entry_id,
                'user_id' => $user_id
            ]);
            return;
        }
        
        // Get email template
        $template = array(
            'subject' => get_option('cpd_admin_new_entry_subject', 'New CPD Entry Submitted: {activity_title}'),
            'heading' => get_option('cpd_admin_new_entry_heading', 'New CPD Entry Submitted'),
            'message' => get_option('cpd_admin_new_entry_message', '')
        );
        
        // Replace placeholders
        $replacements = $this->get_email_replacements($entry, $user);
        $subject = $this->replace_placeholders($template['subject'], $replacements);
        $heading = $this->replace_placeholders($template['heading'], $replacements);
        $message = $this->replace_placeholders($template['message'], $replacements);
        
        // If no custom message, use default template
        if (empty($message)) {
            $message = $this->get_upload_notification_email($entry, $user);
        } else {
            $message = $this->format_email_template($heading, $message);
        }
        
        // Get all administrators and super admin
        $admin_users = get_users(array(
            'role' => 'administrator',
            'fields' => array('ID', 'user_email', 'display_name')
        ));
        
        // Get super admin email from WordPress options (admin_email or cpd_super_admin_email)
        $super_admin_email = get_option('admin_email', ''); // WordPress default
        $cpd_super_admin_email = get_option('cpd_super_admin_email', ''); // Custom option if exists
        
        $emails = array();
        foreach ($admin_users as $admin) {
            if (is_email($admin->user_email)) {
                $emails[] = $admin->user_email;
            }
        }
        
        // Add super admin email(s) if they exist and are valid
        if (!empty($super_admin_email) && is_email($super_admin_email) && !in_array($super_admin_email, $emails)) {
            $emails[] = $super_admin_email;
        }
        
        if (!empty($cpd_super_admin_email) && is_email($cpd_super_admin_email) && !in_array($cpd_super_admin_email, $emails)) {
            $emails[] = $cpd_super_admin_email;
        }
        
        $emails = array_unique($emails);
        
        // Send email to all admins
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        foreach ($emails as $email) {
            // Capture wp_mail errors
            $wp_mail_error = null;
            $error_handler = function($wp_error) use (&$wp_mail_error) {
                if (is_wp_error($wp_error)) {
                    $wp_mail_error = array(
                        'message' => $wp_error->get_error_message(),
                        'data' => $wp_error->get_error_data()
                    );
                } else {
                    $wp_mail_error = $wp_error;
                }
            };
            add_action('wp_mail_failed', $error_handler);
            
            $mail_result = wp_mail($email, $subject, $message);
            remove_action('wp_mail_failed', $error_handler);
            
            // Get actual error message if available
            $error_message = 'wp_mail returned false';
            if (!$mail_result && $wp_mail_error) {
                if (is_array($wp_mail_error)) {
                    $error_message = $wp_mail_error['message'] ?: $error_message;
                    if (!empty($wp_mail_error['data'])) {
                        $error_message .= ' | Data: ' . wp_json_encode($wp_mail_error['data']);
                    }
                } else {
                    $error_message = $wp_mail_error;
                }
            } elseif (!$mail_result) {
                global $phpmailer;
                if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                    $error_message = $phpmailer->ErrorInfo;
                } else {
                    $error_message = 'wp_mail returned false (likely localhost/email configuration issue)';
                }
            }
            
            // Log email
            $recipient_user = get_user_by('email', $email);
            $recipient_name = $recipient_user ? $recipient_user->display_name : $email;
            
            if ($mail_result) {
                cpd_log_email_sent('admin_new_entry', $email, $recipient_name, $subject, [
                    'entry_id' => $entry_id,
                    'user_id' => $user_id,
                    'activity_title' => $entry->activity_title,
                    'activity_date' => $entry->activity_date
                ]);
            } else {
                cpd_log_email_failed('admin_new_entry', $email, $recipient_name, $subject, $error_message, [
                    'entry_id' => $entry_id,
                    'user_id' => $user_id
                ]);
            }
        }
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
    }
    
    /**
     * Get upload notification email template
     */
    private function get_upload_notification_email($entry, $user) {
        $admin_url = admin_url('admin.php?page=cpd-pending-reviews');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #0073aa; }
                .button { display: inline-block; padding: 12px 24px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>New CPD Entry Submitted</h2>
                </div>
                <div class="content">
                    <p>A new CPD entry has been submitted and requires your review.</p>
                    
                    <div class="details">
                        <h3>Entry Details</h3>
                        <p><strong>Candidate:</strong> <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)</p>
                        <p><strong>Activity Title:</strong> <?php echo esc_html($entry->activity_title); ?></p>
                        <p><strong>Activity Date:</strong> <?php echo esc_html(date('F j, Y', strtotime($entry->activity_date))); ?></p>
                        <?php if ($entry->activity_category): ?>
                        <p><strong>Category:</strong> <?php echo esc_html($entry->activity_category); ?></p>
                        <?php endif; ?>
                        <?php if ($entry->points_requested > 0): ?>
                        <p><strong>Points Requested:</strong> <?php echo esc_html($entry->points_requested); ?></p>
                        <?php endif; ?>
                        <?php if ($entry->description): ?>
                        <p><strong>Description:</strong><br><?php echo nl2br(esc_html($entry->description)); ?></p>
                        <?php endif; ?>
                        <?php if ($entry->uploaded_file_url): ?>
                        <p><strong>Document:</strong> <a href="<?php echo esc_url($entry->uploaded_file_url); ?>" target="_blank">View Document</a></p>
                        <?php endif; ?>
                    </div>
                    
                    <a href="<?php echo esc_url($admin_url); ?>" class="button">Review Entry</a>
                    
                    <p><small>This is an automated notification from the CPD Management System.</small></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle update points AJAX
     */
    public function handle_update_points() {
        check_ajax_referer('cpd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission.'));
        }
        
        $entry_id = intval($_POST['entry_id'] ?? 0);
        $points_allocated = floatval($_POST['points_allocated'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? 'pending');
        $review_notes = sanitize_textarea_field($_POST['review_notes'] ?? '');
        
        global $wpdb;
        $result = $wpdb->update(
            $this->table_name,
            array(
                'points_allocated' => $points_allocated,
                'status' => $status,
                'reviewed_by' => get_current_user_id(),
                'reviewed_at' => current_time('mysql'),
                'review_notes' => $review_notes
            ),
            array('cpd_id' => $entry_id),
            array('%f', '%s', '%d', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Failed to update entry.'));
        }
        
        // Send notification to user
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE cpd_id = %d",
            $entry_id
        ));
        
        if ($entry) {
            $this->send_review_notification($entry);
        }
        
        wp_send_json_success(array('message' => 'Entry updated successfully!'));
    }
    
    /**
     * Send review notification to user
     */
    private function send_review_notification($entry) {
        $user = get_userdata($entry->user_id);
        if (!$user) {
            return;
        }
        
        // Determine which template to use based on status
        $template_key = '';
        if ($entry->status === 'approved') {
            $template_key = 'user_entry_approved';
        } elseif ($entry->status === 'rejected') {
            $template_key = 'user_entry_rejected';
        }
        
        if (empty($template_key)) {
            return;
        }
        
        // Check if notification is enabled
        $enabled = get_option($template_key . '_enabled', 'yes');
        if ($enabled !== 'yes') {
            // Log skipped email
            cpd_log_email_skipped($template_key, $user->user_email, $user->display_name, 'CPD Entry Review Notification', 'Email notification disabled', [
                'entry_id' => $entry->cpd_id,
                'status' => $entry->status
            ]);
            return;
        }
        
        // Get email template
        $template = array(
            'subject' => get_option('cpd_' . $template_key . '_subject', ''),
            'heading' => get_option('cpd_' . $template_key . '_heading', ''),
            'message' => get_option('cpd_' . $template_key . '_message', '')
        );
        
        // Replace placeholders
        $replacements = $this->get_email_replacements($entry, $user);
        
        // Handle review notes placeholder - if empty, remove the entire review notes section
        $template_message = $template['message'];
        if (empty($entry->review_notes)) {
            // Remove review notes section including label if present
            $template_message = preg_replace('/<p><strong>Review Notes:<\/strong><br>\{review_notes\}<\/p>/i', '', $template_message);
            $template_message = preg_replace('/<p><strong>Review Notes:<\/strong><\/p>\s*\{review_notes\}/i', '', $template_message);
            $template_message = preg_replace('/\{review_notes\}/', '', $template_message);
        }
        
        $subject = $this->replace_placeholders($template['subject'], $replacements);
        $heading = $this->replace_placeholders($template['heading'], $replacements);
        $message = $this->replace_placeholders($template_message, $replacements);
        
        // If no custom message, use default template
        if (empty($template['message'])) {
            $message = $this->get_review_notification_email($entry, $user);
        } else {
            $message = $this->format_email_template($heading, $message);
        }
        
        // If no custom subject, use default
        if (empty($subject)) {
            $subject = sprintf('CPD Entry %s: %s', ucfirst($entry->status), $entry->activity_title);
        }
        
        // Capture wp_mail errors
        $wp_mail_error = null;
        $error_handler = function($wp_error) use (&$wp_mail_error) {
            if (is_wp_error($wp_error)) {
                $wp_mail_error = array(
                    'message' => $wp_error->get_error_message(),
                    'data' => $wp_error->get_error_data()
                );
            } else {
                $wp_mail_error = $wp_error;
            }
        };
        add_action('wp_mail_failed', $error_handler);
        
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        $mail_result = wp_mail($user->user_email, $subject, $message);
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
        remove_action('wp_mail_failed', $error_handler);
        
        // Get actual error message if available
        $error_message = 'wp_mail returned false';
        if (!$mail_result && $wp_mail_error) {
            if (is_array($wp_mail_error)) {
                $error_message = $wp_mail_error['message'] ?: $error_message;
                if (!empty($wp_mail_error['data'])) {
                    $error_message .= ' | Data: ' . wp_json_encode($wp_mail_error['data']);
                }
            } else {
                $error_message = $wp_mail_error;
            }
        } elseif (!$mail_result) {
            // Try to get PHPMailer error if available
            global $phpmailer;
            if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                $error_message = $phpmailer->ErrorInfo;
            } else {
                $error_message = 'wp_mail returned false (likely localhost/email configuration issue)';
            }
        }
        
        // Log email
        if ($mail_result) {
            cpd_log_email_sent($template_key, $user->user_email, $user->display_name, $subject, [
                'entry_id' => $entry->cpd_id,
                'user_id' => $entry->user_id,
                'status' => $entry->status,
                'points_allocated' => $entry->points_allocated
            ]);
        } else {
            cpd_log_email_failed($template_key, $user->user_email, $user->display_name, $subject, $error_message, [
                'entry_id' => $entry->cpd_id,
                'user_id' => $entry->user_id,
                'status' => $entry->status
            ]);
        }
    }
    
    /**
     * Get review notification email template
     */
    private function get_review_notification_email($entry, $user) {
        $status_class = $entry->status === 'approved' ? 'success' : 'error';
        $status_text = ucfirst($entry->status);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: <?php echo $entry->status === 'approved' ? '#46b450' : '#dc3232'; ?>; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid <?php echo $entry->status === 'approved' ? '#46b450' : '#dc3232'; ?>; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>CPD Entry <?php echo esc_html($status_text); ?></h2>
                </div>
                <div class="content">
                    <p>Dear <?php echo esc_html($user->display_name); ?>,</p>
                    
                    <p>Your CPD entry has been reviewed:</p>
                    
                    <div class="details">
                        <h3><?php echo esc_html($entry->activity_title); ?></h3>
                        <p><strong>Status:</strong> <?php echo esc_html($status_text); ?></p>
                        <?php if ($entry->points_allocated > 0): ?>
                        <p><strong>Points Allocated:</strong> <?php echo esc_html($entry->points_allocated); ?></p>
                        <?php endif; ?>
                        <?php if ($entry->review_notes): ?>
                        <p><strong>Review Notes:</strong><br><?php echo nl2br(esc_html($entry->review_notes)); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <p><small>This is an automated notification from the CPD Management System.</small></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle delete entry AJAX
     */
    public function handle_delete_entry() {
        check_ajax_referer('cpd_frontend_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }
        
        $entry_id = intval($_POST['entry_id'] ?? 0);
        $user_id = get_current_user_id();
        
        global $wpdb;
        
        // Verify ownership or admin capability
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE cpd_id = %d AND user_id = %d",
            $entry_id,
            $user_id
        ));
        
        if (!$entry && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Entry not found or you do not have permission.'));
        }
        
        // Delete file if exists
        if ($entry && $entry->uploaded_file_url) {
            $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $entry->uploaded_file_url);
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete entry
        $result = $wpdb->delete(
            $this->table_name,
            array('cpd_id' => $entry_id),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Failed to delete entry.'));
        }
        
        wp_send_json_success(array('message' => 'Entry deleted successfully!'));
    }
    
    /**
     * Handle get user entries AJAX
     */
    public function handle_get_user_entries() {
        check_ajax_referer('cpd_frontend_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in.'));
        }
        
        $user_id = get_current_user_id();
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        
        global $wpdb;
        
        // Build WHERE clause
        $where_conditions = array('user_id = %d');
        $where_values = array($user_id);
        
        // If date range is provided, use it; otherwise use year
        if (!empty($date_from) && !empty($date_to)) {
            $where_conditions[] = 'activity_date >= %s';
            $where_values[] = $date_from;
            $where_conditions[] = 'activity_date <= %s';
            $where_values[] = $date_to;
        } elseif (!empty($date_from)) {
            $where_conditions[] = 'activity_date >= %s';
            $where_values[] = $date_from;
        } elseif (!empty($date_to)) {
            $where_conditions[] = 'activity_date <= %s';
            $where_values[] = $date_to;
        } else {
            // Default to year filter if no date range
            $where_conditions[] = 'YEAR(activity_date) = %d';
            $where_values[] = $year;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Build the query with proper placeholders
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY activity_date DESC";
        
        // Use prepare with the correct number of placeholders
        if (count($where_values) > 0) {
            $entries = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $entries = $wpdb->get_results($query);
        }
        
        // Calculate points for the selected year (always use year filter method for consistency)
        // If date range is provided, calculate from filtered entries, otherwise use year method
        if (!empty($date_from) || !empty($date_to)) {
            // Calculate from filtered entries when date range is used
            $year_points = 0;
            foreach ($entries as $entry) {
                if ($entry->status === 'approved') {
                    $year_points += floatval($entry->points_allocated);
                }
            }
        } else {
            // Use the standard year points calculation method
            $year_points = $this->get_user_year_points($user_id, $year);
        }
        
        wp_send_json_success(array(
            'entries' => $entries,
            'year_points' => $year_points,
            'year' => $year,
            'date_from' => $date_from,
            'date_to' => $date_to
        ));
    }
    
    /**
     * Check if it's time to generate annual reports and generate if needed
     */
    public function check_and_generate_annual_reports() {
        // Only generate on January 1st
        $current_date = date('Y-m-d');
        if (date('m-d', strtotime($current_date)) === '01-01') {
            $this->generate_annual_reports();
        }
    }
    
    /**
     * Generate annual reports for all users
     */
    public function generate_annual_reports() {
        global $wpdb;
        
        $current_year = date('Y');
        $previous_year = $current_year - 1;
        
        // Get all users with CPD entries
        $users = $wpdb->get_col(
            "SELECT DISTINCT user_id FROM {$this->table_name} WHERE status = 'approved'"
        );
        
        foreach ($users as $user_id) {
            // Calculate total points for the past 5 years (for renewal/recertification eligibility)
            // Period: from 5 years ago to the previous year (inclusive)
            $five_years_ago = $previous_year - 4; // 5 years total (previous year + 4 years before)
            
            $total_points = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(points_allocated) FROM {$this->table_name} 
                WHERE user_id = %d 
                AND status = 'approved' 
                AND YEAR(activity_date) BETWEEN %d AND %d",
                $user_id,
                $five_years_ago,
                $previous_year
            ));
            
            $total_points = floatval($total_points ?? 0);
            
            // Calculate points needed (150 points required over 5 years for renewal/recertification)
            $minimum_required = get_option('cpd_minimum_points_required', 150);
            $points_needed = $minimum_required - $total_points;
            if ($points_needed < 0) {
                $points_needed = 0;
            }
            
            // Save or update report (store 5-year total)
            $wpdb->replace(
                $wpdb->prefix . 'sgndt_cpd_annual_reports',
                array(
                    'user_id' => $user_id,
                    'report_year' => $previous_year,
                    'total_points' => $total_points, // 5-year total
                    'points_needed' => $points_needed,
                    'report_generated_at' => current_time('mysql')
                ),
                array('%d', '%d', '%f', '%f', '%s')
            );
            
            // Send report email to user with 5-year summary
            $this->send_annual_report($user_id, $previous_year, $total_points, $points_needed, $five_years_ago);
        }
    }
    
    /**
     * Send annual report to user
     */
    private function send_annual_report($user_id, $year, $total_points, $points_needed, $period_start_year = null) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        // Check if annual report email is enabled
        $enabled = get_option('user_annual_report_enabled', 'yes');
        if ($enabled !== 'yes') {
            // Log skipped email
            cpd_log_email_skipped('user_annual_report', $user->user_email, $user->display_name, sprintf('CPD Annual Report for %d', $year), 'Email notification disabled', [
                'user_id' => $user_id,
                'year' => $year,
                'total_points' => $total_points
            ]);
            return;
        }
        
        // Get email template
        $template = array(
            'subject' => get_option('cpd_user_annual_report_subject', 'CPD Annual Report for {report_year}'),
            'heading' => get_option('cpd_user_annual_report_heading', 'CPD Annual Report {report_year}'),
            'message' => get_option('cpd_user_annual_report_message', '')
        );
        
        // Calculate period for display
        if (!$period_start_year) {
            $period_start_year = $year - 4; // 5 years total
        }
        $period_text = $period_start_year . ' - ' . $year;
        
        // Prepare replacements
        $replacements = array(
            '{user_name}' => $user->display_name,
            '{total_points}' => number_format($total_points, 2),
            '{points_needed}' => number_format($points_needed, 2),
            '{report_year}' => $year,
            '{period_years}' => $period_text,
            '{user_profile_link}' => home_url('/user-profile#cpd-management-section')
        );
        
        $subject = $this->replace_placeholders($template['subject'], $replacements);
        $heading = $this->replace_placeholders($template['heading'], $replacements);
        $message = $this->replace_placeholders($template['message'], $replacements);
        
        // If no custom message, use default template
        if (empty($message)) {
            $message = $this->get_annual_report_email($user, $year, $total_points, $points_needed);
        } else {
            $message = $this->format_email_template($heading, $message);
        }
        
        // If no custom subject, use default
        if (empty($subject)) {
            $subject = sprintf('CPD Annual Report for %d', $year);
        }
        
        // Capture wp_mail errors
        $wp_mail_error = null;
        $error_handler = function($wp_error) use (&$wp_mail_error) {
            if (is_wp_error($wp_error)) {
                $wp_mail_error = array(
                    'message' => $wp_error->get_error_message(),
                    'data' => $wp_error->get_error_data()
                );
            } else {
                $wp_mail_error = $wp_error;
            }
        };
        add_action('wp_mail_failed', $error_handler);
        
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        $mail_result = wp_mail($user->user_email, $subject, $message);
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
        remove_action('wp_mail_failed', $error_handler);
        
        // Get actual error message if available
        $error_message = 'wp_mail returned false';
        if (!$mail_result && $wp_mail_error) {
            if (is_array($wp_mail_error)) {
                $error_message = $wp_mail_error['message'] ?: $error_message;
                if (!empty($wp_mail_error['data'])) {
                    $error_message .= ' | Data: ' . wp_json_encode($wp_mail_error['data']);
                }
            } else {
                $error_message = $wp_mail_error;
            }
        } elseif (!$mail_result) {
            global $phpmailer;
            if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                $error_message = $phpmailer->ErrorInfo;
            } else {
                $error_message = 'wp_mail returned false (likely localhost/email configuration issue)';
            }
        }
        
        // Log email
        if ($mail_result) {
            cpd_log_email_sent('user_annual_report', $user->user_email, $user->display_name, $subject, [
                'user_id' => $user_id,
                'year' => $year,
                'period' => $period_text,
                'total_points' => $total_points,
                'points_needed' => $points_needed,
                'minimum_required' => get_option('cpd_minimum_points_required', 150)
            ]);
        } else {
            cpd_log_email_failed('user_annual_report', $user->user_email, $user->display_name, $subject, $error_message, [
                'user_id' => $user_id,
                'year' => $year
            ]);
        }
        
        // Update report sent timestamp
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'sgndt_cpd_annual_reports',
            array('report_sent_at' => current_time('mysql')),
            array(
                'user_id' => $user_id,
                'report_year' => $year
            ),
            array('%s'),
            array('%d', '%d')
        );
    }
    
    /**
     * Get annual report email template
     */
    private function get_annual_report_email($user, $year, $total_points, $points_needed, $period_start_year = null) {
        $profile_url = home_url('/user-profile#cpd-management-section');
        
        if (!$period_start_year) {
            $period_start_year = $year - 4; // 5 years total
        }
        $period_text = $period_start_year . ' - ' . $year;
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .summary { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa; }
                .points { font-size: 24px; font-weight: bold; color: #0073aa; }
                .button { display: inline-block; padding: 12px 24px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
                .notice { background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>CPD Annual Report <?php echo esc_html($year); ?></h2>
                </div>
                <div class="content">
                    <p>Dear <?php echo esc_html($user->display_name); ?>,</p>
                    
                    <div class="notice">
                        <p><strong>📋 Renewal & Recertification Requirement:</strong> 150 CPD points over any 5-year period are required for certificate renewal or recertification.</p>
                    </div>
                    
                    <p>Your CPD summary for the period <strong><?php echo esc_html($period_text); ?></strong> (5 years) is now available:</p>
                    
                    <div class="summary">
                        <h3>Summary (5-Year Period: <?php echo esc_html($period_text); ?>)</h3>
                        <p><strong>Total Points Accumulated (5 Years):</strong> <span class="points"><?php echo esc_html(number_format($total_points, 2)); ?></span></p>
                        <p><strong>Points Needed for Renewal/Recertification:</strong> <span class="points"><?php echo esc_html(number_format($points_needed, 2)); ?></span></p>
                        <p><strong>Minimum Required:</strong> 150 points over 5 years</p>
                    </div>
                    
                    <?php if ($points_needed > 0): ?>
                    <p><strong>Reminder:</strong> You need <?php echo esc_html(number_format($points_needed, 2)); ?> more points to meet the 5-year requirement for renewal/recertification. Please continue your professional development activities.</p>
                    <?php else: ?>
                    <p><strong>Congratulations!</strong> You have met the CPD requirement (150 points over 5 years) and are eligible for certificate renewal or recertification.</p>
                    <?php endif; ?>
                    
                    <a href="<?php echo esc_url($profile_url); ?>" class="button">View Your CPD Summary</a>
                    
                    <p><small>This is an automated report from the CPD Management System.</small></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user total CPD points
     * 
     * @param int $user_id User ID
     * @param int|null $year If provided, get points for specific year. If null, get total for past 5 years
     * @return float Total points
     */
    public function get_user_total_points($user_id, $year = null) {
        global $wpdb;
        
        if ($year) {
            // Get points for specific year
            $where_year = $wpdb->prepare("AND YEAR(activity_date) = %d", $year);
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(points_allocated) FROM {$this->table_name} 
                WHERE user_id = %d 
                AND status = 'approved' 
                {$where_year}",
                $user_id
            ));
        } else {
            // Get total points for past 5 years (for renewal/recertification eligibility)
            $current_year = date('Y');
            $five_years_ago = $current_year - 4; // 5 years total
            
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(points_allocated) FROM {$this->table_name} 
                WHERE user_id = %d 
                AND status = 'approved' 
                AND YEAR(activity_date) BETWEEN %d AND %d",
                $user_id,
                $five_years_ago,
                $current_year
            ));
        }
        
        return floatval($total ?? 0);
    }
    
    /**
     * Get user CPD points for a specific year
     */
    public function get_user_year_points($user_id, $year) {
        global $wpdb;
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_allocated) FROM {$this->table_name} 
            WHERE user_id = %d 
            AND status = 'approved' 
            AND YEAR(activity_date) = %d",
            $user_id,
            $year
        ));
        
        return floatval($total ?? 0);
    }
    
    /**
     * Get user entries
     */
    public function get_user_entries($user_id, $year = null, $status = null) {
        global $wpdb;
        
        $where_conditions = array('user_id = %d');
        $where_values = array($user_id);
        
        if ($year) {
            $where_conditions[] = 'YEAR(activity_date) = %d';
            $where_values[] = $year;
        }
        
        if ($status) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $status;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE {$where_clause} 
            ORDER BY activity_date DESC",
            ...$where_values
        ));
    }
    
    /**
     * Get email replacements array
     */
    private function get_email_replacements($entry, $user) {
        $admin_url = admin_url('admin.php?page=cpd-pending-reviews');
        if (is_object($entry) && isset($entry->cpd_id)) {
            $admin_url = admin_url('admin.php?page=cpd-pending-reviews&entry_id=' . $entry->cpd_id);
        }
        
        return array(
            '{user_name}' => $user->display_name,
            '{activity_title}' => isset($entry->activity_title) ? $entry->activity_title : '',
            '{activity_date}' => isset($entry->activity_date) ? date('F j, Y', strtotime($entry->activity_date)) : '',
            '{activity_category}' => isset($entry->activity_category) ? $entry->activity_category : '',
            '{points_allocated}' => isset($entry->points_allocated) ? number_format($entry->points_allocated, 2) : '0',
            '{points_requested}' => isset($entry->points_requested) ? number_format($entry->points_requested, 2) : '0',
            '{review_notes}' => isset($entry->review_notes) ? nl2br(esc_html($entry->review_notes)) : '',
            '{admin_review_link}' => $admin_url,
            '{user_profile_link}' => home_url('/user-profile#cpd-management-section')
        );
    }
    
    /**
     * Replace placeholders in text
     */
    private function replace_placeholders($text, $replacements) {
        foreach ($replacements as $placeholder => $replacement) {
            $text = str_replace($placeholder, $replacement, $text);
        }
        return $text;
    }
    
    /**
     * Format email template with heading and message
     */
    private function format_email_template($heading, $message) {
        $heading_html = !empty($heading) ? '<div class="header"><h2>' . esc_html($heading) . '</h2></div>' : '';
        
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        ' . $heading_html . '
        <div class="content">
            ' . wpautop($message) . '
        </div>
        <div class="footer">
            <p>This is an automated message from the CPD Management System.</p>
        </div>
    </div>
</body>
</html>';
    }
}

// Initialize the CPD Manager
CPD_Manager::get_instance();

