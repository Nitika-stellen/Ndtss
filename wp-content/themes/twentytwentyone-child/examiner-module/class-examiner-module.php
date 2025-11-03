<?php
class Examiner_Module {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_dashboards']);
    }

    public function register_dashboards() {
        // Only show to examiners or administrators
        if (!current_user_can('examiner') && !current_user_can('administrator')) {
            return;
        }

        add_submenu_page(
            'edit.php?post_type=exam_center',   // Parent menu
            'Add Marks',                        // Page title
            'Add Marks',                        // Menu title
            'examiner',                         // ✅ Custom capability
            'examiner-dashboard',               // Menu slug
            [$this, 'examiner_dashboard']       // Callback function
        );

    }

    public function examiner_dashboard() {
        ob_start();
        include get_stylesheet_directory() . '/examiner-module/examiner-dashboard.php';
    }
}

new Examiner_Module();
