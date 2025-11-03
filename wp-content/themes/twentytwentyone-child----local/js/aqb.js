jQuery(function($) {
    $('.review-btn').on('click', function() {
        const eid = $(this).data('entry-id');
		const exam_id = $(this).data('exam-id');
        const method = $(this).data('method');
        $('#aqbModal').removeClass('hidden');
        $('#modalContent').html('<p class="text-gray-500">Loading marks...</p>');
		
        $.post(aqbData.ajax_url, {
            action: 'aqb_get_marks',
            entry_id: eid,
			exam_id: exam_id,
            _ajax_nonce: aqbData.ajax_nonce
        }, function(res) {
            if (res.success) {
                $('#modalContent').html(res.data.html);
               // $('#aqbVerify').data('entry-id', eid).data('exam-id', exam_id).data('method', method);
            } else {
                $('#modalContent').html('<p class="text-red-500">Error loading marks</p>');
            }
        });
    });

    $('#aqbCancel').on('click', function() {
        $('#aqbModal').addClass('hidden');
    });

    $('#aqbVerify').on('click', function() {
        const eid = $(this).data('entry-id');
        const method = $(this).data('method');
        $(this).text('Processing...').prop('disabled', true);

        $.post(aqbData.ajax_url, {
            action: 'aqb_verify_generate',
            entry_id: eid,
            method: method,
            _ajax_nonce: aqbData.ajax_nonce
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data || 'Verification failed');
                $('#aqbVerify').text('Verify & Generate').prop('disabled', false);
            }
        });
    });
	jQuery(document).on('click', '#aqbSubmitAndGenerate', function () {
    const formData = jQuery('#aqbMarkEditForm').serialize();
    jQuery.ajax({
        method: 'POST',
        url: aqbData.ajax_url,
        data: {
            action: 'aqb_save_and_generate',
            _ajax_nonce: aqbData.ajax_nonce,
            ...Object.fromEntries(new URLSearchParams(formData))
        },
        success: function (res) {
            if (res.success) {
                alert('Certificate Generated Successfully');
                window.open(res.data.url, '_blank');
                location.reload();
            } else {
                alert(res.data || 'Something went wrong');
            }
        }
    });
});

});
