<?php
function custom_registration_form() {
    // Check if the user is already logged in
    if (is_user_logged_in()) {
        return '<p>You are already logged in. Please log out if you wish to register a new account.</p>';
    }

    // Check if this is an email verification request
    if (isset($_GET['email']) && isset($_GET['token'])) {
        handle_email_verification();
        return; // Stop further execution of form display
    }

    // Start the form HTML output
    ob_start();
    ?>
    <div class="form">
        <h2>Register</h2>
        <div id="successmessage"></div>
        <form id="custom-registration-form" method="POST">
            <div id="registration-message"></div>
            <div class="names">
                <div class="mb-15 form-group">
                    <label for="first_name">First Name*</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>
                <div class="mb-15 form-group">
                    <label for="last_name">Last Name*</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>
            </div>
             <div class="names">
                <div class="mb-15 form-group">
                    <label for="email">Email Address*</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="mb-15 form-group phone_number">
                    <label for="mobile">Mobile Number*</label>
                    <input type="tel" id="mobile" name="mobile" required>
                    <input type="hidden" id="dial_code" name="dial_code"> <!-- Hidden field for dial code -->
                </div>
              </div>
            <div class="mb-15 form-group">
                <label for="password">Password*</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required>
                    <span class="toggle-password" onclick="togglePasswordVisibility('password', this)">
                        <i class="fa fa-eye" aria-hidden="true"></i>
                    </span>
                </div>
            </div>
            <div class="mb-15 form-group">
                <label for="confirm_password">Confirm Password*</label>
                <div class="password-container">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password', this)">
                        <i class="fa fa-eye" aria-hidden="true"></i>
                    </span>
                </div>
            </div>
            <div id="pwdmessage" style="display:none">
                <h4>Password must contain the following:</h4>
                <p id="letter" class="pwdinvalid">A <b>lowercase</b> letter</p>
                <p id="capital" class="pwdinvalid">A <b>capital (uppercase)</b> letter</p>
                <p id="number" class="pwdinvalid">A <b>number</b></p>
                <p id="length" class="pwdinvalid">Minimum <b>8 characters</b></p>
            </div>
            <p>
                <input type="submit" name="submit_registration" value="Register">
            </p>
        </form>
        <div id="loader" style="display:none;"></div>
    </div>

    <!-- Include intl-tel-input CSS and JS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.css"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js"></script>
    <script>
        var myInput = document.getElementById("password");
        var letter = document.getElementById("letter");
        var capital = document.getElementById("capital");
        var number = document.getElementById("number");
        var length = document.getElementById("length");

        // Initialize intl-tel-input
        var input = document.querySelector("#mobile");
        var iti = window.intlTelInput(input, {
            initialCountry: "sg", // Set default country
            separateDialCode: true, // Show dial code separately
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js" // For validation
        });

        // Update hidden dial code field on country change
        input.addEventListener("countrychange", function() {
            document.getElementById("dial_code").value = iti.getSelectedCountryData().dialCode;
        });

        // Set initial dial code
        document.getElementById("dial_code").value = iti.getSelectedCountryData().dialCode;

        // Restrict first_name and last_name to letters only
       document.getElementById('first_name').addEventListener('input', function(event) {
            var value = event.target.value;
            event.target.value = value.replace(/[^A-Za-z\s]/g, ' ');
        });

        document.getElementById('last_name').addEventListener('input', function(event) {
            var value = event.target.value;
            event.target.value = value.replace(/[^A-Za-z]/g, '');
        });

        myInput.onfocus = function() {
            validatePassword();
        }

        myInput.onkeyup = function() {
            validatePassword();
        }

        function validatePassword() {
            var ispwdValid = true;
            var lowerCaseLetters = /[a-z]/g;
            if (myInput.value.match(lowerCaseLetters)) {  
                letter.classList.remove("pwdinvalid");
                letter.classList.add("pwdvalid");
            } else {
                letter.classList.remove("pwdvalid");
                letter.classList.add("pwdinvalid");
                ispwdValid = false;
            }

            var upperCaseLetters = /[A-Z]/g;
            if (myInput.value.match(upperCaseLetters)) {  
                capital.classList.remove("pwdinvalid");
                capital.classList.add("pwdvalid");
            } else {
                capital.classList.remove("pwdvalid");
                capital.classList.add("pwdinvalid");
                ispwdValid = false;
            }

            var numbers = /[0-9]/g;
            if (myInput.value.match(numbers)) {  
                number.classList.remove("pwdinvalid");
                number.classList.add("pwdvalid");
            } else {
                number.classList.remove("pwdvalid");
                number.classList.add("pwdinvalid");
                ispwdValid = false;
            }

            if (myInput.value.length >= 8) {
                length.classList.remove("pwdinvalid");
                length.classList.add("pwdvalid");
            } else {
                length.classList.remove("pwdvalid");
                length.classList.add("pwdinvalid");
                ispwdValid = false;
            }

            if (ispwdValid) {
                document.getElementById("pwdmessage").style.display = "none";
                jQuery('input[type="submit"]').removeAttr('disabled');
            } else {
                jQuery('input[type="submit"]').attr('disabled', 'disabled');
                document.getElementById("pwdmessage").style.display = "block";
            }
        }

        function togglePasswordVisibility(id, element) {
            var inputField = document.getElementById(id);
            if (inputField.type === "password") {
                inputField.type = "text";
                element.innerHTML = '<i class="fa fa-eye-slash" aria-hidden="true"></i>';
            } else {
                inputField.type = "password";
                element.innerHTML = '<i class="fa fa-eye" aria-hidden="true"></i>';
            }
        }

     jQuery(document).ready(function($) {
    $.validator.addMethod("validPhone", function(value, element) {
        if (this.optional(element)) return true;
        var isValid = iti.isValidNumber();
        if (!isValid) {
            var countryData = iti.getSelectedCountryData();
            var countryName = countryData.name || "selected country";
            var exampleNumber = window.intlTelInputUtils ? 
                window.intlTelInputUtils.getExampleNumber(countryData.iso2, true, 1) : "";
            $.validator.messages.validPhone = 
                "Please enter a valid mobile number for " + countryName + 
                (exampleNumber ? " (e.g., " + exampleNumber + ")" : ".");
        }
        return isValid;
    }, "Please enter a valid mobile number for the selected country.");

    $("#custom-registration-form").validate({
        rules: {
            first_name: {
                required: true,
                maxlength: 100
            },
            last_name: {
                required: true,
                minlength: 2,
                maxlength: 100
            },
            email: {
                required: true,
                email: true
            },
            mobile: {
                required: true,
                validPhone: true // Use custom validation method
            },
            password: {
                required: true
            },
            confirm_password: {
                equalTo: "#password"
            }
        },
        messages: {
            first_name: {
                required: "Please enter first name.",
                maxlength: "First name cannot exceed 100 characters."
            },
            last_name: {
                required: "Please enter last name.",
                minlength: "Last name must be at least 2 characters.",
                maxlength: "Last name cannot exceed 100 characters."
            },
            email: {
                required: "Please enter email.",
                email: "Please enter valid email."
            },
            mobile: {
                required: "Please enter mobile no."
            },
            password: {
                required: "Please provide a password."
            },
            confirm_password: {
                equalTo: "Passwords do not match."
            }
        },
        submitHandler: function(form) {
            // Validate phone number before submission
            if (!iti.isValidNumber()) {
                var countryData = iti.getSelectedCountryData();
                var countryName = countryData.name || "selected country";
                $('#registration-message').html(
                    '<div class="error">Please enter a valid mobile number for ' + countryName + '.</div>'
                );
                return false;
            }

            var formData = $(form).serialize();

            $('#registration-message').html('');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php');?>',
                type: 'POST',
                data: formData + '&action=custom_handle_registration',
                beforeSend: function() {
                    $('#loader').show();
                },
                success: function(response) {
                    if (response.success) {
                        $('#registration-message').html('<div class="success">' + response.data.message + '</div>');
                        $(form)[0].reset();
                    } else {
                        $('#registration-message').html('<div class="error">' + response.data.message + '</div>');
                    }
                    $('#loader').hide();
                },
                error: function(xhr, status, error) {
                    $('#registration-message').html('<div class="error">There was an error processing your registration. Please try again.</div>');
                    $('#loader').hide();
                }
            });
            return false; // Prevent default form submission
        }
    });

    // Revalidate mobile number on input
    $('#mobile').on('input', function() {
        $(this).valid();
    });
});
    </script>
    <?php

    return ob_get_clean();
}

