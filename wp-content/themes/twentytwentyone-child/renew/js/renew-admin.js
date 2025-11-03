/**
 * Global Functions for Renew Admin
 */

// Certificate Generation Handler
function initCertificateGeneration() {
    $('.generate-certificate-btn').on('click', function(e) {
        e.preventDefault();

        const submissionId = $(this).data('submission-id');
        if (!submissionId) {
            alert('Error: Submission ID not found');
            return;
        }

        if (!confirm('Are you sure you want to generate the renewed certificate? This action cannot be undone.')) {
            return;
        }

        generateCertificate(submissionId);
    });
}

// Generate Certificate via AJAX
function generateCertificate(submissionId) {
    const $button = $(`.generate-certificate-btn[data-submission-id="${submissionId}"]`);
    const originalText = $button.text();

    // Show loading state
    $button.prop('disabled', true).html('<span class="spinner"></span> Generating...');

    $.ajax({
        url: renewAdmin.ajax_url,
        type: 'POST',
        data: {
            action: 'generate_certificate',
            submission_id: submissionId,
            nonce: renewAdmin.nonce
        },
        success: function(response) {
            if (response.success) {
                // Show success message
                showNotice('Certificate generated successfully!', 'success');

                // Reload the page to show updated content
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            } else {
                showNotice(response.data || 'Error generating certificate', 'error');
                $button.prop('disabled', false).text(originalText);
            }
        },
        error: function(xhr, status, error) {
            showNotice('AJAX Error: ' + error, 'error');
            $button.prop('disabled', false).text(originalText);
        }
    });
}

// Approval confirmation
window.confirmApproval = function(submissionId) {
    Swal.fire({
        title: 'Approve Renewal?',
        text: 'Are you sure you want to approve this renewal/recertification submission?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, approve it!',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return new Promise((resolve) => {
                setTimeout(() => {
                    resolve();
                }, 500);
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Processing...',
                text: 'Approving submission...',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            window.location.href = renewAdmin.base_url + '&action=approve&submission_id=' + submissionId;
        }
    });
};

// Rejection confirmation
window.confirmRejection = function(submissionId) {
    Swal.fire({
        title: 'Reject Renewal?',
        text: 'Are you sure you want to reject this renewal/recertification submission?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, reject it!',
        cancelButtonText: 'Cancel',
        input: 'textarea',
        inputPlaceholder: 'Please provide a reason for rejection...',
        inputValidator: (value) => {
            if (!value) {
                return 'You need to provide a reason for rejection!';
            }
        },
        showLoaderOnConfirm: true,
        preConfirm: (reason) => {
            return new Promise((resolve) => {
                setTimeout(() => {
                    resolve(reason);
                }, 500);
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Processing...',
                text: 'Rejecting submission...',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            // Create form and submit
            const form = $('<form>', {
                method: 'POST',
                action: renewAdmin.base_url + '&action=reject&submission_id=' + submissionId
            });
            form.append($('<input>', {
                type: 'hidden',
                name: 'reject_reason',
                value: result.value
            }));
            form.append($('<input>', {
                type: 'hidden',
                name: 'renew_nonce',
                value: renewAdmin.nonce
            }));
            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'reject'
            }));
            form.append($('<input>', {
                type: 'hidden',
                name: 'submission_id',
                value: submissionId
            }));
            $('body').append(form);
            form.submit();
        }
    });
};

