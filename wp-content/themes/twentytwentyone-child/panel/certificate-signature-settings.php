<?php
/**
 * Certificate Signature Settings
 * 
 * Allows admins to upload signatures and configure signatory names for certificates
 * 
 * @package SGNDT
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include helper functions
require_once get_stylesheet_directory() . '/includes/certificate-signature-helpers.php';

// Add submenu under Certificates
add_action('admin_menu', 'cert_signature_add_admin_menu', 25);
add_action('admin_init', 'cert_signature_register_settings');
add_action('admin_enqueue_scripts', 'cert_signature_enqueue_scripts');
add_action('wp_ajax_cert_signature_upload', 'cert_signature_handle_upload');

/**
 * Add admin menu
 */
function cert_signature_add_admin_menu() {
    global $submenu, $menu;
    $parent_slug = '';
    
    // Check if certificate-renewals menu exists
    if (isset($submenu['certificate-renewals'])) {
        $parent_slug = 'certificate-renewals';
    }
    // Check if certificate-management menu already exists in submenu
    elseif (isset($submenu['certificate-management'])) {
        $parent_slug = 'certificate-management';
    }
    // Check if certificate-management menu exists in main menu
    elseif (isset($menu)) {
        foreach ($menu as $menu_item) {
            if (isset($menu_item[2]) && $menu_item[2] === 'certificate-management') {
                $parent_slug = 'certificate-management';
                break;
            }
        }
    }
    
    // If neither exists, create the parent menu
    if (empty($parent_slug)) {
        add_menu_page(
            'Certificate Management',
            'Certificates',
            'manage_options',
            'certificate-management',
            '__return_empty_string',
            'dashicons-awards',
            30
        );
        $parent_slug = 'certificate-management';
    }
    
    add_submenu_page(
        $parent_slug,
        'Certificate Signatures',
        'Certificate Signatures',
        'manage_options',
        'certificate-signatures',
        'cert_signature_settings_page'
    );
}

/**
 * Register settings
 */
function cert_signature_register_settings() {
    // Chairman settings
    register_setting('cert_signature_settings', 'cert_chairman_name');
    register_setting('cert_signature_settings', 'cert_chairman_title');
    register_setting('cert_signature_settings', 'cert_chairman_signature');
    
    // Authorized Signatory settings
    register_setting('cert_signature_settings', 'cert_signatory_name');
    register_setting('cert_signature_settings', 'cert_signatory_title');
    register_setting('cert_signature_settings', 'cert_signatory_signature');
}

/**
 * Enqueue admin scripts
 */
function cert_signature_enqueue_scripts($hook) {
    if (strpos($hook, 'certificate-signatures') === false) {
        return;
    }
    
    wp_enqueue_media();
    wp_enqueue_script('jquery');
}

/**
 * Settings page
 */