add_shortcode('custom_registration_form', 'custom_registration_form');
add_action('wp_ajax_custom_handle_registration', 'custom_handle_registration');
add_action('wp_ajax_nopriv_custom_handle_registration', 'custom_handle_registration'); // For non-logged-in users
function custom_handle_registration() {
    global $wpdb;

    // Sanitize and validate input
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $email = $_POST['email'];
    $mobile = sanitize_text_field($_POST['mobile']);
    $dial_code = sanitize_text_field($_POST['dial_code']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($mobile) || empty($dial_code) || empty($password) || empty($confirm_password)) {
        wp_send_json_error(array('message' => 'Please fill in all required fields.'));
    }
    if (!is_email($email)) {
        wp_send_json_error(array('message' => 'Please enter a valid email address.'));
    }
    if ($password !== $confirm_password) {
        wp_send_json_error(array('message' => 'Passwords do not match.'));
    }
    if (email_exists($email)) {
        wp_send_json_error(array('message' => 'This email address is already registered.'));
    }

    // Validate phone number length (adjust based on your requirements)
    if (strlen($mobile) < 7 || strlen($mobile) > 15) {
        wp_send_json_error(array('message' => 'Please enter a valid mobile number.'));
    }

    $verification_token = wp_generate_password(32, false);

    // Hash the password securely (Note: Replace md5 with wp_hash_password for better security)
   // $hashed_password = wp_hash_password($password);
     $hashed_password = md5($password);

    // Store the user data temporarily in the custom table
    $wpdb->insert($wpdb->prefix . 'temp_users', array(
        'user_email' => $email,
        'user_pass' => $password,
        'user_firstname' => $first_name,
        'user_lastname' => $last_name,
        'user_mobile' => $mobile,
        'dial_code' => $dial_code, // Store dial code
        'verification_token' => $verification_token
    ));

    // Send verification email
    $verification_link = home_url('/register/?email=' . urlencode($email) . '&token=' . urlencode($verification_token));
    $email_subject = 'Please verify your email address';
    $email_body = '
    <p>Thank you for registering. Please click the link below to verify your email address:</p>
    <p><a href="' . esc_url($verification_link) . '">Verify your email</a></p>
    <p>If the button above doesn’t work, copy and paste this link into your browser:</p>
    <p><a href="' . esc_url($verification_link) . '">' . esc_url($verification_link) . '</a></p>
    ';
    $message = get_email_template($email_subject, $email_body);

    add_filter('wp_mail_content_type', function() { return 'text/html'; });
    wp_mail($email, $email_subject, $message);
    remove_filter('wp_mail_content_type', function() { return 'text/html'; });

    wp_send_json_success(array('message' => 'Registration initiated. Please check your email for the verification link.'));
}
// Function to generate unique candidate registration number
function generate_candidate_reg_number($user_id) {
    global $wpdb;
    $prefix = 'A';
    $number = 1;
    $reg_number = sprintf("%s%04d", $prefix, $number);

    // Check for uniqueness in wp_usermeta
    while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->usermeta WHERE meta_key = 'candidate_reg_number' AND meta_value = %s", $reg_number))) {
        $number++;
        if ($number > 9999) {
            error_log("Candidate registration number limit reached (A9999)");
            wp_die("Cannot generate unique candidate registration number: limit reached.");
        }
        $reg_number = sprintf("%s%04d", $prefix, $number);
    }

    if ($wpdb->last_error) {
        error_log("Error checking candidate_reg_number uniqueness: " . $wpdb->last_error);
    }

    // Store the registration number as user meta
    update_user_meta($user_id, 'candidate_reg_number', $reg_number);
    return $reg_number;
}

