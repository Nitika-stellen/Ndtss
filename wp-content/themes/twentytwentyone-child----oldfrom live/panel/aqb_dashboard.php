<?php
function register_aqb_admin_dashboard() {
    if ( current_user_can('custom_aqb') || current_user_can('administrator') ) {
        add_menu_page(
            'AQB Dashboard', 'AQB Dashboard', 'read',
            'aqb-dashboard', 'render_aqb_admin_dashboard',
            'dashicons-clipboard-check', 30
        );
    }
}
add_action('admin_menu', 'register_aqb_admin_dashboard');

function render_aqb_admin_dashboard() {
    if (!current_user_can('administrator') && !current_user_can('custom_aqb')) {
        wp_die('Access Denied');
    }

    $current_user_id = get_current_user_id();
    $is_super = current_user_can('administrator');
    $entries = [];

    // Get Form 15 entries grouped by center
    $all_form15 = []; // [center_id => [entries]]

    if ($is_super) {
        $centers = get_posts(['post_type'=>'exam_center','posts_per_page'=>-1]);
    } else {
        $assigned_id = get_user_meta($current_user_id, '_exam_center_aqb_admin', true);
        $centers = $assigned_id ? [ get_post($assigned_id) ] : [];
    }

    foreach ($centers as $center) {
        if (!$center) continue;
        $form15 = GFAPI::get_entries(15, [
            'field_filters'=>[['key'=>'_linked_exam_center','value'=>$center->ID]]
        ]);
        $all_form15[$center->ID] = is_wp_error($form15) ? [] : $form15;
    }

    // Now collect corresponding Form 25 entries
    foreach ($all_form15 as $center_id => $form15_entries) {
        $center_name = get_the_title($center_id);
        foreach ($form15_entries as $e15) {
            $order_no = rgar($e15, '789');
            if (!$order_no) continue;

            $marks = GFAPI::get_entries(25, ['field_filters'=>[['key'=>'20','value'=>$order_no]]]);
            if (is_wp_error($marks) || empty($marks)) continue;

            foreach ($marks as $e) {
                $e['_entry_id'] = rgar($e, 'id');
                $order_no = rgar($e, '20');
                $e['_center_name'] = $center_name;
                $e['_order_no'] = $order_no;

                // Fetch matching Form 15 entry using exam_order_no
                $form15_match = GFAPI::get_entries(15, [
                    'field_filters' => [
                        ['key' => '789', 'value' => $order_no]
                    ]
                ]);

                if (!is_wp_error($form15_match) && !empty($form15_match)) {
                    $form15_entry = $form15_match[0];
                    $e['_exam_id'] = rgar($form15_entry, 'id');
                    $e['_candidate_name'] = rgar($form15_entry, '1');
                    $e['_candidate_id'] = $form15_entry['created_by'];
                } else {
                    $e['_candidate_name'] = 'N/A';
                    $e['_candidate_id'] = 0;
                }

                $entries[] = $e;
            }
        }
    }

    // Collect unique values for filters
    $unique_centers = array_unique(array_column($entries, '_center_name'));
    $unique_methods = array_unique(array_map('rgar', $entries, array_fill(0, count($entries), '28')));
    $unique_levels = array_unique(array_map('rgar', $entries, array_fill(0, count($entries), '1')));
    $unique_marks_statuses = array_unique(array_map(function($e) { return rgar($e, 'mark_status') ?: 'Added'; }, $entries));
    $unique_aqb_statuses = array_unique(array_map(function($e) { 
        $meta = gform_get_meta($e['_entry_id'], '_notification_meta_' . sanitize_title(rgar($e, '28')));
        return !empty($meta['url']) ? 'Verified' : 'Not Verified'; 
    }, $entries));

    echo '<div class="wrap px-6 py-4">';
    echo '<h1 class="text-3xl font-bold mb-6 text-gray-800">AQB Admin Dashboard — Marks Review</h1>';

    if (empty($entries)) {
        echo '<p class="text-red-600">No marks entries available.</p></div>';
        return;
    }

    // Filters and Search bar
    echo '<div class="flex flex-wrap items-center justify-start gap-4 p-2 bg-white rounded-lg shadow-md mb-6">';
    echo '<div class="w-full md:w-auto flex-1 min-w-[200px]">';
    echo '<input type="text" id="aqbSearch" placeholder="Search candidate, method, center..." 
            class="w-full px-2 py-1 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm placeholder-gray-400">';
    echo '</div>';
    echo '<div class="w-full md:w-auto flex-1 min-w-[150px]">';
    echo '<select id="centerFilter" class="w-full px-2 py-1 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">';
    echo '<option value="">All Centers</option>';
    foreach ($unique_centers as $center) {
        echo "<option value='" . esc_attr($center) . "'>{$center}</option>";
    }
    echo '</select>';
    echo '</div>';
    echo '<div class="w-full md:w-auto flex-1 min-w-[150px]">';
    echo '<select id="methodFilter" class="w-full px-2 py-1 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">';
    echo '<option value="">All Methods</option>';
    foreach ($unique_methods as $method) {
        if ($method) echo "<option value='" . esc_attr($method) . "'>{$method}</option>";
    }
    echo '</select>';
    echo '</div>';
    echo '<div class="w-full md:w-auto flex-1 min-w-[150px]">';
    echo '<select id="levelFilter" class="w-full px-2 py-1 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">';
    echo '<option value="">All Levels</option>';
    foreach ($unique_levels as $level) {
        if ($level) echo "<option value='" . esc_attr($level) . "'>{$level}</option>";
    }
    echo '</select>';
    echo '</div>';
    echo '<div class="w-full md:w-auto flex-1 min-w-[150px]">';
    echo '<select id="marksStatusFilter" class="w-full px-2 py-1 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">';
    echo '<option value="">All Marks Statuses</option>';
    foreach ($unique_marks_statuses as $status) {
        echo "<option value='" . esc_attr($status) . "'>{$status}</option>";
    }
    echo '</select>';
    echo '</div>';
    echo '<div class="w-full md:w-auto flex-1 min-w-[150px]">';
    echo '<select id="aqbStatusFilter" class="w-full px-2 py-1 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">';
    echo '<option value="">All AQB Statuses</option>';
    foreach ($unique_aqb_statuses as $status) {
        echo "<option value='" . esc_attr($status) . "'>{$status}</option>";
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';

    // Table header
    echo '<div class="overflow-x-hidden shadow-lg rounded-lg">';
    echo '<table id="aqbTable" class="min-w-[100%] divide-y divide-gray-200 border border-gray-200 text-sm">';
    echo '<thead class="bg-gradient-to-r from-blue-600 to-blue-800 text-white">';
    echo '<tr>';
    echo '<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider" data-sort="order_no">Order # <span class="sort-arrow">▲</span></th>';
    echo '<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider" data-sort="candidate_name">Candidate <span class="sort-arrow">▲</span></th>';
    echo '<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider" data-sort="center_name">Center <span class="sort-arrow">▲</span></th>';
    echo '<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider" data-sort="method">Method <span class="sort-arrow">▲</span></th>';
    echo '<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider" data-sort="level">Level <span class="sort-arrow">▲</span></th>';
    echo '<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider" data-sort="marks_status">Marks Status <span class="sort-arrow">▲</span></th>';
    echo '<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider" data-sort="aqb_status">AQB Status <span class="sort-arrow">▲</span></th>';
    echo '<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider" data-sort="certificate">Certificate <span class="sort-arrow">▲</span></th>';
    echo '<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider">Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody class="bg-white divide-y divide-gray-100" id="tableBody">';

    foreach ($entries as $e) {
        $exam_id = absint($e['_exam_id']);
        $eid = absint($e['_entry_id']); 
        $cid = absint($e['_candidate_id']);
        $name = esc_html($e['_candidate_name']);          // candidate name from Form25
        $method = esc_html(rgar($e,'28'));        // method field in Form25
        $level = esc_html(rgar($e,'1'));         // level field in Form25
        $order_no = esc_html($e['_order_no']);
        $center = esc_html($e['_center_name']);
        $marks_status = esc_html(rgar($e,'mark_status')) ?: 'Added';
        $meta = gform_get_meta($eid, '_notification_meta_' . sanitize_title($method));
        $generated = !empty($meta['url']);
        $aqb_status = $generated ? '✅ Verified' : '❌ Not Verified';
        $certificate_status = $generated ? '🟢 Sent' : '❌ Not Generated';

        echo "<tr class='border-t hover:bg-blue-50 transition-colors duration-200 cursor-pointer'>";
        echo "<td class='px-3 py-2 text-gray-700 text-sm break-words'>{$order_no}</td>";
        echo "<td class='px-3 py-2 text-gray-600 text-sm break-words'>{$name}</td>";
        echo "<td class='px-3 py-2 text-gray-700 text-sm break-words'>{$center}</td>";
        echo "<td class='px-3 py-2 text-gray-800 text-sm break-words'>{$method}</td>";
        echo "<td class='px-3 py-2 text-gray-700 text-sm break-words'>{$level}</td>";
        echo "<td class='px-3 py-2 text-gray-700 text-sm break-words'>{$marks_status}</td>";
        echo "<td class='px-3 py-2 text-gray-700 text-sm break-words'>{$aqb_status}</td>";
        echo "<td class='px-3 py-2 text-gray-700 text-sm break-words'>{$certificate_status}</td>";
        echo "<td class='px-3 py-2 text-center'>";
        if (!$generated) {
            echo "<button class='review-btn text-blue-600 hover:text-blue-800 transition duration-200 text-sm' data-exam-id='{$exam_id}' data-entry-id='{$eid}' data-method='" . esc_attr($method) . "'>🔍 Review</button>";
        } else {
            echo "<a class='text-green-700 hover:text-green-900 transition duration-200 text-sm' href='" . esc_url($meta['url']) . "' target='_blank'>📥 Download</a>";
        }
        echo "</td>";
        echo "</tr>";
    }

    echo '</tbody></table>';
    echo '<div id="pagination" class="flex justify-center items-center gap-2 mt-4"></div>';
    echo '<div id="totalCount" class="text-center mt-2 text-gray-600">Showing 1 to 10 of ' . count($entries) . ' entries</div>';
    echo '</div></div>';

    // Modal section
    ?>
    <div id="aqbModal" class="hidden fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white rounded shadow-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto p-6">   
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Review & Verify Marks</h2>
            <div id="modalContent" class="text-sm text-gray-700">Loading...</div>
            <div class="text-right mt-4">
                <button id="aqbCancel" class="bg-gray-300 hover:bg-gray-400 text-black py-2 px-4 rounded-lg text-sm">Cancel</button>
                <button id="aqbSubmitAndGenerate" class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg text-sm">Save & Generate</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const headers = document.querySelectorAll('#aqbTable thead th[data-sort]');
            const searchInput = document.getElementById('aqbSearch');
            const centerFilter = document.getElementById('centerFilter');
            const methodFilter = document.getElementById('methodFilter');
            const levelFilter = document.getElementById('levelFilter');
            const marksStatusFilter = document.getElementById('marksStatusFilter');
            const aqbStatusFilter = document.getElementById('aqbStatusFilter');
            const tableBody = document.querySelector('#tableBody');
            let currentSortColumn = null;
            let currentSortDirection = 'asc';

            function getCellValue(row, column) {
                const columnMap = {
                    'order_no': 0,
                    'candidate_name': 1,
                    'center_name': 2,
                    'method': 3,
                    'level': 4,
                    'marks_status': 5,
                    'aqb_status': 6,
                    'certificate': 7
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

            // Set default arrows on page load
            headers.forEach(header => {
                const sortSpan = header.querySelector('.sort-arrow');
                if (sortSpan) sortSpan.textContent = '▲';
            });

            headers.forEach(header => {
                header.addEventListener('click', () => {
                    const column = header.dataset.sort;
                    if (!column) return;

                    const rows = Array.from(document.querySelectorAll('#aqbTable tbody tr'));
                    const direction = currentSortColumn === column && currentSortDirection === 'asc' ? 'desc' : 'asc';

                    rows.sort((a, b) => {
                        const aValue = getCellValue(a, column);
                        const bValue = getCellValue(b, column);
                        return direction === 'asc' 
                            ? aValue.localeCompare(bValue) 
                            : bValue.localeCompare(aValue);
                    });

                    rows.forEach(row => tableBody.appendChild(row));

                    currentSortColumn = column;
                    currentSortDirection = direction;
                    updateHeaderIndicators(column);
                    applyFilters();
                });
            });

            // Filter functionality
            function applyFilters() {
                const searchTerm = searchInput.value.toLowerCase();
                const selectedCenter = centerFilter.value;
                const selectedMethod = methodFilter.value;
                const selectedLevel = levelFilter.value;
                const selectedMarksStatus = marksStatusFilter.value;
                const selectedAqbStatus = aqbStatusFilter.value;
                const rows = tableBody.getElementsByTagName('tr');

                Array.from(rows).forEach(row => {
                    const cells = row.getElementsByTagName('td');
                    const center = cells[2].innerText; // Center column
                    const method = cells[3].innerText; // Method column
                    const level = cells[4].innerText; // Level column
                    const marksStatus = cells[5].innerText; // Marks Status column
                    const aqbStatus = cells[6].innerText; // AQB Status column
                    let searchMatch = true;
                    let centerMatch = true;
                    let methodMatch = true;
                    let levelMatch = true;
                    let marksStatusMatch = true;
                    let aqbStatusMatch = true;

                    // Search filter
                    if (searchTerm) {
                        searchMatch = false;
                        for (let i = 0; i < cells.length - 1; i++) { // Skip Actions column
                            if (cells[i].innerText.toLowerCase().includes(searchTerm)) {
                                searchMatch = true;
                                break;
                            }
                        }
                    }

                    // Center filter
                    if (selectedCenter && selectedCenter !== '') {
                        centerMatch = center === selectedCenter;
                    }

                    // Method filter
                    if (selectedMethod && selectedMethod !== '') {
                        methodMatch = method === selectedMethod;
                    }

                    // Level filter
                    if (selectedLevel && selectedLevel !== '') {
                        levelMatch = level === selectedLevel;
                    }

                    // Marks Status filter
                    if (selectedMarksStatus && selectedMarksStatus !== '') {
                        marksStatusMatch = marksStatus === selectedMarksStatus;
                    }

                    // AQB Status filter
                    if (selectedAqbStatus && selectedAqbStatus !== '') {
                        aqbStatusMatch = aqbStatus === selectedAqbStatus;
                    }

                    if (searchMatch && centerMatch && methodMatch && levelMatch && marksStatusMatch && aqbStatusMatch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }

            // Event listeners for filters
            searchInput.addEventListener('input', applyFilters);
            centerFilter.addEventListener('change', applyFilters);
            methodFilter.addEventListener('change', applyFilters);
            levelFilter.addEventListener('change', applyFilters);
            marksStatusFilter.addEventListener('change', applyFilters);
            aqbStatusFilter.addEventListener('change', applyFilters);

            // Initial filter application
            applyFilters();
        });
    </script>
    <?php
}

// Helper function to fetch entries from Form 25 by center (via linked Form15)
function aqb_get_entries_for_center($center_id) {
    // Not used anymore
    return [];
}

// Enqueue Tailwind CSS and JS
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_aqb-dashboard') return;
    wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
    wp_enqueue_script('aqb-script', get_stylesheet_directory_uri() . '/js/aqb.js', ['jquery'], null, true);
    wp_localize_script('aqb-script', 'aqbData', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'ajax_nonce' => wp_create_nonce('aqb_ajax_nonce'),
    ]);
});

// AJAX handlers remain same: aqb_get_marks & aqb_verify_generate

add_action('wp_ajax_aqb_get_marks', function() {
    check_ajax_referer('aqb_ajax_nonce');
	$eid = absint($_POST['entry_id']);
	$exam_id = absint($_POST['exam_id']);
    $entry = GFAPI::get_entry($eid);
    if (is_wp_error($entry)) wp_send_json_error('Entry not found');
	

    $form = GFAPI::get_form($entry['form_id']);

    ob_start();
       echo '<form id="aqbMarkEditForm">';
	   echo '<input type="hidden" name="exam_id" value="' . esc_attr($exam_id) . '">';
    echo '<input type="hidden" name="entry_id" value="' . esc_attr($eid) . '">';
    echo '<table class="min-w-full text-sm border"><thead><tr><th>Field</th><th>Value</th></tr></thead><tbody>';

    foreach ($entry as $field_id => $value) {
        if (!is_numeric($field_id) || trim($value) === '') continue; // Only numeric IDs & skip empty

        $field = GFFormsModel::get_field($form, $field_id);
        if (!$field || stripos($field->label, 'Marks Obtained') === false) continue;

        echo '<tr>';
        echo '<td class="border px-4 py-2 font-medium">' . esc_html($field->label) . '</td>';
        echo '<td class="border px-4 py-2">';
        echo '<input type="number" step="0.01" name="marks[' . esc_attr($field_id) . ']" value="' . esc_attr($value) . '" class="border p-1 w-full rounded">';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</form>';
    wp_send_json_success(['html' => ob_get_clean()]);
});


add_action('wp_ajax_aqb_save_and_generate', function() {
    check_ajax_referer('aqb_ajax_nonce');
	
    $entry_id = absint($_POST['entry_id']);
	$exam_id = absint($_POST['exam_id']);
    $marks = $_POST['marks'] ?? [];

    $entry = GFAPI::get_entry($entry_id);
    if (is_wp_error($entry)) wp_send_json_error('Invalid entry');

    // Update the marks
    foreach ($marks as $field_id => $value) {
        $entry[$field_id] = sanitize_text_field($value);
    }
	
	

    $update = GFAPI::update_entry($entry);
    if (is_wp_error($update)) {
        wp_send_json_error('Failed to save marks');
    }

    // Generate certificate (you may need method info here too)
    $method = $entry['28'];
	
    ob_start();
	echo $exam_id;
	include_once get_stylesheet_directory() . '/includes/pdf-cert-generator.php';
    generate_exam_certificate_pdf($exam_id, $entry_id, $method);
    ob_end_clean();

    $meta = gform_get_meta($entry_id, '_notification_meta_' . sanitize_title($method));
    if (!empty($meta['url'])) {
        wp_send_json_success(['url' => $meta['url']]);
    }

    wp_send_json_error('Certificate generation failed');
});

