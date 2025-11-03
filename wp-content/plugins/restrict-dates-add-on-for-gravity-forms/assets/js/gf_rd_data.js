"use strict";
(function ($) {
var $j = jQuery.noConflict();

var GFRDA_Free = {
    get_disable_dates: function( specificDates ) {
        var disableDates;

        if ( specificDates ) {
            disableDates = specificDates.split( "," );
        } else {
            disableDates = [];
        }

        return disableDates;
    }
};

$j(document).bind("gform_post_render", function (event, form_id) {
    var gfaacData = window["gfrdMainJsVars_" + form_id];

    if (!gfaacData) {
        return;
    }

    var getFieldsData = gfaacData.elements;

    gform.addFilter(
        "gform_datepicker_options_pre_init",
        function (optionsObj, formId, fieldId) {
            $j.each(getFieldsData, function (index, name) {
                var ajaxdata = jQuery.parseJSON(name);

                if (ajaxdata["readOnlyDateGField"] === true) {
                    $j(
                        "#input_" + ajaxdata["formId"] + "_" + ajaxdata["id"]
                    ).attr("readonly", "readonly");
                }

                if (formId == ajaxdata["formId"] && fieldId == ajaxdata["id"]) {
                    if (ajaxdata["rdaMinimumDateGField"]) {
                        var min_date = "";
                        if (ajaxdata["rdaMinimumDateGField"] == "custom_date") {
                            min_date = new Date(
                                ajaxdata["rdaMinDatePickGField"]
                            );
                        } else {
                            min_date = new Date();
                        }
                        optionsObj.minDate = min_date;
                    }

                    if( ajaxdata['rdaWeekStartDayGField'] ) {
                        optionsObj.firstDay = ajaxdata['rdaWeekStartDayGField'];
                    }

                    if (ajaxdata["rdaMaximumDateGField"]) {
                        var max_date = "";
                        if (ajaxdata["rdaMaximumDateGField"] == "custom_date") {
                            max_date = new Date(
                                ajaxdata["rdaMaxDatePickGField"]
                            );
                        } else {
                            max_date = new Date();
                        }
                        optionsObj.maxDate = max_date;
                    }

                    optionsObj.beforeShowDay = function (date) {
                        var day = date.getDay();
                            var currentDate = jQuery.datepicker.formatDate( "mm/dd/yy", date ),
                            disableDates = GFRDA_Free.get_disable_dates(ajaxdata["rdaDisableSDatesGField"]);
                        
                        if (disableDates) {
                            if (disableDates.indexOf(currentDate) >= 0) {
                                return [false];
                            }
                        }

                        if (ajaxdata["rdaWeeklyDateGField"]) {
                            return [day != ajaxdata["rdaWeeklyDateGField"]];
                        }

                        return [true];
                    };
                    
                }
            });

            return optionsObj;
        }
    );
});

})(jQuery);