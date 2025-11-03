<?php
if ( !defined( 'ABSPATH' ) ) exit;

// Include Certificate Lifecycle Manager
require_once get_stylesheet_directory() . '/certificate-lifecycle-manager.php';

// Include Certificate Renewal Admin Dashboard
if (is_admin()) {
    require_once get_stylesheet_directory() . '/certificate-renewal-admin.php';
}

// Include Certificate Lifecycle Test Suite
require_once get_stylesheet_directory() . '/certificate-lifecycle-test-suite.php';

// Include Certificate Lifecycle Migration
require_once get_stylesheet_directory() . '/certificate-lifecycle-migration.php';

require_once get_stylesheet_directory() . '/renew/renew-module.php';

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
	function chld_thm_cfg_locale_css( $uri ){
		if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
			$uri = get_template_directory_uri() . '/rtl.css';
		return $uri;
	}
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );

if ( !function_exists( 'child_theme_configurator_css' ) ):
	function child_theme_enqueue_frontend_styles() {
		wp_enqueue_style(
			'chld_thm_cfg_child',
			trailingslashit(get_stylesheet_directory_uri()) . 'style.css',
			array('twenty-twenty-one-custom-color-overrides', 'twenty-twenty-one-style', 'twenty-twenty-one-print-style'),
		filemtime(get_stylesheet_directory() . '/style.css') // Better than random versioning
	);

		wp_enqueue_style(
		    'frontend-css',
		    get_stylesheet_directory_uri() . '/css/frontend-style.css?v=' . wp_rand(),
		    array(),
		    null
		);

	}
endif;
add_action('wp_enqueue_scripts', 'child_theme_enqueue_frontend_styles');

function child_theme_enqueue_frontend_scripts() {
	wp_enqueue_script(
		'jquery-validate',
		'https://cdn.jsdelivr.net/jquery.validation/1.16.0/jquery.validate.min.js',
		array('jquery'),
		null,
		true
	);

	wp_enqueue_script(
		'sweetalert2-frontend',
		get_stylesheet_directory_uri() . '/js/sweetalert2.all.min.js',
		array('jquery'),
		'11.0.0',
		true
	);
}
add_action('wp_enqueue_scripts', 'child_theme_enqueue_frontend_scripts');

function child_theme_enqueue_admin_assets() {
	wp_enqueue_style(
		'backend-css',
		get_stylesheet_directory_uri() . '/css/backend-style.css',
		array(),
		filemtime(get_stylesheet_directory() . '/css/backend-style.css')
	);

	wp_enqueue_style(
		'datatables-css',
		'https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css'
	);

	wp_enqueue_script('jquery');
	wp_enqueue_script(
		'datatables-js',
		'https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js',
		array('jquery'),
		null,
		true
	);

	wp_enqueue_script(
		'sweetalert2-admin',
		get_stylesheet_directory_uri() . '/js/sweetalert2.all.min.js',
		array('jquery'),
		'11.0.0',
		true
	);
}
add_action('admin_enqueue_scripts', 'child_theme_enqueue_admin_assets');

add_filter('gform_noconflict_styles', function($styles) {
	$styles[] = 'backend-css';
	return $styles;
});

add_action('admin_init', function () {
	remove_all_actions('admin_notices');
	remove_all_actions('all_admin_notices');
});

if (!current_user_can('administrator')) {
	add_filter('show_admin_bar', '__return_false');
}

$custom_includes = [
	'/forms/register_form.php',
	'/forms/login_form.php',
	'/forms/forgot-password.php',
	'/user-profile.php',
	'/panel/verified_users.php',
	// '/panel/membership_module.php',
	'/panel/event_enteries.php',
	'/panel/gravity_functions.php',
	'/gravity_form_functions.php',
	'/shortcodes.php',
	'/event_functions.php',
	'/panel/center_module.php',
	// '/panel/examiner_module.php',
	// '/panel/submitted_forms.php',
	'/panel/invigilator_module.php',
	//'/panel/aqb_dashboard.php',
	'/panel/form-31-workflow.php',
	'/forms/renew-recert-by-exam.php',
	'/membership/functions.php',
	'/panel/assign_admins.php',
	'/panel/certified_users.php',
];

foreach ($custom_includes as $file) {
	$path = get_stylesheet_directory() . $file;
	if (file_exists($path)) {
		require_once $path;
	}
}
add_action('gform_after_submission_15', 'save_user_personal_employment_details', 10, 2);
function save_user_personal_employment_details($entry, $form) {
	$user_id = get_current_user_id();
	if (!$user_id) return;
	$entry_id = rgar($entry, 'id');
	$order_number = 'EXAM-' . date('Ymd') . '-' . $entry_id;
	GFAPI::update_entry_field($entry_id, '789', $order_number);
	update_user_meta($user_id, 'exam_form_' . $entry_id . '_approval_status', 'pending');
	update_user_meta($user_id, 'exam_form_' . $entry_id . '_submitted_at', current_time('mysql'));
	$meta_key = 'form_' . $form['id'] . '_entry_id';
	update_user_meta($user_id, $meta_key, $entry_id);
	$center_name = rgar($entry, '833'); 
	$center_post = get_page_by_title($center_name, OBJECT, 'exam_center');

	if ($center_post) {
		gform_update_meta($entry_id, '_linked_exam_center', $center_post->ID);
		send_exam_submission_notification($entry_id, $order_number, $center_post);
	}
	   $prefix = rgar($entry, '839'); // Dropdown
    if ($prefix === 'Other') {
        $custom_prefix = rgar($entry, '4'); // Custom input
        if (!empty($custom_prefix)) {
            update_user_meta($user_id, 'prefix', sanitize_text_field($custom_prefix));
        }
    } else {
        update_user_meta($user_id, 'prefix', sanitize_text_field($prefix));
    }
       $national_id = rgar($entry, '835');

    if (!empty($national_id)) {
        update_user_meta($user_id, 'national_id', sanitize_text_field($national_id));
		}
		$photo_url = rgar($entry, '861'); // Replace 3 with your actual field ID

    if ($photo_url) {
        update_user_meta($user_id, 'custom_profile_photo', esc_url($photo_url));

        // OPTIONAL: Set as WP User Avatar if using native WP user avatars
        update_user_meta($user_id, 'wp_user_avatar', esc_url($photo_url));
    }
	
		$id_proof_url = rgar($entry, '860'); // Replace 3 with your actual field ID

		if ($id_proof_url) {
			update_user_meta($user_id, 'custom_id_proof', esc_url($id_proof_url));
		}
    update_user_meta($user_id, 'family_name', rgar($entry, '1'));
	update_user_meta($user_id, 'dob', rgar($entry, '3'));
	update_user_meta($user_id, 'title', rgar($entry, '4'));
	update_user_meta($user_id, 'home_address', rgar($entry, '723'));
	update_user_meta($user_id, 'home_country', rgar($entry, '785.1'));
	update_user_meta($user_id, 'home_state', rgar($entry, '785.2'));
	update_user_meta($user_id, 'home_city', rgar($entry, '786'));
	update_user_meta($user_id, 'home_postal_code', rgar($entry, '726'));
	update_user_meta($user_id, 'home_email', rgar($entry, '12'));
	$home_consent = rgar($entry, '15.1');
	$work_consent = rgar($entry, '29.1');
	$final_consent = !empty($home_consent) ? $home_consent : $work_consent;

	update_user_meta($user_id, 'correspondence_address', $final_consent);
	update_user_meta($user_id, 'company_name', rgar($entry, '17'));
	update_user_meta($user_id, 'job_title', rgar($entry, '18'));
	update_user_meta($user_id, 'work_address', rgar($entry, '728'));
	update_user_meta($user_id, 'work_country', rgar($entry, '787.1'));
	update_user_meta($user_id, 'work_state', rgar($entry, '787.2'));
	update_user_meta($user_id, 'work_city', rgar($entry, '788'));
	update_user_meta($user_id, 'work_postal_code', rgar($entry, '731'));
	update_user_meta($user_id, 'phone', rgar($entry, '196'));
	update_user_meta($user_id, 'business_email', rgar($entry, '27'));
}

add_filter('get_avatar_url', 'custom_user_avatar_url', 10, 3);
function custom_user_avatar_url($url, $id_or_email, $args) {
    $user = false;

    if (is_numeric($id_or_email)) {
        $user = get_user_by('id', $id_or_email);
    } elseif (is_object($id_or_email)) {
        if (!empty($id_or_email->user_id)) {
            $user = get_user_by('id', $id_or_email->user_id);
        }
    } else {
        $user = get_user_by('email', $id_or_email);
    }

    if ($user) {
        $custom_avatar = get_user_meta($user->ID, 'custom_profile_photo', true);
        if ($custom_avatar) {
            return esc_url($custom_avatar);
        }
    }

    return $url;
}


add_action('gform_after_update_entry', 'save_user_personal_employment_details_on_update', 10, 2);

function save_user_personal_employment_details_on_update($form, $entry_id) {
    $entry = GFAPI::get_entry($entry_id);

    // Ensure no error occurred while retrieving the entry
    if (is_wp_error($entry)) {
        return;
    }

    // Check if this is the specific form (ID 15)
    if (rgar($entry, 'form_id') != 15) {
        return;
    }

    // Call your custom function to save the details
    save_user_personal_employment_details_update($entry, $form);
}


function save_user_personal_employment_details_update($entry, $form) {
 
    $user_id =  $entry['created_by'];
    
    if (!$user_id) return;

    $entry_id = rgar($entry, 'id');

    // Generate or keep existing order number
    // $order_number = rgar($entry, '789'); 
    // if (empty($order_number)) {
    //     $order_number = 'EXAM-' . date('Ymd') . '-' . $entry_id;
    //     GFAPI::update_entry_field($entry_id, '789', $order_number);
    // }

    // $meta_key = 'form_' . $form['id'] . '_entry_id';
    // update_user_meta($user_id, $meta_key, $entry_id);

    // Link to exam center
    $center_name = rgar($entry, '833');
    $center_post = get_page_by_title($center_name, OBJECT, 'exam_center');
    if ($center_post) {
        gform_update_meta($entry_id, '_linked_exam_center', $center_post->ID);
       // send_exam_submission_notification($entry_id, $order_number, $center_post);
    }
	
	$passport_photo_url = rgar($entry, '861');
	if (!empty($passport_photo_url)) {
		update_user_meta($user_id, 'custom_profile_photo', esc_url($passport_photo_url));
	}
	$id_proof = rgar($entry, '860');
	if (!empty($id_proof)) {
		update_user_meta($user_id, 'custom_id_proof', esc_url($id_proof));
	}

    // Personal details
    // $prefix = rgar($entry, '839'); 
    // if ($prefix === 'Other') {
    //     $custom_prefix = rgar($entry, '4'); 
    //     if (!empty($custom_prefix)) {
    //         update_user_meta($user_id, 'prefix', sanitize_text_field($custom_prefix));
    //     }
    // } else {
    //     update_user_meta($user_id, 'prefix', sanitize_text_field($prefix));
    // }

    // $national_id = rgar($entry, '835');
    // if (!empty($national_id)) {
    //     update_user_meta($user_id, 'national_id', sanitize_text_field($national_id));
    // }
	//   $first_name = rgar($entry, '840');
	// $last_name = rgar($entry, '841');

	// // Save first and last name as user meta
	// update_user_meta($user_id, 'first_name', $first_name);
	// update_user_meta($user_id, 'last_name', $last_name);

	// // Set display_name (must use wp_update_user)
	// $display_name = trim($first_name . ' ' . $last_name);
	// wp_update_user([
	//     'ID' => $user_id,
	//     'display_name' => $display_name,
	// ]);
    //update_user_meta($user_id, 'family_name', rgar($entry, '1'));
    // update_user_meta($user_id, 'dob', rgar($entry, '3'));
    // update_user_meta($user_id, 'title', rgar($entry, '4'));
    // update_user_meta($user_id, 'home_address', rgar($entry, '723'));
    // update_user_meta($user_id, 'home_country', rgar($entry, '785.1'));
    // update_user_meta($user_id, 'home_state', rgar($entry, '785.2'));
    // update_user_meta($user_id, 'home_city', rgar($entry, '786'));
    // update_user_meta($user_id, 'home_postal_code', rgar($entry, '726'));
    // update_user_meta($user_id, 'home_email', rgar($entry, '12'));

    // $home_consent = rgar($entry, '15.1');
    // $work_consent = rgar($entry, '29.1');
    // $final_consent = !empty($home_consent) ? $home_consent : $work_consent;
    // update_user_meta($user_id, 'correspondence_address', $final_consent);

    // update_user_meta($user_id, 'company_name', rgar($entry, '17'));
    // update_user_meta($user_id, 'job_title', rgar($entry, '18'));
    // update_user_meta($user_id, 'work_address', rgar($entry, '728'));
    // update_user_meta($user_id, 'work_country', rgar($entry, '787.1'));
    // update_user_meta($user_id, 'work_state', rgar($entry, '787.2'));
    // update_user_meta($user_id, 'work_city', rgar($entry, '788'));
    // update_user_meta($user_id, 'work_postal_code', rgar($entry, '731'));
    // update_user_meta($user_id, 'phone', rgar($entry, '196'));
    // update_user_meta($user_id, 'business_email', rgar($entry, '27'));
}

add_action('gform_after_submission_30', 'save_retest_details', 10, 2);
function save_retest_details($entry, $form) {
	$user_id = get_current_user_id();
	if (!$user_id) return;
	$entry_id = rgar($entry, 'id');
	$order_number = 'EXAM-' . date('Ymd') . '-' . $entry_id;
	GFAPI::update_entry_field($entry_id, '12', $order_number);
	$center_name = rgar($entry, '9'); 
	$center_post = get_page_by_title($center_name, OBJECT, 'exam_center');

	if ($center_post) {
		gform_update_meta($entry_id, '_linked_exam_center', $center_post->ID);
		send_retest_submission_notification($entry_id,  $center_post);
	}
	
    
}


add_filter('gform_pre_render_15', 'populate_user_data_into_form');
add_filter('gform_pre_validation_15', 'populate_user_data_into_form');

add_filter('gform_admin_pre_render_15', 'populate_user_data_into_form');
add_filter('gform_pre_submission_filter_15', 'populate_user_data_into_form');

/**
 * Populate user data into Gravity Form 15 (Exam Registration Form)
 * 
 * This function automatically populates form fields with existing user data,
 * including special handling for file upload fields (photo and ID proof).
 * 
 * @param array $form The Gravity Form object
 * @return array Modified form object with populated data
 */
function populate_user_data_into_form($form) {
	// Security check: Ensure user is logged in
	$user_id = get_current_user_id();
	if (!$user_id) return $form;
	
	// Get user's saved prefix for dropdown handling
	$saved_prefix = get_user_meta($user_id, 'prefix', true);
	$standard_prefixes = ['Dr.', 'Mr.', 'Ms.', 'Mrs.', 'Prof.'];

	// Get exam centers for dropdown population
	$post_type = 'exam_center';
	$posts = get_posts(array(
		'post_type'      => $post_type,
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC'
	));

	// Loop through all form fields and populate with user data
	foreach ($form['fields'] as &$field) {
		if (!isset($field->id)) continue;
		
		switch ($field->id) {
			// Basic user information fields
			case 1: 
				$field->defaultValue = get_user_meta($user_id, 'family_name', true); 
				break;
			case 840:
				$field->defaultValue = get_user_meta($user_id, 'first_name', true);
				break;
			case 841:
				$field->defaultValue = get_user_meta($user_id, 'last_name', true);
				break;
			case 835:
				$field->defaultValue = get_user_meta($user_id, 'national_id', true);
				break;
			case 3: 
				$field->defaultValue = get_user_meta($user_id, 'dob', true); 
				break;

			// Field 860: ID Proof Upload Field
			case 860:
				$id_proof_url = get_user_meta($user_id, 'custom_id_proof', true);
				
				// Check if ID proof file exists and is accessible
				if (!empty($id_proof_url) && is_file_accessible($id_proof_url)) {
					// Check if preview already exists to prevent duplicates
					$existing_description = get_field_description($field);
					if (strpos($existing_description, 'data-field-id="860"') === false) {
						// Add preview of existing ID proof to field description
						$field->description = $existing_description . 
							'<div class="existing-file-preview" data-field-id="860">
								<div class="file-preview-header">
									<strong>Current ID Proof:</strong>
									<p class="file-preview-note">Upload a new ID proof only if you want to replace this one.</p>
								</div>
								<div class="file-preview-container">
									<a href="' . esc_url($id_proof_url) . '" target="_blank" class="file-preview-link">
										<img src="' . esc_url($id_proof_url) . '" alt="Current ID Proof" 
											 class="file-preview-image" 
											 style="max-width:150px; max-height:150px; border:1px solid #ccc; padding:5px; border-radius:4px;">
									</a>
									<div class="file-preview-actions">
										<small><em>Click to view full size</em></small>
									</div>
								</div>
							</div>';
					}
					
					// Make field optional since file already exists
					$field->isRequired = false;
					
					// Add CSS class for styling
					if (!isset($field->cssClass)) {
						$field->cssClass = '';
					}
					if (strpos($field->cssClass, 'has-existing-file') === false) {
						$field->cssClass .= ' has-existing-file';
					}
				} else {
					// Clean up invalid file reference if URL is not accessible
					if (!empty($id_proof_url)) {
						delete_user_meta($user_id, 'custom_id_proof');
					}
				}
				break;

			// Field 861: Profile Photo Upload Field (Required)
			case 861:
				$photo_url = get_user_meta($user_id, 'custom_profile_photo', true);
				
				// Check if profile photo exists and is accessible
				if (!empty($photo_url) && is_file_accessible($photo_url)) {
					// Check if preview already exists to prevent duplicates
					$existing_description = get_field_description($field);
					if (strpos($existing_description, 'data-field-id="861"') === false) {
						// Add preview of existing photo to field description
						$field->description = $existing_description . 
							'<div class="existing-file-preview" data-field-id="861">
								<div class="file-preview-header">
									<strong>Current Profile Photo:</strong>
									<p class="file-preview-note">Upload a new photo only if you want to replace this one. Please ensure it\'s passport size.</p>
								</div>
								<div class="file-preview-container">
									<img src="' . esc_url($photo_url) . '" alt="Current Profile Photo" 
										 class="file-preview-image profile-photo-preview" 
										 style="max-width:150px; max-height:150px; border:1px solid #ccc; padding:5px; border-radius:4px;">
									<div class="file-preview-actions">
										<small><em>This photo will be used as your profile picture</em></small>
									</div>
								</div>
							</div>';
					}
					
					// Make field optional since photo already exists
					$field->isRequired = false;
					
					// Add CSS class for styling
					if (!isset($field->cssClass)) {
						$field->cssClass = '';
					}
					if (strpos($field->cssClass, 'has-existing-file') === false) {
						$field->cssClass .= ' has-existing-file';
					}
					if (strpos($field->cssClass, 'profile-photo-field') === false) {
						$field->cssClass .= ' profile-photo-field';
					}
				} else {
					// Clean up invalid file reference if URL is not accessible
					if (!empty($photo_url)) {
						delete_user_meta($user_id, 'custom_profile_photo');
						// Also clean up WP user avatar if it was set
						delete_user_meta($user_id, 'wp_user_avatar');
					}
				}
				break;

			// Prefix handling (dropdown with custom option)
			case 839:
				if (in_array($saved_prefix, $standard_prefixes)) {
					$field->defaultValue = $saved_prefix;
				} else {
					$field->defaultValue = 'Other';
				}
				break;
			case 4:
				// Custom prefix field - only populate if saved prefix is not standard
				if (!in_array($saved_prefix, $standard_prefixes)) {
					$field->defaultValue = $saved_prefix;
				}
				break;

			// Address and contact information fields
			case 723: $field->defaultValue = get_user_meta($user_id, 'home_address', true); break;
			case 734: $field->defaultValue = get_user_meta($user_id, 'home_state', true); break;
			case 786: $field->defaultValue = get_user_meta($user_id, 'home_city', true); break;
			case 726: $field->defaultValue = get_user_meta($user_id, 'home_postal_code', true); break;
			case 12: $field->defaultValue = get_user_meta($user_id, 'home_email', true); break;
			case 17: $field->defaultValue = get_user_meta($user_id, 'company_name', true); break;
			case 18: $field->defaultValue = get_user_meta($user_id, 'job_title', true); break;
			case 728: $field->defaultValue = get_user_meta($user_id, 'work_address', true); break;
			case 736: $field->defaultValue = get_user_meta($user_id, 'work_state', true); break;
			case 788: $field->defaultValue = get_user_meta($user_id, 'work_city', true); break;
			case 731: $field->defaultValue = get_user_meta($user_id, 'work_postal_code', true); break;
			case 196: $field->defaultValue = get_user_meta($user_id, 'whatsapp', true); break;
			case 28: $field->defaultValue = get_user_meta($user_id, 'business_mobile', true); break;
			case 26: $field->defaultValue = get_user_meta($user_id, 'business_phone', true); break;
			case 25: $field->defaultValue = get_user_meta($user_id, 'business_fax', true); break;
			case 27: $field->defaultValue = get_user_meta($user_id, 'business_email', true); break;

			// Exam center dropdown population
			case 833:
				$choices = array();
				foreach ($posts as $post) {
					$choices[] = array(
						'text'  => $post->post_title,
						'value' => $post->post_title
					);
				}
				$field->choices = $choices;
				break;
		}
	}

	return $form;
}

