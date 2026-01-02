/**
 * CPD Management Frontend JavaScript
 */

jQuery(document).ready(function($) {
    // Handle upload form submission
    $('#cpd-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var $submitText = $submitBtn.find('.submit-text');
        var $spinner = $submitBtn.find('.loading-spinner');
        var $message = $('#cpd-form-message');
        
        // Validate form
        if (!$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
        }
        
        // Prepare form data
        var formData = new FormData($form[0]);
        formData.append('action', 'cpd_upload_entry');
        formData.append('nonce', cpdFrontend.nonce);
        
        // Show loading state
        $submitBtn.prop('disabled', true);
        $submitText.hide();
        $spinner.show();
        $message.hide();
        
        // Submit via AJAX
        $.ajax({
            url: cpdFrontend.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $message.removeClass('error').addClass('success')
                        .html('<p>' + response.data.message + '</p>').show();
                    
                    // Reset form
                    $form[0].reset();
                    
                    // Reload page after delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    $message.removeClass('success').addClass('error')
                        .html('<p>' + (response.data.message || cpdFrontend.strings.error) + '</p>').show();
                    
                    $submitBtn.prop('disabled', false);
                    $submitText.show();
                    $spinner.hide();
                }
            },
            error: function() {
                $message.removeClass('success').addClass('error')
                    .html('<p>' + cpdFrontend.strings.error + '</p>').show();
                
                $submitBtn.prop('disabled', false);
                $submitText.show();
                $spinner.hide();
            }
        });
    });
    
    // Handle delete entry
    $('.delete-entry').on('click', function() {
        if (!confirm(cpdFrontend.strings.confirmDelete)) {
            return;
        }
        
        var entryId = $(this).data('entry-id');
        var $row = $(this).closest('tr');
        var $button = $(this);
        
        $button.prop('disabled', true).text('Deleting...');
        
        $.ajax({
            url: cpdFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cpd_delete_entry',
                entry_id: entryId,
                nonce: cpdFrontend.nonce
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data.message || cpdFrontend.strings.error);
                    $button.prop('disabled', false).text('Delete');
                }
            },
            error: function() {
                alert(cpdFrontend.strings.error);
                $button.prop('disabled', false).text('Delete');
            }
        });
    });
    
    // File upload preview
    $('#cpd_document').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        if (fileName) {
            $(this).next('.file-name').remove();
            $(this).after('<span class="file-name">Selected: ' + fileName + '</span>');
        }
    });
    
    // Year selector change
    $('#cpd-year-selector').on('change', function() {
        var year = $(this).val();
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('year', year);
        window.location.href = currentUrl.toString();
    });
    
    // Auto-hide success messages
    setTimeout(function() {
        $('.cpd-form-message.success').fadeOut();
    }, 5000);
});