function handle_email_verification() {
    if (isset($_GET['email']) && isset($_GET['token'])) {
        global $wpdb;    
        $email = sanitize_email($_GET['email']);
        $token = sanitize_text_field($_GET['token']);
        $table_name = $wpdb->prefix . 'temp_users';
        $temp_user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_email = %s AND verification_token = %s",
            $email, $token
        ));

        if (!$temp_user) {
            echo '<div class="error">Invalid verification link.</div>';
            return;
        }

        if ($temp_user) {
            $first_name = $temp_user->user_firstname;
            $last_name = $temp_user->user_lastname;
            $mobile = $temp_user->user_mobile;
            $dial_code = $temp_user->dial_code;

            // Create a new user
            $user_data = array(
                'user_login'    => $email,
                'user_pass'     => $temp_user->user_pass,
                'user_email'    => $email,
                'first_name'    => $first_name,
                'last_name'     => $last_name,
                'nickname'      => $first_name,
                'role'          => 'student',
            );

            $user_id = wp_insert_user($user_data);

            if (is_wp_error($user_id)) {
                echo '<div class="error invalid_error">There was an error creating your account: ' . $user_id->get_error_message() . '</div>';
            } else {
                // Update user meta with mobile and dial code
                update_user_meta($user_id, 'mobile', $mobile);
                update_user_meta($user_id, 'dial_code', $dial_code);
                $candidate_reg_number = generate_candidate_reg_number($user_id);

                $new_user = new WP_User($user_id);
                $new_user->set_role('student');
                $wpdb->delete($table_name, array('user_email' => $email));

                echo '<div class="success"><span>Congratulations!</span> Your email has been successfully verified, and your registration is now complete. Your Candidate ID is: ' . esc_html($candidate_reg_number) . '. You can now proceed to log in and explore all the features of our website. <br> Click <a href="' . esc_url(home_url('/sign-in')) . '">here</a> to log in and get started. We\'re excited to have you on board!</div>';

                // Send email notifications
                $email_subject = 'Welcome to Our Website, ' . esc_html($first_name) . '!';
                $email_body = '
                <p>We are thrilled to have you on board! Your registration is now complete, and your Candidate ID is: ' . esc_html($candidate_reg_number) . '.</p>
                <p>Click the button below to log in to your account:</p>
                <p><a href="' . esc_url(home_url('/sign-in')) . '" class="btn">Log In Now</a></p>
                ';
                $user_message = function_exists('get_email_template') ? get_email_template($email_subject, $email_body) : "<html><body>$email_body</body></html>";
                $admin_email = get_option('admin_email');
                $admin_email_subject = 'New User Registered: ' . esc_html($first_name) . ' ' . esc_html($last_name);
                $admin_email_body = '
                <p>A new user has verified their email and completed the registration process.</p>
                <p><strong>User Details:</strong></p>
                <ul>
                <li><strong>Name:</strong> ' . esc_html($first_name) . ' ' . esc_html($last_name) . '</li>
                <li><strong>Email:</strong> ' . esc_html($email) . '</li>
                <li><strong>Mobile:</strong> +' . esc_html($dial_code) . ' ' . esc_html($mobile) . '</li>
                <li><strong>Candidate ID:</strong> ' . esc_html($candidate_reg_number) . '</li>
                </ul>
                ';
                $message = function_exists('get_email_template') ? get_email_template($admin_email_subject, $admin_email_body) : "<html><body>$admin_email_body</body></html>";
                add_filter('wp_mail_content_type', function() { return 'text/html'; });
                wp_mail($email, $email_subject, $user_message);
                wp_mail($admin_email, $admin_email_subject, $message);
                remove_filter('wp_mail_content_type', function() { return 'text/html'; });
            }
        }
    }
}

?>
