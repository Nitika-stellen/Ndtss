<?php
// add_action('admin_menu', function () {
// 	add_menu_page('Certified Users', 'Certified Users', 'manage_options', 'certified-users', 'display_certified_users');
// });

add_action('admin_enqueue_scripts', function ($hook) {
	if ($hook !== 'toplevel_page_certified-users') return;
	
	//wp_enqueue_style('tailwindcss', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
	//wp_enqueue_script('certified-users-js', plugin_dir_url(__FILE__) . 'certified-users.js', ['jquery'], null, true);
});



function display_certified_users() {
	$entries = get_certified_entries_with_meta_prefix();
	$users = [];

	foreach ($entries as $entry_id => $meta_keys) {
		$entry = GFAPI::get_entry($entry_id);
		if (is_wp_error($entry)) continue;

		foreach ($meta_keys as $meta_key) {
			$meta = gform_get_meta($entry_id, $meta_key);

			if (!empty($meta['path']) && file_exists($meta['path'])) {
				$user_id = $entry['created_by'] ?? null;
				$user = $user_id ? get_userdata($user_id) : null;

				$users[] = [
					'name'  => $user ? $user->display_name : rgar($entry, '1'),
					'email' => $user ? $user->user_email : rgar($entry, '2'),
					'url'   => esc_url($meta['url']),
					'date'  => !empty($meta['generated_at']) ? date('d M Y', strtotime($meta['generated_at'])) : 'Unknown',
					'method' => ucfirst(str_replace('_certification_meta_', '', $meta_key)),
					'certificate_number' => gform_get_meta($entry_id, '_final_certificate_number_' . sanitize_title(str_replace('_certification_meta_', '', $meta_key))),
					'exam_number' => rgar($entry, '20'),
				];
			}
		}
	}
	?>

	<div class="wrap">
		<h1 class="text-3xl font-bold mb-6 text-gray-800">Certified Users</h1>

		<div class="search_form">
			<div class="form_controls">
				<input type="text" id="searchInput" class="border p-3 rounded-lg w-full focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder-gray-400" placeholder="Search users...">
			</div>
			<div class="form_controls">
				<select id="methodFilter" class="border p-3 rounded-lg w-full focus:outline-none focus:ring-2 focus:ring-blue-500">
					<option value="">All Methods</option>
					<?php
					$methods = array_unique(array_column($users, 'method'));
					foreach ($methods as $method) {
						echo "<option value=\"$method\">$method</option>";
					}
					?>
				</select>
			</div>
			<div class="form_controls">
				<button id="exportCsv" class="button-primary">Export CSV</button>
			</div>
		</div>

		<div class="overflow-x-hidden">
			<table class="table_custom wp-list-table widefat striped dataTable no-footer" id="certifiedTable">
				<thead class="">
					<tr>
						<th class="">Sr No</th>
						<th class="" data-sort="exam_number">Order-Exam No <span class="sort-arrow">▲</span></th>
						<th class="" data-sort="name">Name <span class="sort-arrow">▲</span></th>
						<th class="" data-sort="email">Email <span class="sort-arrow">▲</span></th>
						<th class="px-6 py-4 text-center text-sm font-semibold uppercase tracking-wider" data-sort="certificate_number">Certificate No <span class="sort-arrow">▲</span></th>
						<th class="px-6 py-4 text-center text-sm font-semibold uppercase tracking-wider">PDF</th>
						<th class="" data-sort="date">Date <span class="sort-arrow">▲</span></th>
						<th class="" data-sort="method">Method <span class="sort-arrow">▲</span></th>
					</tr>
				</thead>
				<tbody class="bg-white divide-y divide-gray-100" id="tableBody">
					<?php foreach ($users as $index => $user): ?>
						<tr class="cert-row hover:bg-blue-50 transition-colors duration-200 cursor-pointer">
							<td class="px-6 py-4 whitespace-nowrap text-gray-700 text-base font-medium text-center"><?php echo esc_html($index + 1); ?></td>
							<td class="px-6 py-4 whitespace-nowrap text-gray-700 text-base"><?php echo esc_html($user['exam_number']); ?></td>
							<td class="px-6 py-4 whitespace-nowrap text-gray-800 text-base font-medium"><?php echo esc_html($user['name']); ?></td>
							<td class="px-6 py-4 whitespace-nowrap text-gray-600 text-base"><?php echo esc_html($user['email']); ?></td>
							<td class="px-6 py-4 whitespace-nowrap text-gray-700 text-base font-medium text-center"><?php echo esc_html($user['certificate_number']); ?></td>
							<td class="px-6 py-4 whitespace-nowrap text-center">
								<a href="<?php echo esc_url($user['url']); ?>" target="_blank" class="doc_link">
									<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline-block" fill="currentColor" viewBox="0 0 24 24">
										<path d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6zm1 2h7v5h5v10H7V4zm7-1.5L16.5 7H13V2.5zM8 11h8v2H8v-2zm0 4h8v2H8v-2z"/>
									</svg>
								</a>
							</td>
							<td class="px-6 py-4 whitespace-nowrap text-gray-700 text-base"><?php echo esc_html($user['date']); ?></td>
							<td class="px-6 py-4 whitespace-nowrap text-gray-700 text-base"><?php echo esc_html($user['method']); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<div id="pagination" class="flex justify-center items-center gap-2 mt-4"></div>
		</div>
	</div>

	<style>
		
		.bg-blue-600 {
			background-color: #2563eb;
		}
		.hover\:bg-blue-700:hover {
			background-color: #1d4ed8;
		}
		.text-blue-600 {
			color: #2563eb;
		}
		.hover\:text-blue-800:hover {
			color: #1e40af;
		}
		.sort-arrow {
			font-size: 1.5em;
			margin-left: 5px;
			vertical-align: middle;
			font-weight: bold;
			color: #ffffff;
		}
		.controls-container {
			background-color: #ffffff;
			padding: 1rem;
			border-radius: 0.5rem;
			box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
		}
		@media (min-width: 768px) {
			.controls-container .flex-1 {
				flex: 1;
				max-width: 33.33%;
			}
			.controls-container button {
				flex-shrink: 0;
			}
		}
		#pagination button {
			padding: 5px 12px;
			border: 1px solid #d1d5db;
			border-radius: 0.375rem;
			background-color: #ffffff;
			cursor: pointer;
		}
		#pagination button.active {
			background-color: #2563eb;
			color: #ffffff;
			border-color: #2563eb;
		}
		#pagination button:hover {
			background-color: #e6f0fa;
		}
	</style>

	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const headers = document.querySelectorAll('#certifiedTable thead th[data-sort]');
			const searchInput = document.getElementById('searchInput');
			const methodFilter = document.getElementById('methodFilter');
			const tableBody = document.querySelector('#tableBody');
			const paginationDiv = document.getElementById('pagination');
			let currentSortColumn = null;
			let currentSortDirection = 'asc';
			let currentPage = 1;
			const rowsPerPage = 10;

			function parseDate(dateStr) {
				if (dateStr === 'Unknown') return 0;
				return new Date(dateStr).getTime();
			}

			function getCellValue(row, column) {
				const columnMap = {
					'exam_number': 1,
					'name': 2,
					'email': 3,
					'certificate_number': 4,
					'date': 6,
					'method': 7
				};
				const cellIndex = columnMap[column];
				if (cellIndex === undefined) return '';
				const cell = row.cells[cellIndex];
				let value = cell.innerText.trim();
				if (column === 'date') {
					return parseDate(value);
				} else if (column === 'exam_number' || column === 'certificate_number') {
					return parseFloat(value) || value;
				}
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
					console.log('Clicked column:', header.dataset.sort);
					const column = header.dataset.sort;
					if (!column) return;

					const rows = Array.from(document.querySelectorAll('#certifiedTable tbody tr'));
					const direction = currentSortColumn === column && currentSortDirection === 'asc' ? 'desc' : 'asc';

					rows.sort((a, b) => {
						const aValue = getCellValue(a, column);
						const bValue = getCellValue(b, column);

						if (column === 'exam_number' || column === 'certificate_number' || column === 'date') {
							if (aValue === bValue) return 0;
							if (!aValue) return 1;
							if (!bValue) return -1;
							return direction === 'asc' ? aValue - bValue : bValue - aValue;
						} else {
							return direction === 'asc' 
								? aValue.localeCompare(bValue) 
								: bValue.localeCompare(aValue);
						}
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

			// Search functionality
			searchInput.addEventListener('input', function () {
				applyFilters();
			});

			// Method filter functionality
			methodFilter.addEventListener('change', function () {
				applyFilters();
			});

			function applyFilters() {
				const searchTerm = searchInput.value.toLowerCase();
				const selectedMethod = methodFilter.value;
				const rows = tableBody.getElementsByTagName('tr');
				let visibleRows = [];

				Array.from(rows).forEach(row => {
					const cells = row.getElementsByTagName('td');
					const methodCell = cells[7].innerText;
					let searchMatch = true;
					let methodMatch = true;

					// Search filter
					if (searchTerm) {
						searchMatch = false;
						for (let i = 1; i < cells.length; i++) {
							if (i !== 10) { // Skip PDF column
								if (cells[i].innerText.toLowerCase().includes(searchTerm)) {
									searchMatch = true;
									break;
								}
							}
						}
					}

					// Method filter
					if (selectedMethod && selectedMethod !== '') {
						methodMatch = methodCell === selectedMethod;
					}

					if (searchMatch && methodMatch) {
						row.style.display = '';
						visibleRows.push(row);
					} else {
						row.style.display = 'none';
					}
				});

				updatePagination(visibleRows);
			}

			function updatePagination(visibleRows) {
				const totalPages = Math.ceil(visibleRows.length / rowsPerPage);
				paginationDiv.innerHTML = '';

				// Previous button
				const prevButton = document.createElement('button');
				prevButton.textContent = 'Previous';
				prevButton.addEventListener('click', () => {
					if (currentPage > 1) {
						currentPage--;
						updatePagination(visibleRows); // Recreate pagination
					}
				});
				paginationDiv.appendChild(prevButton);

				// Page numbers
				for (let i = 1; i <= totalPages; i++) {
					const pageButton = document.createElement('button');
					pageButton.textContent = i;
					pageButton.addEventListener('click', () => {
						currentPage = i;
						updatePagination(visibleRows); // Recreate pagination
					});
					paginationDiv.appendChild(pageButton);
				}

				// Next button
				const nextButton = document.createElement('button');
				nextButton.textContent = 'Next';
				nextButton.addEventListener('click', () => {
					if (currentPage < totalPages) {
						currentPage++;
						updatePagination(visibleRows); // Recreate pagination
					}
				});
				paginationDiv.appendChild(nextButton);

				// Apply active class to current page
				const pageButtons = paginationDiv.querySelectorAll('button:nth-child(n+2):nth-child(-n+' + (totalPages + 1) + ')');
				pageButtons.forEach(button => button.classList.remove('active'));
				if (currentPage <= pageButtons.length) {
					pageButtons[currentPage - 1].classList.add('active');
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
				const csvRows = [['Name', 'Email', 'Certificate Number', 'Exam Number', 'Certificate URL', 'Date', 'Method']];
				document.querySelectorAll('#tableBody tr').forEach(row => {
					if (row.style.display === 'none') return;
					const cols = row.querySelectorAll('td');
					csvRows.push([
						cols[2].innerText, // Name
						cols[3].innerText, // Email
						cols[4].innerText, // Certificate Number
						cols[1].innerText, // Exam Number
						cols[5].querySelector('a')?.href || '', // Certificate URL
						cols[6].innerText, // Date
						cols[7].innerText // Method
					]);
				});

				const csvContent = csvRows.map(e => e.join(",")).join("\n");
				const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
				const url = URL.createObjectURL(blob);
				const a = document.createElement('a');
				a.href = url;
				a.download = 'certified_users.csv';
				a.click();
			});

			// Initial filter and pagination
			applyFilters();
		});
	</script>
	<?php
}

function get_certified_entries_with_meta_prefix($prefix = '_certification_meta_') {
	global $wpdb;
	$meta_table = $wpdb->prefix . 'gf_entry_meta';

	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT entry_id, meta_key FROM $meta_table WHERE meta_key LIKE %s",
			$wpdb->esc_like($prefix) . '%'
		),
		ARRAY_A
	);

	$entries = [];
	foreach ($results as $row) {
		$entries[$row['entry_id']][] = $row['meta_key'];
	}

	return $entries;
}
