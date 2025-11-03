jQuery(document).ready(function($) {
    // Edit Basic Profile
    $('#edit-basic-profile').on('click', function() {
        $('#basic-profile-form input').prop('disabled', false);
        $('#edit-basic-profile').hide();
        $('#save-basic-profile').show();
    });

    // Handle AJAX form submission for Basic Profile
    $('#basic-profile-form').on('submit', function(e) {
        e.preventDefault(); // Prevent form submission

        var formData = new FormData(this); // Create form data

        // Show loader, disable button
        $('#loader-basic-profile').show();
        $('#save-basic-profile').prop('disabled', true).text('Saving...');

        // Make AJAX call
        $.ajax({
            url: profileAjax.ajax_url, // Use the localized ajax_url passed from PHP
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#loader-basic-profile').hide(); // Hide loader
                $('#save-basic-profile').prop('disabled', false).text('Save'); // Enable button
                $('#basic-profile-result').html('<p class="success-message">' + response.data.message + '</p>');
                $('#basic-profile-form input').prop('disabled', true); // Disable inputs after saving
                $('#edit-basic-profile').show();
                $('#save-basic-profile').hide();
            },
            error: function(response) {
                $('#loader-basic-profile').hide(); // Hide loader
                $('#save-basic-profile').prop('disabled', false).text('Save'); // Enable button
                $('#basic-profile-result').html('<p class="error-message">Error updating profile.</p>');
            }
        });
    });
});