/**
 * Helper function to check if a file URL is accessible
 * 
 * @param string $file_url The file URL to check
 * @return bool True if file is accessible, false otherwise
 */
function is_file_accessible($file_url) {
	if (empty($file_url)) {
		return false;
	}
	
	// Check if it's a valid URL
	if (!filter_var($file_url, FILTER_VALIDATE_URL)) {
		return false;
	}
	
	// For local files, convert URL to file path and check if file exists
	$upload_dir = wp_upload_dir();
	if (strpos($file_url, $upload_dir['baseurl']) === 0) {
		$file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
		return file_exists($file_path) && is_readable($file_path);
	}
	
	// For external URLs, we'll assume they're accessible
	// In production, you might want to add a HEAD request check
	return true;
}

/**
 * Helper function to get existing field description or return empty string
 * 
 * @param object $field The Gravity Form field object
 * @return string The existing description or empty string
 */
function get_field_description($field) {
	return isset($field->description) ? $field->description : '';
}

add_action('gform_after_submission_12', 'paid_event_gravity_form_to_usermeta', 10, 2);
function paid_event_gravity_form_to_usermeta($entry, $form) {
	$user_id = get_current_user_id();
	$event_id = get_the_ID();
	if ($user_id && $event_id) {
		$meta_key_entry = 'paid_event_form_entry_' . $event_id;
		$meta_key_form = 'paid_event_form_id_' . $event_id;
		$meta_key_status = 'event_' . $event_id . '_approval_status';
		update_user_meta($user_id, $meta_key_entry, $entry['id']);
		update_user_meta($user_id, $meta_key_form, $form['id']);
		update_user_meta($user_id, $meta_key_status, 'Pending');
		add_user_meta($user_id, 'registered_event_ids', $event_id, false);
	}
}

function send_formatted_email($to, $subject, $message) {
	$site_name = get_bloginfo('name');
	$admin_email = get_option('admin_email', get_bloginfo('admin_email'));
	$headers = [
		'Content-Type: text/html; charset=UTF-8',
		"From: {$site_name} <{$admin_email}>"
	];
	wp_mail($to, $subject, $message, $headers);
}

function gf_auto_lock_inline_script() {
	?>
	<script>
		jQuery(document).ready(function($) {
			function applyReadOnly() {
				$('.gf_auto_lock .ginput_container_text input').each(function() {
					if ($(this).val().trim() !== '') {
						$(this).prop('readonly', true);
					} else {
						$(this).prop('readonly', false);
					}
				});
			}
			applyReadOnly();
			$('.gf_auto_lock input').on('input', applyReadOnly);
		});
	</script>
	<?php  
}