function cert_signature_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    // Handle form submission
    if (isset($_POST['cert_signature_submit']) && check_admin_referer('cert_signature_settings_nonce')) {
        update_option('cert_chairman_name', sanitize_text_field($_POST['cert_chairman_name']));
        update_option('cert_chairman_title', sanitize_text_field($_POST['cert_chairman_title']));
        update_option('cert_chairman_signature', sanitize_text_field($_POST['cert_chairman_signature']));
        
        update_option('cert_signatory_name', sanitize_text_field($_POST['cert_signatory_name']));
        update_option('cert_signatory_title', sanitize_text_field($_POST['cert_signatory_title']));
        update_option('cert_signatory_signature', sanitize_text_field($_POST['cert_signatory_signature']));
        
        // Save Email Notification Setting
        if (current_user_can('administrator')) {
            update_option('ndtss_enable_result_notification_email', isset($_POST['ndtss_enable_result_notification_email']) ? 1 : 0);
            update_option('ndtss_enable_final_cert_notification_email', isset($_POST['ndtss_enable_final_cert_notification_email']) ? 1 : 0);
        }

        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
    }
    
    // Get current values
    $chairman_name = get_option('cert_chairman_name', '');
    $chairman_title = get_option('cert_chairman_title', 'CHAIRMAN / VICE CHAIRMAN');
    $chairman_signature = get_option('cert_chairman_signature', '');
    
    $signatory_name = get_option('cert_signatory_name', '');
    $signatory_title = get_option('cert_signatory_title', 'AUTHORIZED SIGNATORY');
    $signatory_signature = get_option('cert_signatory_signature', '');



    $enable_notification_email = get_option('ndtss_enable_result_notification_email', 1);
    $enable_final_cert_email = get_option('ndtss_enable_final_cert_notification_email', 1);
    
    ?>
    <div class="wrap cert-signature-settings-wrap">
        <h1>Certificate Signature Settings</h1>
        <p class="description">Configure the signatures and names that appear on certificates. These will be used for both new certificates and renewed/recertified certificates.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('cert_signature_settings_nonce'); ?>
            
            <div class="cert-signature-container">
                <!-- Chairman Section -->
                <div class="signature-section">
                    <h2>Chairman / Vice Chairman</h2>
                    <p class="description">This signature appears on the left side of the certificate</p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="cert_chairman_name">Name</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="cert_chairman_name" 
                                       name="cert_chairman_name" 
                                       value="<?php echo esc_attr($chairman_name); ?>" 
                                       class="regular-text"
                                       placeholder="e.g., John Doe">
                                <p class="description">The name that will appear below the signature</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="cert_chairman_title">Title</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="cert_chairman_title" 
                                       name="cert_chairman_title" 
                                       value="<?php echo esc_attr($chairman_title); ?>" 
                                       class="regular-text"
                                       placeholder="e.g., CHAIRMAN / VICE CHAIRMAN">
                                <p class="description">The title that appears above the signature line</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="cert_chairman_signature">Signature Image</label>
                            </th>
                            <td>
                                <div class="signature-upload-wrapper">
                                    <input type="hidden" 
                                           id="cert_chairman_signature" 
                                           name="cert_chairman_signature" 
                                           value="<?php echo esc_attr($chairman_signature); ?>">
                                    
                                    <div class="signature-preview" id="chairman-signature-preview">
                                        <?php if ($chairman_signature): ?>
                                            <img src="<?php echo esc_url($chairman_signature); ?>" alt="Chairman Signature" style="max-width: 300px; max-height: 100px; border: 1px solid #ddd; padding: 10px; background: white;">
                                        <?php else: ?>
                                            <div class="no-signature">No signature uploaded</div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button type="button" class="button upload-signature-btn" data-target="chairman">
                                        <span class="dashicons dashicons-upload"></span> Upload Signature
                                    </button>
                                    
                                    <?php if ($chairman_signature): ?>
                                        <button type="button" class="button remove-signature-btn" data-target="chairman">
                                            <span class="dashicons dashicons-trash"></span> Remove
                                        </button>
                                    <?php endif; ?>
                                    
                                    <p class="description">Upload a transparent PNG image (recommended size: 300x100px)</p>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <hr style="margin: 40px 0;">
                
                <!-- Authorized Signatory Section -->
                <div class="signature-section">
                    <h2>Authorized Signatory</h2>
                    <p class="description">This signature appears on the right side of the certificate</p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="cert_signatory_name">Name</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="cert_signatory_name" 
                                       name="cert_signatory_name" 
                                       value="<?php echo esc_attr($signatory_name); ?>" 
                                       class="regular-text"
                                       placeholder="e.g., Jane Smith">
                                <p class="description">The name that will appear below the signature</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="cert_signatory_title">Title</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="cert_signatory_title" 
                                       name="cert_signatory_title" 
                                       value="<?php echo esc_attr($signatory_title); ?>" 
                                       class="regular-text"
                                       placeholder="e.g., AUTHORIZED SIGNATORY">
                                <p class="description">The title that appears above the signature line</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="cert_signatory_signature">Signature Image</label>
                            </th>
                            <td>
                                <div class="signature-upload-wrapper">
                                    <input type="hidden" 
                                           id="cert_signatory_signature" 
                                           name="cert_signatory_signature" 
                                           value="<?php echo esc_attr($signatory_signature); ?>">
                                    
                                    <div class="signature-preview" id="signatory-signature-preview">
                                        <?php if ($signatory_signature): ?>
                                            <img src="<?php echo esc_url($signatory_signature); ?>" alt="Signatory Signature" style="max-width: 300px; max-height: 100px; border: 1px solid #ddd; padding: 10px; background: white;">
                                        <?php else: ?>
                                            <div class="no-signature">No signature uploaded</div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button type="button" class="button upload-signature-btn" data-target="signatory">
                                        <span class="dashicons dashicons-upload"></span> Upload Signature
                                    </button>
                                    
                                    <?php if ($signatory_signature): ?>
                                        <button type="button" class="button remove-signature-btn" data-target="signatory">
                                            <span class="dashicons dashicons-trash"></span> Remove
                                        </button>
                                    <?php endif; ?>
                                    
                                    <p class="description">Upload a transparent PNG image (recommended size: 300x100px)</p>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <hr style="margin: 40px 0;">
                
                <!-- Preview Section -->
                <div class="signature-preview-section">
                    <h2>Certificate Preview</h2>
                    <p class="description">This is how the signatures will appear on the certificate</p>
                    
                    <div class="cert-preview-box">
                        <div class="cert-preview-signatures">
                            <div class="cert-preview-sig-item">
                                <strong><?php echo esc_html($chairman_title ?: 'CHAIRMAN / VICE CHAIRMAN'); ?></strong><br>
                                <strong>CERTIFICATION COMMITTEE</strong>
                                <div class="cert-preview-sig-image">
                                    <?php if ($chairman_signature): ?>
                                        <img src="<?php echo esc_url($chairman_signature); ?>" alt="Chairman Signature" style="max-height: 60px;">
                                    <?php else: ?>
                                        <div style="height: 60px; display: flex; align-items: center; color: #999;">No signature</div>
                                    <?php endif; ?>
                                </div>
                                <div class="cert-preview-sig-line">__________________</div>
                                <?php if ($chairman_name): ?>
                                    <div class="cert-preview-sig-name"><?php echo esc_html($chairman_name); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="cert-preview-sig-item">
                                <strong><?php echo esc_html($signatory_title ?: 'AUTHORIZED SIGNATORY'); ?></strong><br>
                                <strong>NDTSS</strong>
                                <div class="cert-preview-sig-image">
                                    <?php if ($signatory_signature): ?>
                                        <img src="<?php echo esc_url($signatory_signature); ?>" alt="Signatory Signature" style="max-height: 60px;">
                                    <?php else: ?>
                                        <div style="height: 60px; display: flex; align-items: center; color: #999;">No signature</div>
                                    <?php endif; ?>
                                </div>
                                <div class="cert-preview-sig-line">__________________</div>
                                <?php if ($signatory_name): ?>
                                    <div class="cert-preview-sig-name"><?php echo esc_html($signatory_name); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (current_user_can('administrator')): ?>
                <hr style="margin: 40px 0;">

                <!-- Email Notification Section -->
                <div class="signature-section">
                    <h2>Email Notification Settings</h2>
                    <p class="description">Configure whether to send result notification emails to candidates automatically.</p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ndtss_enable_result_notification_email">Send Result Notification</label>
                            </th>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" 
                                           id="ndtss_enable_result_notification_email" 
                                           name="ndtss_enable_result_notification_email" 
                                           value="1" 
                                           <?php checked($enable_notification_email, 1); ?>>
                                    <span class="slider round"></span>
                                </label>
                                <p class="description" style="display:inline-block; margin-left: 10px; vertical-align: middle;">
                                    If enabled, candidates will receive an email with their PDF result attached when generated (Initial/Retest).
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ndtss_enable_final_cert_notification_email">Send Final Certificate</label>
                            </th>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" 
                                           id="ndtss_enable_final_cert_notification_email" 
                                           name="ndtss_enable_final_cert_notification_email" 
                                           value="1" 
                                           <?php checked($enable_final_cert_email, 1); ?>>
                                    <span class="slider round"></span>
                                </label>
                                <p class="description" style="display:inline-block; margin-left: 10px; vertical-align: middle;">
                                    If enabled, candidates will receive an email with their Final Certificate PDF attached.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <?php submit_button('Save Signature Settings', 'primary', 'cert_signature_submit'); ?>
        </form>
    </div>
    
    <style>
        .cert-signature-settings-wrap {
            max-width: 1200px;
        }
        .cert-signature-container {
            background: white;
            padding: 30px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin-top: 20px;
        }
        .signature-section h2 {
            margin-top: 0;
            color: #23282d;
            font-size: 20px;
        }
        .signature-upload-wrapper {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .signature-preview {
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9f9f9;
            border: 2px dashed #ddd;
            border-radius: 4px;
            padding: 20px;
        }
        .no-signature {
            color: #999;
            font-style: italic;
        }
        .upload-signature-btn,
        .remove-signature-btn {
            width: fit-content;
        }
        .upload-signature-btn .dashicons,
        .remove-signature-btn .dashicons {
            margin-top: 3px;
        }
        .cert-preview-box {
            background: #f0f0f1;
            border: 2px solid #c3c4c7;
            border-radius: 4px;
            padding: 30px;
            margin-top: 20px;
        }
        .cert-preview-signatures {
            display: flex;
            gap: 60px;
            justify-content: center;
        }
        .cert-preview-sig-item {
            text-align: center;
            min-width: 250px;
        }
        .cert-preview-sig-image {
            margin: 15px 0;
            min-height: 60px;
        }
        .cert-preview-sig-line {
            margin: 10px 0;
            font-family: monospace;
        }
        .cert-preview-sig-name {
            font-weight: 600;
            color: #2271b1;
            margin-top: 5px;
        }
        /* Toggle Switch */
        .switch {
          position: relative;
          display: inline-block;
          width: 50px;
          height: 24px;
          vertical-align: middle;
        }
        .switch input { 
          opacity: 0;
          width: 0;
          height: 0;
        }
        .slider {
          position: absolute;
          cursor: pointer;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background-color: #ccc;
          -webkit-transition: .4s;
          transition: .4s;
          border-radius: 34px;
        }
        .slider:before {
          position: absolute;
          content: "";
          height: 16px;
          width: 16px;
          left: 4px;
          bottom: 4px;
          background-color: white;
          -webkit-transition: .4s;
          transition: .4s;
          border-radius: 50%;
        }
        input:checked + .slider {
          background-color: #2271b1;
        }
        input:focus + .slider {
          box-shadow: 0 0 1px #2271b1;
        }
        input:checked + .slider:before {
          -webkit-transform: translateX(26px);
          -ms-transform: translateX(26px);
          transform: translateX(26px);
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Upload signature button
        $('.upload-signature-btn').on('click', function(e) {
            e.preventDefault();
            
            var target = $(this).data('target');
            var button = $(this);
            
            var mediaUploader = wp.media({
                title: 'Select Signature Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#cert_' + target + '_signature').val(attachment.url);
                $('#' + target + '-signature-preview').html(
                    '<img src="' + attachment.url + '" alt="Signature" style="max-width: 300px; max-height: 100px; border: 1px solid #ddd; padding: 10px; background: white;">'
                );
                
                // Add remove button if it doesn't exist
                if (!button.next('.remove-signature-btn').length) {
                    button.after(
                        '<button type="button" class="button remove-signature-btn" data-target="' + target + '">' +
                        '<span class="dashicons dashicons-trash"></span> Remove</button>'
                    );
                }
            });
            
            mediaUploader.open();
        });
        
        // Remove signature button (delegated event)
        $(document).on('click', '.remove-signature-btn', function(e) {
            e.preventDefault();
            
            var target = $(this).data('target');
            
            if (confirm('Are you sure you want to remove this signature?')) {
                $('#cert_' + target + '_signature').val('');
                $('#' + target + '-signature-preview').html('<div class="no-signature">No signature uploaded</div>');
                $(this).remove();
            }
        });
    });
    </script>
    <?php
}
