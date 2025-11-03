jQuery(document).ready(function ($) {
    $(document).bind( "gform_load_field_settings", function (event, field, form) {
        if ($("#gfrda_enable_restrict_value").is(":checked")) {
            $("#rda_enable").show();
        } else {
            $("#rda_enable").hide();
        }

        var min_val = $("#mini_date_value").val();
        if(min_val == "custom_date") {
            $(".rda_minimum_date_picker").show();
        } else {
            $(".rda_minimum_date_picker").hide();
        }

        var max_val = $("#max_date_value").val();
        if(max_val == "custom_date") {
            $(".rda_maximum_date_picker").show();
        } else {
            $(".rda_maximum_date_picker").hide();
        }



	});

    $(document).on("change", "#gfrda_enable_restrict_value", function () {
        $("#rda_enable").slideToggle();
    });

    $(document).on("change", "#mini_date_value", function () {
        var this_val = $(this).val();
        if(this_val == "custom_date") {
            $(".rda_minimum_date_picker").slideDown();
        } else {
            $(".rda_minimum_date_picker").slideUp();
        }
    });

    $(document).on("change", "#max_date_value", function () {
        var this_val = $(this).val();
        if(this_val == "custom_date") {
            $(".rda_maximum_date_picker").slideDown();
        } else {
            $(".rda_maximum_date_picker").slideUp();
        }
    });
});
