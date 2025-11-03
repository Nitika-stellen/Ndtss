<?php
    // Ensure Gravity Forms and WordPress functions are available
    if (!class_exists('GFForms')) require_once(ABSPATH . 'wp-content/plugins/gravityforms/gravityforms.php');
    if (!function_exists('add_action')) require_once(ABSPATH . 'wp-includes/plugin.php');
    GFForms::include_addon_framework();
    class GFCWSAddOn extends GFAddOn{
        protected $_version = GF_CWS_VER;
        protected $_path = 'gforms-addon-for-country-and-state-selection/index.php';
        protected $_full_path = __FILE__;
        protected $_title = 'Country and State Selection Addon for Gravity Forms';
        private static $_instance = null;

        public static function get_instance() {
            if ( self::$_instance == null ) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }
        
        public function pre_init() {
            parent::pre_init();    
            if ( $this->is_gravityforms_supported() && class_exists( 'GF_Field' ) ) {
                require_once( GF_CWS_INC. 'class-gf-cws-admin-fields.php' );
                require_once( GF_CWS_INC. 'functions.php' );
                add_action( 'gform_editor_js_set_default_values' , array( $this,'gfcws_countrywisestate_group_fields' ));
            }
        }

        public function init_admin() {
            parent::init_admin();
        
            add_action( 'gform_editor_js', array( $this,"gfcws_input_placeholders_editor_js"));
            add_filter( 'gform_enable_field_label_visibility_settings', '__return_true' );
            add_filter( 'gform_tooltips', array( $this, 'tooltips' ) );       
            add_action( 'gform_field_standard_settings', array( $this, 'gfcws_field_standard_settings'), 10, 2 );
            add_action( 'gform_field_advanced_settings', array( $this, 'gfcws_field_advanced_settings' ), 10, 2 );  
        }

        

        public function scripts() {
            $scripts = array(
                array(
                    'handle'   => 'gfcws_addon_script_js',
                    'src'      => $this->get_base_url() . '/assets/js/fields.js',
                    'version'  => $this->_version,
                    'deps'     => array( 'jquery' ),
                    'callback' => array( $this, 'localize_scripts' ),
                    'enqueue'  => array(
                        array( 'field_types' => array( 'countrywisestate' ) ),
                    )
                ),
                array(
                    'handle'   => 'gfcws_addon_admin_js',
                    'src'      => $this->get_base_url() . '/assets/js/admin.js',
                    'version'  => $this->_version,
                    'deps'     => array( 'jquery' ),
                    'callback' => array( $this, 'localize_scripts' ),
                    'enqueue'  => array(
                        array( 'field_types' => array( 'countrywisestate' ) ),
                    )
                )
            );
            return array_merge( parent::scripts(), $scripts );
        }

        public function styles() {
            $styles = array(
                array(
                    'handle'  => 'countrywisestate_addon_styles',
                    'src'     => $this->get_base_url() . '/assets/css/admin.css',
                    'version' => $this->_version,
                    'enqueue' => array(
                        array( 'field_types' => array( 'countrywisestate' ) )
                    )
                )
            );
            return array_merge( parent::styles(), $styles );
        }
        
        /**
         * Gravity form setting fields tooltip add using hook
         * 
         * Hook : gform_tooltips
         *
         */
        public function tooltips( $tooltips ) {
            $simple_tooltips = array(
                'default_value_setting' => sprintf( '<h6>%s</h6>%s', esc_html__( 'Default Country', 'gforms-addon-for-country-and-state-selection' ), esc_html__( 'If you would like to pre-populate the value of a field, enter it here.', 'gforms-addon-for-country-and-state-selection' ) ),
                'default_statevalue_setting' => sprintf( '<h6>%s</h6>%s', esc_html__( 'Default State', 'gforms-addon-for-country-and-state-selection' ), esc_html__( 'If you would like to pre-populate the value of a field, select country first.', 'gforms-addon-for-country-and-state-selection' ) ),
            );
            return array_merge( $tooltips, $simple_tooltips );
        }


        /**
         * Show Default Country And State in advances setting using hook
         * 
         * Hook : gform_field_advanced_settings
         *
         */
        public function gfcws_field_advanced_settings( $position, $form_id ) {
            if ( $position == 200 ) {
                $get_countrys = new GF_ADMIN_FIELDS_MODULE;    
                ?>
                <li class="input_class_setting field_setting">
                    <label for="field_default_value" class="section_label">
                        <?php esc_html_e( 'Default Country', 'gforms-addon-for-country-and-state-selection' ); ?>
                        <?php gform_tooltip( 'default_value_setting' ) ?>
                    </label>
                    <select id="field_default_value" class="field_default_value">
                        <?php echo $get_countrys->get_country_dropdown();?>
                    </select>
                </li>
                <li class="input_class_setting field_setting">
                    <label for="field_state_value" class="section_label">
                        <?php esc_html_e( 'Default State', 'gforms-addon-for-country-and-state-selection' ); ?>
                        <?php gform_tooltip( 'default_statevalue_setting' ) ?>
                    </label>
                    <select id="field_state_value" class="field_state_value section_label" onclick="SetFieldProperty('field_state_value', jQuery(this).val());">
                    </select>
                </li>
                <?php
            }
        }

        /**
         * Show / Hide country and state field setting using hook
         * 
         * Hook : gform_field_standard_settings
         *
         */
        public function gfcws_field_standard_settings( $positions, $form_id ) {     
         
            if ( $positions == 25) {
                ?>
                <li class="countrywisestate_setting">
                    <div class="custom_inputs_setting gfield_sub_setting">
                        <label for="field_countrywisestate_fields_container" class="section_label">
                                <?php esc_html_e( 'Fields', 'gforms-addon-for-country-and-state-selection' ); ?>
                        </label>
                        <div id="field_countrywisestate_fields_container" style="padding-top:10px;">                      
                        </div>
                    </div>
                </li>
                <?php
            }
        }

         /**
         * Include Ajax JS file in gravity form panel using hook
         * 
         * Hook : gform_enqueue_scripts
         *
         */
        public function gfcws_addon_admin(){
            wp_register_style( GF_CWS_NAME.'-admin-addon', GF_CWS_CSS . 'admin.css', array(),GF_CWS_VER, 'all' );
            wp_enqueue_style( GF_CWS_NAME.'-admin-addon');

        }

        /**
         * Gravity form editor js default value set using hook
         * 
         * Hook : gform_editor_js_set_default_values
         *
         */
        public function gfcws_countrywisestate_group_fields(){
            ?>
                case 'countrywisestate' : field.inputType = 'countrywisestate';
                field.inputs = new Array();
                field.inputs.push(new Input(field.id + '.1', '<?php echo apply_filters( 'gform_name_country_' . rgget( 'id' ), apply_filters( 'gform_name_country', __( 'Country', 'gforms-addon-for-country-and-state-selection' ), rgget( 'id' ) ), rgget( 'id' ) ); ?>'));
                field.inputs.push(new Input(field.id + '.2', '<?php echo apply_filters( 'gform_name_states_' . rgget( 'id' ), apply_filters( 'gform_name_states', __( 'State', 'gforms-addon-for-country-and-state-selection' ), rgget( 'id' ) ), rgget( 'id' ) ); ?>'));
                break;
            <?php
        }

          /**
         * Gravity form editor js set using hook
         * 
         * Hook : gform_editor_js
         *
         */
        public function gfcws_input_placeholders_editor_js(){
            ?>
            <script>

                jQuery(document).bind("gform_load_field_settings", function(event, field, form){
                  
                    var fieldid = field["id"], formid = form['id'];
                    var ojfield = GetFieldById(fieldid);
                    
                    if(ojfield.type == "countrywisestate"){

                        jQuery(".prepopulate_field_setting.field_setting label").contents().first()[0].textContent = 'Allow Field To Be Populated Dynamically ';
                        
                        //description
                        jQuery("#field_description").keyup(function(){
                            jQuery("#gfield_description_"+formid+"_"+fieldid).text(jQuery(this).val());
                        });

                      
                        for(var i = 1 ; i <=field.inputs.length ; i++){
                            gfcwssublabel(i)
                            gfcwsplaceholder(i)
                        }
                        //sub label input
                        function gfcwssublabel(i){
                            jQuery("input[id='field_custon_input_label_"+fieldid+"."+i+"']").keyup(function(){
                                var string = jQuery(this).val();
                                var place = jQuery(this).attr('placeholder');
                                if(string != ""){
                                    jQuery("label[id='input_"+fieldid+"_"+i+"_label'] strong").text(string);
                                }else{
                                    jQuery("label[id='input_"+fieldid+"_"+i+"_label'] strong").text(place);
                                }
                            });
                        }
                        //placeholder
                        function gfcwsplaceholder(i){
                            jQuery("tr.input_placeholder_row[data-input_id='"+fieldid+"."+i+"'] input").keyup(function(){
                                var string = jQuery(this).val();
                                if(string != ""){
                                    if(jQuery("#input_"+fieldid+"_"+i+" option[name='country_input_placeholders']").length == 0){
                                        jQuery("#input_"+fieldid+"_"+i+"").append("<option name='country_input_placeholders' value='"+string+"' selected>"+string+"</option>")
                                    }else{
                                        jQuery("#input_"+fieldid+"_"+i+" option[name='country_input_placeholders']").text(string)
                                    }
                                }else{
                                    jQuery("#input_"+fieldid+"_"+i+" option[name='country_input_placeholders']").remove();
                                }
                            });
                        }

                        gfcws_load_default_unique_group_value();
                        //Country Default value
                        function gfcws_processData_country(allText,defaultValueText) {
                            var allTextLines = allText.split(/\r\n|\n/);
                            var headers = allTextLines[0].split(',');
                            var lines = [];
                            var defaultValue = "";
                            defaultValue += '<option value="">Choose a default value</option>';
                            for (var i=1; i<allTextLines.length; i++) {
                                var data = allTextLines[i].split(',');
                                if (data.length == headers.length) {         
                                    if(defaultValueText == data[3]){
                                        defaultValue += '<option selected value="'+data[3]+'">'+data[3]+'</option>';
                                    }else{
                                        defaultValue += '<option value="'+data[3]+'">'+data[3]+'</option>';
                                    }                                   
                                }
                            }
                        }

                        function gfcws_load_default_unique_group_value(){
                            jQuery("select[id='field_default_value']").prop( "disabled", true );
                            var defaultValueText = field['defaultValue'];
                            var defaultValue = "";                            
                            var data;
                            jQuery.ajax({
                                type: "GET",  
                                url: "<?php echo plugins_url( "assets/data/states.csv", __FILE__ ) ?>?nocache="+(new Date()).getTime(),
                                dataType: "text",       
                                success: function(response)  
                                {
                                    defaultValue = gfcws_processData_country(response,defaultValueText);
                                    jQuery("select[id='field_default_value']").html(defaultValue);
                                    jQuery("select[id='field_default_value']").prop( "disabled", false );
                                    if(defaultValueText){
                                        jQuery('#field_default_value [value='+defaultValueText+']').attr('selected', 'true');
                                    }                                    
                                }   
                            });
                        }                       
                   
                        // show hide sub label
                        field = GetSelectedField();
                        var countrywisestate_fields_str = GetCustomizeInputsUI(field);
                        jQuery("#field_countrywisestate_fields_container").html(countrywisestate_fields_str).show();                          
                    }else{
						jQuery('.countrywisestate_setting').hide();
					}

                    // Selected country wise state
                    gfcws_select_country();
                    function gfcws_select_country(){
                        var selected_state= '';  
                        var select_country = '';  
                            selected_state = field['field_state_value'];
                            select_country = field['defaultValue'];
                        gfcws_get_selected_state(select_country,selected_state);
                        //on change filter state
                        jQuery(document).on('change','#field_default_value',function(){  
                            var select_country = jQuery(this).val();
                            gfcws_get_selected_state(select_country,selected_state);                       
                        });                                                                                 
                    }     
                    
                    function gfcws_get_selected_state(select_country,selected_state){
                        var data;
                        jQuery.ajax({
                            type: "GET",  
                            url: "<?php echo admin_url('admin-ajax.php'); ?>",
                            data: {
                                action: 'Ajax_GFCWS_Filter',
                                country : select_country,
                                state : selected_state,
                            },
                            dataType: 'json',
                            cache: false,      
                            success: function(response)  
                            {
                                jQuery("#field_state_value").html('');
                                jQuery("#field_state_value").append(response[0]);
                            }   
                        });
                    }  
                });            
            </script>
            <?php
        }

        public function localize_scripts() {
            ?>
            <script type="text/javascript">
                var ajax_object = {
                    ajaxurl: "<?php echo admin_url('admin-ajax.php'); ?>",
                    GF_CWS_ASSETS: "<?php echo plugin_dir_url(__FILE__); ?>assets/"
                };
            </script>
            <?php
        }
    }
    GFAddOn::register( 'GFCWSAddOn' );