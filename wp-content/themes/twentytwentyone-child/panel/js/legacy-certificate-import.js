jQuery(document).ready(function ($) {
    'use strict';

    let csvData = null;
    let pdfList = [];

    // Step 1: CSV Upload
    $('#legacy-csv-upload-form').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData();
        const fileInput = $('#legacy_csv_file')[0];

        if (!fileInput.files.length) {
            showStatus('#legacy-csv-upload-status', 'error', 'Please select a CSV or Excel file to upload.');
            return;
        }

        formData.append('action', 'legacy_cert_import_upload_csv');
        formData.append('nonce', legacyCertImport.nonce);
        formData.append('csv_file', fileInput.files[0]);

        showStatus('#legacy-csv-upload-status', 'info', 'Uploading file...');

        $.ajax({
            url: legacyCertImport.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    showStatus('#legacy-csv-upload-status', 'success', response.data.message);
                    csvData = response.data;
                    displayCSVPreview(response.data.preview);
                    $('#legacy-csv-preview').show();
                } else {
                    showStatus('#legacy-csv-upload-status', 'error', response.data || 'Upload failed');
                }
            },
            error: function (xhr, status, error) {
                showStatus('#legacy-csv-upload-status', 'error', 'Upload failed: ' + error);
            }
        });
    });

    // Display CSV Preview
    function displayCSVPreview(preview) {
        if (!preview || !preview.headers || !preview.rows) {
            return;
        }

        let html = '<table class="widefat"><thead><tr>';

        // Headers
        preview.headers.forEach(function (header) {
            html += '<th>' + escapeHtml(header) + '</th>';
        });
        html += '</tr></thead><tbody>';

        // Rows
        preview.rows.forEach(function (row) {
            html += '<tr>';
            row.forEach(function (cell) {
                html += '<td>' + escapeHtml(cell || '') + '</td>';
            });
            html += '</tr>';
        });

        html += '</tbody></table>';
        html += '<p><strong>Total rows:</strong> ' + preview.total_rows + '</p>';

        $('#legacy-preview-table').html(html);
    }

    // Proceed to Step 2
    $('#legacy-proceed-to-step2').on('click', function () {
        $('#step-1').removeClass('active').hide();
        $('#step-2').addClass('active').show();
    });

    // Step 2: Upload ZIP file with certificates
    $('#legacy-pdf-upload-form').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData();
        const fileInput = $('#legacy_certificates_zip')[0];

        if (!fileInput.files.length) {
            showStatus('#legacy-pdf-upload-status', 'error', 'Please select a ZIP file to upload.');
            return;
        }

        const file = fileInput.files[0];
        const maxSize = 100 * 1024 * 1024; // 100MB

        if (file.size > maxSize) {
            showStatus('#legacy-pdf-upload-status', 'error', 'File size exceeds 100MB limit.');
            return;
        }

        formData.append('action', 'legacy_cert_import_upload_zip');
        formData.append('nonce', legacyCertImport.nonce);
        formData.append('certificates_zip', file);

        showStatus('#legacy-pdf-upload-status', 'info', 'Uploading and extracting ZIP file...');

        $.ajax({
            url: legacyCertImport.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    showStatus('#legacy-pdf-upload-status', 'success', response.data.message);
                    pdfList = response.data.pdfs;
                    displayPDFList(pdfList);
                    $('#legacy-pdf-list').show();
                    $('#legacy-proceed-to-step3').show();
                } else {
                    showStatus('#legacy-pdf-upload-status', 'error', response.data || 'Upload failed');
                }
            },
            error: function (xhr, status, error) {
                showStatus('#legacy-pdf-upload-status', 'error', 'Upload failed: ' + error);
            }
        });
    });

    // Display PDF List
    function displayPDFList(pdfs) {
        if (!pdfs || pdfs.length === 0) {
            $('#legacy-pdf-list-content').html('<p>No PDF files found.</p>');
            return;
        }

        let html = '<table class="widefat"><thead><tr>';
        html += '<th>Filename</th>';
        html += '<th>Candidate Reg</th>';
        html += '<th>Name</th>';
        html += '<th>Issue #</th>';
        html += '<th>Size</th>';
        html += '</tr></thead><tbody>';

        pdfs.forEach(function (pdf) {
            const metadata = pdf.metadata || {};
            const sizeKB = (pdf.size / 1024).toFixed(2);

            html += '<tr>';
            html += '<td>' + escapeHtml(pdf.filename) + '</td>';
            html += '<td>' + escapeHtml(metadata.candidate_reg || 'N/A') + '</td>';
            html += '<td>' + escapeHtml(metadata.name || 'N/A') + '</td>';
            html += '<td>' + escapeHtml(metadata.issue_number || 'N/A') + '</td>';
            html += '<td>' + sizeKB + ' KB</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $('#legacy-pdf-list-content').html(html);
    }

    // Skip Step 2
    $('#legacy-skip-step2').on('click', function () {
        $('#step-2').removeClass('active').hide();
        $('#step-3').addClass('active').show();
    });

    // Proceed to Step 3
    $('#legacy-proceed-to-step3').on('click', function () {
        $('#step-2').removeClass('active').hide();
        $('#step-3').addClass('active').show();
    });

    // Step 3: Start Import
    $('#legacy-start-import').on('click', function () {
        const $button = $(this);
        $button.prop('disabled', true).text('Processing...');

        $('#legacy-import-status').html('<p>Starting import...</p>');
        $('#legacy-progress-fill').css('width', '0%').text('0%');

        $.ajax({
            url: legacyCertImport.ajax_url,
            type: 'POST',
            data: {
                action: 'legacy_cert_import_process',
                nonce: legacyCertImport.nonce
            },
            success: function (response) {
                if (response.success) {
                    $('#legacy-progress-fill').css('width', '100%').text('100%');
                    displayImportResults(response.data);
                    $('#legacy-import-results').show();
                } else {
                    showStatus('#legacy-import-status', 'error', response.data || 'Import failed');
                }
                $button.prop('disabled', false).text('Start Import');
            },
            error: function (xhr, status, error) {
                showStatus('#legacy-import-status', 'error', 'Import failed: ' + error);
                $button.prop('disabled', false).text('Start Import');
            }
        });
    });

    // Display Import Results with improved UX
    function displayImportResults(results) {
        let html = '<div class="import-results-enhanced">';

        // Success Summary Header
        html += '<div class="results-header">';
        html += '<h2><span class="dashicons dashicons-yes-alt"></span> Import Completed</h2>';
        html += '</div>';

        // Statistics Cards
        html += '<div class="results-stats">';
        html += '<div class="stat-card success">';
        html += '<div class="stat-number">' + results.certificates_created + '</div>';
        html += '<div class="stat-label">Certificates Created</div>';
        html += '</div>';

        html += '<div class="stat-card info">';
        html += '<div class="stat-number">' + results.users_created + '</div>';
        html += '<div class="stat-label">New Users</div>';
        html += '</div>';

        html += '<div class="stat-card info">';
        html += '<div class="stat-number">' + results.users_updated + '</div>';
        html += '<div class="stat-label">Users Updated</div>';
        html += '</div>';

        html += '<div class="stat-card warning">';
        html += '<div class="stat-number">' + results.files_linked + '</div>';
        html += '<div class="stat-label">PDFs Linked</div>';
        html += '</div>';

        if (results.certificates_skipped > 0) {
            html += '<div class="stat-card error">';
            html += '<div class="stat-number">' + results.certificates_skipped + '</div>';
            html += '<div class="stat-label">Skipped</div>';
            html += '</div>';
        }
        html += '</div>';

        // Categorize warnings
        const categorizedWarnings = categorizeWarnings(results.warnings || []);

        // Summary message with counts
        html += '<div class="results-summary-message">';
        html += '<p><strong>Import Summary:</strong></p>';
        html += '<ul>';
        html += '<li>✅ Successfully imported <strong>' + results.certificates_created + '</strong> certificate(s)</li>';
        html += '<li>👥 Created <strong>' + results.users_created + '</strong> new user(s) and updated <strong>' + results.users_updated + '</strong> existing user(s)</li>';
        html += '<li>📄 Linked <strong>' + results.files_linked + '</strong> PDF file(s)</li>';
        if (results.certificates_skipped > 0) {
            html += '<li>⚠️ Skipped <strong>' + results.certificates_skipped + '</strong> duplicate certificate(s) (already exist in system)</li>';
        }
        html += '</ul>';
        html += '</div>';

        // Show ERRORS in detail (user needs to fix these)
        if (results.errors && results.errors.length > 0) {
            html += '<div class="results-section error-section">';
            html += '<h3><span class="dashicons dashicons-dismiss"></span> Errors - Action Required (' + results.errors.length + ')</h3>';
            html += '<p class="section-description">⚠️ These rows encountered errors and need your attention:</p>';
            html += '<ul class="error-list">';
            results.errors.forEach(function (error) {
                html += '<li>' + escapeHtml(error) + '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }

        // Show MISSING DATA in detail (user might want to fix these)
        if (categorizedWarnings.emptyData.length > 0) {
            html += '<div class="results-section info-section">';
            html += '<h3><span class="dashicons dashicons-warning"></span> Rows with Missing Certificate Data (' + categorizedWarnings.emptyData.length + ')</h3>';
            html += '<p class="section-description">ℹ️ These rows had users created/updated but certificate data was incomplete:</p>';
            html += '<ul class="notice-list">';
            categorizedWarnings.emptyData.forEach(function (item) {
                html += '<li>Row ' + item.row + ' - User processed successfully, but certificate data was empty or incomplete</li>';
            });
            html += '</ul>';
            html += '</div>';
        }

        // Show OTHER NOTICES in detail if any
        if (categorizedWarnings.other.length > 0) {
            html += '<div class="results-section other-section">';
            html += '<h3><span class="dashicons dashicons-flag"></span> Other Notices (' + categorizedWarnings.other.length + ')</h3>';
            html += '<ul class="notice-list">';
            categorizedWarnings.other.forEach(function (warning) {
                html += '<li>' + escapeHtml(warning) + '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }

        // Action buttons
        html += '<div class="results-actions">';
        html += '<a href="admin.php?page=certificate-management" class="button button-primary button-large">';
        html += '<span class="dashicons dashicons-awards"></span> View All Certificates';
        html += '</a>';
        html += '<button type="button" class="button button-large" onclick="location.reload()">Import More</button>';
        html += '</div>';

        html += '</div>';

        $('#legacy-import-results').html(html);
        $('#legacy-import-status').html('<p class="success-message"><span class="dashicons dashicons-yes"></span> Import process completed successfully!</p>');
    }

    // Categorize warnings into different types
    function categorizeWarnings(warnings) {
        const categorized = {
            duplicates: [],
            emptyData: [],
            other: []
        };

        warnings.forEach(function (warning) {
            // Check for duplicate certificate warnings
            if (warning.includes('already exists')) {
                const certMatch = warning.match(/Certificate number ([A-Z0-9-]+)/);
                const rowMatch = warning.match(/Row (\d+)/);
                categorized.duplicates.push({
                    certNumber: certMatch ? certMatch[1] : 'Unknown',
                    row: rowMatch ? rowMatch[1] : null,
                    message: warning
                });
            }
            // Check for empty data warnings
            else if (warning.includes('no certificates were created') || warning.includes('empty or missing required data')) {
                const rowMatch = warning.match(/Row (\d+)/);
                categorized.emptyData.push({
                    row: rowMatch ? rowMatch[1] : 'Unknown',
                    message: warning
                });
            }
            // Everything else
            else {
                categorized.other.push(warning);
            }
        });

        return categorized;
    }

    // Utility Functions
    function showStatus(selector, type, message) {
        const $status = $(selector);
        $status.removeClass('info success error');
        $status.addClass(type);
        $status.html('<p>' + escapeHtml(message) + '</p>');
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.replace(/[&<>"']/g, function (m) { return map[m]; }) : '';
    }
});
