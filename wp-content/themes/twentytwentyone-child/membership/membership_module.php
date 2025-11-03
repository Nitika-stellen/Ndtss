<?php

function ul_add_membership_menu() {
    // Add top-level admin menu
    add_menu_page(
        'Membership Management',  // Page title
        'Membership',             // Menu title
        'manage_options',         // Capability required to access the menu
        'membership-management',  // Menu slug
        'ul_membership_form_users_page', // Function to display the dashboard page
        'dashicons-groups',       // Menu icon (dashicons)
        25                        // Position in the menu
    );

    // Add submenu for Individual Membership Forms
    add_submenu_page(
        'membership-management',   // Parent slug
        'Individual Membership Forms', // Page title
        'Individual Membership Forms', // Submenu title
        'manage_options',          // Capability required to access
        'individual-membership-forms', // Unique menu slug for the individual forms page
        'ul_membership_form_users_page' // Function to display the individual forms page
    );

    // Add submenu for Corporate Membership Forms
    add_submenu_page(
        'membership-management',   // Parent slug
        'Corporate Membership Forms', // Page title
        'Corporate Membership Forms', // Submenu title
        'manage_options',          // Capability required to access
        'corporate-membership-forms', // Unique menu slug for the corporate forms page
        'ul_corporate_membership_form_users_page' // Function to display the corporate forms page
    );

    // Add submenu for All Members Management
    add_submenu_page(
        'membership-management',   // Parent slug
        'All Members Management',  // Page title
        'All Members',             // Submenu title
        'manage_options',          // Capability required to access
        'all-members-management',  // Unique menu slug
        'ul_all_members_management_page' // Function to display the page
    );

    add_submenu_page(
        'membership-management', // parent slug
        'Membership Email Templates', // page title
        'Membership Email Templates', // menu title
        'manage_options',  // capability
        'membership-email-templates', // menu slug
        'render_email_template_settings_page' // callback
    );

    // Remove the top-level "Membership" menu from the submenu list
    remove_submenu_page('membership-management', 'membership-management');
}
add_action('admin_menu', 'ul_add_membership_menu');

function ul_enqueue_membership_scripts() {
    // Enqueue DataTables and SweetAlert2 only once
    wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css', [], '1.11.5');
    wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', ['jquery'], '1.11.5', true);
    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js', [], '11', true);

    // Enqueue custom script
    wp_enqueue_script('membership-custom-js',  get_stylesheet_directory_uri() . '/membership/js/membership-custom.js', ['jquery', 'datatables-js', 'sweetalert2'], '1.0', true);

    // Localize script for AJAX
    wp_localize_script('membership-custom-js', 'membershipCertificates', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('generate_certificate_nonce')
    ]);
}

