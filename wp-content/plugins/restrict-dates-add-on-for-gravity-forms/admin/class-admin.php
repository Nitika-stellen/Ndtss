<?php
if (!defined('ABSPATH')) {
    exit;
}

class GFRD_Admin_Menu {
    public function __construct() {
        add_filter('admin_footer_text', [$this, 'admin_footer'], 1, 2);
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

        add_action('admin_notices', [$this, 'review_request']);
        add_action('admin_notices', [$this, 'upgrade_notice']);
        add_action('admin_notices', [$this, 'offer_notice']);
        add_action('wp_ajax_gfrd_review_dismiss', [$this, 'gfrd_review_dismiss']);
        add_action('wp_ajax_gfrd_offer_notice_dismiss_action', [$this, 'gfrd_offer_notice_dismiss_action']);
        add_action('wp_ajax_gfrd_upgrade_notice_dismiss_action', [$this, 'gfrd_upgrade_notice_dismiss_action']);
    }

    public function admin_scripts() {
        $current_screen = get_current_screen();
        if (strpos($current_screen->base, 'restrict-dates-for-gravity-forms-pro') === false) {
            return;
        }

        wp_enqueue_style('gfrd_dashboard', GF_RESTRICT_DATES_ADDON_URL . 'assets/css/gfrd_dashboard.css', array(), GF_RESTRICT_DATES_ADDON_VERSION);
        wp_enqueue_script('gfrd_dashboard', GF_RESTRICT_DATES_ADDON_URL . 'assets/js/gfrd_dashboard_script.js', array('jquery'), GF_RESTRICT_DATES_ADDON_VERSION, true);
    }

    public function add_menu() {
        add_submenu_page(
            'options-general.php',
            'Restrict Dates For Gravity Forms',
            'GF Restrict Dates',
            'administrator',
            'restrict-dates-for-gravity-forms-pro',
            [$this, 'gfrd_admin_page']
        );
    }

    public function gfrd_admin_page() {
        echo '<div class="pcafe_spf_dashboard">';
        include_once __DIR__ . '/template/header.php';

        echo '<div id="pcafe_tab_box" class="pcafe_container">';
        include_once __DIR__ . '/template/introduction.php';
        include_once __DIR__ . '/template/usage.php';
        include_once __DIR__ . '/template/help.php';
        include_once __DIR__ . '/template/pro.php';
        include_once __DIR__ . '/template/other-plugins.php';
        echo '</div>';
        echo '</div>';
    }

    public function admin_footer($text) {
        global $current_screen;

        if (! empty($current_screen->id) && strpos($current_screen->id, 'restrict-dates-for-gravity-forms-pro') !== false) {
            $url  = 'https://wordpress.org/support/plugin/restrict-dates-add-on-for-gravity-forms/reviews/?filter=5#new-post';
            $text = sprintf(
                wp_kses(
                    /* translators: $1$s - WPForms plugin name; $2$s - WP.org review link; $3$s - WP.org review link. */
                    __('Thank you for using %1$s. Please rate us <a href="%2$s" target="_blank" rel="noopener noreferrer">&#9733;&#9733;&#9733;&#9733;&#9733;</a> on <a href="%3$s" target="_blank" rel="noopener">WordPress.org</a> to boost our motivation.', 'restrict-dates-add-on-for-gravity-forms'),
                    array(
                        'a' => array(
                            'href'   => array(),
                            'target' => array(),
                            'rel'    => array(),
                        ),
                    )
                ),
                '<strong>Restrict Dates Add-On for Gravity Forms</strong>',
                $url,
                $url
            );
        }

        return $text;
    }

    public function review_request() {
        if (! is_super_admin()) {
            return;
        }

        $time = time();
        $load = false;

        $review = get_option('gfrd_review_status');

        if (! $review) {
            $review_time = strtotime("+15 days", time());
            update_option('gfrd_review_status', $review_time);
        } else {
            if (! empty($review) && $time > $review) {
                $load = true;
            }
        }
        if (! $load) {
            return;
        }

        $this->review();
    }

