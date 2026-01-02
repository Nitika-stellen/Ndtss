jQuery(document).ready(function($) {
    // Initialize DataTable for both tables
    $('#ind_mem_submitted_form, #corp_mem_submitted_form').DataTable({
        order: [], // Disable default sorting
        pageLength: 25,
        responsive: true
    });

    // Handle certificate generation for both tables
    $(document).on('click', '.generate-cert', function() {
        var $button = $(this); // Store button reference
        var userId = $button.data('user-id');
        var userName = $button.data('user-name');
        var memberId = $button.data('member-id');
        var membershipType = $button.data('membership-type');
        var formId = $button.data('form-id');
        var memberClassification = $button.data('member-classification') || '';

        Swal.fire({
            title: 'Generate Certificate',
            text: 'Do you want to generate certificate for ' + userName + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, generate!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Generating...',
                    text: 'Please wait while we generate the certificate.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // AJAX call to generate certificate
                $.ajax({
                    url: membershipCertificates.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'generate_member_certificate',
                        user_id: userId,
                        member_id: memberId,
                        membership_type: membershipType,
                        form_id: formId,
                        member_classification: memberClassification,
                        nonce: membershipCertificates.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Certificate has been generated.',
                                icon: 'success',
                                showCancelButton: true,
                                confirmButtonText: 'Send Email',
                                cancelButtonText: 'Close',
                                confirmButtonColor: '#28a745',
                                cancelButtonColor: '#6c757d'
                            }).then((result) => {
                                // Open certificate in new tab
                                if (response.data && response.data.certificate_url) {
                                    window.open(response.data.certificate_url, '_blank');
                                }
                                
                                // If user clicked Send Email
                                if (result.isConfirmed) {
                                    sendCertificateEmail(userId, memberId, userName);
                                }
                            });
                        } else {
                            Swal.fire(
                                'Error!',
                                response.data && response.data.message ? response.data.message : 'Failed to generate certificate.',
                                'error'
                            );
                        }
                    },
                    error: function() {
                        Swal.fire(
                            'Error!',
                            'Something went wrong.',
                            'error'
                        );
                    }
                });
            }
        });
    });
    
    // Function to send certificate via email
    function sendCertificateEmail(userId, memberId, userName) {
        Swal.fire({
            title: 'Sending Email...',
            text: 'Please wait while we send the certificate.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: membershipCertificates.ajaxurl,
            type: 'POST',
            data: {
                action: 'send_certificate_email',
                user_id: userId,
                member_id: memberId,
                nonce: membershipCertificates.send_email_nonce
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire(
                        'Sent!',
                        response.data || 'Certificate has been sent successfully.',
                        'success'
                    );
                } else {
                    Swal.fire(
                        'Error!',
                        response.data || 'Failed to send email.',
                        'error'
                    );
                }
            },
            error: function() {
                Swal.fire(
                    'Error!',
                    'Something went wrong while sending email.',
                    'error'
                );
            }
        });
    }
});