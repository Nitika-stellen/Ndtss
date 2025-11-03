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


    return $result;
}







