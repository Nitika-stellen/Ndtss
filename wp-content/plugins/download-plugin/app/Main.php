<?php

namespace DPWAP;

if (!defined('ABSPATH')) {
    exit;
}

use DPWAP\Plugins\Base as pluginBase;
use DPWAP\Themes\Base as themeBase;

class Main
{
    protected static $instance = null;
    public $extensions = array();

    public function __construct()
    {
        $this->addActions();
        $this->loadTextdomain();

        add_action('admin_enqueue_scripts', array($this, 'dpwap_load_common_admin_scripts'));

        // add_action('admin_notices', array($this, 'dpwap_general_admin_notice',));
        add_action('admin_notices', array($this, 'dpwap_general_promote_notice',));
        add_action('wp_ajax_dpwap_dismiss_eventprime_promotion', array($this, 'dpwap_dismiss_eventprime_promotion',));

        $plugins = new pluginBase();
        $plugins->setup();

        $themes = new themeBase();
        $themes->setup();
    }

    public function addActions()
    {
        add_action('admin_init', array($this, 'dpwap_plugin_redirect'));
        add_action('admin_menu', array($this, 'dpwap_load_menus'));
        add_action('wp_ajax_dpwap_dismiss_notice_action', array($this, 'dpwap_dismiss_notice_action'));
        add_action('admin_footer', [$this, 'dpwap_customize_modal']);
        add_action('wp_ajax_dpwap_customize_plugin', [$this, 'submit_customization_request']);
    }


    public function dpwap_customize_plugin()
    {
        // print_r($_POST);
        // die;

        // if (!isset($_POST['security']) || empty($_POST['security']) || !wp_verify_nonce(wp_unslash($_POST['security']), 'customize_plugin_action')) {
        //     wp_send_json_error('Invalid nonce');
        //     return;
        // }
        if (isset($_POST['user_email']) && filter_var($_POST['user_email'], FILTER_VALIDATE_EMAIL)) {
            wp_send_json_success('Valid email');
        } else {
            wp_send_json_error('Invalid email');
        }
        // wp_send_json_success('Valid email');

    }

    public function loadTextdomain()
    {
        load_textdomain('download-plugin', WP_LANG_DIR . '/download-plugin/download_plugin-' . get_locale() . '.mo');
    }

    /**
     * redirect plugin to menu on activation
     */
    public function dpwap_plugin_redirect()
    {
        if (get_option('download_plugin_do_activation_redirect', false)) {
            delete_option('download_plugin_do_activation_redirect');
            wp_redirect(admin_url("admin.php?page=dpwap_plugin"));
            exit;
        }
    }

    public function dpwap_load_menus()
    {
        $dpwap = dpwap_plugin_loaded();
        if (in_array('download-users', $dpwap->extensions)) {
            add_menu_page(__('Download', 'download-plugin'), __('Download', 'download-plugin'), 'manage_options', "dpwap_plugin", array($this, 'dpwap_plugin'), 'dashicons-media-archive', '99');
            // download plugin menu
            add_submenu_page("dpwap_plugin", __('Download Plugins', 'download-plugin'), __('Download Plugins', 'download-plugin'), "manage_options", "dpwap_plugin", array($this, 'dpwap_plugin'));
            // download theme menu
            add_submenu_page("dpwap_plugin", __('Download Themes', 'download-plugin'), __('Download Themes', 'download-plugin'), "manage_options", "dpwap_theme", array($this, 'dpwap_theme'));
            // load all extensions
            // show default download user menu
            if (!in_array('download-users', $dpwap->extensions)) {
                add_submenu_page("dpwap_plugin", __('Download Users', 'download-plugin'), __('Download Users', 'download-plugin'), "manage_options", "dpwap_users", array($this, 'duwap_users_check'));
            }
            // show default download bbPress menu
            /*if ( !in_array( 'download-bbpress-integration', $dpwap->extensions ) ) {
                add_submenu_page( "dpwap_plugin", __('bbPress', 'download-plugin'), __('bbPress', 'download-plugin'), "manage_options", "dpwap_bbpress", array( $this, 'duwap_bbpress_check' ) );
            }*/
        }

        do_action('dpwap_downlad_plugin_menus');

        // Enqueue the JavaScript file
        wp_enqueue_script('customize-modal', plugin_dir_url(__DIR__) . '/assets/js/customize-modal.js', array('jquery'), null, true);

        // Localize script to pass AJAX URL
        wp_localize_script('customize-modal', 'ajax_object', array('ajaxurl' => admin_url('admin-ajax.php')));
        // Enqueue the CSS file
        wp_enqueue_style('customize-modal', plugin_dir_url(__DIR__) . '/assets/css/customize-modal.css');
    }

