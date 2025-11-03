<?php
if (!defined('ABSPATH')) { exit; }

require_once get_stylesheet_directory() . '/renew/renew-logger.php';

// Include admin functionality
if (is_admin()) {
    require_once get_stylesheet_directory() . '/renew/renew-admin.php';
}

// Include test AJAX handler for debugging
require_once get_stylesheet_directory() . '/renew/test-ajax.php';

function renew_module_bootstrap() {
    add_shortcode('renew_certification', 'renew_shortcode_handler');
    add_action('init', 'renew_register_cpt');
    add_action('wp_enqueue_scripts', 'renew_enqueue_assets');
    add_action('wp_ajax_submit_cpd_form', 'renew_handle_cpd_submit');
    add_action('wp_ajax_nopriv_submit_cpd_form', 'renew_handle_cpd_submit');
}
add_action('after_setup_theme', 'renew_module_bootstrap');

function renew_register_cpt() {
    register_post_type('cpd_submission', array(
        'label' => 'CPD Submissions',
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'capability_type' => 'post',
        'supports' => array('title'),
        'menu_icon' => 'dashicons-media-spreadsheet',
    ));
}

function renew_enqueue_assets() {
    if (!is_page()) { return; }
    global $post; if (!$post) { return; }
    if (has_shortcode($post->post_content, 'renew_certification')) {
        wp_enqueue_style('renew-frontend', get_stylesheet_directory_uri() . '/renew/css/renew-frontend.css', array(), '1.0.0');
        wp_enqueue_script('renew-frontend', get_stylesheet_directory_uri() . '/renew/js/renew-frontend.js', array('jquery'), '1.0.0', true);
        wp_localize_script('renew-frontend', 'RenewAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('renew_nonce')
        ));
    }
}

function renew_shortcode_handler($atts) {
    ob_start();
    include get_stylesheet_directory() . '/renew/templates/renew-router.php';
    return ob_get_clean();
}

