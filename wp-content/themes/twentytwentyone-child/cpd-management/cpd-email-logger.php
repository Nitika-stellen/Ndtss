<?php
/**
 * CPD Email Logger
 * Dedicated logging system for CPD email operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPD_Email_Logger {
    
    private static $log_file;
    private static $log_dir;
    private static $max_log_size = 10485760; // 10MB
    private static $max_log_files = 5;
    
    /**
     * Initialize the logger
     */
    public static function init() {
        self::$log_dir = get_stylesheet_directory() . '/cpd-management/logs';
        self::$log_file = self::$log_dir . '/cpd-emails-' . date('Y-m') . '.log';
        
        // Create log directory if it doesn't exist
        if (!file_exists(self::$log_dir)) {
            wp_mkdir_p(self::$log_dir);
        }
        
        // Create .htaccess to protect log files
        $htaccess_file = self::$log_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Order deny,allow\nDeny from all\n");
        }
    }
    
    /**
     * Log email sent
     */
    public static function log_email($email_type, $recipient_email, $recipient_name, $subject, $status = 'sent', $context = []) {
        self::init();
        
        $timestamp = current_time('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        $user_info = $user_id ? get_userdata($user_id) : null;
        $action_user = $user_info ? $user_info->display_name : 'System';
        $ip_address = self::get_client_ip();
        
        // Format context data
        $context_str = '';
        if (!empty($context)) {
            $context_str = ' | Context: ' . json_encode($context);
        }
        
        // Create log entry - ensure clean format for parsing
        $log_entry = sprintf(
            "[%s] [EMAIL-%s] [Type: %s] [To: %s (%s)] [Subject: %s] [Action by: %s (ID: %d)] [IP: %s]%s\n",
            $timestamp,
            strtoupper($status),
            $email_type,
            $recipient_email,
            $recipient_name,
            $subject,
            $action_user,
            $user_id,
            $ip_address,
            $context_str
        );
        
        // Clean any HTML entities that might have been introduced
        $log_entry = html_entity_decode($log_entry, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Write to log file
        if (file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX) === false) {
            // Fallback to WordPress error log
            error_log("CPD Email Logger: Failed to write to log file: " . self::$log_file);
        }
        
        // Rotate logs if needed
        self::rotate_logs();
    }
    
    /**
     * Log email sent successfully
     */
    public static function log_sent($email_type, $recipient_email, $recipient_name, $subject, $context = []) {
        self::log_email($email_type, $recipient_email, $recipient_name, $subject, 'sent', $context);
    }
    
    /**
     * Log email failed
     */
    public static function log_failed($email_type, $recipient_email, $recipient_name, $subject, $error_message, $context = []) {
        $context['error'] = $error_message;
        self::log_email($email_type, $recipient_email, $recipient_name, $subject, 'failed', $context);
    }
    
    /**
     * Log email skipped (disabled)
     */
    public static function log_skipped($email_type, $recipient_email, $recipient_name, $subject, $reason = 'disabled', $context = []) {
        $context['reason'] = $reason;
        self::log_email($email_type, $recipient_email, $recipient_name, $subject, 'skipped', $context);
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
    }
    
    /**
     * Rotate log files when they get too large
     */
    private static function rotate_logs() {
        if (!file_exists(self::$log_file)) {
            return;
        }
        
        $file_size = filesize(self::$log_file);
        if ($file_size > self::$max_log_size) {
            // Archive current log
            $archive_file = self::$log_dir . '/cpd-emails-' . date('Y-m') . '-' . time() . '.log';
            rename(self::$log_file, $archive_file);
            
            // Clean up old logs
            self::cleanup_old_logs();
        }
    }
    
    /**
     * Clean up old log files
     */
    private static function cleanup_old_logs() {
        $log_files = glob(self::$log_dir . '/cpd-emails-*.log');
        if (count($log_files) > self::$max_log_files) {
            // Sort by modification time
            usort($log_files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove oldest files
            $files_to_remove = array_slice($log_files, 0, count($log_files) - self::$max_log_files);
            foreach ($files_to_remove as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get log entries
     */
    public static function get_logs($lines = 100, $status = null, $email_type = null) {
        self::init();
        
        if (!file_exists(self::$log_file)) {
            return [];
        }
        
        $log_content = file_get_contents(self::$log_file);
        $log_entries = explode("\n", $log_content);
        $log_entries = array_filter($log_entries); // Remove empty lines
        
        // Filter by status if specified
        if ($status) {
            $log_entries = array_filter($log_entries, function($entry) use ($status) {
                return strpos($entry, "[EMAIL-" . strtoupper($status) . "]") !== false;
            });
        }
        
        // Filter by email type if specified
        if ($email_type) {
            $log_entries = array_filter($log_entries, function($entry) use ($email_type) {
                return strpos($entry, "[Type: $email_type]") !== false;
            });
        }
        
        // Get last N lines
        $log_entries = array_slice($log_entries, -$lines);
        
        return array_reverse($log_entries); // Most recent first
    }
    
    /**
     * Clear log files
     */
    public static function clear_logs() {
        self::init();
        
        $log_files = glob(self::$log_dir . '/cpd-emails-*.log');
        foreach ($log_files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    /**
     * Get log file size
     */
    public static function get_log_size() {
        self::init();
        
        if (!file_exists(self::$log_file)) {
            return 0;
        }
        
        return filesize(self::$log_file);
    }
    
    /**
     * Get log statistics
     */
    public static function get_log_stats() {
        self::init();
        
        $log_files = glob(self::$log_dir . '/cpd-emails-*.log');
        $total_size = 0;
        $total_entries = 0;
        $status_counts = [
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0
        ];
        
        foreach ($log_files as $file) {
            $total_size += filesize($file);
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            $total_entries += count(array_filter($lines));
            
            // Count by status
            foreach ($lines as $line) {
                if (strpos($line, '[EMAIL-SENT]') !== false) {
                    $status_counts['sent']++;
                } elseif (strpos($line, '[EMAIL-FAILED]') !== false) {
                    $status_counts['failed']++;
                } elseif (strpos($line, '[EMAIL-SKIPPED]') !== false) {
                    $status_counts['skipped']++;
                }
            }
        }
        
        return [
            'total_files' => count($log_files),
            'total_size' => $total_size,
            'total_entries' => $total_entries,
            'current_file' => basename(self::$log_file),
            'current_size' => self::get_log_size(),
            'status_counts' => $status_counts
        ];
    }
}

// Initialize logger
CPD_Email_Logger::init();

/**
 * Convenience functions for easy logging
 */
function cpd_log_email_sent($email_type, $recipient_email, $recipient_name, $subject, $context = []) {
    CPD_Email_Logger::log_sent($email_type, $recipient_email, $recipient_name, $subject, $context);
}

function cpd_log_email_failed($email_type, $recipient_email, $recipient_name, $subject, $error_message, $context = []) {
    CPD_Email_Logger::log_failed($email_type, $recipient_email, $recipient_name, $subject, $error_message, $context);
}

function cpd_log_email_skipped($email_type, $recipient_email, $recipient_name, $subject, $reason = 'disabled', $context = []) {
    CPD_Email_Logger::log_skipped($email_type, $recipient_email, $recipient_name, $subject, $reason, $context);
}

