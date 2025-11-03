<?php

add_action('admin_menu', 'add_event_registered_entries_submenu');

function add_event_registered_entries_submenu() {
    add_submenu_page(
        'edit.php?post_type=tribe_events', // Parent slug (CPT)
        'Event Registrations', // Page title
        'Event Registrations', // Menu title
        'manage_options', // Capability
        'event-registrations', // Menu slug
        'display_event_registrations_page', // Function to display the page content
        1 // Position to make it the first submenu item
    );

   
     add_submenu_page(
        'edit.php?post_type=tribe_events', // Parent slug (CPT)
        'Attendee Management', // Page title
        'Attendee Management', // Menu title
        'manage_options', // Capability
        'attendee-management', // Menu slug
        'manage_attendees_cpd_points_page', // Function to display the page content
        2 // Position to make it the first submenu item
    );
}

function display_event_registrations_page() {
    global $wpdb;

    $form_id = 12;
    $search_criteria = array();
    $paging = array('offset' => 0, 'page_size' => 1000); // Adjust as needed
    $sorting = null; // We'll sort manually below

    $entries = GFAPI::get_entries($form_id, $search_criteria, $sorting, $paging);

    // ✅ Sort entries by associated event post publish date
    usort($entries, function($a, $b) {
        $postA = get_post($a['source_id']);
        $postB = get_post($b['source_id']);

        $dateA = $postA ? strtotime($postA->post_date) : 0;
        $dateB = $postB ? strtotime($postB->post_date) : 0;

        return $dateB - $dateA; // Newest first
    });

    ?>
    <div class="wrap">
        <h1>Event Registrations</h1>
        <table id="event_submitted_form" class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>User Name</th>
                    <th>User Email</th>
                    <th>User Phone</th>
                    <th>Event Name</th>
                    <th>Status</th>
                    <th>Payment Status</th>
                    <th>CPD Points</th>
                    <th>Submitted Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php  
                if (is_wp_error($entries)) {
                    echo '<tr><td colspan="9">Error: ' . $entries->get_error_message() . '</td></tr>';
                } else {
                    foreach ($entries as $entry) {
                        $entry_id = $entry['id'];
                        $user_id = rgar($entry, 'created_by');
                        $date_created = $entry['date_created'];
                        $payment_status = rgar($entry, 'payment_status');
                        $user_info = get_userdata($user_id);

                        $user_roles = $user_info ? implode(', ', $user_info->roles) : 'User not found';

                        $event_title = get_the_title($entry['source_id']);
                        $event_link = get_permalink($entry['source_id']);
                        $status = get_user_meta($user_id, 'event_' . $entry['source_id'] . '_approval_status', true);
                        $payment_amount = rgar($entry, 'payment_amount');
                        $cpd_points = get_user_meta($user_id, 'event_' . $entry['source_id'] . '_cpd_points', true);
                        if (empty($payment_amount)) {
                            $payment_amount = 'Free';
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html($entry[29]); ?></td>
                            <td><?php echo esc_html($entry[2]); ?></td>
                            <td><?php echo esc_html($entry[3]); ?></td>
                            <td><a href="<?php echo esc_url($event_link); ?>" target="_blank"><?php echo esc_html($event_title); ?></a></td>
                            <td class="status">
                                <?php
                                if ($status === 'approved') {
                                    echo '<span style="color: green;">Approved</span>';
                                } elseif ($status === 'rejected') {
                                    echo '<span style="color: red;">Rejected</span>';
                                } else {
                                    echo '<span style="color: gray;">Pending</span>';
                                }
                                ?>
                            </td>
                            <td class="payment-status">
                                <?php
                                if ($payment_status === 'Paid') {
                                    echo '<span style="color: green;">Paid</span>';
                                } elseif ($payment_status === 'Pending') {
                                    echo '<span style="color: orange;">Pending</span>';
                                } elseif ($payment_status === 'Failed') {
                                    echo '<span style="color: red;">Failed</span>';
                                } else {
                                    echo '<span style="color: gray;">N/A</span>';
                                }
                                ?>
                            </td>
                            <td class="cpd-points"><?php echo esc_html($cpd_points ?: 'N/A'); ?></td>
                            <td><?php echo esc_html(date('d/m/Y, g:i a', strtotime($date_created))); ?></td>
                            <td>
                                <a href="<?php echo esc_url(home_url("/wp-admin/admin.php?page=gf_entries&view=entry&id=12&lid={$entry_id}")); ?>" class="button-primary">View</a>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </div>

   <script type="text/javascript">   
jQuery(document).ready(function($) {
    $('#event_submitted_form').DataTable({
        "order": [] // ✅ Disable default sorting
    });
});   
</script> 
    <?php
}

add_action('wp_ajax_add_cpd_points', 'save_cpd_points');
function save_cpd_points() {
    $user_id = intval($_POST['user_id']);
    $event_id = intval($_POST['event_id']);
    $cpd_points = $_POST['cpd_points'];

    if (!$user_id || !$event_id || $cpd_points < 0) {
        wp_send_json_error('Invalid data');
        wp_die();
    }

    // Get user and event details
    $user_info = get_userdata($user_id);
    $user_email = $user_info->user_email;
    $user_name = $user_info->display_name;
    $event_name = get_the_title($event_id) ?: "Unknown Event";
    $admin_email = get_option('admin_email');

    // Update CPD Points in user meta
    update_user_meta($user_id, 'event_'.$event_id.'_cpd_points', $cpd_points);

    // Company details
    $company_name = get_bloginfo('name');
    $profile_link = site_url('/user-profile');
    $admin_user_link = admin_url('/edit.php?post_type=tribe_events&page=attendee-management');

    // Email Subject & Message for User
    $user_subject = "CPD Points Awarded: $event_name";

    $user_message = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <h2 style="color: #0073aa;">CPD Points Awarded</h2>
            <p>Dear <strong>' . $user_name . '</strong>,</p>
             <p>We are pleased to inform you that your <strong> CPD points </strong> have been successfully updated to a total of ' . $cpd_points . ' in recognition of your participation in the following event:</p>

            <h3 style="color: #0073aa;">' . esc_html($event_name) . '</h3>
            <p>These points have been successfully added to your profile.</p>
            <p><strong>Next Steps:</strong></p>
            <ul>
                <li>📌 <a href="' . esc_url($profile_link) . '" style="color: #0073aa; text-decoration: none;">Review your CPD points</a></li>
                <li>📅 Keep track of upcoming events for more learning opportunities.</li>
            </ul>
            <p>Thank you for your active participation. We look forward to your continued engagement!</p>
            <p>Best regards,</p>
            <p><strong>' . $company_name . ' Team</strong></p>
        </div>
    ';

    // Email Subject & Message for Admin
    $admin_subject = "CPD Points Update: $user_name - $event_name";

    $admin_message = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <h2 style="color: #d35400;">CPD Points Awarded</h2>
            <p><strong>User:</strong> ' . esc_html($user_name) . '</p>
            <p><strong>Event:</strong> ' . esc_html($event_name) . '</p>
            <p><strong>CPD Points Earned:</strong> ' . $cpd_points . '</p>
            <p>The CPD points have been successfully updated in the system.</p>
            <p><strong>Quick Actions:</strong></p>
            <ul>
                <li>👤 <a href="' . esc_url($admin_user_link) . '" style="color: #d35400; text-decoration: none;">View User Profile</a></li>
            </ul>
            <p>Best regards,</p>
            <p><strong>' . $company_name . ' Admin Team</strong></p>
        </div>
    ';

    // Get email templates
    $user_data = get_email_template($user_subject, $user_message);
    $admin_data = get_email_template($admin_subject, $admin_message);

    // Send Emails
    add_filter('wp_mail_content_type', function() { return 'text/html'; });
    wp_mail($user_email, $user_subject, $user_data);
    wp_mail($admin_email, $admin_subject, $admin_data);
    remove_filter('wp_mail_content_type', function() { return 'text/html'; });

    wp_send_json_success();
    wp_die();
}


function manage_attendees_cpd_points_page() {
    global $wpdb;

    $users = $wpdb->get_results("
        SELECT um1.user_id, um1.meta_key AS event_checkin, um1.meta_value AS checkin_time, 
               um2.meta_value AS cpd_points,
               p.post_title AS event_name,
               p.ID as event_id
        FROM {$wpdb->usermeta} um1
        LEFT JOIN {$wpdb->usermeta} um2 
            ON REPLACE(um1.meta_key, '_check_in_time', '_cpd_points') = um2.meta_key 
            AND um1.user_id = um2.user_id
        LEFT JOIN {$wpdb->posts} p 
            ON p.ID = REPLACE(REPLACE(um1.meta_key, 'event_', ''), '_check_in_time', '') 
        WHERE um1.meta_key LIKE 'event_%_check_in_time'
    ");

    $processed_data = [];
    foreach ($users as $user) {
        $user_id = $user->user_id;
        $event_name = $user->event_name ?: "Unknown Event";
        $event_id = $user->event_id ?: 0;
        $cpd = floatval($user->cpd_points);

        if (!isset($processed_data[$user_id])) {
            $processed_data[$user_id] = [
                'user_id' => $user_id,
                'events' => [],
                'total_cpd' => 0
            ];
        }

        $processed_data[$user_id]['events'][$event_id] = $event_name;
        $processed_data[$user_id]['total_cpd'] += $cpd;
    }
    ?>
   <button id="export-pdf" class="button button-primary">📄 Export CPD Report</button>
        <table id="eventTable" class="display" style="width:100%">
            <thead>
                <tr>
                    <!-- <th>User ID</th> -->
                    <th>Name</th>
                    <th>Email</th>
                    <th>Attended Events</th>
                    <th>Total CPD Points</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($processed_data as $user): 
                    $user_info = get_userdata($user['user_id']);
                    $user_name = $user_info ? $user_info->display_name : 'Unknown';
                    $user_email = $user_info ? $user_info->user_email : 'Unknown';
                    $events = $user['events'];
                    $total_events = count($events);
                ?>
                <tr>
                    
                    <td>
                        <strong><?php echo esc_html($user_name); ?></strong>
                    </td>
                     <td>
                        <?php echo esc_html($user_email); ?>
                    </td>
                    <td>
                        <div class="event-list" data-user-id="<?php echo $user['user_id']; ?>">
                            <?php 
                            $i = 0;
                            foreach ($events as $event_id => $event_name):
                                $cpd_point = get_user_meta($user['user_id'], 'event_'.$event_id.'_cpd_points', true);
                                $cpd_point = $cpd_point === '' ? '0' : $cpd_point;
                                $hidden_class = ($i >= 2) ? 'extra-event' : '';
                            ?>
                                <div class="single-event <?php echo $hidden_class; ?>" style="<?php echo $i >= 2 ? 'display:none;' : ''; ?>">
                                    <div class="event_data">
                                    <strong><?php echo esc_html($event_name); ?></strong> - 
                                    <span id="cpd-points-<?php echo $user['user_id'] . '-' . $event_id; ?>"><?php echo $cpd_point; ?></span> Points
                                </div>
                                    <button class="button-secondary edit-cpd"
                                        data-user-id="<?php echo $user['user_id']; ?>"
                                        data-event-id="<?php echo $event_id; ?>"
                                        data-current-points="<?php echo $cpd_point; ?>">
                                        Edit
                                    </button>
                                </div>
                            <?php 
                                $i++;
                            endforeach; 
                            if ($total_events > 2): ?>
                                <a href="#" class="toggle-events">Show More</a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?php echo $user['total_cpd']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
    jQuery(document).ready(function($){
        $('#eventTable').DataTable({
            pageLength: 5,
            ordering: true
        });

        // Toggle Events
        $(document).on('click', '.toggle-events', function(e) {

            e.preventDefault();
            const list = $(this).closest('.event-list');
            const extra = list.find('.extra-event');

            if (extra.is(':visible')) {
                extra.slideUp();
                $(this).text('Show More');
            } else {
                console.log('not visible');
                extra.slideDown();
                $(this).text('Show Less');
            }
        });

        // Edit CPD Points
         $(document).on('click', '.edit-cpd', function(e) {
        
            e.preventDefault();
            const userId = $(this).data('user-id');
            const eventId = $(this).data('event-id');
            const current = $(this).data('current-points');

            Swal.fire({
                title: 'Edit CPD Points',
                input: 'number',
                inputValue: current,
                inputAttributes: {
                    min: 0,
                    step: '0.1'
                },
                showCancelButton: true,
                confirmButtonText: 'Save',
                showLoaderOnConfirm: true,
                preConfirm: (cpdPoints) => {
                    return $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'add_cpd_points',
                            user_id: userId,
                            event_id: eventId,
                            cpd_points: cpdPoints
                        },
                        success: function() {
                            Swal.fire('Saved!', 'CPD Points updated.', 'success');
                            $(`#cpd-points-${userId}-${eventId}`).text(cpdPoints);
                            location.reload();
                        },
                        error: function() {
                            Swal.fire('Error', 'Something went wrong.', 'error');
                        }
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            });
        });

      jQuery(document).ready(function($) {
        $("#export-pdf").click(function(){
            Swal.fire({
                title: 'Generating PDF...',
                text: 'Please wait...',
                showConfirmButton: false,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            setTimeout(function() {
                window.open('<?php echo admin_url('admin-ajax.php?action=generate_cpd_pdf'); ?>', '_blank');
                Swal.close();
                Swal.fire('Success!', 'Your CPD report is downloaded.', 'success');
            }, 1500); // wait 1.5 sec and download
        });
    });


        });
    </script>
<?php
}

add_action('wp_ajax_generate_cpd_pdf', 'generate_cpd_pdf');

function generate_cpd_pdf() {
    @ini_set('display_errors', 0);
    require_once(ABSPATH . '/wp-content/themes/twentytwentyone-child/TCPDF/tcpdf.php'); 

    global $wpdb;

    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Your Website');
    $pdf->SetTitle('CPD Points Report');
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();

    // Fetch data
    $users = $wpdb->get_results("
        SELECT um1.user_id, um1.meta_key AS event_checkin, um1.meta_value AS checkin_time, 
               um2.meta_value AS cpd_points,
               p.post_title AS event_name,
               p.ID as event_id
        FROM {$wpdb->usermeta} um1
        LEFT JOIN {$wpdb->usermeta} um2 
            ON REPLACE(um1.meta_key, '_check_in_time', '_cpd_points') = um2.meta_key 
            AND um1.user_id = um2.user_id
        LEFT JOIN {$wpdb->posts} p 
            ON p.ID = REPLACE(REPLACE(um1.meta_key, 'event_', ''), '_check_in_time', '') 
        WHERE um1.meta_key LIKE 'event_%_check_in_time'
    ");

    $processed_data = [];
    foreach ($users as $user) {
        $user_id = $user->user_id;
        $event_name = $user->event_name ?: "Unknown Event";
        $event_id = $user->event_id ?: 0;
        $cpd = floatval($user->cpd_points);

        if (!isset($processed_data[$user_id])) {
            $processed_data[$user_id] = [
                'user_id' => $user_id,
                'events' => [],
                'total_cpd' => 0
            ];
        }

        $processed_data[$user_id]['events'][$event_id] = $event_name;
        $processed_data[$user_id]['total_cpd'] += $cpd;
    }

    // Build table HTML
    $html = '
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            font-size: 12px;
        }
        th {
            background-color: #4CAF50;
            color: white;
            text-align: left;
            padding: 8px;
        }
        td {
            border: 1px solid #ddd;
            padding: 8px;
        }
    </style>
    <h2 style="text-align:center;">CPD Points Report</h2>
    <table>
        <thead>
            <tr>
               
                <th>Name</th>
                <th>Email</th>
                <th>Attended Events</th>
                <th>Total CPD Points</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($processed_data as $user_id => $data) {
        $user_info = get_userdata($user_id);
        $user_name = $user_info ? $user_info->display_name : 'Unknown';
        $user_email = $user_info ? $user_info->user_email : 'Unknown';

        // List all events
        $events = '';
        foreach ($data['events'] as $event_id => $event_name) {
             $cpd_point = get_user_meta($user_id, 'event_'.$event_id.'_cpd_points', true);
            $cpd_point = $cpd_point === '' ? '0' : $cpd_point;
            $events .= htmlspecialchars($event_name) . '<br><span id="cpd-points-' . $user_id . '-' . $event_id . '">' . $cpd_point . '</span> Points<br><br>';
           
        
}
        $html .= '<tr>
           
            <td>' . htmlspecialchars($user_name) . '</td>
             <td>' . $user_email . '</td>   
           <td>' . $events . '  </td>

            <td>' . $data['total_cpd'] . '</td>
        </tr>';
    
    }

    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, true, false, '');

    if (ob_get_length()) ob_end_clean(); // Clean any output buffer

    $pdf->Output('cpd_points_report.pdf', 'D'); // Force Download
    exit;
}
