<?php
if (! defined('ABSPATH')) exit; // Exit if accessed directly
global $yeekit_document_addons;
if (!class_exists('Yeekit_Document_Addons')) {
    class Yeekit_Document_Addons
    {
        public $data = array();
        function __construct()
        {
            add_action('admin_menu', array($this, "add_menu"), 9999);
            add_action('admin_enqueue_scripts', array($this, 'add_js'));
            add_filter("fluentform_global_addons", array($this, "fluentform_global_addons"));
            add_action('wp_ajax_yeekit_dismiss_noty', array($this, 'dismiss_noty'));
            add_action('admin_notices', array($this, "add_banner"));
            add_action('elementor/element/form/section_form_options/after_section_end', array($this, 'elementor_addons'));
            if (isset($_GET["page"]) && $_GET["page"] == "ninja-forms") {
                add_action('admin_init', array($this, "add_ninja_form"));
            }
            add_action('elementor/editor/after_enqueue_styles', array($this, "after_register_styles"));
        }
        function after_register_styles()
        {
            $custom_css = "
           .yeekit_addons_list{
               background: #fff;
                padding: 15px 10px;
                border-radius: 5px;
           }
            .yeekit_addons_list li {
                color: #444444;
                display: block;
                font-size: 16px;
                line-height: 18px;
                margin: 0;
                outline: 0;
                padding: 5px 0;
                position: relative;
                text-decoration: none;
                opacity: 0.8;
                position: relative;
                color: #fff;
                text-align: center;
               background: #e0e0e0;
                margin-bottom: 10px;
            }
            .yeekit_addons_list li img {
                magin-top: 5px;
                border-radius: 5px 5px 0 0;
            }
            .yeekit_addons_list li span {
                color: #444;
            }
            .yeekit_addons_list li:hover,.yeekit_addons_list li.active {
                opacity: 1;
            }
            .yeekit_addons_list li .yee-install {
                background: #ffffff;
                position: absolute;
                right: 10px;
                top: 10px;
                border: 1px solid #ddd;
                padding: 5px 10px;
                border-radius: 3px;
                text-decoration: none;
                font-size: 12px;
            }
            .yeekit_addons_list li .yee-pro {
                background: #f18500;
                color: #fff;
                position: absolute;
                left: 10px;
                top: 10px;
                border: 1px solid #ddd;
                padding: 5px 10px;
                border-radius: 3px;
                text-decoration: none;
                font-size: 12px;
            }
        ";
            wp_add_inline_style('elementor-editor', $custom_css);
        }
        function elementor_addons($element)
        {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
            $element->start_controls_section(
                'section_yeekit_addons',
                [
                    'label' => __('Forms Add-ons', 'text-domain'),
                    'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                ]
            );
            $datas = $this->get_addons("elementor");
            $html = '';
            foreach ($datas as $data) {
                if (wp_http_validate_url($data["download"])) {
                    $dl = $data["download"];
                } else {
                    $dl = "https://" . $data["download"];
                }
                $install_url = "";
                if (isset($data["free"]) && $data["free"] != "") {
                    $install_url = wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=' . $data["free"]), 'install-plugin_' . $data["free"]);;
                }
                $class = "";
                if (isset($data["plugin"]) && $data["plugin"] != "") {
                    if (is_plugin_active($data["plugin"])) {
                        $class = "active";
                        continue;
                    }
                }
                $download_html = '<a target="_blank" href="' . $install_url . '" class="yee-install">Install Now – Free</a>';
                if ($install_url == "") {
                    $download_html = "";
                }
                $html .= '<li class="yeekit_addons_list ' . $class . '">
                            <img src="' . esc_url($data["img"]) . '" alt="' . esc_attr($data["name"]) . '" />
                            <span>' . esc_html($data["name"]) . '</span>
                            ' . $download_html . '
                            <a target="_blank" href="' . $dl . '" class="yee-pro">Download</a>
                        </li>';
            }
            $html = '<ul class="yeekit_addons_list">' . $html . '</ul>';
            $element->add_control(
                'yeekit_addons',
                [
                    'type' => \Elementor\Controls_Manager::RAW_HTML,
                    'raw' => $html
                ]
            );
            $element->end_controls_section();
        }
        function set_document_link($data)
        {
            $defaults = array(
                "plugin" => false,
                "pro" => "",
                "notice_id" => "",
                "document" => "#"
            );
            $args = wp_parse_args($data, $defaults);
            $this->data[$args["plugin"]] = $args;
            add_filter('plugin_action_links_' . $args["plugin"], array($this, 'add_action_plugin'));
        }
        function add_action_plugin($links)
        {
            $plugin = current_filter();
            $plugin = str_replace('plugin_action_links_', '', $plugin);
            if (isset($this->data[$plugin])) {
                $item = $this->data[$plugin];

                $mylinks[] = '<a class="color-red" href="' . $item["pro"] . '" target="_blank">Pro Version</a>';
                $mylinks[] = '<a href="https://add-ons.org/supports/" target="_blank">Support</a>';
                $mylinks[] = '<a href="' . $item["document"] . '" target="_blank">Document</a>';

                return array_merge($links, $mylinks);
            }
            return $links;
        }
        function add_banner()
        {
            global $pagenow;
            $admin_pages = array('index.php', 'plugins.php');
            foreach ($this->data as $data) {
                $notice_id = $data["notice_id"];
                $check_notice_id = get_option('_redmuber_item_' . $notice_id);
                if ($check_notice_id != "ok") {
                    if (in_array($pagenow, $admin_pages)) {
                        $check_disable = "";
                        if ($pagenow == "index.php") {
                            if (get_user_meta(get_current_user_id(), "yeeaddons_dismissed_{$notice_id}", true)) {
                                $check_disable = "yes";
                            }
                        }
                        if ($check_disable == "") {
?>
                            <div class="notice notice-warning is-dismissible yeeaddons-s-dismissible" data-id="<?php echo esc_attr($data["notice_id"]) ?>">
                                <p><strong><?php echo esc_attr($data["plugin_name"]) ?>: </strong><?php esc_html_e('Upgrade to pro version: ', 'yeekit'); ?> <a href="<?php echo esc_url($data["pro"]) ?>" target="_blank"><?php echo esc_url($data["pro"]) ?></a></p>
                            </div>
                    <?php
                        }
                    }
                }
            }
        }
        function dismiss_noty()
        {
            check_ajax_referer('yeekit_addons_nonce', 'nonce');
            $id = sanitize_text_field($_POST["id"]);
            update_user_meta(get_current_user_id(), 'yeeaddons_dismissed_' . $id, true);
            wp_send_json_success();
        }
        function add_js()
        {
            wp_enqueue_script('yeekit_list_addons', plugins_url('yeekit.js', __FILE__), array("jquery"), "1.0.0");
            wp_enqueue_style('yeekit_list_addons', plugins_url('yeekit.css', __FILE__), array(), "1.0.0");
            wp_localize_script('yeekit_list_addons', 'yeekit_list_addons', [
                'nonce'    => wp_create_nonce('yeekit_addons_nonce'),
            ]);
        }
        function add_menu()
        {
            add_submenu_page("wpcf7", "contact-form-7 addons", "<span style='color:#f18500'>Add-ons </span><span class='update-plugins count-1'><span class='plugin-count'>36</span></span>", "manage_options", "contact-form-7-addons", array($this, 'page_addons_cf7'), 999);
            add_submenu_page("elementor", "elementor form addons", "<span style='color:#f18500'>Forms Add-ons </span><span class='update-plugins count-1'><span class='plugin-count'>15</span></span>", "manage_options", "elementor-forms-addons", array($this, 'page_addons_elementor'), 999);
            add_submenu_page("fluent_forms", "addons", "<span style='color:#f18500'>Add-ons </span><span class='update-plugins count-1'><span class='plugin-count'>36</span></span>", "manage_options", "fluent_forms-addons", array($this, 'page_addons_fluent_forms'));
            add_submenu_page("formidable", "addons", "<span style='color:#f18500'>Add-ons </span><span class='update-plugins count-1'><span class='plugin-count'>36</span></span>", "manage_options", "formidable-addons", array($this, 'page_addons_formidable'), 999);
            add_submenu_page("quform.dashboard", "addons", "<span style='color:#f18500'>Add-ons </span><span class='update-plugins count-1'><span class='plugin-count'>36</span></span>", "manage_options", "quform.dashboard-addons", array($this, 'page_addons_quform'), 999);
            add_submenu_page("wpforms-overview", "addons", "<span style='color:#f18500'>Add-ons </span><span class='update-plugins count-1'><span class='plugin-count'>9</span></span>", "manage_options", "wpforms.dashboard-addons", array($this, 'page_addons_wpforms'), 999);
            add_filter("http_response", array($this, "http_response_eform"), 10, 3);
            add_submenu_page("edit.php?post_type=yeemail_template", "addons", "<span style='color:#f18500'>Add-ons </span>", "manage_options", "yeemail-addons", array($this, 'page_yeemail'), 999);
        }
        function page_yeemail()
        {
            $this->page_addons("yeemail");
        }
        function page_addons_cf7()
        {
            $this->page_addons("cf7");
        }
        function page_addons_elementor()
        {
            $this->page_addons("elementor");
        }
        function page_addons_fluent_forms()
        {
            $this->page_addons("fluent_forms");
        }
        function page_addons_formidable()
        {
            $this->page_addons("formidable");
        }
        function page_addons_quform()
        {
            $this->page_addons("quform");
        }
        function page_addons_wpforms()
        {
            $this->page_addons("wpforms");
        }
        function fluentform_global_addons($add_ons_ok)
        {
            $datas = $this->get_addons();
            $add_ons = array();
            foreach ($datas as $k => $data) {
                $add_ons[$k] = array(
                    "logo" => $data["img"],
                    "url" => $data["download"],
                    "title" => $data["name"],
                    "description" => $data["des"],
                    "purchase_url" => $data["download"],
                    "category" => "a",
                );
            }
            return array_merge($add_ons, $add_ons_ok);
        }
        function add_ninja_form()
        {
            $saved = get_option('ninja_forms_addons_feed', false);
            $datas = $this->get_addons("ninja_forms");
            $add_ons = array();
            foreach ($datas as $k => $data) {
                $add_ons[] = array(
                    "image" => $data["img"],
                    "url" => $data["download"],
                    "title" => $data["name"],
                    "content" => $data["des"],
                    "link" => $data["download"],
                    "plugin" => "a",
                    "version" => "3.0.1",
                    "categories" => array(
                        array(
                            "name" => "Look &amp; Feel",
                            "slug" => "form-function-design"
                        )
                    )
                );
            }
            update_option("ninja_forms_addons_feed", json_encode($add_ons));
        }
        function http_response_eform($response, $datas, $url)
        {
            $add_ons = array();
            switch ($url) {
                case "https://wpquark.com/wp-json/ipt-api/v1/fsqm/":
                    // eforms
                    $datas = $this->get_addons("eforms");
                    foreach ($datas as $data) {
                        $add_ons[] = array(
                            "image" => $data["img"],
                            "url" => $data["download"],
                            "name" => $data["name"],
                            "description" => $data["des"],
                            "author" => "rednumber",
                            "authorurl" => "https://add-ons.org",
                            "class" => "",
                            "star" => 5,
                            "starnum" => rand(10, 100),
                            "downloaded" => rand(100, 1000),
                            "version" => "2." . rand(10, 100),
                            "compatible" => "4.0",
                            "date" => date("Y-m-d h:i:sa")
                        );
                        $datas_rs = json_decode($response['body'], true);
                        $add_on = $datas_rs["addons"];
                        $datas_rs["addons"] = array_merge($add_ons, $add_on);
                        $response["body"] = json_encode($datas_rs);
                    }
                    break;
                case "https://gravityapi.com/wp-content/plugins/gravitymanager/api.php?op=plugin_browser&page=gf_addons":
                    // gravity form
                    ob_start();
                    ?>
                    <h1><?php esc_html_e("Improve your forms with our premium addons.", "yeekit") ?></h1>
                    <div class="list-addons-container">
                        <?php
                        $datas = $this->get_addons("gravity");
                        foreach ($datas as $data) {
                        ?>
                            <div class="add-ons-box">
                                <img src="<?php echo esc_url($data["img"]) ?>">
                                <h3><?php echo esc_attr($data["name"]) ?></h3>
                                <div class="add-ons-box-content">
                                    <p><?php echo esc_attr($data["des"]) ?></p>
                                    <div class="add-ons-box-actions">
                                        <a href="<?php echo esc_url($data["demo"]) ?>" target="_blank" class="add-ons-box-actions-button-live"><?php esc_html_e("Live Demo", "yeekit") ?></a>
                                        <?php
                                        if (wp_http_validate_url($data["download"])) {
                                            $dl = $data["download"];
                                        } else {
                                            $dl = "https://" . $data["download"];
                                        }
                                        ?>
                                        <a href="<?php echo esc_url($dl) ?>" target="_blank" class="add-ons-box-actions-button-download"><?php esc_html_e("Download", "yeekit") ?></a>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
            <?php
                    $html = ob_get_clean();
                    $response["body"] = $html . $response["body"];
                    break;
                case "http://api.ninjaforms.com/feeds/?fetch=addons":
                    $datas = $this->get_addons("ninja_forms");
                    $add_ons = array();
                    foreach ($datas as $k => $data) {
                        $add_ons[] = array(
                            "image" => $data["img"],
                            "url" => $data["download"],
                            "title" => $data["name"],
                            "content" => $data["des"],
                            "link" => $data["download"],
                            "plugin" => "a",
                            "version" => "3.0.1",
                            "categories" => array(
                                array(
                                    "name" => "Look &amp; Feel",
                                    "slug" => "form-function-design"
                                )
                            )
                        );
                    }
                    $add_on = json_decode($response['body'], true);
                    $response["body"] = json_encode(array_merge($add_ons, $add_on));
                    break;
                default:
                    // code...
                    break;
            }
            return $response;
        }
        function page_addons($addon = "")
        {
            ?>
            <div class="wrap">
                <h2><?php esc_html_e("Improve your forms with our premium addons.", "yeekit") ?></h2>
                <p></p>
                <?php
                switch ($addon) {
                    case "cf7":
                ?>
                        <div class="cf7-container-bundle">
                            <div class="cf7-container-bundle-h">
                                <p><?php esc_html_e("Having a tough time choosing just a few?", "yeekit") ?></p>
                                <p><?php esc_html_e("Bundle and save big with $59", "yeekit") ?></p>
                            </div>
                            <p><?php esc_html_e("This is a special pack including all add-on for contact form 7 issued by us and every released add-on!", "yeekit") ?></p>
                            <h3><?php esc_html_e("Save up to 90%", "yeekit") ?></h3>
                            <p><?php esc_html_e("In fact, purchasing every item singularly you would spend at least $891. Bundle Price – Only $59", "yeekit") ?></p>
                            <a href="https://add-ons.org/plugin/contact-form-7-add-on-bundle-all-in-one/" target="_blank" class="add-ons-box-actions-button-download"><?php esc_html_e("Get Now", "yeekit") ?></a>
                        </div>
                    <?php
                        break;
                    case "wpforms":
                    ?>
                        <div class="cf7-container-bundle">
                            <div class="cf7-container-bundle-h">
                                <p><?php esc_html_e("Having a tough time choosing just a few?", "yeekit") ?></p>
                                <p><?php esc_html_e("Bundle and save big with $49", "yeekit") ?></p>
                            </div>
                            <p>
                                <?php esc_html_e("This is a special pack including all add-on for WPForms issued by us and every released add-on!", "yeekit") ?>
                            </p>
                            <h3><?php esc_html_e("Save up to 80%", "yeekit") ?></h3>
                            <p><?php esc_html_e("In fact, purchasing every item singularly you would spend at least $250. Bundle Price – Only $49", "yeekit") ?></p>
                            <a href="https://add-ons.org/plugin/wpforms-add-on-bundle-all-in-one/" target="_blank" class="add-ons-box-actions-button-download"><?php esc_html_e("Get Now", "yeekit") ?></a>
                        </div>
                    <?php
                        break;
                    case "elementor":
                    ?>
                        <div class="cf7-container-bundle">
                            <div class="cf7-container-bundle-h">
                                <p><?php esc_html_e("Having a tough time choosing just a few?", "yeekit") ?></p>
                                <p><?php esc_html_e("Bundle and save big with $49", "yeekit") ?></p>
                            </div>
                            <p>
                                <?php esc_html_e("This is a special pack including all add-on for Elementor Forms issued by us and every released add-on!", "yeekit") ?>
                            </p>
                            <h3><?php esc_html_e("Save up to 85%", "yeekit") ?></h3>
                            <p><?php esc_html_e("In fact, purchasing every item singularly you would spend at least $350. Bundle Price – Only $49", "yeekit") ?></p>
                            <a href="https://add-ons.org/plugin/elementor-forms-add-on-bundle-all-in-one/" target="_blank" class="add-ons-box-actions-button-download"><?php esc_html_e("Get Now", "yeekit") ?></a>
                        </div>
                <?php
                        break;
                }
                ?>
                <div class="list-addons-container">
                    <?php
                    $datas = $this->get_addons($addon);
                    foreach ($datas as $data) {
                    ?>
                        <div class="add-ons-box">
                            <img src="<?php echo esc_attr($data["img"]) ?>">
                            <h3><?php echo esc_attr($data["name"]) ?></h3>
                            <div class="add-ons-box-content">
                                <p><?php echo esc_attr($data["des"]) ?></p>
                                <div class="add-ons-box-actions">
                                    <a href="<?php echo esc_url($data["demo"]) ?>" target="_blank" class="add-ons-box-actions-button-live"><?php esc_html_e("Live Demo", "yeekit") ?></a>
                                    <?php
                                    if (wp_http_validate_url($data["download"])) {
                                        $dl = $data["download"];
                                    } else {
                                        $dl = "https://" . $data["download"];
                                    }
                                    ?>
                                    <a href="<?php echo esc_url($dl) ?>" target="_blank" class="add-ons-box-actions-button-download"><?php esc_html_e("Download", "yeekit") ?></a>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
    <?php
        }
        function get_addons($add_on = null)
        {
            if (isset($add_on)) {
                $yeekit_addons = get_transient("yeekit_addons_" . $add_on);
                if ($yeekit_addons === false) {
                    $rs = wp_remote_get("https://cdn.add-ons.org/plugins.php?type=" . $add_on);
                    $data = $rs['body'];
                    set_transient("yeekit_addons_" . $add_on, $data, 86400);
                    return json_decode($data, true);
                } else {
                    return json_decode($yeekit_addons, true);
                }
            } else {
                $yeekit_addons = get_transient("yeekit_addons");
                if ($yeekit_addons === false) {
                    $rs = wp_remote_get("https://cdn.add-ons.org/plugins.php");
                    $data = $rs['body'];
                    set_transient("yeekit_addons", $data,  86400);
                    return json_decode($data, true);
                } else {
                    return json_decode($yeekit_addons, true);
                }
            }
        }
    }
    $yeekit_document_addons = new Yeekit_Document_Addons;
}
$yeekit_document_addons->set_document_link(
    array(
        "plugin" => "repeater-for-gravity-forms/repeater-for-gravity-forms.php",
        "pro"=>"https://add-ons.org/plugin/gravity-forms-repeater-fields/",
        "plugin_name"=> "Repeater Field For Gravity Forms",
        "document"=>"https://add-ons.org/document-gravity-forms-repeater-fields/",
        "notice_id"=>1540
    )
);