function ul_corporate_membership_form_users_page() {
    global $wpdb;

    // Enqueue scripts
    ul_enqueue_membership_scripts();

    $form_id = 4; // Corporate Membership Form ID
    $search_criteria = array();
    $sorting = array(
        'key'       => 'date_created',  // Sort by the entry creation date
        'direction' => 'DESC' // Latest first
    );
    $paging = array(
        'offset'    => 0,
        'page_size' => 1000 // Adjust as needed
    );

    $entries = GFAPI::get_entries($form_id, $search_criteria, $sorting, $paging);
    $displayed_users = [];

    ?>
    <div class="wrap">
        <h1>Corporate Membership</h1>
        <table id="corp_mem_submitted_form" class="wp-list-table widefat fixed striped custom_table">
            <thead>
                <tr>
                    <th>User Name</th>
                    <th>User Email</th>
                    <th>User Phone</th>
                    <th>Membership Duration</th>
                    <th>Status</th>
                    <th>Payment Status</th>
                    <th>Submitted Date</th>
                    <th>Expiry Date</th>
                    <th class="action_th">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php  
                if (is_wp_error($entries)) {
                    echo '<tr><td colspan="8">Error: ' . $entries->get_error_message() . '</td></tr>';
                } else {
                    foreach ($entries as $entry) {
                        $entry_id = $entry['id'];
                        $user_id = rgar($entry, 'created_by');

                        // Skip duplicate users
                        if (in_array($user_id, $displayed_users)) {
                            continue;
                        }
                        $displayed_users[] = $user_id;

                        $user_info = get_userdata($user_id);
                        $date_created = $entry['date_created'];
                        $payment_status = rgar($entry, 'payment_status');
                        $membership_type = $entry[31];
                        $membership_type_parts = explode('|', $membership_type);
                        $membership_label = $membership_type_parts[0];

                        $status = get_user_meta($user_id, 'membership_approval_status', true);
                        $expiry_date = get_user_meta($user_id, 'membership_expiry_date', true) ?: 'N/A';

                        $status_colors = array(
                            'approved'  => 'green',
                            'rejected'  => 'red',
                            'pending'   => 'gray',
                            'cancelled' => 'orange',
                        );
                        ?>
                        <tr>
                            <td><?php echo ($user_info) ? esc_html($user_info->display_name) : 'Unknown User'; ?></td>
                            <td><?php echo ($user_info) ? esc_html($user_info->user_email) : 'Unknown Email'; ?></td>
                            <td><?php echo esc_html($entry[7]); ?></td>
                            <td><?php echo esc_html($membership_label); ?></td>
                            <td>
                                <?php
                                echo '<span style="color: ' . esc_attr($status_colors[$status] ?? 'black') . ';">' . ucfirst($status ?: 'Unknown') . '</span>';
                                ?>
                            </td>
                            <td>
                                <?php
                                if ($payment_status == 'Paid') {
                                    echo '<span style="color: green;">Paid</span>';
                                } elseif ($payment_status == 'Pending') {
                                    echo '<span style="color: orange;">Pending</span>';
                                } elseif ($payment_status == 'Failed') {
                                    echo '<span style="color: red;">Failed</span>';
                                } else {
                                    echo '<span style="color: gray;">N/A</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html(date('d/m/Y', strtotime($date_created))); ?></td>
                            <td><?php echo esc_html(date('d/m/Y', strtotime($expiry_date))); ?></td>
                            <td>
                                <div class="btn_action">
                                     <a href="<?php echo esc_url(admin_url("admin.php?page=gf_entries&view=entry&id=4&lid={$entry_id}")); ?>" class="button-primary">View</a>
                                    <?php if ($status === 'approved') : ?>
                                        <button class="button generate-cert w-100" data-user-id="<?php echo esc_attr($user_id); ?>" data-member-id="<?php echo esc_attr($entry_id); ?>"
                                                data-user-name="<?php echo esc_attr($user_info ? $user_info->display_name : 'Unknown User'); ?>"
                                                data-membership-type="Corporate">
                                            Generate Certificate
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

function ul_membership_form_users_page() {
    // Enqueue scripts
    ul_enqueue_membership_scripts();

    $form_id = 5;
    $search_criteria = array();
    $sorting = array(
        'key'       => 'date_created',
        'direction' => 'DESC'
    );
    $paging = array(
        'offset'    => 0,
        'page_size' => 1000
    );

    // Retrieve form entries
    $entries = GFAPI::get_entries($form_id, $search_criteria, $sorting, $paging);
    $displayed_users = [];

    ?>
    <div class="wrap">
        <h1>Individual Membership</h1>
        <table id="ind_mem_submitted_form" class="wp-list-table widefat fixed striped custom_table">
            <thead>
                <tr>
                    <th>User Name</th>
                    <th>User Email</th>
                    <th>User Phone</th>
                    <th>Membership Duration</th>
                    <th>Member Category</th>
                    <th>Status</th>
                    <th>Payment Status</th>
                    <th>Submitted Date</th>
                    <th>Expire Date</th>
                    <th class="action_th">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (is_wp_error($entries)) {
                    echo '<tr><td colspan="9">Error: ' . esc_html($entries->get_error_message()) . '</td></tr>';
                } else {
                    foreach ($entries as $entry) {
                        $entry_id = $entry['id'];
                        $user_id = rgar($entry, 'created_by');

                        // Skip if this user was already shown
                        if (in_array($user_id, $displayed_users)) {
                            continue;
                        }
                        $displayed_users[] = $user_id;

                        $user_info = get_userdata($user_id);
                        $date_created = $entry['date_created'];
                        $payment_status = rgar($entry, 'payment_status');
                        $membership_type = $entry[27];
                        $membership_type_parts = explode('|', $membership_type);
                        $member_type = get_user_meta($user_id, 'member_type', true);

                        $membership_label = $membership_type_parts[0];
                        $status = get_user_meta($user_id, 'membership_approval_status', true);
                        $expiry_date = get_user_meta($user_id, 'membership_expiry_date', true) ?: 'N/A';

                        $status_colors = array(
                            'approved'  => 'green',
                            'rejected'  => 'red',
                            'pending'   => 'gray',
                            'cancelled' => 'orange',
                        );
                        ?>
                        <tr>
                            <td><?php echo ($user_info) ? esc_html($user_info->display_name) : 'Unknown User'; ?></td>
                            <td><?php echo ($user_info) ? esc_html($user_info->user_email) : 'Unknown Email'; ?></td>
                            <td><?php echo esc_html($entry[7]); ?></td>
                            <td><?php echo esc_html($membership_label); ?></td>
                            <td><?php echo esc_html($member_type); ?></td>
                            <td>
                                <?php
                                echo '<span style="color: ' . esc_attr($status_colors[$status] ?? 'black') . ';">' . ucfirst($status ?: 'Unknown') . '</span>';
                                ?>
                            </td>
                            <td>
                                <?php
                                if ($payment_status == 'Paid') {
                                    echo '<span style="color: green;">Paid</span>';
                                } elseif ($payment_status == 'Pending') {
                                    echo '<span style="color: orange;">Pending</span>';
                                } elseif ($payment_status == 'Failed') {
                                    echo '<span style="color: red;">Failed</span>';
                                } else {
                                    echo '<span style="color: gray;">N/A</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html(date('d/m/Y', strtotime($date_created))); ?></td>
                            <td><?php echo esc_html(date('d/m/Y', strtotime($expiry_date))); ?></td>
                            <td>
                                <div class="btn_action">
                                     <a href="<?php echo esc_url(admin_url("admin.php?page=gf_entries&view=entry&id=5&lid={$entry_id}")); ?>" class="button-primary">View</a>
                                        <?php if ($status === 'approved') : ?>
                                            <button class="button generate-cert w-100" data-user-id="<?php echo esc_attr($user_id); ?>" data-member-id="<?php echo esc_attr($entry_id); ?>"
                                                    data-user-name="<?php echo esc_attr($user_info ? $user_info->display_name : 'Unknown User'); ?>"
                                                    data-membership-type="Individual">
                                                Generate Certificate
                                            </button>
                                        <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

function ul_all_members_management_page() {
    // Enqueue scripts
    ul_enqueue_membership_scripts();
    
    // Handle bulk role removal action
    if (isset($_POST['bulk_action']) && isset($_POST['selected_members'])) {
        ul_handle_bulk_membership_role_removal();
    }
    
    // Handle individual role removal action
    if (isset($_POST['remove_membership_role']) && isset($_POST['user_id'])) {
        ul_handle_membership_role_removal();
    }
    
    // Get all users with membership roles
    $members = ul_get_all_membership_users();
    
    ?>
    <div class="wrap">
        <h1>All Members Management</h1>
        <p>Manage membership roles and convert members back to students.</p>
        
        <!-- Bulk Actions Form -->
        <form method="post" id="bulk-membership-form">
            <?php wp_nonce_field('bulk_membership_removal', 'bulk_membership_nonce'); ?>
            
            <!-- Bulk Actions Controls -->
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="bulk_action" id="bulk-action-selector">
                        <option value="">Bulk Actions</option>
                        <option value="remove_membership">Remove Membership from Selected</option>
                    </select>
                    <input type="submit" class="button action" value="Apply" 
                           onclick="return confirmBulkActionSweet();">
                </div>
                <div class="alignright">
                    <span class="displaying-num"><?php echo count($members); ?> members</span>
                </div>
            </div>
            
         <table id="all_members_management_table" class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th class="manage-column column-cb check-column">
                <input type="checkbox" id="cb-select-all">
            </th>
            <th>User Name</th>
            <th>User Email</th>
            <th>Current Role</th>
            <th>Member Type</th>
            <th>Membership Status</th>
            <th>Registration Date</th>
            <th>Expiry Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($members)): ?>
            <tr>
                <td></td>
                <td>No members found.</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        <?php else: ?>
            <?php foreach ($members as $member): ?>
                <tr>
                    <td class="check-column">
                        <input type="checkbox" name="selected_members[]" value="<?php echo esc_attr($member['ID']); ?>">
                    </td>
                    <td><?php echo esc_html($member['display_name']); ?></td>
                    <td><?php echo esc_html($member['user_email']); ?></td>
                    <td>
                        <span class="role-badge" style="background: #2271b1; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $member['role']))); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($member['member_type']); ?></td>
                    <td>
                        <?php
                        $status = $member['membership_status'];
                        $status_color = 'gray';
                        if ($status === 'approved') $status_color = 'green';
                        elseif ($status === 'pending') $status_color = 'orange';
                        elseif ($status === 'rejected') $status_color = 'red';
                        ?>
                        <span style="color: <?php echo $status_color; ?>;">
                            <?php echo esc_html(ucfirst($status)); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($member['user_registered']); ?></td>
                    <td><?php echo esc_html($member['expiry_date']); ?></td>
                    <td>
                        <button type="button" 
                                class="button button-secondary remove-individual-member"
                                data-user-id="<?php echo esc_attr($member['ID']); ?>"
                                data-user-name="<?php echo esc_attr($member['display_name']); ?>">
                            Remove Membership
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Initialize DataTable
        $('#all_members_management_table').DataTable({
            "pageLength": 25,
            "order": [[ 6, "desc" ]], // Sort by registration date (adjusted for checkbox column)
            "columnDefs": [
                { "orderable": false, "targets": [0, 8] } // Disable sorting on checkbox and Actions columns
            ]
        });
        
        // Handle select all checkbox
        $('#cb-select-all').on('change', function() {
            $('input[name="selected_members[]"]').prop('checked', this.checked);
        });
        
        // Update select all checkbox when individual checkboxes change
        $('input[name="selected_members[]"]').on('change', function() {
            var totalCheckboxes = $('input[name="selected_members[]"]').length;
            var checkedCheckboxes = $('input[name="selected_members[]"]:checked').length;
            $('#cb-select-all').prop('checked', totalCheckboxes === checkedCheckboxes);
        });
        
        // Handle individual member removal with SweetAlert
        $('.remove-individual-member').on('click', function(e) {
            e.preventDefault();
            var userId = $(this).data('user-id');
            var userName = $(this).data('user-name');
            var button = $(this);
            
            Swal.fire({
                title: 'Remove Membership?',
                html: `Are you sure you want to remove membership role from <strong>${userName}</strong>?<br><br>They will be converted to a student.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, Remove Membership',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return new Promise((resolve, reject) => {
                        // Show processing message
                        Swal.fire({
                            title: 'Processing...',
                            html: `Removing membership from <strong>${userName}</strong>`,
                            icon: 'info',
                            allowOutsideClick: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Send AJAX request
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'remove_individual_membership',
                                user_id: userId,
                                user_name: userName,
                                nonce: '<?php echo wp_create_nonce('remove_individual_membership'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        title: 'Success!',
                                        html: `Membership role has been removed from <strong>${userName}</strong>.<br>They are now a student.`,
                                        icon: 'success',
                                        confirmButtonColor: '#28a745'
                                    }).then(() => {
                                        // Remove the row from table or reload page
                                        button.closest('tr').fadeOut(500, function() {
                                            $(this).remove();
                                            // Update member count
                                            var currentCount = parseInt($('.displaying-num').text().match(/\d+/)[0]);
                                            $('.displaying-num').text((currentCount - 1) + ' members');
                                        });
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Error!',
                                        text: response.data || 'Failed to remove membership. Please try again.',
                                        icon: 'error',
                                        confirmButtonColor: '#dc3545'
                                    });
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'Network error. Please try again.',
                                    icon: 'error',
                                    confirmButtonColor: '#dc3545'
                                });
                            }
                        });
                    });
                }
            });
        });
        
        // Show success message if redirected with success parameter
        <?php if (isset($_GET['message']) && $_GET['message'] === 'success' && isset($_GET['user_name'])): ?>
        Swal.fire({
            title: 'Success!',
            html: 'Membership role has been removed from <strong><?php echo esc_js(sanitize_text_field($_GET['user_name'])); ?></strong>.<br>They are now a student.',
            icon: 'success',
            confirmButtonColor: '#28a745'
        });
        <?php endif; ?>
        
        // Show bulk success message
        <?php if (isset($_GET['message']) && $_GET['message'] === 'bulk_success'): ?>
        <?php
        $processed_count = intval($_GET['processed_count']);
        $failed_count = intval($_GET['failed_count']);
        $names = isset($_GET['processed_names']) ? explode('|', urldecode($_GET['processed_names'])) : [];
        ?>
        Swal.fire({
            title: 'Bulk Operation Complete!',
            html: `
                <div style="text-align: left;">
                    <p>✅ Successfully removed membership from <strong><?php echo $processed_count; ?></strong> member(s)</p>
                    <?php if ($failed_count > 0): ?>
                    <p>❌ Failed to process <strong><?php echo $failed_count; ?></strong> member(s)</p>
                    <?php endif; ?>
                    <?php if (!empty($names)): ?>
                    <p>📝 Processed members: <?php echo esc_js(implode(', ', $names)); ?><?php echo $processed_count > 5 ? ' and ' . ($processed_count - 5) . ' more...' : ''; ?></p>
                    <?php endif; ?>
                    <p><em>All processed members have been converted to students.</em></p>
                </div>
            `,
            icon: 'success',
            confirmButtonColor: '#28a745',
            width: '600px'
        });
        <?php endif; ?>
        
        // Show error messages
        <?php if (isset($_GET['message']) && ($_GET['message'] === 'bulk_error' || $_GET['message'] === 'error')): ?>
        <?php $error_msg = isset($_GET['error_msg']) ? sanitize_text_field($_GET['error_msg']) : 'No members were processed. Please try again.'; ?>
        Swal.fire({
            title: 'Error!',
            text: '<?php echo esc_js($error_msg); ?>',
            icon: 'error',
            confirmButtonColor: '#dc3545'
        });
        <?php endif; ?>
    });
    
    function confirmBulkActionSweet() {
        var selectedMembers = $('input[name="selected_members[]"]:checked');
        var action = $('#bulk-action-selector').val();
        
        if (!action) {
            Swal.fire({
                title: 'No Action Selected',
                text: 'Please select an action from the dropdown.',
                icon: 'warning',
                confirmButtonColor: '#ffc107'
            });
            return false;
        }
        
        if (selectedMembers.length === 0) {
            Swal.fire({
                title: 'No Members Selected',
                text: 'Please select at least one member to perform the bulk action.',
                icon: 'warning',
                confirmButtonColor: '#ffc107'
            });
            return false;
        }
        
        var memberNames = [];
        selectedMembers.each(function() {
            var row = $(this).closest('tr');
            var name = row.find('td:nth-child(2)').text();
            memberNames.push(name);
        });
        
        var membersList = memberNames.length > 5 ? 
            memberNames.slice(0, 5).join('<br>') + '<br><em>and ' + (memberNames.length - 5) + ' more...</em>' :
            memberNames.join('<br>');
        
        Swal.fire({
            title: 'Remove Membership from Selected Members?',
            html: `
                <div style="text-align: left;">
                    <p>You are about to remove membership from <strong>${selectedMembers.length}</strong> member(s):</p>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;">
                        ${membersList}
                    </div>
                    <p><strong>⚠️ Warning:</strong> All selected members will be converted to students.</p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Remove All',
            cancelButtonText: 'Cancel',
            width: '600px',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return new Promise((resolve) => {
                    // Show processing message
                    Swal.fire({
                        title: 'Processing Bulk Operation...',
                        html: `Removing membership from <strong>${selectedMembers.length}</strong> member(s).<br><br>Please wait...`,
                        icon: 'info',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                            // Submit the form after a short delay to show the processing message
                            setTimeout(() => {
                                document.getElementById('bulk-membership-form').submit();
                            }, 1000);
                        }
                    });
                });
            }
        });
        
        return false; // Prevent default form submission
    }
    </script>
    <?php
}

function ul_get_all_membership_users() {
    global $wpdb;
    
    // Get all users with membership roles (excluding administrators and students)
    $membership_roles = ['member', 'individual_member', 'corporate_member'];
    
    $query = "
        SELECT u.ID, u.display_name, u.user_email, u.user_registered,
               um1.meta_value as membership_status,
               um2.meta_value as member_type,
               um3.meta_value as expiry_date,
               um4.meta_value as wp_capabilities
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'membership_approval_status'
        LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'member_type'
        LEFT JOIN {$wpdb->usermeta} um3 ON u.ID = um3.user_id AND um3.meta_key = 'membership_expiry_date'
        LEFT JOIN {$wpdb->usermeta} um4 ON u.ID = um4.user_id AND um4.meta_key = 'wp_capabilities'
        WHERE um4.meta_value LIKE '%member%'
        AND um4.meta_value NOT LIKE '%administrator%'
        ORDER BY u.user_registered DESC
    ";
    
    $results = $wpdb->get_results($query, ARRAY_A);
    
    $members = [];
    foreach ($results as $result) {
        // Parse capabilities to get the actual role
        $capabilities = maybe_unserialize($result['wp_capabilities']);
        $role = 'student'; // default
        
        if (is_array($capabilities)) {
            foreach ($membership_roles as $membership_role) {
                if (isset($capabilities[$membership_role]) && $capabilities[$membership_role]) {
                    $role = $membership_role;
                    break;
                }
            }
        }
        
        $members[] = [
            'ID' => $result['ID'],
            'display_name' => $result['display_name'],
            'user_email' => $result['user_email'],
            'user_registered' => date('d/m/Y', strtotime($result['user_registered'])),
            'membership_status' => $result['membership_status'] ?: 'unknown',
            'member_type' => $result['member_type'] ?: 'N/A',
            'expiry_date' => $result['expiry_date'] ? date('d/m/Y', strtotime($result['expiry_date'])) : 'N/A',
            'role' => $role
        ];
    }
    
    return $members;
}

function ul_handle_membership_role_removal() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['remove_membership_nonce'], 'remove_membership_role')) {
        wp_die('Security check failed');
    }
    
    $user_id = intval($_POST['user_id']);
    $user_name = sanitize_text_field($_POST['user_name']);
    
    if (!$user_id) {
        wp_die('Invalid user ID');
    }
    
    // Get user object
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        wp_die('User not found');
    }
    
    // Remove all membership roles
    $membership_roles = ['member', 'individual_member', 'corporate_member'];
    foreach ($membership_roles as $role) {
        $user->remove_role($role);
    }
    
    // Add student role if not already present
    if (!in_array('student', $user->roles)) {
        $user->add_role('student');
    }
    
    // Update membership status to indicate role removal
    update_user_meta($user_id, 'membership_approval_status', 'role_removed');
    update_user_meta($user_id, 'membership_role_removed_date', current_time('mysql'));
    update_user_meta($user_id, 'membership_role_removed_by', get_current_user_id());
    
    // Log the action
    if (function_exists('membership_log_info')) {
        membership_log_info('Membership role removed', [
            'user_id' => $user_id,
            'user_name' => $user_name,
            'removed_by' => get_current_user_id(),
            'action' => 'role_removal'
        ]);
    }
    
    // Redirect back with success message
    wp_redirect(add_query_arg([
        'page' => 'all-members-management',
        'message' => 'success',
        'user_name' => urlencode($user_name)
    ], admin_url('admin.php')));
    exit;
}

function ul_handle_bulk_membership_role_removal() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['bulk_membership_nonce'], 'bulk_membership_removal')) {
        wp_die('Security check failed');
    }
    
    $action = sanitize_text_field($_POST['bulk_action']);
    $selected_members = array_map('intval', $_POST['selected_members']);
    
    if (empty($action) || $action !== 'remove_membership' || empty($selected_members)) {
        wp_redirect(add_query_arg([
            'page' => 'all-members-management',
            'message' => 'error',
            'error_msg' => 'Invalid bulk action or no members selected'
        ], admin_url('admin.php')));
        exit;
    }
    
    $processed_count = 0;
    $failed_count = 0;
    $processed_names = [];
    
    foreach ($selected_members as $user_id) {
        // Get user object
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            $failed_count++;
            continue;
        }
        
        // Remove all membership roles
        $membership_roles = ['member', 'individual_member', 'corporate_member'];
        foreach ($membership_roles as $role) {
            $user->remove_role($role);
        }
        
        // Add student role if not already present
        if (!in_array('student', $user->roles)) {
            $user->add_role('student');
        }
        
        // Update membership status to indicate role removal
        update_user_meta($user_id, 'membership_approval_status', 'role_removed');
        update_user_meta($user_id, 'membership_role_removed_date', current_time('mysql'));
        update_user_meta($user_id, 'membership_role_removed_by', get_current_user_id());
        
        // Log the action
        if (function_exists('membership_log_info')) {
            membership_log_info('Bulk membership role removed', [
                'user_id' => $user_id,
                'user_name' => $user->display_name,
                'removed_by' => get_current_user_id(),
                'action' => 'bulk_role_removal'
            ]);
        }
        
        $processed_count++;
        $processed_names[] = $user->display_name;
    }
    
    // Redirect back with success/error message
    $redirect_args = [
        'page' => 'all-members-management',
        'processed_count' => $processed_count,
        'failed_count' => $failed_count
    ];
    
    if ($processed_count > 0) {
        $redirect_args['message'] = 'bulk_success';
        $redirect_args['processed_names'] = urlencode(implode('|', array_slice($processed_names, 0, 5))); // Limit to first 5 names
    } else {
        $redirect_args['message'] = 'bulk_error';
    }
    
    wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
    exit;
}

