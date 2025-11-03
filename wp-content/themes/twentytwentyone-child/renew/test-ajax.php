<?php
// Simple test AJAX handler to debug the issue
add_action('wp_ajax_test_renew_ajax', 'test_renew_ajax_handler');
add_action('wp_ajax_nopriv_test_renew_ajax', 'test_renew_ajax_handler');

function test_renew_ajax_handler() {
    wp_send_json_success(array('message' => 'Test AJAX working', 'time' => current_time('mysql')));
}
