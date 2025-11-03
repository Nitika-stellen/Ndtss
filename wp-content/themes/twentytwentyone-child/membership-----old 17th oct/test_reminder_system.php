<?php
/**
 * Test script for membership reminder system
 * This file helps test the reminder functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function test_membership_reminder_system() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    echo '<div class="wrap">';
    echo '<h1>Test Membership Reminder System</h1>';
    
    // Test 1: Check if cron job is scheduled
    echo '<h2>1. Cron Job Status</h2>';
    $next_run = wp_next_scheduled('membership_expiry_reminder_cron');
    if ($next_run) {
        echo '<p style="color: green;">✓ Cron job is scheduled. Next run: ' . date('Y-m-d H:i:s', $next_run) . '</p>';
    } else {
        echo '<p style="color: red;">✗ Cron job is not scheduled.</p>';
    }
    
    // Test 2: Check email templates
    echo '<h2>2. Email Templates</h2>';
    $templates = [
        'user_membership_reminder_enabled' => get_option('user_membership_reminder_enabled'),
        'user_membership_reminder_subject' => get_option('user_membership_reminder_subject'),
        'user_membership_reminder_heading' => get_option('user_membership_reminder_heading'),
        'user_membership_reminder_message' => get_option('user_membership_reminder_message')
    ];
    
    $all_templates_set = true;
    foreach ($templates as $key => $value) {
        if (empty($value)) {
            echo '<p style="color: red;">✗ ' . $key . ' is not set</p>';
            $all_templates_set = false;
        } else {
            echo '<p style="color: green;">✓ ' . $key . ' is set</p>';
        }
    }
    
    if ($all_templates_set) {
        echo '<p style="color: green;"><strong>All email templates are configured!</strong></p>';
    }
    
    // Test 3: Check users with approved memberships
    echo '<h2>3. Users with Approved Memberships</h2>';
    $users = get_users([
        'meta_query' => [
            [
                'key' => 'membership_approval_status',
                'value' => 'approved',
                'compare' => '='
            ]
        ]
    ]);
    
    echo '<p>Found ' . count($users) . ' users with approved memberships.</p>';
    
    if (!empty($users)) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>User ID</th><th>Name</th><th>Email</th><th>Expiry Date</th><th>Days Until Expiry</th></tr></thead>';
        echo '<tbody>';
        
        $today = new DateTime(current_time('Y-m-d'));
        foreach ($users as $user) {
            $expiry_date = get_user_meta($user->ID, 'membership_expiry_date', true);
            if ($expiry_date) {
                $expiry = new DateTime($expiry_date);
                $days_until_expiry = $today->diff($expiry)->days;
                
                $color = $days_until_expiry <= 7 ? 'red' : ($days_until_expiry <= 14 ? 'orange' : 'green');
                
                echo '<tr>';
                echo '<td>' . $user->ID . '</td>';
                echo '<td>' . esc_html($user->display_name) . '</td>';
                echo '<td>' . esc_html($user->user_email) . '</td>';
                echo '<td>' . esc_html($expiry_date) . '</td>';
                echo '<td style="color: ' . $color . ';">' . $days_until_expiry . ' days</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
    }
    
    // Test 4: Manual trigger test
    echo '<h2>4. Manual Test</h2>';
    echo '<p><a href="' . admin_url('admin.php?page=membership-reminders&trigger_reminders=1') . '" class="button button-primary">Send Test Reminders</a></p>';
    
    // Test 5: Check reminder settings
    echo '<h2>5. Reminder Settings</h2>';
    echo '<p>Reminders are sent on: 30, 14, 7, and 1 days before expiry</p>';
    echo '<p>Current time: ' . current_time('Y-m-d H:i:s') . '</p>';
    
    echo '</div>';
}

// Add admin menu for testing
function add_reminder_test_menu() {
    add_submenu_page(
        'membership-management',
        'Test Reminders',
        'Test Reminders',
        'manage_options',
        'test-reminders',
        'test_membership_reminder_system'
    );
}
add_action('admin_menu', 'add_reminder_test_menu');