// Certificate generation confirmation
window.confirmCertificateGeneration = function(submissionId) {
    // Check if certificate is already being generated
    const existingButton = document.querySelector(`.generate-certificate-btn[data-submission-id="${submissionId}"]`);
    if (existingButton && existingButton.disabled) {
        Swal.fire({
            title: 'Certificate Already Processing',
            text: 'A certificate is already being generated for this submission. Please wait...',
            icon: 'info',
            showConfirmButton: false,
            allowOutsideClick: false,
            showCloseButton: false
        });
        return;
    }

    // Determine method from the button data attribute
    const triggerBtn = document.querySelector(`#certificateForm${submissionId} button[type="button"]`);
    const method = triggerBtn ? (triggerBtn.getAttribute('data-method') || 'CPD') : 'CPD';
    const isRecert = method === 'RECERT';

    const label = isRecert ? 'Recertification' : 'Renewal';
    Swal.fire({
        title: isRecert ? 'Generate ReCertificate?' : 'Generate Renewed Certificate?',
        html: `
            <div style="text-align: left; margin: 20px 0;">
                <p><strong>Certificate Details:</strong></p>
                <ul style="margin-left: 20px;">
                    <li>Issue Date: <strong>Auto-calculated from original certificate expiry</strong></li>
                    <li>Certificate Validity: <strong>5 years from issue date</strong></li>
                    <li>Logic: If original expiry is in the future, use that date; otherwise use current date</li>
                    <li>Certificate Number: <strong>Original number + "${isRecert ? '-02' : '-01'}"</strong></li>
                </ul>
                <p style="margin-top: 15px; color: #0c5460; background: #d1ecf1; padding: 10px; border: 1px solid #bee5eb; border-radius: 4px;">
                    <strong>Note:</strong> The issue date will be automatically calculated based on the original certificate's expiry date to ensure proper certificate continuity.
                </p>
                <p style="margin-top: 15px;">Are you sure you want to generate this ${isRecert ? 'recertified certificate' : 'renewed certificate'}?</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, generate certificate!',
        cancelButtonText: 'Cancel',
        width: '500px',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return new Promise((resolve) => {
                setTimeout(() => {
                    resolve();
                }, 500);
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Generating Certificate...',
                text: 'Please wait while we generate your ' + (isRecert ? 'recertified certificate...' : 'renewed certificate...'),
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            // Disable the button to prevent multiple submissions
            if (existingButton) {
                existingButton.disabled = true;
                existingButton.innerHTML = '<span class="spinner"></span> Generating...';
            }

            document.getElementById('certificateForm' + submissionId).submit();
        }
    });
};

// Tab Navigation
function initTabNavigation() {
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();

        const target = $(this).attr('href');
        if (!target) return;

        // Remove active class from all tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Hide all tab contents
        $('.tab-content').hide();

        // Show target tab content
        $(target).show();
    });
}

// Form Validation
function initFormValidation() {
    $('.renew-admin-form').on('submit', function(e) {
        const $form = $(this);
        let isValid = true;

        // Clear previous errors
        $('.form-error', $form).remove();
        $('.form-group', $form).removeClass('has-error');

        // Validate required fields
        $('input[required], select[required], textarea[required]', $form).each(function() {
            const $field = $(this);
            if (!$field.val().trim()) {
                showFieldError($field, 'This field is required');
                isValid = false;
            }
        });

        if (!isValid) {
            e.preventDefault();
            showNotice('Please fill in all required fields', 'error');
        }
    });
}

// Show field error
function showFieldError($field, message) {
    $field.addClass('has-error');
    $field.after('<div class="form-error" style="color: #dc3545; font-size: 12px; margin-top: 4px;">' + message + '</div>');
}

// CPD Points Calculations
function initCPDCalculations() {
    $('.cpd-points-input').on('input', function() {
        calculateTotalCPD();
    });

    function calculateTotalCPD() {
        let totalPoints = 0;
        $('.cpd-points-input').each(function() {
            const value = parseFloat($(this).val()) || 0;
            totalPoints += value;
        });

        $('.total-cpd-points').text(totalPoints.toFixed(2));

        // Update validation status
        const $totalField = $('.total-cpd-points-display');
        if ($totalField.length) {
            $totalField.val(totalPoints.toFixed(2));
        }
    }
}

// Email Template Toggles
function initEmailTemplateToggles() {
    $('.toggle-btn').on('click', function(e) {
        e.preventDefault();

        const target = $(this).data('target');
        if (!target) return;

        const $content = $('#' + target);
        const $button = $(this);

        if ($content.is(':visible')) {
            $content.slideUp();
            $button.html($button.html().replace('▼', '▶'));
        } else {
            $content.slideDown();
            $button.html($button.html().replace('▶', '▼'));
        }
    });
}

// Success Messages
function initSuccessMessages() {
    // Show success messages based on URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('updated') === '1') {
        Swal.fire({
            title: 'Success!',
            text: 'The submission has been updated successfully.',
            icon: 'success',
            timer: 3000,
            showConfirmButton: false
        });
    } else if (urlParams.get('cpd_updated') === '1') {
        Swal.fire({
            title: 'Success!',
            text: 'CPD points have been updated successfully.',
            icon: 'success',
            timer: 3000,
            showConfirmButton: false
        });
    } else if (urlParams.get('certificate_generated') === '1') {
        Swal.fire({
            title: 'Certificate Generated!',
            text: 'The renewed certificate has been generated successfully.',
            icon: 'success',
            timer: 3000,
            showConfirmButton: false
        });
    }
}

// Show Notice
function showNotice(message, type) {
    const noticeClass = type === 'success' ? 'notice-success' :
                      type === 'error' ? 'notice-error' :
                      'notice-info';

    const $notice = $('<div class="notice ' + noticeClass + '">' + message + '</div>');
    $('.wrap h1').after($notice);

    // Auto-remove after 5 seconds
    setTimeout(function() {
        $notice.fadeOut(function() {
            $(this).remove();
        });
    }, 5000);
}

/**
 * Document Ready Functions
 */
jQuery(document).ready(function($) {
    'use strict';

    // Initialize all functionality
    initCertificateGeneration();
    initTabNavigation();
    initFormValidation();
    initCPDCalculations();
    initEmailTemplateToggles();
    initSuccessMessages();

    // File thumbnail preview functionality
    $('.file-thumbnail').on('click', function() {
        const fileUrl = $(this).data('file-url');
        const fileName = $(this).data('file-name');

        if (fileUrl) {
            window.open(fileUrl, '_blank');
        }
    });

    // Download file functionality
    $('.download-file').on('click', function(e) {
        e.preventDefault();
        const fileUrl = $(this).attr('href');
        const fileName = $(this).data('file-name');

        Swal.fire({
            title: 'Download File',
            text: 'Do you want to download ' + fileName + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, download it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.open(fileUrl, '_blank');
            }
        });
    });

    // View Certificate Handler
    $('.view-certificate-btn').on('click', function(e) {
        e.preventDefault();

        const url = $(this).attr('href');
        if (url) {
            window.open(url, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
        }
    });

    // Download Certificate Handler
    $('.download-certificate-btn').on('click', function(e) {
        const url = $(this).attr('href');
        if (url) {
            // Create a temporary link and trigger download
            const link = document.createElement('a');
            link.href = url;
            link.download = $(this).data('filename') || 'certificate.pdf';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    });

    // Update notes confirmation
    $('form').on('submit', function(e) {
        const form = $(this);
        const action = form.find('input[name="action"]').val();

        if (action === 'update_notes') {
            e.preventDefault();
            Swal.fire({
                title: 'Update Notes?',
                text: 'Are you sure you want to update the admin notes?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, update notes!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Updating...',
                        text: 'Updating admin notes...',
                        icon: 'info',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    form.off('submit').submit();
                }
            });
        } else if (action === 'update_submission') {
            e.preventDefault();
            Swal.fire({
                title: 'Update Submission?',
                text: 'Are you sure you want to update this submission data?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, update it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Updating...',
                        text: 'Updating submission data...',
                        icon: 'info',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    form.off('submit').submit();
                }
            });
        } else if (action === 'update_cpd_points') {
            e.preventDefault();
            Swal.fire({
                title: 'Update CPD Points?',
                text: 'Are you sure you want to update the CPD points data?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, update points!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Updating...',
                        text: 'Updating CPD points...',
                        icon: 'info',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    form.off('submit').submit();
                }
            });
        }
    });
});
