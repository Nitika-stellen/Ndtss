<?php
function custom_template_settings_menu() {
    add_menu_page(
        'Template Manager',
        'Template Manager',
        'manage_options',
        'template-manager',
        'custom_template_settings_page',
        'dashicons-edit-page',
        90
    );
}
add_action('admin_menu', 'custom_template_settings_menu');

function custom_template_settings_page() {
    if (isset($_POST['template_form_submitted']) && check_admin_referer('update_templates_nonce')) {
        update_option('custom_template_admin', wp_kses_post($_POST['admin_template']));
        update_option('custom_template_invigilator', wp_kses_post($_POST['invigilator_template']));
        update_option('custom_template_examiner', wp_kses_post($_POST['examiner_template']));
        update_option('custom_template_user', wp_kses_post($_POST['user_template']));
        echo '<div class="updated"><p>Templates updated.</p></div>';
    }

    $admin = get_option('custom_template_admin', '');
    $invigilator = get_option('custom_template_invigilator', '');
    $examiner = get_option('custom_template_examiner', '');
    $user = get_option('custom_template_user', '');
    ?>
    <div class="wrap">
        <h1>Template Manager</h1>
        <form method="post">
            <?php wp_nonce_field('update_templates_nonce'); ?>
            <h2>Admin Template</h2>
            <textarea name="admin_template" rows="6" style="width:100%;"><?php echo esc_textarea($admin); ?></textarea>
            <h2>Invigilator Template</h2>
            <textarea name="invigilator_template" rows="6" style="width:100%;"><?php echo esc_textarea($invigilator); ?></textarea>
            <h2>Examiner Template</h2>
            <textarea name="examiner_template" rows="6" style="width:100%;"><?php echo esc_textarea($examiner); ?></textarea>
            <h2>User Template</h2>
            <textarea name="user_template" rows="6" style="width:100%;"><?php echo esc_textarea($user); ?></textarea>
            <p><input type="submit" name="template_form_submitted" class="button button-primary" value="Save Templates"></p>
        </form>
    </div>
    <?php
}
