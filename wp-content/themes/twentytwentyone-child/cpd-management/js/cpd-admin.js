/**
 * CPD Management Admin JavaScript
 */

jQuery(document).ready(function($) {
    // Helper function for SweetAlert success
    function showSuccess(message, callback) {
        Swal.fire({
            icon: 'success',
            title: cpdAdmin.strings.successTitle,
            text: message,
            confirmButtonText: 'OK',
            confirmButtonColor: '#0073aa',
            timer: 2000,
            timerProgressBar: true
        }).then(function() {
            if (callback && typeof callback === 'function') {
                callback();
            }
        });
    }
    
    // Helper function for SweetAlert error
    function showError(message) {
        Swal.fire({
            icon: 'error',
            title: cpdAdmin.strings.errorTitle,
            text: message || cpdAdmin.strings.error,
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc3232'
        });
    }
    
    // Helper function for SweetAlert confirmation
    function showConfirm(title, text, confirmText, cancelText, callback) {
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0073aa',
            cancelButtonColor: '#dc3232',
            confirmButtonText: confirmText || 'Yes',
            cancelButtonText: cancelText || 'Cancel',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed && callback && typeof callback === 'function') {
                callback();
            }
        });
    }
    
    // Handle review form submission
    $('#cpd-review-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var originalText = $submitBtn.text();
        
        // Show loading state
        Swal.fire({
            title: cpdAdmin.strings.saving,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function() {
                Swal.showLoading();
            }
        });
        
        $submitBtn.prop('disabled', true).text(cpdAdmin.strings.saving);
        
        var formData = $form.serialize();
        formData += '&action=cpd_update_points';
        
        $.ajax({
            url: cpdAdmin.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                Swal.close();
                if (response.success) {
                    showSuccess(response.data.message, function() {
                        window.location.reload();
                    });
                } else {
                    showError(response.data.message || cpdAdmin.strings.error);
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                Swal.close();
                showError(cpdAdmin.strings.error);
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Handle delete entry (if needed in admin)
    $('.cpd-delete-entry').on('click', function(e) {
        e.preventDefault();
        
        var entryId = $(this).data('entry-id');
        var $row = $(this).closest('tr');
        var $button = $(this);
        
        showConfirm(
            cpdAdmin.strings.confirmDeleteTitle,
            cpdAdmin.strings.confirmDelete,
            'Yes, Delete',
            'Cancel',
            function() {
                // Show loading
                Swal.fire({
                    title: 'Deleting...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: function() {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: cpdAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'cpd_delete_entry',
                        entry_id: entryId,
                        nonce: cpdAdmin.nonce
                    },
                    success: function(response) {
                        Swal.close();
                        if (response.success) {
                            showSuccess('Entry deleted successfully!', function() {
                                $row.fadeOut(function() {
                                    $(this).remove();
                                });
                            });
                        } else {
                            showError(response.data.message || cpdAdmin.strings.error);
                        }
                    },
                    error: function() {
                        Swal.close();
                        showError(cpdAdmin.strings.error);
                    }
                });
            }
        );
    });
    
    // Status badge styling
    $('.status-badge').each(function() {
        var status = $(this).text().toLowerCase().trim();
        $(this).addClass('status-' + status);
    });
    
    // Tooltips for status badges
    $('.status-badge').hover(function() {
        var status = $(this).text().toLowerCase().trim();
        var tooltip = '';
        
        switch(status) {
            case 'pending':
                tooltip = 'Awaiting admin review';
                break;
            case 'approved':
                tooltip = 'Entry approved and points allocated';
                break;
            case 'rejected':
                tooltip = 'Entry rejected';
                break;
        }
        
        if (tooltip) {
            $(this).attr('title', tooltip);
        }
    });
});

