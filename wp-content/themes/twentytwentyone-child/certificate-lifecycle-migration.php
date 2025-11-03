<?php
/**
 * Certificate Lifecycle Database Migration
 * 
 * Ensures database schema is properly set up for certificate lifecycle management
 * 
 * @package SGNDT
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CertificateLifecycleMigration {
    
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
        add_action('admin_init', array($this, 'checkDatabaseSchema'));
        add_action('wp_ajax_migrate_certificate_database', array($this, 'runMigration'));
    }
    
    /**
     * Check database schema on admin init
     */
    public function checkDatabaseSchema() {
        if (is_admin() && current_user_can('manage_options')) {
            $this->ensureDatabaseSchema();
        }
    }
    
    /**
     * Ensure database schema is up to date
     */
    public function ensureDatabaseSchema() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sgndt_final_certifications';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if (!$table_exists) {
            $this->createFinalCertificationsTable();
        } else {
            $this->updateFinalCertificationsTable();
        }
        
        // Create certificate lifecycle status table
        $this->createCertificateStatusTable();
        
        // Create renewal applications table
        $this->createRenewalApplicationsTable();
    }
    
    /**
     * Create final certifications table
     */
    private function createFinalCertificationsTable() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sgndt_final_certifications';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            final_certification_id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            certificate_number varchar(50) NOT NULL,
            issue_date date NOT NULL,
            expiry_date date NOT NULL,
            status varchar(20) DEFAULT 'pending',
            method varchar(50) NOT NULL,
            level varchar(50) NOT NULL,
            sector varchar(50) NOT NULL,
            scope varchar(100) NOT NULL,
            certificate_link text,
            exam_entry_id bigint(20),
            marks_entry_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (final_certification_id),
            KEY user_id (user_id),
            KEY certificate_number (certificate_number),
            KEY status (status),
            KEY expiry_date (expiry_date)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('Created sgndt_final_certifications table');
    }
    
    /**
     * Update final certifications table
     */
    private function updateFinalCertificationsTable() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sgndt_final_certifications';
        
        // Check if created_at column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'created_at'");
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP");
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            
            error_log('Added created_at and updated_at columns to sgndt_final_certifications table');
        }
        
        // Check if exam_entry_id and marks_entry_id columns exist
        $exam_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'exam_entry_id'");
        $marks_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'marks_entry_id'");
        
        if (empty($exam_column_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN exam_entry_id bigint(20)");
            error_log('Added exam_entry_id column to sgndt_final_certifications table');
        }
        
        if (empty($marks_column_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN marks_entry_id bigint(20)");
            error_log('Added marks_entry_id column to sgndt_final_certifications table');
        }
    }
    
    /**
     * Create certificate status table
     */
    private function createCertificateStatusTable() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sgndt_certificate_status';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            status_id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            certificate_id bigint(20) NOT NULL,
            certificate_number varchar(50) NOT NULL,
            status varchar(50) NOT NULL,
            status_date datetime NOT NULL,
            submission_method varchar(50),
            cert_type varchar(50),
            additional_data text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (status_id),
            KEY user_id (user_id),
            KEY certificate_id (certificate_id),
            KEY certificate_number (certificate_number),
            KEY status (status),
            KEY status_date (status_date)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('Created sgndt_certificate_status table');
    }
    
    /**
     * Create renewal applications table
     */
    private function createRenewalApplicationsTable() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sgndt_renewal_applications';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            application_id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            original_certificate_id bigint(20) NOT NULL,
            certificate_number varchar(50) NOT NULL,
            application_type varchar(50) NOT NULL,
            submission_method varchar(50) NOT NULL,
            form_entry_id bigint(20),
            status varchar(50) DEFAULT 'submitted',
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            reviewed_at datetime,
            reviewed_by bigint(20),
            rejection_reason text,
            approved_at datetime,
            approved_by bigint(20),
            certificate_generated_at datetime,
            new_certificate_id bigint(20),
            new_certificate_number varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (application_id),
            KEY user_id (user_id),
            KEY original_certificate_id (original_certificate_id),
            KEY certificate_number (certificate_number),
            KEY status (status),
            KEY submitted_at (submitted_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('Created sgndt_renewal_applications table');
    }
    
    /**
     * Run migration via AJAX
     */
    public function runMigration() {
        if (!check_ajax_referer('cert_migration_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $this->ensureDatabaseSchema();
            $this->migrateExistingData();
            
            wp_send_json_success('Database migration completed successfully');
        } catch (Exception $e) {
            wp_send_json_error('Migration failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Migrate existing certificate data
     */
    private function migrateExistingData() {
        global $wpdb;
        
        // Migrate existing certificates to new schema
        $existing_certificates = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications 
             WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'"
        );
        
        foreach ($existing_certificates as $cert) {
            $wpdb->update(
                $wpdb->prefix . 'sgndt_final_certifications',
                array(
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('final_certification_id' => $cert->final_certification_id)
            );
        }
        
        error_log('Migrated ' . count($existing_certificates) . ' existing certificates');
    }
    
    /**
     * Get database schema status
     */
    public function getSchemaStatus() {
        global $wpdb;
        
        $status = array(
            'final_certifications' => false,
            'certificate_status' => false,
            'renewal_applications' => false
        );
        
        // Check final certifications table
        $table_name = $wpdb->prefix . 'sgndt_final_certifications';
        $status['final_certifications'] = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        // Check certificate status table
        $table_name = $wpdb->prefix . 'sgndt_certificate_status';
        $status['certificate_status'] = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        // Check renewal applications table
        $table_name = $wpdb->prefix . 'sgndt_renewal_applications';
        $status['renewal_applications'] = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        return $status;
    }
    
    /**
     * Create admin notice for database migration
     */
    public function showMigrationNotice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $schema_status = $this->getSchemaStatus();
        
        if (!$schema_status['final_certifications'] || !$schema_status['certificate_status'] || !$schema_status['renewal_applications']) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>Certificate Lifecycle System:</strong> 
                    Database schema needs to be updated. 
                    <a href="<?php echo admin_url('admin-ajax.php?action=migrate_certificate_database&nonce=' . wp_create_nonce('cert_migration_nonce')); ?>" 
                       class="button button-primary">Run Migration</a>
                </p>
            </div>
            <?php
        }
    }
}

// Initialize migration
CertificateLifecycleMigration::getInstance();

// Show migration notice
add_action('admin_notices', array(CertificateLifecycleMigration::getInstance(), 'showMigrationNotice'));
