jQuery(document).ready(function($) {
    // Initialize DataTable for both tables
    $('#ind_mem_submitted_form, #corp_mem_submitted_form').DataTable({
        order: [], // Disable default sorting
        pageLength: 25,
        responsive: true
    });

    // Handle certificate generation for both tables
    $(document).on('click', '.generate-cert', function() {
        var userId = $(this).data('user-id');
        var userName = $(this).data('user-name');
        var memberId = $(this).data('member-id');

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
                        nonce: membershipCertificates.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire(
                                'Success!',
                                'Certificate has been generated.',
                                'success'
                            ).then(() => {
                                if (response.data && response.data.certificate_url) {
                                    window.open(response.data.certificate_url, '_blank');
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
});