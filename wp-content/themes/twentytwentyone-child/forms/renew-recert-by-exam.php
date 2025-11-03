<?php
/**
 * Form 31 - Renewal/Recertification by Exam Functions
 * 
 * This file contains all functions related to Form 31 (Gravity Form ID 31)
 * used for renewal and recertification by examination process.
 * 
 * Features:
 * - URL parameter auto-fill functionality
 * - Duplicate submission protection
 * - Script enqueuing for proper form functionality
 * - Debug helpers for field identification
 * 
 * @package NDTSS
 * @subpackage Forms
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================================
 * FORM 31 GRAVITY FORMS HOOKS AND FILTERS
 * ============================================================================
 */

// Form 31 URL parameter auto-fill functionality
add_filter('gform_pre_render_31', 'populate_form_31_from_url_params');
add_filter('gform_pre_validation_31', 'populate_form_31_from_url_params');
add_filter('gform_admin_pre_render_31', 'populate_form_31_from_url_params');
add_filter('gform_pre_submission_filter_31', 'populate_form_31_from_url_params');

// Form 31 submission protection
add_filter('gform_pre_submission_31', 'prevent_duplicate_form_31_submissions');
add_action('gform_pre_submission_31', 'check_form_31_submission_rate');

// Form 31 script enqueuing and UI functions
add_action('wp_enqueue_scripts', 'enqueue_gravity_forms_scripts_for_form_31', 20);
add_action('wp_footer', 'prevent_form_31_duplicate_submissions');
add_action('wp_footer', 'add_gravity_forms_fallback_script', 25);

/**
 * ============================================================================
 * URL PARAMETER AUTO-FILL FUNCTIONALITY
 * ============================================================================
 */

/**
 * Auto-fill Form 31 fields from URL parameters
 * 
 * Extracts method, level, sector.level, scope from URL and populates corresponding form fields
 * 
 * Usage Examples:
 * 1. Single parameter: ?method=UT
 * 2. Multiple parameters: ?method=UT&level=2&scope=Limited
 * 3. Using sector.level: ?method=RT&sector.level=General&scope=Full
 * 4. Alternative format: ?method=PT&sector_level=Specific&level=1
 * 
 * Supported URL Parameters:
 * - method: NDT method (UT, RT, PT, MT, ET, VT, TT, PAUT, TOFD)
 * - level: Certification level (1, 2, 3)
 * - sector.level or sector_level: Sector level (General, Specific, etc.)
 * - scope: Certification scope (Full, Limited, etc.)
 * 
 * Field Mapping Configuration:
 * Update the $field_mapping array below with actual Form 31 field IDs.
 * Use the debug_form_31_fields() function to identify correct field IDs.
 * 
 * Supported Field Types:
 * - Text fields: Direct value assignment
 * - Select/Radio: Sets default selected value
 * - Checkbox: Automatically checks matching options
 * 
 * @param array $form The Gravity Forms form array
 * @return array Modified form array with populated fields
 */