function renew_handle_cpd_submit() {
    try {
        // Log the request for debugging
        renew_log_info('CPD submit request received', array(
            'user_logged_in' => is_user_logged_in(),
            'post_data' => $_POST,
            'files_data' => array_keys($_FILES)
        ));
        
        // Check if user is logged in first
        if (!is_user_logged_in()) {
            renew_log_warn('CPD submit failed - user not logged in');
            wp_send_json_error(array('message' => 'Login required'));
        }

        // Verify nonce
        if (!check_ajax_referer('renew_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        $user_id = get_current_user_id();

        // Sanitize input data
        $name   = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $dob    = isset($_POST['dob']) ? sanitize_text_field($_POST['dob']) : '';
        $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : '';
        $level  = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : '';
        $sector = isset($_POST['sector']) ? sanitize_text_field($_POST['sector']) : '';
        $years  = isset($_POST['years']) && is_array($_POST['years']) ? $_POST['years'] : array();

        $errors = array();
        if (empty($name)) { $errors['name'] = 'Name is required'; }
        if (empty($dob)) { $errors['dob'] = 'Date of birth is required'; }
        if (!empty($dob) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) { $errors['dob'] = 'Invalid date format'; }
        if (empty($method) || $method !== 'CPD') { $errors['method'] = 'Invalid method'; }
        if (empty($level)) { $errors['level'] = 'Level is required'; }
        if (empty($sector)) { $errors['sector'] = 'Sector is required'; }
        if (empty($years)) { $errors['years'] = 'CPD points are required'; }
        
        // Validate CPD points are numeric and not negative
        if (!empty($years)) {
            foreach ($years as $year_num => $year_data) {
                if (is_array($year_data)) {
                    foreach (['training', 'workshops', 'seminars', 'publications', 'other'] as $cat) {
                        $value = isset($year_data[$cat]) ? $year_data[$cat] : 0;
                        if (!is_numeric($value) || $value < 0) {
                            $errors['years'] = 'CPD points must be valid numbers >= 0';
                            break 2;
                        }
                    }
                }
            }
        }

        if ($errors) {
            renew_log_warn('Validation failed on CPD submit', array('user' => $user_id, 'errors' => $errors));
            wp_send_json_error(array('message' => 'Validation failed', 'errors' => $errors));
        }

        $post_id = wp_insert_post(array(
            'post_type' => 'cpd_submission',
            'post_title' => $name . ' - ' . $level . ' - ' . current_time('Y-m-d H:i'),
            'post_status' => 'publish'
        ));

        if (is_wp_error($post_id) || !$post_id) {
            renew_log_error('Failed to create CPD submission', array('user' => $user_id, 'error' => is_wp_error($post_id) ? $post_id->get_error_message() : 'Unknown error'));
            wp_send_json_error(array('message' => 'Could not save submission'));
        }

        // Calculate total CPD points
        $total_cpd_points = 0;
        if (!empty($years)) {
            foreach ($years as $year_data) {
                if (is_array($year_data)) {
                    foreach (['training', 'workshops', 'seminars', 'publications', 'other'] as $cat) {
                        $total_cpd_points += floatval($year_data[$cat] ?? 0);
                    }
                }
            }
        }

        update_post_meta($post_id, '_user_id', $user_id);
        update_post_meta($post_id, '_name', $name);
        update_post_meta($post_id, '_dob', $dob);
        update_post_meta($post_id, '_method', $method);
        update_post_meta($post_id, '_level', $level);
        update_post_meta($post_id, '_sector', $sector);
        update_post_meta($post_id, '_years', $years);
        update_post_meta($post_id, '_total_cpd_points', $total_cpd_points);
        update_post_meta($post_id, '_status', 'pending');

        $uploaded_files = array();
        foreach (array('cpd_files', 'support_docs', 'previous_certificate') as $key) {
            if (!empty($_FILES[$key])) {
                $files = renew_handle_file_uploads($_FILES[$key]);
                if (is_wp_error($files)) {
                    renew_log_error('File upload failed', array('key' => $key, 'error' => $files->get_error_message()));
                    wp_send_json_error(array('message' => 'Upload failed: ' . $files->get_error_message()));
                }
                $uploaded_files[$key] = $files;
            }
        }
        update_post_meta($post_id, '_uploads', $uploaded_files);

        renew_log_info('CPD submission saved', array('post_id' => $post_id, 'user' => $user_id));
        wp_send_json_success(array('message' => 'Submission received', 'submission_id' => $post_id));
        
    } catch (Exception $e) {
        renew_log_error('Critical error in CPD submit', array('user' => $user_id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
        wp_send_json_error(array('message' => 'An unexpected error occurred. Please try again.'));
    }
}

function renew_handle_file_uploads($file_field) {
    try {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $results = array();
        $overrides = array('test_form' => false);
        
        if (is_array($file_field['name'])) {
            $count = count($file_field['name']);
            for ($i = 0; $i < $count; $i++) {
                // Check for upload errors
                if ($file_field['error'][$i] !== UPLOAD_ERR_OK) {
                    return new WP_Error('upload_error', 'File upload error: ' . $file_field['error'][$i]);
                }
                
                $file = array(
                    'name' => $file_field['name'][$i],
                    'type' => $file_field['type'][$i],
                    'tmp_name' => $file_field['tmp_name'][$i],
                    'error' => $file_field['error'][$i],
                    'size' => $file_field['size'][$i],
                );
                
                $movefile = wp_handle_upload($file, $overrides);
                if (isset($movefile['error'])) {
                    return new WP_Error('upload_error', $movefile['error']);
                }
                $results[] = $movefile;
            }
        } else {
            // Check for upload errors
            if ($file_field['error'] !== UPLOAD_ERR_OK) {
                return new WP_Error('upload_error', 'File upload error: ' . $file_field['error']);
            }
            
            $movefile = wp_handle_upload($file_field, $overrides);
            if (isset($movefile['error'])) {
                return new WP_Error('upload_error', $movefile['error']);
            }
            $results[] = $movefile;
        }
        return $results;
    } catch (Exception $e) {
        return new WP_Error('upload_error', 'File upload exception: ' . $e->getMessage());
    }
}


