jQuery(document).ready(function($) {
    'use strict';
    
    var currentStep = 1;
    var importInProgress = false;
    var importBatchNumber = 0;
    var importOptions = {};
    
    // Use CSV file from theme directory
    $('.use-csv-file').on('click', function() {
        var filePath = $(this).data('file-path');
        var fileName = filePath.split(/[\\/]/).pop();
        
        $('#upload-status').html('<p>Loading file from theme directory...</p>').removeClass('error success');
        
        // Store file path in a way that the backend can access
        $.ajax({
            url: legacyImport.ajax_url,
            type: 'POST',
            data: {
                action: 'legacy_import_use_theme_file',
                nonce: legacyImport.nonce,
                file_path: filePath
            },
            success: function(response) {
                if (response.success) {
                    $('#upload-status').html('<p class="success">Using file: ' + fileName + '</p>').addClass('success');
                    displayPreview(response.data.preview);
                    $('#file-preview').show();
                } else {
                    $('#upload-status').html('<p class="error">' + response.data + '</p>').addClass('error');
                }
            },
            error: function() {
                $('#upload-status').html('<p class="error">Failed to load file. Please try uploading instead.</p>').addClass('error');
            }
        });
    });
    
    // Step 1: File Upload
    $('#legacy-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData();
        formData.append('action', 'legacy_import_upload');
        formData.append('nonce', legacyImport.nonce);
        formData.append('legacy_file', $('#legacy_file')[0].files[0]);
        
        $('#upload-status').html('<p>Uploading file...</p>').removeClass('error success');
        
        $.ajax({
            url: legacyImport.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#upload-status').html('<p class="success">' + response.data.message + '</p>').addClass('success');
                    displayPreview(response.data.preview);
                    $('#file-preview').show();
                } else {
                    $('#upload-status').html('<p class="error">' + response.data + '</p>').addClass('error');
                }
            },
            error: function() {
                $('#upload-status').html('<p class="error">Upload failed. Please try again.</p>').addClass('error');
            }
        });
    });
    
    // Display file preview
    function displayPreview(preview) {
        var html = '<div class="file-preview-content">';
        html += '<p><strong>Total Rows:</strong> ' + preview.total_rows + '</p>';
        html += '<p><strong>Tabs Found:</strong> ' + preview.tabs.length + '</p>';
        
        preview.tabs.forEach(function(tab) {
            html += '<div class="tab-preview">';
            html += '<h4>' + tab.name + ' (' + tab.total_rows + ' rows)</h4>';
            html += '<div class="tab-info">Headers: ' + tab.headers.join(', ') + '</div>';
            
            // Show header mapping if available
            if (tab.header_map) {
                html += '<div class="header-mapping-info" style="margin: 10px 0; padding: 10px; background: #f0f0f0; border-radius: 4px; font-size: 12px;">';
                html += '<strong>Column Mapping:</strong><br>';
                var mappingItems = [];
                for (var orig in tab.header_map) {
                    if (orig !== tab.header_map[orig]) {
                        mappingItems.push('<code>' + orig + '</code> → <code>' + tab.header_map[orig] + '</code>');
                    }
                }
                if (mappingItems.length > 0) {
                    html += mappingItems.join(', ');
                } else {
                    html += '<em>All headers matched exactly</em>';
                }
                html += '</div>';
            }
            
            if (tab.rows.length > 0) {
                // Use normalized headers for display
                var displayHeaders = tab.headers;
                if (tab.header_map) {
                    // Show both original and mapped headers
                    displayHeaders = tab.headers.map(function(h) {
                        return tab.header_map[h] !== h ? h + ' (' + tab.header_map[h] + ')' : h;
                    });
                }
                
                html += '<table><thead><tr>';
                displayHeaders.forEach(function(header) {
                    html += '<th>' + header + '</th>';
                });
                html += '</tr></thead><tbody>';
                
                tab.rows.slice(0, 5).forEach(function(row) {
                    html += '<tr>';
                    // Use normalized field names from row data
                    tab.headers.forEach(function(header) {
                        var mappedHeader = tab.header_map ? tab.header_map[header] : header;
                        var value = row[mappedHeader] || row[header] || '';
                        html += '<td>' + value + '</td>';
                    });
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                if (tab.total_rows > 5) {
                    html += '<p><em>Showing first 5 rows of ' + tab.total_rows + ' total rows</em></p>';
                }
            }
            
            html += '</div>';
        });
        
        html += '</div>';
        $('#preview-content').html(html);
    }
    
    // Proceed to Step 2
    $('#proceed-to-step2').on('click', function() {
        $('#step-1').removeClass('active');
        $('#step-2').addClass('active').show();
        currentStep = 2;
    });
    
    // Start Import
    $('#start-import').on('click', function() {
        if (importInProgress) {
            return;
        }
        
        // Validate membership type is selected
        var membershipType = $('#membership_type').val();
        if (!membershipType) {
            alert('Please select a Membership Type (Individual or Corporate) before starting the import.');
            return;
        }
        
        // Collect import options
        importOptions = {
            dry_run: $('#dry_run').is(':checked'),
            batch_size: parseInt($('#batch_size').val()) || 50,
            create_gf_entries: $('#create_gf_entries').is(':checked'),
            skip_duplicates: $('#skip_duplicates').is(':checked'),
            membership_type: membershipType
        };
        
        importBatchNumber = 0;
        importInProgress = true;
        
        // Move to Step 3
        $('#step-2').removeClass('active');
        $('#step-3').addClass('active').show();
        currentStep = 3;
        
        // Start import process
        processImportBatch();
    });
    
    // Process import batch
    function processImportBatch() {
        var data = {
            action: 'legacy_import_process',
            nonce: legacyImport.nonce,
            dry_run: importOptions.dry_run ? 'true' : 'false',
            batch_size: importOptions.batch_size,
            create_gf_entries: importOptions.create_gf_entries ? 'true' : 'false',
            skip_duplicates: importOptions.skip_duplicates ? 'true' : 'false',
            batch_number: importBatchNumber,
            membership_type: importOptions.membership_type || ''
        };
        
        $('#import-status').html('<p>Processing batch ' + (importBatchNumber + 1) + '...</p>').removeClass('error success');
        
        $.ajax({
            url: legacyImport.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    updateProgress(response.data);
                    
                    if (response.data.completed) {
                        importInProgress = false;
                        // Clear the import session batch ID
                        showFinalResults(response.data);
                    } else {
                        // Continue with next batch only if next_batch is valid
                        if (response.data.next_batch !== null && response.data.next_batch !== undefined) {
                            importBatchNumber = response.data.next_batch;
                            setTimeout(processImportBatch, 500); // Small delay between batches
                        } else {
                            // No more batches, mark as completed
                            importInProgress = false;
                            showFinalResults(response.data);
                        }
                    }
                } else {
                    var errorMessage = 'Import failed';
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data.message) {
                            errorMessage = response.data.message;
                        } else if (Array.isArray(response.data)) {
                            errorMessage = response.data.join(', ');
                        } else if (response.data.errors && Array.isArray(response.data.errors)) {
                            errorMessage = 'Errors: ' + response.data.errors.slice(0, 5).join(', ');
                            if (response.data.errors.length > 5) {
                                errorMessage += ' (and ' + (response.data.errors.length - 5) + ' more)';
                            }
                        }
                    }
                    $('#import-status').html('<p class="error"><strong>Error:</strong> ' + errorMessage + '</p>').addClass('error');
                    if (response.data && response.data.file) {
                        console.error('Error in file:', response.data.file, 'Line:', response.data.line);
                    }
                    importInProgress = false;
                    $('#start-import-btn').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Import failed. Please check the logs.';
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data.message) {
                            errorMessage = response.data.message;
                        } else if (Array.isArray(response.data)) {
                            errorMessage = response.data.join(', ');
                        } else if (response.data.errors && Array.isArray(response.data.errors)) {
                            errorMessage = 'Errors: ' + response.data.errors.slice(0, 5).join(', ');
                            if (response.data.errors.length > 5) {
                                errorMessage += ' (and ' + (response.data.errors.length - 5) + ' more)';
                            }
                        }
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    console.error('Response:', xhr.responseText);
                }
                
                $('#import-status').html('<p class="error"><strong>Import Failed:</strong> ' + errorMessage + '</p>').addClass('error');
                if (xhr.responseText) {
                    console.error('Import Error Response:', xhr.responseText);
                }
                importInProgress = false;
                $('#start-import-btn').prop('disabled', false);
            }
        });
    }
    
    // Update progress display
    function updateProgress(data) {
        var percent = data.progress_percent || 0;
        $('#progress-fill').css('width', percent + '%').text(percent.toFixed(1) + '%');
        
        var statusHtml = '<p><strong>Progress:</strong> ' + data.total_processed + ' / ' + data.total_rows + ' rows processed</p>';
        if (data.dry_run) {
            statusHtml += '<p><em>Dry Run Mode - No data was created</em></p>';
        }
        $('#import-status').html(statusHtml).addClass('success');
        
        var statsHtml = '<h3>Import Statistics</h3>';
        statsHtml += '<table>';
        statsHtml += '<tr><td>Users Created:</td><td>' + (data.users_created || 0) + '</td></tr>';
        statsHtml += '<tr><td>Users Updated:</td><td>' + (data.users_updated || 0) + '</td></tr>';
        statsHtml += '<tr><td>Users Skipped:</td><td>' + (data.users_skipped || 0) + '</td></tr>';
        statsHtml += '<tr><td>Skipped (No Start Date):</td><td><strong style="color: #c60;">' + (data.skipped_entries ? data.skipped_entries.length : 0) + '</strong></td></tr>';
        statsHtml += '<tr><td>Successfully Imported:</td><td><strong style="color: #060;">' + (data.imported_entries ? data.imported_entries.length : 0) + '</strong></td></tr>';
        statsHtml += '<tr><td>GF Entries Created:</td><td>' + (data.gf_entries_created || 0) + '</td></tr>';
        statsHtml += '<tr><td>Errors:</td><td>' + (data.errors ? data.errors.length : 0) + '</td></tr>';
        statsHtml += '<tr><td>Import Batch ID:</td><td><code>' + (data.import_batch_id || 'N/A') + '</code></td></tr>';
        statsHtml += '</table>';
        
        $('#import-stats').html(statsHtml);
    }
    
    // Show final results
    function showFinalResults(data) {
        var resultsHtml = '<div class="import-results-content">';
        resultsHtml += '<h3>Import Completed!</h3>';
        
        resultsHtml += '<div class="summary-stats">';
        resultsHtml += '<h4>Summary</h4>';
        resultsHtml += '<table>';
        resultsHtml += '<tr><td>Total Rows Processed:</td><td><strong>' + data.total_processed + '</strong></td></tr>';
        resultsHtml += '<tr><td>Users Created:</td><td><strong>' + (data.users_created || 0) + '</strong></td></tr>';
        resultsHtml += '<tr><td>Users Updated:</td><td><strong>' + (data.users_updated || 0) + '</strong></td></tr>';
        resultsHtml += '<tr><td>Users Skipped:</td><td><strong>' + (data.users_skipped || 0) + '</strong></td></tr>';
        resultsHtml += '<tr><td>GF Entries Created:</td><td><strong>' + (data.gf_entries_created || 0) + '</strong></td></tr>';
        resultsHtml += '<tr><td>Total Errors:</td><td><strong>' + (data.errors ? data.errors.length : 0) + '</strong></td></tr>';
        resultsHtml += '</table>';
        resultsHtml += '</div>';
        
        // Show Imported Entries List
        if (data.imported_entries && data.imported_entries.length > 0) {
            resultsHtml += '<div class="imported-entries-section" style="margin-top: 20px; padding: 15px; background: #efe; border: 1px solid #9c9; border-radius: 4px;">';
            resultsHtml += '<h4 style="color: #060; margin-top: 0;">Successfully Imported Entries (' + data.imported_entries.length + ')</h4>';
            resultsHtml += '<p><button type="button" class="button button-secondary export-imported-btn" data-imported-entries=\'' + JSON.stringify(data.imported_entries) + '\' style="margin-top: 10px;">Export Imported Entries to CSV</button></p>';
            resultsHtml += '<details style="margin-top: 10px;" open>';
            resultsHtml += '<summary style="cursor: pointer; font-weight: bold; padding: 5px; background: #dfd; border-radius: 3px;">Click to view/hide imported entries</summary>';
            resultsHtml += '<table class="imported-entries-table" style="width: 100%; margin-top: 10px; border-collapse: collapse; background: #fff;">';
            resultsHtml += '<thead><tr style="background: #dfd;">';
            resultsHtml += '<th style="padding: 8px; border: 1px solid #9c9; text-align: left;">Row #</th>';
            resultsHtml += '<th style="padding: 8px; border: 1px solid #9c9; text-align: left;">Tab</th>';
            resultsHtml += '<th style="padding: 8px; border: 1px solid #9c9; text-align: left;">Name</th>';
            resultsHtml += '<th style="padding: 8px; border: 1px solid #9c9; text-align: left;">Email</th>';
            resultsHtml += '<th style="padding: 8px; border: 1px solid #9c9; text-align: left;">Action</th>';
            resultsHtml += '<th style="padding: 8px; border: 1px solid #9c9; text-align: left;">GF Entry ID</th>';
            resultsHtml += '</tr></thead><tbody>';
            
            data.imported_entries.forEach(function(entry) {
                resultsHtml += '<tr style="border-bottom: 1px solid #9c9;">';
                resultsHtml += '<td style="padding: 8px; border: 1px solid #9c9;">' + (entry.row_number || 'N/A') + '</td>';
                resultsHtml += '<td style="padding: 8px; border: 1px solid #9c9;">' + (entry.tab_name || 'N/A') + '</td>';
                resultsHtml += '<td style="padding: 8px; border: 1px solid #9c9;">' + (entry.name || 'N/A') + '</td>';
                resultsHtml += '<td style="padding: 8px; border: 1px solid #9c9;">' + (entry.email || 'N/A') + '</td>';
                resultsHtml += '<td style="padding: 8px; border: 1px solid #9c9;"><span style="color: #060; font-weight: bold;">' + (entry.action || 'N/A') + '</span></td>';
                resultsHtml += '<td style="padding: 8px; border: 1px solid #9c9;">';
                if (entry.gf_entry_id) {
                    resultsHtml += entry.gf_entry_id;
                    if (entry.gf_entries_count > 1) {
                        resultsHtml += ' <span style="color: #666; font-size: 0.9em;">(' + entry.gf_entries_count + ' entries)</span>';
                    }
                } else {
                    resultsHtml += 'N/A';
                }
                resultsHtml += '</td>';
                resultsHtml += '</tr>';
            });
            
            resultsHtml += '</tbody></table>';
            resultsHtml += '</details>';
            resultsHtml += '</div>';
        }
        
        // Show Skipped Entries List (No Start Date)
        if (data.skipped_entries && data.skipped_entries.length > 0) {
            resultsHtml += '<div class="skipped-entries-section" style="margin-top: 20px; padding: 15px; background: #ffe; border: 1px solid #fc9; border-radius: 4px;">';
            resultsHtml += '<h4 style="color: #c60; margin-top: 0;">Skipped Entries - Missing Dates (' + data.skipped_entries.length + ')</h4>';
            resultsHtml += '<p><strong>Note:</strong> These entries were skipped because they do not have valid Member Since or Renewal Date. They have been saved for later processing.</p>';
            resultsHtml += '<p><button type="button" class="button button-secondary export-skipped-btn" data-batch-id="' + (data.import_batch_id || '') + '" style="margin-top: 10px;">Export Skipped Entries to CSV</button></p>';
            resultsHtml += '<details style="margin-top: 10px;" open>';
            resultsHtml += '<summary style="cursor: pointer; font-weight: bold; padding: 5px; background: #fdd; border-radius: 3px;">Click to view/hide skipped entries</summary>';
            resultsHtml += '<table class="skipped-entries-table" style="width: 100%; margin-top: 10px; border-collapse: collapse; background: #fff;">';
            resultsHtml += '<thead><tr style="background: #fdd;">';
            resultsHtml += '<th style="padding: 8px; border: 1px solid #fc9; text-align: left;">Row #</th>';
            resultsHtml += '<th style="padding: 8px; border: 1px solid #fc9; text-align: left;">Tab</th>';
            resultsHtml += '<th style="padding: 8px; border: 1px solid #fc9; text-align: left;">Name</th>';
            resultsHtml += '<th style="padding: 8px; border: 1px solid #fc9; text-align: left;">Email</th>';
            resultsHtml += '<th style="padding: 8px; border: 1px solid #fc9; text-align: left;">Member Since</th>';
            resultsHtml += '<th style="padding: 8px; border: 1px solid #fc9; text-align: left;">Renewal Date</th>';
            resultsHtml += '<th style="padding: 8px; border: 1px solid #fc9; text-align: left;">Reason</th>';
            resultsHtml += '</tr></thead><tbody>';
            
            data.skipped_entries.forEach(function(entry) {
                resultsHtml += '<tr style="border-bottom: 1px solid #fc9;">';
                resultsHtml += '<td style="padding: 8px; border: 1px solid #fc9;">' + (entry.row_number || 'N/A') + '</td>';
                resultsHtml += '<td style="padding: 8px; border: 1px solid #fc9;">' + (entry.tab_name || 'N/A') + '</td>';
                resultsHtml += '<td style="padding: 8px; border: 1px solid #fc9;">' + (entry.name || 'N/A') + '</td>';
                resultsHtml += '<td style="padding: 8px; border: 1px solid #fc9;">' + (entry.email || 'N/A') + '</td>';
                resultsHtml += '<td style="padding: 8px; border: 1px solid #fc9;">' + (entry.member_since || 'N/A') + '</td>';
                resultsHtml += '<td style="padding: 8px; border: 1px solid #fc9;">' + (entry.renewal_date || 'N/A') + '</td>';
                resultsHtml += '<td style="padding: 8px; border: 1px solid #fc9;"><span style="color: #c60; font-weight: bold;">' + (entry.reason || 'Missing Start Date') + '</span></td>';
                resultsHtml += '</tr>';
            });
            
            resultsHtml += '</tbody></table>';
            resultsHtml += '</details>';
            resultsHtml += '</div>';
        }
        
        if (data.errors && data.errors.length > 0) {
            resultsHtml += '<div class="error-section" style="margin-top: 20px; padding: 15px; background: #fee; border: 1px solid #fcc; border-radius: 4px;">';
            resultsHtml += '<h4 style="color: #c00; margin-top: 0;">Errors (' + data.errors.length + ')</h4>';
            resultsHtml += '<p><strong>Common issues:</strong> Missing required fields, invalid date formats, or column header mismatches.</p>';
            
            // Show detailed error records if available
            if (data.error_records && data.error_records.length > 0) {
                resultsHtml += '<details style="margin-top: 10px;" open>';
                resultsHtml += '<summary style="cursor: pointer; font-weight: bold; padding: 5px; background: #fdd; border-radius: 3px;">Click to view detailed error records</summary>';
                resultsHtml += '<table class="error-records-table" style="width: 100%; margin-top: 10px; border-collapse: collapse; background: #fff;">';
                resultsHtml += '<thead><tr style="background: #fdd;">';
                resultsHtml += '<th style="padding: 8px; border: 1px solid #fcc; text-align: left;">Row #</th>';
                resultsHtml += '<th style="padding: 8px; border: 1px solid #fcc; text-align: left;">Tab</th>';
                resultsHtml += '<th style="padding: 8px; border: 1px solid #fcc; text-align: left;">Name</th>';
                resultsHtml += '<th style="padding: 8px; border: 1px solid #fcc; text-align: left;">Email</th>';
                resultsHtml += '<th style="padding: 8px; border: 1px solid #fcc; text-align: left;">Errors</th>';
                resultsHtml += '</tr></thead><tbody>';
                
                data.error_records.forEach(function(errorRecord) {
                    resultsHtml += '<tr style="border-bottom: 1px solid #fcc;">';
                    resultsHtml += '<td style="padding: 8px; border: 1px solid #fcc;">' + (errorRecord.row_number || 'N/A') + '</td>';
                    resultsHtml += '<td style="padding: 8px; border: 1px solid #fcc;">' + (errorRecord.tab_name || 'N/A') + '</td>';
                    resultsHtml += '<td style="padding: 8px; border: 1px solid #fcc;">' + (errorRecord.name || 'N/A') + '</td>';
                    resultsHtml += '<td style="padding: 8px; border: 1px solid #fcc;">' + (errorRecord.email || 'N/A') + '</td>';
                    resultsHtml += '<td style="padding: 8px; border: 1px solid #fcc;">';
                    if (errorRecord.errors && Array.isArray(errorRecord.errors)) {
                        resultsHtml += '<ul style="margin: 0; padding-left: 20px;">';
                        errorRecord.errors.forEach(function(err) {
                            resultsHtml += '<li style="margin: 2px 0;">' + err + '</li>';
                        });
                        resultsHtml += '</ul>';
                    } else {
                        resultsHtml += errorRecord.error_message || 'Unknown error';
                    }
                    resultsHtml += '</td>';
                    resultsHtml += '</tr>';
                });
                
                resultsHtml += '</tbody></table>';
                resultsHtml += '</details>';
            }
            
            // Also show simple error list
            resultsHtml += '<details style="margin-top: 10px;">';
            resultsHtml += '<summary style="cursor: pointer; font-weight: bold; padding: 5px; background: #fdd; border-radius: 3px;">Click to view all error messages</summary>';
            resultsHtml += '<ul class="error-list" style="max-height: 400px; overflow-y: auto; margin-top: 10px;">';
            data.errors.forEach(function(error) {
                resultsHtml += '<li style="margin: 5px 0; padding: 5px; background: #fff; border-left: 3px solid #c00;">' + error + '</li>';
            });
            resultsHtml += '</ul>';
            resultsHtml += '</details>';
            resultsHtml += '</div>';
        }
        
        if (data.warnings && data.warnings.length > 0) {
            resultsHtml += '<div class="warning-section">';
            resultsHtml += '<h4>Warnings (' + data.warnings.length + ')</h4>';
            resultsHtml += '<ul class="warning-list">';
            data.warnings.slice(0, 50).forEach(function(warning) {
                resultsHtml += '<li>' + warning + '</li>';
            });
            if (data.warnings.length > 50) {
                resultsHtml += '<li><em>... and ' + (data.warnings.length - 50) + ' more warnings</em></li>';
            }
            resultsHtml += '</ul>';
            resultsHtml += '</div>';
        }
        
        resultsHtml += '<p><strong>Import Batch ID:</strong> <code>' + (data.import_batch_id || 'N/A') + '</code></p>';
        resultsHtml += '<p><em>Check the log file in wp-content/uploads/legacy-import-logs/ for detailed information.</em></p>';
        
        resultsHtml += '</div>';
        
        $('#import-results').html(resultsHtml).show();
        
        // Handle export buttons
        $('.export-skipped-btn').on('click', function() {
            var batchId = $(this).data('batch-id');
            if (!batchId) {
                alert('Batch ID not found. Cannot export skipped entries.');
                return;
            }
            
            // Create form and submit
            var form = $('<form>', {
                'method': 'POST',
                'action': legacyImport.ajax_url,
                'target': '_blank'
            });
            form.append($('<input>', {
                'type': 'hidden',
                'name': 'action',
                'value': 'legacy_import_export_skipped'
            }));
            form.append($('<input>', {
                'type': 'hidden',
                'name': 'nonce',
                'value': legacyImport.nonce
            }));
            form.append($('<input>', {
                'type': 'hidden',
                'name': 'batch_id',
                'value': batchId
            }));
            $('body').append(form);
            form.submit();
            form.remove();
        });
        
        $('.export-imported-btn').on('click', function() {
            var importedEntries = $(this).data('imported-entries');
            if (!importedEntries || importedEntries.length === 0) {
                alert('No imported entries to export.');
                return;
            }
            
            // Create form and submit
            var form = $('<form>', {
                'method': 'POST',
                'action': legacyImport.ajax_url,
                'target': '_blank'
            });
            form.append($('<input>', {
                'type': 'hidden',
                'name': 'action',
                'value': 'legacy_import_export_imported'
            }));
            form.append($('<input>', {
                'type': 'hidden',
                'name': 'nonce',
                'value': legacyImport.nonce
            }));
            form.append($('<input>', {
                'type': 'hidden',
                'name': 'imported_entries',
                'value': JSON.stringify(importedEntries)
            }));
            $('body').append(form);
            form.submit();
            form.remove();
        });
    }
});