add_action('wp_footer', 'gf_auto_lock_inline_script');
function get_email_template($email_title, $email_content) {
	$template = '
	<html>
	<head>
	<style>
	body {font-family: Arial, sans-serif; color: #333;}
	.content {background-color: #f4f4f4; padding: 20px; border-radius: 10px;}
	.button {
		background-color: #dc3545;
		color: white;
		padding: 10px 20px;
		text-decoration: none;
		border-radius: 5px;
		margin-top: 10px;
		display: inline-block;
	}
	.footer {font-size: 14px; color: #777; margin-top: 10px;}
	</style>
	</head>
	<body>
	<div class="content">
	<h2>' . esc_html($email_title) . '</h2>
	<p>' . wp_kses_post($email_content) . '</p>
	<div class="footer">
	<p>&copy; ' . date('Y') . ' Non-Destructive Testing Society (Singapore). All rights reserved.</p>
	</div>
	</div>
	</body>
	</html>
	';    
	return $template;
}

function average_marks(){?>
	<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function () {
			function updateAverage() {
        const fields = ['input_25_34', 'input_25_31','input_25_36', 'input_25_39', 'input_25_40','input_25_42', 'input_25_44','input_25_46', 'input_25_48', 'input_25_50']; // General + Specific Marks
        let total = 0;
        let count = 0;

        fields.forEach(function(id) {
        	const el = document.getElementById(id);
        	if (el && el.value.trim() !== '') {
        		total += parseFloat(el.value) || 0;
        		count++;
        	}
        });

        const averageField = document.getElementById('input_25_35'); // Average
        if (averageField) {
        	averageField.value = count > 0 ? (total / count).toFixed(2) : '';
        }
    }
    document.getElementById('input_25_34').addEventListener('input', updateAverage);
    document.getElementById('input_25_31').addEventListener('input', updateAverage);
    document.getElementById('input_25_36').addEventListener('input', updateAverage);
    document.getElementById('input_25_39').addEventListener('input', updateAverage);
    document.getElementById('input_25_40').addEventListener('input', updateAverage);
    document.getElementById('input_25_42').addEventListener('input', updateAverage);
    document.getElementById('input_25_44').addEventListener('input', updateAverage);
    document.getElementById('input_25_46').addEventListener('input', updateAverage);
    document.getElementById('input_25_48').addEventListener('input', updateAverage);
    document.getElementById('input_25_50').addEventListener('input', updateAverage);
});
</script>
<?php
}

add_action('wp_footer', 'set_readonly_for_price_field');
function set_readonly_for_price_field() {
	?>
	<script type="text/javascript">

		jQuery(document).ready(function($) {
			function setReadonlyPriceField() {
				$('#input_12_28').prop('readonly', true);
				$('#input_12_28').on('focus', function() {
                $(this).blur();  // Prevent focus on the field
            });
			}
			function setExamCenterSelection(radioGroupName, targetFieldID) {
				$('input[name="' + radioGroupName + '"]').on('change', function() {
					var selectedExamCenterID = $('input[name="' + radioGroupName + '"]:checked').attr('id');
					var selectedExamCenterLabel = $('label[for="' + selectedExamCenterID + '"]').text().trim();if (selectedExamCenterLabel) {
						$('#' + targetFieldID).val(selectedExamCenterLabel).prop('readonly', true);
					}
				});
				$('#' + targetFieldID).prop('readonly', true);
			}
			setReadonlyPriceField();
			$(document).on('gform_post_render', function() {
				setReadonlyPriceField();
			});
			var examCenterMapping = [
				{ radioGroup: 'input_590', targetField: 'input_15_215' },
				{ radioGroup: 'input_594', targetField: 'input_15_259' },
				{ radioGroup: 'input_714', targetField: 'input_15_411' },
				{ radioGroup: 'input_652', targetField: 'input_15_415' },
				{ radioGroup: 'input_716', targetField: 'input_15_413' },
				{ radioGroup: 'input_717', targetField: 'input_15_417' },
				{ radioGroup: 'input_718', targetField: 'input_15_505' },
				{ radioGroup: 'input_719', targetField: 'input_15_537' },
				{ radioGroup: 'input_720', targetField: 'input_15_706' },
				];
			examCenterMapping.forEach(function(item) {
				setExamCenterSelection(item.radioGroup, item.targetField);
			});


			function updateAverage() {
				const fieldIds = [31, 34];
				let total = 0;
				let count = 0;

				fieldIds.forEach(function (fid) {
					const el = document.querySelector('#input_25_' + fid);
					if (el && el.value !== '') {
						total += parseFloat(el.value) || 0;
						count++;
					}
				});

				const average = count > 0 ? (total / count).toFixed(2) : '';
        const averageField = document.querySelector('#input_25_35'); // replace XXX with your Average field ID
        if (averageField) {
        	averageField.value = average;
        }
    }

    ['input_25_31', 'input_25_34'].forEach(function (id) {
    	const el = document.getElementById(id);
    	if (el) {
    		el.addEventListener('input', updateAverage);
    	}
    });
});
</script>
<?php
}

add_filter('tribe_get_cost', function($cost, $event_id) {
	$member_price = get_post_meta($event_id, 'member_price', true);
	$non_member_price = get_post_meta($event_id, '_EventCost', true);
	if ($member_price === '' && $non_member_price === '') {
		return esc_html__('0', 'text-domain');
	}
	$current_user = wp_get_current_user();
	$is_member = in_array('member', $current_user->roles);
	$price = $is_member ? $member_price : $non_member_price;
	return esc_html($price !== '' ? $price : '0');
}, 10, 2);

add_filter('map_meta_cap', function($caps, $cap, $user_id) {
	$restricted_admin_users = ['skbabu', 'M S Vetriselvan', 'Nath Kaushal', 'Joyce'];
	$user = get_userdata($user_id);
	if ($user && in_array($user->user_login, $restricted_admin_users)) {
		$restricted_caps = [
			'install_plugins',
			'update_plugins',
			'edit_themes',
			'switch_themes',
			'update_core',
			'activate_plugins',
			'edit_theme_options'
		];
		if (in_array($cap, $restricted_caps)) {
			return ['do_not_allow'];
		}
	}

	return $caps;
}, 10, 3);

function has_micro_permission( $role, $permission ) {

	$permissions = get_option( "micro_permissions_{$role}", [] );

	return in_array( $permission, $permissions, true );
}
function current_user_has_micro_permission( $permission ) {
	$user = wp_get_current_user();

	if ( empty( $user->roles ) ) {
		return false;
	}

	foreach ( $user->roles as $role ) {
		if ( has_micro_permission( $role, $permission ) ) {

			return true;
		}
	}

	return false;
}

add_action('upgrader_process_complete', function($upgrader_object, $options) {
	if ($options['action'] === 'update' && $options['type'] === 'plugin') {
		$file = WP_PLUGIN_DIR . '/gforms-addon-for-country-and-state-selection/includes/functions.php';
		if (file_exists($file)) {
			$content = file_get_contents($file);
			$content = preg_replace('/\?>\s*$/', '', $content);
			file_put_contents($file, $content);
		}
	}
}, 10, 2);

add_filter( 'wp_logging_should_ignore_error', function( $ignore, $error ) {
	return !in_array( $error['type'], [ E_ERROR, E_PARSE ], true );
}, 10, 2 );

function get_admin_email_addresses() {
	$admins = get_users([
		'role'   => 'administrator',
		'fields' => ['user_email'],
	]);

	$emails = [];

	foreach ($admins as $admin) {
		if (!empty($admin->user_email) && is_email($admin->user_email)) {
			$emails[] = sanitize_email($admin->user_email);
		}
	}

	return $emails;
}


function send_retest_submission_notification($entry_id, $center_post) {
    $form_id = 30;
    $entry = GFAPI::get_entry($entry_id);

    // Candidate info
    $candidate_email = rgar($entry, '26'); 
    $candidate_name  = rgar($entry, '19'); 
	$order_number    = rgar($entry, '12');
    $center_name     = get_the_title($center_post->ID);

    // --- 1. Send mail to candidate ---
    if (is_email($candidate_email)) {
        $subject = 'Retest Examination Form – Acknowledgement';
		$body = "
		    Dear {$candidate_name},<br><br>
		    We have received your <strong>retest examination form</strong>. Our team is currently reviewing your submission.<br><br>
		    <strong>Order Number:</strong> {$order_number}<br>
		    <strong>Examination Center:</strong> {$center_name}<br><br>
		    You will be notified with further instructions regarding your retest schedule and next steps shortly.<br><br>
		    If you have any questions in the meantime, please do not hesitate to contact us.<br><br>
		    Best regards,<br>
		    NDTSS Admin Team
		";

        $message = get_email_template($subject, $body);
        add_filter('wp_mail_content_type', fn() => 'text/html');
        wp_mail($candidate_email, $subject, $message);
        remove_filter('wp_mail_content_type', fn() => 'text/html');
    }

    // --- 2. Send mail to center admins, AQB admins & site admins ---
    $admin_emails = [];

    // Center Admins (multiple)
    $center_admin_ids = (array) get_post_meta($center_post->ID, '_center_admin_id', true);
    foreach ($center_admin_ids as $user_id) {
        $user = get_userdata($user_id);
        if ($user && is_email($user->user_email)) {
            $admin_emails[] = $user->user_email;
        }
    }

    // AQB Admins (optional)
    $aqb_admin_ids = (array) get_post_meta($center_post->ID, '_aqb_admin_id', true);
    foreach ($aqb_admin_ids as $user_id) {
        $user = get_userdata($user_id);
        if ($user && is_email($user->user_email) && !in_array($user->user_email, $admin_emails)) {
            $admin_emails[] = $user->user_email;
        }
    }

    // WP Admins
    $admins = get_users(['role' => 'administrator', 'fields' => ['user_email']]);
    foreach ($admins as $admin) {
        if (is_email($admin->user_email) && !in_array($admin->user_email, $admin_emails)) {
            $admin_emails[] = $admin->user_email;
        }
    }

    if (!empty($admin_emails)) {
       $subject = '📢 New Retest Exam Form Submission';
		$message = "
		    <p><strong>Retest Exam Form Submission Notification</strong></p>
		    <p>A candidate has submitted a <strong>retest examination form</strong>.</p>
		    <p><strong>Candidate Name:</strong> {$candidate_name}</p>
		    <p><strong>Order Number:</strong> {$order_number}</p>
		    <p><strong>Examination Center:</strong> {$center_name}</p>
		    <br>
		    <p>Please review the submission and proceed with the necessary actions.</p>
		    <hr>
		    <p><em>This is an automated notification from the NDTSS Exam Management System.</em></p>
		";

        add_filter('wp_mail_content_type', fn() => 'text/html');
        wp_mail($admin_emails, $subject, $message);
        remove_filter('wp_mail_content_type', fn() => 'text/html');
    }
}


function send_exam_submission_notification($entry_id, $order_number, $center_post) {
    $form_id = 15; // Change this if your form ID is different
    $entry = GFAPI::get_entry($entry_id);

    // Get center admin
    $center_admin_id = get_post_meta($center_post->ID, '_center_admin_id', true);
    $center_admin = get_userdata($center_admin_id);

    // Get admin users
    $admin_users = get_users([
    	'role'   => 'administrator',
    	'fields' => ['user_email'],
    ]);

    // Get candidate details
    $candidate_email = rgar($entry, '12'); // Replace with correct field ID for email
    $candidate_name  = rgar($entry, '1'); // Replace with correct field ID for name

    // Get center details
    $center_name = get_the_title($center_post->ID);

    // --- 1. Send mail to candidate ---
    if (is_email($candidate_email)) {
    	$candidate_subject = 'Exam Form Submission – Acknowledgement';
    	$candidate_body = '
    	Dear ' . esc_html($candidate_name) . ',<br><br>
    	Thank you for submitting your examination form. Your submission has been received and is being reviewed.<br><br>
            Order Number : ' . esc_html($order_number) . '<br>
            Examination Center : ' . esc_html($center_name) . '<br><br>
    	We will notify you once further actions are required. If you have any questions, feel free to reach out.<br><br>
    	Best regards,<br>
    	NDTSS Admin Team
    	';
    	$candidate_message = get_email_template($candidate_subject, $candidate_body);

    	add_filter('wp_mail_content_type', function() { return 'text/html'; });
    	wp_mail($candidate_email, $candidate_subject, $candidate_message);
    	remove_filter('wp_mail_content_type', function() { return 'text/html'; });
    }

    // --- 2. Send mail to center admin + all admins ---
    $admin_emails = [];

    if ($center_admin && is_email($center_admin->user_email)) {
    	$admin_emails[] = $center_admin->user_email;
    }

    foreach ($admin_users as $admin_user) {
    	if (is_email($admin_user->user_email) && !in_array($admin_user->user_email, $admin_emails)) {
    		$admin_emails[] = $admin_user->user_email;
    	}
    }

    if (!empty($admin_emails)) {
    	$admin_subject = '📢 New Exam Form Submission Received';
    	$admin_message  = '<p><strong>Exam Form Submission Notification</strong></p>';
    	$admin_message .= '<p>A new exam form has been submitted by <strong>' . esc_html($candidate_name) . '</strong>.</p>';
    	$admin_message .= '<p><strong>Order Number:</strong> ' . esc_html($order_number) . '</p>';
    	$admin_message .= '<p><strong>Examination Center:</strong> ' . esc_html($center_name) . '</p>';
    	$admin_message .= '<p>This is an automated notification from the exam management platform.</p>';

    	add_filter('wp_mail_content_type', function() { return 'text/html'; });
    	wp_mail($admin_emails, $admin_subject, $admin_message);
    	remove_filter('wp_mail_content_type', function() { return 'text/html'; });
    }
}

add_action('init', 'create_exam_center_cpt');
function create_exam_center_cpt() {
	$labels = array(
		'name' => 'Examination Centers',
		'singular_name' => 'Examination Center',
		'menu_name' => 'Examination Centers',
		'add_new' => 'Add New',
		'add_new_item' => 'Add New Examination Center',
		'edit_item' => 'Edit Examination Center',
		'new_item' => 'New Examination Center',
		'view_item' => 'View Examination Center',
		'all_items' => 'All Examination Centers',
		'search_items' => 'Search Examination Centers',
		'not_found' => 'No Examination Centers found',
	);

	$args = array(
		'labels' => $labels,
		'public' => true,
		'has_archive' => true,
		'supports' => array('title'),
		'show_in_menu' => true,
		'menu_position' => 5,
		'menu_icon' => 'dashicons-admin-site',
		'capability_type' => 'exam_center',
		'map_meta_cap' => true,
	);

	register_post_type('exam_center', $args);
}

// Add custom capabilities for administrator only
add_action('admin_init', 'restrict_exam_center_caps');
function restrict_exam_center_caps() {
	$role = get_role('administrator');
	if ($role) {
		$role->add_cap('edit_exam_center');
		$role->add_cap('edit_exam_centers');
		$role->add_cap('edit_others_exam_centers');
		$role->add_cap('publish_exam_centers');
		$role->add_cap('read_exam_center');
		$role->add_cap('delete_exam_center');
		$role->add_cap('delete_others_exam_centers');
		$role->add_cap('delete_published_exam_centers');
	}
}


add_action('admin_menu', 'remove_exam_center_add_new_menu', 999);
function remove_exam_center_add_new_menu() {
	if (!current_user_can('administrator')) {
		$screen = get_current_screen();
		if ($screen && $screen->post_type === 'exam_center') {
			remove_submenu_page('edit.php?post_type=exam_center', 'post-new.php?post_type=exam_center');
		}
	}
}

add_action('admin_init', 'block_exam_center_add_edit_for_non_admins');
function block_exam_center_add_edit_for_non_admins() {
	if (!is_admin() || current_user_can('administrator')) {
		return;
	}

	$screen = get_current_screen();
	if (!$screen) {
		return;
	}

	if (
		$screen->post_type === 'exam_center' &&
		in_array($screen->base, array('post', 'post-new'))
	) {
		wp_die(__('You are not allowed to add or edit Examination Centers.'));
	}
}

// Add custom submenu for submitted forms
add_action('admin_menu', 'add_students_center_admin_page');
function add_students_center_admin_page() {
	if (!current_user_can('custom_center') && !current_user_can('aqb_admin') && !current_user_can('administrator')) {
		return;
	}

	add_submenu_page(
		'edit.php?post_type=exam_center',
		'Submitted Forms',
		'Submitted Forms',
		'read',
		'submitted-forms',
		'display_submitted_forms_page'
	);

	add_submenu_page(
		'edit.php?post_type=exam_center',
		'Retest Forms',
		'Retest Forms',
		'read',
		'retest-forms',
		'display_retest_forms_page'
	);

	add_submenu_page(
        'edit.php?post_type=exam_center',
        'Renew/Recert By Exam',
        'Renew/Recert By Exam',
        'read',
        'form-31-entries',
        'display_form_31_entries_page'
    );
}

function custom_admin_notice_center_conflict() {
	if (isset($_GET['center_conflict']) && $_GET['center_conflict'] === '1') {
		echo '<div class="notice notice-error"><p><strong>Error:</strong> This center is already assigned to another user for this role.</p></div>';
	}
}
add_action('admin_notices', 'custom_admin_notice_center_conflict');


add_action('show_user_profile', 'custom_user_fields_by_role');
add_action('edit_user_profile', 'custom_user_fields_by_role');

function custom_user_fields_by_role($user) {

	if (!$user || !$user->ID) {
        return;
    }   
    $family_name    =  get_user_meta($user->ID, 'family_name', true); 
    $address        =  get_user_meta($user->ID, 'home_address', true);
    $city           =  get_user_meta($user->ID, 'home_city', true);
    $state          =  get_user_meta($user->ID, 'home_state', true);
    $postal_code    =  get_user_meta($user->ID, 'home_postal_code', true);
    $country        =  get_user_meta($user->ID, 'home_country', true);
    $work_address   =  get_user_meta($user->ID, 'work_address', true);
    $work_city      =  get_user_meta($user->ID, 'work_city', true); 
    $work_state     =  get_user_meta($user->ID, 'work_state', true); 
    $work_postal    =  get_user_meta($user->ID, 'work_postal_code', true);
    $work_country   =  get_user_meta($user->ID, 'work_country', true);
    $consent        =  get_user_meta($user->ID, 'correspondence_address', true);
    ?>
    <h2>Address Details</h2>
    <table class="form-table">
    	 <tr>
            <th><label for="family_name">Family Name</label></th>
            <td><input type="text" name="family_name" value="<?php echo esc_attr($family_name); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="home_address">Home Address</label></th>
            <td><input type="text" name="home_address" value="<?php echo esc_attr($address); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="home_city">Home City</label></th>
            <td><input type="text" name="home_city" value="<?php echo esc_attr($city); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="home_state">Home State</label></th>
            <td><input type="text" name="home_state" value="<?php echo esc_attr($state); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="home_postal_code">Home Postal Code</label></th>
            <td><input type="text" name="home_postal_code" value="<?php echo esc_attr($postal_code); ?>" class="regular-text" /></td>
        </tr>
         <tr>
            <th><label for="home_country">Home Country</label></th>
            <td><input type="text" name="home_country" value="<?php echo esc_attr($country); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="work_address">Work Address</label></th>
            <td><input type="text" name="work_address" value="<?php echo esc_attr($work_address); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="work_city">Work City</label></th>
            <td><input type="text" name="work_city" value="<?php echo esc_attr($work_city); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="work_state">Work State</label></th>
            <td><input type="text" name="work_state" value="<?php echo esc_attr($work_state); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="work_postal_code">Work Postal Code</label></th>
            <td><input type="text" name="work_postal_code" value="<?php echo esc_attr($work_postal); ?>" class="regular-text" /></td>
        </tr>
         <tr>
            <th><label for="work_pcountry">Work Country</label></th>
            <td><input type="text" name="work_country" value="<?php echo esc_attr($work_country); ?>" class="regular-text" /></td>
        </tr>
       <tr>
	    <th><label for="correspondence_address">Correspondence Address</label></th>
	    <td>
        <?php $selected = get_user_meta($user->ID, 'correspondence_address', true); ?>
        <label>
            <input type="radio" name="correspondence_address" value="Please use Personal address for correspondence" <?php checked($selected, 'Please use Personal address for correspondence'); ?> />
            Personal Address
        </label><br />
        <label>
            <input type="radio" name="correspondence_address" value="Please use Work address for correspondence" <?php checked($selected, 'Please use Work address for correspondence'); ?> />
            Work Address
        </label>
    </td>
	</tr>
    </table>
    <?php
	$user_roles = (array) $user->roles;
	$centers = wp_cache_get('exam_centers', 'custom_user_fields');
	if (false === $centers) {
		$centers = get_posts([
			'post_type'   => 'exam_center',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		]);
		wp_cache_set('exam_centers', $centers, 'custom_user_fields', 3600);
	}
	if (in_array('examiner', $user_roles, true)) {
		$specializations = get_user_meta($user->ID, '_examiner_specializations', true);
		if (!is_array($specializations)) {
			$specializations = [];
		}
		$methods = [
			'UT' => 'UT',
			'RT' => 'RT',
			'MT' => 'MT',
			'PT' => 'PT',
			'VT' => 'VT',
			'ET' => 'ET',
			'TT' => 'TT',
			'PAUT' => 'PAUT',
			'TOFD' => 'TOFD'
		];
		?>
		<h3>Examiner Method Specializations</h3>
		<table class="form-table">
			<?php foreach ($methods as $key => $label): ?>
				<tr>
					<th><label for="specialization_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
					<td>
						<input type="text"
						name="examiner_specializations[<?php echo esc_attr($key); ?>]"
						id="specialization_<?php echo esc_attr($key); ?>"
						value="<?php echo esc_attr($specializations[$key] ?? ''); ?>"
						class="regular-text"
						placeholder="e.g. Aerospace, Welding" />
						<p class="description">Specialization for <?php echo esc_html($label); ?></p>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}
}

add_action('personal_options_update', 'save_custom_user_fields_by_role');
add_action('edit_user_profile_update', 'save_custom_user_fields_by_role');

function save_custom_user_fields_by_role($user_id) {
	if (!current_user_can('edit_user', $user_id)) {
        return false;
    }
	update_user_meta($user_id, 'family_name', sanitize_text_field($_POST['family_name']));
    update_user_meta($user_id, 'home_address', sanitize_text_field($_POST['home_address']));
    update_user_meta($user_id, 'home_city', sanitize_text_field($_POST['home_city']));
    update_user_meta($user_id, 'home_state', sanitize_text_field($_POST['home_state']));
    update_user_meta($user_id, 'home_postal_code', sanitize_text_field($_POST['home_postal_code']));
    update_user_meta($user_id, 'home_country', sanitize_text_field($_POST['home_country']));

    update_user_meta($user_id, 'work_address', sanitize_text_field($_POST['work_address']));
    update_user_meta($user_id, 'work_city', sanitize_text_field($_POST['work_city']));
    update_user_meta($user_id, 'work_state', sanitize_text_field($_POST['work_state']));
    update_user_meta($user_id, 'work_postal_code', sanitize_text_field($_POST['work_postal_code']));
    update_user_meta($user_id, 'work_country', sanitize_text_field($_POST['work_country']));

    // Save checkbox consent — set to "Yes" if checked, "No" otherwise
    $consent = isset($_POST['correspondence_address']) && $_POST['correspondence_address'] === 'Yes' ? 'Yes' : 'No';
    update_user_meta($user_id, 'correspondence_address', $consent);
	if (!current_user_can('edit_user', $user_id)) return;

	$user = get_userdata($user_id);
	$roles = (array) $user->roles;

  

    // Save examiner specializations
	if (in_array('examiner', $roles, true) && isset($_POST['examiner_specializations'])) {
		$specializations = array_map('sanitize_text_field', $_POST['examiner_specializations']);
		update_user_meta($user_id, '_examiner_specializations', $specializations);
	}
}
add_filter('manage_users_columns', 'add_custom_user_columns');
function add_custom_user_columns($columns) {
	$new_columns = [];
	foreach ($columns as $key => $label) {
		$new_columns[$key] = $label;
		if ($key === 'name') {
			$new_columns['examiner_specializations'] = 'Specializations';
		}
	}
	return $new_columns;
}

add_filter('manage_users_custom_column', 'show_custom_user_column_content', 10, 3);
function show_custom_user_column_content($value, $column_name, $user_id) {
	if ($column_name === 'assigned_center') {
		$user = get_userdata($user_id);
		if (in_array('center_admin', (array) $user->roles, true)) {
			$center_id = get_user_meta($user_id, '_exam_center', true);
			if ($center_id) {
				$center_post = get_post($center_id);
				if ($center_post && $center_post->post_type === 'exam_center') {
					return esc_html($center_post->post_title);
				}
			}
			return '<em>No center assigned</em>';
		}
		return '<em>N/A</em>';
	}

	if ($column_name === 'examiner_specializations') {
		$user = get_userdata($user_id);
		if (in_array('examiner', (array) $user->roles, true)) {
			$specializations = get_user_meta($user_id, '_examiner_specializations', true);
			if (!empty($specializations) && is_array($specializations)) {
				$display = [];
				foreach ($specializations as $method => $value) {
					if (!empty($value)) {
						$display[] = esc_html($method . ': ' . $value);
					}
				}
				return $display ? implode('<br>', $display) : '<em>None</em>';
			}
			return '<em>None</em>';
		}
		return '<em>N/A</em>';
	}
	return $value;
}

add_filter('manage_exam_center_posts_columns', 'add_custom_admin_columns');
function add_custom_admin_columns($columns) {
	$new_columns = [];

	foreach ($columns as $key => $value) {
		if ($key === 'date') {
			$new_columns['center_admin'] = 'Center Admin(s)';
			$new_columns['aqb_admin'] = 'AQB Admin(s)';
		}
		$new_columns[$key] = $value;
	}

	return $new_columns;
}

add_action('manage_exam_center_posts_custom_column', 'show_custom_admin_column_content', 10, 2);
function show_custom_admin_column_content($column, $post_id) {
	if ($column === 'center_admin' || $column === 'aqb_admin') {
		$meta_key = ($column === 'center_admin') ? '_center_admin_id' : '_aqb_admin_id';
		$user_ids = (array) get_post_meta($post_id, $meta_key, true);

		if (!empty($user_ids)) {
			$names = [];

			foreach ($user_ids as $user_id) {
				$user = get_userdata($user_id);
				if ($user) {
					$names[] = esc_html($user->display_name);
				}
			}

			if (!empty($names)) {
				echo implode(', ', $names);
			} else {
				echo '<em>No valid users</em>';
			}
		} else {
			echo '<em>Not assigned</em>';
		}
	}
}

add_filter('manage_edit-exam_center_sortable_columns', 'make_custom_admin_columns_sortable');
function make_custom_admin_columns_sortable($columns) {
	$columns['center_admin'] = 'center_admin';
	$columns['aqb_admin'] = 'aqb_admin';
	return $columns;
}

add_action('pre_get_posts', 'sort_admin_columns_query');
function sort_admin_columns_query($query) {
	if (!is_admin() || !$query->is_main_query()) {
		return;
	}

	$orderby = $query->get('orderby');

	if ($orderby === 'center_admin') {
		$query->set('meta_key', '_center_admin_id');
		$query->set('orderby', 'meta_value_num');
	}

	if ($orderby === 'aqb_admin') {
		$query->set('meta_key', '_aqb_admin_id');
		$query->set('orderby', 'meta_value_num');
	}
}

add_action('pre_get_posts', 'center_admin_orderby_column');
function center_admin_orderby_column($query) {
	if (!is_admin()) return;
	$orderby = $query->get('orderby');
	if ($orderby == 'center_admin') {
		$query->set('meta_key', '_center_admin_id');
		$query->set('orderby', 'meta_value_num');
	}
}

add_action('gform_after_update_entry', 'save_examiner_assignment_from_entry_page', 10, 2);
function save_examiner_assignment_from_entry_page($entry, $form) {
	if (isset($_POST['save_examiner_assignment_final']) && check_admin_referer('save_examiner_assignment_modal', '_examiner_modal_nonce')) {
		$examiner_ids = array_map('intval', $_POST['assigned_examiner'] ?? []);
		$notes = sanitize_textarea_field($_POST['assignment_notes'] ?? '');
		gform_update_meta($entry['id'], '_assigned_examiner', $examiner_ids);
		gform_update_meta($entry['id'], '_assignment_notes', $notes);
	}
}

add_action('wp_ajax_save_exam_assignments', 'handle_exam_assignments_ajax');
function handle_exam_assignments_ajax() {
	if (empty($_POST['entry_id']) || !is_numeric($_POST['entry_id'])) {
		wp_send_json_error('Invalid entry ID.');
	}

	$entry_id = absint($_POST['entry_id']);
	$entry = GFAPI::get_entry($entry_id);
	if (is_wp_error($entry)) {
		wp_send_json_error('Entry not found.');
	}
	if ($entry['form_id'] == 30) {
		$exam_type       = 'Retest Exam';
		$field_789_value = rgar($entry, '12');
		$candidate_email = rgar($entry, '26');
		$candidate_name  = rgar($entry, '19');
		$center_name     = trim(rgar($entry, '9'));
	} else {
		$exam_type       = 'Initial Exam';
		$field_789_value = rgar($entry, '789');
		$candidate_email = rgar($entry, '12');
		$candidate_name  = rgar($entry, '840'); 
		$center_name     = trim(rgar($entry, '833')); 
	}	

	$assigned_examiners = isset($_POST['assigned_examiners']) && is_array($_POST['assigned_examiners']) ? array_map('intval', $_POST['assigned_examiners']) : [];
	$assigned_invigilators = isset($_POST['assigned_invigilators']) && is_array($_POST['assigned_invigilators']) ? array_map('intval', $_POST['assigned_invigilators']) : [];

	$method_slots = isset($_POST['method_slots']) ? $_POST['method_slots'] : [];
	$sanitized_method_slots = [];

	foreach ($method_slots as $method => $slots) {
		$method = sanitize_text_field($method);
		if (empty($slots['slot_1']['date']) || empty($slots['slot_1']['time'])) {
			wp_send_json_error("Slot 1 date and time are required for method: {$method}.");
		}

		foreach ($slots as $slot_key => $slot_values) {
			$date = sanitize_text_field($slot_values['date'] ?? '');
			$time = sanitize_text_field($slot_values['time'] ?? '');

			if (!empty($date) && !empty($time)) {
				$datetime_obj = DateTime::createFromFormat('Y-m-d H:i', "{$date} {$time}");
				if (!$datetime_obj) {
					wp_send_json_error("Invalid date/time for {$slot_key} in method: {$method}.");
				}

				$sanitized_method_slots[$method][$slot_key] = [
					'date' => $date,
					'time' => $time,
				];
			}
		}
	}

	gform_update_meta($entry_id, '_method_slots', $sanitized_method_slots);
	gform_update_meta($entry_id, '_assigned_examiners', $assigned_examiners);
	gform_update_meta($entry_id, '_assigned_invigilators', $assigned_invigilators);
	$current_time = current_time('mysql');
	$admin_id = get_current_user_id();
	$roles = [
		'examiner'     => $assigned_examiners,
		'invigilator'  => $assigned_invigilators
	];

	foreach ($roles as $role_key => $user_ids) {
		$meta_key = "_gform_assignment_log_{$role_key}";
		$existing_log = gform_get_meta($entry_id, $meta_key) ?: [];

		foreach ($user_ids as $user_id) {
			$already_logged = false;
			foreach ($existing_log as $log_item) {
				if ($log_item['user_id'] == $user_id && in_array($log_item['status'], ['assigned', 'accepted'])) {
					$already_logged = true;
					break;
				}
			}

			if (!$already_logged) {
				$existing_log[] = [
					'user_id'     => $user_id,
					'status'      => 'assigned',
					'timestamp'   => $current_time,
					'assigned_by' => $admin_id,
				];
			}
		}

		gform_update_meta($entry_id, $meta_key, $existing_log);
	}

    // Build method date table (used in email)
	$method_dates_html = '<table style="width:100%; border-collapse: collapse; margin: 15px 0; font-family: Arial, sans-serif; font-size: 14px;">';
	$method_dates_html .= '<thead><tr style="background-color: #f4f4f4;">
	<th style="border: 1px solid #ddd; padding: 10px;">Method</th>
	<th style="border: 1px solid #ddd; padding: 10px;">Slot</th>
	<th style="border: 1px solid #ddd; padding: 10px;">Date</th>
	<th style="border: 1px solid #ddd; padding: 10px;">Time</th>
	</tr></thead><tbody>';

	if (!empty($sanitized_method_slots)) {
		foreach ($sanitized_method_slots as $method => $slots) {
			$method_clean = esc_html(ucwords(str_replace('_', ' ', $method)));
			foreach ($slots as $slot => $slot_data) {
				$method_dates_html .= "<tr>
				<td style=\"border: 1px solid #ddd; padding: 10px;\">{$method_clean}</td>
				<td style=\"border: 1px solid #ddd; padding: 10px;\">".ucfirst(str_replace('_', ' ', $slot))."</td>
				<td style=\"border: 1px solid #ddd; padding: 10px;\">".esc_html(date('F j, Y', strtotime($slot_data['date'])))."</td>
				<td style=\"border: 1px solid #ddd; padding: 10px;\">".esc_html(date('g:i A', strtotime($slot_data['time'])))."</td>
				</tr>";
			}
		}
	} else {
		$method_dates_html .= '<tr><td colspan="4" style="border: 1px solid #ddd; padding: 10px; text-align: center; font-style: italic;">No methods scheduled</td></tr>';
	}
	$method_dates_html .= '</tbody></table>';

    // Send email notifications
	add_filter('wp_mail_content_type', function() { return 'text/html'; });

	foreach ($roles as $role => $users) {
		foreach ($users as $user_id) {
			$meta_key = "_assigned_entries_{$role}";
			$entries = get_user_meta($user_id, $meta_key, true);
			if (!is_array($entries)) $entries = [];
			if (!in_array($entry_id, $entries)) {
				$entries[] = $entry_id;
				update_user_meta($user_id, $meta_key, $entries);
			}

			$user_info = get_userdata($user_id);
			if ($user_info && $user_info->user_email) {
				$user_subject = "Assignment Notification: " . ucfirst($role) . " for {$exam_type} Order #{$field_789_value}";
				$user_body = "
				    <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; padding: 20px;'>
				        <h2>Assignment Notification</h2>
				        <p>Dear {$user_info->display_name},</p>
				        <p>You have been assigned as <strong>".ucfirst($role)."</strong> 
				        for <strong>{$exam_type}</strong> Order <strong>#{$field_789_value}</strong>.</p>
				        <h3>Scheduled Methods and Dates</h3>{$method_dates_html}
				        <p>Please log in to your dashboard to review and prepare.</p>
				        <p>Contact: <a href='mailto:".esc_attr(get_option('admin_email'))."'>".esc_html(get_option('admin_email'))."</a></p>
				        <p>Regards,<br>Administration Team</p>
				    </div>";
				    
				// $user_subject = "Assignment Notification: " . ucfirst($role) . " for Exam Order #" . $field_789_value;
				// $user_body = "<div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; padding: 20px;'>
				// <h2>Assignment Notification</h2>
				// <p>Dear {$user_info->display_name},</p>
				// <p>You have been assigned as <strong>".ucfirst($role)."</strong> for Exam Order <strong>#{$field_789_value}</strong>.</p>
				// <h3>Scheduled Methods and Dates</h3>{$method_dates_html}
				// <p>Please log in to your dashboard to review and prepare.</p>
				// <p>Contact: <a href='mailto:".esc_attr(get_option('admin_email'))."'>".esc_html(get_option('admin_email'))."</a></p>
				// <p>Regards,<br>Administration Team</p>
				// </div>";
				wp_mail($user_info->user_email, $user_subject, $user_body);
			}
		}
	}

    // ✅ Admin summary email
		$admin_subject = "Assignment Summary: {$exam_type} Order #{$field_789_value}";
		$admin_body = "
		    <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; padding: 20px;'>
		        <h2>Assignment Summary for {$exam_type} Order #{$field_789_value}</h2>
		        <p>The following users have been assigned:</p>";
	foreach ($roles as $role => $user_ids) {
		$label = ucfirst($role) . 's';
		$admin_body .= "<h3>{$label}</h3><ul>";
		foreach ($user_ids as $id) {
			$u = get_userdata($id);
			$admin_body .= "<li>".esc_html($u->display_name)." (ID: {$id})</li>";
		}
		$admin_body .= "</ul>";
	}

	$admin_body .= "<h3>Scheduled Methods and Dates</h3>{$method_dates_html}
	<p>Review these assignments in the system.</p>
	<p>Regards,<br>System Notification</p>
	</div>";

	

	
	$center_post     = get_page_by_title($center_name, OBJECT, 'exam_center');
	$center_admin_id = get_post_meta($center_post->ID, '_center_admin_id', true);
	$aqb_admin_id    = get_post_meta($center_post->ID, '_aqb_admin_id', true);

	$center_admin = get_userdata($center_admin_id);
	$aqb_admin    = get_userdata($aqb_admin_id);

	$admin_users = get_users([
		'role'   => 'administrator',
		'fields' => ['user_email'],
	]);

	$admin_emails = [];

	if ($center_admin && is_email($center_admin->user_email)) {
		$admin_emails[] = $center_admin->user_email;
	}
	if ($aqb_admin && is_email($aqb_admin->user_email)) {
		$admin_emails[] = $aqb_admin->user_email;
	}

	foreach ($admin_users as $admin_user) {
		if (is_email($admin_user->user_email)) {
			$admin_emails[] = $admin_user->user_email;
		}
	}

	$admin_emails = array_unique($admin_emails);


	add_filter('wp_mail_content_type', function () { return 'text/html'; });
	foreach ($admin_emails as $email) {

		wp_mail($email, $admin_subject, $admin_body);
	}
	remove_filter('wp_mail_content_type', '__return_true');

		$center_address = get_post_meta($center_post->ID, 'location', true); // Adjust meta key if different

		// ✅ Candidate confirmation email
		$candidate_subject = "{$exam_type} Assignment Details: Order #{$field_789_value}";
		$candidate_body = "
		    <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; padding: 20px;'>
		        <h2>{$exam_type} Assignment Confirmation</h2>
		        <p>Dear ".esc_html($candidate_name).",</p>
		        <p>Your <strong>{$exam_type}</strong> (Order #{$field_789_value}) has been scheduled. Please find the details below:</p>
		        <h3>Center Information</h3>
		        <p><strong>Name:</strong> ".esc_html($center_name)."<br>
		        <strong>Location:</strong> ".esc_html($center_address)."</p>
		        <h3>Scheduled Methods and Dates</h3>{$method_dates_html}
		        <p>Please arrive at the center on time and bring any required documents.</p>
		        <p>If you have questions, contact: <a href='mailto:".esc_attr(get_option('admin_email'))."'>".esc_html(get_option('admin_email'))."</a></p>
		        <p>Best wishes,<br>Administration Team</p>
		    </div>";


		add_filter('wp_mail_content_type', function () { return 'text/html'; });
		if (is_email($candidate_email)) {
			wp_mail($candidate_email, $candidate_subject, $candidate_body);
		}
		remove_filter('wp_mail_content_type', '__return_true');

		wp_send_json_success('Assignments saved and emails sent successfully.');
}

if (!function_exists('get_email_template')) {
	function get_email_template($subject, $body) {
		return "<!DOCTYPE html><html><head><title>{$subject}</title></head><body style=\"margin: 0; padding: 0; background-color: #f7f7f7;\"><div style=\"background-color: #ffffff; padding: 20px; margin: 20px auto; max-width: 600px; border: 1px solid #ddd;\">$body</div></body></html>";
	}
}

require_once get_stylesheet_directory() . '/examiner-module/examiner-dashboard.php';

// Handle renewed certificate downloads
add_action('init', 'handle_renewed_certificate_download');

function handle_renewed_certificate_download() {
    if (isset($_GET['download_renewed_cert']) && $_GET['download_renewed_cert'] === '1' && isset($_GET['cert_id'])) {
        global $wpdb;

        $cert_id = sanitize_text_field($_GET['cert_id']); // This is the certificate number, not database ID
        $user_id = get_current_user_id();

        // Find certificate by certificate number, not by ID
        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications WHERE certificate_number = %s AND user_id = %d",
            $cert_id, $user_id
        ));

        if (!$certificate) {
            wp_die('Certificate not found or access denied.');
        }

        // Check if certificate file exists
        if (empty($certificate->certificate_link)) {
            // Fallback: Try to find the certificate file in uploads/certificates directory
            $upload_dir = wp_upload_dir();
            $certificates_dir = $upload_dir['basedir'] . '/certificates';

            if (is_dir($certificates_dir)) {
                $pattern = "renewed_certificate_{$user_id}_{$cert_id}_*.pdf";
                $files = glob($certificates_dir . '/' . $pattern);

                if (!empty($files)) {
                    $latest_file = $files[0]; // Get the first (should be the only) match
                    $file_url = $upload_dir['baseurl'] . '/certificates/' . basename($latest_file);

                    // Update the database with the found certificate link
                    $wpdb->update(
                        $wpdb->prefix . 'sgndt_final_certifications',
                        array('certificate_link' => $file_url),
                        array('certificate_number' => $cert_id, 'user_id' => $user_id),
                        array('%s'),
                        array('%s', '%d')
                    );

                    $certificate->certificate_link = $file_url;
                } else {
                    // Debug: List all files in certificates directory
                    $all_files = scandir($certificates_dir);
                    $cert_files = array_filter($all_files, function($file) {
                        return strpos($file, 'certificate') !== false;
                    });

                    wp_die('Certificate file not available. Debug info: <br>' .
                           'Looking for pattern: ' . $pattern . '<br>' .
                           'Certificates directory: ' . $certificates_dir . '<br>' .
                           'Files found in certificates dir: ' . implode(', ', $all_files) . '<br>' .
                           'Certificate-related files: ' . implode(', ', $cert_files));
                }
            } else {
                wp_die('Certificate file not available. Certificates directory not found: ' . $certificates_dir);
            }
        }

        // Extract file path from URL
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $certificate->certificate_link);

        if (!file_exists($file_path)) {
            wp_die('Certificate file not found on server.');
        }

        // Force download
        $file_name = 'Certificate_' . $certificate->certificate_number . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        readfile($file_path);
        exit;
    }
}

