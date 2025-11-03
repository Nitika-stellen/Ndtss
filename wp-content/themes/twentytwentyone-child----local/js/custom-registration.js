jQuery(document).ready(function($) {
    $("#custom-registration-form").submit(function(e) {
        e.preventDefault(); // Prevent the default form submission

        var first_name = $('#first_name').val();
        var last_name = $('#last_name').val();
        var email = $('#email').val();
        var mobile = $('#mobile').val();
        var password = $('#password').val();
        var confirm_password = $('#confirm_password').val();

        // Clear previous messages
        $('#registration-message').html('');
        console.log('hhg'); 
        console.log(custom_registration_params.ajax_url);
        
        $.ajax({
            url: custom_registration_params.ajax_url, // Use the localized AJAX URL
            type: 'POST',
            data: {
                action: 'custom_handle_registration',
                first_name: first_name,
                last_name: last_name,
                email: email,
                mobile: mobile,
                password: password,
                confirm_password: confirm_password,
            },
            beforeSend: function() {
                $('#registration-message').html('<p>Processing your registration...</p>');
            },
            success: function(response) {
                if (response.success) {
                    $('#registration-message').html(response.data.message);
                } else {
                    $('#registration-message').html('<div class="error">' + response.data.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#registration-message').html('<div class="error">There was an error processing your registration. Please try again.</div>');
            }
        });
    });
});
