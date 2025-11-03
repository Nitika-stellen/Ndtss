<?php
/**
 * Event Module - Main Event Management System
 * Handles event registrations, approvals, and CPD tracking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include required files
include get_stylesheet_directory() . '/event/event-functions.php';
include get_stylesheet_directory() . '/event/event-admin.php';
include get_stylesheet_directory() . '/event/event-ajax.php';
include get_stylesheet_directory() . '/event/event-logger.php';
include get_stylesheet_directory() . '/event/event-templates.php';

/**
 * Initialize Event Module
 */
function init_event_module() {
    // Enqueue scripts and styles
    add_action('wp_enqueue_scripts', 'event_enqueue_scripts');
    add_action('admin_enqueue_scripts', 'event_admin_enqueue_scripts');
    
    // Add admin menus
    add_action('admin_menu', 'add_event_admin_menus');
    
    // Add AJAX handlers
    add_action('wp_ajax_event_approve_entry_ajax', 'event_handle_approve_entry_ajax');
    add_action('wp_ajax_event_reject_entry_ajax', 'event_handle_reject_entry_ajax');
    add_action('wp_ajax_event_checkout_ajax', 'event_handle_checkout_ajax');
    add_action('wp_ajax_add_cpd_points', 'event_handle_add_cpd_points');
    add_action('wp_ajax_generate_cpd_pdf', 'event_handle_generate_cpd_pdf');
    
    // Add Gravity Forms hooks
    add_action('gform_after_submission_12', 'event_handle_form_submission', 10, 2);
    
    // Add event pricing hooks
    add_filter('tribe_get_cost', 'event_modify_tribe_event_cost', 10, 2);
    
    // Add meta boxes
    add_action('add_meta_boxes', 'event_add_member_price_meta_box');
    add_action('save_post', 'event_save_member_price');
    
    // Initialize logging
    EventLogger::init();
}

/**
 * Enqueue frontend scripts and styles
 */
function event_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('sweetalert2', get_stylesheet_directory_uri() . '/js/sweetalert2.all.min.js', ['jquery'], '1.0', true);
    wp_enqueue_script('event-frontend', get_stylesheet_directory_uri() . '/event/js/event-frontend.js', ['jquery', 'sweetalert2'], '1.0', true);
    
    wp_localize_script('event-frontend', 'event_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('event_nonce'),
        'loading_text' => __('Processing...', 'textdomain'),
        'success_text' => __('Success!', 'textdomain'),
        'error_text' => __('Error!', 'textdomain')
    ]);
    
    wp_enqueue_style('event-frontend', get_stylesheet_directory_uri() . '/event/css/event-frontend.css', [], '1.0');
}

/**
 * Enqueue admin scripts and styles
 */
function event_admin_enqueue_scripts($hook) {
    // Only load on event-related pages
    if (strpos($hook, 'event') === false && strpos($hook, 'attendee') === false) {
        return;
    }
    
    wp_enqueue_script('jquery');
    wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', ['jquery'], '1.11.5', true);
    wp_enqueue_script('sweetalert2', get_stylesheet_directory_uri() . '/js/sweetalert2.all.min.js', ['jquery'], '1.0', true);
    wp_enqueue_script('event-admin', get_stylesheet_directory_uri() . '/event/js/event-admin.js', ['jquery', 'datatables', 'sweetalert2'], '1.0', true);
    
    wp_localize_script('event-admin', 'event_admin_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'approve_nonce' => wp_create_nonce('approve_nonce'),
        'reject_nonce' => wp_create_nonce('reject_nonce'),
        'cpd_nonce' => wp_create_nonce('cpd_nonce'),
        'loading_text' => __('Processing...', 'textdomain'),
        'success_text' => __('Success!', 'textdomain'),
        'error_text' => __('Error!', 'textdomain')
    ]);
    
    wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css', [], '1.11.5');
    wp_enqueue_style('event-admin', get_stylesheet_directory_uri() . '/event/css/event-admin.css', [], '1.0');
}

/**
 * Add admin menus
 */
function add_event_admin_menus() {
    add_submenu_page(
        'edit.php?post_type=tribe_events',
        'Event Registrations',
        'Event Registrations',
        'manage_options',
        'event-registrations',
        'event_display_registrations_page'
    );
    
    add_submenu_page(
        'edit.php?post_type=tribe_events',
        'Attendee Management',
        'Attendee Management',
        'manage_options',
        'attendee-management',
        'event_display_attendee_management_page'
    );
    
    add_submenu_page(
        'edit.php?post_type=tribe_events',
        'Event Logs',
        'Event Logs',
        'manage_options',
        'event-logs',
        'event_display_logs_page'
    );
}

// Initialize the module
init_event_module();