function examiner_module_assets() {
	wp_enqueue_style('examiner-styles', get_stylesheet_directory_uri() . '/examiner-module/assets/styles.css');
	wp_enqueue_script('examiner-scripts', get_stylesheet_directory_uri() . '/examiner-module/assets/scripts.js', ['jquery'], null, true);
}
//add_action('admin_enqueue_scripts', 'examiner_module_assets');

add_action('wp_ajax_examiner_acceptance_status', 'examiner_entry_response');

function examiner_entry_response() {   
	$user_id = get_current_user_id();   
	$entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
	$status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
	$comment = isset($_POST['comments']) ? sanitize_textarea_field($_POST['comments']) : '';

	if (!$entry_id || !in_array($status, ['accepted', 'declined'], true)) {
		wp_send_json_error('Invalid request.');
	}

	$update_result = update_examiner_entry_status($user_id, $entry_id, $status, $comment);

	if (!$update_result) {
		wp_send_json_error('Failed to update status.');
	}

	if ($status === 'accepted') {
		$user = get_userdata($user_id);
		$entry = GFAPI::get_entry($entry_id);
		$form_id = (int) $entry['form_id'];
		switch ($form_id) {
			case 15:
				$order_number = rgar($entry, '789') ?: 'Unknown Order';
				break;
			case 30:
				$order_number = rgar($entry, '12') ?: 'Unknown Order';
				break;
			default:
				$order_number = 'Unknown Order';
		}
		$center_id = gform_get_meta($entry_id, '_linked_exam_center');
		$center_name = get_the_title($center_id);
		switch ($form_id) {
			case 15:
			$center_post_name     = trim(rgar($entry, '833'));
				break;
			case 30:
				$center_post_name     = trim(rgar($entry, '9'));
				break;
			default:
				$center_post_name = 'Unknown Order';
		}
		$center_post     = get_page_by_title($center_post_name, OBJECT, 'exam_center');
		$center_admin_id = get_post_meta($center_post->ID, '_center_admin_id', true);
		$aqb_admin_id    = get_post_meta($center_post->ID, '_aqb_admin_id', true);

		$subject = "Examiner Accepted: Exam Order #$order_number";
		$body = "<p><strong>{$user->display_name}</strong> has accepted the examiner assignment.</p>";
		$body .= "<p><strong>Order:</strong> #{$order_number}</p>";
		$body .= "<p><strong>Center:</strong> " . esc_html($center_name) . "</p>";
		$body .= "<p><strong>Comment:</strong> " . esc_html($comment) . "</p>";
		$message = get_email_template($subject, $body);
		$center_admin = get_userdata($center_admin_id);
		$aqb_admin    = get_userdata($aqb_admin_id);

		$admin_users = get_users([
			'role'   => 'administrator',
			'fields' => ['user_email'],
		]);

		$admin_emails = [];

		if ($center_admin && is_email($center_admin->user_email)) {
			$admin_emails[] = $center_admin->user_email;
		}
		if ($aqb_admin && is_email($aqb_admin->user_email)) {
			$admin_emails[] = $aqb_admin->user_email;
		}

		foreach ($admin_users as $admin_user) {
			if (is_email($admin_user->user_email) && !in_array($admin_user->user_email, $admin_emails)) {
				$admin_emails[] = $admin_user->user_email;
			}
		}
		add_filter('wp_mail_content_type', function () { return 'text/html'; });
		wp_mail($admin_emails, $subject, $message);
		remove_filter('wp_mail_content_type', function () { return 'text/html'; });

	}
	if ($status === 'declined') {
		$user = get_userdata($user_id);
		$entry = GFAPI::get_entry($entry_id);
		$form_id = (int) $entry['form_id'];
		switch ($form_id) {
			case 15:
				$order_number = rgar($entry, '789') ?: 'Unknown Order';
				$center_post_name     = trim(rgar($entry, '833'));
				break;
			case 30:
				$order_number = rgar($entry, '12') ?: 'Unknown Order';
				$center_post_name     = trim(rgar($entry, '9'));
				break;
			default:
				$order_number = 'Unknown Order';
				$center_post_name = 'Unknown Order';
		}
		$center_id = gform_get_meta($entry_id, '_linked_exam_center');
		$center_name = get_the_title($center_id);
		$center_post     = get_page_by_title($center_post_name, OBJECT, 'exam_center');
		$center_admin_id = get_post_meta($center_post->ID, '_center_admin_id', true);
		$aqb_admin_id    = get_post_meta($center_post->ID, '_aqb_admin_id', true);

		$subject = "Examiner Declined: Exam Order #$order_number";
		$body = "<p><strong>{$user->display_name}</strong> has declined the examiner assignment.</p>";
		$body .= "<p><strong>Order:</strong> #{$order_number}</p>";
		$body .= "<p><strong>Center:</strong> " . esc_html($center_name) . "</p>";
		$body .= "<p><strong>Comment:</strong> " . esc_html($comment) . "</p>";
		$message = get_email_template($subject, $body);
		$center_admin = get_userdata($center_admin_id);
		$aqb_admin    = get_userdata($aqb_admin_id);

		$admin_users = get_users([
			'role'   => 'administrator',
			'fields' => ['user_email'],
		]);

		$admin_emails = [];

		if ($center_admin && is_email($center_admin->user_email)) {
			$admin_emails[] = $center_admin->user_email;
		}
		if ($aqb_admin && is_email($aqb_admin->user_email)) {
			$admin_emails[] = $aqb_admin->user_email;
		}

		foreach ($admin_users as $admin_user) {
			if (is_email($admin_user->user_email) && !in_array($admin_user->user_email, $admin_emails)) {
				$admin_emails[] = $admin_user->user_email;
			}
		}
		add_filter('wp_mail_content_type', function () { return 'text/html'; });
		wp_mail($admin_emails, $subject, $message);
		remove_filter('wp_mail_content_type', function () { return 'text/html'; });
	}
	wp_send_json_success(['message' => 'Status updated and emails sent.']);
}

function update_examiner_entry_status($user_id, $entry_id, $status, $comment = '') {
	if (!in_array($status, ['accepted', 'declined'], true)) {
		return false;
	}
	$user_meta_key = '_assigned_entries_examiner_status';
	$user_statuses = get_user_meta($user_id, $user_meta_key, true);
	if (!is_array($user_statuses)) {
		$user_statuses = [];
	}
	$entry_statuses = [];
	$user_statuses[$entry_id] = $status;
	update_user_meta($user_id, $user_meta_key, $user_statuses);
	$entry_meta_key = '_examiner_status_summary';
	$entry_statuses = gform_get_meta($entry_id, $entry_meta_key);    
	$entry_statuses[$user_id] = $status; 
	if ($status === 'declined') {
		$assigned_examiner = gform_get_meta($entry_id, '_assigned_examiners');

		if (is_array($assigned_examiner)) {
			$updated_examiners = array_values(array_filter($assigned_examiner, function($val) use ($user_id) {
				return (int) $val !== (int) $user_id;
			}));
			gform_update_meta($entry_id, '_assigned_examiners', array_values($updated_examiners));
		}
	} 


	gform_update_meta($entry_id, $entry_meta_key, $entry_statuses);
	if (!empty($comment)) {
		$comment_meta_key = '_examiner_comments';
		$entry_comments = gform_get_meta($entry_id, $comment_meta_key);
		if (!is_array($entry_comments)) {
			$entry_comments = [];
		}
		$entry_comments[$user_id] = $comment;
		gform_update_meta($entry_id, $comment_meta_key, $entry_comments);
	}
	return true;
}

