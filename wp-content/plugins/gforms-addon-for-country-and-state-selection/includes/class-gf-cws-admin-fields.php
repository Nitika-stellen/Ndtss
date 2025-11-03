<?php
// Ensure Gravity Forms and WordPress functions are available
if (!class_exists('GF_Field')) require_once(ABSPATH . 'wp-content/plugins/gravityforms/includes/fields/class-gf-field.php');
if (!function_exists('esc_attr__')) require_once(ABSPATH . 'wp-includes/l10n.php');
if (!class_exists('GFCommon')) require_once(ABSPATH . 'wp-content/plugins/gravityforms/common.php');
if (!function_exists('__')) require_once(ABSPATH . 'wp-includes/l10n.php');
if (!class_exists('GFForms')) die();

class GF_ADMIN_FIELDS_MODULE extends GF_Field {
    public $type = 'countrywisestate';

    public function get_form_editor_field_title() {
        return esc_attr__( 'Lookup', 'gfcws' );
    }
    public function get_form_editor_field_icon() {
        return 'gform-icon gform-icon--place';
    }
    public function get_form_editor_button() {
        return array(
            'group' => 'advanced_fields',
            'text'  => $this->get_form_editor_field_title(),
            'icon'  => $this->get_form_editor_field_icon(),
        );
    }
    function get_form_editor_field_settings() {
        return array(
            'label_setting',
            'description_setting',
            'input_placeholders_setting',
            'admin_label_setting',
            'label_placement_setting',
            'sub_label_placement_setting',
            'input_class_setting',
            'countrywisestate_setting',
            'css_class_setting',
            'visibility_setting',
            'conditional_logic_field_setting',
            'description_setting',
            'rules_setting',
            'prepopulate_field_setting',
        );
    }
    public function is_conditional_logic_supported() { return true; }
    public function get_required_inputs_ids() { return array( '1', '2'); }
    public function get_field_container_tag( $form ) {
        if ( GFCommon::is_legacy_markup_enabled( $form ) ) {
            return parent::get_field_container_tag( $form );
        }
        return 'fieldset';
    }
    function validate( $value, $form ) {
        $newval = array();
        foreach($value as $key => $data){
            $newval[$key] = ($data == 'No data') ? '' : sanitize_text_field($data);
        }
        if ( $this->isRequired ) {
            $message = $this->complex_validation_message( $newval, $this->get_required_inputs_ids() );
            if ( $message ) {
                $this->failed_validation  = true;
                $message_intro            = empty( $this->errorMessage ) ? __( 'This field is required.', 'gfcws' ) : $this->errorMessage;
                $this->validation_message = $message_intro . ' ' . $message;
            }
        }
    }
    public function get_conditional_logic_event_custom(){
        return "onchange='gf_apply_rules_addon(" . esc_attr($this->formId) . "," . GFCommon::json_encode( $this->conditionalLogicFields ) . ");'";
    }
    public function get_form_editor_inline_script_on_page_render() {
        $script = sprintf( "function SetDefaultValues_countrywisestate(field) {field.label = '%s';}", esc_js($this->get_form_editor_field_title()) ) . PHP_EOL;
        $script .= "jQuery(document).bind('gform_load_field_settings', function (event, field, form) {" .
                   "var inputClass = field.inputClass == undefined ? '' : field.inputClass;" .
                   "jQuery('#input_class_setting').val(inputClass);" .
                   "});" . PHP_EOL;
        $script .= "function SetInputClassSetting(value) {SetFieldProperty('inputClass', value);}" . PHP_EOL;
        return $script;
    }
    public function get_css_class() {
        $state_field_input  = GFFormsModel::get_input( $this, $this->id . '.1' );
        $country_field_input = GFFormsModel::get_input( $this, $this->id . '.2' );
        $css_class = '';
        if ( ! rgar( $state_field_input, 'isHidden' ) ) $css_class .= 'has_state ';
        if ( ! rgar( $country_field_input, 'isHidden' ) ) $css_class .= 'has_country ';
        $css_class .= 'ginput_container_address gform-grid-row';
        return trim( $css_class );
    }
    /**
     * Returns a country state fieldset.
     */
    public function get_field_input( $form, $value = '', $entry = null ) {
        $form_sub_label_placement  = rgar( $form, 'subLabelPlacement' );
        $id              = absint( $this->id );
        $form_id         = absint( $form['id'] );
        $is_entry_detail = $this->is_entry_detail();
        $is_form_editor  = $this->is_form_editor();
        $tabindex        = $this->get_tabindex();
        $is_admin        = $is_entry_detail || $is_form_editor;
        $css_class       = $this->get_css_class();
        $field_id        = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
        $disabled_text   = $is_form_editor ? "disabled='disabled'" : '';
        $class_suffix    = $is_entry_detail ? '_admin' : '';
        $country = '';
        $states = '';
        if ( is_array( $value ) ) {
            $country = esc_attr( RGForms::get( $this->id . '.1', $value ) );
            $states = esc_attr( RGForms::get( $this->id . '.2', $value ) );
        }
        $country_input = GFFormsModel::get_input( $this, $this->id . '.1' );
        $states_input = GFFormsModel::get_input( $this, $this->id . '.2' );
        $country_sub_label = rgar( $country_input, 'customLabel' ) != '' ? esc_html($country_input['customLabel']) : apply_filters( "gform_name_country_{$form_id}", apply_filters( 'gform_name_country', __( 'Country', 'gforms-addon-for-country-and-state-selection' ), $form_id ), $form_id );
        $states_sub_label = rgar( $states_input, 'customLabel' ) != '' ? esc_html($states_input['customLabel']) : apply_filters( "gform_name_states_{$form_id}", apply_filters( 'gform_name_states', __( 'State', 'gforms-addon-for-country-and-state-selection' ), $form_id ), $form_id );
        $hide_country = isset($this->hideCountry) && $this->hideCountry || rgar( $country_input, 'isHidden' );
        $field = GFFormsModel::get_field( $form, $id );
        $logic_event           = 'class="gfield_select"';
        $inputClass            = isset($this->inputClass) ? esc_attr($this->inputClass) : '';
        $disabled_select       = $is_form_editor ? 'disabled="disabled"' : '';
        $required_attribute    = "";
        $field_sub_label_placement = isset($this->subLabelPlacement) ? $this->subLabelPlacement : '';
        $sub_label_class_attribute = $field_sub_label_placement == 'hidden_label' ? "class='hidden_sub_label'" : 'class="gform-field-label gform-field-label--type-sub"';
        $is_sub_label_above        = $field_sub_label_placement == 'above' || ( empty( $field_sub_label_placement ) && $form_sub_label_placement == 'above' );
        // Load country/state data
        $csvFile = file(plugin_dir_path( __DIR__ ) . 'assets/data/states.csv');
        $datas = [];
        foreach ($csvFile as $line) {
            $datas[] = str_getcsv($line);
        }
        $newcountry = array();
        foreach ($datas as $values) {
            $state = $values[0];
            $key = $values[3];
            $newcountry[$key][] = $state;
        }
        array_shift($newcountry); // Remove header
        ksort($newcountry);
        $country_option = "";
        $states_option = "";
        $defaultValue = !empty($country) ? $country : (isset($field->defaultValue) ? $field->defaultValue : '');
        $defaultstate = !empty($states) ? $states : (isset($field->field_state_value) ? $field->field_state_value : '');
        // Build country and state dropdown options
        $boolean_defaultValue = false;
        foreach($newcountry as $countryname => $countrystate){
            if($countryname == $defaultValue){
                $boolean_defaultValue = true;
                break;
            }
        }
        if($boolean_defaultValue){
            foreach($newcountry as $countryname => $countrystate){
                if ($countryname == $defaultValue) {
                    $country_option .= "<option value='" . esc_attr($countryname) . "' selected>" . esc_html($countryname) . "</option>";
                    foreach($countrystate as $val_states){
                        if($val_states == $defaultstate){
                            $states_option .= "<option value='" . esc_attr($val_states) . "' selected>" . esc_html($val_states) . "</option>";
                        } else {
                            $states_option .= "<option value='" . esc_attr($val_states) . "'>" . esc_html($val_states) . "</option>";
                        }
                    }
                    continue;
                }
                $country_option .= "<option value='" . esc_attr($countryname) . "'>" . esc_html($countryname) . "</option>";
            }
        } else {
            foreach($newcountry as $countryname => $countrystate){
                $country_option .= "<option value='" . esc_attr($countryname) . "'>" . esc_html($countryname) . "</option>";
                foreach($countrystate as $val_states){
                    $states_option .= "<option value='" . esc_attr($val_states) . "'>" . esc_html($val_states) . "</option>";
                }
            }
        }
        $country_field = self::gfcws_get_country_field( $country_input, $id, $field_id, $country, $disabled_select, $country_option, $logic_event, $tabindex, $required_attribute);
        $states_field = self::gfcws_get_states_field( $states_input, $id, $form_id, $field_id, $states, $disabled_select, $states_option, $logic_event, $tabindex, $required_attribute);
        // Country field.
        if ( $is_admin || ! $hide_country ) {
            $style    = $hide_country ? "style='display:none;'" : '';
            if ( $is_sub_label_above ) {
                $countryinput = "<span class='ginput_left{$class_suffix} address_country ginput_address_country gf_left_half gform-grid-col' id='{$field_id}_1_container' {$style}><label for='{$field_id}_1' id='{$field_id}_1_label' {$sub_label_class_attribute}>{$country_sub_label}</label>{$country_field}</span>";
            } else {
                $countryinput = "<span class='ginput_left{$class_suffix} address_country ginput_address_country gf_left_half gform-grid-col' id='{$field_id}_1_container' {$style}>{$country_field}<label for='{$field_id}_1' id='{$field_id}_1_label' {$sub_label_class_attribute}>{$country_sub_label}</label></span>";
            }
        } else {
            $countryinput = sprintf( "<input type='hidden' class='gform_hidden' name='input_%d.1' id='%s_1' value='%s'/>", $id, $field_id, esc_attr($defaultValue) );
        }
        // State field.
        $style = ( $is_admin && ( isset($this->hideState) && $this->hideState || rgar( $states_input, 'isHidden' ) ) ) ? "style='display:none;'" : '';
        if ( $is_admin || ( !(isset($this->hideState) && $this->hideState) && ! rgar( $states_input, 'isHidden' ) ) ) {
            if ( $is_sub_label_above ) {
                $stateinput = "<span class='ginput_right{$class_suffix} gfcws_stateload address_state ginput_address_state gf_right_half gform-grid-col' id='{$field_id}_2_container' {$style}><label for='{$field_id}_2' id='{$field_id}_2_label' {$sub_label_class_attribute}>{$states_sub_label}</label>{$states_field}</span>";
            } else {
                $stateinput = "<span class='ginput_right{$class_suffix} gfcws_stateload address_state ginput_address_state gf_right_half gform-grid-col' id='{$field_id}_2_container' {$style}>{$states_field}<label for='{$field_id}_2' id='{$field_id}_2_label' {$sub_label_class_attribute}>{$states_sub_label}</label></span>";
            }
        } else {
            $stateinput = sprintf( "<input type='hidden' class='gform_hidden' name='input_%d.2' id='%s_2' value='%s'/>", $id, $field_id, esc_attr($defaultValue) );
        }
        $input = $countryinput . $stateinput;
        $show = "<div class='ginput_complex{$class_suffix} ginput_container {$css_class}' name='country_state_fliter' id='$field_id'>{$input}<div class='gf_clear gf_clear_complex'></div></div>";
        return $show;
    }
    /**
     * Returns a country select field.
     */
    public function gfcws_get_country_field($input, $id, $field_id, $country, $disabled_select, $country_option, $logic_event, $tabindex, $required_attribute){
        $placeholder_value_country = GFCommon::get_input_placeholder_value( $input );
        $options_country = "";
        $style_width='';
        $autocomplete_country = 'large';
        if ($placeholder_value_country) {
            $options_country .= "<option name='country_input_placeholders' value='{$placeholder_value_country}'>{$placeholder_value_country}</option>";
        }else{
            $options_country .= '<option value="">Select Country</option>';
        }
        $options_country .= $country_option;
        $markup = '';
        $markup .= "<select class='{$autocomplete_country}' data-show-subtext='true' data-live-search='true' name='input_{$id}.1' id='{$field_id}_1' {$logic_event} {$disabled_select} {$style_width} {$tabindex} {$required_attribute}>";
        $markup .= "{$options_country}";
        $markup .= "</select>";
        return $markup;
    }
    /**
     * Returns a state select field.
     */
    public function gfcws_get_states_field($input, $id, $form_id, $field_id, $states, $disabled_select, $states_option, $logic_event, $tabindex,  $required_attribute){
        $placeholder_value_states = GFCommon::get_input_placeholder_value( $input );
        $field = GFFormsModel::get_field( $form_id, $id );
        $options_states = "";
        $customInput = "";
        $autocomplete_states = 'large';
        if ($placeholder_value_states) {
            $options_states .= "<option name='states_input_placeholders' value='{$placeholder_value_states}'>{$placeholder_value_states}</option>";
        }else{
            $options_states .= '<option value="">Select State</option>';
        }
        $options_states .= $states_option;
        // Render both select and text input, hide text input by default
        $markup = "<select class='{$autocomplete_states}' data-custom-input='{$customInput}' name='input_{$id}.2' id='{$field_id}_2' {$logic_event} {$disabled_select} {$tabindex} {$required_attribute}>{$options_states}</select>";
        $markup .= "<input type='text' class='gfcws_state_text_input' name='input_{$id}.2' id='{$field_id}_2_text' placeholder='Enter State/Region' value='" . esc_attr($states) . "' />";
        return $markup;
    }

