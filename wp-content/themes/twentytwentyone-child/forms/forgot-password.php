<?php

function custom_password_reset_form() {
    // Check if the user is already logged in
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        echo '<div class="success">You are already logged in as ' . esc_html($current_user->first_name) . '. Redirecting to your profile...</div>';
        
        // Redirect to user profile page
        wp_redirect(home_url('/user-profile/'));
        exit;
    }

    ob_start();

    // Check if the URL contains a reset key and user login
    if ( isset( $_GET['key'] ) && isset( $_GET['login'] ) ) {
        // Show reset password form
        custom_show_reset_password_form();
    } else {
        // Show forgot password form
        custom_show_forgot_password_form();
    }

    return ob_get_clean();
}
add_shortcode('custom_password_reset_form', 'custom_password_reset_form');
function custom_show_forgot_password_form() {
    ?>
    <div class="form">
        <h2>Forgot Password</h2>
        <div id="success-message"></div>
        <form id="forgot-password-form" method="POST">
            <p>
                <label for="user_login">Enter your email address</label>
                <input type="email" name="user_login" id="user_login" required>
            </p>
            <p>
                <input type="submit" name="submit_forgot_password" value="Reset Password">
            </p>
            <div id="forgot-password-message"></div>
        </form>
        <div id="invalid"></div>
    </div>
    <script type="text/javascript">
        document.getElementById("forgot-password-form").addEventListener("submit", function(event) {
            var email = document.getElementById("user_login").value;
            if (email === "") {
                event.preventDefault();
                document.getElementById("forgot-password-message").innerHTML = "Please enter your email.";
            }
        });
    </script>
    <?php

    if (isset($_POST['submit_forgot_password'])) {
        custom_handle_forgot_password();
    }
}

function custom_handle_forgot_password() {
    if (isset($_POST['user_login'])) {
        $user_login = sanitize_email($_POST['user_login']);
        $user = get_user_by('email', $user_login);
        
        if (!$user) {?>
            <script>
               document.getElementById("forgot-password-message").innerHTML = '<div class="error invalid_error">Invalid email address. Please try again.</div>';
           </script>
           <?php
           // echo '<div class="error invalid_error">Invalid email address. Please try again.</div>';
           return;
       }

        // Generate reset token and URL
       $reset_key = get_password_reset_key($user);
       $reset_url = home_url('/forgot-password/') . '?key=' . $reset_key . '&login=' . rawurlencode($user->user_login);

        // Subject
       $first_name = get_user_meta($user->ID, 'first_name', true);
       $subject = 'Password Reset Request';

// Message body with professional tone and layout
       $email_content = '
       <p>Dear ' . esc_html($first_name) . ',</p>
       <p>We received a request to reset the password for your account. If you made this request, please click the link below to reset your password:</p>
       <p><a href="' . esc_url($reset_url) . '" class="btn">Reset Your Password</a></p>
       <p>If the above button does not work, you can copy and paste the following link into your browser:</p>
       <p><a href="' . esc_url($reset_url) . '">' . esc_url($reset_url) . '</a></p>
       <p>If you did not request a password reset, please disregard this email or contact us for further assistance.</p>

       ';
       $message = get_email_template($subject, $email_content);
       add_filter('wp_mail_content_type', function() { return 'text/html'; });
       wp_mail($user_login, $subject, $message);
       remove_filter('wp_mail_content_type', function() { return 'text/html'; });
       
       ?>
       <script>
           document.getElementById("success-message").innerHTML = '<div class="success">A reset link has been sent to your email.</div>';
       </script>
       <?php
   }
}

function custom_show_reset_password_form() {
    ?>
    <div class="form">
        <h2>Reset Password</h2>
        <div id="success-message"></div>    
        <form id="reset-password-form" method="POST">
            <p>
                <label for="new_password">New Password</label>
                <div class="password-container">
                  <input type="password" name="new_password" id="new_password" >
                  <span class="toggle-password" onclick="togglePasswordVisibility('new_password', this)"><i class="fa fa-eye" aria-hidden="true"></i></span>
              </div>    
          </p>
          <p>
            <label for="confirm_password">Confirm Password</label>
            <div class="password-container">
                <input type="password" name="confirm_password" id="confirm_password" >
                <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password', this)"><i class="fa fa-eye" aria-hidden="true"></i></span>
            </div>
        </p>
        <p>
            <input type="submit" name="submit_reset_password" value="Reset Password">
        </p>
        <div id="reset-password-message"></div>
    </form>
</div>
<script type="text/javascript">
    document.getElementById("reset-password-form").addEventListener("submit", function(event) {
        var password = document.getElementById("new_password").value;
        var confirmPassword = document.getElementById("confirm_password").value;
        if (password === "" || confirmPassword === "") {
            event.preventDefault();
            document.getElementById("reset-password-message").innerHTML = '<div class="invalid_error error">Please fill in all fields.</div>';
        } else if (password !== confirmPassword) {
            event.preventDefault();
            document.getElementById("reset-password-message").innerHTML = '<div class="invalid_error error">Passwords do not match.</div>';
        }
    });
    function togglePasswordVisibility(id, element) {
        var inputField = document.getElementById(id);

        if (inputField.type == "password") {
            inputField.type = "text";
                element.innerHTML = '<i class="fa fa-eye-slash" aria-hidden="true"></i>'; // Change icon to closed eye
            } else {
                inputField.type = "password";
                element.innerHTML = '<i class="fa fa-eye" aria-hidden="true"></i>';
            }
        }
    </script>
    <?php

    if (isset($_POST['submit_reset_password'])) {
        custom_handle_reset_password();
    }
}

function custom_handle_reset_password() {
    if (isset($_GET['key']) && isset($_GET['login'])) {
        $reset_key = sanitize_text_field($_GET['key']);
        $user_login = sanitize_text_field($_GET['login']);

        $user = check_password_reset_key($reset_key, $user_login);
        if (!$user || is_wp_error($user)) {?>
            <script type="text/javascript">
                document.getElementById("reset-password-message").innerHTML = '<div class="error invalid_error">Invalid reset key. Please try again.</div>';
            </script>
            <?php
           // echo '<div class="error">Invalid reset key. Please try again.</div>';
            return;
        }

        $new_password = sanitize_text_field($_POST['new_password']);
        $confirm_password = sanitize_text_field($_POST['confirm_password']);

        if ($new_password !== $confirm_password) {
            ?>
            <script type="text/javascript">
                document.getElementById("reset-password-message").innerHTML = '<div class="error invalid_error">Passwords do not match. Please try again.</div>';
            </script>
            <?php
            //echo '<div class="error">Passwords do not match. Please try again.</div>';
            return;
        }

        reset_password($user, $new_password);
        ?>
        <script type="text/javascript">
            document.getElementById("success-message").innerHTML = '<div class="success">Your password has been reset successfully!</div>';
        </script>
        <?php
       // echo '<div class="success">Your password has been reset successfully!</div>';
        wp_redirect(home_url('/sign-in/')); // Redirect to login page after success
        exit;
    }
}

