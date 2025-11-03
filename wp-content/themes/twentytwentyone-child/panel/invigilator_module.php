<?php
function carm_add_admin_menus() {
    if (!current_user_can('custom_invigilator') && !current_user_can('administrator')) {
        return;
    }

    add_submenu_page(
        'edit.php?post_type=exam_center',         // Parent menu slug
        'Add Entry Record',                      // Page title
        'Add Entry Record',                      // Menu title
        'custom_invigilator',                    // Capability
        'invigilator-dashboard',                 // Menu slug
        'carm_invigilator_dashboard'             // Callback function
    );
}
add_action('admin_menu', 'carm_add_admin_menus');

function carm_invigilator_dashboard() {
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
        'form_39' => [
            'exam_order_no' => '12',
            //'candidate_name' => '19',
            'methods' => '1',
            'designatore_fee' => '10',
            'center_fee' => '11.2',
            'payment_amount' => 'payment_amount',
            'payment_status' => 'payment_status',
        ],
    ];

    // // Determine form ID and retest status
    // $form_id = isset($_GET['form_id']) && $_GET['form_id'] == 30 ? 30 : 15;
    // $is_retest = $form_id == 30;
    // $is_renew_recert = $form_id == 31;
    // Detect form_id from URL, default to 15
    $form_id = isset($_GET['form_id']) && in_array($_GET['form_id'], ['30', '39', '15']) ? intval($_GET['form_id']) : 15;
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
        $profile_photo = get_user_meta($user_data->ID, 'custom_profile_photo', true);
        $first_name = get_user_meta($user_data->ID, 'first_name', true);
        $last_name = get_user_meta($user_data->ID, 'last_name', true);
        $candidate_name = ($first_name || $last_name) ? trim($first_name . ' ' . $last_name) : 'N/A';
        ?>
            <h1 class="text-3xl font-bold mb-6 text-gray-800">Invigilator Entry Details <?= $is_retest ? '(Retest)' : '' ?></h1>
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
                                <td class="font-medium px-4 py-2"><strong>Designator Fee:</strong></td>
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
                <div class="invigilator_data">
                    <div class="invigilator_sidebar bg-white p-6 rounded-lg shadow">
                        <?php
                        $user_meta_key = '_assigned_entries_invigilator_status';
                        $user_statuses = get_user_meta($user_id, $user_meta_key, true);
                        $user_statuses = is_array($user_statuses) ? $user_statuses : [];
                        $status = isset($user_statuses[$entry_id]) ? $user_statuses[$entry_id] : 'pending';
                      
                        ?>
                        <h2 class="text-xl font-semibold mb-4 text-gray-800">Actions</h2>
                        <?php if ($status === 'pending') { ?>
                            <div class="invigilator_action flex flex-col gap-3 mb-0">
                                <button id="acceptEntry" class="button button-primary bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">
                                    <i class="fas fa-check mr-2"></i> Accept
                                </button>
                                <button id="declineEntry" class="button button-secondary bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded">
                                    <i class="fas fa-times mr-2"></i> Decline
                                </button>
                            </div>
                        <?php } else { ?>
                            <h3 class="text-lg font-semibold mb-3 text-gray-800">Status</h3>
                            <button class="button button-primary bg-<?php echo $status === 'accepted' ? 'green' : 'red'; ?>-600 text-white font-medium py-2 px-4 rounded w-full" disabled>
                                <?php echo esc_html(ucfirst($status)); ?>
                            </button>
                        <?php } ?>
                    </div>
                    <?php if ($status === 'accepted') { ?>
                        <h2 class="text-xl font-semibold mb-4 mt-6 text-gray-800">Update Invigilator Data</h2>
                        <?php
                        $method_slots = gform_get_meta($entry_id, '_method_slots');
                        $method_slots = is_array($method_slots) ? $method_slots : [];
                        $invigilator_data = gform_get_meta($entry_id, '_invigilator_update_record');
                        $invigilator_data = is_array($invigilator_data) ? $invigilator_data : [];
                        ?>
                        <form id="editInvigilatorForm" class="space-y-6">
                            <?php foreach ($method_slots as $method => $slots): 
                                foreach ($slots as $slot_key => $slot): 
                                    if (empty($slot['date']) || empty($slot['time'])) continue;
                                    $key = "{$method}|{$slot_key}";
                                    $checkin_time = isset($invigilator_data[$key]['checkin_time']) ? $invigilator_data[$key]['checkin_time'] : '';
                                    $checkout_time = isset($invigilator_data[$key]['checkout_time']) ? $invigilator_data[$key]['checkout_time'] : '';
                                    $proofs_verified_status = isset($invigilator_data[$key]['proofs_verified_status']) ? $invigilator_data[$key]['proofs_verified_status'] : 'pending';
                                    $comments = isset($invigilator_data[$key]['comments']) ? $invigilator_data[$key]['comments'] : '';
                                    $formatted_slot_label = ucfirst(str_replace('_', ' ', $slot_key));
                                    $formatted_method = ucfirst(str_replace('_', ' ', $method));
                                    $start_datetime = date('Y-m-d\TH:i', strtotime("{$slot['date']} {$slot['time']}"));
                                    ?>
                                    <div class="method-block border border-gray-200 rounded-lg p-4 bg-gray-50">
                                        <h3 class="font-semibold mb-3 text-base text-gray-800"><?php echo esc_html("{$formatted_method} - {$formatted_slot_label}"); ?> Record</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Check-In Time (Scheduled: <?php echo esc_html(date('d M Y H:i', strtotime($slot['date'] . ' ' . $slot['time']))); ?>)
                                                </label>
                                                <input type="datetime-local" 
                                                       name="checkin_time[<?php echo esc_attr($key); ?>]" 
                                                       id="checkin_<?php echo esc_attr($key); ?>" 
                                                       value="<?php echo esc_attr($checkin_time); ?>" 
                                                       class="datetime-field block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-3 py-2" />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Check-Out Time
                                                </label>
                                                <input type="datetime-local" 
                                                       name="checkout_time[<?php echo esc_attr($key); ?>]" 
                                                       id="checkout_<?php echo esc_attr($key); ?>" 
                                                       value="<?php echo esc_attr($checkout_time); ?>" 
                                                       class="datetime-field block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-3 py-2" />
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Document Verified
                                            </label>
                                            <select name="proofs_verified_status[<?php echo esc_attr($key); ?>]" 
                                                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-3 py-2">
                                                <option value="pending" <?php selected($proofs_verified_status, 'pending'); ?>>Pending</option>
                                                <option value="verified" <?php selected($proofs_verified_status, 'verified'); ?>>Verified</option>
                                                <option value="not_verified" <?php selected($proofs_verified_status, 'not_verified'); ?>>Not Verified</option>
                                            </select>
                                        </div>
                                        <div class="mt-3">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Comments
                                            </label>
                                            <textarea name="comments[<?php echo esc_attr($key); ?>]" 
                                                      class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-3 py-2"><?php echo esc_textarea($comments); ?></textarea>
                                        </div>
                                    </div>
                                <?php endforeach; endforeach; ?>
                                <button type="submit" class="button button-primary bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded mt-4">
                                    <i class="fas fa-save mr-2"></i> Update Entry Record
                                </button>
                                <div id="loader" class="mt-4 hidden">
                                    <img src="<?php echo admin_url('images/spinner.gif'); ?>" alt="Loading...">
                                </div>
                                <div id="statusMessage" class="mt-4 text-center"></div>
                            </form>
                        <?php } ?>
                    </div>
                
            </div>

            <style>
                .invigilator_edit_page {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                }
                .method-block {
                    background-color: #f9fafb;
                    border-radius: 8px;
                    padding: 16px;
                    margin-bottom: 16px;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                }
                .method-block h3 {
                    margin-bottom: 12px;
                    font-size: 16px;
                    color: #1f2937;
                }
                .method-block input, .method-block select, .method-block textarea {
                    padding: 8px;
                    border: 1px solid #d1d5db;
                    border-radius: 6px;
                    width: 100%;
                    font-size: 14px;
                    transition: border-color 0.2s, box-shadow 0.2s;
                }
                .method-block input:focus, .method-block select:focus, .method-block textarea:focus {
                    outline: none;
                    border-color: #2563eb;
                    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
                }
                .invigilator_sidebar {
                    background-color: #ffffff;
                    border-radius: 8px;
                    padding: 24px;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }
                .wp-list-table {
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    overflow: hidden;
                }
                .status-pending {
                    color: #6b7280;
                    font-weight: 500;
                }
                .status-paid {
                    color: #15803d;
                    font-weight: 500;
                }
            </style>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const now = new Date();
                    const pad = n => n < 10 ? '0' + n : n;
                    const formattedNow = now.getFullYear() + '-' +
                                        pad(now.getMonth() + 1) + '-' +
                                        pad(now.getDate()) + 'T' +
                                        pad(now.getHours()) + ':' +
                                        pad(now.getMinutes());
                    document.querySelectorAll('.datetime-field').forEach(input => {
                        input.setAttribute('max', formattedNow);
                    });

                    jQuery('#editInvigilatorForm').on('submit', function (event) {
                        event.preventDefault();
                        const now = new Date();
                        let hasFutureError = false;
                        jQuery('.datetime-field').each(function () {
                            const val = jQuery(this).val();
                            if (val && new Date(val) > now) {
                                hasFutureError = true;
                                jQuery(this).addClass('border-red-500');
                            } else {
                                jQuery(this).removeClass('border-red-500');
                            }
                        });

                        if (hasFutureError) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Invalid Time',
                                text: 'Future time is not allowed. Please correct the highlighted fields.',
                            });
                            return;
                        }

                        const entryId = '<?php echo esc_js($entry_id); ?>';
                        const formData = {
                            action: 'update_invigilator_entry',
                            entry_id: entryId,
                            checkin_time: {},
                            checkout_time: {},
                            proofs_verified_status: {},
                            comments: {}
                        };

                        jQuery('.method-block').each(function () {
                            const method = jQuery(this).find('input, select, textarea').first().attr('name').match(/\[([^\]]*)\]/)[1];
                            formData.checkin_time[method] = jQuery(this).find('input[name^="checkin_time"]').val();
                            formData.checkout_time[method] = jQuery(this).find('input[name^="checkout_time"]').val();
                            formData.proofs_verified_status[method] = jQuery(this).find('select[name^="proofs_verified_status"]').val();
                            formData.comments[method] = jQuery(this).find('textarea[name^="comments"]').val();
                        });

                        jQuery('#loader').fadeIn();
                        jQuery('#statusMessage').html('');

                        jQuery.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: formData,
                            success: function (response) {
                                jQuery('#loader').fadeOut();
                                if (response.success) {
                                    jQuery('.saveEntryDetails').prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
                                    Swal.fire({
                                        title: 'Updated!',
                                        text: 'The entry has been updated.',
                                        icon: 'success',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: 'Error updating entry. Please try again.'
                                    });
                                }
                            },
                            error: function () {
                                jQuery('#loader').fadeOut();
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Network Error',
                                    text: 'Please check your connection.'
                                });
                            }
                        });
                    });

                    function handleEntryStatus(status, comments = '') {
                        jQuery('#loader').fadeIn();
                        jQuery('#statusMessage').html('');
                        jQuery.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'invigilator_acceptance_status',
                                entry_id: '<?php echo esc_js($entry_id); ?>',
                                status: status,
                                comments: comments
                            },
                            success: function (response) {
                                jQuery('#loader').fadeOut();
                                if (response.success) {
                                    Swal.fire({
                                        title: status === 'accepted' ? 'Accepted!' : 'Declined!',
                                        text: `The entry has been ${status === 'accepted' ? 'accepted' : 'declined'}.`,
                                        icon: 'success',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        if (status === 'accepted') {
                                            jQuery('.invigilator_data').show();
                                        }
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: `Error ${status === 'accepted' ? 'accepting' : 'declining'} entry. Please try again.`
                                    });
                                }
                            },
                            error: function () {
                                jQuery('#loader').fadeOut();
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Network Error',
                                    text: 'Please check your connection.'
                                });
                            }
                        });
                    }

                    jQuery('#acceptEntry').on('click', function () {
                        const declarationText = `
                            <strong>Declaration of Independence</strong><br><br>
                            I hereby give an undertaking in respect of the commitments, as an Invigilator
                            that I <strong>do not have any conflict of interest</strong> with the persons mentioned to be examined —
                            including independence from commercial and other interests, and from any prior and/or present link.<br><br>
                            I also confirm that <strong>I have not been a trainer for the below listed candidates in the last 2 years</strong>
                            for the below methods sought.
                        `;
                        Swal.fire({
                            title: 'Conflict of Interest Declaration',
                            html: declarationText,
                            icon: 'info',
                            input: 'checkbox',
                            inputPlaceholder: 'I agree to the above declaration',
                            inputValidator: (value) => {
                                if (!value) {
                                    return 'You must agree before accepting the assignment';
                                }
                            },
                            confirmButtonText: 'Accept Assignment',
                            showCancelButton: true,
                            cancelButtonText: 'Cancel',
                        }).then((result) => {
                            if (result.isConfirmed) {
                                Swal.fire({
                                    title: 'Processing...',
                                    text: 'Please wait while we approve the entry.',
                                    allowOutsideClick: false,
                                    allowEscapeKey: false,
                                    didOpen: () => Swal.showLoading()
                                });
                                handleEntryStatus('accepted');
                            }
                        });
                    });

                    jQuery('#declineEntry').on('click', function () {
                        const conflictText = `
                            <strong>Declaration of Conflict</strong><br><br>
                            I am unable to proceed with this assignment as an Invigilator due to a
                            <strong>conflict of interest</strong> with the persons to be examined —
                            which may include commercial or other interests, or prior/present links.<br><br>
                            I may have been a trainer for the below candidates in the last 2 years
                            for the methods sought, or have other grounds to decline this assignment.
                        `;
                        Swal.fire({
                            title: 'Conflict of Interest - Decline Entry',
                            html: conflictText,
                            icon: 'warning',
                            input: 'textarea',
                            inputPlaceholder: 'Please specify the reason for declining...',
                            inputAttributes: {
                                'aria-label': 'Reason for declining',
                            },
                            inputValidator: (value) => {
                                if (!value) {
                                    return 'Reason is required to decline this entry.';
                                }
                            },
                            showCancelButton: true,
                            confirmButtonText: 'Submit Decline',
                            cancelButtonText: 'Cancel',
                        }).then((result) => {
                            if (result.isConfirmed) {
                                Swal.fire({
                                    title: 'Processing...',
                                    text: 'Please wait while we decline the entry.',
                                    allowOutsideClick: false,
                                    allowEscapeKey: false,
                                    didOpen: () => Swal.showLoading()
                                });
                                handleEntryStatus('declined', result.value);
                            }
                        });
                    });
                });
            </script>
        <?php
    } else {
        $current_user_id = get_current_user_id();

// Get entries based on role
$allowed_form_ids = [15, 30, 39];
$entries = [];

if (current_user_can('administrator')) {
    foreach ($allowed_form_ids as $form_id) {
        $search_criteria = [];
        $sorting = [];
        $paging = ['offset' => 0, 'page_size' => 9999];
        $form_entries = GFAPI::get_entries($form_id, $search_criteria, $sorting, $paging);
        foreach ($form_entries as $entry) {
            $entry['form_id'] = $form_id;
            $entries[] = $entry;
        }
    }
} else {
    $entry_ids = get_user_meta($current_user_id, '_assigned_entries_invigilator', true);
    if (!is_array($entry_ids)) {
        $entry_ids = !empty($entry_ids) ? explode(',', $entry_ids) : [];
    }

    foreach ($entry_ids as $entry_id) {
        $entry_data = GFAPI::get_entry($entry_id);
        if (!is_wp_error($entry_data) && in_array($entry_data['form_id'], $allowed_form_ids)) {
            $entries[] = $entry_data;
        }
    }
}


        if (empty($entries)) {
            echo '<p class="text-red-600">No assigned entries found.</p>';
            return;
        }

        // Sort entries by creation date descending
        usort($entries, function ($a, $b) {
            return strtotime($b['date_created']) - strtotime($a['date_created']);
        });
        $user_id = $entry['created_by'] ?? get_current_user_id();

        $user_data = get_userdata($user_id);
       $first_name = get_user_meta($user_data->ID, 'first_name', true);
        $last_name = get_user_meta($user_data->ID, 'last_name', true);
        $candidate_name = ($first_name || $last_name) ? trim($first_name . ' ' . $last_name) : 'N/A';

        // Output
        ?>
        <div class="wrap max-w-7xl mx-auto py-6">
            <h1 class="text-3xl font-bold mb-6 text-gray-800">Invigilator Dashboard</h1>
            <div class="search_form flex flex-wrap gap-4 mb-6 controls-container bg-white p-4 rounded-lg shadow">
                <div class="form_controls flex-1 min-w-[200px]">
                    <input type="text" id="searchInput" class="border p-3 rounded-lg w-full focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder-gray-400 text-sm" placeholder="Search users..." value="<?php echo esc_attr(isset($_GET['search']) ? sanitize_text_field($_GET['search']) : ''); ?>">
                </div>
                <div class="form_controls flex-1 min-w-[150px]">
                    <select id="designatorFilter" class="border p-3 rounded-lg w-full focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">All Designators</option>
                        <?php
                        $designators = ['ET', 'MT', 'PT', 'UT', 'RT', 'VT', 'TT', 'PAUT', 'TOFD'];
                        foreach ($designators as $designator) {
                            $selected = (isset($_GET['designator']) && $_GET['designator'] === $designator) ? 'selected' : '';
                            echo "<option value='$designator' $selected>$designator</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form_controls flex-1 min-w-[150px]">
                    <select id="statusFilter" class="border p-3 rounded-lg w-full focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">All Statuses</option>
                        <?php
                        $statuses = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
                        foreach ($statuses as $key => $label) {
                            $selected = (isset($_GET['status']) && $_GET['status'] === $key) ? 'selected' : '';
                            echo "<option value='$key' $selected>$label</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form_controls">
                    <button id="exportCsv" class="button-primary bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg">Export CSV</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table_custom wp-list-table widefat striped dataTable no-footer" id="certifiedTable">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-gray-700 font-medium">Sr No</th>
                            <th class="px-6 py-3 text-gray-700 font-medium">Img</th>
                            <th class="px-6 py-3 text-gray-700 font-medium" data-sort="exam_number">Exam Order No <span class="sort-arrow">▲</span></th>
                            <th class="px-6 py-3 text-gray-700 font-medium" data-sort="name">Student Name <span class="sort-arrow">▲</span></th>
                            <th class="px-6 py-3 text-gray-700 font-medium" data-sort="designator">Designator <span class="sort-arrow">▲</span></th>
                            <th class="px-6 py-3 text-gray-700 font-medium" data-sort="status">Status <span class="sort-arrow">▲</span></th>
                            <th class="px-6 py-3 text-gray-700 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100" id="tableBody">
                        <?php
                        foreach ($entries as $index => $entry):
                            $entry_id = $entry['id'];
                            $entry_form_id = $entry['form_id'];
                            $config = $field_map['form_' . $entry_form_id];
                            $entry_meta = gform_get_meta($entry_id, '_invigilator_verification');
                            $entry_meta = is_array($entry_meta) ? $entry_meta : [];
                            $user_id = $entry['created_by'] ?? get_current_user_id();

                            $user_data = get_userdata($user_id);
                            $photo_url = get_user_meta($user_data->ID, 'custom_profile_photo', true);
                            $first_name = get_user_meta($user_data->ID, 'first_name', true);
                            $last_name = get_user_meta($user_data->ID, 'last_name', true);
                            $candidate_name = ($first_name || $last_name) ? trim($first_name . ' ' . $last_name) : 'N/A';

                            // Get methods
                            $field_188_values = [];
                            if ($entry_form_id == 30 || $entry_form_id == 39) {
                                $method_value = trim(rgar($entry, $config['methods']) ?? '');
                                $field_188_values = !empty($method_value) ? [$method_value] : [];
                            } else {
                                foreach ($config['methods'] as $key => $label) {
                                    if (!empty(rgar($entry, $key))) {
                                        $field_188_values[] = $label;
                                    }
                                }
                            }
                            $entry_meta_key = '_invigilator_status_summary';
                            $entry_stas     = gform_get_meta($entry_id, $entry_meta_key);

                               
                            $user_meta_key = '_assigned_entries_invigilator_status';

                            $user_statuses = get_user_meta($current_user_id, $user_meta_key, true);
                            $user_statuses = is_array($user_statuses) ? $user_statuses : [];
                            $status = isset($user_statuses[$entry_id]) ? $user_statuses[$entry_id] : 'pending';
                            $status_badge = '<span class="px-2 py-1 text-xs font-medium rounded-full ' . ($status === 'approved' ? 'bg-green-100 text-green-800' : ($status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-gray-200 text-gray-700')) . '">' . ucfirst($status) . '</span>';
                            // Show status badges for all invigilators if available
                            if (!empty($entry_stas) && is_array($entry_stas)) {
                                $status_badge = '';
                                foreach ($entry_stas as $invigilator_id => $invigilator_status) {
                                    $user = get_userdata($invigilator_id);
                                    $badge_color = $invigilator_status === 'approved' ? 'bg-green-100 text-green-800' : ($invigilator_status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-gray-200 text-gray-700');
                                    $display_name = $user ? esc_html($user->display_name) : '(Unknown User ID: ' . intval($invigilator_id) . ')';
                                    $status_badge .= '<span class="px-2 py-1 text-xs font-medium rounded-full ' . $badge_color . '">' . ucfirst(esc_html($invigilator_status)) . ' - ' . $display_name . '</span><br>';
                                }
                            } else {
                                $status_badge = '<span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-200 text-gray-700">Pending</span>';
                            }
                            ?>
                            <tr class="cert-row hover:bg-blue-50 transition-colors duration-200 cursor-pointer">
                                <td class="px-6 py-4 whitespace-nowrap text-gray-700 text-sm font-medium text-center"><?php echo esc_html($index + 1); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-700 text-sm font-medium text-center">
                                <?php if (!empty($photo_url)) {
                                    echo '<div style="width:50px; height:50px;border-radius:50%;"><img style="width:50px; height:50px;border-radius:50%;" src="' . esc_url($photo_url) . '" alt="Profile Photo"></div>';
                                } else {
                                    echo '<div style="width:50px; height:50px; background:#ccc; display:flex; align-items:center; justify-content:center; border-radius:50%;">No Photo</div>';
                                }?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-700 text-sm"><?php echo esc_html(rgar($entry, $config['exam_order_no'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-800 text-sm font-medium"><?php echo $candidate_name; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-700 text-sm"><?php echo esc_html(!empty($field_188_values) ? implode(', ', $field_188_values) : 'None'); ?></td>

                                <td class="px-6 py-4 whitespace-nowrap text-gray-700 text-sm"><?php echo $status_badge; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=invigilator-dashboard&entry_id=' . esc_attr($entry_id) . '&form_id=' . esc_attr($entry_form_id))); ?>" class="doc_link text-blue-600 hover:text-blue-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6zm1 2h7v5h5v10H7V4zm7-1.5L16.5 7H13V2.5zM8 11h8v2H8v-2zm0 4h8v2H8v-2z"/>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="table_footer mt-4">
                    <div id="pagination" class="flex justify-center items-center gap-2"></div>
                    <div id="totalCount" class="text-center mt-2 text-gray-600">Showing 1 to <?php echo esc_html(min(10, count($entries))); ?> of <?php echo esc_html(count($entries)); ?> entries</div>
                </div>
            </div>
        </div>

      <!--   <style>
            .controls-container {
                background-color: #ffffff;
                padding: 16px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            .search_form {
                display: flex;
                flex-wrap: wrap;
                gap: 16px;
            }
            .table_custom {
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                overflow: hidden;
            }
            .sort-arrow {
                font-size: 1.2em;
                margin-left: 5px;
                vertical-align: middle;
                color: #374151;
            }
            .form_controls input, .form_controls select, .form_controls button {
                padding: 8px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-size: 14px;
                transition: border-color 0.2s, box-shadow 0.2s;
            }
            .form_controls input:focus, .form_controls select:focus {
                outline: none;
                border-color: #2563eb;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            }
            .form_controls button {
                background-color: #2563eb;
                color: #ffffff;
                border: none;
            }
            .form_controls button:hover {
                background-color: #1d4ed8;
            }
            @media (min-width: 768px) {
                .form_controls {
                    flex: 1;
                    max-width: 25%;
                }
                .form_controls button {
                    flex-shrink: 0;
                }
            }
        </style> -->

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const headers = document.querySelectorAll('#certifiedTable thead th[data-sort]');
                const searchInput = document.getElementById('searchInput');
                const designatorFilter = document.getElementById('designatorFilter');
                const statusFilter = document.getElementById('statusFilter');
                const tableBody = document.querySelector('#tableBody');
                const paginationDiv = document.getElementById('pagination');
                const totalCountDiv = document.getElementById('totalCount');
                let currentSortColumn = null;
                let currentSortDirection = 'asc';
                let currentPage = 1;
                const rowsPerPage = 10;
                const totalUnfilteredRows = <?php echo count($entries); ?>;

                function getCellValue(row, column) {
                    const columnMap = {
                        'exam_number': 1,
                        'name': 2,
                        'designator': 3,
                        'status': 4
                    };
                    const cellIndex = columnMap[column];
                    if (cellIndex === undefined) return '';
                    const cell = row.cells[cellIndex];
                    let value = cell.innerText.trim();
                    return value.toLowerCase();
                }

                function updateHeaderIndicators(activeColumn) {
                    headers.forEach(header => {
                        const isActive = header.dataset.sort === activeColumn;
                        const sortSpan = header.querySelector('.sort-arrow');
                        if (isActive) {
                            sortSpan.textContent = currentSortDirection === 'asc' ? '▲' : '▼';
                        } else {
                            sortSpan.textContent = '▲';
                        }
                    });
                }

                headers.forEach(header => {
                    const sortSpan = header.querySelector('.sort-arrow');
                    if (sortSpan) sortSpan.textContent = '▲';
                });

                headers.forEach(header => {
                    header.addEventListener('click', () => {
                        const column = header.dataset.sort;
                        if (!column) return;

                        const rows = Array.from(document.querySelectorAll('#certifiedTable tbody tr'));
                        const direction = currentSortColumn === column && currentSortDirection === 'asc' ? 'desc' : 'asc';

                        rows.sort((a, b) => {
                            const aValue = getCellValue(a, column);
                            const bValue = getCellValue(b, column);
                            return direction === 'asc' 
                                ? aValue.localeCompare(bValue) 
                                : bValue.localeCompare(aValue);
                        });

                        rows.forEach((row, index) => {
                            row.cells[0].innerText = index + 1;
                        });

                        const tbody = document.querySelector('#certifiedTable tbody');
                        tbody.innerHTML = '';
                        rows.forEach(row => tbody.appendChild(row));

                        currentSortColumn = column;
                        currentSortDirection = direction;
                        updateHeaderIndicators(column);
                        applyFilters();
                    });
                });

                searchInput.addEventListener('input', function () {
                    applyFilters();
                });

                designatorFilter.addEventListener('change', function () {
                    applyFilters();
                });

                statusFilter.addEventListener('change', function () {
                    applyFilters();
                });

                function applyFilters() {
                    const searchTerm = searchInput.value.toLowerCase();
                    const selectedDesignator = designatorFilter.value;
                    const selectedStatus = statusFilter.value;
                    const rows = tableBody.getElementsByTagName('tr');
                    let visibleRows = [];
                    let isFiltered = false;

                    Array.from(rows).forEach(row => {
                        const cells = row.getElementsByTagName('td');
                        const designatorCell = cells[3].innerText;
                        const statusCell = cells[4].innerText.toLowerCase();
                        let searchMatch = true;
                        let designatorMatch = true;
                        let statusMatch = true;

                        if (searchTerm) {
                            searchMatch = false;
                            for (let i = 1; i < cells.length - 1; i++) {
                                if (cells[i].innerText.toLowerCase().includes(searchTerm)) {
                                    searchMatch = true;
                                    break;
                                }
                            }
                            isFiltered = true;
                        }

                        if (selectedDesignator && selectedDesignator !== '') {
                            designatorMatch = designatorCell.split(', ').includes(selectedDesignator);
                            isFiltered = true;
                        }

                        if (selectedStatus && selectedStatus !== '') {
                            statusMatch = statusCell.includes(selectedStatus);
                            isFiltered = true;
                        }

                        if (searchMatch && designatorMatch && statusMatch) {
                            row.style.display = '';
                            visibleRows.push(row);
                        } else {
                            row.style.display = 'none';
                        }
                    });

                    updatePagination(visibleRows, isFiltered);
                }

                function updatePagination(visibleRows, isFiltered) {
                    const totalRows = visibleRows.length;
                    const totalPages = Math.ceil(totalRows / rowsPerPage);
                    paginationDiv.innerHTML = '';

                    const prevButton = document.createElement('button');
                    prevButton.textContent = 'Previous';
                    prevButton.className = 'px-3 py-1 border rounded-md bg-white text-gray-700 hover:bg-gray-100';
                    prevButton.addEventListener('click', () => {
                        if (currentPage > 1) {
                            currentPage--;
                            updatePagination(visibleRows, isFiltered);
                        }
                    });
                    paginationDiv.appendChild(prevButton);

                    for (let i = 1; i <= totalPages; i++) {
                        const pageButton = document.createElement('button');
                        pageButton.textContent = i;
                        pageButton.className = `px-3 py-1 border rounded-md ${currentPage === i ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'}`;
                        pageButton.addEventListener('click', () => {
                            currentPage = i;
                            updatePagination(visibleRows, isFiltered);
                        });
                        paginationDiv.appendChild(pageButton);
                    }

                    const nextButton = document.createElement('button');
                    nextButton.textContent = 'Next';
                    nextButton.className = 'px-3 py-1 border rounded-md bg-white text-gray-700 hover:bg-gray-100';
                    nextButton.addEventListener('click', () => {
                        if (currentPage < totalPages) {
                            currentPage++;
                            updatePagination(visibleRows, isFiltered);
                        }
                    });
                    paginationDiv.appendChild(nextButton);

                    const startIndex = (currentPage - 1) * rowsPerPage + 1;
                    const endIndex = Math.min(currentPage * rowsPerPage, totalRows);
                    if (isFiltered && totalUnfilteredRows > totalRows) {
                        totalCountDiv.textContent = `Showing ${startIndex} to ${endIndex} of ${totalRows} entries (filtered from ${totalUnfilteredRows} total entries)`;
                    } else {
                        totalCountDiv.textContent = `Showing ${startIndex} to ${endIndex} of ${totalRows} entries`;
                    }

                    renderPage(visibleRows);
                }

                function renderPage(visibleRows) {
                    const start = (currentPage - 1) * rowsPerPage;
                    const end = start + rowsPerPage;
                    const paginatedRows = visibleRows.slice(start, end);

                    Array.from(tableBody.getElementsByTagName('tr')).forEach(row => {
                        row.style.display = 'none';
                    });

                    paginatedRows.forEach((row, index) => {
                        row.style.display = '';
                        row.cells[0].innerText = index + 1 + (currentPage - 1) * rowsPerPage;
                    });
                }

                document.getElementById('exportCsv').addEventListener('click', function () {
                    const csvRows = [
                        ['Exam Order No', 'Student Name', 'Designator', 'Status']
                    ];
                    document.querySelectorAll('#tableBody tr').forEach(row => {
                        if (row.style.display === 'none') return;
                        const cols = row.querySelectorAll('td');
                        csvRows.push([
                            cols[1].innerText,
                            cols[2].innerText,
                            cols[3].innerText,
                            cols[4].innerText
                        ]);
                    });

                    const csvContent = csvRows.map(e => e.join(",")).join("\n");
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'invigilator_dashboard.csv';
                    a.click();
                    URL.revokeObjectURL(url);
                });

                applyFilters();
            });
        </script>
    <?php
    }
}

add_action('wp_ajax_invigilator_acceptance_status', 'handle_invigilator_entry_response');

function handle_invigilator_entry_response() {
    $user_id = get_current_user_id();
    $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $comment = isset($_POST['comments']) ? sanitize_textarea_field($_POST['comments']) : '';

    if (!$entry_id || !in_array($status, ['accepted', 'declined'], true)) {
        wp_send_json_error('Invalid request.');
    }

    $update_result = update_invigilator_entry_status($user_id, $entry_id, $status, $comment);
    if (!$update_result) {
        wp_send_json_error('Failed to update status.');
    }

    // Send notification
    if (in_array($status, ['accepted', 'declined'])) {
        $user = get_userdata($user_id);
        $entry = GFAPI::get_entry($entry_id);
        $form_id = $entry['form_id'];
        $field_map = [
            'form_15' => ['exam_order_no' => '789', 'center_name' => '833'],
            'form_30' => ['exam_order_no' => '12', 'center_name' => '13'],
        ];
        $config = $field_map['form_' . $form_id];
        $order_number = rgar($entry, $config['exam_order_no']) ?: 'Unknown Order';
        $center_id = gform_get_meta($entry_id, '_linked_exam_center');
        $center_name = get_the_title($center_id);
        $center_post_name = trim(rgar($entry, $config['center_name']));
        $center_post = get_page_by_title($center_post_name, OBJECT, 'exam_center');
        $center_admin_id = $center_post ? get_post_meta($center_post->ID, '_center_admin_id', true) : 0;
        $aqb_admin_id = $center_post ? get_post_meta($center_post->ID, '_aqb_admin_id', true) : 0;
        $first_name = get_user_meta($user->ID, 'first_name', true);
        $last_name = get_user_meta($user->ID, 'last_name', true);
        $candidate_name = ($first_name || $last_name) ? trim($first_name . ' ' . $last_name) : 'N/A';

        $subject = "Invigilator " . ($status === 'accepted' ? 'Accepted' : 'Declined') . ": Exam Order #$order_number";
        $body = "<p><strong>{$candidate_name}</strong> has " . ($status === 'accepted' ? 'accepted' : 'declined') . " the invigilation assignment.</p>";
        $body .= "<p><strong>Order:</strong> #{$order_number}</p>";
        $body .= "<p><strong>Center:</strong> " . esc_html($center_name) . "</p>";
        if ($status === 'declined' && !empty($comment)) {
            $body .= "<p><strong>Comment:</strong> " . esc_html($comment) . "</p>";
        }

        $message = get_email_template($subject, $body);

        $center_admin = $center_admin_id ? get_userdata($center_admin_id) : null;
        $aqb_admin = $aqb_admin_id ? get_userdata($aqb_admin_id) : null;
        $admin_users = get_users([
            'role' => 'administrator',
            'fields' => ['user_email'],
        ]);

        $admin_emails = [];
        if ($center_admin && is_email($center_admin->user_email)) {
            $admin_emails[] = $center_admin->user_email;
        }
        if ($aqb_admin && is_email($aqb_admin->user_email)) {
            $admin_emails[] = $aqb_admin->user_email;
        }
        foreach ($admin_users as $admin_user) {
            if (is_email($admin_user->user_email) && !in_array($admin_user->user_email, $admin_emails)) {
                $admin_emails[] = $admin_user->user_email;
            }
        }

        add_filter('wp_mail_content_type', function () { return 'text/html'; });
        wp_mail($admin_emails, $subject, $message);
        remove_filter('wp_mail_content_type', function () { return 'text/html'; });
    }

    wp_send_json_success(['message' => 'Status updated and emails sent.']);
}

function update_invigilator_entry_status($user_id, $entry_id, $status, $comment = '') {
    if (!in_array($status, ['accepted', 'declined'], true)) {
        return false;
    }

    $user_meta_key = '_assigned_entries_invigilator_status';
    $user_statuses = get_user_meta($user_id, $user_meta_key, true);
    if (!is_array($user_statuses)) {
        $user_statuses = [];
    }
    $user_statuses[$entry_id] = $status;
    update_user_meta($user_id, $user_meta_key, $user_statuses);

    $entry_meta_key = '_invigilator_status_summary';
    $entry_statuses = gform_get_meta($entry_id, $entry_meta_key);
    if (!is_array($entry_statuses)) {
        $entry_statuses = [];
    }
    $entry_statuses[$user_id] = $status;
    gform_update_meta($entry_id, $entry_meta_key, $entry_statuses);

    if (!empty($comment)) {
        $comment_meta_key = '_invigilator_comments';
        $entry_comments = gform_get_meta($entry_id, $comment_meta_key);
        if (!is_array($entry_comments)) {
            $entry_comments = [];
        }
        $entry_comments[$user_id] = $comment;
        gform_update_meta($entry_id, $comment_meta_key, $entry_comments);
    }

    if ($status === 'declined') {
        $assigned_invigilators = gform_get_meta($entry_id, '_assigned_invigilators');
        if (is_array($assigned_invigilators)) {
            $updated_invigilators = array_values(array_filter($assigned_invigilators, function($val) use ($user_id) {
                return (int) $val !== (int) $user_id;
            }));
            gform_update_meta($entry_id, '_assigned_invigilators', $updated_invigilators);
        }
    }

    return true;
}

add_action('wp_ajax_update_invigilator_entry', 'update_invigilator_entry_handler');
function update_invigilator_entry_handler() {
    $entry_id = intval($_POST['entry_id']);
    $checkin_times = $_POST['checkin_time'] ?? [];
    $checkout_times = $_POST['checkout_time'] ?? [];
    $statuses = $_POST['proofs_verified_status'] ?? [];
    $comments = $_POST['comments'] ?? [];

    $record_data = [];
    foreach ($checkout_times as $method => $checkout_time) {
        $record_data[$method] = [
            'checkin_time' => sanitize_text_field($checkin_times[$method] ?? ''),
            'checkout_time' => sanitize_text_field($checkout_time),
            'proofs_verified_status' => sanitize_text_field($statuses[$method] ?? 'pending'),
            'comments' => sanitize_textarea_field($comments[$method] ?? ''),
        ];
    }

    gform_update_meta($entry_id, '_invigilator_update_record', $record_data);
    wp_send_json_success(['message' => 'Record updated.']);
}



?>