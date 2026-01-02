<?php
/**
 * CPD Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$cpd_manager = CPD_Manager::get_instance();
if (!$cpd_manager->user_can_access_cpd_admin()) {
    wp_die('You do not have permission to access this page.');
}

// Handle form submission
if (isset($_POST['save_settings']) && check_admin_referer('cpd_settings')) {
    update_option('cpd_minimum_points_required', floatval($_POST['minimum_points_required']));
    update_option('cpd_annual_report_enabled', isset($_POST['annual_report_enabled']) ? 'yes' : 'no');
    
    echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
}

// Get super admin email from options (for display only)
$super_admin_email = get_option('admin_email', '');
$cpd_super_admin_email = get_option('cpd_super_admin_email', '');
$display_super_admin_email = !empty($cpd_super_admin_email) ? $cpd_super_admin_email : $super_admin_email;

$minimum_points = get_option('cpd_minimum_points_required', 150);
$annual_report_enabled = get_option('cpd_annual_report_enabled', 'yes');
?>

<div class="wrap">
    <h1>CPD Settings</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('cpd_settings'); ?>
        
        <div class="notice notice-info">
            <p><strong>Email Notifications:</strong> All administrators and the super admin (email stored in WordPress options) will automatically receive CPD entry notifications. No additional configuration needed.</p>
            <?php if (!empty($display_super_admin_email)): ?>
            <p><strong>Current Super Admin Email:</strong> <?php echo esc_html($display_super_admin_email); ?> (from WordPress options)</p>
            <?php endif; ?>
        </div>
        
        <table class="form-table">
            <tr>
                <th><label for="minimum_points_required">Minimum Points Required:</label></th>
                <td>
                    <input type="number" 
                           id="minimum_points_required" 
                           name="minimum_points_required" 
                           value="<?php echo esc_attr($minimum_points); ?>" 
                           step="0.1" 
                           min="0" 
                           class="small-text" />
                    <p class="description">Minimum CPD points required over 5 years for certificate renewal or recertification (default: 150 points over 5 years).</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="annual_report_enabled">Annual Reports:</label></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="annual_report_enabled" 
                               name="annual_report_enabled" 
                               value="yes" 
                               <?php checked($annual_report_enabled, 'yes'); ?> />
                        Enable automatic annual report generation
                    </label>
                    <p class="description">When enabled, annual reports will be automatically generated and sent to users.</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" name="save_settings" class="button button-primary" id="save-cpd-settings">Save Settings</button>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Show success message if settings were saved
    <?php if (isset($_POST['save_settings'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Settings saved successfully!',
        confirmButtonColor: '#0073aa',
        timer: 2000,
        timerProgressBar: true
    });
    <?php endif; ?>
    
    // Handle form submission with SweetAlert
    $('form').on('submit', function(e) {
        var $form = $(this);
        var $submitBtn = $('#save-cpd-settings');
        
        // Validate form
        var formValid = true;
        $form.find('input[required]').each(function() {
            if (!$(this).val()) {
                formValid = false;
                return false;
            }
        });
        
        if (!formValid) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please fill in all required fields.',
                confirmButtonColor: '#dc3232'
            });
            return false;
        }
        
        // Show loading
        Swal.fire({
            title: 'Saving Settings...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function() {
                Swal.showLoading();
            }
        });
        
        // Let form submit normally - success will be shown on reload
        // The form will submit and reload, then show success message above
    });
});
</script>

