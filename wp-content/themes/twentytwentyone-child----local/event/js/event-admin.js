/**
 * Event Admin JavaScript
 * Handles admin interface interactions and AJAX calls
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize DataTables
    if ($.fn.DataTable) {
        $('#event_submitted_form, #eventTable').DataTable({
            pageLength: 25,
            order: [],
            responsive: true,
            dom: 'Bfrtip',
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ],
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
    
    // Event approval handler
    $(document).on('click', '.approve-entry', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const entryId = button.data('entry-id');
        const userId = button.data('user-id');
        const eventId = button.data('event-id');
        
        if (!entryId || !userId || !eventId) {
            showAdminNotification('Missing required data', 'error');
            return;
        }
        
        // Show loading state
        showLoading(button);
        
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
            hideLoading(button);
            
            if (result.isConfirmed) {
                showAdminNotification('Event registration approved successfully!', 'success');
                // Update button state
                button.removeClass('button-success').addClass('button-secondary').text('Approved').prop('disabled', true);
                // Update status in table
                const row = button.closest('tr');
                row.find('.status').html('<span class="status-approved">Approved</span>');
                // Hide reject button
                row.find('.reject-entry').hide();
            }
        }).catch((error) => {
            hideLoading(button);
            showAdminNotification('Error: ' + error.message, 'error');
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
            showAdminNotification('Missing required data', 'error');
            return;
        }
        
        // Show loading state
        showLoading(button);
        
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
            hideLoading(button);
            
            if (result.isConfirmed) {
                showAdminNotification('Event registration rejected successfully!', 'success');
                // Update button state
                button.removeClass('button-danger').addClass('button-secondary').text('Rejected').prop('disabled', true);
                // Update status in table
                const row = button.closest('tr');
                row.find('.status').html('<span class="status-rejected">Rejected</span>');
                // Hide approve button
                row.find('.approve-entry').hide();
            }
        }).catch((error) => {
            hideLoading(button);
            showAdminNotification('Error: ' + error.message, 'error');
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
            showAdminNotification('Missing required data', 'error');
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
                showAdminNotification('CPD points updated successfully!', 'success');
                // Update display
                $(`#cpd-points-${userId}-${eventId}`).text(result.value);
                // Update button data
                button.data('current-points', result.value);
            }
        }).catch((error) => {
            showAdminNotification('Error: ' + error.message, 'error');
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
        
        const button = $(this);
        showLoading(button);
        
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
            url: event_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_cpd_pdf'
            },
            success: function(response) {
                hideLoading(button);
                Swal.close();
                
                if (response.success) {
                    // Open PDF in new tab
                    window.open(event_admin_ajax.ajax_url + '?action=generate_cpd_pdf', '_blank');
                    showAdminNotification('CPD report generated successfully!', 'success');
                } else {
                    showAdminNotification('Failed to generate PDF: ' + response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                hideLoading(button);
                Swal.close();
                showAdminNotification('An error occurred while generating the PDF', 'error');
                console.error('PDF Generation Error:', error);
            }
        });
    });
    
    // Refresh data handler
    $(document).on('click', '#refresh-data', function(e) {
        e.preventDefault();
        
        const button = $(this);
        showLoading(button);
        
        showAdminNotification('Refreshing data...', 'info');
        
        setTimeout(() => {
            hideLoading(button);
            location.reload();
        }, 1000);
    });
    
    // Bulk actions handler
    $(document).on('click', '#bulk-approve', function(e) {
        e.preventDefault();
        
        const selectedRows = $('.approve-entry:not(:disabled)');
        if (selectedRows.length === 0) {
            showAdminNotification('No pending registrations to approve', 'warning');
            return;
        }
        
        Swal.fire({
            title: 'Bulk Approve',
            text: `Are you sure you want to approve ${selectedRows.length} registrations?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Approve All',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#46b450',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return bulkApproveRegistrations(selectedRows);
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                showAdminNotification('Bulk approval completed!', 'success');
                location.reload();
            }
        });
    });
    
    // Helper Functions
    
    /**
     * Approve event registration
     */
    function approveEventRegistration(entryId, userId, eventId) {
        return $.ajax({
            url: event_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'event_approve_entry_ajax',
                entry_id: entryId,
                user_id: userId,
                event_id: eventId,
                nonce: event_admin_ajax.approve_nonce
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
            url: event_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'event_reject_entry_ajax',
                entry_id: entryId,
                user_id: userId,
                event_id: eventId,
                reject_reason: reason,
                nonce: event_admin_ajax.reject_nonce
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
            url: event_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'add_cpd_points',
                user_id: userId,
                event_id: eventId,
                cpd_points: cpdPoints,
                nonce: event_admin_ajax.cpd_nonce
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
     * Bulk approve registrations
     */
    function bulkApproveRegistrations(selectedRows) {
        const promises = [];
        
        selectedRows.each(function() {
            const button = $(this);
            const entryId = button.data('entry-id');
            const userId = button.data('user-id');
            const eventId = button.data('event-id');
            
            if (entryId && userId && eventId) {
                promises.push(approveEventRegistration(entryId, userId, eventId));
            }
        });
        
        return Promise.all(promises);
    }
    
    /**
     * Show admin notification
     */
    function showAdminNotification(message, type = 'info') {
        const notice = $('#event-admin-notice');
        const noticeClass = type === 'success' ? 'notice-success' : 
                          type === 'error' ? 'notice-error' : 
                          type === 'warning' ? 'notice-warning' : 'notice-info';
        
        notice.removeClass('notice-success notice-error notice-warning notice-info')
              .addClass(noticeClass)
              .find('p')
              .text(message);
        
        notice.show();
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            notice.fadeOut();
        }, 5000);
    }
    
    /**
     * Show loading state
     */
    function showLoading(element) {
        const originalText = element.text();
        element.data('original-text', originalText);
        element.prop('disabled', true).text(event_admin_ajax.loading_text);
    }
    
    /**
     * Hide loading state
     */
    function hideLoading(element) {
        const originalText = element.data('original-text');
        if (originalText) {
            element.prop('disabled', false).text(originalText);
        }
    }
    
    // Global error handler
    $(document).ajaxError(function(event, xhr, settings, thrownError) {
        console.error('AJAX Error:', {
            url: settings.url,
            status: xhr.status,
            error: thrownError,
            response: xhr.responseText
        });
        
        showAdminNotification('An error occurred. Please try again.', 'error');
    });
    
    // Auto-refresh data every 5 minutes
    setInterval(function() {
        if (document.visibilityState === 'visible') {
            // Only refresh if page is visible
            const refreshButton = $('#refresh-data');
            if (refreshButton.length) {
                refreshButton.click();
            }
        }
    }, 300000); // 5 minutes
    
    // Show admin notice if present
    const adminNotice = $('#event-admin-notice');
    if (adminNotice.length && adminNotice.text().trim()) {
        adminNotice.show();
        setTimeout(() => {
            adminNotice.fadeOut();
        }, 5000);
    }
});


