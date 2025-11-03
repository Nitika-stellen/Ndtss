<?php
function create_exam_appointments_cpt() {
    $labels = array(
        'name' => 'Exam Appointments',
        'singular_name' => 'Exam Appointment',
        'menu_name' => 'Exam Appointments',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Exam Appointment',
        'edit_item' => 'Edit Exam Appointment',
        'new_item' => 'New Exam Appointment',
        'view_item' => 'View Exam Appointment',
        'all_items' => 'All Exam Appointments',
        'search_items' => 'Search Exam Appointments',
        'not_found' => 'No Exam Appointments found',
    );
    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'menu_position' => 6,
        'supports' => array('title', 'editor'),
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-welcome-learn-more', // Menu icon for Exam Appointments
    );
    register_post_type('exam_appointment', $args);
}
add_action('init', 'create_exam_appointments_cpt');

function add_appointment_meta_boxes() {
    add_meta_box(
        'appointment_details',
        'Appointment Details',
        'appointment_meta_box_callback',
        'exam_appointment',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'add_appointment_meta_boxes');

function appointment_meta_box_callback($post) {
    $examiner_id = get_post_meta($post->ID, '_examiner_id', true);
    $candidate_id = get_post_meta($post->ID, '_candidate_id', true);
    $exam_date = get_post_meta($post->ID, '_exam_date', true);

    // Get users with 'examiner' role
    $examiners = get_users(array('role' => 'examiner'));
    // Get all candidates (users)
    $candidates = get_users(array('role' => 'candidate')); // Adjust role if necessary

    ?>
    <p>
        <label for="examiner_id">Select Examiner:</label>
        <select name="examiner_id" id="examiner_id">
            <option value="">Select Examiner</option>
            <?php foreach ($examiners as $examiner) : ?>
                <option value="<?php echo esc_attr($examiner->ID); ?>" <?php selected($examiner_id, $examiner->ID); ?>>
                    <?php echo esc_html($examiner->display_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label for="candidate_id">Select Candidate:</label>
        <select name="candidate_id" id="candidate_id">
            <option value="">Select Candidate</option>
            <?php foreach ($candidates as $candidate) : ?>
                <option value="<?php echo esc_attr($candidate->ID); ?>" <?php selected($candidate_id, $candidate->ID); ?>>
                    <?php echo esc_html($candidate->display_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label for="exam_date">Exam Date:</label>
        <input type="date" name="exam_date" id="exam_date" value="<?php echo esc_attr($exam_date); ?>">
    </p>
    <?php
}

function save_appointment_meta_data($post_id) {
    if (isset($_POST['examiner_id'])) {
        update_post_meta($post_id, '_examiner_id', sanitize_text_field($_POST['examiner_id']));
    }
    if (isset($_POST['candidate_id'])) {
        update_post_meta($post_id, '_candidate_id', sanitize_text_field($_POST['candidate_id']));
    }
    if (isset($_POST['exam_date'])) {
        update_post_meta($post_id, '_exam_date', sanitize_text_field($_POST['exam_date']));
    }
}
add_action('save_post', 'save_appointment_meta_data');

function display_approved_candidates() {
    // Get all approved candidates (modify this logic based on your approval system)
    $approved_candidates = get_users(array(
        'role' => 'candidate', // Assuming 'candidate' role for users
        'meta_key' => 'application_status', // Replace with your actual meta key for approval status
        'meta_value' => 'approved', // Filter by approved status
    ));

    echo '<h2>Approved Candidates</h2>';
    if (!empty($approved_candidates)) {
        echo '<ul>';
        foreach ($approved_candidates as $candidate) {
            echo '<li>' . esc_html($candidate->display_name) . '</li>';
            echo '<a href="' . admin_url('post-new.php?post_type=exam_appointment') . '">Assign Examiner</a>';
        }
        echo '</ul>';
    } else {
        echo 'No approved candidates found.';
    }
}
add_action('admin_menu', function() {
    add_menu_page('Approved Candidates', 'Approved Candidates', 'manage_options', 'approved-candidates', 'display_approved_candidates');
});
