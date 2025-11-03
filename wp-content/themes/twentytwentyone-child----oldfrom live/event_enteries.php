<?php

add_action('admin_menu', 'add_event_registered_entries_submenu');

function add_event_registered_entries_submenu() {
    // Add submenu under tribe_events CPT
    add_submenu_page(
        'edit.php?post_type=tribe_events', // Parent slug (CPT)
        'Event Registrations', // Page title
        'Event Registrations', // Menu title
        'manage_options', // Capability
        'event-registrations', // Menu slug
        'display_event_registrations_page', // Function to display the page content
        1 // Position to make it the first submenu item
    );
}

function display_event_registrations_page() {
    global $wpdb;

    // Get users who have registered for events (those with 'registered_event_ids' meta key)
    $users = get_users(array(
        'meta_key' => 'registered_event_ids',
        'meta_compare' => 'EXISTS'
    ));
      ?>
    <div class="wrap">
        <h1>Event Registrations</h1>
        <table id="event_submitted_form" class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>User Name</th>
                    <th>User Role</th>
                    <th>Event Name</th>
                    <th>Submitted Date</th>
                    <th>Status</th>
                    <th>Payment Status</th>
                    <th>Payment Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php

                $form_id = 12; // The ID of the form you want to get entries for

                // Optional: Set search criteria (can be empty for all entries)
                $search_criteria = array();

                // Optional: Set sorting options (can be null for no specific sorting)
                $sorting = array(
                    'key' => 'date_created',  // Sort by the entry creation date
                    'direction' => 'DESC',    // Sort in descending order
                );

                // Optional: Set paging options to control how many entries to retrieve at once
                $paging = array(
                    'offset'    => 0,    // Start from the first entry
                    'page_size' => 100,  // Number of entries to retrieve (increase if needed)
                );

                // Retrieve entries using GFAPI::get_entries()
                $entries = GFAPI::get_entries($form_id, $search_criteria, $sorting, $paging);

                if (is_wp_error($entries)) {
                    // Handle errors if the API call fails
                    echo 'Error: ' . $entries->get_error_message();
                } else {
                    // Successfully retrieved entries
                    foreach ($entries as $entry) {
                        // Do something with each entry
                        $entry_id = $entry['id']; // Get entry ID
                        $user_id = rgar($entry, 'created_by'); // Get user ID (if available)
                        $date_created = $entry['date_created']; // Entry creation date
                        $payment_status = rgar($entry, 'payment_status'); // Payment status (if available)

                        // Output example
                        echo 'Entry ID: ' . $entry_id . '<br>';
                        echo 'User ID: ' . $user_id . '<br>';
                        echo 'Created On: ' . $date_created . '<br>';
                        echo 'Payment Status: ' . $payment_status . '<br>';
                        echo '<hr>';
                    }
                }
                foreach ($users as $user) {
                    $user_id = $user->ID;
                    $user_info = get_userdata($user_id);
                    $user_roles = implode(', ', $user_info->roles);
                    $registered_events = get_user_meta($user_id, 'registered_event_ids', false);
                    echo $registered_events;

                    if (!empty($registered_events)) {
                        foreach ($registered_events as $event_id) {
                            $entry_id = get_user_meta($user_id, 'paid_event_form_entry_' . $event_id, true);
                            $status = get_user_meta($user_id, 'event_' . $event_id . '_approval_status', true);
                            echo $entry_id;

                            if (!empty($entry_id)) {
                                $entry = GFAPI::get_entry($entry_id);

                                if (!is_wp_error($entry)) {
                                    $submitted_date = $entry['date_created'];
                                    $payment_status = rgar($entry, 'payment_status');
                                    $payment_amount = rgar($entry, 'payment_amount');
                                    if (empty($payment_amount)) {
                                        $payment_amount = 'Free';
                                    }

                                    $event_title = get_the_title($event_id);
                                    $event_link = get_permalink($event_id);
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($user->display_name); ?></td>
                                        <td><?php echo esc_html(ucfirst($user_roles)); ?></td>
                                        <td><a href="<?php echo esc_url($event_link); ?>" target="_blank"><?php echo esc_html($event_title); ?></a></td>
                                        <td><?php echo esc_html(date('F j, Y, g:i a', strtotime($submitted_date))); ?></td>
                                        <td class="status">
                                            <?php
                                            if ($status == 'approved') {
                                                echo '<span style="color: green;">Approved</span>';
                                            } elseif ($status == 'rejected') {
                                                echo '<span style="color: red;">Rejected</span>';
                                            } else {
                                                echo '<span style="color: gray;">Pending</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="payment-status">
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
                                        <td class="payment-amount"><?php echo esc_html($payment_amount); ?></td>
                                        <td>
                                           
                                            <a href="<?php echo home_url();?>/wp-admin/admin.php?page=gf_entries&view=entry&id=12&lid=<?php echo esc_attr($entry_id); ?>" class="button-primary">View</a>

                                            


                                            <a href="#" data-entry-id="<?php echo esc_attr($entry_id); ?>" data-user-id="<?php echo esc_attr($user_id); ?>" data-event-id="<?php echo esc_attr($event_id); ?>" data-approver-id="<?php echo get_current_user_id(); ?>"class="button button-success approve-entry">Approve</a>

                                            <a href="#" data-entry-id="<?php echo esc_attr($entry_id); ?>" data-user-id="<?php echo esc_attr($user_id); ?>" data-event-id="<?php echo esc_attr($event_id); ?>" data-rejecter-id="<?php echo get_current_user_id(); ?>" class="button button-danger reject-entry">Reject</a>


                                           
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                        }
                    }
                }
                ?>
            </tbody>
        </table>
    </div>


<script type="text/javascript">
   
jQuery(document).ready(function($) {

    $('#event_submitted_form').DataTable();

    var loader = $('<div id="ajax-loader" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%);">Loading</div>');
    $('body').append(loader);

    function showLoader() {
        loader.show();
    }

    function hideLoader() {
        loader.hide();
    }

    function disableActionButtons(button) {
        var row = button.closest('tr');
        row.find('.approve-entry, .reject-entry').attr('disabled', true);
    }

 

    $('tr').each(function() {
        var status = $(this).find('.status').text().trim();
        if (status === 'Approved' || status === 'Rejected') {
        $(this).find('.approve-entry, .reject-entry').attr('disabled', true);
       }
    });


});

   
</script>    
  <?php
 }          

