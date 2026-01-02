<?php
/**
 * Email System Diagnostic Tool
 * 
 * Add this to functions.php TEMPORARILY to debug email issues
 * Remove after debugging is complete
 * 
 * Usage: Add ?email_debug=1&entry_id=123 to any admin page URL
 */

add_action('admin_init', 'ndtss_email_diagnostic_tool');

function ndtss_email_diagnostic_tool() {
    if (!isset($_GET['email_debug']) || !current_user_can('administrator')) {
        return;
    }
    
    $entry_id = isset($_GET['entry_id']) ? absint($_GET['entry_id']) : 0;
    
    if (!$entry_id) {
        wp_die('Please provide an entry_id parameter. Example: ?email_debug=1&entry_id=123');
    }
    
    $entry = GFAPI::get_entry($entry_id);
    
    if (is_wp_error($entry)) {
        wp_die('Entry not found: ' . $entry_id);
    }
    
    $form_id = $entry['form_id'];
    
    echo '<h1>Email System Diagnostic Report</h1>';
    echo '<p><strong>Entry ID:</strong> ' . $entry_id . '</p>';
    echo '<p><strong>Form ID:</strong> ' . $form_id . '</p>';
    echo '<hr>';
    
    // Determine form type and extract fields
    if ($form_id == 30) {
        $form_type = 'Form 30 (Retest Exam)';
        $exam_type = 'Retest Exam';
        $order_field = '12';
        $name_field = '19';
        $email_field = '26';
        $center_field = '9';
    } elseif ($form_id == 39) {
        $form_type = 'Form 39 (Renewal/Recertification Exam)';
        $exam_type = 'Renewal/Recertification Exam';
        $order_field = '13';
        $name_field = '1';
        $email_field = '12';
        $center_field = '9';
    } else {
        $form_type = 'Form 15 (Initial Exam)';
        $exam_type = 'Initial Exam';
        $order_field = '789';
        $name_field = '840';
        $email_field = '12';
        $center_field = '833';
    }
    
    echo '<h2>Form Type</h2>';
    echo '<p>' . $form_type . '</p>';
    
    echo '<h2>Field Mappings</h2>';
    echo '<table border="1" cellpadding="5">';
    echo '<tr><th>Data</th><th>Field ID</th><th>Value</th><th>Status</th></tr>';
    
    // Order Number
    $order_value = rgar($entry, $order_field);
    $order_status = !empty($order_value) ? '✅ OK' : '❌ EMPTY';
    echo '<tr><td>Order Number</td><td>' . $order_field . '</td><td>' . esc_html($order_value) . '</td><td>' . $order_status . '</td></tr>';
    
    // Candidate Name
    $name_value = rgar($entry, $name_field);
    $name_status = !empty($name_value) ? '✅ OK' : '❌ EMPTY';
    echo '<tr><td>Candidate Name</td><td>' . $name_field . '</td><td>' . esc_html($name_value) . '</td><td>' . $name_status . '</td></tr>';
    
    // Candidate Email
    $email_value = rgar($entry, $email_field);
    $email_status = is_email($email_value) ? '✅ VALID' : '❌ INVALID/EMPTY';
    echo '<tr><td>Candidate Email</td><td>' . $email_field . '</td><td>' . esc_html($email_value) . '</td><td>' . $email_status . '</td></tr>';
    
    // Center Name
    $center_value = trim(rgar($entry, $center_field));
    $center_status = !empty($center_value) ? '✅ OK' : '❌ EMPTY';
    echo '<tr><td>Center Name</td><td>' . $center_field . '</td><td>' . esc_html($center_value) . '</td><td>' . $center_status . '</td></tr>';
    
    echo '</table>';
    
    // User Email Fallback
    echo '<h2>User Email Fallback</h2>';
    $user_id = $entry['created_by'];
    $user_data = get_userdata($user_id);
    
    if ($user_data) {
        $user_email = $user_data->user_email;
        $user_email_status = is_email($user_email) ? '✅ VALID' : '❌ INVALID';
        echo '<p><strong>User ID:</strong> ' . $user_id . '</p>';
        echo '<p><strong>User Email:</strong> ' . esc_html($user_email) . ' ' . $user_email_status . '</p>';
        echo '<p><strong>Display Name:</strong> ' . esc_html($user_data->display_name) . '</p>';
    } else {
        echo '<p>❌ User not found</p>';
    }
    
    // Email Decision
    echo '<h2>Email Decision Logic</h2>';
    $final_email = '';
    $email_source = '';
    
    if (is_email($email_value)) {
        $final_email = $email_value;
        $email_source = 'Form Field ' . $email_field;
    } elseif ($user_data && is_email($user_data->user_email)) {
        $final_email = $user_data->user_email;
        $email_source = 'User Account (Fallback)';
    }
    
    if ($final_email) {
        echo '<p>✅ <strong>Email will be sent to:</strong> ' . esc_html($final_email) . '</p>';
        echo '<p><strong>Source:</strong> ' . $email_source . '</p>';
    } else {
        echo '<p>❌ <strong>NO VALID EMAIL FOUND</strong></p>';
        echo '<p>Email will NOT be sent!</p>';
    }
    
    // Center Verification
    echo '<h2>Center Verification</h2>';
    if (!empty($center_value)) {
        $center_post = get_page_by_title($center_value, OBJECT, 'exam_center');
        if ($center_post) {
            $center_address = get_post_meta($center_post->ID, 'location', true);
            echo '<p>✅ <strong>Center Found:</strong> ' . esc_html($center_post->post_title) . '</p>';
            echo '<p><strong>Center ID:</strong> ' . $center_post->ID . '</p>';
            echo '<p><strong>Location:</strong> ' . esc_html($center_address) . '</p>';
        } else {
            echo '<p>❌ <strong>Center NOT Found:</strong> No exam center with name "' . esc_html($center_value) . '"</p>';
            echo '<p>Create an exam center with this exact name, or update the entry.</p>';
        }
    } else {
        echo '<p>❌ Center name is empty</p>';
    }
    
    // Email Preview
    echo '<h2>Email Preview</h2>';
    if ($final_email) {
        echo '<p><strong>Subject:</strong> ' . esc_html($exam_type) . ' Assignment Details: Order #' . esc_html($order_value) . '</p>';
        echo '<p><strong>To:</strong> ' . esc_html($final_email) . '</p>';
        echo '<p><strong>Candidate Name in Email:</strong> ' . esc_html($name_value ?: 'N/A') . '</p>';
        echo '<p><strong>Order Number in Email:</strong> ' . esc_html($order_value ?: 'N/A') . '</p>';
        echo '<p><strong>Center Name in Email:</strong> ' . esc_html($center_value ?: 'N/A') . '</p>';
    }
    
    // WordPress Email Test
    echo '<h2>WordPress Email Test</h2>';
    echo '<p>Testing if WordPress can send emails...</p>';
    
    $test_email = get_option('admin_email');
    $test_subject = 'NDTSS Email System Test';
    $test_message = 'This is a test email from the NDTSS email diagnostic tool. If you receive this, WordPress email is working.';
    
    add_filter('wp_mail_content_type', function() { return 'text/html'; });
    $test_sent = wp_mail($test_email, $test_subject, $test_message);
    remove_filter('wp_mail_content_type', function() { return 'text/html'; });
    
    if ($test_sent) {
        echo '<p>✅ Test email sent to: ' . esc_html($test_email) . '</p>';
        echo '<p>Check your inbox (and spam folder)</p>';
    } else {
        echo '<p>❌ Test email FAILED to send</p>';
        echo '<p>WordPress email system may not be configured correctly.</p>';
        echo '<p>Consider installing WP Mail SMTP plugin.</p>';
    }
    
    // Recommendations
    echo '<h2>Recommendations</h2>';
    echo '<ul>';
    
    if (empty($order_value)) {
        echo '<li>❌ Add order number to field ' . $order_field . '</li>';
    }
    if (empty($name_value)) {
        echo '<li>❌ Add candidate name to field ' . $name_field . '</li>';
    }
    if (!is_email($email_value) && (!$user_data || !is_email($user_data->user_email))) {
        echo '<li>❌ Add valid email to field ' . $email_field . ' OR update user account email</li>';
    }
    if (empty($center_value)) {
        echo '<li>❌ Add center name to field ' . $center_field . '</li>';
    } elseif (!isset($center_post)) {
        echo '<li>❌ Create exam center with name: "' . esc_html($center_value) . '"</li>';
    }
    
    if (!empty($order_value) && !empty($name_value) && $final_email && !empty($center_value) && isset($center_post)) {
        echo '<li>✅ All data looks good! Email should be sent successfully.</li>';
    }
    
    echo '</ul>';
    
    echo '<hr>';
    echo '<p><a href="' . admin_url('admin.php?page=gf_entries&view=entry&id=' . $form_id . '&lid=' . $entry_id) . '">← Back to Entry</a></p>';
    
    exit;
}
