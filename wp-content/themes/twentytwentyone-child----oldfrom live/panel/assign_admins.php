<?php
add_action('admin_menu', 'register_center_admin_assignment_page');
function register_center_admin_assignment_page() {
  add_submenu_page(
        'edit.php?post_type=exam_center', // parent slug
        'Assign Admins',                  // page title
        'Assign Admins',                  // menu title
        'manage_options',                 // capability (admins only)
        'assign-center-admins',          // menu slug
        'render_center_admin_assignment_page' // callback function
    );

}
function render_center_admin_assignment_page() {
    if (!current_user_can('administrator')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    $centers = get_posts([
        'post_type'   => 'exam_center',
        'numberposts' => -1,
        'orderby'     => 'title',
        'order'       => 'ASC',
    ]);

    $center_admins = get_users(['role' => 'center_admin']);
    $aqb_admins    = get_users(['role' => 'aqb_admin']);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('save_center_admin_assignments')) {

        // --- Save Center Admins ---
        if (isset($_POST['center_admins'])) {
            foreach ($_POST['center_admins'] as $center_id => $admin_ids) {
                $admin_ids = array_map('absint', $admin_ids);
                update_post_meta($center_id, '_center_admin_id', $admin_ids);

                foreach ($admin_ids as $admin_id) {
                    $existing = (array) get_user_meta($admin_id, '_exam_center_center_admin', true);
                    if (!in_array($center_id, $existing, true)) {
                        $existing[] = $center_id;
                        update_user_meta($admin_id, '_exam_center_center_admin', $existing);
                    }
                }
            }

            // Clean up removed center assignments
            foreach ($center_admins as $admin) {
                $new_centers = [];
                foreach ($_POST['center_admins'] as $center_id => $admin_ids) {
                    if (in_array($admin->ID, $admin_ids)) {
                        $new_centers[] = (int) $center_id;
                    }
                }
                update_user_meta($admin->ID, '_exam_center_center_admin', $new_centers);
            }
        }

        // --- Save AQB Admins ---
        if (isset($_POST['aqb_admins'])) {
            foreach ($_POST['aqb_admins'] as $center_id => $admin_ids) {
                $admin_ids = array_map('absint', $admin_ids);
                update_post_meta($center_id, '_aqb_admin_ids', $admin_ids);

                foreach ($admin_ids as $admin_id) {
                    $existing = (array) get_user_meta($admin_id, '_exam_center_aqb_admin', true);
                    if (!in_array($center_id, $existing, true)) {
                        $existing[] = $center_id;
                        update_user_meta($admin_id, '_exam_center_aqb_admin', $existing);
                    }
                }
            }

            // Clean up removed AQB assignments
            foreach ($aqb_admins as $admin) {
                $new_centers = [];
                foreach ($_POST['aqb_admins'] as $center_id => $admin_ids) {
                    if (in_array($admin->ID, $admin_ids)) {
                        $new_centers[] = (int) $center_id;
                    }
                }
                update_user_meta($admin->ID, '_exam_center_aqb_admin', $new_centers);
            }
        }

        echo '<div class="notice notice-success"><p>Assignments saved successfully.</p></div>';
    }

    // --- Admin Page UI ---
    ?>
    <div class="wrap">
        <h1>Assign Admins to Exam Centers</h1>
        <form method="post">
            <?php wp_nonce_field('save_center_admin_assignments'); ?>

            <?php foreach ($centers as $center) : ?>
                <div style="margin-bottom: 25px; padding: 15px; border: 1px solid #ccc; background: #f9f9f9;">
                    <h2><?php echo esc_html($center->post_title); ?></h2>

                    <!-- Center Admins -->
                    <h4 style="margin-bottom: 5px;">Center Admins</h4>
                    <ul style="margin: 0 0 15px 0; padding-left: 20px;">
                        <?php
                        $assigned_center_admins = (array) get_post_meta($center->ID, '_center_admin_id', true);
                        foreach ($center_admins as $admin) :
                        ?>
                            <li>
                                <label>
                                    <input type="checkbox"
                                           name="center_admins[<?php echo esc_attr($center->ID); ?>][]"
                                           value="<?php echo esc_attr($admin->ID); ?>"
                                           <?php checked(in_array($admin->ID, $assigned_center_admins)); ?>>
                                    <?php echo esc_html($admin->display_name . ' (' . $admin->user_email . ')'); ?>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- AQB Admins -->
                    <h4 style="margin-bottom: 5px;">AQB Admins</h4>
                    <ul style="margin: 0 0 15px 0; padding-left: 20px;">
                        <?php
                        $assigned_aqb_admins = (array) get_post_meta($center->ID, '_aqb_admin_ids', true);
                        foreach ($aqb_admins as $admin) :
                        ?>
                            <li>
                                <label>
                                    <input type="checkbox"
                                           name="aqb_admins[<?php echo esc_attr($center->ID); ?>][]"
                                           value="<?php echo esc_attr($admin->ID); ?>"
                                           <?php checked(in_array($admin->ID, $assigned_aqb_admins)); ?>>
                                    <?php echo esc_html($admin->display_name . ' (' . $admin->user_email . ')'); ?>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>

            <p><input type="submit" class="button button-primary" value="Save Assignments"></p>
        </form>
    </div>
    <?php
}