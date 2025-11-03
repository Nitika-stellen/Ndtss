<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://#
 * @since      1.0.0
 *
 * @package    Stellen_Gravity
 * @subpackage Stellen_Gravity/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Stellen_Gravity
 * @subpackage Stellen_Gravity/admin
 * @author     vikas <vikas@stelleninfotech.in>
 */
class Stellen_Gravity_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	private $countryTable;
	private $stateTable;
	private $cityTable;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		global $wpdb;
		$this->plugin_name = $plugin_name;
		$this->version = $version;

		$this->countryTable = $wpdb->prefix . 'stellen_countries';
		$this->stateTable = $wpdb->prefix . 'stellen_states';
		$this->cityTable = $wpdb->prefix . 'stellen_cities';


	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Stellen_Gravity_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Stellen_Gravity_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/stellen-gravity-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Stellen_Gravity_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Stellen_Gravity_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/stellen-gravity-admin.js', array( 'jquery' ), $this->version, false );

	}



public function populate_country_dropdown_class($form) {

	global $wpdb;

	foreach ($form['fields'] as &$field) {
		if ($field->type !== 'select') continue;

		// Handle gf-country-code
		if (strpos($field->cssClass, 'gf-country-code') !== false) {
			$results = $wpdb->get_results("SELECT dial_code, name FROM {$this->countryTable} ORDER BY name ASC");
			$choices = [];

			foreach ($results as $row) {
				$is_singapore = strtolower($row->name) === 'singapore';

				$choices[] = [
					'text'       => $row->name . " (" . $row->dial_code . ")",
					'value'      => $row->dial_code,
					'isSelected' => $is_singapore,
				];
			}

			$field->choices = $choices; // ✅ Set choices here
		}

		// Handle gf-country
		if (strpos($field->cssClass, 'gf-country') !== false && strpos($field->cssClass, 'gf-country-code') === false) {
			$results = $wpdb->get_results("SELECT name FROM {$this->countryTable} ORDER BY name ASC");
			$choices = [];

			foreach ($results as $row) {
				$is_singapore = strtolower($row->name) === 'singapore';

				$choices[] = [
					'text'       => $row->name,
					'value'      => $row->name,
					'isSelected' => $is_singapore,
				];
			}

			$field->choices = $choices; // ✅ Set choices here
		}
	}

	return $form;
}



/**
 * Validate phone number based on country code.
 *
 * @param array $result Validation result.
 * @param string $value Field value.
 * @param array $form Form data.
 * @param object $field Field object.
 * @return array Updated validation result.
 */

// public function validate_phone_by_country_code($result, $value, $form, $field) {
//     global $wpdb;

//     // Only apply to phone fields with the right class
//     if (strpos($field->cssClass, 'gf-phone-number') === false) {
//         return $result;
//     }

//     // Get selected country code value
//     $country_field_id = $this->get_input_id_by_class($form, 'gf-country-code');

//     $country_code = rgpost("input_{$country_field_id}");

//     $digits_only = preg_replace('/\D/', '', $value);

//     if (!$country_code || !$digits_only) {
//         $result['is_valid'] = false;
//         $result['message'] = 'Please select a country code and enter a valid phone number.';
//         return $result;
//     }

//     $row = $wpdb->get_row($wpdb->prepare(
//         "SELECT phone_length FROM $this->countryTable WHERE dial_code = %s",
//         $country_code
//     ));

//     if ($row && strlen($digits_only) != (int)$row->phone_length) {
//         $result['is_valid'] = false;
//         $result['message'] = "Phone number must be {$row->phone_length} digits for the selected country.";
//     }

//     return $result;
// }

public function validate_phone_by_country_code($result, $value, $form, $field) {
    global $wpdb;

    // Only apply validation to fields with the gf-phone-number class
    if (strpos($field->cssClass, 'gf-phone-number') === false) {
        return $result;
    }

    // Try to find the matching country code field based on the order
    $phone_field_index = $this->get_field_index_by_class($form, $field->id, 'gf-phone-number');
    $country_field_id = $this->get_field_id_by_index_and_class($form, $phone_field_index, 'gf-country-code');

    if (!$country_field_id) {
        return $result; // No matching country field found
    }

    $country_code = rgpost("input_{$country_field_id}");
    $digits_only = preg_replace('/\D/', '', $value);

    if (!$country_code || !$digits_only) {
        $result['is_valid'] = false;
        $result['message'] = 'Please select a country code and enter a valid phone number.';
        return $result;
    }

    // Query your country table (assumed to exist and contain dial_code and phone_length)
    $table = esc_sql($this->countryTable); // Prevent injection
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT phone_length FROM $table WHERE dial_code = %s",
        $country_code
    ));

    if ($row && strlen($digits_only) != (int) $row->phone_length) {
        $result['is_valid'] = false;
        $result['message'] = "Phone number must be {$row->phone_length} digits for the selected country.";
    }

    return $result;
}
// Get the index of the current phone field among all phone fields
private function get_field_index_by_class($form, $field_id, $class_name) {
    $index = 0;
    foreach ($form['fields'] as $field) {
        if (strpos($field->cssClass, $class_name) !== false) {
            if ($field->id == $field_id) {
                return $index;
            }
            $index++;
        }
    }
    return null;
}