    public function dpwap_plugin()
    {
        $plugin_info_file = DPWAP_DIR . DS . 'app' . DS . 'Plugins' . DS . 'templates' . DS . 'dpwap_plugin_info.php';
        include($plugin_info_file);
        // Add the modal HTML
    }

    public function dpwap_customize_modal()
    {
?>
        <div id="dtwap-customizeModal" style="display:none;">
            <div class="dpmodal-content">
                <span class="dtwap-close-button" onclick="handleCloseButtonClick()"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
                        <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z" />
                    </svg></span>
                <script>
                    function handleCloseButtonClick() {
                        // Close logic here (if any)
                        location.reload(); // Refresh the page
                    }
                </script>
                <div class="modal-logo-text">
                    <h1><?php esc_html_e("Download Plugin", "download-plugin") ?></h1>
                    <span> <img src="<?php echo plugin_dir_url(__DIR__) . 'assets/images/mg-logo.svg'; ?>" alt="Success Icon" class="response-icon" width="100px"></span>
                </div>
                <h2><?php esc_html_e("Customize Your Plugin to Match Your Needs", "download-plugin") ?></h2>
                <p id="p3"><?php esc_html_e("Whether you need additional features, design changes, or integrations, our team will tailor the plugin to your exact requirements.", 'download-plugin') ?></p>
                <form id="dtwap-customizeForm" method="post">
                    <?php wp_nonce_field('customize_plugin_action', 'customize_plugin_nonce'); ?>

                    <label for="pluginSelect">Select Plugin:</label>
                    <select id="pluginSelect" name="plugin" required>
                        <?php
                        $active_plugins = get_option('active_plugins');
                        $all_plugins = get_plugins();
                        foreach ($active_plugins as $plugin_file) {
                            if (isset($all_plugins[$plugin_file])) {
                                echo '<option value="' . esc_attr($all_plugins[$plugin_file]['Name']) . '">' . esc_html($all_plugins[$plugin_file]['Name']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <label for="email"><?php esc_html_e("Email Address:", "download-plugin") ?> </label>
                    <input type="email" id="email" name="email">
                    <label for="customizationType"><?php esc_html_e("Details:", "download-plugin") ?></label>
                    <textarea id="customizationType" name="customizationType" placeholder="Describe Your Customization Needs"></textarea>
                    <div class="dp-button-block">
                        <button type="submit" class="button button-primary" id="dpwap-submit"><?php esc_html_e("Submit", 'download-plugin') ?></button>
                        <span class="spinner is-active" style="display:none;" aria-hidden="true"></span>
                    </div>
                    <span class="dtwap-close-button"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
                            <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z" />
                        </svg></span>
                </form>
                <div id="formResponse" style="display:none;">
                    <div id="successResponse" style="display:none;">
                        <img src="<?php echo plugin_dir_url(__DIR__) . 'assets/images/success-icon.svg'; ?>" alt="Success Icon" class="response-icon">
                        <h2><?php esc_html_e("Request Submitted!", "download-plugin") ?></h2>
                        <p><?php esc_html_e("Thank you for your request! Our team will review it and respond within 12-24 hours. Please check your spam folder if you don’t see our email.", "download-plugin") ?></p>
                        <p>You can also track your tickets directly on our Helpdesk at <a href="https://metagauss.com/customization-help/" target="_blank">https://metagauss.com/customization-help/</a>, where you can add additional details, images, or files as needed.</p>
                    </div>
                    <div id="failureResponse" style="display:none;">
                        <img src="<?php echo plugin_dir_url(__DIR__) . 'assets/images/failure-icon.svg'; ?>" alt="Failure Icon" class="response-icon">
                        <h2><?php esc_html_e("Submission Failed", "download-plugin") ?></h2>
                        <p>Something went wrong. Please try again or create a ticket manually at <a href="https://metagauss.com/customization-help/" target="_blank" rel="noopener noreferrer">https://metagauss.com/customization-help/</a>.</p>
                        <label for="userRequirements"><?php esc_html_e("Your Requirements:", "download-plugin") ?></label>
                        <textarea id="userRequirements" readonly></textarea>
                        <button id="copyButton" class="button button-secondary">Copy</button>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    function submit_customization_request()
    {
        // if (isset($_POST['security']) && !wp_verify_nonce(wp_unslash($_POST['security']), 'customize_plugin_action')) {
        //     wp_send_json_error(array('message' => 'valid nonce.'));
        //     return;
        // }
        // Check if the current user has the necessary permission
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'download-plugin')));
            return;
        }

        if (!isset($_POST['security']) || empty($_POST['security']) || !wp_verify_nonce(wp_unslash($_POST['security']), 'customize_plugin_action')) {
            wp_send_json_error(array('message' => 'Invalid nonce.'));
            return;
        }
        $user_email = isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '';
        if (!isset($_POST['user_email']) || !filter_var($_POST['user_email'], FILTER_VALIDATE_EMAIL)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
            return;
        }

        // Check if customization type is provided
        $customization_type = isset($_POST['customizationType']) ? sanitize_textarea_field(wp_unslash($_POST['customizationType'])) : '';
        if (empty($_POST['customizationType'])) {
            wp_send_json_error(array('message' => esc_html__('Please provide details about your customization request.', 'download-plugin')));
            return;
        }

        // Prepare email details
        $to = 'support@metagauss.com';
        $subject = 'WordPress Support Request';
        $user_email = sanitize_email($_POST['user_email']);
        $plugin = sanitize_text_field($_POST['plugin_select']);
        $customization_type = sanitize_textarea_field($_POST['customizationType']);

        // Construct the HTML email body
        $message = "
    <html>
    <body>
        <h2>WordPress Support Request</h2>
        <p>You have received a new customization request. Below are the details:</p>
        <table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%;'>
            <tr>
                <th align='left'>Field</th>
                <th align='left'>Submitted Value</th>
            </tr>
            <tr>
                <td>Plugin</td>
                <td>{$plugin}</td>
            </tr>
            <tr>
                <td>Email</td>
                <td>{$user_email}</td>
            </tr>
            <tr>
                <td>Customization Needs</td>
                <td>{$customization_type}</td>
            </tr>
        </table>
    </body>
    </html>";

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $user_email,
        );

