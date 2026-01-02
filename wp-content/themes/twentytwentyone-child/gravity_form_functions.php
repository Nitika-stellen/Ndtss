<?php
add_filter('gform_field_validation', 'validate_custom_fields', 10, 4);

function validate_custom_fields($result, $value, $form, $field) {
    global $wpdb;
    // If the value is an array, convert it to a string
    if (is_array($value)) {
        $value = implode('', $value);  // Join array elements into a single string
    }

    // First, check if the field is empty
    // if (empty($value)) {
    //     $result['is_valid'] = false;
    //     $result['message'] = 'This field is required.';
    //     return $result; // Return early if the field is empty
    // }
 
    // Alphabetic validation (letters only)
   if (preg_match('/\balphabetic-only\b/', $field->cssClass)) {
    if (!preg_match("/^[a-zA-Z\s]+$/", $value)) {
        $result['is_valid'] = false;
        $result['message'] = 'This field must contain only letters.';
    }
}

    
    // Alphanumeric validation (letters and numbers only)
    if (preg_match('/\balphanumeric-only\b/', $field->cssClass)) {
        if (!preg_match("/^[a-zA-Z0-9]+$/", $value)) {
            $result['is_valid'] = false;
            $result['message'] = 'This field must contain only letters and numbers.';
        }
    }

    // Numeric-only validation (numbers only)
    if (preg_match('/\bnumeric-only\b/', $field->cssClass)) {
        if (!preg_match("/^[0-9]+$/", $value)) {
            $result['is_valid'] = false;
            $result['message'] = 'This field must contain only numbers.';
        }
    }

  if (strpos($field->cssClass, 'dob-only') !== false) {
        $value = rgpost("input_{$field->id}");
        $value = trim($value);
    
        try {
            error_log("Processing DOB: " . $value);
    
            $dob = DateTime::createFromFormat('d/m/Y', $value);
            $errors = DateTime::getLastErrors();
    
            if (!$dob || $errors['warning_count'] > 0 || $errors['error_count'] > 0) {
                $result['is_valid'] = false;
                $result['message'] = 'Invalid date format. Please use DD/MM/YYYY.';
                error_log("Validation failed: Invalid date format - $value");
                return $result;
            }
    
            $today = new DateTime();
    
            if ($dob > $today) {
                $result['is_valid'] = false;
                $result['message'] = 'Date of birth cannot be in the future.';
                error_log("Validation failed: DOB is in the future.");
               return $result;
            }
    
            $age = $today->diff($dob)->y;
            $minimum_age = 18;
    
            if ($age < $minimum_age) {
                $result['is_valid'] = false;
                $result['message'] = "You must be at least $minimum_age years old.";
                error_log("Validation failed: Age is $age (Minimum required: $minimum_age)");
                return $result;
            }
    
        } catch (Exception $e) {
            $result['is_valid'] = false;
            $result['message'] = 'An error occurred while validating your date of birth.';
            error_log("Exception: " . $e->getMessage());
            return $result;
        }
    }

    // Validate Lifetime Membership eligibility (Form 5, Field 54)
    if ((int) $form['id'] === 5 && (int) $field->id === 54) {
        $selected_product = is_array($value) ? (isset($value[0]) ? $value[0] : '') : $value;
        
        // Check if Lifetime option is selected
        if (!empty($selected_product) && strpos($selected_product, 'Lifetime') !== false) {
            // Get Date of Birth (Field 43)
            $dob_value = rgpost('input_43');
            $dob_value = trim($dob_value);
            
            // Get Classification (Field 24)
            $classification_value = rgpost('input_24');
            if (is_array($classification_value)) {
                $classification_value = isset($classification_value[0]) ? $classification_value[0] : '';
            }
            $classification_value = trim($classification_value);
            
            // Check if user is a Fellow
            $is_fellow = (!empty($classification_value) && strtolower($classification_value) === 'fellow');
            
            // Validate eligibility
            if (!$is_fellow) {
                $result['is_valid'] = false;
                $result['message'] = 'Lifetime Membership is only available for Fellows.';
                error_log("Lifetime validation failed: User is not a Fellow. Classification: " . $classification_value);
                return $result;
            }
            
            if (empty($dob_value)) {
                $result['is_valid'] = false;
                $result['message'] = 'Date of Birth is required to validate Lifetime Membership eligibility.';
                error_log("Lifetime validation failed: Date of Birth is missing.");
                return $result;
            }
            
            // Validate date format and calculate age
            try {
                $dob = DateTime::createFromFormat('d/m/Y', $dob_value);
                $errors = DateTime::getLastErrors();
                
                if (!$dob || $errors['warning_count'] > 0 || $errors['error_count'] > 0) {
                    $result['is_valid'] = false;
                    $result['message'] = 'Invalid Date of Birth format. Please use DD/MM/YYYY.';
                    error_log("Lifetime validation failed: Invalid DOB format - " . $dob_value);
                    return $result;
                }
                
                $today = new DateTime();
                
                if ($dob > $today) {
                    $result['is_valid'] = false;
                    $result['message'] = 'Date of Birth cannot be in the future.';
                    error_log("Lifetime validation failed: DOB is in the future.");
                    return $result;
                }
                
                $age = $today->diff($dob)->y;
                
                // Check if user is 50 or older
                if ($age < 50) {
                    $result['is_valid'] = false;
                    $result['message'] = 'Lifetime Membership is only available for Fellows aged 50 or older. Your age is ' . $age . ' years.';
                    error_log("Lifetime validation failed: Age is $age (Minimum required: 50)");
                    return $result;
                }
                
                // Validation passed - user is eligible
                // Explicitly mark as valid to override any Gravity Forms default validation
                $result['is_valid'] = true;
                $result['message'] = '';
                error_log("Lifetime validation passed: Fellow, Age $age");
                return $result;
                
            } catch (Exception $e) {
                $result['is_valid'] = false;
                $result['message'] = 'An error occurred while validating your Lifetime Membership eligibility.';
                error_log("Lifetime validation exception: " . $e->getMessage());
                return $result;
            }
        }
    }

    return $result;
}

