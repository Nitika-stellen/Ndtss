<?php
/**
 * Event Logger - Dedicated logging system for event operations
 * Provides comprehensive error tracking and monitoring for event functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EventLogger {
    
    private static $log_file;
    private static $log_dir;
    private static $max_log_size = 10485760; // 10MB
    private static $max_log_files = 5;
    
    /**
     * Initialize the logger
     */
    public static function init() {
        self::$log_dir = get_stylesheet_directory() . '/event/logs';
        self::$log_file = self::$log_dir . '/event-errors-' . date('Y-m') . '.log';
        
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
     * Log an error message
     */
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }
    
    /**
     * Log a warning message
     */
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
    
    /**
     * Log an info message
     */
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    /**
     * Log a debug message
     */
    public static function debug($message, $context = []) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::log('DEBUG', $message, $context);
        }
    }
    
    /**
     * Main logging function
     */
    private static function log($level, $message, $context = []) {
        self::init();
        
        $timestamp = current_time('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        $user_info = $user_id ? get_userdata($user_id) : null;
        $user_name = $user_info ? $user_info->display_name : 'Guest';
        $ip_address = self::get_client_ip();
        
        // Format context data
        $context_str = '';
        if (!empty($context)) {
            $context_str = ' | Context: ' . json_encode($context);
        }
        
        // Create log entry
        $log_entry = sprintf(
            "[%s] [%s] [User: %s (ID: %d)] [IP: %s] %s%s\n",
            $timestamp,
            $level,
            $user_name,
            $user_id,
            $ip_address,
            $message,
            $context_str
        );
        
        // Write to log file
        if (file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX) === false) {
            // Fallback to WordPress error log
            error_log("Event Logger: Failed to write to log file: " . self::$log_file);
        }
        
        // Rotate logs if needed
        self::rotate_logs();
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
            $archive_file = self::$log_dir . '/event-errors-' . date('Y-m') . '-' . time() . '.log';
            rename(self::$log_file, $archive_file);
            
            // Clean up old logs
            self::cleanup_old_logs();
        }
    }
    
    /**
     * Clean up old log files
     */
    private static function cleanup_old_logs() {
        $log_files = glob(self::$log_dir . '/event-errors-*.log');
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
    public static function get_logs($lines = 100, $level = null) {
        self::init();
        
        if (!file_exists(self::$log_file)) {
            return [];
        }
        
        $log_content = file_get_contents(self::$log_file);
        $log_entries = explode("\n", $log_content);
        $log_entries = array_filter($log_entries); // Remove empty lines
        
        // Filter by level if specified
        if ($level) {
            $log_entries = array_filter($log_entries, function($entry) use ($level) {
                return strpos($entry, "[$level]") !== false;
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
        
        $log_files = glob(self::$log_dir . '/event-errors-*.log');
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
        
        $log_files = glob(self::$log_dir . '/event-errors-*.log');
        $total_size = 0;
        $total_entries = 0;
        
        foreach ($log_files as $file) {
            $total_size += filesize($file);
            $content = file_get_contents($file);
            $total_entries += substr_count($content, "\n");
        }
        
        return [
            'total_files' => count($log_files),
            'total_size' => $total_size,
            'total_entries' => $total_entries,
            'current_file' => basename(self::$log_file),
            'current_size' => self::get_log_size()
        ];
    }
}

// Initialize logger
EventLogger::init();

/**
 * Convenience functions for easy logging
 */
function event_log_error($message, $context = []) {
    EventLogger::error($message, $context);
}

function event_log_warning($message, $context = []) {
    EventLogger::warning($message, $context);
}

function event_log_info($message, $context = []) {
    EventLogger::info($message, $context);
}

function event_log_debug($message, $context = []) {
    EventLogger::debug($message, $context);
}


