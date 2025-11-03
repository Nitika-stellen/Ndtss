<?php
function custom_login_form() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        return '<p>Welcome, ' . esc_html($current_user->user_login) . '! You are already logged in.</p>';
    }
    ob_start();
    ?>
    <div class="form">
        <h2>Login</h2>
        <div id="login-message"></div> <!-- Add message container for displaying login errors/success -->
        <form id="custom-login-form" method="POST">
            <div class="mb-15">
                <label for="username">Email Address</label>
                <input type="text" id="username" name="username">
            </div>
            <div class="mb-15">
                <label for="password">Password</label>
                <div class="password-container">
                    <input type="password" id="password" name="password">
                    <span class="toggle-password" onclick="togglePasswordVisibility('password', this)">
                        <i class="fa fa-eye" aria-hidden="true"></i>
                    </span>
                </div>
            </div>
            <p>
                <a class="forgot_pass" href="<?php echo site_url('/forgot-password/'); ?>">Forgot Password?</a>
            </p>
            <div class="password-container">
                <input type="submit" id="submit-login" name="submit_login" value="Login">
                <div id="loader" style="display:none;">
                </div>
            </div>
           
        </form>
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#custom-login-form').submit(function(event) {
                event.preventDefault(); // Prevent the form from submitting

                var username = $('#username').val();
                var password = $('#password').val();

                // Check if fields are empty
                if (username === '' || password === '') {
                    $('#login-message').html('<div class="error invalid_error">Please fill in both fields.</div>');
                    return;
                }

                // Show loader and hide login button while processing
                $('#loader').show();
               // $('#submit-login').hide();

                // Prepare the data to send in the AJAX request
                var formData = {
                    'username': username,
                    'password': password,
                    'action': 'custom_handle_login' // This is the action for the PHP function
                };

                // Use jQuery AJAX to send the request
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>', // Use the correct AJAX URL
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        $('#loader').hide(); // Hide loader after processing

                        if (response.success) {
                            
                            $('#login-message').html('<div class="success">' + response.data.message + '</div>');
                            window.location.href = 'https://sistagging.com/ndtss/user-profile'; // Redirect user on success
                        } else {
                            $('#login-message').html('<div class="error invalid_error">' + response.data.message + '</div>');
                            $('#submit-login').show(); // Show login button again on failure
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#loader').hide(); // Hide loader in case of an error
                        $('#login-message').html('<div class="error">An error occurred. Please try again.</div>');
                        $('#submit-login').show(); // Show login button again on error
                    }
                });
            });
        });

        function togglePasswordVisibility(id, element) {
            var inputField = document.getElementById(id);
            if (inputField.type === "password") {
                inputField.type = "text";
                element.innerHTML = '<i class="fa fa-eye-slash" aria-hidden="true"></i>'; // Change icon to closed eye
            } else {
                inputField.type = "password";
                element.innerHTML = '<i class="fa fa-eye" aria-hidden="true"></i>';
            }
        }
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('custom_login_form', 'custom_login_form');

function custom_handle_login() {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username_or_email = sanitize_text_field($_POST['username']);
        $password = sanitize_text_field($_POST['password']);
        
        if (empty($username_or_email) || empty($password)) {
            wp_send_json_error(['message' => 'Please fill in both fields.']);
        }

        // Check if it's an email or username
        if (is_email($username_or_email)) {
            $user = get_user_by('email', $username_or_email);
         } 
            //else {
        //     $user = get_user_by('login', $username_or_email);
        // }

        // If user exists, check password and account status
        if ($user && wp_check_password($password, $user->user_pass, $user->ID)) {
            // Check if the account is inactive
            $account_status = get_user_meta($user->ID, 'user_account_status', true);
            if ($account_status === 'inactive') {
                wp_send_json_error(['message' => 'Your account is inactive. Please contact support for assistance.']);
            }

            // If the account is active, proceed with login
            $creds = array(
                'user_login'    => $user->user_login,
                'user_password' => $password,
                'remember'      => true,
            );
            $login = wp_signon($creds, false);

            if (!is_wp_error($login)) {
                wp_send_json_success([
                    'message' => 'Login successful! Redirecting...',
                    'redirect' => home_url('/user-profile/') // Redirect URL after successful login
                ]);

            } else {
                wp_send_json_error(['message' => 'Login failed. Please try again.']);
            }
        } else {
            wp_send_json_error(['message' => 'Invalid username or password. Please try again.']);
        }
    }

    wp_die(); // Terminate the request after processing
}

add_action('wp_ajax_custom_handle_login', 'custom_handle_login');
add_action('wp_ajax_nopriv_custom_handle_login', 'custom_handle_login');