    public function review() {
        $current_user = wp_get_current_user();
        $nonce = wp_create_nonce('gfrd_review_dismiss_nonce');
?>
        <div class="notice notice-info is-dismissible gfrd_review_notice_wrap" data-nonce="<?php echo esc_attr($nonce); ?>">
            <p>
                <?php
                echo sprintf(
                    /* translators: 1: User display name, 2: Plugin name */
                    esc_html__(
                        'Hey %1$s 👋, I noticed you are using %2$s for a few days — that\'s Awesome! If you feel %2$s is helping your business to grow in any way, could you please do us a BIG favor and give it a 5-star rating on WordPress to boost our motivation?',
                        'restrict-dates-add-on-for-gravity-forms'
                    ),
                    esc_html($current_user->display_name),
                    '<strong>Restrict Dates Addon For Gravity Forms</strong>'
                );
                ?>
            </p>

            <ul style="margin-bottom: 5px">
                <li style="display: inline-block">
                    <a style="padding: 5px 5px 5px 0; text-decoration: none;" target="_blank" href="<?php echo esc_url('https://wordpress.org/support/plugin/restrict-dates-add-on-for-gravity-forms/reviews/?filter=5#new-post') ?>">
                        <span class="dashicons dashicons-external"></span><?php esc_html_e(' Ok, you deserve it!', 'restrict-dates-add-on-for-gravity-forms') ?>
                    </a>
                </li>
                <li style="display: inline-block">
                    <a style="padding: 5px; text-decoration: none;" href="#" class="already_done" data-status="already">
                        <span class="dashicons dashicons-smiley"></span>
                        <?php esc_html_e('I already did', 'restrict-dates-add-on-for-gravity-forms') ?>
                    </a>
                </li>
                <li style="display: inline-block">
                    <a style="padding: 5px; text-decoration: none;" href="#" class="later" data-status="later">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php esc_html_e('Maybe Later', 'restrict-dates-add-on-for-gravity-forms') ?>
                    </a>
                </li>
                <li style="display: inline-block">
                    <a style="padding: 5px; text-decoration: none;" target="_blank" href="<?php echo esc_url('https://pluginscafe.com/support/') ?>">
                        <span class="dashicons dashicons-sos"></span>
                        <?php esc_html_e('I need help', 'restrict-dates-add-on-for-gravity-forms') ?>
                    </a>
                </li>
                <li style="display: inline-block">
                    <a style="padding: 5px; text-decoration: none;" href="#" class="never" data-status="never">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php esc_html_e('Never show again', 'restrict-dates-add-on-for-gravity-forms') ?>
                    </a>
                </li>
            </ul>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $(document).on('click', '.already_done, .later, .never, .notice-dismiss', function(event) {
                    event.preventDefault();
                    var $this = $(this);
                    var status = $this.attr('data-status');
                    var nonce = $this.closest('.gfrd_review_notice_wrap').data('nonce');

                    var data = {
                        action: 'gfrd_review_dismiss',
                        status: status,
                        nonce: nonce
                    };
                    $.ajax({
                        url: ajaxurl,
                        type: 'post',
                        data: data,
                        success: function(data) {
                            $('.gfrd_review_notice_wrap').remove();
                        },
                        error: function(data) {}
                    });
                });
            });
        </script>
        <?php
    }

    public function gfrd_review_dismiss() {
        check_ajax_referer('gfrd_review_dismiss_nonce', 'nonce');

        $status = '';
        if (isset($_POST['status'])) {
            $status = sanitize_text_field(wp_unslash($_POST['status']));
        }

        if ($status == 'already' || $status == 'never') {
            $next_try     = strtotime("+30 days", time());
            update_option('gfrd_review_status', $next_try);
        } else if ($status == 'later') {
            $next_try     = strtotime("+10 days", time());
            update_option('gfrd_review_status', $next_try);
        }
        wp_die();
    }

    public function offer_notice() {
        $nonce = wp_create_nonce('gfrd_offer_dismiss_nonce');
        $ajax_url = admin_url('admin-ajax.php');

        $transient_key = 'gfrd_offer_notice';
        $notice_array = get_transient($transient_key);
        $is_offer_checked = get_transient('gfrd_offer_arrived_notice');

        $allowed_tags = [
            'strong' => [],
            'code' => [],
            'a'      => [
                'href'   => [],
                'title'  => [],
                'target' => [],
                'rel'    => [],
            ],
            'span'   => ['style' => []],
        ];


        if ($notice_array === false) {
            // Fetch from remote only if cache expired
            $endpoint  = 'https://api.pluginscafe.com/wp-json/pcafe/v1/offers?id=2';
            $response  = wp_remote_get($endpoint, array('timeout' => 10));

            if (!is_wp_error($response) && $response['response']['code'] === 200) {
                $notice_array = json_decode($response['body'], true);

                // Save in cache for 3 hours (change as needed)
                set_transient($transient_key, $notice_array, 3 * HOUR_IN_SECONDS);
            }
        }

        if (!empty($notice_array) && isset($notice_array['notice']) && $notice_array['live'] === true && $is_offer_checked === false) {
            $notice_type = $notice_array['notice']['notice_type'] ? $notice_array['notice']['notice_type'] : 'info';
            $notice_class = "notice-{$notice_type}";
        ?>
            <div class="notice <?php echo esc_attr($notice_class); ?> is-dismissible gfrd_offer_notice_wrap" data-ajax-url="<?php echo esc_url($ajax_url); ?>"
                data-nonce="<?php echo esc_attr($nonce); ?>">
                <div class="pcafe_notice_container" style="display: flex;align-items:center;padding:10px 0;justify-content:space-between;gap:15px;">
                    <div class="pcafe_spf_notice_content" style="display: flex;align-items:center;gap:15px;">
                        <?php if ($notice_array['notice']['image']) : ?>
                            <div class="pcafe_notice_img">
                                <img width="90px" src="<?php echo esc_url($notice_array['notice']['image']); ?>" />
                            </div>
                        <?php endif; ?>
                        <div class="pcafe_notice_text">
                            <h3 style="margin:0 0 6px;"><?php echo esc_html($notice_array['notice']['title']); ?></h3>
                            <p><?php echo wp_kses($notice_array['notice']['content'], $allowed_tags); ?></p>
                            <div class="pcafe_notice_buttons" style="display: flex; gap:15px;align-items:center;">
                                <?php if ($notice_array['notice']['show_demo_url'] === true) : ?>
                                    <a href="https://demo.pluginscafe.com/smart-phone-field-for-gravity-forms/" class="button-primary" target="__blank"><?php esc_html_e('Check Demo', 'restrict-dates-add-on-for-gravity-forms'); ?></a>
                                <?php endif; ?>
                                <a href="#" class="gfrd_dismis_api__notice">
                                    <?php esc_html_e('Dismiss', 'restrict-dates-add-on-for-gravity-forms'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php if ($notice_array['notice']['upgrade_btn'] === true) : ?>
                        <div class="pcafe_spf_upgrade_btn">
                            <a href="<?php echo esc_url(rdfgf_fs()->get_upgrade_url()); ?>" style="text-decoration: none;font-size: 15px;background: #7BBD02;color: #fff;display: inline-block;padding: 10px 20px;border-radius: 3px;">
                                <?php echo esc_html($notice_array['notice']['upgrade_btn_text']); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <style>

                </style>
            </div>

            <script>
                jQuery(document).ready(function($) {
                    $(document).on('click', '.gfrd_dismis_api__notice, .notice-dismiss', function(event) {
                        event.preventDefault();
                        const $notice = jQuery(this).closest('.gfrd_offer_notice_wrap');
                        const ajaxUrl = $notice.data('ajax-url');
                        const nonce = $notice.data('nonce');

                        $.ajax({
                            url: ajaxUrl,
                            type: 'post',
                            data: {
                                action: 'gfrd_offer_notice_dismiss_action',
                                nonce: nonce
                            },
                            success: function(response) {
                                $('.gfrd_offer_notice_wrap').remove();
                            },
                            error: function(data) {}
                        });
                    });
                });
            </script>
        <?php

        }
    }
    public function gfrd_offer_notice_dismiss_action() {
        check_ajax_referer('gfrd_offer_dismiss_nonce', 'nonce');
        set_transient('gfrd_offer_arrived_notice', true, 12 * HOUR_IN_SECONDS);
        wp_send_json_success();
    }

    public function upgrade_notice() {
        $upgrade_nonece = wp_create_nonce('upgrade_notice_dismiss_nonce');

        $show = false;
        if (rdfgf_fs()->is_not_paying()) {
            $show = true;
        }

        if ($show && false == get_transient('gfrd_upgrade_notice_time') && current_user_can('install_plugins')) {
        ?>

            <div class="gfrd_upgrade_notice notice notice-info is-dismissible" data-nonce="<?php echo esc_attr($upgrade_nonece); ?>">
                <div class="notice_container">
                    <div class="notice_wrap">
                        <div class="rda_img">
                            <img width="100px" src="<?php echo esc_url(GF_RESTRICT_DATES_ADDON_URL . '/admin/images/logo.svg'); ?>" class="gfrs_logo">
                        </div>
                        <div class="notice-content">
                            <div class="notice-heading">
                                <?php esc_html_e("Hi there, Thanks for using Restrict Dates Addon for Gravity Forms", "restrict-dates-add-on-for-gravity-forms"); ?>
                            </div>
                            <?php esc_html_e("Did you know our PRO version includes the ability to frontend & backend validation, Inline Date Picker, Date Modifier and more features? Check it out!", "restrict-dates-add-on-for-gravity-forms"); ?>
                            <div class="gfrd_review-notice-container">
                                <a href="https://demo.pluginscafe.com/restrict-dates-for-gravity-forms-pro/" class="gfrs_notice-close gfrs_review-notice button-primary" target="_blank">
                                    <?php esc_html_e("See The Demo", "restrict-dates-add-on-for-gravity-forms"); ?>
                                </a>
                                <span class="dashicons dashicons-smiley"></span>
                                <a href="#" class="gfrd_notice_dismiss">
                                    <?php esc_html_e("Dismiss", "restrict-dates-add-on-for-gravity-forms"); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="gfrd_upgrade_btn">
                        <a href="<?php echo esc_url(rdfgf_fs()->get_upgrade_url()); ?>" target="_blank">
                            <?php esc_html_e('Upgrade Now!', 'restrict-dates-add-on-for-gravity-forms'); ?>
                        </a>
                    </div>
                </div>
                <style>
                    .notice_container {
                        display: flex;
                        align-items: center;
                        padding: 10px 0;
                        gap: 15px;
                        justify-content: space-between;
                    }

                    img.gfrs_logo {
                        max-width: 90px;
                    }

                    .notice-heading {
                        font-size: 16px;
                        font-weight: 500;
                        margin-bottom: 5px;
                    }

                    .gfrd_review-notice-container {
                        margin-top: 11px;
                        display: flex;
                        align-items: center;
                    }

                    .gfrd_notice-close {
                        padding-left: 5px;
                    }

                    span.dashicons.dashicons-smiley {
                        padding-left: 15px;
                    }

                    .notice_wrap {
                        display: flex;
                        align-items: center;
                        gap: 15px;
                    }

                    .gfrd_upgrade_btn a {
                        text-decoration: none;
                        font-size: 15px;
                        background: #7BBD02;
                        color: #fff;
                        display: inline-block;
                        padding: 10px 20px;
                        border-radius: 3px;
                        transition: 0.3s;
                    }

                    .gfrd_upgrade_btn a:hover {
                        background: #69a103;
                    }
                </style>
                <script>
                    jQuery(document).ready(function($) {
                        $(document).on('click', '.gfrd_notice_dismiss, .notice-dismiss', function(event) {
                            var admin_url_gfrd = '<?php echo esc_url(admin_url("admin-ajax.php")); ?>';
                            var upgrade_nonce = $(this).closest('.gfrd_upgrade_notice').data('nonce');

                            $.ajax({
                                url: admin_url_gfrd,
                                type: 'post',
                                data: {
                                    action: 'gfrd_upgrade_notice_dismiss_action',
                                    nonce: upgrade_nonce
                                },
                                success: function(response) {
                                    $('.gfrd_upgrade_notice').remove();
                                },
                                error: function(data) {}
                            });

                        });
                    });
                </script>
            </div>

<?php

        }
    }

    public function gfrd_upgrade_notice_dismiss_action() {
        check_ajax_referer('upgrade_notice_dismiss_nonce', 'nonce');
        set_transient('gfrd_upgrade_notice_time', true, 14 * DAY_IN_SECONDS);
        wp_send_json_success();
    }
}


new GFRD_Admin_Menu();
