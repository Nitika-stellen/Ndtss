document.addEventListener('DOMContentLoaded', function () {
    console.log('Marks Entry Script Loaded');

    if (typeof tb_show !== 'function') {
        console.error('Thickbox is not defined. Ensure it is enqueued.');
        return;
    }

    if (!window.marksEntryAjax || !marksEntryAjax.ajax_url || !marksEntryAjax.nonce) {
        console.error('marksEntryAjax object is not defined or missing properties. Ensure it is localized properly.');
        return;
    }

    document.querySelectorAll('.add-marks-button').forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();

            const modalId = this.getAttribute('href').split('inlineId=')[1];
            const method = this.getAttribute('data-method');
            const entryId = this.getAttribute('data-entry-id');

            console.log('Opening Thickbox for modal ID:', modalId);

            // Open the Thickbox modal
            tb_show('', `#TB_inline?width=600&height=400&inlineId=${modalId}`);

            // Wait for Thickbox to render the modal content
            setTimeout(() => {
                const tbWindow = document.querySelector('#TB_window');
                if (!tbWindow) {
                    console.error('Thickbox window (#TB_window) not found');
                    return;
                }

                const placeholder = tbWindow.querySelector('.form-placeholder');
                if (!placeholder) {
                    console.error('Form placeholder not found in Thickbox window for modal:', modalId);
                    return;
                }

                console.log('Placeholder found, making AJAX request for method:', method, 'entry_id:', entryId);

                placeholder.innerHTML = 'Loading form...';

                const requestBody = new URLSearchParams({
                    action: 'load_marks_form',
                    nonce: marksEntryAjax.nonce,
                    method: method,
                    entry_id: entryId,
                });
                console.log('AJAX request body:', requestBody.toString());

                fetch(marksEntryAjax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: requestBody,
                })
                    .then(response => {
                        console.log('AJAX response status:', response.status);
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('AJAX response data:', data);
                        if (data.success && data.data && data.data.form_html) {
                            // Inject the form HTML into the placeholder
                            placeholder.innerHTML = data.data.form_html;
                            $('.gform_wrapper').css('display','block');

                            // Ensure the form wrapper is visible
                            const formWrapper = placeholder.querySelector('#gform_wrapper_24');
                            if (formWrapper) {
                                formWrapper.style.display = 'block'; // Remove display:none
                                const formElement = formWrapper.querySelector('form');
                                if (formElement) {
                                    formElement.style.opacity = '1'; // Ensure form is not hidden by opacity
                                }
                            } else {
                                console.error('Form wrapper #gform_wrapper_24 not found in the response HTML');
                            }

                            // Trigger Gravity Forms initialization
                            if (typeof jQuery !== 'undefined') {
                                // Trigger gform_post_render to initialize the form
                                jQuery(document).trigger('gform_post_render', [24, 1]);

                                // Trigger gform_post_conditional_logic to handle visibility and conditional logic
                                jQuery(document).trigger('gform_post_conditional_logic', [24, null, true]);

                                // Manually execute the initialization scripts included in the response
                                if (typeof gform !== 'undefined' && gform.initializeOnLoaded) {
                                    gform.initializeOnLoaded(() => {
                                        console.log('Manually triggering Gravity Forms initialization');
                                        // Ensure the form is visible
                                        jQuery('#gform_wrapper_24').show();
                                        jQuery('#gform_wrapper_24 form').css('opacity', '');

                                        // Trigger post-render events
                                        if (typeof gform.core !== 'undefined' && gform.core.triggerPostRenderEvents) {
                                            gform.core.triggerPostRenderEvents(24, 1);
                                        }

                                        // Re-apply conditional logic
                                        if (typeof gf_apply_rules !== 'undefined') {
                                            gf_apply_rules(24, [5, 6, 8, 9, 11, 12, 10, 13, 14, 16, 15, 17, 25], true);
                                        }
                                    });
                                }
                            } else {
                                console.warn('jQuery or Gravity Forms scripts not available for initialization');
                            }

                            console.log('Gravity Form loaded and initialized in modal:', modalId);
                        } else {
                            const errorMessage = data.data && data.data.message ? data.data.message : 'Failed to load form - no form_html in response';
                            placeholder.innerHTML = 'Error: ' + errorMessage;
                            console.error('Failed to load form:', errorMessage);
                        }
                    })
                    .catch(error => {
                        placeholder.innerHTML = 'Error loading form';
                        console.error('AJAX error:', error);
                    });
            }, 1000);
        });
    });
});