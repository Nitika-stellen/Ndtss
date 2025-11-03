<?php

function ul_add_admin_menus() {
     add_users_page(
        'Verified Users',             // Page title
        'Verified Users',             // Menu title
        'manage_options',               // Capability required
        'verified_user',                   // Menu slug
        'ul_registered_users_page',      // Callback function
        1
    );

   
}
add_action( 'admin_menu', 'ul_add_admin_menus' );

function ul_registered_users_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Insufficient permissions.' );
    }
    $roles_with_count = array(
        'student' => count_users_by_role('student'),
        'examiner' => count_users_by_role('examiner'),
        'center_admin' => count_users_by_role('center_admin'),
        'aqb_admin' => count_users_by_role('aqb_admin')
    );
    $selected_role = isset( $_GET['role_filter'] ) ? sanitize_text_field( $_GET['role_filter'] ) : 'student';
    $args = array(
        'role'    => $selected_role,
        'orderby' => 'display_name',
        'order'   => 'ASC'
    );
    $user_query = new WP_User_Query( $args );
    $users      = $user_query->get_results();
    ?>
    <div class="wrap">
        <div class="verified_users">
        <h1>Registered Users</h1>
        <form method="get" action="">
            <input type="hidden" name="page" value="verified_user">
            <label for="role_filter">Filter by Role: </label>
            <select name="role_filter" id="role_filter" onchange="this.form.submit()">
                <?php foreach ( $roles_with_count as $role => $count ) : ?>
                    <option value="<?php echo esc_attr( $role ); ?>" <?php selected( $selected_role, $role ); ?>>
                        <?php echo ucfirst( $role ) . " ({$count})"; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
        <?php if ( ! empty( $users ) ) : ?>
            <table id="registeredUsersTable" class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>S. No.</th>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Registeration Date</th>
                        <th>Role(s)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $serial = 1;
                    foreach ( $users as $user ) : 
                        $status = get_user_meta( $user->ID, 'user_account_status', true );
                        $status = ( $status === 'inactive' ) ? 'Inactive' : 'Active';
                        $action_link = ( $status === 'Active' ) ? 'deactivate' : 'activate';
                        $action_text = ( $status === 'Active' ) ? 'Deactivate' : 'Activate';
                        $user_registered = $user->user_registered;
                    ?>
                        <tr>
                            <td><?php echo esc_html( $serial++ ); ?></td>
                            <td><?php echo esc_html( $user->user_login ); ?></td>
                            <td><?php echo esc_html( $user->display_name ); ?></td>
                            <td><?php echo esc_html( $user->user_email ); ?></td>
                            <td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $user_registered ) ) );?></td>
                            <td><?php echo esc_html( implode( ', ', $user->roles ) ); ?></td>
                            <td><?php echo esc_html( $status ); ?></td>
                            <td>
                                <button class="button button-primary action-button" onclick="handleUserAction('<?php echo $user->ID; ?>', '<?php echo $action_link; ?>', '<?php echo $action_text; ?>', '<?php echo $user->display_name; ?>');">
                                    <?php echo esc_html( $action_text ); ?>
                                </button>
                                <button class="button button-danger action-button" onclick="handleDeleteUser('<?php echo $user->ID; ?>', '<?php echo $user->display_name; ?>');">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No users found for the selected role.</p>
        <?php endif; ?>
    </div>
    <style type="text/css">
        .action-button .loader {
    display: none;
    width: 18px;
    height: 18px;
    border: 3px solid rgba(255, 255, 255, 0.6);
    border-top: 3px solid #fff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
    </style>
    <script type="text/javascript">
    // Show loader and disable button

        jQuery(document).ready(function($) {
            $('#registeredUsersTable').DataTable();
        });
 
    function showLoader(button) {
        button.prop('disabled', true);
        button.find('.loader').css('display', 'inline-block');
    }

    // Hide loader and enable button
    function hideLoader(button) {
        button.prop('disabled', false);
        button.find('.loader').css('display', 'none');
    }

    // Handle user action using SweetAlert and AJAX
    function handleUserAction(userId, action, actionText, userName) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'Do you really want to ' + actionText + ' ' + userName + '\'s account?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, ' + actionText + ' it!',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading() // Disable outside click when loading
    }).then((result) => {
        if (result.isConfirmed) {
            // Add a loading spinner to the button
            var button = jQuery('#action-button-' + userId);
           // showLoader(button);

            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'handle_user_action',
                    user_id: userId,
                    user_action: action
                },
                beforeSend: function() {
                    // Show loading spinner in Swal
                    Swal.showLoading();
                },
                success: function(response) {
                    // Hide loading spinner and show success message
                   // hideLoader(button);
                    Swal.hideLoading();
                    Swal.fire(
                        'Done!',
                        userName + '\'s account has been ' + actionText.toLowerCase() + 'd.',
                        'success'
                    ).then(() => location.reload()); // Reload page after confirmation
                },
                error: function() {
                   // hideLoader(button);
                    Swal.hideLoading();
                    Swal.fire('Error', 'There was an error processing the request.', 'error');
                }
            });
        }
    });
}



    // Handle delete user using SweetAlert and AJAX
    function handleDeleteUser(userId, userName) {
        Swal.fire({
            title: 'Are you sure?',
            text: 'Do you really want to delete ' + userName + '\'s account?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                var button = jQuery('#delete-button-' + userId);
                showLoader(button);

                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php');?>',
                    type: 'POST',
                    data: {
                        action: 'handle_user_action',
                        user_id: userId,
                        user_action: 'delete'
                    },
                    success: function(response) {
                        hideLoader(button);
                        Swal.fire('Deleted!', userName + '\'s account has been deleted.', 'success')
                            .then(() => location.reload());
                    },
                    error: function() {
                        hideLoader(button);
                        Swal.fire('Error', 'There was an error processing the request.', 'error');
                    }
                });
            }
        });
    }
