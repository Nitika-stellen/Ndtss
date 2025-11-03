<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="renew-container">
    <?php
    $method = isset($_GET['method']) ? sanitize_text_field($_GET['method']) : '';
    $name   = isset($_GET['name']) ? sanitize_text_field($_GET['name']) : '';
    $dob    = isset($_GET['dob']) ? sanitize_text_field($_GET['dob']) : '';
    $level  = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
    $sector = isset($_GET['sector']) ? sanitize_text_field($_GET['sector']) : '';

    // Show method selection if no method specified
    if (!$method) {
        include get_stylesheet_directory() . '/renew/templates/renew-options.php';
    } else {
        // Show both method selection and form on same page
        include get_stylesheet_directory() . '/renew/templates/renew-options.php';
        
        echo '<div class="renew-form-section">';
        if (strtoupper($method) === 'CPD') {
            include get_stylesheet_directory() . '/renew/templates/renew-form-cpd.php';
        } elseif (strtoupper($method) === 'EXAM') {
            echo '<div class="exam-form-wrapper">';
            echo '<h3>Renewal by Examination</h3>';
            echo do_shortcode('[gravityform id="31" title="true" description="false" ajax="true"]');
            echo '</div>';
        }
        echo '</div>';
    }
    ?>
</div>


