<?php
/**
 * CPD Annual Reports Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$cpd_manager = CPD_Manager::get_instance();
if (!$cpd_manager->user_can_access_cpd_admin()) {
    wp_die('You do not have permission to access this page.');
}

global $wpdb;
$table_name = $wpdb->prefix . 'sgndt_cpd_annual_reports';

// Handle manual report generation
$reports_generated = false;
if (isset($_POST['generate_reports']) && check_admin_referer('generate_annual_reports')) {
    $cpd_manager->generate_annual_reports();
    $reports_generated = true;
}

// Get annual reports
$reports = $wpdb->get_results(
    "SELECT r.*, u.display_name, u.user_email 
    FROM {$table_name} r 
    LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID 
    ORDER BY r.report_year DESC, u.display_name ASC"
);
?>

<div class="wrap">
    <h1>CPD Annual Reports</h1>
    
    <div class="cpd-reports-actions">
        <form method="post" action="" id="generate-reports-form" style="display: inline-block;">
            <?php wp_nonce_field('generate_annual_reports'); ?>
            <button type="submit" name="generate_reports" class="button button-primary" id="generate-reports-btn">
                Generate Reports for Previous Year
            </button>
        </form>
        <p class="description">Click to generate annual reports for all users for the previous year.</p>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>User</th>
                <th>Year</th>
                <th>Total Points</th>
                <th>Points Needed</th>
                <th>Generated</th>
                <th>Sent</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reports)): ?>
            <tr>
                <td colspan="6">No annual reports generated yet.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($reports as $report): ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($report->display_name); ?></strong><br>
                    <small><?php echo esc_html($report->user_email); ?></small>
                </td>
                <td><?php echo esc_html($report->report_year); ?></td>
                <td><strong><?php echo esc_html(number_format($report->total_points, 2)); ?></strong></td>
                <td>
                    <?php if ($report->points_needed > 0): ?>
                        <span style="color: #dc3232;"><?php echo esc_html(number_format($report->points_needed, 2)); ?></span>
                    <?php else: ?>
                        <span style="color: #46b450;">Met requirement</span>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html(date('M j, Y', strtotime($report->report_generated_at))); ?></td>
                <td>
                    <?php if ($report->report_sent_at): ?>
                        <?php echo esc_html(date('M j, Y', strtotime($report->report_sent_at))); ?>
                    <?php else: ?>
                        <span class="text-muted">Not sent</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle generate reports form submission
    $('#generate-reports-form').on('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Generate Reports?',
            text: 'This will generate annual reports for all users for the previous year. Continue?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0073aa',
            cancelButtonColor: '#dc3232',
            confirmButtonText: 'Yes, Generate',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Generating Reports...',
                    text: 'Please wait while reports are being generated.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: function() {
                        Swal.showLoading();
                    }
                });
                
                // Submit form
                var $form = $('#generate-reports-form');
                var formData = $form.serialize();
                
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        Swal.close();
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Annual reports generated successfully!',
                            confirmButtonColor: '#0073aa',
                            timer: 2000,
                            timerProgressBar: true
                        }).then(function() {
                            window.location.reload();
                        });
                    },
                    error: function() {
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred while generating reports. Please try again.',
                            confirmButtonColor: '#dc3232'
                        });
                    }
                });
            }
        });
    });
    
    // Show success message if reports were generated
    <?php if ($reports_generated): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Annual reports generated successfully!',
        confirmButtonColor: '#0073aa',
        timer: 2000,
        timerProgressBar: true
    });
    <?php endif; ?>
});
</script>