/**
 * Add Lifetime Membership option to Form 5 Product Field (Field 54) when user is eligible
 * This ensures Gravity Forms recognizes it as a valid choice
 */
add_filter('gform_pre_render_5', 'add_lifetime_membership_option');
add_filter('gform_pre_validation_5', 'add_lifetime_membership_option');
add_filter('gform_pre_submission_filter_5', 'add_lifetime_membership_option');

function add_lifetime_membership_option($form) {
    if ((int) $form['id'] !== 5) {
        return $form;
    }
    
    // Check if user is eligible for lifetime membership
    $dob_value = isset($_POST['input_43']) ? trim($_POST['input_43']) : '';
    $classification_value = isset($_POST['input_24']) ? trim($_POST['input_24']) : '';
    
    // If not in POST, try to get from entry or previous values
    if (empty($dob_value) || empty($classification_value)) {
        // This might be initial render, so we'll let JavaScript handle it
        return $form;
    }
    
    // Check if user is a Fellow
    $is_fellow = (!empty($classification_value) && strtolower($classification_value) === 'fellow');
    
    if (!$is_fellow || empty($dob_value)) {
        return $form;
    }
    
    // Calculate age
    try {
        $dob = DateTime::createFromFormat('d/m/Y', $dob_value);
        if (!$dob) {
            return $form;
        }
        
        $today = new DateTime();
        $age = $today->diff($dob)->y;
        
        // If eligible (50+), remove all existing lifetime options and let JavaScript handle it
        // We don't add it server-side to avoid duplicates - JavaScript will add it client-side
        if ($age >= 50) {
            foreach ($form['fields'] as &$field) {
                if ((int) $field->id === 54 && $field->type === 'product') {
                    // Remove any existing lifetime options to prevent duplicates
                    if (isset($field->choices) && is_array($field->choices)) {
                        $field->choices = array_filter($field->choices, function($choice) {
                            if (!isset($choice['value'])) {
                                return true;
                            }
                            // Remove any choice that contains "Lifetime" in value or text
                            $value = isset($choice['value']) ? strtolower($choice['value']) : '';
                            $text = isset($choice['text']) ? strtolower($choice['text']) : '';
                            return (strpos($value, 'lifetime') === false && strpos($text, 'lifetime') === false);
                        });
                        // Re-index array after filtering
                        $field->choices = array_values($field->choices);
                    }
                    break;
                }
            }
        }
    } catch (Exception $e) {
        error_log('Error adding lifetime option: ' . $e->getMessage());
    }
    
    return $form;
}