// Admin notices are now handled by SweetAlert2 in JavaScript for better UX

// AJAX handler for individual membership removal
add_action('wp_ajax_remove_individual_membership', 'ul_handle_individual_membership_removal_ajax');

function ul_handle_individual_membership_removal_ajax() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'remove_individual_membership')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $user_id = intval($_POST['user_id']);
    $user_name = sanitize_text_field($_POST['user_name']);
    
    if (!$user_id) {
        wp_send_json_error('Invalid user ID');
        return;
    }
    
    // Get user object
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        wp_send_json_error('User not found');
        return;
    }
    
    // Remove all membership roles
    $membership_roles = ['member', 'individual_member', 'corporate_member'];
    foreach ($membership_roles as $role) {
        $user->remove_role($role);
    }
    
    // Add student role if not already present
    if (!in_array('student', $user->roles)) {
        $user->add_role('student');
    }
    
    // Update membership status to indicate role removal
    update_user_meta($user_id, 'membership_approval_status', 'role_removed');
    update_user_meta($user_id, 'membership_role_removed_date', current_time('mysql'));
    update_user_meta($user_id, 'membership_role_removed_by', get_current_user_id());
    
    // Log the action
    if (function_exists('membership_log_info')) {
        membership_log_info('Individual membership role removed via AJAX', [
            'user_id' => $user_id,
            'user_name' => $user_name,
            'removed_by' => get_current_user_id(),
            'action' => 'individual_role_removal_ajax'
        ]);
    }
    
    wp_send_json_success([
        'message' => 'Membership role removed successfully',
        'user_name' => $user_name
    ]);
}
