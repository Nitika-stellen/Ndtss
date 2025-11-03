<?php
if (!defined('ABSPATH')) { exit; }

function renew_log_dir() {
    $dir = get_stylesheet_directory() . '/renew/logs';
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
        @file_put_contents($dir . '/.htaccess', "Deny from all\n");
    }
    return $dir;
}

function renew_log_write($level, $message, $context = array()) {
    $date = current_time('Y-m');
    $file = renew_log_dir() . "/renew-{$date}.log";
    $line = sprintf(
        "%s [%s] %s %s\n",
        current_time('mysql'),
        strtoupper($level),
        is_string($message) ? $message : wp_json_encode($message),
        $context ? wp_json_encode($context) : ''
    );
    error_log($line, 3, $file);
}

function renew_log_info($message, $context = array()) { renew_log_write('info', $message, $context); }
function renew_log_warn($message, $context = array()) { renew_log_write('warning', $message, $context); }
function renew_log_error($message, $context = array()) { renew_log_write('error', $message, $context); }


