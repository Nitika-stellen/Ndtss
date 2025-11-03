<?php
// If Gravity Forms isn't loaded, bail.
if ( ! class_exists( 'GFForms' ) ) {
	die();
}
/**
 * Class GF_Field_Phone
 *
 * Handles the behavior of Phone fields.
 *
 * @since Unknown
 */
class Superaddons_GFRepeater_Start_Field extends GF_Field {
	/**
	 * Defines the field type.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @var string The field type.
	 */
	public $type = 'repeater_start';
	/**
	 * Defines the field title to be used in the form editor.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFCommon::get_field_type_title()
	 *
	 * @return string The field title. Translatable and escaped.
	 */
	public function get_form_editor_field_title() {
		return esc_attr__('Repeater Start', 'repeater-for-gravity-forms' );
	}
	public function get_form_editor_button() {
	    return array(
	        'group' => 'advanced_fields',
	        'text'  => $this->get_form_editor_field_title()
	    );
	}
	/**
	 * Defines the field settings available within the field editor.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return array The field settings available for the field.
	 */
	function get_form_editor_field_settings() {
		return array(
			'prepopulate_field_setting',
			'label_setting',
			'field_field_repeater_title_setting',
			'css_class_setting',
		);
	}
	/**
	 * Defines if conditional logic is supported in this field type.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormDetail::inline_scripts()
	 * @used-by GFFormSettings::output_field_scripts()
	 *
	 * @return bool true
	 */
	public function is_conditional_logic_supported() {
		return true;
	}
	/**
	 * Returns the field input.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFCommon::get_field_input()
	 * @uses    GF_Field::is_entry_detail()
	 * @uses    GF_Field::is_form_editor()
	 * @uses    GF_Field_Phone::$failed_validation
	 * @uses    GF_Field_Phone::get_phone_format()
	 * @uses    GFFormsModel::is_html5_enabled()
	 * @uses    GF_Field::get_field_placeholder_attribute()
	 * @uses    GF_Field_Phone::$isRequired
	 * @uses    GF_Field::get_tabindex()
	 *
	 * @param array      $form  The Form Object.
	 * @param string     $value The value of the input. Defaults to empty string.
	 * @param null|array $entry The Entry Object. Defaults to null.
	 *
	 * @return string The HTML markup for the field.
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		if ( is_array( $value ) ) {
			$value = '';
		}
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$form_id  = $form['id'];
		$id       = intval( $this->id );
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
		$size          = $this->size;
		$disabled_text = $is_form_editor ? "disabled='disabled'" : '';
		$class_suffix  = $is_entry_detail ? '_admin' : '';
		$class         = $size . $class_suffix. " hidden";
		$class         = esc_attr( $class );
		$repeater_title = $this->repeater_title;
		$text_input = '<div class="repeater-field-header">
				<div class="repeater-field-header-title">'.$repeater_title." ";
				 	$text_input .= '<span class="repeater-field-header-count">1</span>';
				 	$text_input .= '</div>
				<div class="repeater-field-header-acctions">
					<ul>
						<li><i class="repeater-icon icon-down-open repeater-field-header-acctions-toogle" aria-hidden="true"></i></li>
						<li><i class="repeater-icon icon-cancel-1 repeater-field-header-acctions-remove" aria-hidden="true"></i></li>
					</ul>
				</div>
			</div>';
		$text_input = htmlentities($text_input);
		$html = '<div class="wpforms-field-repeater-start">
			<input type="hidden" class="repeater-field-header-data hidden" value="'.$text_input.'" />
		</div>';
		
		if (is_admin()) { 
			$html = '<hr>Begin Repeater<hr>';
			return sprintf( "<div class='ginput_container1'>%s</div>", $html);
		}else{
			return sprintf( "<div class='ginput_container ginput_container_text'> %s </div>", $html);
		}
	}
	/**
	 * Gets the value of the submitted field.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormsModel::get_field_value()
	 * @uses    GF_Field::get_value_submission()
	 * @uses    GF_Field_Phone::sanitize_entry_value()
	 *
	 * @param array $field_values             The dynamic population parameter names with their corresponding values to be populated.
	 * @param bool  $get_from_post_global_var Whether to get the value from the $_POST array as opposed to $field_values. Defaults to true.
	 *
	 * @return array|string
	 */
	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {
		return false;
	}
	/**
	 * Sanitizes the entry value.
	 *
	 * @since Unknown
	 * @access public
	 *
	 * @used-by GF_Field_Phone::get_value_save_entry()
	 * @used-by GF_Field_Phone::get_value_submission()
	 *
	 * @param string $value   The value to be sanitized.
	 * @param int    $form_id The form ID of the submitted item.
	 *
	 * @return string The sanitized value.
	 */
	public function sanitize_entry_value( $value, $form_id ) {
		return false;
	}
	/**
	 * Gets the field value when an entry is being saved.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFormsModel::prepare_value()
	 * @uses    GF_Field_Phone::sanitize_entry_value()
	 * @uses    GF_Field_Phone::$phoneFormat
	 *
	 * @param string $value      The input value.
	 * @param array  $form       The Form Object.
	 * @param string $input_name The input name.
	 * @param int    $lead_id    The Entry ID.
	 * @param array  $lead       The Entry Object.
	 *
	 * @return string The field value.
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		return false;
	}
	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		return false;
	}
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		return false;
	}
}
// Register the phone field with the field framework.
GF_Fields::register( new Superaddons_GFRepeater_Start_Field() );