function populate_form_31_from_url_params($form) {
    // Only process Form 31
    if ($form['id'] != 31) {
        return $form;
    }

    // Extract URL parameters
    $method = isset($_GET['method']) ? sanitize_text_field($_GET['method']) : '';
    $level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
    $sector_level = isset($_GET['sector_level']) ? sanitize_text_field($_GET['sector_level']) : '';
    $scope = isset($_GET['scope']) ? sanitize_text_field($_GET['scope']) : '';

    // Also check for 'sector.level' parameter format
    if (empty($sector_level) && isset($_GET['sector.level'])) {
        $sector_level = sanitize_text_field($_GET['sector.level']);
    }
     $post_type = 'exam_center';

    // Get CPT posts
    $posts = get_posts( array(
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC'
    ) );

    // Field mapping for Form 31 - Update these field IDs based on actual Form 31 structure
    $field_mapping = [
        'method' => 1,        // Method selection field ID (update as needed)
        'level' => 5,         // Level field ID (update as needed)
        'sector_level' => 4,  // Sector.Level field ID (update as needed)
        'scope' => 3,  
        'center' => 9,       // Scope field ID (update as needed)
    ];

    // Process each field in the form
    foreach ($form['fields'] as &$field) {
        if (!isset($field->id)) continue;

        switch ($field->id) {
            case $field_mapping['method']:
                if (!empty($method)) {
                    // Handle different field types (dropdown, radio, checkbox)
                    if ($field->type === 'select' || $field->type === 'radio') {
                        // Set default value for select/radio fields
                        $field->defaultValue = $method;
                    } elseif ($field->type === 'checkbox') {
                        // For checkbox fields, check specific choices
                        foreach ($field->choices as &$choice) {
                            if (stripos($choice['value'], $method) !== false || stripos($choice['text'], $method) !== false) {
                                $choice['isSelected'] = true;
                            }
                        }
                    } else {
                        // For text fields
                        $field->defaultValue = $method;
                    }
                }
                break;

            case $field_mapping['level']:
                if (!empty($level)) {
                    if ($field->type === 'select' || $field->type === 'radio') {
                        $field->defaultValue = $level;
                    } elseif ($field->type === 'checkbox') {
                        foreach ($field->choices as &$choice) {
                            if (stripos($choice['value'], $level) !== false || stripos($choice['text'], $level) !== false) {
                                $choice['isSelected'] = true;
                            }
                        }
                    } else {
                        $field->defaultValue = $level;
                    }
                }
                break;

            case $field_mapping['sector_level']:
                if (!empty($sector_level)) {
                    if ($field->type === 'select' || $field->type === 'radio') {
                        $field->defaultValue = $sector_level;
                    } elseif ($field->type === 'checkbox') {
                        foreach ($field->choices as &$choice) {
                            if (stripos($choice['value'], $sector_level) !== false || stripos($choice['text'], $sector_level) !== false) {
                                $choice['isSelected'] = true;
                            }
                        }
                    } else {
                        $field->defaultValue = $sector_level;
                    }
                }
                break;

            case $field_mapping['scope']:
                if (!empty($scope)) {
                    if ($field->type === 'select' || $field->type === 'radio') {
                        $field->defaultValue = $scope;
                    } elseif ($field->type === 'checkbox') {
                        foreach ($field->choices as &$choice) {
                            if (stripos($choice['value'], $scope) !== false || stripos($choice['text'], $scope) !== false) {
                                $choice['isSelected'] = true;
                            }
                        }
                    } else {
                        $field->defaultValue = $scope;
                    }
                }
                break;

                case $field_mapping['center']:
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
 * ============================================================================
 * DEBUG AND HELPER FUNCTIONS
 * ============================================================================
 */

/**
 * Debug helper to log Form 31 field structure
 * Enable this temporarily to identify correct field IDs
 * 
 * @param array $form The Gravity Forms form array
 * @return array Unmodified form array
 */
function debug_form_31_fields($form) {
    if ($form['id'] == 31) {
        error_log('=== FORM 31 FIELD DEBUG ===');
        foreach ($form['fields'] as $field) {
            error_log(sprintf('Field ID: %s | Type: %s | Label: %s', 
                $field->id, 
                $field->type, 
                $field->label
            ));
            
            // Log choices for select/radio/checkbox fields
            if (isset($field->choices) && !empty($field->choices)) {
                foreach ($field->choices as $choice) {
                    error_log(sprintf('  Choice: %s = %s', $choice['text'], $choice['value']));
                }
            }
        }
        error_log('=== END FORM 31 DEBUG ===');
    }
    return $form;
}

// Uncomment the next line to enable field debugging:
// add_filter('gform_pre_render_31', 'debug_form_31_fields');

/**
 * ============================================================================
 * SCRIPT ENQUEUING AND FORM LOADING
 * ============================================================================
 */

/**
 * Enqueue Gravity Forms scripts when Form 31 is loaded
 * Fixes "gf_global is not defined" error
 */
function enqueue_gravity_forms_scripts_for_form_31() {
    // Check if we're on a page that might load Form 31
    if (is_page() || is_single() || (isset($_GET['cert_id']) && isset($_GET['method']))) {
        // Check if Gravity Forms is active
        if (class_exists('GFForms')) {
            // Manually enqueue Gravity Forms scripts
            gravity_form_enqueue_scripts(31, false); // Form ID 31, not AJAX
            
            // Also enqueue conditional logic script
            wp_enqueue_script('gform_conditional_logic');
            wp_enqueue_script('gform_gravityforms');
            
            // Enqueue form-specific scripts
            GFFormDisplay::enqueue_form_scripts(GFAPI::get_form(31), false);
        }
    }
}

/**
 * ============================================================================
 * SUBMISSION PROTECTION FUNCTIONS
 * ============================================================================
 */

/**
 * Prevent duplicate Form 31 submissions
 * Add JavaScript to handle submission conflicts
 */
function prevent_form_31_duplicate_submissions() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Form 31 submission protection
        var form31Submitting = false;
        
        // Handle form submission for Form 31
        $('#gform_31').on('submit', function(e) {
            if (form31Submitting) {
                e.preventDefault();
                console.log('Form 31 submission blocked - already in progress');
                return false;
            }
            
            form31Submitting = true;
            console.log('Form 31 submission started');
            
            // Reset flag after submission completes (success or error)
            setTimeout(function() {
                form31Submitting = false;
                console.log('Form 31 submission flag reset');
            }, 5000); // Reset after 5 seconds
        });
        
        // Reset flag on form events
        $(document).on('gform_confirmation_loaded', function(event, formId) {
            if (formId == 31) {
                form31Submitting = false;
                console.log('Form 31 submission completed successfully');
            }
        });
        
        // Reset flag on validation errors
        $(document).on('gform_post_render', function(event, formId) {
            if (formId == 31) {
                // Check for validation errors
                if ($('#gform_31 .gfield_error').length > 0) {
                    form31Submitting = false;
                    console.log('Form 31 submission reset due to validation errors');
                }
            }
        });
    });
    </script>
    <?php
}