    /**
     * Returns a entry detail.
     */
    public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
        if ( is_array( $value ) ) {
            $country_entry  = trim( rgget( $this->id . '.1', $value ) );
            $states_entry  = trim( rgget( $this->id . '.2', $value ) );

            $line_break = $format == 'html' ? '<br />' : "\n";

            $country_entry_ct = '';
            $states_entry_ct = '';
            if(!empty($states_entry ) ){
                $states_entry_ct = '<strong>State: </strong>'.$states_entry;
            }
            if(!empty($country_entry) ){
                $country_entry_ct = '<strong>Country: </strong>'.$country_entry .$line_break;
            }
            $address = $country_entry_ct . $states_entry_ct;
            return $address;
        } else {
            return '';
        }
    }


    /**
     * Returns a options of countries.
     *
     * @since Unknown
     *
     * @return array
     */
    public function get_country_dropdown( $selected_country = '', $placeholder = '' ) {
        $str       = '';
        $selected_country = strtolower( $selected_country );
        $countries = array_merge( array( '' ), $this->get_countries() );
        foreach ( $countries as $code => $country ) {
            if ( is_numeric( $code ) ) {
                $code = $country;
            }
            if ( empty( $country ) ) {
                $country = $placeholder;
            }


            $selected = strtolower( esc_attr( $code ) ) == $selected_country ? "selected='selected'" : '';
            $str .= "<option value='" . esc_attr( $code ) . "' $selected>" . esc_html( $country ) . '</option>';
        }

        return $str;
    }


    /**
     * Returns a list of countries.
     *
     * @since Unknown
     *
     * @return array
     */

    public function get_countries() {

        $countries = array_values( $this->get_default_countries() );
        sort( $countries );

        /**
         * A list of countries displayed in the Lookup field country drop down.
         *
         * @since Unknown
         *
         * @param array $countries ISO 3166-1 list of countries.
         */
        return apply_filters( 'gform_countries', $countries );

    }

    /**
     * Returns the default array of countries using the ISO 3166-1 alpha-2 code as the key to the country name.
     *
     * @return array
     *
     */

    public function get_default_countries() {

        $csvFile = file(plugin_dir_path( __DIR__ ) . 'assets/data/states.csv');
        $datas = [];
        foreach ($csvFile as $line) {
            $datas[] = str_getcsv($line);
        }
        $newcountry = array();
        foreach ($datas as $values) {
            $countrycode = $values[2];
            $country = $values[3];
            $newcountry[$countrycode] = $country;
        }

        $newcountrys = array_shift($newcountry);
        ksort($newcountry);
        return $newcountry;
    }
}
GF_Fields::register( new GF_ADMIN_FIELDS_MODULE() );