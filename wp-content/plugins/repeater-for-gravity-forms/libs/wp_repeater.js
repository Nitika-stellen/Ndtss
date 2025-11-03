(function($) {
	"use strict";
		jQuery(document).ready(function($){
			jQuery( document ).on( 'change keyup', '.gfield input, .gfield select, .gfield textarea', function( event ) {
				gf_raw_input_change( event, this );
			} );
			$("body").on('change',".container-repeater-field .gform-grid-col--size-auto",function(e){
				var self = $(this);	
				var value = $(this).val();
				var input_ids = $(this).attr("name");
				if( input_ids === undefined) {
					return;
				}
				input_ids = input_ids.split("_");
				var input_id = input_ids[1];
				var form_ids = self.closest("form").attr("id");
				form_ids = form_ids.split("_");
				var form_id = form_ids[1];
				var field_ids = input_ids[1].split(".");
				if( field_ids[1] == 2) {
					return;
				}
				var field_id = field_ids[0];
				const obj = {
				    [input_id]: value,
				    [field_id+".2"]: ""
				}
				var $nextSelect = $(this).closest(".ginput_container").find("#input_"+form_id+"_"+field_id+"_2_container select")
				$.post( gformChainedSelectData.ajaxUrl, {
	                action:   'gform_get_next_chained_select_choices',
	                input_id: input_id,
	                form_id:  form_id,
	                field_id: field_id,
	                value :    obj,
	                nonce:    gformChainedSelectData.nonce
	            }, function( response ) {
	                if( ! response ) {
	                    return;
	                }
	                var choices       = $.parseJSON( response ),
	                    optionsMarkup = '';
	                $nextSelect.find( 'option' ).remove();
	                if( choices.length <= 0 ) {
	                    //self.resetSelects( $select, true );
	                } else {
	                    var hasSelectedChoice = false;
	                    $.each( choices, function( i, choice ) {
	                        var selected = choice.isSelected ? 'selected="selected"' : '';
	                        if ( selected )  {
	                            hasSelectedChoice = true;
	                        }
	                        optionsMarkup += '<option value="' + choice.value + '"' + selected + '>' + choice.text + '</option>';
	                    } );
	                    $nextSelect.show().append( optionsMarkup );
	                    // the placeholder will be selected by default, rather than removing it and re-adding, just force the noOptions option to be selected
	                    if( choices[0].noOptions ) {
	                        var $noOption = $nextSelect.find( 'option:last-child' ).clone(),
	                            $nextSelects = $nextSelect.parents( 'span' ).nextAll().find( 'select' );
	                        $nextSelects.append( $noOption );
	                        $nextSelects.add( $nextSelect )
	                            .addClass( 'gf_no_options' )
	                            .find( 'option:last-child' ).prop( 'selected', true );
	                        //self.toggleCompleted( true );
	                    } else {
	                        $nextSelect
	                            .removeClass( 'gf_no_options' )
	                            //.prop( 'disabled', false ).show();
	                            .toggleSelect( false, self );
	                        if ( hasSelectedChoice ) {
	                            $nextSelect.change();
	                        }
	                    }
	                }
	                //self.resizeSelects();
	            } );
			})
			gform.addFilter( 'gform_is_value_match', function( isMatch, formId, rule) {
				var check_repeater = rule['fieldId'].split("-");
				if(check_repeater.length < 2) {
					return isMatch;
				}else{
					var $               = jQuery,
					inputId         = rule['fieldId'],
					fieldId         = ( inputId ),
					inputIndex      = gformExtractInputIndex( inputId ),
					isInputSpecific = inputIndex !== false,
					$inputs;
					if( isInputSpecific ) {
						$inputs = $( '#input_{0}_{1}_{2}, #choice_{0}_{1}_{2}'.gformFormat( formId, fieldId, inputIndex ) );
					} else {
						$inputs = $( 'input[id="input_{0}_{1}"], input[id^="input_{0}_{1}_"], input[id^="choice_{0}_{1}_"], select#input_{0}_{1}, textarea#input_{0}_{1}'.gformFormat( formId, fieldId ) );
					}
					var isCheckable = $.inArray( $inputs.attr( 'type' ), [ 'checkbox', 'radio' ] ) !== -1;
					var isMatch     = isCheckable ? gf_is_match_checkable( $inputs, rule, formId, fieldId ) : gf_is_match_default( $inputs.eq( 0 ), rule, formId, fieldId );
					return isMatch;
				}
			} );
			gform.addFilter( 'gform_field_meta_raw_input_change', function( form,input, event) {
				var htmlId = input.attr( 'id' );
				var fieldId   = yeeaddons_gf_get_input_id_by_html_id( htmlId );
        		var formId    = gf_get_form_id_by_html_id( htmlId );
        		if( formId !== undefined ) {
        			var check_ids = htmlId.split("-");
					if(check_ids.length > 1) {
						form = {fieldId:fieldId ,formId:formId };
					}
        		}
				return form;
			} );
			function yeeaddons_gf_get_input_id_by_html_id( htmlId ) {
				var ids = yeeaddons_gf_get_ids_by_html_id( htmlId );
				if (Array.isArray(ids) && ids.length == 3) {
					var	id  = ids[ ids.length - 1 ];
					ids.shift();
					id = ids.join( '.' );
					return id;
				}else{
					return ids;
				}
			}
			function yeeaddons_gf_get_ids_by_html_id( htmlId ) {
				var ids = htmlId ? htmlId.split( '_' ) : [];
				if( Array.isArray(ids) && ids.length > 2 ){
					ids = ids[2];
				}else{
					for( var i = ids.length - 1; i >= 0; i-- ) {
						if ( ! gformIsNumber( ids[ i ] ) ) {
							ids.splice( i, 1 );
						}
					}
				}
				return ids;
			}
			gform.addAction( 'gform_input_change', function( elem, formId, fieldId ) {
				if( ! window.gf_form_conditional_logic ) {
					return;
				}
				var dependentFieldIds = rgars( gf_form_conditional_logic, [ formId, 'fields', ( fieldId ) ].join( '/' ) );
				if( dependentFieldIds ) {
					gf_apply_rules( formId, dependentFieldIds );
				}
			}, 10 );
			get_repeater_data_name();
			var names_upload = [];
			jQuery(document).on("gform_page_loaded", function (e, form_id) {
				//jQuery(".gform-datepicker").datepicker('destroy');
				get_repeater_data_name();
			});
			jQuery(document).on('gform_post_render', function(event, form_id, current_page){
				//jQuery(".gform-datepicker").datepicker('destroy');
				get_repeater_data_name();
			});
			gform.addFilter( 'gform_file_upload_markup', function( html, file, up, strings, imagesUrl, response ) {
				var repeater = $('#' + file.id).closest(".container-repeater-field").find(".gform_multifile_upload")
				var val_repeater = repeater.val();
				var link = response.data.uploaded_filename;
				if(val_repeater == ""){
					repeater.val(link)
				}else{
					repeater.val(val_repeater+", " + link);
				}
				return html.replace("onclick", "data-onclick");;
			} );
			var repeater_fields_htmls ={};
			//condition out repeater out
			gform.addAction( 'gform_post_conditional_logic_field_action1', function( formId, action, targetId, defaultValues, isInit) {
				if(targetId != ""){
					var dat_select = targetId.replace("#", '');
					$(".gf-field-repeater-data-html").each(function(){
						var html_data = $(this).val();
						html_data = $(html_data);
						if(action == "hide"){
							$('[data-js-reload="'+dat_select+'"]',html_data).css("display","none");
						}else{
							$('[data-js-reload="'+dat_select+'"]',html_data).css("display","block");
						}
						$(this).val("<div class='container-repeater-field'>"+html_data.html()+'</div>');
					})
					if(action == "hide"){
						$('[data-js-reload="'+dat_select+'"]').css("display","none");
					}else{
						$('[data-js-reload="'+dat_select+'"]').css("display","block");
					}
				}
			} );
			$("body").on("click",".gform_delete_file",function(e){
				e.preventDefault();
				var str =$(this).data("onclick");
				const regex = /\((.*?)\)/gm;
				var m="";
				var a="";
				while ((m = regex.exec(str)) !== null) {
					// This is necessary to avoid infinite loops with zero-width matches
					if (m.index === regex.lastIndex) {
						regex.lastIndex++;
					}
					a =m[1].split(',');;
				}
				gformDeleteUploadedFileRepeater(a[0],a[1],$(this));
				return;
			})
			function gformDeleteUploadedFileRepeater(formId, fieldId, deleteButton){
				if(deleteButton.closest(".repeater-field-warp-item-data").length > 0) {
					var rand_id = deleteButton.closest(".container-repeater-field").data("id");
					var fileIndex = jQuery(deleteButton).parent().index();
					var parent = jQuery("#field_" + formId + "_" + fieldId);
					parent.find('input[type="file"],.validation_message,#extensions_message_' + formId + '_' + fieldId).removeClass("gform_hidden");
					parent.find(".ginput_post_image_file").show();
					//parent.find("input[type=\"text\"]").val('');
					var filesJson = jQuery('#gform_uploaded_files_' + formId).val();
					if(filesJson){
						var files = jQuery.secureEvalJSON(filesJson);
						if(files) {
							var inputName = "input_" + fieldId;
							var full_name = deleteButton.closest(".repeater-field-warp-item-data").find('input[name="gform_multifile_upload_'+formId+'_'+fieldId+'__'+rand_id+'"]').val();
							if(full_name == ""){
								full_name = [];
							}else{
								full_name = full_name.split(",");
							}
							var $multfile = parent.find("#gform_multifile_upload_" + formId + "_" + fieldId +"__"+rand_id );
							var remove_name = deleteButton.closest(".ginput_preview").find(".gfield_fileupload_filename").html();
							var index1 = full_name.indexOf(remove_name);
							if (index1 !== -1) {
								full_name.splice(index1, 1);
							}
							deleteButton.closest(".repeater-field-warp-item-data").find('input[name="gform_multifile_upload_'+formId+'_'+fieldId+'__'+rand_id+'"]').val(full_name.join(","));
							deleteButton.closest(".ginput_preview").remove();
							if( $multfile.length > 0 ) {
								files[inputName].splice(fileIndex, 1);
								var settings = $multfile.data('settings');
								var max = settings.gf_vars.max_files;
								jQuery("#" + settings.gf_vars.message_id +"__"+rand_id).html('');
								if(files[inputName].length < max)
									gfMultiFileUploader.toggleDisabled(settings, false);
							} else {
								files[inputName] = null;
							}
							jQuery('#gform_uploaded_files_' + formId).val(jQuery.toJSON(files));
						}
					}
				}else{
					var parent = jQuery("#field_" + formId + "_" + fieldId);
					var fileIndex = jQuery(deleteButton).parent().index();
					deleteButton.closest(".ginput_preview").remove();
					//displaying single file upload field
					parent.find('input[type="file"],.validation_message,#extensions_message_' + formId + '_' + fieldId).removeClass("gform_hidden");
					//displaying post image label
					parent.find(".ginput_post_image_file").show();
					//clearing post image meta fields
					parent.find("input[type=\"text\"]").val('');
					//removing file from uploaded meta
					var filesJson = jQuery('#gform_uploaded_files_' + formId).val();
					if(filesJson){
						var files = jQuery.secureEvalJSON(filesJson);
						if(files) {
									var inputName = "input_" + fieldId;
							var $multfile = parent.find("#gform_multifile_upload_" + formId + "_" + fieldId );
							if( $multfile.length > 0 ) {
								files[inputName].splice(fileIndex, 1);
								var settings = $multfile.data('settings');
								var max = settings.gf_vars.max_files;
								jQuery("#" + settings.gf_vars.message_id).html('');
								if(files[inputName].length < max)
									gfMultiFileUploader.toggleDisabled(settings, false);
							} else {
								files[inputName] = null;
							}
							jQuery('#gform_uploaded_files_' + formId).val(jQuery.toJSON(files));
						}
					}
				}
			}
			var input_ids = [];
			function change_name_and_ids(item , field_end = null, key = null){
				if( key == null ){
					var id_rand = Math.floor(Math.random() * 10000);
				}else{
					var id_rand = key;
				}
				var datas = JSON.parse(field_end.find(".gf-field-repeater-data").val());
				var datas_ids = datas.id;
				datas_ids.push(id_rand);
				datas.id = datas_ids;
				field_end.find(".gf-field-repeater-data").val(JSON.stringify(datas));	
				item = $(item);
				item.attr("data-id",id_rand);
				$(".gfield_visibility_visible",item).each(function(){
					var id = $(this).attr("id");
					$(this).attr("id",id +"-"+id_rand);
					$(this).attr("data-js-reload",id +"-"+id_rand);
				})
				$(".gform_fileupload_multifile",item).each(function(){
					var id = $(this).attr("id");
					var container_item = $(this).closest(".gfield gfield--type-fileupload");
					var settings = $(this).attr("data-settings");
					settings = jQuery.parseJSON(settings);
					$.each(settings, function( index, value ) {
						switch(index) {
						case "browse_button":
						case "container":
						case "drop_element":
						case "filelist":
							settings[index] = value + "__"+id_rand;
							$("#"+value,item).attr("id",value + "__"+id_rand );
							break;
						case "multipart_params":
							//settings["multipart_params"]["field_id"] = value.field_id + "__"+id_rand;
							break;
						}
					});
					item.append('<input type="hidden" name="'+id+'" class="gform_multifile_upload" />');
					$(this).attr("id",id+"__"+id_rand);
					$(this).attr("data-settings",JSON.stringify(settings));
					names_upload.push(id+"__"+id_rand);
				})
				$("input",item).each(function(){
					var name = $(this).attr("name");
					if( typeof name !== 'string' || name == "MAX_FILE_SIZE"){
						return;
					}
					var type = $(this).attr("type");
					var id = $(this).attr("id");
					input_ids.push(id);
					if ( name != "" && name.endsWith('[]')) {
						name = name.replace(/\[\]$/, '');
					    $(this).attr("name",name+"__"+id_rand+"[]");
					}else{
						$(this).attr("name",name+"__"+id_rand);
					}
					$(this).attr("id",id+"-"+id_rand);
					var value_check = localStorage.getItem(id+"-"+id_rand);
					if( type == "checkbox" ){
						$(this).closest("div").find("label").attr("for",id+"-"+id_rand);
						if( value_check != null ){
							$(this).attr("checked","checked");
						}
					} else if( type == "file"  ){
						$(this).attr("id",id+"-"+id_rand);
					}else if( type == "radio"  ){
						$(this).attr("id",id+"-"+id_rand);
						$(this).closest("div, li").find("label").attr("for",id+"-"+id_rand);
						var value_check = localStorage.getItem(name+"__"+id_rand);
						if( value_check != null ){
							var old_check = $(this).val();
							if(old_check == value_check){
								$(this).attr("checked", true);
							}
						}
					}
					else{
						// ensure the field label's "for" attribute corresponds with the field's ID
						$(this).closest("div").parent().find("label").attr("for",id+"-"+id_rand);
						if( value_check != null ){
							$(this).val(value_check);
						}
					}
				})
				$("textarea",item).each(function(){
					var name = $(this).attr("name");
					var id = $(this).attr("id");
					$(this).attr("name",name+"__"+id_rand);
					$(this).attr("id",id+"-"+id_rand);
					// ensure the field label's "for" attribute corresponds with the field's ID
					$(this).closest("div").parent().find("label").attr("for",id+"-"+id_rand);
					var value_check = localStorage.getItem(id+"-"+id_rand);
					if( value_check != null ){
						$(this).val(value_check);
					}
				})
				$("select",item).each(function(){
					var name = $(this).attr("name");
					var id = $(this).attr("id");
					if(name.includes("[")) {
						name = name.replace(/\[\]/g, '');
						$(this).attr("name",name+"__"+id_rand+"[]");
					}else{
						$(this).attr("name",name+"__"+id_rand);
					}
					$(this).attr("id",id+"-"+id_rand);
					// ensure the field label's "for" attribute corresponds with the field's ID
					$(this).closest("div").parent().find("label").attr("for",id+"-"+id_rand);
					var value_check = localStorage.getItem(id+"-"+id_rand);
					if( value_check != null ){
						$(this).val(value_check);
					}
				})
				return item;
			}
			function add_repeater_data(button, key=null){
				var start_field;
				if(key == null){
					var key = Math.floor(Math.random() * 10000);
				}
				var item = $('<div class="repeater-field-item"><div class="repeater-field-header"></div><div class="repeater-field-content"></div></div>');
				button.prevAll().each(function(index){ 
					var item = $(this).clone();
					if( item.hasClass("gfield--type-repeater_start") ) {
						start_field = $(this);
						return false;
					}
				})
				var html_field = get_repeater_data(button,key);
				var header = get_repeater_data_header(start_field);
				item.find(".repeater-field-header").append(header);
				item.find(".repeater-field-content").append(html_field);
				button.find(".repeater-field-warp-item").append(item);
				update_repeater_count_header();
				$( "input" ).trigger( "done_load_repeater" );
				var form_ids = button.attr("id").split("_");
				if(yeeaddons_gf_repeater_data.pro == "ok") {
					var form_id = form_ids[1];
					if( window["gf_form_conditional_logic"] !== undefined ) {
						if(  typeof window["gf_form_conditional_logic"][form_id] !== "undefined" ) {
							var dependents = window["gf_form_conditional_logic"][form_id].dependents;
							var dependents_new = dependents;
							var dependents_new_id = [];
							$.each( dependents, function( key_1, value ) {
								var datas_logic = [];
								$.each( value, function( key_2, value_2 ) {
									datas_logic.push(value_2+"-"+key);
								})
								if( key_1.search("-") < 0 ){
									dependents_new[key_1+"-"+key] = datas_logic;
									dependents_new_id.push(key_1+"-"+key);
								}else{
									dependents_new_id.push(key_1);
								}
							});
							var fields = window["gf_form_conditional_logic"][form_id].fields;
							var fields_new = fields;
							$.each( fields, function( key_1, value ) {
								var datas_logic = [];
								$.each( value, function( key_2, value_2 ) {
									datas_logic.push(value_2+"-"+key);
								})
								if( key_1.search("-") < 0 ){
									fields_new[key_1+"-"+key] = datas_logic;
								}
							});
							var logic = window["gf_form_conditional_logic"][form_id].logic;
							//console.log(window["gf_form_conditional_logic"][form_id]);
							var logic_new = logic;
							$.each( logic, function( key_1, value ) {
								if( key_1.search("-") < 0 ){
									// use old and add new repeater
									logic_new[key_1+"-"+key] = yeeaddons_change_id_logic(value,key);
								}else{
									//da them
								}
							});
							var animation = window["gf_form_conditional_logic"][form_id].animation;
							var defaults = window["gf_form_conditional_logic"][form_id].defaults;
							window["gf_form_conditional_logic"][form_id] = {animation:animation,defaults:defaults,dependents:dependents_new,fields:fields_new,logic:logic_new,"ok":"ok"};
							gf_apply_rules(form_id, dependents_new_id , true);
						}
					}
				}
				//end logic
				var j = 0;
				$.each( names_upload, function( key_1, value ) {
					gfMultiFileUploader.setup("#"+value);
					names_upload.splice(j, 1);
					j++;
				});
				var input_mask = yeeaddons_gf_repeater_data.input_mask;
				$.each( input_ids, function( key_1, value_1 ) {
					if(value_1 in input_mask){
						$('#'+value_1+"-"+key).mask(input_mask[value_1]).bind('keypress', function(e){if(e.which == 13){jQuery(this).blur();} } )
					}
				});
				conditional_logic_custom(key);  
				input_ids = [];
				$( '.gform-datepicker' ).each( function() {
					var $element = $( this );
					initSingleDatepicker( $element );
					$element.addClass( 'initialized' );
				} );
			}
			function yeeaddons_change_id_logic(value,key){
				var field_rules_inner = [];
				$.each( value.field.rules, function( key_2, value_2 ) {
					if( value_2.fieldId.search("-") < 0 ){
						var field_id_1 = value_2.fieldId +"-"+key;
					}
					field_rules_inner.push({fieldId:field_id_1,operator:value_2.operator,value:value_2.value});
				})
				var rules =field_rules_inner;
				var field = {actionType:value.field.actionType,enabled:value.field.enabled,logicType:value.field.logicType,rules:rules};
				return {field:field,nextButton:value.nextButton,section:value.section};
			}
			function conditional_logic_custom(rand){
				return;
				var value_check = $("#input_1_13-"+rand).val();
				if(value_check == "Other" || value_check == "other"){
					$("#input_1_14-"+rand).closest(".gfield").addClass("hidden");
				}else{
					$("#input_1_14-"+rand).closest(".gfield").removeClass("hidden");
				}
			}
			gform.addAction( 'gform_input_change', function( elem, formId, fieldId ) {
				var datas = $(elem).attr("name").split("__");;
				if( datas.length>1 ){
					conditional_logic_custom(datas[1]);
				}
			}, 10 );
			function get_repeater_data(step_field,key=null){
				var data_html = step_field.find(".gf-field-repeater-data-html").val();
				if(data_html == ""){
					data_html = step_field.find(".gf-field-repeater-data-html").attr('value');
				}			
				var html_step = change_name_and_ids(data_html,step_field,key);
				return html_step;
			}
			function get_repeater_data_name(){
				var i = 1;
				$(".gfield--type-repeater_start").each(function(){
					if($(this).data("installed") == "installed"){
						return;
					}
					$(this).data("installed","installed");
					$(this).addClass("installed");
					var html_step = $("<div class='container-repeater-field'></div>");
					var names = [];
					var step_field = "";
					var elements = $(this).nextAll();
					var value = "";
					elements.each(function(index){ 
							var item = $(this).clone();
							if( item.hasClass("gfield--type-repeater_end") ) {
								$(this).attr("data-id",i);
								value = $(this).find("input").val();
								if(value == ""){
									value = $(this).find("input").attr("value");
								}
								step_field = $(this);
								$(this).find(".gf-field-repeater-data").val(JSON.stringify({"count":1,"fields":names,"id":[]}));
								$(this).find(".gf-field-repeater-data").attr("value",JSON.stringify({"count":1,"fields":names,"id":[]}));
								return false;
							}
							$(this).remove();
							html_step.append(item);
							var check_name = null;
							if(item.find(".ginput_container_fileupload").length > 0 ){
								var name = item.find("input[type=file]").attr("name");
								if( name === undefined ){
									name = item[0].id;
									name = name.split("field");
									names.push("gform_multifile_upload"+name[1]);
								}else{
									names.push(item.find("input[type=file]").attr("name"));
								}
							}else{
								if( item.find("input").attr("name") ) {
									var custom_n = item.find("input").attr("name");
									custom_n = custom_n.replace(/\[\]$/, '');
									names.push(custom_n);
								}else if( item.find("textarea").attr("name") ){
									names.push(item.find("textarea").attr("name"));
								}else if( item.find("select").attr("name") ){
									names.push(item.find("select").attr("name"));
								}else{
									var type = item.find("input").attr("type");
									names.push(item.find("input").attr("name"));
							}	
							}
					})
					var text_html = "<div class='container-repeater-field'>"+html_step.html()+"</div>";
					step_field.find(".gf-field-repeater-data-html").val(text_html);
					step_field.find(".gf-field-repeater-data-html").attr("value",text_html);
					var initial_rows = 1;
					initial_rows = step_field.find(".repeater-field-warp-item-data").data("initial_rows");
					var initial_rows_map_field_check = step_field.find(".repeater-field-warp-item-data").data("initial_rows_map_check");
					if( initial_rows_map_field_check != "" && initial_rows_map_field_check !== undefined  ) {
						var initial_rows_map_field = step_field.find(".repeater-field-warp-item-data").data("initial_rows_map");
						var initial_rows_map_number = $("#"+initial_rows_map_field).val();
						if(initial_rows_map_number == ""){
							initial_rows_map_number = $("#"+initial_rows_map_field).attr("value");
						}
						if(initial_rows_map_number == ""){
							initial_rows_map_number = 0;
						}
						$("#"+initial_rows_map_field).attr("data-repeater",step_field.find(".repeater-field-warp-item-data").data("map_id"));
						$("#"+initial_rows_map_field).attr("repeater_initial_rows","ok");
						initial_rows = initial_rows_map_number;
						step_field.find(".gf-repeater-field-button-add").addClass("hidden");
						step_field.addClass("repeater-remove-toolbar");
					}
					if(initial_rows === undefined || initial_rows === ""){
						initial_rows = 1;
					}
					if( value != "" ){
						value = JSON.parse(value);
						initial_rows = value.count;
						var data_arr_ids = value.id;
						setTimeout(function() {
						for (var j = 0; j < initial_rows; j++) {
							add_repeater_data(step_field.closest(".gfield--type-repeater_end"),data_arr_ids[j]);
						}
					}, 100);
					}else{
						setTimeout(function() {
							for (var j = 0; j < initial_rows; j++) {
								add_repeater_data(step_field.closest(".gfield--type-repeater_end"));
							}
					}, 100);
					}
					i++;
				})
			}
			$("body").on("change","[repeater_initial_rows='ok']",function (e){
				var repeater_id = $(this).data("repeater");
				$("#"+repeater_id).find(".repeater-field-item").remove();
				var number = $(this).val();
				if(number == ""){
					number = $(this).attr("value");
				}
				for (let i = 0; i < number; i++) {
					$("#"+repeater_id).find(".gf-repeater-field-button-add").click();
				}	
			})
			function get_repeater_data_header(start_field){
				var html_step = start_field.find(".repeater-field-header-data").val();
				if(html_step == ""){
					html_step = start_field.find(".repeater-field-header-data").attr("value");
				}
				return html_step;
			}
			function update_repeater_count_header(){
				$(".gfield--type-repeater_end").each(function(){
						var i = 1;
						$(".repeater-field-item",$(this)).each(function(){
							$(this).find(".repeater-field-header-count").html(i);
							i++;
						})
						var data_js = $(this).find(".gf-field-repeater-data").val();
						if(data_js == ""){
							$(this).find(".gf-field-repeater-data").attr("value");
						}
						var datas = JSON.parse(data_js);
						datas.count = i-1;
						$(this).find(".gf-field-repeater-data").val(JSON.stringify(datas));
						$(this).find(".gf-field-repeater-data").attr("value",JSON.stringify(datas));
				});
			}
			function check_max_row(step_field){
				var max = step_field.find(".repeater-field-warp-item-data").data("limit");
				var number_item = $('.repeater-field-item',step_field).length;
				if( number_item >= max ){
					return false;
				}else{
					return true;
				}
			}
			function check_min_row(step_field){
				var min = step_field.find(".repeater-field-warp-item-data").data("initial_rows");
				var number_item = $('.repeater-field-item',step_field).length;
				if( number_item <= min ){
					return false;
				}else{
					return true;
				}
			}
			function removeAR(arr) {
				var what, a = arguments, L = a.length, ax;
				while (L > 1 && arr.length) {
					what = a[--L];
					while ((ax= arr.indexOf(what)) !== -1) {
						arr.splice(ax, 1);
					}
				}
				return arr;
			}
			$("body").on("click",".gf-repeater-field-button-add",function(e){
				e.preventDefault();
				if( check_max_row($(this).closest(".gfield--type-repeater_end")) ){
					add_repeater_data($(this).closest(".gfield--type-repeater_end"));
				}else{
					$(this).addClass('hidden');
				}
			})
			$("body").on("click",".repeater-field-header-acctions-toogle",function(e){
				e.preventDefault();
				if( $(this).hasClass("icon-down-open")){
					$(this).removeClass("icon-down-open");
					$(this).addClass("icon-up-open");
				}else{
					$(this).addClass("icon-down-open");
					$(this).removeClass("icon-up-open");
				}
				$(this).closest(".repeater-field-item").find(".repeater-field-content").slideToggle("slow");
				$(this).closest(".repeater-field-item").find(".repeater-field-header").toggleClass('repeater-content-show');
			})
			$("body").on("click",".repeater-field-header-acctions-remove",function(e){
				e.preventDefault();
				$(this).closest(".gfield--type-repeater_end").find(".gf-repeater-field-button-add").removeClass('hidden');
				if( check_min_row($(this).closest(".gfield--type-repeater_end")) ){
					var id = $(this).closest(".repeater-field-item").find(".container-repeater-field").data("id");
					var data_js = $(this).closest(".gfield--type-repeater_end").find(".gf-field-repeater-data").val();
					if(data_js == ""){
						data_js = $(this).closest(".gfield--type-repeater_end").find(".gf-field-repeater-data").attr("value");
					}
					var datas = JSON.parse(data_js);
					var datas_ids = datas.id;
					datas_ids = removeAR(datas_ids,id);
					datas.id = datas_ids;
					$(this).closest(".gfield--type-repeater_end").find(".gf-field-repeater-data").val(JSON.stringify(datas));
					$(this).closest(".gfield--type-repeater_end").find(".gf-field-repeater-data").attr("value",JSON.stringify(datas));
					$(this).closest(".repeater-field-item").remove();
				}else{
				}
				update_repeater_count_header();
			})
			//date pick
			function getDatepickerI18n() {
				var i18n = gform_i18n.datepicker;
				return {
					dayNamesMin: [
						i18n.days.sunday,
						i18n.days.monday,
						i18n.days.tuesday,
						i18n.days.wednesday,
						i18n.days.thursday,
						i18n.days.friday,
						i18n.days.saturday,
					],
					monthNamesShort: [
						i18n.months.january,
						i18n.months.february,
						i18n.months.march,
						i18n.months.april,
						i18n.months.may,
						i18n.months.june,
						i18n.months.july,
						i18n.months.august,
						i18n.months.september,
						i18n.months.october,
						i18n.months.november,
						i18n.months.december,
					],
					firstDay: i18n.firstDay,
					iconText: i18n.iconText,
				};
			}
			/**
			 * @function getDatepickerBaseOptions
			 * @description Return base options object that configures the datepicker.
			 * @param $element The datepicker trigger.
			 * @since 2.5
			 *
			 * @returns {{
			 *  suppressDatePicker: boolean,
			 *  changeMonth: boolean,
			 *  changeYear: boolean,
			 *  onClose: onClose,
			 *  yearRange: string,
			 *  dateFormat: string,
			 *  showOn: string,
			 *  dayNamesMin: *[],
			 *  monthNamesShort: *[],
			 *  beforeShow: (function(*, *): boolean),
			 *  showOtherMonths: boolean
			 * }}
			 */
			function getDatepickerBaseOptions( $element ) {
				var i18n = getDatepickerI18n();
				var isThemeDatepicker = $element.closest( '.gform_wrapper' ).length > 0;
				var isPreview = $( '#preview_form_container' ).length > 0;
				var isRTL = window.getComputedStyle($element[0], null).getPropertyValue('direction') === 'rtl';
				var formTheme = isThemeDatepicker ? $element.closest( '.gform_wrapper' ).data( 'form-theme' ) : 'gravity-theme';
				var formId = isThemeDatepicker ? $element.closest( '.gform_wrapper' ).attr( 'id' ).replace( 'gform_wrapper_', '' ) : '';
				var formPageInstance = isThemeDatepicker ? $element.closest( '.gform_wrapper' ).attr( 'data-form-index' ) : '';
				return {
					yearRange: '-100:+20',
					showOn: 'focus',
					dateFormat: 'mm/dd/yy',
					dayNamesMin: i18n.dayNamesMin,
					monthNamesShort: i18n.monthNamesShort,
					firstDay: i18n.firstDay,
					changeMonth: true,
					changeYear: true,
					isRTL: isRTL,
					showOtherMonths: isThemeDatepicker,
					suppressDatePicker: false,
					onClose: function() {
						var self = this;
						$element.focus();
						this.suppressDatePicker = true;
						setTimeout( function() {
							self.suppressDatePicker = false;
						}, 200 );
					},
					beforeShow: function( input, inst ) {
						// Remove any classes that were added before as it could have been added to a different datepicker.
						inst.dpDiv[0].classList.remove( 'gform-theme-datepicker' );
						inst.dpDiv[0].classList.remove( 'gravity-theme' );
						inst.dpDiv[0].classList.remove( 'gform-theme' );
						inst.dpDiv[0].classList.remove( 'gform-legacy-datepicker' );
						inst.dpDiv[0].classList.remove( 'gform-theme--framework' );
						inst.dpDiv[0].classList.remove( 'gform-theme--foundation' );
						inst.dpDiv[0].classList.remove( 'gform-theme--orbital' );
						if ( isThemeDatepicker ) {
							inst.dpDiv[ 0 ].classList.add( 'gform-theme-datepicker' );
							$( inst.dpDiv[ 0 ] ).attr( 'data-parent-form', formId + '_' + formPageInstance );
						}
						if ( formTheme === undefined || formTheme === 'gravity-theme' ) {
							$( inst.dpDiv[0] ).addClass( 'gravity-theme' );
						} else if ( formTheme === 'legacy' ) {
							$( inst.dpDiv[0] ).addClass( 'gform-legacy-datepicker' );
						}
						else {
							$( inst.dpDiv[0] ).addClass( 'gform-theme--' + formTheme );
							if ( formTheme === 'orbital' ) {
								$( inst.dpDiv[0] ).addClass( 'gform-theme--framework' );
								$( inst.dpDiv[0] ).addClass( 'gform-theme--foundation' );
							}
						}
						if ( isRTL && isPreview ) {
							var $inputContainer = $( input ).closest( '.gfield' );
							var rightOffset = $( document ).outerWidth() - ( $inputContainer.offset().left + $inputContainer.outerWidth() );
							inst.dpDiv[ 0 ].style.right = rightOffset + 'px';
						}
						return ! this.suppressDatePicker;
					},
				};
			}
			/**
			 * @function initSingleDatepicker
			 * @description Initialize a datepicker assigning various additional options based on the trigger element.
			 * @param $element The datepicker trigger.
			 * @since 2.4
			 */
			function initSingleDatepicker( $element ) {
				var i18n = getDatepickerI18n();
				var inputId = $element.attr( 'id' ) ? $element.attr( 'id' ) : '';
				var optionsObj = getDatepickerBaseOptions( $element );
				if ( $element.hasClass( 'dmy' ) ) {
					optionsObj.dateFormat = 'dd/mm/yy';
				} else if ( $element.hasClass( 'dmy_dash' ) ) {
					optionsObj.dateFormat = 'dd-mm-yy';
				} else if ( $element.hasClass( 'dmy_dot' ) ) {
					optionsObj.dateFormat = 'dd.mm.yy';
				} else if ( $element.hasClass( 'ymd_slash' ) ) {
					optionsObj.dateFormat = 'yy/mm/dd';
				} else if ( $element.hasClass( 'ymd_dash' ) ) {
					optionsObj.dateFormat = 'yy-mm-dd';
				} else if ( $element.hasClass( 'ymd_dot' ) ) {
					optionsObj.dateFormat = 'yy.mm.dd';
				}
				if ( $element.hasClass( 'gdatepicker_with_icon' ) ) {
					optionsObj.showOn = 'both';
					optionsObj.buttonImage = $element.parent().siblings( "[id^='gforms_calendar_icon_input']" ).val();
					optionsObj.buttonImageOnly = true;
					optionsObj.buttonText = i18n.iconText;
				} else {
					optionsObj.showOn = 'focus';
				}
				inputId = inputId.split( '_' );
				// allow the user to override the datepicker options object
				optionsObj = gform.applyFilters( 'gform_datepicker_options_pre_init', optionsObj, inputId[ 1 ], inputId[ 2 ], $element );
				$element.datepicker( optionsObj );
				// We give the input focus after selecting a date which differs from default Datepicker behavior; this prevents
				// users from clicking on the input again to open the datepicker. Let's add a manual click event to handle this.
				if ( $element.is( ':input' ) ) {
					$element.click( function() {
						$element.datepicker( 'show' );
					} );
				}
			}
		})
		$(document).on("change",".gfield--type-repeater_end input,.gfield--type-repeater_end textarea,.gfield--type-repeater_end select",function(event) {
			var id = $(this).attr("id");
			if (typeof(Storage) !== "undefined") {
				if ($(this).attr("name") != "" && typeof $(this).attr("name") != 'undefined') { 
					var type = $(this).attr("type");
					var name = $(this).attr("id");
					if( type == "checkbox" ) {
						if( $("#"+name+":checked").length > 0 ) {
							var value = $("#"+name+":checked").val();
							localStorage.setItem(id, value);
						}else{
							localStorage.removeItem(id);
						}
					}else if(type == "radio"){
						var id = $(this).attr("name");
						var  value = $(this).val();
						localStorage.setItem(id, value);
					}
					else{
						var  value = $(this).val();
						localStorage.setItem(id, value);
					}
				}
			}
		});
	})(jQuery);