</script>
    <?php
}



// Helper function to count users by role
function count_users_by_role( $role ) {
    $args = array(
        'role' => $role,
    );
    $user_query = new WP_User_Query( $args );
    return $user_query->get_total();
}
// Handle user actions via AJAX
add_action('wp_ajax_handle_user_action', 'handle_user_action_ajax');
function handle_user_action_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Insufficient permissions.' );
    }

    $user_id = intval( $_POST['user_id'] );
    $action = sanitize_text_field( $_POST['user_action'] );
    $user_info = get_userdata( $user_id );
    $user_email = $user_info->user_email;

    if ( $action === 'deactivate' ) {
        update_user_meta( $user_id, 'user_account_status', 'inactive' );
        
        // Send deactivation email
        $subject = 'Account Deactivated';
        $message = '
            <html>
            <head>
                <style>
                    body {font-family: Arial, sans-serif; color: #333;}
                    .content {background-color: #f4f4f4; padding: 20px; border-radius: 10px;}
                    .button {
                        background-color: #dc3545;
                        color: white;
                        padding: 10px 20px;
                        text-decoration: none;
                        border-radius: 5px;
                        margin-top: 10px;
                        display: inline-block;
                    }
                    .footer {font-size: 14px; color: #777; margin-top: 20px;}
                </style>
            </head>
            <body>
                <div class="content">
                    <h2>Account Deactivated</h2>
                    <p>Your account has been deactivated by the admin. You will no longer be able to log in until your account is reactivated.</p>
                    <div class="footer">
                        <p>If you have any questions, please contact our support team.</p>
                    </div>
                </div>
            </body>
            </html>
        ';
        wp_mail($user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));

    } elseif ( $action === 'activate' ) {
        update_user_meta( $user_id, 'user_account_status', 'active' );
        
        // Send activation email
        $subject = 'Account Activated';
        $message = '
            <html>
            <head>
                <style>
                    body {font-family: Arial, sans-serif; color: #333;}
                    .content {background-color: #f4f4f4; padding: 20px; border-radius: 10px;}
                    .button {
                        background-color: #28a745;
                        color: white;
                        padding: 10px 20px;
                        text-decoration: none;
                        border-radius: 5px;
                        margin-top: 10px;
                        display: inline-block;
                    }
                    .footer {font-size: 14px; color: #777; margin-top: 20px;}
                </style>
            </head>
            <body>
                <div class="content">
                    <h2>Account Activated</h2>
                    <p>Your account has been reactivated. You can now log in to the website.</p>
                    <a href="' . esc_url( home_url( '/sign-in' ) ) . '" class="button">Log In Now</a>
                    <div class="footer">
                        <p>If you have any questions, please contact our support team.</p>
                    </div>
                </div>
            </body>
            </html>
        ';
        wp_mail($user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));

    } elseif ( $action === 'delete' ) {
        // Send deletion email before deleting the user
        $subject = 'Account Deleted';
        $message = '
            <html>
            <head>
                <style>
                    body {font-family: Arial, sans-serif; color: #333;}
                    .content {background-color: #f4f4f4; padding: 20px; border-radius: 10px;}
                    .button {
                        background-color: #dc3545;
                        color: white;
                        padding: 10px 20px;
                        text-decoration: none;
                        border-radius: 5px;
                        margin-top: 10px;
                        display: inline-block;
                    }
                    .footer {font-size: 14px; color: #777; margin-top: 20px;}
                </style>
            </head>
            <body>
                <div class="content">
                    <h2>Account Deleted</h2>
                    <p>Your account has been deleted by the admin. If you have any questions, please contact our support team.</p>
                    <div class="footer">
                        <p>This is an automated message. Please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>
        ';
        wp_mail($user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));

        wp_delete_user( $user_id );
    }

    wp_die();
}


// Restrict login for inactive users
function restrict_inactive_user_login( $user, $username, $password ) {
    if ( isset( $user->ID ) ) {
        $status = get_user_meta( $user->ID, 'user_account_status', true );
        if ( $status === 'inactive' ) {
            return new WP_Error( 'account_disabled', 'Your account has been deactivated. Please contact the site administrator.' );
        }
    }
    return $user;
}
add_filter( 'authenticate', 'restrict_inactive_user_login', 30, 3 );
// Log out user if their account is deactivated and they are logged in
function check_user_account_status() {
    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        $status = get_user_meta( $user_id, 'user_account_status', true );

        if ( $status === 'inactive' ) {
            wp_logout(); // Log out the user
            wp_redirect( home_url() ); // Redirect to homepage or any page of your choice
            exit;
        }
    }
}
add_action( 'wp_loaded', 'check_user_account_status' );