add_filter('gform_field_value_examno', function() {
	return isset($_GET['examno']) ? sanitize_text_field($_GET['examno']) : '';
});

add_action('gform_after_submission_25', 'save_examiner_marks_entry_id', 10, 2);
add_action('gform_after_update_entry', 'save_examiner_marks_entry_id_update', 10, 3);

/**
 * Runs on initial submission
 */
function save_examiner_marks_entry_id($entry, $form) {
    $exam_form_id = rgar($entry, '29');
    $method       = sanitize_text_field(rgar($entry, '28'));
    if (!$exam_form_id || !$method) return;

    $meta_key = '_examiner_marks_entry_id_' . sanitize_title($method);
    gform_update_meta($exam_form_id, $meta_key, $entry['id']);

    $exam_form_data = GFAPI::get_entry($exam_form_id);
    if (is_wp_error($exam_form_data)) return;

    // Get the form ID to determine which fields to use
    $exam_form_form_id = rgar($exam_form_data, 'form_id');
	
    
    // Use different field IDs based on form ID
    if ($exam_form_form_id == 31) {
        // Form 31 (Renewal/Recertification by Exam)
        $order_number  = rgar($exam_form_data, '12');
        $center_name   = rgar($exam_form_data, '9');
    } else {
        // Form 15 (Initial Exam) - default
        $order_number  = rgar($exam_form_data, '789');
        $center_name   = rgar($exam_form_data, '833');
    }

    $center_post = get_page_by_title($center_name, OBJECT, 'exam_center');
    if ($center_post) {
        send_marks_submission_notification($exam_form_id, $method, $order_number, $center_post);
    }
}

/**
 * Runs on update
 */
function save_examiner_marks_entry_id_update($form, $entry_id, $entry) {
    if ((int) $form['id'] !== 25) {
        return; // only run for form 25
    }

    $exam_form_id = rgar($entry, '29');
    $method       = sanitize_text_field(rgar($entry, '28'));
    if (!$exam_form_id || !$method) return;

    $meta_key = '_examiner_marks_entry_id_' . sanitize_title($method);
    gform_update_meta($exam_form_id, $meta_key, $entry['id']);

    $exam_form_data = GFAPI::get_entry($exam_form_id);
    if (is_wp_error($exam_form_data)) return;

    // Get the form ID to determine which fields to use
    $exam_form_form_id = rgar($exam_form_data, 'form_id');
	
    
    // Use different field IDs based on form ID
    if ($exam_form_form_id == 31) {
        // Form 31 (Renewal/Recertification by Exam)
        $order_number  = rgar($exam_form_data, '12');
        $center_name   = rgar($exam_form_data, '9');
    } else {
        // Form 15 (Initial Exam) - default
        $order_number  = rgar($exam_form_data, '789');
        $center_name   = rgar($exam_form_data, '833');
    }

    $center_post = get_page_by_title($center_name, OBJECT, 'exam_center');
    if ($center_post) {
        send_marks_submission_notification($exam_form_id, $method, $order_number, $center_post);
    }
}


function send_marks_submission_notification($entry_id, $method, $order_number, $center_post) {
	$center_admin_ids = (array) get_post_meta($center_post->ID, '_center_admin_id', true);
	$aqb_admin_ids    = (array) get_post_meta($center_post->ID, '_aqb_admin_id', true);

	$emails = [];

	// Center admins
	foreach ($center_admin_ids as $center_id) {
	    $center_user = get_userdata((int) $center_id);
	    if ($center_user && is_email($center_user->user_email)) {
	        $emails[] = $center_user->user_email;
	    }
	}

	// AQB admins
	foreach ($aqb_admin_ids as $aqb_id) {
	    $aqb_user = get_userdata((int) $aqb_id);
	    if ($aqb_user && is_email($aqb_user->user_email)) {
	        $emails[] = $aqb_user->user_email;
	    }
	}

	// Global WordPress administrators
	$admin_users = get_users([
	    'role'   => 'administrator',
	    'fields' => ['user_email'],
	]);
	foreach ($admin_users as $admin_user) {
	    if (is_email($admin_user->user_email) && !in_array($admin_user->user_email, $emails)) {
	        $emails[] = $admin_user->user_email;
	    }
	}

	// Optional: remove duplicates just in case
	$emails = array_unique($emails);


	$marks_entry = GFAPI::get_entry($entry_id);
	$candidate_user = get_user_by('id', $marks_entry['created_by']);
	$submitted_by_user = wp_get_current_user();
	$method = $method;
	$email_title = 'Marks Submission Notification';
	$email_body  = '
	<p>Marks have been submitted by the examiner.</p>
	<p><strong>Candidate:</strong> ' . esc_html($candidate_user->display_name) . ' (' . esc_html($candidate_user->user_email) . ')</p>
	<p><strong>Marks Submitted By:</strong> ' . esc_html($submitted_by_user->display_name) . ' (' . esc_html($submitted_by_user->user_email) . ')</p>
	<p><strong>Method:</strong> ' . esc_html($method) . '</p>
	<p><strong>Exam Order Number:</strong> ' . esc_html($order_number) . '</p>
	<p><strong>Exam Center:</strong> ' . esc_html($center_post->post_title) . '</p>
	<br>
	<p><a class="button" href="' . esc_url(admin_url("admin.php?page=gf_entries&view=entry&id=15&lid={$entry_id}")) . '">View Marks details</a></p>';
	if (!function_exists('get_email_template')) {
		function get_email_template($title, $content) {
			return '<html><body style="font-family:Arial,sans-serif;"><h2>' . esc_html($title) . '</h2><div>' . wp_kses_post($content) . '</div></body></html>';
		}
	}
	$message = get_email_template($email_title, $email_body);
	add_filter('wp_mail_content_type', fn() => 'text/html');
	wp_mail($emails, $email_title, $message);
	remove_filter('wp_mail_content_type', fn() => 'text/html');
}

function render_examiner_marks_page() {
	if (!isset($_GET['entry_id'])) {
		echo '<p>Error: No entry ID provided.</p>';
		return;
	}
	$entry_id = intval($_GET['entry_id']);
	$method = $_GET['method'];

	// Get the entry data to access field 833
	$entry_data = GFAPI::get_entry($entry_id);
	

	// // Check if entry exists and has the required field
	// if (!$entry_data || !isset($entry_data['833']) || !isset($entry_data['9'])) {
	// 	echo '<p>Error: Entry not found or missing required data.</p>';
	// 	return;
	// }

	$result_ref_no = 'NDT1902-' . str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
	?>
	<div id="add-marks-form">
		<div class="marks-entry-wrapper">
			<h3>Enter Marks – <?= esc_html($method); ?></h3>
			<a href='<?php echo admin_url("admin.php?page=examiner-dashboard");?>'>Back to list</a>
			<?php
			$prefix = $entry_data['833'] ?: $entry_data['9'];
			$fixed_code = '1902';
			$random_number = '21' . str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
			$result_ref_no = $prefix . $fixed_code . '-' . $random_number;
			$method_param = urlencode($method);
			echo do_shortcode('[gravityform id="25" title="false" description="false" ajax="true" field_values="result_ref_no=' . $result_ref_no . '&method=' . $method_param . '&exam_form_id=' . $entry_id . '"]'); ?>
		</div>
	</div>
	<?php
}

function add_examiner_marks_page() {
	add_submenu_page(
        null, // Hidden from menu
        'Examiner Marks Entry', 
        'Examiner Marks Entry', 
        'read', // 🔹 Allows all logged-in users
        'examiner-marks-entry', 
        'render_examiner_marks_page'
    );
}
add_action('admin_menu', 'add_examiner_marks_page');

add_action('admin_menu', 'examiner_custom_dashboard_page');
function examiner_custom_dashboard_page() {
	// add_menu_page(
	// 	'Exam Assignment Dashboard',
	// 	'Exam Assignment',
	// 	'manage_options',
	// 	'examiner-assignment-dashboard',
	// 	'render_examiner_assignment_dashboard',
	// 	'dashicons-welcome-learn-more',
	// 	6
	// );
	add_submenu_page(
            'edit.php?post_type=exam_center',   // Parent menu
            'Add Marks',                        // Page title
            'Add Marks',                        // Menu title
            'examiner',                         // ✅ Custom capability
            'examiner-dashboard',               // Menu slug
            'examiner_dashboard'       // Callback function
        );

}

function render_examiner_assignment_dashboard() {
    //if ((int) $form['id'] !== 15) return;
	$entry_id = 296;
	$entry = GFAPI::get_entry( $entry_id );
	$entry_status = gform_get_meta($entry_id, 'approval_status');
	if (strtolower($entry_status) !== 'approved') {
		echo '<div class="postbox"><h3 class="hndle"><span>Assign Examiner</span></h3><div class="inside"><p>This entry must be approved before assigning an examiner.</p></div></div>';
		return;
	}
	$center_id = gform_get_meta($entry_id, '_linked_exam_center');    
	if (!$center_id) return;
	$assigned_examiners = (array) gform_get_meta($entry_id, '_assigned_examiners');
	$assigned_invigilators = (array) gform_get_meta($entry_id, '_assigned_invigilators');
	$examiner_users = get_post_meta($center_id, 'assign_examiners', true);
	$invigilator_users = get_post_meta($center_id, 'assign_invigilator', true);
	if (empty($examiner_users) && empty($invigilator_users)) {
		echo '<div class="postbox"><h3 class="hndle"><span>Assign Examiner/Invigilator</span></h3><div class="inside"><p>No examiners or invigilators found for this center.</p></div></div>';
		return;
	}
	$invigilator_data = gform_get_meta($entry_id, '_invigilator_update_record');

	$field_188_values = [];
	$desired_keys = ['188.1', '188.2', '188.3', '188.4', '188.5', '188.6', '188.7', '188.8', '188.9'];
	foreach ($desired_keys as $key) {
		if (isset($entry[$key]) && !empty($entry[$key])) {
			$field_188_values[] = $entry[$key];
		}
	}

	$exam_order_no = $entry['789'];
	$form_id = 25;
		$field_id = '20'; // Order Number field in Form 24

		$search_criteria = [
			'field_filters' => [
				[
					'key'   => $field_id,
					'value' => $exam_order_no,
				],
			],
		];

		$result_entries = GFAPI::get_entries($form_id, $search_criteria);
		$marks_form = GFAPI::get_form($form_id);

		// Group entries by method
		$entries_by_method = [];
		foreach ($result_entries as $entry_row) {
		    $method_name = $entry_row['28'] ?? ''; // Assuming field 28 stores method name
		    if (!empty($method_name)) {
		    	$entries_by_method[$method_name] = $entry_row;
		    }
		}

		// Check if marks exist for disabling the button
		$disable_assignment = !empty($entries_by_method);


		?>
		<div class="bg-white shadow rounded-md p-6">
			<h2 class="text-lg font-semibold text-gray-800 mb-4">Assign Examiner & Invigilator</h2>
			<?php if ($disable_assignment): ?>
				<div class="bg-green-100 text-green-700 p-3 rounded mb-4 text-sm font-medium">
					✅ Marks have already been entered. No further assignment needed.
				</div>
			<?php endif; ?>
			<form id="assign-users-form" class="space-y-6">
				<input type="hidden" name="entry_id" value="<?= esc_attr($entry_id); ?>">
				<?php wp_nonce_field('assign_users_nonce', 'assign_users_nonce_field'); ?>

				<div>
					<h3 class="font-semibold text-sm mb-2">Select Examiners:</h3>
					<div class="grid grid-cols-2 gap-2">
						<?php foreach ($examiner_users as $uid):
							$user = get_userdata($uid);
							if (!$user) continue;
							?>
							<label class="flex items-center gap-2 text-sm">
								<input type="checkbox" name="assigned_examiners[]" value="<?= esc_attr($uid); ?>"
								<?= in_array($uid, $assigned_examiners) ? 'checked' : ''; ?>
								class="rounded border-gray-300 text-blue-600 focus:ring focus:ring-blue-200">
								<?= esc_html($user->display_name); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div>
					<h3 class="font-semibold text-sm mb-2">Select Invigilators:</h3>

				</div>
				<?= display_invigilator_acceptance_summary($entry_id);?>

				<div>
					<h3 class="font-semibold text-sm mt-4 mb-2">Schedule Methods</h3>
					<?php
					$tomorrow = date('Y-m-d', strtotime('+1 day'));
					foreach ($field_188_values as $method):
						?>
						<div class="mb-3 border rounded p-3 bg-gray-50">

							<label class="block font-medium text-gray-600 mb-1"><?= esc_html($method); ?> Slots:</label>
							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block text-xs text-gray-500">Slot 1 Date*</label>
									<input type="date" name="method_slots[<?= esc_attr($method); ?>][slot_1_date]"
									min="<?= esc_attr($tomorrow); ?>"
									value="<?= esc_attr($method_dates[$method]['slot_1']['date'] ?? ''); ?>"
									class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
								</div>
								<div>
									<label class="block text-xs text-gray-500">Slot 1 Time*</label>
									<input type="time" name="method_slots[<?= esc_attr($method); ?>][slot_1_time]"
									value="<?= esc_attr($method_dates[$method]['slot_1']['time'] ?? ''); ?>"
									class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
								</div>
								<div>
									<label class="block text-xs text-gray-500">Slot 2 Date</label>
									<input type="date" name="method_slots[<?= esc_attr($method); ?>][slot_2_date]"
									value="<?= esc_attr($method_dates[$method]['slot_2']['date'] ?? ''); ?>"
									min="<?= esc_attr($tomorrow); ?>"
									class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
								</div>
								<div>
									<label class="block text-xs text-gray-500">Slot 2 Time</label>
									<input type="time" name="method_slots[<?= esc_attr($method); ?>][slot_2_time]"
									value="<?= esc_attr($method_dates[$method]['slot_2']['time'] ?? ''); ?>"
									class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="pt-4">
					<button type="submit"
					class="bg-blue-600 text-white font-semibold px-5 py-2 rounded hover:bg-blue-700 transition"
					<?= $disable_assignment ? 'disabled class="opacity-50 cursor-not-allowed"' : ''; ?>>
					Save Assignments
				</button>
				<span id="assign-loader" class="ml-2 text-gray-500 hidden">Saving...</span>
				<div id="assign-response" class="mt-3 text-sm text-red-600"></div>
			</div>
		</form>
	</div>
	<div class="bg-white shadow rounded-md p-6 mb-8">
		<h2 class="text-lg font-semibold text-gray-800 mb-4">Candidate Marks Summary</h2>
		<div class="space-y-4">
			<?php foreach ($field_188_values as $method): ?>
				<div class="border p-4 rounded-md bg-gray-50">
					<h3 class="text-md font-semibold text-blue-600"><?= esc_html($method); ?></h3>
					<?php if (isset($entries_by_method[$method])) {
						$method_key = sanitize_title($method);
						$marks_entry_id = gform_get_meta($entry_id, '_examiner_marks_entry_id_' . $method_key);
						if (!empty($marks_entry_id) && is_numeric($marks_entry_id)) {
							$marks_entry_data = GFAPI::get_entry($marks_entry_id);

							if (!is_wp_error($marks_entry_data)) {
								$marks_form = GFAPI::get_form($marks_entry_data['form_id']);
								$marks_combined = [];
								$other_fields = [];

								foreach ($marks_form['fields'] as $field) {
									$field_id = $field->id;
									$label = trim($field->label);
									$value = $marks_entry_data[$field_id] ?? '';

									if (empty($value) || in_array($field->type, ['html', 'hidden']) || in_array($field_id, [18])) {
										continue;
									}

									if (stripos($label, 'Marks Obtained') !== false) {
										$base = trim(str_ireplace('Marks Obtained', '', $label));
										$marks_combined[$base]['obtained'] = $value;
										$marks_combined[$base]['label'] = $base;
									} elseif (stripos($label, 'Total Marks') !== false) {
										$base = trim(str_ireplace('Total Marks', '', $label));
										$marks_combined[$base]['total'] = $value;
										$marks_combined[$base]['label'] = $base;
									} else {
										$other_fields[$label] = $value;
									}
								}

								echo '<table class="wp-list-table widefat striped"><tbody>';
								foreach ($other_fields as $label => $value) {
									echo '<tr><td><strong>' . esc_html($label) . ':</strong></td><td>' . esc_html($value) . '</td></tr>';
								}
								foreach ($marks_combined as $data) {
									echo '<tr><td><strong>' . esc_html($data['label']) . ':</strong></td>';
									echo '<td>' . esc_html($data['obtained'] ?? '-') . '/' . esc_html($data['total'] ?? '-') . '</td></tr>';
								}
								echo '</tbody></table>';

								$edit_link = esc_url(admin_url("admin.php?page=gf_entries&view=entry&id=24&lid={$marks_entry_id}"));
								echo '<a href="' . $edit_link . '" class="button button-secondary" target="_blank">View/Edit Marks</a>';
							} else {
								echo '<p><em>Could not load marks entry.</em></p>';
							}
						} else {
							$examiner_marks_url = admin_url("admin.php?page=examiner-marks-entry&entry_id=" . intval($entry_id) . "&method=" . urlencode($method) . "&examno=" . esc_attr($entry_data['789']));
							echo '<a href="' . esc_url($examiner_marks_url) . '" class="button button-primary add-marks-button">Add Marks</a>';
						}
					} else { ?>
						<p style="color: red;"><em>Marks not added by examiner yet for this method.</em></p>
					<?php } ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>


	<?php
}


function display_invigilator_acceptance_summary($entry_id) {
	$status_data = gform_get_meta($entry_id, '_invigilator_status_summary');
	$comments_data = gform_get_meta($entry_id, '_invigilator_comments');

	if (empty($status_data)) {
		echo '<p class="text-gray-500 italic">No invigilator responses yet.</p>';
		return;
	}

	echo '<div class="bg-white border border-gray-200 shadow-sm rounded-lg p-4">';
	echo '<h3 class="text-lg font-semibold text-gray-800 mb-3">Invigilator Responses</h3>';
	echo '<ul class="divide-y divide-gray-200">';

	foreach ($status_data as $user_id => $status) {
		$user = get_userdata($user_id);
		$name = $user ? $user->display_name : 'Unknown User';
		$comment = $comments_data[$user_id] ?? '';

		$badge_class = $status === 'accepted' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';

		echo '<li class="py-2 flex justify-between items-start">';
		echo '<div>';
		echo '<p class="font-medium text-gray-700">' . esc_html($name) . '</p>';
		echo '<p class="text-sm text-gray-500">Comment: ' . esc_html($comment) . '</p>';
		echo '</div>';
		echo '<span class="inline-block px-3 py-1 rounded-full text-sm font-semibold ' . $badge_class . '">' . ucfirst($status) . '</span>';
		echo '</li>';
	}

    echo '</ul>';
    echo '</div>';
}