/**
 * Server-side protection against Form 31 duplicate submissions
 * Checks for recent submissions from the same user
 * 
 * @param array $form The Gravity Forms form array
 * @return array Unmodified form array
 */
function prevent_duplicate_form_31_submissions($form) {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return $form;
    }

    // Check for recent submissions (within last 30 seconds)
    $recent_entries = GFAPI::get_entries(31, [
        'field_filters' => [
            ['key' => 'created_by', 'value' => $user_id]
        ],
        'start_date' => date('Y-m-d H:i:s', strtotime('-30 seconds'))
    ]);

    if (!is_wp_error($recent_entries) && !empty($recent_entries)) {
        // Prevent submission if recent entry exists
        add_filter('gform_validation_31', function($validation_result) {
            $validation_result['is_valid'] = false;
            $validation_result['form']['fields'][0]->failed_validation = true;
            $validation_result['form']['fields'][0]->validation_message = 'Please wait before submitting another form. A recent submission was already processed.';
            return $validation_result;
        });
    }

    return $form;
}

/**
 * Alternative approach: Use transients to prevent rapid Form 31 submissions
 * This is more reliable than database queries
 */
function check_form_31_submission_rate() {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return;
    }

    $transient_key = 'form_31_submit_' . $user_id;
    $last_submission = get_transient($transient_key);

    if ($last_submission) {
        // Block submission if attempted within 10 seconds
        add_filter('gform_validation_31', function($validation_result) {
            $validation_result['is_valid'] = false;
            $form = $validation_result['form'];
            
            // Find first field to attach error message
            foreach ($form['fields'] as &$field) {
                if (!empty($field->label)) {
                    $field->failed_validation = true;
                    $field->validation_message = 'Please wait a moment before submitting again. Your previous submission is being processed.';
                    break;
                }
            }
            
            $validation_result['form'] = $form;
            return $validation_result;
        });
    } else {
        // Set transient for 10 seconds to prevent rapid submissions
        set_transient($transient_key, time(), 10);
    }
}

