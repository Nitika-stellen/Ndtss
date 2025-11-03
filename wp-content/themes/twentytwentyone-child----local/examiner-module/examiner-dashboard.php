<?php
function examiner_dashboard() {
   if (!is_user_logged_in()) {
        echo '<p class="text-red-600">You must be logged in to view this page.</p>';
        return;
    }

    if (!class_exists('GFAPI')) {
        echo '<p class="text-red-600">Gravity Forms plugin is not active.</p>';
        return;
    }

    // Field mappings for forms
    $field_map = [
        'form_15' => [
            'exam_order_no' => '789',
            //'candidate_name' => '1',
            'methods' => [
                '188.1' => 'ET', '188.2' => 'MT', '188.3' => 'PT', '188.4' => 'UT',
                '188.5' => 'RT', '188.6' => 'VT', '188.7' => 'TT', '188.8' => 'PAUT', '188.9' => 'TOFD'
            ],
            'designatore_fee' => '830',
            'center_fee' => '831.2',
            'payment_amount' => 'payment_amount',
            'payment_status' => 'payment_status',
        ],
        'form_30' => [
            'exam_order_no' => '27',
            //'candidate_name' => '19',
            'methods' => '1',
            'designatore_fee' => '10',
            'center_fee' => '11.2',
            'payment_amount' => 'payment_amount',
            'payment_status' => 'payment_status',
        ],
    ];

    // Determine form ID and retest status
    $form_id = isset($_GET['form_id']) && $_GET['form_id'] == 30 ? 30 : 15;
    $is_retest = $form_id == 30;
    $config = $field_map['form_' . $form_id];

    if (isset($_GET['entry_id'])) {
        $user_id = get_current_user_id();
        $entry_id = intval($_GET['entry_id']);
        $entry_data = GFAPI::get_entry($entry_id);
        if (is_wp_error($entry_data) || $entry_data['form_id'] != $form_id) {
            echo '<p class="text-red-600">Invalid or inaccessible entry.</p>';
            return;
        }
        $form = GFAPI::get_form($entry_data['form_id']);
        $entry_meta = gform_get_meta($entry_id, '_invigilator_verification');
        $entry_meta = is_array($entry_meta) ? $entry_meta : [];
        $designatore_fee = $entry_data[$config['designatore_fee']] ?? 'N/A';
        $center_fee = trim($entry_data[$config['center_fee']] ?? 'Pending');
        $center_fee = str_replace('$ ', '$', $center_fee);
        $payment_amount = $entry_data[$config['payment_amount']] ?? 'N/A';
        $payment_status = $entry_data[$config['payment_status']] ?? 'Pending';
        $candidate_id = $entry_data['created_by'] ?? get_current_user_id();
        $user_data = get_userdata($candidate_id);
        $current_user_id = get_current_user_id();
        $statuses = get_user_meta($current_user_id, '_assigned_entries_examiner_status', true) ?: [];
        $status = $statuses[$entry_id] ?? '';
        $profile_photo = get_user_meta($user_data->ID, 'custom_profile_photo', true);
        $first_name = get_user_meta($user_data->ID, 'first_name', true);
        $last_name = get_user_meta($user_data->ID, 'last_name', true);
        $candidate_name = ($first_name || $last_name) ? trim($first_name . ' ' . $last_name) : 'N/A';
        $order_number = $entry_data[$config['exam_order_no']]? ($entry_data[$config['exam_order_no']] ?? 'N/A') : 'N/A';
        ?>
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Examiner Entry Details <?= $is_retest ? '(Retest)' : '' ?></h1>
        <div class="wrap invigilator_edit_page max-w-7xl py-6 w-100 m-0">
            
                <div class="invigilator_details lg:col-span-2">
                
                    <h2 class="text-xl font-semibold mb-4 text-gray-800">Payment Details</h2>
                    <table class="wp-list-table widefat striped">
                        <tbody>
                         <tr>
                            <td class="font-medium px-4 py-2"><strong>Profile Photo:</strong></td>
                            <td class="px-4 py-2">
                                <?php if (!empty($profile_photo) && $profile_photo !== 'N/A') : ?>
                                    <img src="<?php echo esc_url($profile_photo); ?>" alt="Profile Photo" style="max-width: 100px; border-radius: 6px; border: 1px solid #ccc;">
                                <?php else : ?>
                                    <span>No profile photo uploaded.</span>
                                <?php endif; ?>
                            </td>
                        </tr>

                            <tr>
                                <td class="font-medium px-4 py-2"><strong>Designatore Fee:</strong></td>
                                <td class="px-4 py-2">
                                    <?php echo (!empty($designatore_fee) && $designatore_fee !== 'N/A') ? '$' . esc_html($designatore_fee) : esc_html($designatore_fee); ?>
                                    <i class="fas fa-credit-card ml-2"></i>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium px-4 py-2"><strong>Center Fee:</strong></td>
                                <td class="px-4 py-2">
                                    <?php echo (!empty($center_fee) && $center_fee !== 'Pending') ? esc_html($center_fee) : esc_html($center_fee); ?>
                                    <i class="fas fa-credit-card ml-2"></i>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium px-4 py-2"><strong>Amount Paid:</strong></td>
                                <td class="px-4 py-2">
                                    <?php echo (!empty($payment_amount) && $payment_amount !== 'N/A') ? '$' . esc_html($payment_amount) : esc_html($payment_amount); ?>
                                    <i class="fas fa-credit-card ml-2"></i>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium px-4 py-2"><strong>Payment Status:</strong></td>
                                <td class="px-4 py-2 status-<?php echo strtolower($payment_status); ?>">
                                    <?php echo esc_html(ucwords($payment_status)); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <h2 class="text-xl font-semibold mb-4 mt-6 text-gray-800">Submitted Entry Details</h2>
                    <table class="wp-list-table widefat striped">
                        <tbody>
                               <?php foreach ($form['fields'] as $field) : 
                            $field_id = $field['id'];
                            
                            // Get actual field value (with calculations, checkboxes, etc.)
                            $field_value = GFFormsModel::get_lead_field_value($entry_data, $field);

                            // Skip fields hidden via conditional logic
                            if (!is_field_visible_by_conditional_logic($field, $form, $entry_data)) {
                                continue;
                            }

                            // Skip HTML, hidden fields, or excluded fields
                            if ($field['type'] === 'html' || $field['type'] === 'hidden' || in_array($field_id, [771, 772, 773])) {
                                continue;
                            }

                            // Skip empty values (including empty arrays)
                            if (empty($field_value) || (is_array($field_value) && count(array_filter($field_value)) === 0)) {
                                continue;
                            }

                            // Format date
                            if ($field['type'] === 'date') {
                                try {
                                    $date = new DateTime($field_value);
                                    $field_value = $date->format('d/m/Y');
                                } catch (Exception $e) {
                                    // Leave as-is
                                }
                            }

                            // Format phone numbers with dial codes
                            if (in_array($field_id, [196, 28, 26])) {
                                $dial_code = $entry_data[771] ?? ($entry_data[772] ?? ($entry_data[773] ?? ''));
                                $full_number = $dial_code . $field_value;
                                $field_value = '<a href="tel:' . esc_attr($full_number) . '">' . esc_html($full_number) . '</a>';
                            }

                            // Format email links
                            if ($field['type'] === 'email') {
                                $email = sanitize_email($field_value);
                                if (is_email($email)) {
                                    $field_value = '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                                }
                            }

                            // Format currency
                            if ($field['type'] === 'number' && !empty($field['enableCalculation'])) {
                                $field_value = '$' . preg_replace('/^\s*\$\s*/', '', trim($field_value));
                            }

                            // Format file uploads
                            if ($field['type'] === 'fileupload') {
                                $files = is_array($field_value) ? $field_value : [$field_value];
                                $file_links = '<ul>';
                                foreach ($files as $file_url) {
                                    $file_name = basename($file_url);
                                    $file_links .= '<li><a href="' . esc_url($file_url) . '" target="_blank">' . esc_html($file_name) . '</a></li>';
                                }
                                $file_links .= '</ul>';
                                $field_value = $file_links;
                            }

                            // Format signature field
                            if ($field_id == 115) {
                                $upload_dir = wp_upload_dir();
                                $signature_url = $upload_dir['baseurl'] . '/gravity_forms/signatures/' . $field_value;
                                $field_value = '<img src="' . esc_url($signature_url) . '" alt="Signature" style="max-width: 300px;">';
                            }

                            // Format list fields
                            if ($field['type'] === 'list') {
                                $list_data = maybe_unserialize($field_value);
                                if (is_array($list_data)) {
                                    $field_value = '<ul>';
                                    foreach ($list_data as $row) {
                                        if (is_array($row)) {
                                            $field_value .= '<li>';
                                            foreach ($row as $key => $value) {
                                                $field_value .= '<strong>' . esc_html($key) . ':</strong> ' . esc_html($value) . '<br>';
                                            }
                                            $field_value .= '</li>';
                                        } else {
                                            $field_value .= '<li>' . esc_html($row) . '</li>';
                                        }
                                    }
                                    $field_value .= '</ul>';
                                }
                            }

                            // If array (e.g., checkboxes), convert to string
                            if (is_array($field_value)) {
                                $field_value = implode(', ', array_filter($field_value));
                            }
                        ?>
                        <tr>
                            <td class="font-medium px-4 py-2"><strong><?php echo esc_html($field['label']); ?>:</strong></td>
                            <td class="px-4 py-2"><?php echo $field_value ?: '<em>(no value)</em>'; ?></td>
                        </tr>
                        <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>
                <div class="">
                    <div class="invigilator_sidebar bg-white p-6 rounded-lg shadow">
               <a href="<?php echo admin_url('admin.php?page=examiner-dashboard'); ?>" class="button button-primary">← Back to Dashboard</a>
               <h3 class="text-2xl font-semibold"><?php echo esc_html($entry_data['1']); ?> (Order: <?php echo esc_html($order_number); ?>)</h3>            

               <h2 class="text-xl font-semibold mb-4">Examiner Actions</h2>

               <?php if (!$status): ?>
                <div class="custom_actions">
                 <div class="event-approval-actions">
                    <button id="acceptEntry" class="button button-primary">
                        Accept
                    </button>
                    <button id="declineEntry" class="button button-secondary">
                        Decline
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="custom_actions">
                <strong class="text-gray-700 font-semibold">Status:</strong>
                <?php
                $status_classes = [
                    'Accepted' => 'bg-green-600 text-white',
                    'Declined' => 'bg-red-600 text-white',
                    'Pending'  => 'bg-yellow-400 text-gray-900'
                ];
                $status_labels = [
                    'Accepted' => '✅ Accepted',
                    'Declined' => '❌ Declined',
                    'Pending'  => '⏳ Pending'
                ];
                $badge_class = $status_classes[$status] ?? 'bg-gray-400 text-white';
                $badge_label = $status_labels[$status] ?? ucfirst($status);
                ?>
                <span class="inline-block px-3 py-1 text-sm font-semibold rounded-full <?php echo esc_attr($badge_class); ?>">
                    <?php echo esc_html($badge_label); ?>
                </span>
            </div>



            <?php if ($status === 'accepted'):
               if ($entry_data['form_id'] == 15) {
                $methods = array_filter([
                    $entry_data['188.1'], $entry_data['188.2'], $entry_data['188.3'],
                    $entry_data['188.4'], $entry_data['188.5'], $entry_data['188.6'],
                    $entry_data['188.7'], $entry_data['188.8'], $entry_data['188.9']
                ]);
            }

            if ($entry_data['form_id'] == 30) {
                $method_value = trim($entry_data['1'] ?? '');
    $methods = !empty($method_value) ? [$method_value] : [];
}
foreach ($methods as $method):
    $method_key = sanitize_title($method);
    $marks_entry_id = gform_get_meta($entry_id, '_examiner_marks_entry_id_' . $method_key);

    if ($marks_entry_id && is_numeric($marks_entry_id)) {
        $marks_entry_data = GFAPI::get_entry($marks_entry_id);
        if (!is_wp_error($marks_entry_data)) {
            $marks_form = GFAPI::get_form($marks_entry_data['form_id']);
            $marks_combined = [];
            $other_fields = [];

            $marks_combined = [];
            $other_fields = [];
            $practical_samples_html = ''; 

            foreach ($marks_form['fields'] as $field) {
                $field_id = $field->id;
                $label = trim($field->label);
                $value = $marks_entry_data[$field_id] ?? '';

                if (empty($value) || in_array($field->type, ['html', 'hidden']) || in_array($field_id, [18])) continue;
                if ($field_id == 54 && is_serialized($value)) {
                    $samples = maybe_unserialize($value);
                    if (is_array($samples)) {
                        $items = [];
                        foreach ($samples as $sample) {
                            $sample_no = $sample['Practical Sample Number'] ?? '';
                            $marks = $sample['Marks'] ?? '';
                            if ($sample_no || $marks) {
                                $items[] = esc_html($sample_no) . ' : ' . esc_html($marks);
                            }
                        }
                        if (!empty($items)) {
                            $practical_samples_html = "<tr><td class='font-medium px-2 py-1'>Practical Samples</td><td class='px-2 py-1'>" .
                            implode('<br>', $items) . "</td></tr>";
                        }
                    }
                    continue;
                }
                if (stripos($label, 'Marks Obtained') !== false) {
                    $base = trim(str_ireplace('Marks Obtained', '', $label));
                    $key = strtolower(preg_replace('/[^a-z0-9]/', '', $base));
                    $marks_combined[$key]['obtained'] = $value;
                    $marks_combined[$key]['label'] = $base;
                } elseif (stripos($label, 'Total Marks') !== false) {
                    $base = trim(str_ireplace('Total Marks', '', $label));
                    $key = strtolower(preg_replace('/[^a-z0-9]/', '', $base));
                    $marks_combined[$key]['total'] = $value;
                    $marks_combined[$key]['label'] = $base;
                } else {
                    $other_fields[$label] = $value;
                }
            }
            echo '<div class="border rounded-lg p-4 mb-4 bg-gray-50 table_sidebar">';
            echo '<h3 class="text-md font-bold mb-2 mt-0">' . esc_html($method) . ' Marks</h3>';
            echo '<table class="min-w-full text-sm table-auto border mb-4">';

            foreach ($other_fields as $label => $value) {
                echo "<tr><td class='font-medium px-2 py-1'>$label</td><td class='px-2 py-1'>$value</td></tr>";
            }


            foreach ($marks_combined as $data) {
                $label = $data['label'] ?? '-';
                $obtained = $data['obtained'] ?? '-';
                $total = $data['total'] ?? '-';
                echo "<tr><td class='font-medium px-2 py-1'>{$label}</td><td class='px-2 py-1'>{$obtained} / {$total}</td></tr>";
            }


            if (!empty($practical_samples_html)) {
                echo $practical_samples_html;
            }

            echo '</table>';

            $edit_link = admin_url("admin.php?page=gf_entries&view=entry&id={$marks_form['id']}&lid={$marks_entry_id}");
            echo "<a href='$edit_link' target='_blank' class='button button-primary mt-2'>
            View/Edit Marks
            </a>";
            echo '</div>';
        }
    } else {

        if($entry_data['form_id'] == 15){
            echo '<div class="border rounded-lg p-4 mb-4 bg-gray-50 table_sidebar">';
            echo '<h3 class="text-md font-bold mb-2 mt-0">' . esc_html($method) . ' Marks</h3>';
            $examiner_marks_url = admin_url("admin.php?page=examiner-marks-entry&entry_id=" . intval($entry_id) . "&method=" . urlencode($method) . "&examno=" . esc_attr($order_number));
            echo '<a href="' . esc_url($examiner_marks_url) . '" class="button button-primary">Add Marks</a>';
            echo '</div>';
        }


        if ($entry_data['form_id'] == 30) {
         

           
                $matched_entry = $entries[0]; // Take the first match
                $edit_url = admin_url("admin.php?page=gf_entries&view=entry&id=25&lid=" . intval($entry_data['14']));

                echo '<div class="border rounded-lg p-4 mb-4 bg-gray-50 table_sidebar">';
                echo '<h3 class="text-md font-bold mb-2 mt-0">Edit Marks Entry</h3>';
                echo '<a href="' . esc_url($edit_url) . '" class="button button-primary">Edit Marks</a>';
                echo '</div>';
            
        }

    }
endforeach;
endif;
endif; ?>
</div>
</div>
</div>

<!-- Accept/Decline JS -->
<script>
    jQuery(function($) {
        function handleExaminerStatus(status, comments = '') {
            $.post("<?php echo admin_url('admin-ajax.php'); ?>", {
                action: "examiner_acceptance_status",
                entry_id: "<?php echo $entry_id; ?>",
                status: status,
                comments: comments
            }, function(response) {
                if (response.success) {
                    Swal.fire('Success', 'Status updated.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', response.data || 'Action failed.', 'error');
                }
            });
        }

        $("#acceptEntry").click(function() {
            Swal.fire({
                title: 'Conflict of Interest Declaration',
                html: 'I declare I have no conflict of interest.',
                input: 'checkbox',
                inputPlaceholder: 'I agree',
                inputValidator: val => !val && 'You must agree',
                showCancelButton: true
            }).then(result => {
                if (result.isConfirmed) handleExaminerStatus('accepted');
            });
        });

        $("#declineEntry").click(function() {
            Swal.fire({
                title: 'Decline Reason',
                input: 'textarea',
                inputPlaceholder: 'Why are you declining?',
                inputValidator: val => !val && 'Required',
                showCancelButton: true
            }).then(result => {
                if (result.isConfirmed) handleExaminerStatus('declined', result.value);
            });
        });
    });
</script>
</div>
<?php
return;
}

    // Overview list with filters
if (!is_user_logged_in()) {
    echo '<p class="text-red-600">You must be logged in.</p>';
    return;
}

$user_id = get_current_user_id();
if (current_user_can('administrator')) {
    $entries_form_15 = GFAPI::get_entries(15, [], [], ['offset' => 0, 'page_size' => 999]);
    $entries_form_30 = GFAPI::get_entries(30, [], [], ['offset' => 0, 'page_size' => 999]);
    $entries = array_merge(
        is_array($entries_form_15) ? $entries_form_15 : [],
        is_array($entries_form_30) ? $entries_form_30 : []
    );
} else {
    $ids = get_user_meta($user_id, '_assigned_entries_examiner', true) ?: [];
    $ids = is_array($ids) ? $ids : explode(',', $ids);
    $entries = [];
    foreach ($ids as $e) {
        $tmp = GFAPI::get_entry($e);
        if (!is_wp_error($tmp)) $entries[] = $tmp;
    }
}

if (empty($entries)) {
    echo '<p class="text-gray-600">No assigned entries found.</p>';
    return;
}

    // Filters
$order_filter  = $_GET['exam_order']   ?? '';
$name_filter   = $_GET['student_name'] ?? '';
$status_filter = $_GET['status']       ?? '';

usort($entries, fn($a, $b) => strtotime($b['date_created']) - strtotime($a['date_created']));

?>
<div class="wrap p-6">
    <h1 class="text-2xl font-semibold mb-4">Examiner Dashboard</h1>

    <!-- Filters -->
    <form method="GET" class="search_form"> 
       <div class="form_controls">
        <input type="hidden" name="page" value="examiner-dashboard">
        <input type="text" name="exam_order" value="<?php echo esc_attr($order_filter); ?>" placeholder="Exam Order No" class="border rounded px-3 py-2 w-40">
    </div>   
    <div class="form_controls">
     <input type="text" name="student_name" value="<?php echo esc_attr($name_filter); ?>" placeholder="Student Name" class="border rounded px-3 py-2 w-64">
 </div>   
 <div class="form_controls">
    <select name="status" class="border rounded px-3 py-2">
        <option value="">All Statuses</option>
        <option value="pending"  <?php selected($status_filter, 'pending'); ?>>Pending</option>
        <option value="accepted" <?php selected($status_filter, 'accepted'); ?>>Accepted</option>
        <option value="declined" <?php selected($status_filter, 'declined'); ?>>Declined</option>
    </select>
</div>   
<div class="form_controls search_btns">
    <button type="submit" class="button-primary">Filter</button>
    <a href="<?php echo admin_url('admin.php?page=examiner-dashboard'); ?>" class="button-primary btn_red">Clear</a>
</div>   
</form>

<div class="overflow-x-hidden">
    <table class="table_custom wp-list-table widefat striped dataTable no-footer">
        <thead class="bg-gray-100 font-semibold uppercase text-gray-700 text-xs">
            <tr>
                <th class="px-4 py-2">Img</th>
                <th class="px-4 py-2">Order No</th>
                <th class="px-4 py-2">Name</th>
                <th class="px-4 py-2">Designator</th>
                <th class="px-4 py-2">Status</th>
                <th class="px-4 py-2">Action</th>
            </tr>
        </thead>
        <tbody class="text-gray-800">
            <?php foreach ($entries as $entry):
                $eid = $entry['id'];
                $statuses = get_user_meta($user_id, '_assigned_entries_examiner_status', true) ?: [];
                $status = is_array($statuses) ? ($statuses[$eid] ?? '') : $statuses;
                $entry_meta_key = '_examiner_status_summary';
                $entry_status   = gform_get_meta($eid, $entry_meta_key);

                  if (!empty($entry_status) && is_array($entry_status)) {
                                    $st = '';
                                    foreach ($entry_status as $user_id => $status) {
                                        $user = get_userdata($user_id);
                                        if ($user) {
                                          $st .= esc_html($status) . ': ' . esc_html($user->display_name) . "<br>";
                                        } else {
                                           $st .= esc_html($status) . ' (Unknown User ID: ' . intval($user_id) . ')<br>';
                                        }
                                    }
                                }
                                else{
                                    $st = '<span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-200 text-gray-700">Pending</span>';
                                }
                $exam_no = $entry[$config['exam_order_no']] ?? 'N/A';
                



                $user_id = $entry['created_by'] ?? get_current_user_id();
                $user_data = get_userdata($user_id);
                $photo_url = get_user_meta($user_id, 'custom_profile_photo', true);
                $first_name = get_user_meta($user_id, 'first_name', true);
                $last_name = get_user_meta($user_id, 'last_name', true);
                $name = ($first_name || $last_name) ? trim($first_name . ' ' . $last_name) : 'N/A';
               // $name = $user_data ? $user_data->display_name : 'N/A';


                if ($order_filter && stripos($entry['789'], $order_filter) === false) continue;
                if ($name_filter && stripos($entry['1'], $name_filter) === false) continue;
                if ($status_filter && $st !== $status_filter) continue;

                $designators = array_filter([
                    $entry['188.1'], $entry['188.2'], $entry['188.3'],
                    $entry['188.4'], $entry['188.5'], $entry['188.6'],
                    $entry['188.7'], $entry['188.8'], $entry['188.9']
                ]);
                if($entry['form_id'] == 30){
                    $name = $entry[19];
                    $designators = $entry[1];
                    //$exam_no = $entry['27'];
                }
                if($entry['form_id'] == 15){
                    $designators = implode(', ', $designators);
                    $exam_no = $entry['789'];
                    //$name=$entry['1'];
                }
                ?>
                <tr class="border-t">
                <td class="px-4 py-2">
                <?php if (!empty($photo_url)) {
                                    echo '<div style="width:50px; height:50px;border-radius:50%;"><img style="width:50px; height:50px;border-radius:50%;" src="' . esc_url($photo_url) . '" alt="Profile Photo"></div>';
                                } else {
                                    echo '<div style="width:50px; height:50px; background:#ccc; display:flex; align-items:center; justify-content:center; border-radius:50%;">No Photo</div>';
                                }?>
                                </td>
                    <td class="px-4 py-2"><?php echo esc_html($exam_no); ?></td>
                    <td class="px-4 py-2"><?php echo esc_html($name); ?></td>
                    <td class="px-4 py-2"><?php echo esc_html($designators); ?></td>
                    <td class="px-4 py-2">
                       <?php echo $st; ?>
            </td>
            <td class="px-4 py-2">
                <a href="<?php echo esc_url(admin_url('admin.php?page=examiner-dashboard&entry_id=' . $eid)); ?>"
                 class="button-primary">
                 View
             </a>
         </td>
     </tr>
 <?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php
}



