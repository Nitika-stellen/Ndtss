// Namespace all code under window.gfcws
window.gfcws = window.gfcws || {};
(function($, gfcws) {
    // filter state by country using ajax
    gfcws.initCountryStateFilter = function() {
        $('div[name="country_state_fliter"]').each(function() {
            const field_id = $(this).attr("id");
            $(document).on('change', '#' + field_id + ' select[id="' + field_id + '_1"]', function() {
                var ajaxObj = window.ajax_object || {};
                $('select[id="' + field_id + '_2"]').addClass('stateselection');
                let country = $('select[id="' + field_id + '_1"] option:selected').val();
                $('select[id="' + field_id + '_2"] option').remove();
                $('#' + field_id + ' select[id="' + field_id + '_2"]').attr('disabled', 'disabled');
                $.ajax({
                    type: 'get',
                    url: ajaxObj.ajaxurl,
                    data: {
                        action: 'Ajax_GFCWS_Filter_Record',
                        country: country,
                    },
                    dataType: 'json',
                    cache: false,
                    success: function(data) {
                        var $dropdown = $('#' + field_id + ' select[id="' + field_id + '_2"]');
                        var $textInput = $('#' + field_id + ' input#gfcws_state_text_input, #' + field_id + ' input[id="' + field_id + '_2_text"]');
                        $dropdown.html(data[0]);
                        $dropdown.removeAttr('disabled');
                        $dropdown.removeClass('stateselection');
                        // Check if only the placeholder is present (no real states)
                        var optionCount = $dropdown.find('option').length;
                        if (optionCount <= 1) {
                            $dropdown.hide();
                            $textInput.show();
                        } else {
                            $dropdown.show();
                            $textInput.hide();
                        }
                    }
                });
            });
        });
    };
    // Initialize on document ready
    $(document).ready(function() {
        gfcws.initCountryStateFilter();
    });
})(jQuery, window.gfcws);