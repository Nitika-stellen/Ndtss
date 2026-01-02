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
    wp_enqueue_script('membership-custom-js',  get_stylesheet_directory_uri() . '/membership/js/membership-customs.js', ['jquery', 'datatables-js', 'sweetalert2'], '1.0', true);

    // Localize script for AJAX
    wp_localize_script('membership-custom-js', 'membershipCertificates', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('generate_certificate_nonce'),
        'send_email_nonce' => wp_create_nonce('send_certificate_email_nonce'),
        'cert_review_nonce' => wp_create_nonce('cert_review_nonce')
    ]);
    
    // Add inline CSS for badges
    wp_add_inline_style('datatables-css', '
        .import-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .import-badge.admin-approval {
            background-color: #28a745;
            color: white;
        }
        .import-badge.csv-import {
            background-color: #17a2b8;
            color: white;
        }
        .swal2-container {
            z-index: 100010 !important;
        }
    ');
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
                    <th>Source</th>
                    <th>Member Since</th>
                    <th>Status</th>
                    <th>Payment Status</th>
                    <th>Certificate From</th>
                    <th>Expiry Date</th>
                    <th class="action_th">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php  
                if (is_wp_error($entries)) {
                    echo '<tr><td colspan="9">Error: ' . esc_html($entries->get_error_message()) . '</td></tr>';
                } elseif (empty($entries) || !is_array($entries)) {
                    echo '<tr><td colspan="9">No corporate membership entries found.</td></tr>';
                } else {
                    $entry_count = 0;
                    foreach ($entries as $entry) {
                        if (empty($entry['id'])) {
                            continue;
                        }
                        
                        $entry_id = $entry['id'];
                        $user_id = rgar($entry, 'created_by');

                        // Skip if no user ID
                        if (empty($user_id)) {
                            continue;
                        }

                        // Skip duplicate users
                        if (in_array($user_id, $displayed_users)) {
                            continue;
                        }
                        $displayed_users[] = $user_id;

                        $user_info = get_userdata($user_id);
                        
                        // Skip if user doesn't exist
                        if (!$user_info) {
                            continue;
                        }
                        
                        $date_created = isset($entry['date_created']) ? $entry['date_created'] : '';
                        $payment_status = rgar($entry, 'payment_status');
                       //  $membership_type = isset($entry[31]) ? $entry[31] : '';
                       //  $membership_type_parts = !empty($membership_type) ? explode('|', $membership_type) : array('N/A');
                       // $membership_label = $membership_type_parts[0];
                       //  $membership_label = $membership_type;

                        $membership_label = 'N/A';
$form = GFAPI::get_form($form_id);

if ($form) {
    foreach ($form['fields'] as $field) {
        // Look for product fields that have a value
        if ($field->type === 'product' && !empty($entry[$field->id])) {
            $product_value = $entry[$field->id];
            if (!empty($product_value)) {
                $parts = explode('|', $product_value);
                $membership_label = trim($parts[0]);
                break; // Stop at the first found product with a value
            }
        }
    }
}

                        $status = get_user_meta($user_id, 'membership_approval_status', true);
                        
                        // Default to current user meta
                        $expiry_date = get_user_meta($user_id, 'membership_expiry_date', true) ?: 'N/A';
                        
                        // Check history log to see if this specific entry has a historical expiry date
                        $history_log = get_user_meta($user_id, 'membership_history_log', true);
                        if (is_array($history_log)) {
                            foreach ($history_log as $history_item) {
                                if (isset($history_item['entry_id']) && $history_item['entry_id'] == $entry_id) {
                                    $expiry_date = $history_item['expiry_date'];
                                    break;
                                }
                            }
                        }

                        $status_colors = array(
                            'approved'  => 'green',
                            'rejected'  => 'red',
                            'pending'   => 'gray',
                            'cancelled' => 'orange',
                        );
                        
                        $entry_count++;
                        ?>
                        <tr>
                            <td><?php echo esc_html($user_info->display_name); ?></td>
                            <td><?php echo esc_html($user_info->user_email); ?></td>
                            <td><?php echo esc_html(isset($entry[7]) ? $entry[7] : 'N/A'); ?></td>
                            <td><?php 
                                // Calculate duration dynamically from this row's dates
                                $calculated_period = 'N/A';
                                
                                if (!empty($date_created) && !empty($expiry_date) && $expiry_date !== 'N/A') {
                                    $start_date_ts = strtotime($date_created);
                                    $expiry_date_ts = strtotime($expiry_date);
                                    
                                    if ($expiry_date_ts > $start_date_ts) {
                                        $dur_years = round(($expiry_date_ts - $start_date_ts) / (365.25 * 24 * 60 * 60), 1);
                                        
                                        if ($dur_years > 10) {
                                            $calculated_period = 'Lifetime';
                                        } elseif ($dur_years >= 5) {
                                            $calculated_period = '5 Years';
                                        } elseif ($dur_years >= 3) {
                                            $calculated_period = '3 Years';
                                        } elseif ($dur_years >= 2) {
                                            $calculated_period = '2 Years';
                                        } elseif ($dur_years >= 1) {
                                            $calculated_period = '1 Year';
                                        } else {
                                            $calculated_period = round($dur_years, 1) . ' Years';
                                        }
                                    }
                                }
                                
                                // Fallback to user meta
                                if ($calculated_period === 'N/A') {
                                    $calculated_period = get_user_meta($user_id, 'membership_period', true) ?: 'N/A';
                                }
                                
                                echo esc_html($calculated_period); 
                            ?></td>
                            <td>
                                <?php
                                $import_source = get_user_meta($user_id, 'import_source', true);
                                $source_badge = '';
                                
                                if ($import_source === 'csv_import') {
                                    $source_badge = '<span class="import-badge csv-import">CSV Import</span>';
                                } elseif ($import_source === 'admin_approval') {
                                    $source_badge = '<span class="import-badge admin-approval">Admin Approval</span>';
                                } else {
                                    // Auto-detect for old records
                                    $legacy_import_id = get_user_meta($user_id, 'legacy_import_id', true);
                                    if (!empty($legacy_import_id)) {
                                        $source_badge = '<span class="import-badge csv-import">CSV Import</span>';
                                    } else {
                                        $source_badge = '<span class="import-badge admin-approval">Admin Approval</span>';
                                    }
                                }
                                echo $source_badge;
                                ?>
                            </td>
                            <td>
                                <?php 
                                $member_since = get_user_meta($user_id, 'member_since', true);
                                // Fallback to Certificate From date if member_since is empty
                                if (empty($member_since) && !empty($date_created)) {
                                    $member_since = $date_created;
                                }
                                echo !empty($member_since) ? esc_html(date('d/m/Y', strtotime($member_since))) : 'N/A';
                                ?>
                            </td>
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
                            <td><?php echo !empty($date_created) ? esc_html(date('d/m/Y', strtotime($date_created))) : 'N/A'; ?></td>
                            <td><?php echo ($expiry_date !== 'N/A' && !empty($expiry_date)) ? esc_html(date('d/m/Y', strtotime($expiry_date))) : 'N/A'; ?></td>
                            <td>
                                <div class="btn_action" style="display:flex; flex-direction:column; gap:5px;">
                                     <a href="<?php echo esc_url(admin_url("admin.php?page=gf_entries&view=entry&id=4&lid={$entry_id}")); ?>" class="button-primary" style="text-align:center;">View</a>
                                    <?php if ($status === 'approved') : 
                                        $member_since_raw = get_user_meta($user_id, 'member_since', true);
                                        // Fallback to Certificate From date if member_since is empty
                                        if (empty($member_since_raw) && !empty($date_created)) {
                                            $member_since_raw = $date_created;
                                        }
                                        $approval_date_raw = get_user_meta($user_id, 'membership_approval_date', true);
                                    ?>
                                        <button class="button review-generate-cert w-100" 
                                                style="white-space:nowrap; padding: 0 10px;"
                                                data-user-id="<?php echo esc_attr($user_id); ?>" 
                                                data-member-id="<?php echo esc_attr($entry_id); ?>"
                                                data-user-name="<?php echo esc_attr($user_info->display_name); ?>"
                                                data-membership-type="Corporate" 
                                                data-form-id="4"
                                                data-member-classification=""
                                                data-member-since="<?php echo esc_attr($member_since_raw); ?>"
                                                data-approval-date="<?php echo esc_attr($approval_date_raw); ?>"
                                                data-import-source="<?php echo esc_attr($import_source); ?>"
                                                data-expiry-date="<?php echo esc_attr($expiry_date); ?>">
                                            Review & Generate
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php
                    }
                    
                    // Show message if no valid entries were processed
                    if ($entry_count === 0) {
                        echo '<tr><td colspan="9">No valid corporate membership entries found. Please check that entries have associated users.</td></tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
        // Render the shared certificate review modal + JS so corporate behaves like individual
        if (function_exists('ul_render_membership_certificate_modal')) {
            ul_render_membership_certificate_modal();
        }
    ?>
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
                    <th>Source</th>
                    <th>Member Since</th>
                    <th>Status</th>
                    <th>Payment Status</th>
                    <th>Certificate From</th>
                    <th>Expire Date</th>
                    <th class="action_th">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (is_wp_error($entries)) {
                    echo '<tr><td colspan="10">Error: ' . esc_html($entries->get_error_message()) . '</td></tr>';
                } elseif (empty($entries) || !is_array($entries)) {
                    echo '<tr><td colspan="10">No individual membership entries found.</td></tr>';
                } else {
                    $entry_count = 0;
                    foreach ($entries as $entry) {
                        if (empty($entry['id'])) {
                            continue;
                        }
                        
                        $entry_id = $entry['id'];
                        $user_id = rgar($entry, 'created_by');

                        // Skip if no user ID
                        if (empty($user_id)) {
                            continue;
                        }

                        // Skip if this user was already shown
                        if (in_array($user_id, $displayed_users)) {
                            continue;
                        }
                        $displayed_users[] = $user_id;

                        $user_info = get_userdata($user_id);
                        
                        // Skip if user doesn't exist
                        if (!$user_info) {
                            continue;
                        }
                        
                        $date_created = isset($entry['date_created']) ? $entry['date_created'] : '';
                        $payment_status = rgar($entry, 'payment_status');
                       // $membership_type = isset($entry[27]) ? $entry[27] : '';
                        // With this code to find the selected product
                        // Replace the membership type handling with this:
                        $membership_type = '';
                        $membership_label = 'N/A';

                        // Get the form to access field properties
                        $form = GFAPI::get_form($form_id);
                        if ($form) {
                            foreach ($form['fields'] as $field) {
                                if (strpos($field->cssClass, 'membership-product-field') !== false && !empty($entry[$field->id])) {
                                    $product = GFAPI::get_field($form_id, $field->id);

                                    if ($product) {
                                        $product_value = $product->get_value_export($entry);
                                      
                                    
                                        $parts = explode('(', $product_value);
                                        
                                        if (count($parts) >= 2) {
                                            $name = trim($parts[0]);
                                            $price = floatval(trim($parts[1]));
                                            
                                            // Format as "1 Year ($ 100.00)" or "3 Years ($ 300.00)"
                                            //$membership_label = $name . ' ($ ' . number_format($price, 2) . ')';
                                             $membership_label = $name;
                                        } else {
                                            $membership_label = $product_value;
                                        }
                                    }
                                    break;
                                }
                            }
                        }              $membership_type_parts = !empty($membership_type) ? explode('|', $membership_type) : array('N/A');
                        // Get member classification from entry field 24 (for individual forms)
                        $member_classification = rgar($entry, '24') ?: 'N/A';

                        //$membership_label = $membership_type_parts[0];
                        $status = get_user_meta($user_id, 'membership_approval_status', true);
                        
                        // Default to current user meta
                        $expiry_date = get_user_meta($user_id, 'membership_expiry_date', true) ?: 'N/A';
                        
                        // Check history log to see if this specific entry has a historical expiry date
                        $history_log = get_user_meta($user_id, 'membership_history_log', true);
                        if (is_array($history_log)) {
                            foreach ($history_log as $history_item) {
                                if (isset($history_item['entry_id']) && $history_item['entry_id'] == $entry_id) {
                                    $expiry_date = $history_item['expiry_date'];
                                    break;
                                }
                            }
                        }

                        $status_colors = array(
                            'approved'  => 'green',
                            'rejected'  => 'red',
                            'pending'   => 'gray',
                            'cancelled' => 'orange',
                        );
                        
                        $entry_count++;
                        ?>
                        <tr>
                            <td><?php echo esc_html($user_info->display_name); ?></td>
                            <td><?php echo esc_html($user_info->user_email); ?></td>
                            <td><?php echo esc_html(isset($entry[7]) ? $entry[7] : 'N/A'); ?></td>
                            <td><?php 
                                // Calculate duration dynamically from this row's dates
                                // This ensures 'Certificate From' and 'Expiry' match the displayed duration
                                $calculated_period = 'N/A';
                                
                                if (!empty($date_created) && !empty($expiry_date) && $expiry_date !== 'N/A') {
                                    $start_date_ts = strtotime($date_created);
                                    $expiry_date_ts = strtotime($expiry_date);
                                    
                                    if ($expiry_date_ts > $start_date_ts) {
                                        $dur_years = round(($expiry_date_ts - $start_date_ts) / (365.25 * 24 * 60 * 60), 1);
                                        
                                        if ($dur_years > 10) {
                                            $calculated_period = 'Lifetime';
                                        } elseif ($dur_years >= 5) {
                                            $calculated_period = '5 Years';
                                        } elseif ($dur_years >= 3) {
                                            $calculated_period = '3 Years';
                                        } elseif ($dur_years >= 2) {
                                            $calculated_period = '2 Years';
                                        } elseif ($dur_years >= 1) {
                                            $calculated_period = '1 Year';
                                        } else {
                                            $calculated_period = round($dur_years, 1) . ' Years';
                                        }
                                    }
                                }
                                
                                // Fallback to user meta ONLY if calculation failed (e.g. missing dates)
                                if ($calculated_period === 'N/A') {
                                    $calculated_period = get_user_meta($user_id, 'membership_period', true) ?: 'N/A';
                                }
                                
                                echo esc_html($calculated_period); 
                            ?></td>
                            <td><?php echo esc_html($member_classification); ?></td>
                            <td>
                                <?php
                                $import_source = get_user_meta($user_id, 'import_source', true);
                                $source_badge = '';
                                
                                if ($import_source === 'csv_import') {
                                    $source_badge = '<span class="import-badge csv-import">CSV Import</span>';
                                } elseif ($import_source === 'admin_approval') {
                                    $source_badge = '<span class="import-badge admin-approval">Admin Approval</span>';
                                } else {
                                    // Auto-detect for old records
                                    $legacy_import_id = get_user_meta($user_id, 'legacy_import_id', true);
                                    if (!empty($legacy_import_id)) {
                                        $source_badge = '<span class="import-badge csv-import">CSV Import</span>';
                                    } else {
                                        $source_badge = '<span class="import-badge admin-approval">Admin Approval</span>';
                                    }
                                }
                                echo $source_badge;
                                ?>
                            </td>
                            <td>
                                <?php 
                                $member_since = get_user_meta($user_id, 'member_since', true);
                                echo !empty($member_since) ? esc_html(date('d/m/Y', strtotime($member_since))) : 'N/A';
                                ?>
                            </td>
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
                            <td><?php echo !empty($date_created) ? esc_html(date('d/m/Y', strtotime($date_created))) : 'N/A'; ?></td>
                            <td><?php echo ($expiry_date !== 'N/A' && !empty($expiry_date)) ? esc_html(date('d/m/Y', strtotime($expiry_date))) : 'N/A'; ?></td>
                            <td>
                                <div class="btn_action" style="display:flex; flex-direction:column; gap:5px;">
                                     <a href="<?php echo esc_url(admin_url("admin.php?page=gf_entries&view=entry&id=5&lid={$entry_id}")); ?>" class="button-primary" style="text-align:center;">View</a>
                                    <?php if ($status === 'approved') : 
                                        $member_since_raw = get_user_meta($user_id, 'member_since', true);
                                        $approval_date_raw = get_user_meta($user_id, 'membership_approval_date', true);
                                    ?>
                                        <button class="button review-generate-cert w-100" 
                                                style="white-space:nowrap; padding: 0 10px;"
                                                data-user-id="<?php echo esc_attr($user_id); ?>" 
                                                data-member-id="<?php echo esc_attr($entry_id); ?>"
                                                data-user-name="<?php echo esc_attr($user_info->display_name); ?>"
                                                data-membership-type="Individual" 
                                                data-form-id="5" 
                                                data-member-classification="<?php echo esc_attr($member_classification); ?>"
                                                data-member-since="<?php echo esc_attr($member_since_raw); ?>"
                                                data-approval-date="<?php echo esc_attr($approval_date_raw); ?>"
                                                data-import-source="<?php echo esc_attr($import_source); ?>"
                                                data-expiry-date="<?php echo esc_attr($expiry_date); ?>">
                                            Review & Generate
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php
                    }
                    
                    // Show message if no valid entries were processed
                    if ($entry_count === 0) {
                        echo '<tr><td colspan="10">No valid individual membership entries found. Please check that entries have associated users.</td></tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
        // Render the shared certificate review modal + JS
        if (function_exists('ul_render_membership_certificate_modal')) {
            ul_render_membership_certificate_modal();
        }
    ?>
    <?php
}

/**
 * Shared certificate review modal + JS for both Individual and Corporate membership lists.
 */
function ul_render_membership_certificate_modal() {
    ?>
    <!-- Certificate Review Modal -->
    <div id="cert-review-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:100000;">
        <div style="position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7);" class="cert-modal-overlay"></div>
        <div style="position:relative; background:white; max-width:600px; margin:50px auto; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.3); z-index:100001;">
            <div style="padding:20px 24px; border-bottom:1px solid #ddd; display:flex; justify-content:space-between; align-items:center;">
                <h2 style="margin:0; font-size:20px;">Review Certificate Details</h2>
                <button class="cert-modal-close" style="background:none; border:none; font-size:28px; cursor:pointer; color:#666; padding:0; width:30px; height:30px; line-height:1;">&times;</button>
            </div>
            
            <div style="padding:24px; max-height:60vh; overflow-y:auto;">
                <div style="margin-bottom:20px;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:#333;">
                        Member Since Date <span style="color:#d63638;">*</span>
                    </label>
                    <input type="date" id="edit-member-since" max="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:8px 12px; border:1px solid #ddd; border-radius:4px; font-size:14px;" required>
                    <p id="member-since-error" style="color:#d63638; display:none; margin: 4px 0 0 0; font-size: 13px;">Please select a Member Since date.</p>
                    <p style="margin:4px 0 0 0; font-size:12px; color:#666; font-style:italic;">This date will appear on the certificate as "Since"</p>
                </div>
                
                <div style="margin-bottom:20px;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:#333;">Approval Date (Reference)</label>
                    <input type="date" id="ref-approval-date" style="width:100%; padding:8px 12px; border:1px solid #ddd; border-radius:4px; font-size:14px; background-color:#f5f5f5; cursor:not-allowed;" disabled>
                    <p style="margin:4px 0 0 0; font-size:12px; color:#666; font-style:italic;">Date when membership was approved</p>
                </div>
                
                <div style="margin-bottom:20px;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:#333;">Certificate Date</label>
                    <input type="date" id="ref-certificate-date" style="width:100%; padding:8px 12px; border:1px solid #ddd; border-radius:4px; font-size:14px; background-color:#f5f5f5; cursor:not-allowed;" disabled>
                    <p style="margin:4px 0 0 0; font-size:12px; color:#666; font-style:italic;">Date shown as admission date on certificate</p>
                </div>
                
                <div style="margin-bottom:20px;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:#333;">Import Source</label>
                    <div id="ref-import-source"></div>
                </div>
            </div>
            
            <div style="padding:16px 24px; border-top:1px solid #ddd; display:flex; justify-content:flex-end; gap:10px;">
                <button class="button button-secondary cancel-cert-review">Cancel</button>
                <button class="button button-secondary save-member-since">
                    Save
                </button>
                <button class="button button-primary confirm-generate-cert">
                    Update & Generate Certificate
                </button>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        let currentCertData = {};
        
        // Open modal for both Individual and Corporate review-generate buttons
        $(document).on('click', '.review-generate-cert', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            currentCertData = {
                user_id: $btn.data('user-id'),
                member_id: $btn.data('member-id'),
                member_since: $btn.data('member-since'),
                approval_date: $btn.data('approval-date'),
                import_source: $btn.data('import-source'),
                membership_type: $btn.data('membership-type'),
                form_id: $btn.data('form-id'),
                member_classification: $btn.data('member-classification'),
                expiry_date: $btn.data('expiry-date')
            };
            
            // Populate modal
            $('#edit-member-since').val(currentCertData.member_since);
            $('#member-since-error').hide();
            $('#ref-approval-date').val(currentCertData.approval_date);
            
            // Calculate certificate date based on import source
            let certDate = currentCertData.approval_date;
            if (currentCertData.import_source === 'csv_import') {
                certDate = currentCertData.member_since;
            }
            $('#ref-certificate-date').val(certDate);
            
            // Show import source badge
            let sourceBadge = '';
            if (currentCertData.import_source === 'csv_import') {
                sourceBadge = '<span class="import-badge csv-import">CSV Import</span>';
            } else {
                sourceBadge = '<span class="import-badge admin-approval">Admin Approval</span>';
            }
            $('#ref-import-source').html(sourceBadge);
            
            // Show modal
            $('#cert-review-modal').fadeIn(200);
        });
        
        // Close modal
        $(document).on('click', '.cert-modal-close, .cancel-cert-review, .cert-modal-overlay', function() {
            $('#cert-review-modal').fadeOut(200);
        });
        
        // Save member_since date only
        $(document).on('click', '.save-member-since', function(e) {
            e.preventDefault();
            
            const newMemberSince = $('#edit-member-since').val();
            
            if (!newMemberSince) {
                $('#member-since-error').show();
                return;
            }
            $('#member-since-error').hide();
            
            const $btn = $(this);
            $btn.prop('disabled', true).html('Saving...');
            
            // Get AJAX URL and nonce with fallback
            var ajaxUrl = (typeof membershipCertificates !== 'undefined' && membershipCertificates.ajaxurl) 
                ? membershipCertificates.ajaxurl 
                : '<?php echo admin_url('admin-ajax.php'); ?>';
            var reviewNonce = (typeof membershipCertificates !== 'undefined' && membershipCertificates.cert_review_nonce) 
                ? membershipCertificates.cert_review_nonce 
                : '<?php echo wp_create_nonce('cert_review_nonce'); ?>';
            
            // Save only member_since date
            jQuery.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'save_member_since_only',
                    nonce: reviewNonce,
                    user_id: currentCertData.user_id,
                    member_id: currentCertData.member_id,
                    member_since: newMemberSince
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Saved Successfully!',
                            text: response.data || 'Saved successfully.',
                            confirmButtonText: 'OK',
                            timer: 2000
                        }).then(() => {
                            $('#cert-review-modal').fadeOut(200);
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.data || 'Failed to save date. Please try again.'
                        });
                        $btn.prop('disabled', false).html('Save');
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to save date. Please try again.'
                    });
                    $btn.prop('disabled', false).html('Save');
                }
            });
        });
        
        // Confirm and generate
        $(document).on('click', '.confirm-generate-cert', function(e) {
            e.preventDefault();
            
            const newMemberSince = $('#edit-member-since').val();
            
            if (!newMemberSince) {
                $('#member-since-error').show();
                return;
            }
            $('#member-since-error').hide();
            
            const $btn = $(this);
            $btn.prop('disabled', true).html('Processing...');
            
            // Get AJAX URL and nonce with fallback
            var ajaxUrl = (typeof membershipCertificates !== 'undefined' && membershipCertificates.ajaxurl) 
                ? membershipCertificates.ajaxurl 
                : '<?php echo admin_url('admin-ajax.php'); ?>';
            var reviewNonce = (typeof membershipCertificates !== 'undefined' && membershipCertificates.cert_review_nonce) 
                ? membershipCertificates.cert_review_nonce 
                : '<?php echo wp_create_nonce('cert_review_nonce'); ?>';
            
            // Update member_since and generate certificate
            jQuery.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'update_member_since_and_generate',
                    nonce: reviewNonce,
                    user_id: currentCertData.user_id,
                    member_id: currentCertData.member_id,
                    member_since: newMemberSince,
                    membership_type: currentCertData.membership_type,
                    form_id: currentCertData.form_id,
                    member_classification: currentCertData.member_classification
                },
                success: function(response) {
                    if (response.success) {
                        $('#cert-review-modal').fadeOut(200);
                        
                        // Store certificate URL for buttons
                        var certUrl = (response.data && response.data.certificate_url) ? response.data.certificate_url : '';
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Certificate Generated!',
                            text: 'Certificate has been generated successfully.',
                            showCancelButton: true,
                            confirmButtonText: 'Send Mail',
                            cancelButtonText: 'View Only',
                            confirmButtonColor: '#28a745',
                            cancelButtonColor: '#6c757d',
                            showDenyButton: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Send Email button clicked
                                sendCertificateEmail(currentCertData.user_id, currentCertData.member_id, certUrl);
                            } else if (result.dismiss === Swal.DismissReason.cancel) {
                                // View Only button clicked
                                if (certUrl) {
                                    window.open(certUrl, '_blank');
                                }
                                setTimeout(() => {
                                    location.reload();
                                }, 500);
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.data || 'Unknown error occurred'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to generate certificate. Please try again.'
                    });
                },
                complete: function() {
                    $btn.prop('disabled', false).html('Update & Generate Certificate');
                }
            });

            });
        });
        
        // Function to send certificate via email (shared for both flows)
        function sendCertificateEmail(userId, memberId, certUrl) {
            Swal.fire({
                title: 'Sending Email...',
                text: 'Please wait while we send the certificate.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Use WordPress admin-ajax.php directly (more reliable)
            var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            var emailNonce = '<?php echo wp_create_nonce('send_certificate_email_nonce'); ?>';

            jQuery.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'send_certificate_email',
                    user_id: userId,
                    member_id: memberId,
                    nonce: emailNonce
                },
                success: function (response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Mail Sent Successfully!',
                            text: response.data || 'Mail sent successfully. Certificate has been emailed.',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            // Optionally open certificate after sending email
                            if (certUrl) {
                                window.open(certUrl, '_blank');
                            }
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Email Failed',
                            text: response.data || 'Failed to send email. Please try again.',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            // Still allow viewing certificate even if email fails
                            if (certUrl) {
                                Swal.fire({
                                    icon: 'question',
                                    title: 'View Certificate?',
                                    text: 'Would you like to view the certificate?',
                                    showCancelButton: true,
                                    confirmButtonText: 'Yes, View',
                                    cancelButtonText: 'No, Close'
                                }).then((viewResult) => {
                                    if (viewResult.isConfirmed && certUrl) {
                                        window.open(certUrl, '_blank');
                                    }
                                    location.reload();
                                });
                            } else {
                                location.reload();
                            }
                        });
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Email send error:', error, xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Something went wrong while sending email. Please try again.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        // Still allow viewing certificate even if email fails
                        if (certUrl) {
                            Swal.fire({
                                icon: 'question',
                                title: 'View Certificate?',
                                text: 'Would you like to view the certificate?',
                                showCancelButton: true,
                                confirmButtonText: 'Yes, View',
                                cancelButtonText: 'No, Close'
                            }).then((viewResult) => {
                                if (viewResult.isConfirmed && certUrl) {
                                    window.open(certUrl, '_blank');
                                }
                                location.reload();
                            });
                        } else {
                            location.reload();
                        }
                    });
                }
            });
        }
    </script>
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
    
    // Temporary debug mode - set to true to see debugging information
    $debug_mode = isset($_GET['debug']) && current_user_can('manage_options');
    
    ?>
    <div class="wrap">
        <h1>All Members Management</h1>
        <p>Manage membership roles and convert members back to students.</p>
        
        <?php if ($debug_mode): ?>
            <div class="notice notice-info" style="margin: 20px 0; padding: 15px;">
                <h3>Debug Information</h3>
                <p><strong>Members found:</strong> <?php echo count($members); ?></p>
                <p><strong>Membership roles checked:</strong> member, individual_member, corporate_member</p>
                <?php if (empty($members)): ?>
                    <p><strong>Testing get_users() directly:</strong></p>
                    <pre><?php 
                        $test_users = get_users(array('role__in' => ['member', 'individual_member', 'corporate_member'], 'number' => 5));
                        echo "Found " . count($test_users) . " users with membership roles\n";
                        foreach ($test_users as $u) {
                            echo "- " . $u->display_name . " (" . implode(', ', $u->roles) . ")\n";
                        }
                    ?></pre>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
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
                        jQuery.ajax({
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
    
    // Use WordPress native get_users() for better reliability across environments
    $args = array(
        'role__in' => $membership_roles,
        'orderby' => 'registered',
        'order' => 'DESC',
        'number' => -1, // Get all users
    );
    
    $users = get_users($args);
    
    // Fallback: If get_users() doesn't work, try direct query with better error handling
    if (empty($users)) {
        // Build LIKE patterns for each membership role
        $like_patterns = array();
        $prepare_values = array('wp_capabilities');
        
        foreach ($membership_roles as $role) {
            $like_patterns[] = 'um.meta_value LIKE %s';
            $prepare_values[] = '%"' . $wpdb->esc_like($role) . '"%';
        }
        
        $query = "
            SELECT DISTINCT u.ID
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = %s
            AND (" . implode(' OR ', $like_patterns) . ")
            ORDER BY u.user_registered DESC
        ";
        
        $query = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($query), $prepare_values));
        
        $user_ids = $wpdb->get_col($query);
        
        if ($wpdb->last_error) {
            // Log error if logging function exists
            if (function_exists('membership_log_error')) {
                membership_log_error('Failed to get membership users', ['error' => $wpdb->last_error, 'query' => $query]);
            }
            // Return empty array on error
            return [];
        }
        
        if (!empty($user_ids) && is_array($user_ids)) {
            $users = get_users(array('include' => $user_ids, 'orderby' => 'registered', 'order' => 'DESC'));
        }
    }
    
    $members = [];
    
    if (!empty($users) && is_array($users)) {
        foreach ($users as $user) {
            // Skip administrators
            if (in_array('administrator', $user->roles)) {
                continue;
            }
            
            // Get user meta values
            $membership_status = get_user_meta($user->ID, 'membership_approval_status', true);
            $member_type = get_user_meta($user->ID, 'member_type', true);
            $expiry_date = get_user_meta($user->ID, 'membership_expiry_date', true);
            
            // Determine the membership role
            $role = 'student'; // default
            foreach ($membership_roles as $membership_role) {
                if (in_array($membership_role, $user->roles)) {
                    $role = $membership_role;
                    break;
                }
            }
            
            $members[] = [
                'ID' => $user->ID,
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
                'user_registered' => date('d/m/Y', strtotime($user->user_registered)),
                'membership_status' => $membership_status ?: 'unknown',
                'member_type' => $member_type ?: 'N/A',
                'expiry_date' => $expiry_date ? date('d/m/Y', strtotime($expiry_date)) : 'N/A',
                'role' => $role
            ];
        }
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
