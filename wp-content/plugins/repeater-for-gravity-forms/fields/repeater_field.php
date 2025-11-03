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
class Superaddons_GFRepeater_Field extends GF_Field {
	private $lead_id = null;
	/**
	 * Defines the field type.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @var string The field type.
	 */
	public $type = 'repeater_end';
	public $list_fields_validate = array();
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
		return esc_attr__('Repeater end', 'repeater-for-gravity-forms' );
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
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'label_setting',
			'field_field_repeater_end_text_setting',
			'field_field_repeater_initial_rows_setting',
			'field_field_repeater_max_setting',
			'label_placement_setting',
			'admin_label_setting',
			'size_setting',
			'rules_setting',
			'visibility_setting',
			'duplicate_setting',
			'placeholder_setting',
			'description_setting',
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
		return false;
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
		$class         = $size . $class_suffix. " gf-field-repeater-data hidden";
		$class         = esc_attr( $class );
		$instruction_div = '';
		$html_input_type       = 'hidden';
		$placeholder_attribute = $this->get_field_placeholder_attribute();
		$required_attribute    = $this->isRequired ? 'aria-required="true"' : '';
		$invalid_attribute     = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';
		$aria_describedby      = $this->get_aria_describedby();
		$tabindex = $this->get_tabindex();
		$repeater_add_button = $this->field_repeater_end_text;
		if($repeater_add_button == ""){
			$repeater_add_button = esc_attr__("Add more","gravityforms-repeater");
		}
		$initial_rows = $this->repeater_initial_rows;
		if( $initial_rows == ""){
			$initial_rows = 1;
		}
		$limit = $this->repeater_max;
		if( $limit == "" or $limit < 1){
			$limit = 9999;
		}
		$limit = apply_filters( "yeeaddons_gf_repeater_limit", 5, $limit);
		$initial_rows = apply_filters( "yeeaddons_gf_repeater_initial_rows", 1, $initial_rows);
		$input = "<input data-initial_rows_map_check='".$this->repeater_initial_rows_map."' data-initial_rows_map='input_{$form_id}_".$this->repeater_initial_rows_map."' data-map_id='field_".$form_id."_".$id."' name='input_{$id}' id='{$field_id}' type='{$html_input_type}' value='{$value}' class='{$class}' {$tabindex} {$placeholder_attribute} {$required_attribute} {$invalid_attribute} {$disabled_text}/>";
		$html ='<div data-initial_rows_map_check="'.$this->repeater_initial_rows_map.'" class="repeater-field-warp-item-data" data-initial_rows="'.$initial_rows.'" data-limit="'.$limit.'" data-initial_rows_map="input_'.$form_id.'_'.$this->repeater_initial_rows_map.'" data-map_id="field_'.$form_id.'_'.$id.'">
			<div class="repeater-field-warp-item">
			</div>
			<div class="repeater-field-footer"><a href="#"" class="gf-repeater-field-button-add" >'.$repeater_add_button.'</a></div>
			'.$input.'
			<input type="hidden" class="gf-field-repeater-data-html hidden" />
		</div>';
		if (is_admin()) { 
			$html = '<hr>End Repeater<hr>';
			return sprintf( "<div class='ginput_container1'>%s</div>", $html);
		}else{
			return sprintf( "<div class='ginput_container'>%s</div>", $html);
		}
	}
	public static function remove_validation($form) {
		$zo = false;
		$zo_datas = array();
		foreach( $form["fields"] as $field ){
				$type = $field->type;
				if( $type == "repeater_start"){
					$zo = true;
					continue;
				}
				if( $type == "repeater_end"){
					$zo = false;
					continue;
				}
				if( $zo  ){	
					if( !isset($field->repeater_validate )) {
						$field->repeater_validate = array("isRequired"=>$field->isRequired);
					}
					$field->isRequired = false;
				}		
		}
		return $form;
	}
	function validate($value, $form) { 
		$datas = json_decode($value, true);
		$list_fields_validate = array();
		$failedValidation = false;
		foreach( $form["fields"] as $field  ){
			if( is_array($field->repeater_validate)) {
				foreach( $field->repeater_validate as $k=>$v ){
					if($k=="isRequired" && $v == true) {
						$list_fields_validate["input_".$field->id] = $field->type;
					}
				}
			}
		}
		if( isset($datas["id"]) && is_array($datas["id"])) {
			foreach( $datas["id"] as $id  ){ 	
				foreach( $datas["fields"] as $field ){
					if($field == ""){
						continue;
					}
					$field = str_replace('[]', '', $field);
					if( isset($list_fields_validate[$field]) ){
						$getInputData = rgpost($field."__".$id);
						switch($list_fields_validate[$field]){
							case "name":
								$first_name = rgpost($field."_3__".$id);
								$last_name = rgpost($field."_6__".$id);
								if ( empty($first_name) ||  empty($last_name) ) { 
									$failedValidation = true; 
								}
								break;
							case 'address':
								$address = rgpost($field."_1__".$id);
								if (  empty($address) ) { 
									$failedValidation = true; 
								}
								break;
							default:
								if(is_array($getInputData)){
									foreach($getInputData as $vl){
										if (empty($vl)) { $failedValidation = true; }
									}
								}else{
									if (empty($getInputData)) { $failedValidation = true; }
								}
								break;
						}
					}
				}
			}
			if ($failedValidation) {
				$this->failed_validation  = true;
				if ($this->errorMessage) { $this->validation_message = $this->errorMessage; } else { $this->validation_message = "This field is required."; }
				return;
			}else{
				$this->failed_validation  = false;
			}
		}
	}
	function custom_validation($form){
		$dataRepeater = array();
		$zo = false;
		$zo_datas = array();
		foreach( $form["fields"] as $field ){
				$type = $field->type;
				if( $type == "repeater_start"){
					$zo = true;
					continue;
				}
				if( $zo  ){
					$zo_datas[$field->id] = array("isRequired"=>$field->isRequired);
					$field->isRequired = false;
				}
				if( $type == "repeater_end"){
					$zo = false;
					$zo_datas = array();
					continue;
				}
		}
		return $form;
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
		$value = parent::get_value_submission( $field_values, $get_from_post_global_var );
		$value = $this->sanitize_entry_value( $value, $this->formId );
		return $value;
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
		$value = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( $value );
		return $value;
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
		$this->lead_id = $lead_id;
		$datas = json_decode($value, true);
		//var_dump($value);
		$values = array();
		$form_id = $this->formId;
		$get_form = GFFormsModel::get_form_meta_by_id($form_id);
		$form = $get_form[0];
		$fields = array();
		foreach ( $datas["fields"] as $f ){
			$datas_f = explode(".",$f);
			$fields[] = $datas_f[0];
		}
		$fields = array_unique($fields);
		foreach( $datas["id"] as $id_rand){
			$datas_step = array();
			foreach( $fields as $field ){
				$field = str_replace("[]", "", $field);
				$inputs =$this->get_inputs($form,$field);
				if( is_array($inputs)) {
					$getInputData = array();
					foreach( $inputs as $child_input ){
						$getInputName = "input_".$child_input["id"]."__".$id_rand;
						$vl = rgpost( str_replace('.', '_', strval($getInputName)) );
						if($vl != ""){
							$getInputData[$child_input["id"]] = $vl;
						}
					}
					$datas_step[$getInputName] = $getInputData;
				}else{
					$getInputName = $field."__".$id_rand;
					if(isset($_FILES[ $getInputName ])){
						$datas_step[$getInputName] = apply_filters( "yeeaddons_gf_repeater_file", "Upgrade to pro version", $form_id, $_FILES[ $getInputName ]);
					}else if( isset($_POST[ $getInputName ]) ){
						$datas_step[$getInputName] = rgpost( str_replace('.', '_', strval($getInputName)) );
					}
					else{
						$getInputData = rgpost( str_replace('.', '_', strval($getInputName)) );
						$datas_step[$getInputName] = $getInputData;
					}
				}
			}
			$values[$id_rand] = $datas_step;
		}
		return maybe_serialize($values);
	}
	public function upload_file( $form_id, $file ) {
		$target = GFFormsModel::get_file_upload_path( $form_id, $file['name'] );
		if ( ! $target ) {
			return 'FAILED (Upload folder could not be created.)';
		}
		if ( move_uploaded_file( $file['tmp_name'], $target['path'] ) ) {
			$this->set_permissions( $target['path'] );
			return $target['url'];
		} else {
			return 'FAILED (Temporary file could not be copied.)';
		}
	}
	function set_permissions( $path ) {
		GFFormsModel::set_permissions( $path );
	}
	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		if (empty($value)) {
			return '';
		} else {
			$dataArray = GFFormsModel::unserialize($value);
			$arrayCount = count($dataArray);
			if ($arrayCount > 1) { $returnText = $arrayCount.' entries'; } else { $returnText = $arrayCount.' entry'; }
			return $returnText;
		}
	}
	function get_datas_field($format="html",$value='',$form_id=""){
		global $wpdb;
		$dataArray = GFFormsModel::unserialize($value);
		$get_form = GFFormsModel::get_form_meta_by_id($form_id);
		$form = $get_form[0];
		if(isset($_GET['lid'])){
			$result = GFAPI::get_entry( $_GET['lid'] );
		}else{
			$table = $wpdb->prefix."gf_entry";
			$mylink = $wpdb->get_row( "SELECT id FROM $table ORDER BY id DESC" );
			$result = GFAPI::get_entry( $mylink->id );
		}
		if ( $format === 'html' ) {
			$html = '<ol>';
			foreach( $dataArray as $step_datas ){
				$html .= '<li><ul>';
				foreach( $step_datas as $name=>$vl ){
					$type = $this->get_type($form,$name,$form_id);
					$lb = $this->get_field_label($form, $name, $type,false,$form_id);
					if( is_array($vl)){
						switch($type ){
							case "address":
								$vl_data = "";
								foreach( $vl as $k => $v ){
									$child_lb = $this->get_field_label($form, "input_".$k, $type,true);
									$vl_data .= $child_lb.": ".$v ."<br>";
								}
								$html .= '<li>'.$lb.": <br>". $vl_data."</li>";
								break;
							default:
								$vl_data = implode(", ",$vl);
								$html .= '<li>'.$lb.": ". $vl_data."</li>";
								break;
						}
					}else{
						$vl_data = $vl;
						switch($type ){ 
							case "fileupload":
								if( $vl_data == "Upgrade to pro version") {
									$html .= '<li>'.$lb.": ". $vl_data."</li>";
								}else{
									if (filter_var($vl_data, FILTER_VALIDATE_URL) === FALSE) {
									    $content= array();
									    $main_name = explode("__",$name);
									    $main_name = explode("_",$main_name[0]);
									    if(isset($main_name[4])){
									    	$main_name_id = $main_name[4];
									    }else{
									    	$main_name_id = $main_name[1];
									    }
									    $vl_data = explode(",",$vl_data);
									    if(isset($result[$main_name_id])){
									    	$data_uploads = json_decode($result[$main_name_id],true);
									    	foreach( $vl_data as $n ){
									    		if ( version_compare( phpversion(), '7.4', '<' ) && get_magic_quotes_gpc() ) {
													$n = stripslashes( $n );
												}
									    		$n = sanitize_file_name( $n );
									    		foreach( $data_uploads as $name ){
									    			$name_s = explode(".",$n);
									    			$name_s = $name_s[0];
										    		$re = "/".$name_s."\.|".$name_s."[\d]\./";
										    		if (preg_match($re, $name) ){
										    			$content[] = '<a href="'.$name.'" download>'. $n."</a> ";
										    			break;
										    		}
										    	}
									    	}
									    }
									    $html .= '<li>'.$lb.': '.implode(" | ",$content)."</li>";
									}else{
										$html .= '<li>'.$lb.': <a href="'.$vl_data.'" download>'. $vl_data."</a></li>";
									}
								}
								break;
							default:
								$html .= '<li>'.$lb.": ". $vl_data."</li>";
								break;
						}
					}
				}
				$html .= '</ul></li>';
			}
			$html .= '<ol>';
			$html = apply_filters( "yeeadons_gravity_forms_repeater_html",$html,$dataArray, $form);
			return $html;
		}else{
			$html = '';
			foreach( $dataArray as $step_datas ){
				foreach( $step_datas as $name=>$vl ){
					$type = $this->get_type($form,$name,$form_id);
					$lb = $this->get_field_label($form, $name, $type,false,$form_id);
					if( is_array($vl)){
						switch($type ){
							case "address":
								$vl_data = "";
								foreach( $vl as $k => $v ){
									$child_lb = $this->get_field_label($form, "input_".$k, $type,true);
									$vl_data .= $child_lb.": ".$v ."\n";
								}
								$html .= $lb." : ". $vl_data."\n";
								break;
							default:
								$vl_data = implode(", ",$vl);
								$html .= $lb.": ". $vl_data."\n";
								break;
						}
					}else{
						$vl_data = $vl;
						switch($type ){ 
							case "fileupload":
								if (filter_var($vl_data, FILTER_VALIDATE_URL) === FALSE) {
									$content= array();
									$main_name = explode("__",$name);
									$main_name = explode("_",$main_name[0]);
									$main_name_id = $main_name[4];
									$vl_data = explode(",",$vl_data);
									if(isset($result[$main_name_id])){
										$data_uploads = json_decode($result[$main_name_id],true);
										foreach( $vl_data as $n ){
											if ( version_compare( phpversion(), '7.4', '<' ) && get_magic_quotes_gpc() ) {
												$n = stripslashes( $n );
											}
											$n = sanitize_file_name( $n );
											foreach( $data_uploads as $name ){
												$name_s = explode(".",$n);
												$name_s = $name_s[0];
												$re = "/".$name_s."\.|".$name_s."[\d]\./";
												if (preg_match($re, $name) ){
													$content[] = '<a href="'.$name.'" download>'. $n."</a> ";
													break;
												}
											}
										}
									}
									$html .= $lb.': '.implode(" | ",$content)."\n";
								}else{
									$html .= $lb.':'.$vl_data."\n";
								}
								break;
							default:
								$html .= $lb.": ". $vl_data."\n";
								break;
						}
					}
				}
				$html .= "\n";
			}
			$html = apply_filters( "yeeadons_gravity_forms_repeater_text",$html,$dataArray, $form);
			return $html;
		}
	}
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		//Export doesn’t require encoding, but field data may require some manipulation or formatting before it is exported
		$form_id = $entry["form_id"];
		$return = $this->get_datas_field("text",rgar( $entry, $input_id ),$form_id);
		return $return;
    }
	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		if (empty($value)) {
			return '';
		}
		$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : null;
		$return = $this->get_datas_field($format,$value,$form_id);
		return $return;
	}
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		global $wpdb;
		if (empty($value)) {
			return '';
		}else{
			$form_id = $this->formId;
			$return = $this->get_datas_field($format,$value,$form_id);
			return $return;
		}
	}
	function get_field_label($form, $name = '', $type = '', $child = false, $form_id ='') {
		if (is_array($form)) {
			if (!array_key_exists('fields', $form)) { return false; }
		} else { return false; }
		$name = str_replace("gform_multifile_upload_".$form_id, "input", $name);
		$names = explode("__",$name);
		$names = explode("_",$names[0]);
		$name = $names[1];
		foreach ($form['fields'] as $field_key=>$field_value) {
			if( is_array($field_value->inputs)){
				foreach( $field_value->inputs  as $children_id ){
					if( $children_id["id"] == $name ) {
						if( $child ){
							return $children_id["label"];
						}else{
							return $field_value->label;
						}
					}
				}
			}else{
				if( $field_value->id == $name ) {
					return $field_value->label;
				}
			}
		}
		return "";
	}
	function get_inputs($form, $name = '') {
		if (is_array($form)) {
			if (!array_key_exists('fields', $form)) { return false; }
		} else { return false; }
		$names = explode("__",$name);
		$names = explode("_",$names[0]);
		$name = $names[1];
		foreach ($form['fields'] as $field_key=>$field_value) {
			if( $field_value->id == $name ) { 
				if($field_value->type == "date"){
					return $name;
				}
				if( is_array($field_value->inputs)){
					return $field_value->inputs;
				}
			}	
		}
		return false;
	}
	function get_type($form, $name = '',$form_id ="") {
		if (is_array($form)) {
			if (!array_key_exists('fields', $form)) { return false; }
		} else { return false; }
		$name = str_replace("gform_multifile_upload_".$form_id, "input", $name);
		$names = explode("__",$name);
		$names = explode("_",$names[0]);
		$name = $names[1];
		foreach ($form['fields'] as $field_key=>$field_value) {
			if( is_array($field_value->inputs)){
				foreach( $field_value->inputs  as $children_id ){
					if( $children_id["id"] == $name ) {
						return $field_value->type;
					}
				}
			}else{
				if( $field_value->id == $name ) {
					return $field_value->type;
				}
			}
		}
		return "";
	}
}
// Register the phone field with the field framework.
GF_Fields::register( new Superaddons_GFRepeater_Field() );