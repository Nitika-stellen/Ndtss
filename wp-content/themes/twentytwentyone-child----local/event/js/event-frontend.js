/**
 * Event Frontend JavaScript
 * Handles frontend event interactions and AJAX calls
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Event approval handler
    $(document).on('click', '.approve-entry', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const entryId = button.data('entry-id');
        const userId = button.data('user-id');
        const eventId = button.data('event-id');
        
        if (!entryId || !userId || !eventId) {
            showNotification('Missing required data', 'error');
            return;
        }
        
        // Show confirmation dialog
        Swal.fire({
            title: 'Approve Registration',
            text: 'Are you sure you want to approve this event registration?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Approve',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#46b450',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return approveEventRegistration(entryId, userId, eventId);
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                showNotification('Event registration approved successfully!', 'success');
                // Reload page to update status
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
        });
    });
    
    // Event rejection handler
    $(document).on('click', '.reject-entry', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const entryId = button.data('entry-id');
        const userId = button.data('user-id');
        const eventId = button.data('event-id');
        
        if (!entryId || !userId || !eventId) {
            showNotification('Missing required data', 'error');
            return;
        }
        
        // Show rejection dialog with reason input
        Swal.fire({
            title: 'Reject Registration',
            text: 'Please provide a reason for rejection:',
            input: 'textarea',
            inputPlaceholder: 'Enter rejection reason...',
            inputValidator: (value) => {
                if (!value || value.trim().length < 5) {
                    return 'Please provide a reason (at least 5 characters)';
                }
            },
            showCancelButton: true,
            confirmButtonText: 'Reject',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3232',
            showLoaderOnConfirm: true,
            preConfirm: (reason) => {
                return rejectEventRegistration(entryId, userId, eventId, reason);
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                showNotification('Event registration rejected successfully!', 'success');
                // Reload page to update status
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
        });
    });
    
    // CPD points edit handler
    $(document).on('click', '.edit-cpd', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const userId = button.data('user-id');
        const eventId = button.data('event-id');
        const currentPoints = button.data('current-points');
        
        if (!userId || !eventId) {
            showNotification('Missing required data', 'error');
            return;
        }
        
        Swal.fire({
            title: 'Edit CPD Points',
            input: 'number',
            inputValue: currentPoints,
            inputAttributes: {
                min: 0,
                step: '0.1'
            },
            showCancelButton: true,
            confirmButtonText: 'Save',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#0073aa',
            showLoaderOnConfirm: true,
            preConfirm: (cpdPoints) => {
                return updateCpdPoints(userId, eventId, cpdPoints);
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                showNotification('CPD points updated successfully!', 'success');
                // Update display
                $(`#cpd-points-${userId}-${eventId}`).text(result.value);
                // Reload page to update totals
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
        });
    });
    
    // Toggle events handler
    $(document).on('click', '.toggle-events', function(e) {
        e.preventDefault();
        
        const link = $(this);
        const list = link.closest('.event-list');
        const extra = list.find('.extra-event');
        
        if (extra.is(':visible')) {
            extra.slideUp();
            link.text('Show More');
        } else {
            extra.slideDown();
            link.text('Show Less');
        }
    });
    
    // Export PDF handler
    $(document).on('click', '#export-pdf', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Generating PDF...',
            text: 'Please wait while we generate your CPD report...',
            showConfirmButton: false,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Generate PDF
        $.ajax({
            url: event_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_cpd_pdf',
                nonce: event_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Open PDF in new tab
                    window.open(event_ajax.ajax_url + '?action=generate_cpd_pdf', '_blank');
                    Swal.close();
                    showNotification('CPD report generated successfully!', 'success');
                } else {
                    Swal.close();
                    showNotification('Failed to generate PDF: ' + response.data.message, 'error');
                }
            },
            error: function() {
                Swal.close();
                showNotification('An error occurred while generating the PDF', 'error');
            }
        });
    });
    
    // Refresh data handler
    $(document).on('click', '#refresh-data', function(e) {
        e.preventDefault();
        
        showNotification('Refreshing data...', 'info');
        location.reload();
    });
    
    // Initialize DataTables
    if ($.fn.DataTable) {
        $('#event_submitted_form, #eventTable').DataTable({
            pageLength: 25,
            order: [],
            responsive: true,
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            }
        });
    }
    
    // Helper Functions
    
    /**
     * Approve event registration
     */
    function approveEventRegistration(entryId, userId, eventId) {
        return $.ajax({
            url: event_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'event_approve_entry_ajax',
                entry_id: entryId,
                user_id: userId,
                event_id: eventId,
                nonce: event_ajax.approve_nonce
            }
        }).then(function(response) {
            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.data.message || 'Approval failed');
            }
        });
    }
    
    /**
     * Reject event registration
     */
    function rejectEventRegistration(entryId, userId, eventId, reason) {
        return $.ajax({
            url: event_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'event_reject_entry_ajax',
                entry_id: entryId,
                user_id: userId,
                event_id: eventId,
                reject_reason: reason,
                nonce: event_ajax.reject_nonce
            }
        }).then(function(response) {
            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.data.message || 'Rejection failed');
            }
        });
    }
    
    /**
     * Update CPD points
     */
    function updateCpdPoints(userId, eventId, cpdPoints) {
        return $.ajax({
            url: event_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'add_cpd_points',
                user_id: userId,
                event_id: eventId,
                cpd_points: cpdPoints,
                nonce: event_ajax.cpd_nonce
            }
        }).then(function(response) {
            if (response.success) {
                return cpdPoints;
            } else {
                throw new Error(response.data.message || 'CPD points update failed');
            }
        });
    }
    
    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        const icon = type === 'success' ? 'success' : 
                    type === 'error' ? 'error' : 
                    type === 'warning' ? 'warning' : 'info';
        
        Swal.fire({
            title: type.charAt(0).toUpperCase() + type.slice(1),
            text: message,
            icon: icon,
            timer: 3000,
            showConfirmButton: false
        });
    }
    
    /**
     * Show loading state
     */
    function showLoading(element) {
        const originalText = element.text();
        element.data('original-text', originalText);
        element.prop('disabled', true).text(event_ajax.loading_text);
    }
    
    /**
     * Hide loading state
     */
    function hideLoading(element) {
        const originalText = element.data('original-text');
        element.prop('disabled', false).text(originalText);
    }
    
    // Global error handler
    $(document).ajaxError(function(event, xhr, settings, thrownError) {
        console.error('AJAX Error:', {
            url: settings.url,
            status: xhr.status,
            error: thrownError,
            response: xhr.responseText
        });
        
        showNotification('An error occurred. Please try again.', 'error');
    });
    
    // Show admin notice if present
    const adminNotice = $('#event-admin-notice');
    if (adminNotice.length && adminNotice.text().trim()) {
        adminNotice.show();
        setTimeout(() => {
            adminNotice.fadeOut();
        }, 5000);
    }
});


