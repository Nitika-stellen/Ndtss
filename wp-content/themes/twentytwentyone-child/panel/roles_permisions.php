<?php

function micro_permissions_menu() {
    add_menu_page(
        'Role & Permissions',      // Page title
        'Role & Permissions',      // Menu title
        'manage_options',          // Capability required
        'micro-permissions',       // Menu slug
        'micro_permissions_page'   // Callback function
    );
}
add_action( 'admin_menu', 'micro_permissions_menu' );

function micro_permissions_page() {
      // Manager Admin role
    if ( ! get_role( 'manager_admin' ) ) {
        add_role(
            'manager_admin',
            'Manager',
            array(
                'read'           => true,
                'custom_manager' => true,
            )
        );
    }
    // AQB Admin role
    if ( ! get_role( 'aqb_admin' ) ) {
        add_role(
            'aqb_admin',
            'AQB Admin',
            array(
                'read'        => true,
                'custom_aqb'  => true,
            )
        );
    }
    // Center Admin role
    if ( ! get_role( 'center_admin' ) ) {
        add_role(
            'center_admin',
            'Center Admin',
            array(
                'read'           => true,
                'custom_center'  => true,
            )
        );
    }
    // Examiner role
    if ( ! get_role( 'examiner' ) ) {
        add_role(
            'examiner',
            'Examiner',
            array(
                'read'            => true,
                'custom_examiner' => true,
            )
        );
    }
    // Invigilator role
    if ( ! get_role( 'invigilator' ) ) {
        add_role(
            'invigilator',
            'Invigilator',
            array(
                'read'             => true,
                'custom_invigilator' => true,
            )
        );
    }

    // Only allow administrators.
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have permission to access this page.' );
    }
    
    // Allowed roles for which permissions can be managed.
    $allowed_roles = array( 'manager_admin', 'aqb_admin', 'center_admin', 'examiner', 'invigilator' );
    
    // Get all registered roles.
    $all_roles = wp_roles()->roles;
    
    // Filter to include only the allowed roles.
    $allowed_roles_array = array_intersect_key( $all_roles, array_flip( $allowed_roles ) );
    
    // Determine the selected role (from GET or POST). If not set, default to the first allowed role.
    if ( isset( $_REQUEST['user_role'] ) && array_key_exists( $_REQUEST['user_role'], $allowed_roles_array ) ) {
        $selected_role = sanitize_text_field( $_REQUEST['user_role'] );
    } else {
        $role_keys     = array_keys( $allowed_roles_array );
        $selected_role = reset( $role_keys );
    }
    $features = [
        'exam' => [
            'Add Result Marks',
            'Verify Result Marks',
            'Upload Certificate',
            'Verify Certificate',
            'Approve/Reject the Exam Form',
            'Assign the Examiner/ Invigilator'
        ],
        'subadmin' => [
            'Create the Sub-Admin'
        ]
    ];

    // Process and save the submitted permissions.
    if ( isset( $_POST['save_permissions'] ) && check_admin_referer( 'micro_permissions_nonce_action', 'micro_permissions_nonce_field' ) ) {
        $permissions = isset( $_POST['permissions'] ) ? (array) $_POST['permissions'] : [];
        update_option( "micro_permissions_{$selected_role}", $permissions );
        echo '<div class="updated"><p>Permissions saved for role: ' . esc_html( $selected_role ) . '</p></div>';
    }

    // Retrieve saved permissions for the selected role.
    $saved_permissions = get_option( "micro_permissions_{$selected_role}", [] );
    ?>
    <div class="wrap">
        <h1>Micro Permissions Manager</h1>

        <!-- Role Selection Form -->
        <!-- Loader -->
        <div id="loader" style="display:none; text-align:center; margin:20px 0;">
            <span class="spinner_loader" style="float:none;display:inline-block;"></span>
        </div>

        <!-- Role Selection Form -->
        <form method="get" action="" id="role-select-form">
            <input type="hidden" name="page" value="micro-permissions">
            <label for="user_role">Select Role:</label>
            <select name="user_role" id="user_role" onchange="showLoaderAndSubmit(this.form);">
                <?php foreach ( $allowed_roles_array as $role_key => $role_data ) { ?>
                    <option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $selected_role, $role_key ); ?>>
                        <?php echo esc_html( $role_data['name'] ); ?>
                    </option>
                <?php } ?>
            </select>
            <noscript><input type="submit" value="Select Role"></noscript>
        </form>

        <br>

        <!-- Add JavaScript at bottom -->
        <script>
            function showLoaderAndSubmit(form) {
                document.getElementById('loader').style.display = 'block';
                form.submit();
            }
        </script>
        <style>
            div#loader { position: relative; margin: 0 !important; }

            .spinner_loader { height: 0; width: 0; padding: 12px; border: 4px solid #ccc; border-right-color: #888; border-radius: 50%; -webkit-animation: rotate 1s infinite linear; position: absolute; left: 50%; top: 50%; }
            @-webkit-keyframes rotate {
  /* 100% keyframe for  clockwise. 
               use 0% instead for anticlockwise */
               100% {
                -webkit-transform: rotate(360deg);
            }
        }
    </style>



    <br>

    <!-- Permissions Form -->
    <form method="post" action="">
        <?php wp_nonce_field( 'micro_permissions_nonce_action', 'micro_permissions_nonce_field' ); ?>
        <!-- Preserve the selected role -->
        <input type="hidden" name="user_role" value="<?php echo esc_attr( $selected_role ); ?>">
        <table class="widefat">
            <thead>
                <tr>
                    <th>Features</th>
                    <th>Capabilities</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $features as $feature => $capabilities ) { ?>
                    <tr>
                        <td><strong><?php echo ucwords( str_replace( '_', ' ', $feature ) ); ?></strong></td>
                        <td>
                            <?php foreach ( $capabilities as $capability ) {
                                    // Build a permission key.
                                    // For example, for "exam" and "Add Result Marks", the key becomes: exam_add-result-marks
                                $perm_key = $feature . '_' . sanitize_title( $capability );
                                ?>
                                <label style="margin-right: 10px;">
                                    <input type="checkbox" name="permissions[]" value="<?php echo esc_attr( $perm_key ); ?>"
                                    <?php echo in_array( $perm_key, $saved_permissions, true ) ? 'checked' : ''; ?>>
                                    <?php echo esc_html( $capability ); ?>
                                </label>
                                <br>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <br>
        <input type="submit" name="save_permissions" value="Save Permissions" class="button button-primary">
    </form>
</div>
<style>
    .widefat { width: 100%; }
    .widefat th, .widefat td { padding: 10px; border-bottom: 1px solid #ddd; }
</style>
<?php
}