add_action('wp_ajax_generate_notification', function () {
	echo "<pre>"; 
	print_r($_POST);
    $response = [];    
    if (
        isset($_POST['generate_certificate']) &&
        $_POST['generate_certificate'] == '1' &&
        isset($_POST['exam_entry_id'], $_POST['marks_entry_id'], $_POST['method'])
    ) {
        $exam_entry_id = absint($_POST['exam_entry_id']);
        $marks_entry_id = absint($_POST['marks_entry_id']);
        $method = sanitize_text_field($_POST['method']);
        if (empty($exam_entry_id) || empty($marks_entry_id) || empty($method)) {
            wp_send_json_error('Invalid input data.');
            return;
        }
        include_once get_stylesheet_directory() . '/includes/pdf-cert-generator.php';
        try {
            $result = generate_exam_certificate_pdf($exam_entry_id, $marks_entry_id, $method);
            if ($result) {
                $response['message'] = 'Result notification generated.';
            } else {
                wp_send_json_error('Failed to generate notification.');
                return;
            }
        } catch (Exception $e) {
            wp_send_json_error('Error generating notification: ' . $e->getMessage());
            return;
        }
    }

    
    if (
        isset($_POST['generate_final_certificate']) &&
        $_POST['generate_final_certificate'] == '1' &&
        isset($_POST['exam_entry_id'], $_POST['marks_entry_id'], $_POST['method'])
    ) {
        
        $exam_entry_id = absint($_POST['exam_entry_id']);
        $marks_entry_id = absint($_POST['marks_entry_id']);
        $method = sanitize_text_field($_POST['method']);

        // Validate inputs
        if (empty($exam_entry_id) || empty($marks_entry_id) || empty($method)) {
            wp_send_json_error('Invalid input data.');
            return;
        }

        // Check manager approval
        $manager_approval_status = gform_get_meta($marks_entry_id, '_manager_approval_status_' . sanitize_title($method));
      

        // Include PDF generator and generate final certificate
        include_once get_stylesheet_directory() . '/includes/pdf-final-cert-generator.php';
        try {
            $result = generate_final_certificate_pdf($exam_entry_id, $marks_entry_id, $method);
            if ($result) {
                $response['message'] = 'Final certificate generated.';
            } else {
                wp_send_json_error('Failed to generate final certificate.');
                return;
            }
        } catch (Exception $e) {
            wp_send_json_error('Error generating final certificate: ' . $e->getMessage());
            return;
        }
    }

if (
    isset($_POST['approve_certificate_step']) &&
    $_POST['approve_certificate_step'] == '1' &&
    isset($_POST['marks_entry_id'], $_POST['method'])
) {       
    // $marks_entry_id = absint($_POST['marks_entry_id']);
    // $method         = sanitize_text_field($_POST['method']);
	    $exam_entry_id = absint($_POST['exam_entry_id']);
        $marks_entry_id = absint($_POST['marks_entry_id']);
        $method = sanitize_text_field($_POST['method']);


    if (empty($marks_entry_id) || empty($method)) {
        wp_send_json_error('Invalid input data.');
        return;
    }

    // Save approval status
    gform_update_meta($marks_entry_id, '_manager_approval_status_' . sanitize_title($method), 'approved');

    // Fetch entry details for better email content
    $entry = GFAPI::get_entry($exam_entry_id);
   

    
    if (!is_wp_error($entry)) {
        $user_id = $entry['created_by'] ?? get_current_user_id();
      	$user_id = $entry['created_by'];
    	$user_data = get_userdata($user_id);
    	$candidate_name = $user_data ? $user_data->display_name : 'N/A';
        $candidate_email =rgar($entry, '12'); // change '2' to actual email field ID
        $order_number    = rgar($entry, '789'); // change to correct field if available
    } else {
        $candidate_name  = 'Unknown Candidate';
        $candidate_email = 'N/A';
        $order_number    = 'N/A';
    }

    // Build email
   $super_admin_email = get_option('admin_email'); // WP Super Admin / Site Admin email
$subject = 'Final Certificate Approval Notification';

$body = "
    Dear Super Admin,<br><br>
    The following candidate has been approved for final certification:<br><br>
    <strong>Candidate Name:</strong> {$candidate_name}<br>
    <strong>Candidate Email:</strong> {$candidate_email}<br>
    <strong>Order Number:</strong> {$order_number}<br>
    <strong>Examination Center:</strong> {$center_name}<br>
    <strong>Method:</strong> {$method}<br><br>
    This result has been approved from the manager's end.<br><br>
    Best regards,<br>
    NDTSS Certification System
";

// If you have a wrapper template function
$message = get_email_template($subject, $body);

// Define named function for mail content type
function ndtss_set_html_mail_content_type() {
    return 'text/html';
}

add_filter('wp_mail_content_type', 'ndtss_set_html_mail_content_type');
wp_mail($super_admin_email, $subject, $message);
remove_filter('wp_mail_content_type', 'ndtss_set_html_mail_content_type');


    $response['message'] = 'Result approved and super admin notified.';
}

    if (!empty($response)) {
        wp_send_json_success($response);
    } else {
        wp_send_json_error('Invalid request: Missing required parameters.');
    }
});


add_filter('gform_pre_submission', 'format_all_date_fields_to_ddmmyyyy');
function format_all_date_fields_to_ddmmyyyy($form) {
	foreach ($form['fields'] as $field) {
        // Only process date fields
		if ($field->type === 'date') {
			$field_id = $field->id;
			$input_name = 'input_' . $field_id;

			if (!empty($_POST[$input_name])) {
                // Try to parse Y-m-d, fallback if already in correct format
				$date_obj = DateTime::createFromFormat('Y-m-d', $_POST[$input_name]);
				if ($date_obj) {
					$_POST[$input_name] = $date_obj->format('d/m/Y');
				}
			}
		}
	}

	return $form;
}


add_action('gform_after_submission_30', 'update_retest_status_after_submission', 10, 2);
function update_retest_status_after_submission($entry, $form) {
    global $wpdb;
    $table_certifications = $wpdb->prefix . 'sgndt_certifications';
    $table_subject_marks = $wpdb->prefix . 'sgndt_subject_marks';

    $cert_number = rgar($entry, '13'); 
    $retest_part = rgar($entry, '11'); 

    if (!$cert_number || !$retest_part) {
        error_log('Missing data: cert_number or retest_part is empty or invalid. Entry: ' . json_encode($entry, true));
        return;
    }

    $certification_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT certification_id FROM $table_certifications WHERE cert_number = %s",
            $cert_number
        )
    );

    if (!$certification_id) {
        error_log('No certification_id found for cert_number: ' . $cert_number . '. Table check: ' . $wpdb->get_var("SHOW TABLES LIKE '$table_certifications'"));
        return;
    }

    $existing_record = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_subject_marks WHERE certification_id = %d AND subject_name = %s",
            $certification_id,
            $retest_part
        )
    );

    if (!$existing_record) {
        error_log('No existing record found for certification_id: ' . $certification_id . ', subject_name: ' . $retest_part);
        return;
    }

  
    $attempt_number = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(MAX(attempt_number), 0) + 1 FROM $table_subject_marks WHERE certification_id = %d AND subject_name = %s",
            $certification_id,
            $retest_part
        )
    );

    $updated = $wpdb->update(
        $table_subject_marks,
        [
            'status' => 'pending',
           // 'attempt_number' => $attempt_number
        ],
        [
            'certification_id' => $certification_id,
            'subject_name' => $retest_part
        ],
        ['%s', '%d'],
        ['%d', '%s']
    );

    if ($updated === false) {
        error_log('Error updating subject marks: ' . $wpdb->last_error . '. Affected rows: ' . $wpdb->rows_affected);
    } else {
        error_log('Successfully updated status to pending for cert_number: ' . $cert_number . ', subject: ' . $retest_part . ', attempt: ' . $attempt_number . ', rows affected: ' . $updated);
    }
}
function get_user_retest_entries_by_cert_and_method($cert_number, $method) {
    if (!class_exists('GFAPI')) {
        return array('error' => 'Gravity Forms not active');
    }

    $form_id = 30; // Retest form ID
    $user_id = get_current_user_id(); // Current user's ID

    $search_criteria = array(
        'status' => 'active',
        'field_filters' => array(
            array(
                'key' => '30', // Field ID for cert_number
                'value' => $cert_number,
                'operator' => 'is'
            ),
            array(
                'key' => '31', // Field ID for method (adjust if different)
                'value' => $method,
                'operator' => 'is'
            )
        ),
        'field_filters' => array(
            array(
                'key' => 'created_by', // Gravity Forms internal field for user ID
                'value' => $user_id,
                'operator' => 'is'
            )
        )
    );

    $entries = GFAPI::get_entries($form_id, $search_criteria);
    error_log('Retest entries search for user_id: ' . $user_id . ', cert_number: ' . $cert_number . ', method: ' . $method . ', entries found: ' . count($entries));

    if (empty($entries)) {
        error_log('No retest entries found for user_id: ' . $user_id . ', cert_number: ' . $cert_number . ', method: ' . $method);
        return array('error' => 'No matching entries found');
    }

    return $entries; 
}

function display_user_retest_status($cert) {
    $cert_number = $cert['cert_number'];
    $method = $cert['method'];
    $entries = get_user_retest_entries_by_cert_and_method($cert_number, $method);

    if (isset($entries['error'])) {
        return $entries['error'];
    }

    $status = '';
    if (!empty($entries)) {
        $entry = $entries[0];
        $status_field_id = 13; 
        $status = rgar($entry, $status_field_id) ?: 'No status available';
        error_log('Retest status for entry ID ' . $entry['id'] . ': ' . $status);
    }
    return $status;
}

function percent($obtained, $total) {
    return ($total > 0) ? round(($obtained / $total) * 100, 2) : 0;
}

function format_cell($value) {
    if (!isset($value) || $value === '' || $value == 0) {
        return '-';
    }
    return is_numeric($value) ? number_format($value, 2) : $value;
}

function generate_level_2_table($entry, $passed_subjects = [], $is_retest = false) {
    $get_val = fn($k, $default = '') => isset($entry[$k]) && $entry[$k] !== '' ? $entry[$k] : $default;

    // Initialize subjects array
    $subjects = $is_retest ? $passed_subjects : [];

    // Method General
    if (!$is_retest || isset($entry["31"])) {
        $method_general_obt = floatval($get_val("31", ''));
        $method_general_total = floatval($get_val("32", ''));
        if ($method_general_obt != 0 || $method_general_total != 0) {
            $method_general_percent = percent($method_general_obt, $method_general_total);
            $subjects['Method General'] = [
                'name' => 'General',
                'obtained' => $method_general_obt,
                'total' => $method_general_total,
                'percent' => $method_general_percent
            ];
        }
    }

    // Method Specific
    if (!$is_retest || isset($entry["33"])) {
        $method_specific_obt = floatval($get_val("33", ''));
        $method_specific_total = floatval($get_val("34", ''));
        if ($method_specific_obt != 0 || $method_specific_total != 0) {
            $method_specific_percent = percent($method_specific_obt, $method_specific_total);
            $subjects['Method Specific'] = [
                'name' => 'Specific',
                'obtained' => $method_specific_obt,
                'total' => $method_specific_total,
                'percent' => $method_specific_percent
            ];
        }
    }

    // Instruction Writing
    if (!$is_retest || isset($entry["57"])) {
        $procedure_obt = floatval($get_val("57", ''));
        $procedure_total = floatval($get_val("38", ''));
        if ($procedure_obt != 0 || $procedure_total != 0) {
            $procedure_percent = percent($procedure_obt, $procedure_total);
            $subjects['Instruction Writing'] = [
                'name' => 'Instruction Writing',
                'obtained' => $procedure_obt,
                'total' => $procedure_total,
                'percent' => $procedure_percent
            ];
        }
    }

    // Practical
    if (!$is_retest || isset($entry["54"])) {
        $practical_list_raw = maybe_unserialize($get_val("54", ''));
        $practical_rows = '';
        $practical_obt = 0;
        $practical_total = 0;
        $sample_count = 0;

        if (is_array($practical_list_raw) && !empty($practical_list_raw)) {
            foreach ($practical_list_raw as $sample) {
                $sample_name = $sample['Practical Samples'] ?? 'Sample ' . ($sample_count + 1);
                $sample_marks = isset($sample['Marks']) ? floatval($sample['Marks']) : 0;
                if ($sample_marks != 0) { // Only include non-zero samples
                    $practical_obt += $sample_marks;
                    $practical_total += 100;
                    $sample_count++;
                    $practical_rows .= '<tr><td style="width:50%">' . esc_html($sample_name) . '</td><td style="width:50%">' . format_cell($sample_marks) . '</td></tr>';
                }
            }
        }
        if ($sample_count == 0) {
            $practical_rows = '<tr><td style="width:50%">No Samples</td><td style="width:50%">-</td></tr>';
            error_log("No valid practical samples found for entry: " . print_r($entry, true));
        }

        if ($practical_obt != 0 || $practical_total != 0) {
            $practical_percent = percent($practical_obt, $practical_total);
            $subjects['Practical'] = [
                'name' => 'Practical',
                'obtained' => $practical_obt,
                'total' => $practical_total,
                'percent' => $practical_percent
            ];
        }
    }

    if (!$is_retest) {
        foreach ($passed_subjects as $name => $subject) {
            if (!isset($subjects[$name])) {
                $subjects[$name] = $subject;
            }
        }
    }

    $failed_subjects = [];
    foreach ($subjects as $name => $subject) {
        if (is_numeric($subject['percent']) && $subject['percent'] < 70) {
            $failed_subjects[] = $name;
        }
    }

    $overall_result = empty($failed_subjects) ? 'Pass' : 'Fail';
    $retest = empty($failed_subjects) ? 'No' : 'Yes';
    $retest_details = !empty($failed_subjects) ? implode(', ', $failed_subjects) : '-';

    $percentages = array_map(function($subject) {
        return is_numeric($subject['percent']) ? floatval($subject['percent']) : null;
    }, $subjects);
    $percentages = array_filter($percentages);
    $overall_calc_percent = !empty($percentages) ? round(array_sum($percentages) / count($percentages), 2) : 'N/A';

    $table_html = '
    <table border="1" cellpadding="3" style="font-size:10.5pt; width:100%; border-collapse:collapse; border-color:#000;">
    <tbody>';

    foreach ($subjects as $name => $subject) {
        if ($name !== 'Practical') {
            $table_html .= '<tr><td style="width:30%">' . esc_html($name) . '</td><td style="width:40%" colspan="2"></td><td style="width:30%">' . format_cell($subject['obtained']) . '</td></tr>';
        }
    }

    $table_html .= '
    <tr><td style="width:30%" rowspan="' . ($sample_count + 1) . '">PRACTICAL LEVEL 2</td>
    <td style="width:40%" colspan="2"><strong>Samples</strong></td>
    <td style="width:30%" rowspan="' . ($sample_count + 1) . '">' . format_cell($practical_percent) . ($practical_percent !== '-' ? '%' : '') . '</td>
    </tr>
    ' . $practical_rows . '

    <tr style="background-color:#f9f9f9;">
    <td style="width:70%; text-align:center;" colspan="2"><strong>OVERALL RESULT</strong></td>
    <td style="width:30%; text-align:center; font-weight:bold; font-size:13pt; border:2px solid #000;" colspan="2">
    ' . format_cell($overall_result) . ' (' . ($overall_calc_percent !== 'N/A' ? $overall_calc_percent . '%' : '-') . ')
    </td>
    </tr>

    <tr><td style="width:70%" colspan="2">RETEST APPLICABLE</td><td style="width:30%" colspan="2">' . format_cell($retest) . '</td></tr>
    <tr><td style="width:70%" colspan="2">IF Yes (Details)</td><td style="width:30%" colspan="2">' . format_cell($retest_details) . '</td></tr>
    </tbody>
    </table>';

    return [
        'table_html' => $table_html,
        'overall_result' => $overall_result,
        'retest' => $retest,
        'retest_details' => $retest_details,
        'overall_percent' => $overall_calc_percent,
        'subjects' => $subjects,
        'failed_subjects' => $failed_subjects
    ];
}

