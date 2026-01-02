jQuery(document).ready(function($) {
    'use strict';
    
    let uploadedFiles = [];
    let csvData = null;
    
    // Step 1: CSV Upload
    $('#csv-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        const fileInput = $('#csv_file')[0];
        
        if (!fileInput.files.length) {
            showStatus('#csv-upload-status', 'error', 'Please select a CSV file to upload.');
            return;
        }
        
        formData.append('action', 'certificate_import_upload_csv');
        formData.append('nonce', certImport.nonce);
        formData.append('csv_file', fileInput.files[0]);
        
        showStatus('#csv-upload-status', 'info', 'Uploading CSV file...');
        
        $.ajax({
            url: certImport.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showStatus('#csv-upload-status', 'success', response.data.message);
                    csvData = response.data;
                    displayCSVPreview(response.data.preview);
                    $('#csv-preview').show();
                } else {
                    showStatus('#csv-upload-status', 'error', response.data || 'Upload failed');
                }
            },
            error: function(xhr, status, error) {
                showStatus('#csv-upload-status', 'error', 'Upload failed: ' + error);
            }
        });
    });
    
    // Display CSV Preview
    function displayCSVPreview(preview) {
        if (!preview || !preview.headers || !preview.rows) {
            return;
        }
        
        let html = '<table><thead><tr>';
        
        // Headers
        preview.headers.forEach(function(header) {
            html += '<th>' + escapeHtml(header) + '</th>';
        });
        html += '</tr></thead><tbody>';
        
        // Rows
        preview.rows.forEach(function(row) {
            html += '<tr>';
            row.forEach(function(cell) {
                html += '<td>' + escapeHtml(cell || '') + '</td>';
            });
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        
        $('#preview-table').html(html);
    }
    
    // Proceed to Step 2
    $('#proceed-to-step2').on('click', function() {
        $('#step-1').removeClass('active').hide();
        $('#step-2').addClass('active').show();
    });
    
    // Step 2: Certificate Files Upload
    $('#cert-files-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        const fileInput = $('#cert_files')[0];
        
        if (!fileInput.files.length) {
            showStatus('#cert-files-upload-status', 'error', 'Please select files to upload.');
            return;
        }
        
        // Add all files
        for (let i = 0; i < fileInput.files.length; i++) {
            formData.append('cert_files[]', fileInput.files[i]);
        }
        
        formData.append('action', 'certificate_import_upload_files');
        formData.append('nonce', certImport.nonce);
        
        showStatus('#cert-files-upload-status', 'info', 'Uploading certificate files...');
        
        $.ajax({
            url: certImport.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    uploadedFiles = uploadedFiles.concat(response.data.files);
                    let message = response.data.message;
                    if (response.data.errors && response.data.errors.length > 0) {
                        message += '<br>Errors: ' + response.data.errors.join(', ');
                    }
                    showStatus('#cert-files-upload-status', 'success', message);
                    $('#proceed-to-step3').show();
                } else {
                    showStatus('#cert-files-upload-status', 'error', response.data || 'Upload failed');
                }
            },
            error: function(xhr, status, error) {
                showStatus('#cert-files-upload-status', 'error', 'Upload failed: ' + error);
            }
        });
    });
    
    // Skip Step 2
    $('#skip-step2').on('click', function() {
        $('#step-2').removeClass('active').hide();
        $('#step-3').addClass('active').show();
    });
    
    // Proceed to Step 3
    $('#proceed-to-step3').on('click', function() {
        $('#step-2').removeClass('active').hide();
        $('#step-3').addClass('active').show();
    });
    
    // Step 3: Start Import
    $('#start-import').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('Importing...');
        
        $('#import-progress').show();
        $('#import-results').hide();
        updateProgress(0);
        
        $.ajax({
            url: certImport.ajax_url,
            type: 'POST',
            data: {
                action: 'certificate_import_process',
                nonce: certImport.nonce
            },
            success: function(response) {
                button.prop('disabled', false).text('Start Import');
                
                if (response.success) {
                    updateProgress(100);
                    displayImportResults(response.data);
                    showStatus('#import-status', 'success', 'Import completed successfully!');
                } else {
                    showStatus('#import-status', 'error', response.data || 'Import failed');
                }
            },
            error: function(xhr, status, error) {
                button.prop('disabled', false).text('Start Import');
                showStatus('#import-status', 'error', 'Import failed: ' + error);
            }
        });
    });
    
    // Display Import Results
    function displayImportResults(results) {
        let html = '<h3>Import Results</h3>';
        
        // Summary Cards
        html += '<div class="result-summary">';
        html += '<div class="result-card"><div class="label">Users Created</div><div class="number">' + (results.users_created || 0) + '</div></div>';
        html += '<div class="result-card"><div class="label">Users Updated</div><div class="number">' + (results.users_updated || 0) + '</div></div>';
        html += '<div class="result-card"><div class="label">Certificates Created</div><div class="number">' + (results.certificates_created || 0) + '</div></div>';
        html += '<div class="result-card"><div class="label">Certificates Skipped</div><div class="number">' + (results.certificates_skipped || 0) + '</div></div>';
        html += '<div class="result-card"><div class="label">Files Linked</div><div class="number">' + (results.files_linked || 0) + '</div></div>';
        html += '</div>';
        
        // Errors
        if (results.errors && results.errors.length > 0) {
            html += '<div class="errors-list">';
            html += '<h4>Errors (' + results.errors.length + ')</h4>';
            html += '<ul>';
            results.errors.forEach(function(error) {
                html += '<li>' + escapeHtml(error) + '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }
        
        // Warnings
        if (results.warnings && results.warnings.length > 0) {
            html += '<div class="warnings-list">';
            html += '<h4>Warnings (' + results.warnings.length + ')</h4>';
            html += '<ul>';
            results.warnings.forEach(function(warning) {
                html += '<li>' + escapeHtml(warning) + '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }
        
        $('#import-results').html(html).show();
    }
    
    // Update Progress Bar
    function updateProgress(percentage) {
        $('#progress-fill').css('width', percentage + '%').text(percentage + '%');
    }
    
    // Show Status Message
    function showStatus(selector, type, message) {
        const $status = $(selector);
        $status.removeClass('success error info').addClass(type);
        $status.html(message);
    }
    
    // Escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});


