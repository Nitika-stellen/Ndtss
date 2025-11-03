jQuery(function($){
    var $form = $('#cpd-renew-form');
    if ($form.length) {
        // Form submission
        $form.on('submit', function(e){
            e.preventDefault();
            var $submitBtn = $form.find('.submit-button');
            var $msg = $form.find('.renew-message');
            
            // Disable submit button
            $submitBtn.prop('disabled', true).text('Submitting...');
            $msg.hide();
            
            var formData = new FormData(this);
            formData.append('action', 'submit_cpd_form');
            formData.append('nonce', RenewAjax.nonce);

            $.ajax({
                url: RenewAjax.ajax_url,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).done(function(resp){
                console.log('AJAX Response:', resp);
                if (resp && resp.success) {
                    $msg.removeClass('error').addClass('success')
                        .text(resp.data.message || 'Application submitted successfully!')
                        .show();
                    $form[0].reset();
                    updateYearTotals(); // Reset totals
                } else {
                    var err = 'Error submitting application';
                    if (resp && resp.data) {
                        if (resp.data.message) {
                            err = resp.data.message;
                        } else if (resp.data.errors) {
                            err = 'Validation errors: ' + Object.values(resp.data.errors).join(', ');
                        }
                    }
                    $msg.removeClass('success').addClass('error')
                        .text(err).show();
                }
            }).fail(function(xhr, status, error){
                console.log('AJAX Error:', {xhr: xhr, status: status, error: error, responseText: xhr.responseText});
                $msg.removeClass('success').addClass('error')
                    .text('Request failed: ' + error + '. Please try again.').show();
            }).always(function(){
                $submitBtn.prop('disabled', false).html('<i class="dashicons dashicons-yes"></i>Submit CPD Renewal Application');
            });
        });

        // Real-time CPD point calculations
        function updateYearTotals() {
            $('.cpd-year-card').each(function(){
                var $card = $(this);
                var total = 0;
                $card.find('input[type="number"]').each(function(){
                    var val = parseFloat($(this).val()) || 0;
                    total += val;
                });
                $card.find('.total-points').text(total.toFixed(1));
            });
        }

        // Update totals when inputs change
        $form.on('input', 'input[type="number"]', updateYearTotals);

        // File upload handling
        function handleFileUpload(input, listId) {
            var $input = $(input);
            var $list = $('#' + listId);
            
            $input.on('change', function(){
                $list.empty();
                var files = this.files;
                
                if (files.length === 0) {
                    $list.append('<div class="no-files">No files selected</div>');
                    return;
                }
                
                for (var i=0; i<files.length; i++) {
                    (function(idx){
                        var f = files[idx];
                        var size = Math.round(f.size/1024);
                        var sizeText = size > 1024 ? (size/1024).toFixed(1) + ' MB' : size + ' KB';
                        
                        var $item = $('<div class="file-item">');
                        $item.append('<span class="file-name">' + f.name + '</span>');
                        $item.append('<span class="file-size">' + sizeText + '</span>');
                        
                        var $remove = $('<button type="button" class="remove-file">Remove</button>');
                        $remove.on('click', function(){
                            // Create new FileList without this file
                            var dt = new DataTransfer();
                            for (var j=0; j<files.length; j++) {
                                if (j !== idx) {
                                    dt.items.add(files[j]);
                                }
                            }
                            $input[0].files = dt.files;
                            handleFileUpload($input[0], listId); // Refresh list
                        });
                        
                        $item.append($remove);
                        $list.append($item);
                    })(i);
                }
            });
        }

        // Initialize file uploads
        handleFileUpload('#cpd_files', 'cpd-files-list');
        handleFileUpload('#support_docs', 'support-files-list');
        handleFileUpload('#previous_certificate', 'certificate-file-list');

        // Drag and drop functionality
        $('.file-upload-wrapper').on('dragover', function(e){
            e.preventDefault();
            $(this).addClass('drag-over');
        }).on('dragleave', function(e){
            e.preventDefault();
            $(this).removeClass('drag-over');
        }).on('drop', function(e){
            e.preventDefault();
            $(this).removeClass('drag-over');
            var files = e.originalEvent.dataTransfer.files;
            var input = $(this).find('input[type="file"]')[0];
            
            // Create new FileList
            var dt = new DataTransfer();
            for (var i=0; i<files.length; i++) {
                dt.items.add(files[i]);
            }
            input.files = dt.files;
            
            // Trigger change event
            $(input).trigger('change');
        });

        // Initialize year totals
        updateYearTotals();
        
        // Test AJAX connection
        $('#test-ajax').on('click', function(){
            $.ajax({
                url: RenewAjax.ajax_url,
                method: 'POST',
                data: {
                    action: 'test_renew_ajax',
                    nonce: RenewAjax.nonce
                }
            }).done(function(resp){
                console.log('Test AJAX Response:', resp);
                alert('Test AJAX Success: ' + JSON.stringify(resp));
            }).fail(function(xhr, status, error){
                console.log('Test AJAX Error:', {xhr: xhr, status: status, error: error, responseText: xhr.responseText});
                alert('Test AJAX Failed: ' + error + '\nResponse: ' + xhr.responseText);
            });
        });
    }
});


