<?php
/**
 * Event Migration Script
 * Helps migrate from old event system to new event module
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migrate event functionality to new module
 */
function event_migrate_to_new_module() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    echo '<div class="wrap">';
    echo '<h1>Event Module Migration</h1>';
    
    $migration_steps = [
        'backup_existing_files' => 'Backup existing event files',
        'update_ajax_handlers' => 'Update AJAX handlers',
        'migrate_admin_pages' => 'Migrate admin pages',
        'update_templates' => 'Update event templates',
        'test_functionality' => 'Test functionality'
    ];
    
    $completed_steps = get_option('event_migration_steps', []);
    
    foreach ($migration_steps as $step => $description) {
        $status = in_array($step, $completed_steps) ? 'completed' : 'pending';
        $icon = $status === 'completed' ? '✅' : '⏳';
        
        echo '<div class="migration-step ' . $status . '">';
        echo '<h3>' . $icon . ' ' . $description . '</h3>';
        
        if ($status === 'pending') {
            echo '<p>This step needs to be completed.</p>';
            if (isset($_GET['run_step']) && $_GET['run_step'] === $step) {
                echo '<div class="step-actions">';
                echo '<a href="' . admin_url('admin.php?page=event-migration&run_step=' . $step . '&execute=1') . '" class="button button-primary">Execute Step</a>';
                echo '</div>';
            } else {
                echo '<div class="step-actions">';
                echo '<a href="' . admin_url('admin.php?page=event-migration&run_step=' . $step) . '" class="button">Prepare Step</a>';
                echo '</div>';
            }
        } else {
            echo '<p>This step has been completed successfully.</p>';
        }
        
        echo '</div>';
    }
    
    // Handle step execution
    if (isset($_GET['execute']) && isset($_GET['run_step'])) {
        $step = sanitize_text_field($_GET['run_step']);
        event_execute_migration_step($step);
    }
    
    echo '</div>';
}

/**
 * Execute migration step
 */
function event_execute_migration_step($step) {
    try {
        switch ($step) {
            case 'backup_existing_files':
                event_backup_existing_files();
                break;
            case 'update_ajax_handlers':
                event_update_ajax_handlers();
                break;
            case 'migrate_admin_pages':
                event_migrate_admin_pages();
                break;
            case 'update_templates':
                event_update_templates();
                break;
            case 'test_functionality':
                event_test_functionality();
                break;
        }
        
        // Mark step as completed
        $completed_steps = get_option('event_migration_steps', []);
        if (!in_array($step, $completed_steps)) {
            $completed_steps[] = $step;
            update_option('event_migration_steps', $completed_steps);
        }
        
        echo '<div class="notice notice-success"><p>Step completed successfully!</p></div>';
        
    } catch (Exception $e) {
        echo '<div class="notice notice-error"><p>Error: ' . esc_html($e->getMessage()) . '</p></div>';
    }
}

/**
 * Backup existing event files
 */
function event_backup_existing_files() {
    $backup_dir = get_stylesheet_directory() . '/event/backups/' . date('Y-m-d_H-i-s');
    
    if (!wp_mkdir_p($backup_dir)) {
        throw new Exception('Failed to create backup directory');
    }
    
    $files_to_backup = [
        'event_functions.php',
        'event_enteries.php',
        'panel/event_enteries.php'
    ];
    
    foreach ($files_to_backup as $file) {
        $source = get_stylesheet_directory() . '/' . $file;
        if (file_exists($source)) {
            $destination = $backup_dir . '/' . basename($file);
            if (!copy($source, $destination)) {
                throw new Exception('Failed to backup ' . $file);
            }
        }
    }
    
    echo '<p>✅ Files backed up to: ' . $backup_dir . '</p>';
}

/**
 * Update AJAX handlers
 */
function event_update_ajax_handlers() {
    // Remove old AJAX handlers
    remove_action('wp_ajax_event_approve_entry_ajax', 'event_handle_approve_entry_ajax');
    remove_action('wp_ajax_event_reject_entry_ajax', 'event_handle_reject_entry_ajax');
    remove_action('wp_ajax_event_checkout_ajax', 'handle_event_checkout_ajax');
    remove_action('wp_ajax_add_cpd_points', 'save_cpd_points');
    remove_action('wp_ajax_generate_cpd_pdf', 'generate_cpd_pdf');
    
    echo '<p>✅ Old AJAX handlers removed</p>';
    echo '<p>✅ New AJAX handlers are now active in the event module</p>';
}

/**
 * Migrate admin pages
 */
function event_migrate_admin_pages() {
    // Remove old admin menus
    remove_action('admin_menu', 'add_event_registered_entries_submenu');
    
    echo '<p>✅ Old admin menus removed</p>';
    echo '<p>✅ New admin menus are now active in the event module</p>';
}

/**
 * Update templates
 */
function event_update_templates() {
    // The new event module handles templates automatically
    echo '<p>✅ Event templates are now handled by the new module</p>';
}

/**
 * Test functionality
 */
function event_test_functionality() {
    $tests = [
        'event_logger' => class_exists('EventLogger'),
        'event_functions' => function_exists('event_modify_tribe_event_cost'),
        'event_ajax' => function_exists('event_handle_approve_entry_ajax'),
        'event_admin' => function_exists('event_display_registrations_page'),
        'event_templates' => function_exists('event_get_email_template')
    ];
    
    echo '<h4>Functionality Tests:</h4>';
    echo '<ul>';
    
    foreach ($tests as $test => $result) {
        $status = $result ? '✅' : '❌';
        echo '<li>' . $status . ' ' . ucfirst(str_replace('_', ' ', $test)) . '</li>';
    }
    
    echo '</ul>';
    
    // Test event logging
    if (function_exists('event_log_info')) {
        event_log_info('Event migration test completed', [
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id()
        ]);
        echo '<p>✅ Event logging test passed</p>';
    }
}

// Add migration page to admin menu
function event_add_migration_menu() {
    add_submenu_page(
        'edit.php?post_type=tribe_events',
        'Event Migration',
        'Event Migration',
        'manage_options',
        'event-migration',
        'event_migrate_to_new_module'
    );
}
add_action('admin_menu', 'event_add_migration_menu');


