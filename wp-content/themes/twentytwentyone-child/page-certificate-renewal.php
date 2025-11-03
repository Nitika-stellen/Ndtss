<?php
/**
 * Template Name: Certificate Renewal Page
 * 
 * @package CertificateRenewal
 */

get_header(); ?>

<div class="certificate-renewal-page">
    <div class="container">
        <?php
        // Check if user is logged in
        if (!is_user_logged_in()) {
            echo '<div class="login-required">';
            echo '<h2>Login Required</h2>';
            echo '<p>You must be logged in to access the certificate renewal system.</p>';
            echo '<a href="' . wp_login_url(get_permalink()) . '" class="btn btn-primary">Login</a>';
            echo '</div>';
        } else {
            // Get user data and pre-fill form
            $current_user = wp_get_current_user();
            $user_meta = get_user_meta($current_user->ID);
            
            // Get certificate details from URL parameters if available
            $cert_number = isset($_GET['cert_number']) ? sanitize_text_field($_GET['cert_number']) : '';
            $method = isset($_GET['method']) ? sanitize_text_field($_GET['method']) : ($user_meta['method'][0] ?? '');
            $level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : ($user_meta['level'][0] ?? '');
            $sector = isset($_GET['sector']) ? sanitize_text_field($_GET['sector']) : ($user_meta['sector'][0] ?? '');
            $renew_method = isset($_GET['renew_method']) ? sanitize_text_field($_GET['renew_method']) : 'cpd';
            
            // Display the renewal form
            echo do_shortcode('[certificate_renewal_form]');
            
            // Pre-fill form with JavaScript if parameters are provided
            if ($cert_number || $method || $level || $sector) {
                ?>
                <script>
                jQuery(document).ready(function($) {
                    <?php if ($cert_number): ?>
                    $('#previous_cert_number').val('<?php echo esc_js($cert_number); ?>');
                    <?php endif; ?>
                    
                    <?php if ($method): ?>
                    $('#method').val('<?php echo esc_js($method); ?>');
                    <?php endif; ?>
                    
                    <?php if ($level): ?>
                    $('#level').val('<?php echo esc_js($level); ?>');
                    <?php endif; ?>
                    
                    <?php if ($sector): ?>
                    $('#sector').val('<?php echo esc_js($sector); ?>');
                    <?php endif; ?>
                    
                    <?php if ($renew_method === 'exam'): ?>
                    $('input[name="renewal_type"][value="exam"]').prop('checked', true).trigger('change');
                    <?php endif; ?>
                });
                </script>
                <?php
            }
        }
        ?>
    </div>
</div>

<style>
.certificate-renewal-page {
    padding: 40px 0;
    background: #f8f9fa;
    min-height: 70vh;
}

.certificate-renewal-page .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.login-required {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.login-required h2 {
    color: #2c3e50;
    margin-bottom: 20px;
}

.login-required p {
    color: #7f8c8d;
    margin-bottom: 30px;
    font-size: 16px;
}

.login-required .btn {
    padding: 12px 30px;
    background: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-weight: 600;
    transition: background 0.3s ease;
}

.login-required .btn:hover {
    background: #2980b9;
}
</style>

<?php get_footer(); ?>