        // Send the email
        if (wp_mail($to, $subject, $message, $headers)) {
            wp_send_json_success(array('message' => 'Your request has been submitted successfully. We will get back to you shortly.'));
        } else {
            wp_send_json_error(array(
                'message' => 'Something went wrong. Please try again or create a ticket manually at https://metagauss.freshdesk.com/support/tickets/new.'
            ));
        }
    }


    public function dpwap_theme()
    {
        $theme_info_file = DPWAP_DIR . DS . 'app' . DS . 'Themes' . DS . 'templates' . DS . 'dpwap_theme_info.php';
        include_once $theme_info_file;
    }

    public function duwap_users_check()
    {
        $users_info_file = DPWAP_DIR . DS . 'app' . DS . 'Users' . DS . 'templates' . DS . 'dpwap_users_info.php';
        include_once $users_info_file;
    }

    public function duwap_bbpress_check()
    {
        $bbpress_info_file = DPWAP_DIR . DS . 'app' . DS . 'bbPress' . DS . 'templates' . DS . 'dpwap_bbpress_info.php';
        include_once $bbpress_info_file;
    }

    public function dpwap_load_common_admin_scripts()
    {
        wp_enqueue_script('dpwap_common_js', DPWAP_URL . 'assets/js/dpwap-common.js', array(), DPWAP_VERSION);
        wp_localize_script('dpwap_common_js', 'admin_vars', array('admin_url' => admin_url(), 'ajax_url' => admin_url('admin-ajax.php'),  'nonce' => wp_create_nonce('dpwap_secure_action')));
        wp_enqueue_style('dpwap_common_css', DPWAP_URL . 'assets/css/dpwap-common.css', array(), DPWAP_VERSION);
    }

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Admin notice
     */
    // public function dpwap_general_admin_notice()
    // {
    //     $dpwap = dpwap_plugin_loaded();
    //     $get_dismiss_option = get_option('dpwap_dismiss_offer_notice', false);
    //     if (empty($dpwap->extensions) && empty($get_dismiss_option)) {
    //         // echo '<div class="dpwap-notice-pre notice notice-info is-dismissible">
    //         //     <p><b>Download Plugin</b> now has add-on for downloading and uploading your website\'s user accounts. <a href="https://metagauss.com/wordpress-users-import-export-plugin/?utm_source=dp_plugin&utm_medium=admin_notice&utm_campaign=download_users_addon" target="_new">Click here </a>to get it now!</p>
    //         // </div>';
    //     }
    // }

    public function dpwap_general_promote_notice()
    {

        $installed_plugins = get_plugins();

        // Check if EventPrime is installed (active or inactive)
        if (class_exists('Eventprime_Event_Calendar_Management') || isset($installed_plugins['eventprime-event-calendar-management/event-prime.php'])) {
            return;
        }

        $notice_dismissed = get_option('dpwap_dismiss_eventprime_promotion', false);

        if ($notice_dismissed) {
            return;
        }

        // Show only on plugin pages or plugins.php
        $install_url = wp_nonce_url(
            self_admin_url('update.php?action=install-plugin&plugin=eventprime-event-calendar-management'),
            'install-plugin_eventprime-event-calendar-management'
        );


    ?>
        <div class="notice notice-info is-dismissible dpwap-dismissible" id="dpwap_eventprime_promotion">
           
            <div class="dpwap_eventprime_promotion-wrap">
                     
                         <div class="dpwap_eventprime_promotion-text">
                             <span>
                                <a href="<?php echo esc_url($install_url); ?>"
                                   class="button button-primary install-eventprime"
                                   aria-label="<?php esc_attr_e('Install EventPrime Plugin', 'download-plugin'); ?>"
                                   rel="noopener noreferrer">
                                       <?php esc_html_e('Click here', 'download-plugin'); ?>
                                </a>
                               </span>
                             
                               <div><?php esc_html_e('to add an events calendar to your site. From the team behind', 'download-plugin'); ?> <strong><?php esc_html_e('Download Plugin', 'download-plugin'); ?></strong>.</div>                         </div>
                </div>
        </div>

<style>
    .dpwap_eventprime_promotion-wrap {
    width: fit-content;
    padding: 10px 0px;
}

.dpwap_eventprime_promotion-text {
    display: flex;
    align-items: center;
    gap: 10px;
}
</style>
<?php

    }

    // AJAX handler for dismissing notice
    public function dpwap_dismiss_eventprime_promotion()
    {
        check_ajax_referer('dpwap_secure_action', 'nonce');
        if (current_user_can('manage_options')) {
            update_option('dpwap_dismiss_eventprime_promotion', true);
        }
        wp_die();
    }


    /**
     * Hide admin notice
     */
    public function dpwap_dismiss_notice_action()
    {
        add_option('dpwap_dismiss_offer_notice', true);
        wp_send_json_success('Notice Dismissed');
    }
}