function generate_level_3_table($entry, $passed_subjects = [], $is_retest = false) {
    $get_val = fn($k, $default = '') => isset($entry[$k]) && $entry[$k] !== '' ? $entry[$k] : $default;

    $subjects = $is_retest ? $passed_subjects : [];

    // Handle Basic parts with combined percentage evaluation
    if (!$is_retest || isset($entry["40"]) || isset($entry["42"]) || isset($entry["44"])) {
        $basic_parts = [
            'Part A' => ['obtained' => floatval($entry["40"] ?? 0), 'total' => floatval($entry["41"] ?? 0)],
            'Part B' => ['obtained' => floatval($entry["42"] ?? 0), 'total' => floatval($entry["43"] ?? 0)],
            'Part C' => ['obtained' => floatval($entry["44"] ?? 0), 'total' => floatval($entry["45"] ?? 0)]
        ];

        $basic_obt = 0;
        $basic_total = 0;
        $basic_part_subjects = [];
        foreach ($basic_parts as $part => $data) {
            $obtained = $data['obtained'];
            $total = $data['total'];
            if ($obtained != 0 || $total != 0) {
                $percent = percent($obtained, $total);
                $basic_part_subjects['Basic ' . $part] = [
                    'name' => 'Basic ' . $part,
                    'obtained' => $obtained,
                    'total' => $total,
                    'percent' => $percent
                ];
                $basic_obt += $obtained;
                $basic_total += $total;
            }
        }

        if ($basic_obt != 0 || $basic_total != 0) {
            $basic_percent = percent($basic_obt, $basic_total);
            $subjects['Basic'] = [
                'name' => 'Basic',
                'obtained' => $basic_obt,
                'total' => $basic_total,
                'percent' => $basic_percent
            ];
            // Only add individual parts to subjects if the combined percentage is below 70
            if ($basic_percent < 70) {
                $subjects = array_merge($subjects, $basic_part_subjects);
            }
        }
    }

    // Handle Practical
    if (!$is_retest || isset($entry["54"])) {
        $practical_list_raw = maybe_unserialize($get_val("54", ''));
        $practical_rows = '';
        $practical_obt = 0;
        $practical_total = 0;
        $sample_count = 0;

        if (is_array($practical_list_raw) && !empty($practical_list_raw)) {
            foreach ($practical_list_raw as $sample) {
                $sample_name = $sample['Practical Samples'] ?? 'Sample ' . ($sample_count + 1);
                $sample_marks = isset($sample['Marks']) ? floatval($sample['Marks']) : 0;
                if ($sample_marks != 0) {
                    $practical_obt += $sample_marks;
                    $practical_total += 100;
                    $sample_count++;
                    $practical_rows .= '<tr><td style="width:50%">' . esc_html($sample_name) . '</td><td style="width:50%">' . format_cell($sample_marks) . '</td></tr>';
                }
            }
        }
        if ($sample_count == 0) {
            $practical_rows = '<tr><td style="width:50%">No Samples</td><td style="width:50%">-</td></tr>';
            error_log("No valid practical samples found for entry: " . print_r($entry, true));
        }

        if ($practical_obt != 0 || $practical_total != 0) {
            $practical_percent = percent($practical_obt, $practical_total);
            $subjects['Practical'] = [
                'name' => 'Practical',
                'obtained' => $practical_obt,
                'total' => $practical_total,
                'percent' => $practical_percent
            ];
        }
    }

    // Handle Method General
    if (!$is_retest || isset($entry["46"])) {
        $method_general_obt = floatval($get_val("46", ''));
        $method_general_total = floatval($get_val("47", ''));
        if ($method_general_obt != 0 || $method_general_total != 0) {
            $method_general_percent = percent($method_general_obt, $method_general_total);
            $subjects['Method General'] = [
                'name' => 'General',
                'obtained' => $method_general_obt,
                'total' => $method_general_total,
                'percent' => $method_general_percent
            ];
        }
    }

    // Handle Method Specific
    if (!$is_retest || isset($entry["48"])) {
        $method_specific_obt = floatval($get_val("48", ''));
        $method_specific_total = floatval($get_val("49", ''));
        if ($method_specific_obt != 0 || $method_specific_total != 0) {
            $method_specific_percent = percent($method_specific_obt, $method_specific_total);
            $subjects['Method Specific'] = [
                'name' => 'Specific',
                'obtained' => $method_specific_obt,
                'total' => $method_specific_total,
                'percent' => $method_specific_percent
            ];
        }
    }

    // Handle Procedure
    if (!$is_retest || isset($entry["50"])) {
        $procedure_obt = floatval($get_val("50", ''));
        $procedure_total = floatval($get_val("51", ''));
        if ($procedure_obt != 0 || $procedure_total != 0) {
            $procedure_percent = percent($procedure_obt, $procedure_total);
            $subjects['Procedure'] = [
                'name' => 'Procedure',
                'obtained' => $procedure_obt,
                'total' => $procedure_total,
                'percent' => $procedure_percent
            ];
        }
    }

    if (!$is_retest) {
        foreach ($passed_subjects as $name => $subject) {
            if (!isset($subjects[$name])) {
                $subjects[$name] = $subject;
            }
        }
    }

    $failed_subjects = [];
    foreach ($subjects as $name => $subject) {
        if ($name !== 'Basic' && is_numeric($subject['percent']) && $subject['percent'] < 70) {
            $failed_subjects[] = $name;
        }
    }

    // Result and retest logic
    $overall_result = empty($failed_subjects) ? 'Pass' : 'Fail';
    $retest = empty($failed_subjects) ? 'No' : 'Yes';
    $retest_details = !empty($failed_subjects) ? implode(', ', $failed_subjects) : '-';

    // Overall average %
    $percentages = array_map(function($subject) {
        return is_numeric($subject['percent']) ? floatval($subject['percent']) : null;
    }, $subjects);
    $percentages = array_filter($percentages);
    $overall_calc_percent = !empty($percentages) ? round(array_sum($percentages) / count($percentages), 2) : 'N/A';

    // Build table HTML
    $table_html = '<table border="1" cellpadding="3" style="font-size:10.5pt; width:100%; border-color:#000; border-collapse:collapse;">
    <tbody>
    <tr><td style="width:30%" rowspan="3">BASIC</td><td style="width:20%">PART A</td><td style="width:20%">' . format_cell($entry["40"] ?? '') . '</td><td style="width:30%" rowspan="3">' . format_cell($basic_percent ?? '-') . ($basic_percent !== '-' ? '%' : '') . '</td></tr>
    <tr><td style="width:20%">PART B</td><td style="width:20%">' . format_cell($entry["42"] ?? '') . '</td></tr>
    <tr><td style="width:20%">PART C</td><td style="width:20%">' . format_cell($entry["44"] ?? '') . '</td></tr>';

    foreach ($subjects as $name => $subject) {
        if ($name !== 'Basic' && $name !== 'Practical') {
            $table_html .= '<tr><td style="width:30%">' . esc_html($name) . '</td><td style="width:40%" colspan="2">' . esc_html($name) . '</td><td style="width:30%">' . format_cell($subject['obtained']) . '</td></tr>';
        }
    }

    $table_html .= '<tr><td style="width:30%" rowspan="' . ($sample_count + 1) . '">PRACTICAL LEVEL 3</td>';
    $table_html .= '<td style="width:40%" colspan="2"><strong>Samples</strong></td>';
    $table_html .= '<td style="width:30%" rowspan="' . ($sample_count + 1) . '">' . format_cell($practical_percent ?? '-') . ($practical_percent !== '-' ? '%' : '') . '</td></tr>';
    $table_html .= $practical_rows;

    $table_html .= '<tr><td style="width:70%" colspan="2">Reason of Failure (If any)</td><td style="width:30%" colspan="2">' . format_cell($entry["failure_reason"] ?? '') . '</td></tr>';
    $table_html .= '<tr><td style="width:70%" colspan="2"><strong>OVERALL RESULT</strong></td><td style="width:30%" colspan="2"><strong>' . format_cell($overall_result) . ' (' . ($overall_calc_percent !== 'N/A' ? $overall_calc_percent . '%' : '-') . ')</strong></td></tr>';
    $table_html .= '<tr><td style="width:70%" colspan="2">RETEST APPLICABLE</td><td style="width:30%" colspan="2">' . format_cell($retest) . '</td></tr>';
    $table_html .= '<tr><td style="width:70%" colspan="2">IF Yes (Details)</td><td style="width:30%" colspan="2">' . format_cell($retest_details) . '</td></tr>';
    $table_html .= '</tbody></table>';
   

    return [
        'table_html' => $table_html,
        'overall_result' => $overall_result,
        'retest' => $retest,
        'retest_details' => $retest_details,
        'overall_percent' => $overall_calc_percent,
        'subjects' => $subjects,
        'failed_subjects' => $failed_subjects
    ];
}


add_filter('gform_pre_render_30', 'populate_user_home_address');
add_filter('gform_pre_validation_30', 'populate_user_home_address');
add_filter('gform_pre_submission_filter_30', 'populate_user_home_address');
add_filter('gform_admin_pre_render_30', 'populate_user_home_address');

function populate_user_home_address($form) {
    $user = wp_get_current_user();

    if ($user && $user->ID) {
        $address      = get_user_meta($user->ID, 'home_address', true);
        $city         = get_user_meta($user->ID, 'home_city', true);
        $state        = get_user_meta($user->ID, 'home_state', true);
        $country_state        = get_user_meta($user->ID, 'home_country', true);
        $postal_code  = get_user_meta($user->ID, 'home_postal_code', true);

        $work_address      = get_user_meta($user->ID, 'work_address', true);
        $work_state        = get_user_meta($user->ID, 'work_state', true);
        $work_city        = get_user_meta($user->ID, 'work_city', true);
        $work_country        = get_user_meta($user->ID, 'work_country', true);
        $work_postal_code  = get_user_meta($user->ID, 'work_postal_code', true);

        foreach ($form['fields'] as &$field) {
            switch ($field->id) {
                case 15:
                    $field->defaultValue = $address;
                    break;
                case 17:
                    $field->defaultValue = $city;
                    break;
                case 18:
                    $field->defaultValue = $postal_code;
                    break;

                case 22:
                    $field->defaultValue = $work_address;
                    break;
                case 24:
                    $field->defaultValue = $work_city;
                    break;
                case 25:
                    $field->defaultValue = $work_postal_code;
                    break;
            }
        }
    }

    return $form;
}

add_action('wp_ajax_update_user_address_block', 'handle_update_user_address_block');

function handle_update_user_address_block() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized');
    }

    $user_id = get_current_user_id();
    $type = sanitize_text_field($_POST['type']);

    $address = sanitize_text_field($_POST['address'] ?? '');
    $city = sanitize_text_field($_POST['city'] ?? '');
    $state = sanitize_text_field($_POST['state'] ?? '');
    $postal = sanitize_text_field($_POST['postal'] ?? '');

    if (!$address || !$city || !$state || !preg_match('/^\d{5,6}$/', $postal)) {
        wp_send_json_error('Invalid data provided');
    }

    if ($type === 'home') {
        update_user_meta($user_id, 'home_address', $address);
        update_user_meta($user_id, 'home_city', $city);
        update_user_meta($user_id, 'home_state', $state);
        update_user_meta($user_id, 'home_postal_code', $postal);
    } elseif ($type === 'work') {
        update_user_meta($user_id, 'work_address', $address);
        update_user_meta($user_id, 'work_city', $city);
        update_user_meta($user_id, 'work_state', $state);
        update_user_meta($user_id, 'work_postal_code', $postal);
    } else {
        wp_send_json_error('Invalid type');
    }

    wp_send_json_success('Address updated');
}


// function export_users_to_csv() {
//     global $wpdb;

//     // Define meta keys for the fields to export
//     $meta_keys = [
//         'candidate_reg_number',
//         'home_address',
//         'home_city',
//         'home_state',
//         'home_postal_code',
//         'work_address',
//         'work_city',
//         'work_state',
//         'work_postal_code',
//         'correspondence_address'
//     ];

//     // Query to fetch users, their meta data, and hashed passwords
//     $query = "
//         SELECT 
//             u.ID,
//             u.user_login,
//             u.user_email,
//             u.user_pass,
//             MAX(CASE WHEN m.meta_key = 'candidate_reg_number' THEN m.meta_value END) as candidate_reg_number,
//             MAX(CASE WHEN m.meta_key = 'home_address' THEN m.meta_value END) as home_address,
//             MAX(CASE WHEN m.meta_key = 'home_city' THEN m.meta_value END) as home_city,
//             MAX(CASE WHEN m.meta_key = 'home_state' THEN m.meta_value END) as home_state,
//             MAX(CASE WHEN m.meta_key = 'home_postal_code' THEN m.meta_value END) as home_postal_code,
//             MAX(CASE WHEN m.meta_key = 'work_address' THEN m.meta_value END) as work_address,
//             MAX(CASE WHEN m.meta_key = 'work_city' THEN m.meta_value END) as work_city,
//             MAX(CASE WHEN m.meta_key = 'work_state' THEN m.meta_value END) as work_state,
//             MAX(CASE WHEN m.meta_key = 'work_postal_code' THEN m.meta_value END) as work_postal_code,
//             MAX(CASE WHEN m.meta_key = 'correspondence_address' THEN m.meta_value END) as correspondence_address
//         FROM {$wpdb->users} u
//         LEFT JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
//         WHERE m.meta_key IN ('" . implode("','", array_map('esc_sql', $meta_keys)) . "')
//         GROUP BY u.ID, u.user_login, u.user_email, u.user_pass
//     ";

//     $users = $wpdb->get_results($query, ARRAY_A);

//     // Set headers for CSV download
//     header('Content-Type: text/csv; charset=utf-8');
//     header('Content-Disposition: attachment; filename=users_export_' . date('Y-m-d_H-i-s') . '.csv');

//     // Open output stream
//     $output = fopen('php://output', 'w');

//     // Write CSV headers
//     fputcsv($output, [
//         'User ID',
//         'Username',
//         'Email',
//         'Hashed Password',
//         'Candidate Registration Number',
//         'Home Address',
//         'Home City',
//         'Home State',
//         'Home Postal Code',
//         'Work Address',
//         'Work City',
//         'Work State',
//         'Work Postal Code',
//         'Correspondence Address'
//     ]);

//     // Write user data to CSV
//     foreach ($users as $user) {
//         fputcsv($output, [
//             $user['ID'],
//             $user['user_login'],
//             $user['user_email'],
//             $user['user_pass'] ?? '',
//             $user['candidate_reg_number'] ?? '',
//             $user['home_address'] ?? '',
//             $user['home_city'] ?? '',
//             $user['home_state'] ?? '',
//             $user['home_postal_code'] ?? '',
//             $user['work_address'] ?? '',
//             $user['work_city'] ?? '',
//             $user['work_state'] ?? '',
//             $user['work_postal_code'] ?? '',
//             $user['correspondence_address'] ?? ''
//         ]);
//     }

//     // Close output stream
//     fclose($output);
//     exit;
// }

// // Hook to trigger the export via admin action
// add_action('admin_init', function() {
//     if (isset($_GET['export_users']) && $_GET['export_users'] === 'csv' && current_user_can('manage_options')) {
//         export_users_to_csv();
//     }
// });



add_action('wp_ajax_ndtss_search_cert', 'ndtss_search_cert_callback');
add_action('wp_ajax_nopriv_ndtss_search_cert', 'ndtss_search_cert_callback');

function ndtss_search_cert_callback() {
    global $wpdb;

    $cert_no   = sanitize_text_field($_POST['cert_no']);
    $name      = sanitize_text_field($_POST['name']);
    $table     = $wpdb->prefix . 'sgndt_final_certifications';
    $usermeta  = $wpdb->prefix . 'usermeta';

   $query = "
    SELECT fc.*, 
           CONCAT_WS(' ', first.meta_value, last.meta_value) AS given_name,
           family.meta_value AS family_name
    FROM $table fc
    LEFT JOIN $usermeta first ON first.user_id = fc.user_id AND first.meta_key = 'first_name'
    LEFT JOIN $usermeta last ON last.user_id = fc.user_id AND last.meta_key = 'last_name'
    LEFT JOIN $usermeta family ON family.user_id = fc.user_id AND family.meta_key = 'family_name'
    WHERE 1=1";


    $params = [];

    if (!empty($cert_no)) {
        $query .= " AND fc.certificate_number = %s";
        $params[] = $cert_no;
    }
	if (!empty($name)) {
	    $query .= " AND (
	        CONCAT_WS(' ', first.meta_value, last.meta_value) LIKE %s
	        OR family.meta_value LIKE %s
	    )";
	    $params[] = '%' . $name . '%';
	    $params[] = '%' . $name . '%';
	}


    $prepared = call_user_func_array([$wpdb, 'prepare'], array_merge([$query], $params));
	$results = $wpdb->get_results($prepared);


    if ($results && count($results)) {
        $data = [];

        foreach ($results as $r) {
            $data[] = [
                'certificate_number' => $r->certificate_number,
                'given_name'         => $r->given_name,
                'family_name'        => $r->family_name,
                'method'             => $r->method,
                'level'              => $r->level,
                'sector'             => $r->sector,
                'scope'             => $r->scope,
                'expiry_date'        => date('d-M-Y', strtotime($r->expiry_date)),
            ];
        }

        wp_send_json_success($data);
    } else {
        wp_send_json_error(['message' => 'No matching records found.']);
    }

    wp_die();
}




add_filter('gform_pre_render_39', 'populate_user_data');
add_filter('gform_pre_validation_39', 'populate_user_data');
add_filter('gform_pre_submission_filter_39', 'populate_user_data');
add_filter('gform_admin_pre_render_39', 'populate_user_data');

function populate_user_data($form) {

    $user = wp_get_current_user();

    if ($user && $user->ID) {

        // Home address
        $address     = get_user_meta($user->ID, 'home_address', true);
        $city        = get_user_meta($user->ID, 'home_city', true);
        $state       = get_user_meta($user->ID, 'home_state', true);
        $country     = get_user_meta($user->ID, 'home_country', true);
        $postal_code = get_user_meta($user->ID, 'home_postal_code', true);

        // Work address
        $work_address     = get_user_meta($user->ID, 'work_address', true);
        $work_city        = get_user_meta($user->ID, 'work_city', true);
        $work_state       = get_user_meta($user->ID, 'work_state', true);
        $work_country     = get_user_meta($user->ID, 'work_country', true);
        $work_postal_code = get_user_meta($user->ID, 'work_postal_code', true);

        foreach ($form['fields'] as &$field) {

            // HOME
            if (strpos($field->cssClass, 'user_street_address') !== false) {
                $field->defaultValue = $address;
            }
            elseif (strpos($field->cssClass, 'user_city') !== false) {
                $field->defaultValue = $city;
            }
            elseif (strpos($field->cssClass, 'user_zip_code') !== false) {
                $field->defaultValue = $postal_code;
            }
            elseif (strpos($field->cssClass, 'user_country_state') !== false && !empty($field->inputs)) {
                if (isset($field->inputs[0])) {
                    $field->inputs[0]['defaultValue'] = $country;
                }
                if (isset($field->inputs[1])) {
                    $field->inputs[1]['defaultValue'] = $state;
                }
            }

            // WORK
            if (strpos($field->cssClass, 'user_work_street_address') !== false) {
                $field->defaultValue = $work_address;
            }
            elseif (strpos($field->cssClass, 'user_work_city') !== false) {
                $field->defaultValue = $work_city;
            }
            elseif (strpos($field->cssClass, 'user_work_zip_code') !== false) {
                $field->defaultValue = $work_postal_code;
            }
            elseif (strpos($field->cssClass, 'user_work_country_state') !== false && !empty($field->inputs)) {
                if (isset($field->inputs[0])) {
                    $field->inputs[0]['defaultValue'] = $work_country;
                }
                if (isset($field->inputs[1])) {
                    $field->inputs[1]['defaultValue'] = $work_state;
                }
            }
        }
    }

    return $form;
}
function is_field_visible_by_conditional_logic($field, $form, $entry) {
    if (empty($field['conditionalLogic']) || !is_array($field['conditionalLogic'])) {
        return true; // No logic = always visible
    }

    $logic = $field['conditionalLogic'];

    foreach ($logic['rules'] as $rule) {
        $field_id = $rule['fieldId'];
        $operator = $rule['operator'];
        $value = $rule['value'];
        $entry_value = $entry[$field_id] ?? '';

        switch ($operator) {
            case 'is':
                if ($entry_value != $value) return !$logic['actionType'] === 'hide';
                break;
            case 'isnot':
                if ($entry_value == $value) return !$logic['actionType'] === 'hide';
                break;
            case 'greater_than':
                if (!is_numeric($entry_value) || $entry_value <= $value) return !$logic['actionType'] === 'hide';
                break;
            case 'less_than':
                if (!is_numeric($entry_value) || $entry_value >= $value) return !$logic['actionType'] === 'hide';
                break;
            // Add more operators as needed
        }
    }

    return $logic['actionType'] === 'show';
}