// Get the nth country code field based on index
private function get_field_id_by_index_and_class($form, $target_index, $class_name) {
    $index = 0;
    foreach ($form['fields'] as $field) {
        if (strpos($field->cssClass, $class_name) !== false) {
            if ($index == $target_index) {
                return $field->id;
            }
            $index++;
        }
    }
    return null;
}






public function validate_state_field($result, $value, $form, $field) {
	global $wpdb;

	if (strpos($field->cssClass, 'gf-state') === false) {
		return $result;
	}

    if (empty($value) ) {
        $result['is_valid'] = false;
        $result['message'] = 'Please Select the state!';
        return $result;
    }
	$result['is_valid'] = true;

	return $result;
}


/**
 * Validate city and country fields based on country code.
 *
 * @param array $result Validation result.
 * @param string $value Field value.
 * @param array $form Form data.
 * @param object $field Field object.
 * @return array Updated validation result.
 */

public function validate_city_field($result, $value, $form, $field) {
	global $wpdb;

	if (strpos($field->cssClass, 'gf-city') === false) {
		return $result;
	}

    if (empty($value) ) {
        $result['is_valid'] = false;
        $result['message'] = 'Please Select the City!';
        return $result;
    }
	$result['is_valid'] = true;

	return $result;
}


/**
 * Restore selected dropdowns in Gravity Forms.
 *
 * @param array $form Form data.
 * @return array Updated form data.
 */
public function gf_restore_selected_dropdowns($form) {

	$country_field_id = $this->get_input_id_by_class($form, 'gf-country');
	$state_field_id   = $this->get_input_id_by_class($form, 'gf-state');
	$city_field_id    = $this->get_input_id_by_class($form, 'gf-city');
	$country = rgpost("input_{$country_field_id}");
	$state   = rgpost("input_{$state_field_id}");
	$city    = rgpost("input_{$city_field_id}");	
  
    echo "<script>
        window.gfDropdownSelected = {
            country: " . json_encode($country) . ",
            state: " . json_encode($state) . ",
            city: " . json_encode($city) . "
        };
    </script>";

    return $form;
}


/**
 * 
 *  Helper: get field ID by CSS class
 **/

public function get_input_id_by_class($form, $css_class) {
    foreach ($form['fields'] as $field) {
        if (strpos($field->cssClass, $css_class) !== false) {
            return $field->id;
        }
    }
    return null;
}

/**
 * AJAX handlers for getting country, state, and city IDs by name.
 *
 * @return void
 */
// Note: These functions are public to allow AJAX calls from both logged-in and guest users.



public function get_country_id_by_name() {
	global $wpdb;
	$name = sanitize_text_field($_GET['name']);
	$result = $wpdb->get_var($wpdb->prepare("SELECT id FROM $this->countryTable WHERE name = %s", $name));
	wp_send_json(['id' => $result]);
}


public function get_state_id_by_name() {
	global $wpdb;
	$name = sanitize_text_field($_GET['name']);
	$result = $wpdb->get_var($wpdb->prepare("SELECT id FROM $this->stateTable WHERE name = %s", $name));
	wp_send_json(['id' => $result]);
}


public function get_states() {
	global $wpdb;
	$country_id = intval($_GET['country_id']);
	$results = $wpdb->get_results($wpdb->prepare("SELECT id, name FROM $this->stateTable WHERE country_id = %d ORDER BY name ASC", $country_id));
	wp_send_json($results);
}


public function get_cities() {
	global $wpdb;
	$state_id = intval($_GET['state_id']);
	$results = $wpdb->get_results($wpdb->prepare("SELECT id, name FROM $this->cityTable WHERE state_id = %d ORDER BY name ASC", $state_id));
	wp_send_json($results);
}










}
