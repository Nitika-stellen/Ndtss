<?php
/**
 * Review Single CPD Entry
 */

if (!defined('ABSPATH')) {
    exit;
}

// Function to get category name from code (avoid redeclare across templates)
if (!function_exists('get_cpd_category_name')) {
    function get_cpd_category_name($code) {
        $categories = array(
            'A1' => 'Performing NDT Activity',
            'A2' => 'Theoretical Training',
            'A3' => 'Practical Training',
            'A4' => 'Delivery of Training',
            'A5' => 'Research Activities',
            '6' => 'Technical Seminar/Paper',
            '7' => 'Presenting Technical Seminar',
            '8' => 'Society Membership',
            '9' => 'Technical Oversight',
            '10' => 'Committee Participation',
            '11' => 'Certification Body Role'
        );
        
        return isset($categories[$code]) ? $categories[$code] : $code;
    }
}

$cpd_manager = CPD_Manager::get_instance();
if (!$cpd_manager->user_can_access_cpd_admin()) {
    wp_die('You do not have permission to access this page.');
}
?>

<div class="wrap">
    <h1>Review CPD Entry</h1>
    
    <div class="cpd-review-container">
        <div class="cpd-review-header">
            <a href="<?php echo admin_url('admin.php?page=cpd-pending-reviews'); ?>" class="button">
                ← Back to Pending Reviews
            </a>
        </div>
        
        <div class="cpd-review-content">
            <div class="review-section">
                <h2>User Information</h2>
                <table class="form-table">
                    <tr>
                        <th>Name:</th>
                        <td><?php echo esc_html($entry->display_name); ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?php echo esc_html($entry->user_email); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="review-section">
                <h2>Activity Details</h2>
                <table class="form-table">
                    <tr>
                        <th>Activity Category:</th>
                        <td>
                            <strong>
                                <?php if (!empty($entry->activity_category)): ?>
                                    <?php echo esc_html(get_cpd_category_name($entry->activity_category)); ?>
                                <?php else: ?>
                                    <?php echo esc_html($entry->activity_title ?: '-'); ?>
                                <?php endif; ?>
                            </strong>
                        </td>
                    </tr>
                    <tr>
                        <th>Activity Date:</th>
                        <td><?php echo esc_html(date('F j, Y', strtotime($entry->activity_date))); ?></td>
                    </tr>
                    <?php if ($entry->description): ?>
                    <tr>
                        <th>Description:</th>
                        <td><?php echo nl2br(esc_html($entry->description)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($entry->points_requested > 0): ?>
                    <tr>
                        <th>Points Requested:</th>
                        <td><?php echo esc_html($entry->points_requested); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($entry->uploaded_file_url): ?>
                    <tr>
                        <th>Document:</th>
                        <td>
                            <a href="<?php echo esc_url($entry->uploaded_file_url); ?>" target="_blank" class="button">
                                View Document
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <div class="review-section">
                <h2>Review & Allocate Points</h2>
                <form id="cpd-review-form" method="post">
                    <table class="form-table">
                        <tr>
                            <th><label for="points_allocated">Points Allocated:</label></th>
                            <td>
                                <input type="number" 
                                       id="points_allocated" 
                                       name="points_allocated" 
                                       step="0.1" 
                                       min="0" 
                                       value="<?php echo esc_attr($entry->points_allocated); ?>" 
                                       class="regular-text" />
                                <p class="description">Enter the points to allocate for this activity.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="status">Status:</label></th>
                            <td>
                                <select id="status" name="status" class="regular-text">
                                    <option value="pending" <?php selected($entry->status, 'pending'); ?>>Pending</option>
                                    <option value="approved" <?php selected($entry->status, 'approved'); ?>>Approved</option>
                                    <option value="rejected" <?php selected($entry->status, 'rejected'); ?>>Rejected</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="review_notes">Review Notes:</label></th>
                            <td>
                                <textarea id="review_notes" 
                                          name="review_notes" 
                                          rows="5" 
                                          class="large-text"><?php echo esc_textarea($entry->review_notes); ?></textarea>
                                <p class="description">Add any notes about this review (optional).</p>
                            </td>
                        </tr>
                    </table>
                    
                    <input type="hidden" name="entry_id" value="<?php echo esc_attr($entry->cpd_id); ?>" />
                    <input type="hidden" name="action" value="cpd_update_points" />
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('cpd_admin_nonce'); ?>" />
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large">Save Review</button>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>