/**
 * ============================================================================
 * JAVASCRIPT FALLBACK FUNCTIONS
 * ============================================================================
 */

/**
 * Add JavaScript fallback for gf_global undefined errors
 * Provides compatibility when Gravity Forms scripts aren't fully loaded
 */
function add_gravity_forms_fallback_script() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Check if gf_global is undefined and create fallback
        if (typeof gf_global === 'undefined') {
            console.log('gf_global not found, creating fallback');
            
            // Create minimal gf_global object
            window.gf_global = {
                gform_theme: 'gravity'
            };
        }
        
        // Check if gform object exists, create minimal fallback if not
        if (typeof gform === 'undefined') {
            console.log('gform not found, creating fallback');
            
            window.gform = {
                addAction: function(action, callback) {
                    // Simple action system fallback
                    console.log('gform.addAction fallback called:', action);
                },
                triggerPostRender: function(formId) {
                    console.log('triggerPostRender fallback called for form:', formId);
                    $(document).trigger('gform_post_render', [formId]);
                },
                core: {
                    triggerPostRenderEvents: function(formId, currentPage) {
                        console.log('triggerPostRenderEvents fallback called for form:', formId);
                        $(document).trigger('gform_post_render', [formId, currentPage]);
                    }
                }
            };
        }
        
        // Initialize Form 31 if present
        if ($('#gform_31').length > 0) {
            console.log('Form 31 detected, ensuring initialization');
            
            // Trigger form initialization events
            $(document).trigger('gform_post_render', [31, 1]);
            
            // Apply conditional logic if function exists
            if (typeof gf_apply_rules !== 'undefined') {
                gf_apply_rules(31, [], true);
            }
        }
    });
    </script>
    <?php
}

/**
 * ============================================================================
 * UTILITY FUNCTIONS
 * ============================================================================
 */

/**
 * Get Form 31 field mapping configuration
 * Centralized configuration for all Form 31 field mappings
 * 
 * @return array Field mapping configuration
 */
function get_form_31_field_mapping() {
    return [
        'url_params' => [
            'method' => 1,        // Method selection field ID
            'level' => 5,         // Level field ID
            'sector_level' => 4,  // Sector.Level field ID
            'scope' => 3,  
            'center' => 9,        // Scope field ID
        ],
      
        'workflow' => [
            'methods' => [
                '188.1' => 'ET', '188.2' => 'MT', '188.3' => 'PT', '188.4' => 'UT',
                '188.5' => 'RT', '188.6' => 'VT', '188.7' => 'TT', '188.8' => 'PAUT', '188.9' => 'TOFD'
            ],
            'exam_order_no' => '12',
            'candidate_name' => '19',
            'user_email' => '12',
            'prefer_center' => '9'
        ]
    ];
}

/**
 * Check if current page should load Form 31
 * Determines if Form 31 functionality should be active
 * 
 * @return bool True if Form 31 should be loaded
 */
function should_load_form_31() {
    // Check URL parameters that indicate Form 31 usage
    $renewal_indicators = [
        isset($_GET['renew_method']) && in_array($_GET['renew_method'], ['cpd', 'exam']),
        isset($_GET['type']) && $_GET['type'] === 'renewal',
        isset($_GET['cert_id']) && isset($_GET['method']),
        is_page() && (strpos(get_query_var('pagename'), 'renew') !== false || strpos(get_query_var('pagename'), 'recert') !== false)
    ];
    
    return array_filter($renewal_indicators) ? true : false;
}

/**
 * Log Form 31 activity for debugging
 * 
 * @param string $message Log message
 * @param array $context Additional context data
 */
function log_form_31_activity($message, $context = []) {
    if (WP_DEBUG && WP_DEBUG_LOG) {
        $log_entry = sprintf(
            '[Form 31] %s | Context: %s',
            $message,
            !empty($context) ? json_encode($context) : 'none'
        );
        error_log($log_entry);
    }
}