// CPD Renewal Form Submission Handler
add_action('gform_after_submission_39', 'process_cpd_renewal_submission', 10, 2);

function process_cpd_renewal_submission($entry, $form) {
    global $wpdb;

    // Get form data
    $cert_id = rgar($entry, '1'); // Certificate ID
    $user_id = get_current_user_id();
    if (!$user_id) return;
    $entry_id = rgar($entry, 'id');
    $order_number = 'EXAM-' . date('Ymd') . '-' . $entry_id;
	GFAPI::update_entry_field($entry_id, '12', $order_number);
    $method = 'Exam'; // This is CPD renewal
	

    // Get user data
    $user_data = get_userdata($user_id);
    $candidate_name = $user_data ? $user_data->display_name : '';
    $candidate_reg_number = get_user_meta($user_id, 'candidate_reg_number', true);
	$center_name = rgar($entry, '9'); 
	$center_post = get_page_by_title($center_name, OBJECT, 'exam_center');
	

	if ($center_post) {
		gform_update_meta($entry_id, '_linked_exam_center', $center_post->ID);
		send_exam_submission_notification($entry_id, $order_number, $center_post);
	}

    if (empty($candidate_reg_number)) {
        error_log("Candidate registration number not found for user ID: $user_id");
        return;
    }


}

function send_cpd_renewal_confirmation($user_id, $certificate_data, $cpd_data) {
    $user_data = get_userdata($user_id);
    $candidate_email = $user_data && is_email($user_data->user_email) ? $user_data->user_email : '';

    if (empty($candidate_email)) {
        error_log("No email found for user ID: $user_id");
        return;
    }

    // Check if required data exists
    if (empty($certificate_data) || empty($cpd_data)) {
        error_log("Missing certificate or CPD data for user ID: $user_id");
        return;
    }

    // Extract certificate number and URL with fallbacks
    $certificate_number = isset($certificate_data['certificate_number']) ? $certificate_data['certificate_number'] : 'N/A';
    $certificate_url = isset($certificate_data['certificate_url']) ? $certificate_data['certificate_url'] : '';
    $candidate_name = isset($cpd_data['candidate_name']) ? $cpd_data['candidate_name'] : 'Valued Candidate';

    $subject = 'CPD Renewal Certificate Generated';
    $body = '
    Dear ' . esc_html($candidate_name) . ',<br><br>
    Your CPD renewal certificate has been successfully generated.<br><br>
    <strong>Certificate Number:</strong> ' . esc_html($certificate_number) . '<br>
    <strong>Generated Date:</strong> ' . date('d/m/Y') . '<br><br>';

    if (!empty($certificate_url)) {
        $body .= '<a href="' . esc_url($certificate_url) . '" target="_blank">Download Your Renewal Certificate</a><br><br>';
    }

    $body .= 'Best regards,<br>
    NDTSS Certification Team
    ';

    if (function_exists('get_email_template')) {
        $message = get_email_template($subject, $body);
    } else {
        // Fallback if get_email_template doesn't exist
        $message = '<html><body>' . $body . '</body></html>';
    }

    add_filter('wp_mail_content_type', function () { return 'text/html'; });
    $sent = wp_mail($candidate_email, $subject, $message);
    remove_filter('wp_mail_content_type', function () { return 'text/html'; });

    if (!$sent) {
        error_log("Failed to send CPD renewal confirmation email to: $candidate_email");
    } else {
        error_log("CPD renewal confirmation email sent successfully to: $candidate_email");
    }
}

/**
 * Certificate Status Management Functions for Renewal Workflow
 */

/**
 * Update certificate status using the new consolidated system
 */
function update_certificate_statuss($user_id, $cert_number, $new_status, $additional_data = []) {
    $cert_status_key = 'cert_status_' . $cert_number;

    // Update the status
    $updated = update_user_meta($user_id, $cert_status_key, $new_status);

    if ($updated) {
        // Update status date
        update_user_meta($user_id, $cert_status_key . '_date', current_time('mysql'));

        // Store additional data if provided
        if (!empty($additional_data)) {
            foreach ($additional_data as $key => $value) {
                update_user_meta($user_id, $cert_status_key . '_' . $key, $value);
            }
        }

        // Clear any cached status info
        delete_transient('cert_status_info_' . $user_id . '_' . $cert_number);

        return true;
    }

    return false;
}

/**
 * Get certificate status information by ID
 */
function get_certificate_status_info_by_ida($user_id, $cert_id) {
    global $wpdb;

    $cache_key = 'cert_status_info_' . $user_id . '_' . $cert_id;
    $cached_info = get_transient($cache_key);

    if ($cached_info !== false) {
        return $cached_info;
    }

    // Get certificate details from database
    $cert = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sgndt_final_certifications
         WHERE user_id = %d AND final_certification_id = %d",
        $user_id, $cert_id
    ));

    if (!$cert) {
        return false;
    }

    // Check status in user meta using certificate number
    $cert_status_key = 'cert_status_' . $cert->certificate_number;
    $cert_status = get_user_meta($user_id, $cert_status_key, true);

    $status_info = [
        'effective_status' => !empty($cert_status) ? $cert_status : $cert->status,
        'formatted_status_date' => '',
        'renewed_cert_number' => '',
        'certificate_number' => $cert->certificate_number,
        'original_cert_id' => $cert_id
    ];

    // Get additional status data
    if (!empty($cert_status)) {
        $status_date = get_user_meta($user_id, $cert_status_key . '_date', true);
        $status_info['formatted_status_date'] = $status_date ? date('d/m/Y', strtotime($status_date)) : '';

        $renewed_cert_number = get_user_meta($user_id, $cert_status_key . '_cert_number', true);
        $status_info['renewed_cert_number'] = $renewed_cert_number ?: '';

        // Special handling for renewal workflow statuses
        if ($cert_status === 'submitted') {
            $status_info['effective_status'] = 'renewal_applied';
        } elseif ($cert_status === 'approved') {
            $status_info['effective_status'] = 'approved';
        } elseif ($cert_status === 'certificate_issued') {
            $cert_issued_number = get_user_meta($user_id, $cert_status_key . '_cert_number', true);
            if ($cert_issued_number) {
                $status_info['effective_status'] = 'renewed_ref_' . $cert_issued_number;
            }
        }
    }

    // Cache for 15 minutes
    set_transient($cache_key, $status_info, 15 * MINUTE_IN_SECONDS);

    return $status_info;
}

/**
 * Get certificate status information by certificate number
 */
function get_certificate_status_info_by_number($user_id, $cert_number) {
    $cert_status_key = 'cert_status_' . $cert_number;
    $cert_status = get_user_meta($user_id, $cert_status_key, true);

    $status_info = [
        'effective_status' => !empty($cert_status) ? $cert_status : 'no_status',
        'formatted_status_date' => '',
        'renewed_cert_number' => '',
        'certificate_number' => $cert_number
    ];

    if (!empty($cert_status)) {
        $status_date = get_user_meta($user_id, $cert_status_key . '_date', true);
        $status_info['formatted_status_date'] = $status_date ? date('d/m/Y', strtotime($status_date)) : '';

        $renewed_cert_number = get_user_meta($user_id, $cert_status_key . '_cert_number', true);
        $status_info['renewed_cert_number'] = $renewed_cert_number ?: '';

        // Special handling for renewal workflow statuses
        if ($cert_status === 'submitted') {
            $status_info['effective_status'] = 'renewal_applied';
        } elseif ($cert_status === 'approved') {
            $status_info['effective_status'] = 'approved';
        } elseif ($cert_status === 'certificate_issued') {
            $cert_issued_number = get_user_meta($user_id, $cert_status_key . '_cert_number', true);
            if ($cert_issued_number) {
                $status_info['effective_status'] = 'renewed_ref_' . $cert_issued_number;
            }
        }
    }

    return $status_info;
}

/**
 * Get user-friendly status display text
 */
function get_renewal_status_display_textd($status) {
    $status_map = [
        'submitted' => 'Applied for Renewal',
        'under_review' => 'Under Review',
        'reviewing' => 'Under Review',
        'approved' => 'Renewal Approved',
        'certificate_issued' => 'Certificate Issued',
        'renewed' => 'Renewed',
        'completed' => 'Completed'
    ];

    return isset($status_map[$status]) ? $status_map[$status] : ucfirst(str_replace('_', ' ', $status));
}

/**
 * Get formatted certificate status display for user profile
 */
function get_certificate_status_displaya($effective_status, $formatted_status_date, $cert_issued_number = '', $formatted_cert_date = '') {
    $display_text = get_renewal_status_display_text($effective_status);

    switch ($effective_status) {
        case 'submitted':
        case 'under_review':
        case 'reviewing':
            return '<div class="renewal-status-wrapper">' .
                   '<span class="status-reviewing">' . esc_html($display_text) . '</span><br>' .
                   '<small>Submitted: ' . esc_html($formatted_status_date) . '</small>' .
                   '</div>';
        case 'approved':
            return '<div class="renewal-status-wrapper">' .
                   '<span class="status-approved">✓ ' . esc_html($display_text) . '</span><br>' .
                   '<small>Approved: ' . esc_html($formatted_status_date) . '</small>' .
                   '</div>';
        case 'renewed':
            return '<div class="renewal-status-wrapper">' .
                   '<span class="status-renewed">✓ ' . esc_html($display_text) . '</span><br>' .
                   '<small>New Cert: ' . esc_html($cert_issued_number ?: 'N/A') . '</small><br>' .
                   '<small>Issued: ' . esc_html($formatted_cert_date) . '</small>' .
                   '</div>';
        case 'certificate_issued':
        case 'completed':
            return '<div class="renewal-status-wrapper">' .
                   '<span class="status-approved">✓ ' . esc_html($display_text) . '</span><br>' .
                   '<small>New Cert: ' . esc_html($cert_issued_number ?: 'N/A') . '</small><br>' .
                   '<small>Issued: ' . esc_html($formatted_cert_date) . '</small>' .
                   '</div>';
        default:
            return '<div class="renewal-status-wrapper">' .
                   '<span class="status-pending">' . esc_html($display_text) . '</span><br>' .
                   '<small>Status: ' . esc_html($formatted_status_date) . '</small>' .
                   '</div>';
    }
}

/**
 * Check if certificate is renewed (has -1, -2, etc. suffix)
 */
function is_renewed_certificates($cert_number) {
    return preg_match('/-\d+$/', $cert_number);
}

/**
 * Get certificate action buttons based on status and eligibility
 */
function get_certificate_action_buttonss($cert, $current_date, $renewal_url, $recertification_url, $full_exam_url) {
    $cert_number = $cert['certificate_number'];
    $expiry_date_obj = !empty($cert['expiry_date']) ? new DateTime($cert['expiry_date'], new DateTimeZone('Asia/Kolkata')) : null;

    if (!$expiry_date_obj) {
        return '<span style="color: #6c757d;">No expiry date</span>';
    }

    $days_until_expiry = $current_date->diff($expiry_date_obj)->days;
    $is_expired = $expiry_date_obj < $current_date;

    // Check if already has a renewal in progress
    $user_id = get_current_user_id();
    $cert_status_key = 'cert_status_' . $cert_number;
    $cert_status = get_user_meta($user_id, $cert_status_key, true);

    if (!empty($cert_status) && in_array($cert_status, ['submitted', 'approved', 'certificate_issued'])) {
        return '<span style="color: #007bff; font-style: italic;">Renewal in Progress</span>';
    }

    if ($is_expired || $days_until_expiry <= 180) {
        // Show renewal options
        $buttons = [];

        if ($days_until_expiry <= 30 || $is_expired) {
            // Show both CPD and Exam options for urgent renewals
            $buttons[] = '<a href="' . $renewal_url . '" class="action-button" data-action="cpd" data-exam-id="" data-marks-id="" data-method="' . $cert['method'] . '" style="background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px; margin-right: 5px;">CPD Renewal</a>';
            $buttons[] = '<a href="' . $full_exam_url . '" class="action-button" data-action="exam" data-exam-id="" data-marks-id="" data-method="' . $cert['method'] . '" style="background: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">Exam Renewal</a>';
        } else {
            // Show CPD renewal option for regular renewals
            $buttons[] = '<a href="' . $renewal_url . '" class="action-button" data-action="cpd" data-exam-id="" data-marks-id="" data-method="' . $cert['method'] . '" style="background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">Renew</a>';
        }

        return implode('', $buttons);
    }

    // Not yet eligible for renewal
    $formatted_expiry = $expiry_date_obj->format('d/m/Y');
    return '<span style="color: #28a745; font-style: italic;">Valid until ' . $formatted_expiry . '</span>';
}	


/**
 * Restrict Gravity Forms access for membership_admin role
 */
add_action('admin_menu', function() {
    $user = wp_get_current_user();

    if (in_array('membership_admin', (array) $user->roles)) {
        // Remove restricted Gravity Forms menu items
        remove_submenu_page('gf_edit_forms', 'gf_settings');          // Settings
        remove_submenu_page('gf_edit_forms', 'gf_export');            // Export
        remove_submenu_page('gf_edit_forms', 'gf_export_entries');    // Export Entries
        remove_submenu_page('gf_edit_forms', 'gf_import');            // Import
        remove_submenu_page('gf_edit_forms', 'gf_addons');            // Add-ons
        remove_submenu_page('gf_edit_forms', 'gf_new_form');          // New Form
    }
}, 999);


/**
 * Restrict forms list visibility for membership_admin
 */
add_filter('gform_form_list_forms', function($forms) {
    $user = wp_get_current_user();
    if (in_array('membership_admin', (array) $user->roles)) {
        $allowed_forms = [12, 4, 5];
        foreach ($forms as $key => $form) {
            if (!in_array($form->id, $allowed_forms)) {
                unset($forms[$key]);
            }
        }
    }
    return $forms;
});


/**
 * Restrict entry list to only allowed form IDs
 */
add_action('gform_pre_entry_list', function($form_id) {
    $user = wp_get_current_user();
    if (in_array('membership_admin', (array) $user->roles)) {
        $allowed_forms = [12, 4, 5];
        if (!in_array($form_id, $allowed_forms)) {
            wp_die(__('You do not have permission to view these entries.'));
        }
    }
});


/**
 * Block direct access to restricted admin pages
 */
add_action('current_screen', function($screen) {
    $user = wp_get_current_user();
    if (in_array('membership_admin', (array) $user->roles)) {
        $restricted_pages = [
            'gf_settings', 'gf_export', 'gf_export_entries', 'gf_import', 'gf_addons', 'gf_new_form'
        ];
        if (in_array($screen->id, $restricted_pages)) {
            wp_die(__('You do not have permission to access this page.'));
        }
    }
});


/**
 * Prevent "Add New" button on Forms list page
 */
add_action('admin_head', function() {
    $user = wp_get_current_user();
    if (in_array('membership_admin', (array) $user->roles)) {
        echo '<style>
            .gf_new_form_button, .page-title-action { display: none !important; }
        </style>';
    }
});


/**
 * Adjust form counts in Gravity Forms list navigation (All / Active / Inactive / Trash)
 */
add_action('admin_footer', function() {
    $user = wp_get_current_user();
    if (in_array('membership_admin', (array) $user->roles)) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Hide unnecessary filters
            $('.form-list-nav .subsubsub li').each(function() {
                var text = $(this).text().toLowerCase();
                if (text.includes('all') || text.includes('active') || text.includes('inactive') || text.includes('trash')) {
                    // Keep visible, but adjust counts dynamically later
                } else {
                    $(this).hide();
                }
            });

            // Update counts to show only allowed forms (if visible on page)
            const allowedFormIDs = [12, 4, 5];
            let totalCount = 0, activeCount = 0, inactiveCount = 0, trashCount = 0;

            $('table.wp-list-table.forms tbody tr').each(function() {
                const formID = parseInt($(this).find('input[type="checkbox"]').val());
                if (allowedFormIDs.includes(formID)) {
                    totalCount++;
                    if ($(this).hasClass('inactive')) inactiveCount++;
                    else if ($(this).hasClass('trash')) trashCount++;
                    else activeCount++;
                } else {
                    $(this).hide();
                }
            });

            $('#all_count').text(totalCount);
            $('#active_count').text(activeCount);
            $('#inactive_count').text(inactiveCount);
            $('#trash_count').text(trashCount);
        });
        </script>
        <?php
    }
});
/**
 * Hide Gravity Forms tabs and restrict access for membership_admin role
 */
add_action('admin_head', function() {
    $user = wp_get_current_user();

    if (in_array('membership_admin', (array) $user->roles)) {
        echo '<style>
            /* Hide All | Active | Inactive | Trash tabs */
            .form-list-nav, 
            .subsubsub { display: none !important; }

            /* Hide Add New button */
            .gf_new_form_button, 
            .page-title-action { display: none !important; }

            /* Hide Settings, Import/Export, Add-ons menu */
            #toplevel_page_gf_edit_forms .wp-submenu li a[href*="gf_settings"],
            #toplevel_page_gf_edit_forms .wp-submenu li a[href*="gf_export"],
            #toplevel_page_gf_edit_forms .wp-submenu li a[href*="gf_export_entries"],
            #toplevel_page_gf_edit_forms .wp-submenu li a[href*="gf_import"],
            #toplevel_page_gf_edit_forms .wp-submenu li a[href*="gf_addons"],
            #toplevel_page_gf_edit_forms .wp-submenu li a[href*="gf_new_form"] {
                display: none !important;
            }
        </style>';
    }
});

/**
 * Remove restricted forms from admin bar for membership_admin
 */
add_action('admin_bar_menu', function($wp_admin_bar) {
    $user = wp_get_current_user();
    if (in_array('membership_admin', (array) $user->roles)) {
        // Remove All Forms link
        $wp_admin_bar->remove_node('new-gf-form');   // "New Form" under Forms
        $wp_admin_bar->remove_node('gf_edit_forms'); // Main Forms menu

        // Optional: If you want to keep Forms menu but restrict items, you need to rebuild allowed submenu
        $allowed_forms = [12, 4, 5];
        foreach ($allowed_forms as $form_id) {
            // Keep only allowed forms here if needed
        }
    }
}, 999);
