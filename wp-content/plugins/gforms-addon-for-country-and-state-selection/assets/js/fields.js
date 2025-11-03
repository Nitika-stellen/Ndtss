// Namespace for frontend logic for the Country/State field
window.gfcws = window.gfcws || {};
(function($, gfcws) {
    // AJAX logic for frontend country/state selection
    gfcws.initCountryStateFilterFrontend = function() {
        $('div[name="country_state_fliter"]').each(function() {
            const field_id = $(this).attr("id");
            function toggleStateField() {
                var $dropdown = $('#' + field_id + ' select[id="' + field_id + '_2"]');
                var $textInput = $('#' + field_id + ' input.gfcws_state_text_input, #' + field_id + ' input[id="' + field_id + '_2_text"]');
                var optionCount = $dropdown.find('option').length;
                var dropdownName = $dropdown.data('original-name') || $dropdown.attr('name');
                // Store original name for later use
                if (!$dropdown.data('original-name')) {
                    $dropdown.data('original-name', dropdownName);
                }
                if (optionCount <= 1) {
                    $dropdown.addClass('gfcws-force-hide').hide();
                    $dropdown.removeAttr('name');
                    $textInput.show();
                    $textInput.attr('name', dropdownName);
                    // Set value to country name if text input is shown and empty or matches previous country
                    var countryName = $('#' + field_id + ' select[id="' + field_id + '_1"] option:selected').text();
                    if (!$textInput.data('user-typed') || !$textInput.val() || $textInput.val() === $textInput.data('last-country')) {
                        $textInput.val(countryName);
                        $textInput.data('last-country', countryName);
                    }
                    // Remove error styles from hidden dropdown
                    $dropdown.removeClass('gfield_error').removeAttr('aria-invalid').css('border', '');
                } else {
                    $dropdown.removeClass('gfcws-force-hide').show();
                    $dropdown.attr('name', dropdownName);
                    $textInput.hide();
                    $textInput.removeAttr('name');
                    // Sync value from text input to dropdown (if any)
                    if ($textInput.val()) {
                        $dropdown.val($textInput.val());
                    }
                    // Remove error styles from hidden text input
                    $textInput.removeClass('gfield_error').removeAttr('aria-invalid').css('border', '');
                }
            }
            // Track if user types in the text input (so we don't overwrite their input)
            $(document).on('input', '#' + field_id + ' input.gfcws_state_text_input, #' + field_id + ' input[id="' + field_id + '_2_text"]', function() {
                $(this).data('user-typed', true);
            });
            // On country change
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
                    dataType: 'html',
                    cache: false,
                    success: function(data) {
                        var $dropdown = $('#' + field_id + ' select[id="' + field_id + '_2"]');
                        var $textInput = $('#' + field_id + ' input.gfcws_state_text_input, #' + field_id + ' input[id="' + field_id + '_2_text"]');
                        $dropdown.html(data);
                        $dropdown.removeAttr('disabled');
                        $dropdown.removeClass('stateselection');
                        // Toggle after AJAX
                        var optionCount = $dropdown.find('option').length;
                        // Fix: define dropdownName here
                        var dropdownName = $dropdown.data('original-name') || $dropdown.attr('name');
                        if (optionCount <= 1) {
                            $dropdown.addClass('gfcws-force-hide').hide();
                            $dropdown.removeAttr('name');
                            $textInput.show();
                            $textInput.attr('name', dropdownName);
                            // Always set value to country name after AJAX
                            var countryName = $('#' + field_id + ' select[id="' + field_id + '_1"] option:selected').text();
                            $textInput.val(countryName);
                           // console.log('Set state text field to country:', countryName);
                            $textInput.data('last-country', countryName);
                            // Remove error styles from hidden dropdown
                            $dropdown.removeClass('gfield_error').removeAttr('aria-invalid').css('border', '');
                        } else {
                            $dropdown.removeClass('gfcws-force-hide').show();
                            $textInput.hide();
                        }
                    }
                });
            });
            // On page load, if a country is pre-selected, trigger AJAX to load states
            var $countrySelect = $('#' + field_id + ' select[id="' + field_id + '_1"]');
            var defaultCountry = $countrySelect.val();
            if (defaultCountry) {
                var ajaxObj = window.ajax_object || {};
                var $dropdown = $('#' + field_id + ' select[id="' + field_id + '_2"]');
                $dropdown.addClass('stateselection');
                $dropdown.attr('disabled', 'disabled');
                $.ajax({
                    type: 'get',
                    url: ajaxObj.ajaxurl,
                    data: {
                        action: 'Ajax_GFCWS_Filter_Record',
                        country: defaultCountry,
                    },
                    dataType: 'html',
                    cache: false,
                    success: function(data) {
                        var $dropdown = $('#' + field_id + ' select[id="' + field_id + '_2"]');
                        var $textInput = $('#' + field_id + ' input.gfcws_state_text_input, #' + field_id + ' input[id="' + field_id + '_2_text"]');
                        $dropdown.html(data);
                        $dropdown.removeAttr('disabled');
                        $dropdown.removeClass('stateselection');
                        // Toggle after AJAX
                        var optionCount = $dropdown.find('option').length;
                      // console.log('Page Load AJAX:', {field_id, optionCount, $dropdown, $textInput});
                        if (optionCount <= 1) {
                            $dropdown.addClass('gfcws-force-hide').hide();
                            $textInput.show();
                            $textInput.val(defaultCountry);
                        } else {
                            $dropdown.removeClass('gfcws-force-hide').show();
                            $textInput.hide();
                        }
                    }
                });
            } else {
                // If no country selected, just run the toggle
                toggleStateField();
            }

            // On form submit, ensure only the visible field has the name attribute
            var $form = $(this).closest('form');
            $form.on('submit', function() {
                var $dropdown = $('#' + field_id + ' select[id="' + field_id + '_2"]');
                var $textInput = $('#' + field_id + ' input.gfcws_state_text_input, #' + field_id + ' input[id="' + field_id + '_2_text"]');
                var dropdownName = $dropdown.data('original-name') || $dropdown.attr('name');
                if ($dropdown.is(':visible')) {
                    $dropdown.attr('name', dropdownName);
                    $textInput.removeAttr('name');
                } else if ($textInput.is(':visible')) {
                    $textInput.attr('name', dropdownName);
                    $dropdown.removeAttr('name');
                }
            });
        });
    };
    // Initialize on document ready
    $(document).ready(function() {
        gfcws.initCountryStateFilterFrontend();
    });
})(jQuery, window.gfcws);
