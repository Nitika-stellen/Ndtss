/**
 * Certificate Management JavaScript
 */

jQuery(document).ready(function ($) {

    // Export certificates
    $('#export-certificates').on('click', function (e) {
        e.preventDefault();

        var button = $(this);
        var originalText = button.text();

        button.text('Exporting...').prop('disabled', true);

        $.ajax({
            url: certManagement.ajax_url,
            type: 'POST',
            data: {
                action: 'export_certificates',
                nonce: certManagement.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Create temporary link and trigger download
                    var link = document.createElement('a');
                    link.href = response.data.url;
                    link.download = '';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    button.text('Export Complete!');
                    setTimeout(function () {
                        button.text(originalText).prop('disabled', false);
                    }, 2000);
                } else {
                    alert('Export failed: ' + response.data);
                    button.text(originalText).prop('disabled', false);
                }
            },
            error: function () {
                alert('Export failed. Please try again.');
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    // Real-time search (debounced)
    var searchTimeout;
    $('.cert-search-input').on('keyup', function () {
        clearTimeout(searchTimeout);
        var searchTerm = $(this).val();

        if (searchTerm.length >= 3 || searchTerm.length === 0) {
            searchTimeout = setTimeout(function () {
                // Submit the form
                $('.cert-filters-form').submit();
            }, 500);
        }
    });

    // Highlight search terms in results
    function highlightSearchTerms() {
        var searchTerm = $('.cert-search-input').val();
        if (searchTerm && searchTerm.length >= 3) {
            $('.cert-table tbody td').each(function () {
                var text = $(this).text();
                var regex = new RegExp('(' + searchTerm + ')', 'gi');
                var newText = text.replace(regex, '<mark>$1</mark>');
                if (newText !== text) {
                    $(this).html(newText);
                }
            });
        }
    }

    highlightSearchTerms();

    // Confirm before actions
    $('.cert-table').on('click', '.delete-cert', function (e) {
        if (!confirm('Are you sure you want to delete this certificate? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });

    // Tooltip for truncated text
    $('.cert-table td').each(function () {
        var $this = $(this);
        if (this.offsetWidth < this.scrollWidth) {
            $this.attr('title', $this.text());
        }
    });

    // Auto-refresh statistics every 30 seconds (optional)
    // Uncomment if you want live updates
    /*
    setInterval(function() {
        $.ajax({
            url: certManagement.ajax_url,
            type: 'POST',
            data: {
                action: 'get_certificate_stats',
                nonce: certManagement.nonce
            },
            success: function(response) {
                if (response.success) {
                    var stats = response.data;
                    $('.stat-card').eq(0).find('.stat-number').text(stats.total.toLocaleString());
                    $('.stat-card').eq(1).find('.stat-number').text(stats.active.toLocaleString());
                    $('.stat-card').eq(2).find('.stat-number').text(stats.expired.toLocaleString());
                    $('.stat-card').eq(3).find('.stat-number').text(stats.legacy_imports.toLocaleString());
                    $('.stat-card').eq(4).find('.stat-number').text(stats.with_pdfs.toLocaleString());
                }
            }
        });
    }, 30000);
    */
});
