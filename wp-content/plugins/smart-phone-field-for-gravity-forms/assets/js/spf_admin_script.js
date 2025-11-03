jQuery(document).ready(function ($) {
    const { __ } = wp.i18n;

    $('#gform_setting_enable_validation, #gform_setting_validation_message, #gform_setting_number_format, #gform_setting_strict_mode, #gform_setting_rtl, #gform_setting_language, #gform_setting_enable_ipinfo, #gform_setting_ipinfo_token').addClass('pro_version');

    $('#gform_setting_enable_validation input[type=checkbox], #gform_setting_strict_mode input[type=checkbox], #gform_setting_rtl input[type=checkbox], #gform_setting_enable_ipinfo input[type=checkbox]').attr('disabled', true);

    // Append Pro button
   $('.spf_global_settings_field .gform-settings-panel__title').append(
        $('<a href="https://pluginscafe.com/plugin/smart-phone-field-for-gravity-forms-pro" target="_blank" class="button primary spf-btn-pro"></a>').text(__('Upgrade to Pro version', 'smart-phone-field-for-gravity-forms'))
   );


   //Admin Settings
   $("#pcafe_tab_box div.tab_item").hide();
   $("#pcafe_tab_box div:first").show();
   $(".pcafe_menu_wrap li:first").addClass("active");

   // Change tab class and display content
   $(".pcafe_menu_wrap a").on("click", function (event) {

    if ($(this).hasClass("demo_btn")) {
        // Open in a new tab
        window.open($(this).attr("href"), "_blank");
    } else {
        // Prevent default for other links
        event.preventDefault();
        $(".pcafe_menu_wrap li").removeClass("active");
       $(this).parent().addClass("active");
       $("#pcafe_tab_box div.tab_item").hide();
       $($(this).attr("href")).show();
    }
   });

   $('.p__install').on('click', function (e) {
       $(this).find('.p_btn_text').text('Installing...');
       $(this).find('.loader').addClass('active');
   });

   $('.p__activate').on('click', function (e) { 
       $(this).find('.p_btn_text').text('Activating...');
       $(this).find('.loader').addClass('active');
   });

    $(document).bind( "gform_load_field_settings", function (event, field, form) {
        toggleSpfDefaultSetting();
        toggleSpfOptions();
        toggleCountries();
    });

    function toggleSpfDefaultSetting() {
        if ($("#spf_auto_ip_value").is(":checked")) {
            $(".spf_default_setting").hide();
        } else {
            $(".spf_default_setting").show();
        }
    }

    function toggleCountries() {
        var country = $("#spf_dropdown_countries").val();
        if( country != '') {
            $('.spf_prefer_setting').show();
        } else {
            $('.spf_prefer_setting').hide();
        }
    }

    function toggleSpfOptions() {
        var isEnabled = $("#spf_enable_value").is(":checked");
        var configType = $("#spf_configuration_type").val();
    
        if (isEnabled) {
            $("#spf_option").show();
            
            if (configType === 'custom') {
                $("#spf_custom_options").show();
            } else {
                $("#spf_custom_options").hide();
            }
        } else {
            $("#spf_option").hide();
            $("#spf_custom_options").hide();
        }
    }

    $(document).on("change", "#spf_enable_value", function () {
        toggleSpfOptions();
    });

    $(document).on("change", "#spf_auto_ip_value", function () {
        toggleSpfDefaultSetting();
    });

    $(document).on("change", "#spf_configuration_type", function () {
        toggleSpfOptions();
    });

    $(document).on("change", "#spf_dropdown_countries", function () {
        toggleCountries();
    });

});