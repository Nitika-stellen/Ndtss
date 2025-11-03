<?php
/**
 * Event Functions - Core event functionality
 * Handles event pricing, registration, and basic operations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add member price meta box to events
 */
function event_add_member_price_meta_box() {
    add_meta_box(
        'member_price_meta_box',
        'Member Price',
        'event_display_member_price_meta_box',
        'tribe_events',
        'side',
        'low'
    );
}

/**
 * Display member price meta box
 */
function event_display_member_price_meta_box($post) {
    wp_nonce_field('event_member_price_nonce', 'event_member_price_nonce_field');
    
    $member_price = get_post_meta($post->ID, 'member_price', true);
    ?>
    <p>
        <label for="member_price"><?php esc_html_e('Price for Members', 'textdomain'); ?>:</label><br />
        <input type="number" id="member_price" name="member_price" 
               value="<?php echo esc_attr($member_price); ?>" 
               step="0.01" min="0" style="width:100%;" />
        <span class="description">
            <?php esc_html_e('Enter a price specifically for members. Leave blank if not applicable.', 'textdomain'); ?>
        </span>
    </p>
    <?php
}

/**
 * Save member price meta box
 */
function event_save_member_price($post_id) {
    // Verify nonce
    if (!isset($_POST['event_member_price_nonce_field']) || 
        !wp_verify_nonce($_POST['event_member_price_nonce_field'], 'event_member_price_nonce')) {
        return;
    }
    
    // Check if this is an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Check if this is the correct post type
    if (get_post_type($post_id) !== 'tribe_events') {
        return;
    }
    
    // Save member price
    if (isset($_POST['member_price'])) {
        $member_price = sanitize_text_field($_POST['member_price']);
        $member_price = is_numeric($member_price) ? floatval($member_price) : 0;
        update_post_meta($post_id, 'member_price', $member_price);
        
        event_log_info('Member price updated', [
            'event_id' => $post_id,
            'member_price' => $member_price
        ]);
    } else {
        update_post_meta($post_id, 'member_price', 0);
    }
}

/**
 * Modify event cost based on user membership
 */
function event_modify_tribe_event_cost($cost, $event_id) {
    try {
        $non_member_price = get_post_meta($event_id, '_EventCost', true);
        $member_price = get_post_meta($event_id, 'member_price', true);
        
        if (!is_user_logged_in()) {
            return !empty($non_member_price) ? '$' . esc_html($non_member_price) : $cost;
        }
        
        $user = wp_get_current_user();
        if (in_array('member', $user->roles) && !empty($member_price)) {
            return '$' . esc_html($member_price);
        }
        
        return !empty($non_member_price) ? '$' . esc_html($non_member_price) : $cost;
        
    } catch (Exception $e) {
        event_log_error('Error modifying event cost', [
            'event_id' => $event_id,
            'error' => $e->getMessage()
        ]);
        return $cost;
    }
}

/**
 * Handle event form submission
 */
function event_handle_form_submission($entry, $form) {
    try {
        $user_id = get_current_user_id();
        $event_id = rgar($entry, 'source_id', 0);
        
        event_log_info('Event form submission started', [
            'form_id' => $form['id'],
            'entry_id' => $entry['id'],
            'user_id' => $user_id,
            'event_id' => $event_id
        ]);
        
        if (!$user_id || !$event_id) {
            throw new Exception('Invalid user or event ID');
        }
        
        // Store event registration data
        $registered_events = get_user_meta($user_id, 'registered_event_ids', false);
        if (!in_array($event_id, $registered_events)) {
            add_user_meta($user_id, 'registered_event_ids', $event_id);
        }
        
        // Store entry ID for this event
        update_user_meta($user_id, 'paid_event_form_entry_' . $event_id, $entry['id']);
        
        // Set initial approval status
        update_user_meta($user_id, 'event_' . $event_id . '_approval_status', 'pending');
        
        // Send notification emails
        event_send_registration_notifications($user_id, $event_id, $entry);
        
        event_log_info('Event form submission completed', [
            'user_id' => $user_id,
            'event_id' => $event_id,
            'entry_id' => $entry['id']
        ]);
        
    } catch (Exception $e) {
        event_log_error('Event form submission failed', [
            'form_id' => $form['id'],
            'entry_id' => $entry['id'],
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Send registration notifications
 */
function event_send_registration_notifications($user_id, $event_id, $entry) {
    try {
        $user_info = get_userdata($user_id);
        $event_name = get_the_title($event_id);
        $admin_email = get_option('admin_email');
        
        // User notification
        $user_subject = 'Event Registration Confirmation - ' . $event_name;
        $user_message = event_get_email_template('user_registration', [
            'user_name' => $user_info->display_name,
            'event_name' => $event_name,
            'event_id' => $event_id
        ]);
        
        $user_sent = wp_mail($user_info->user_email, $user_subject, $user_message);
        
        if ($user_sent) {
            event_log_info('User registration email sent', [
                'user_id' => $user_id,
                'event_id' => $event_id,
                'user_email' => $user_info->user_email
            ]);
        } else {
            event_log_error('Failed to send user registration email', [
                'user_id' => $user_id,
                'event_id' => $event_id,
                'user_email' => $user_info->user_email
            ]);
        }
        
        // Admin notification
        $admin_subject = 'New Event Registration - ' . $event_name;
        $admin_message = event_get_email_template('admin_registration', [
            'user_name' => $user_info->display_name,
            'user_email' => $user_info->user_email,
            'event_name' => $event_name,
            'event_id' => $event_id,
            'entry_id' => $entry['id']
        ]);
        
        $admin_sent = wp_mail($admin_email, $admin_subject, $admin_message);
        
        if ($admin_sent) {
            event_log_info('Admin registration email sent', [
                'user_id' => $user_id,
                'event_id' => $event_id,
                'admin_email' => $admin_email
            ]);
        } else {
            event_log_error('Failed to send admin registration email', [
                'user_id' => $user_id,
                'event_id' => $event_id,
                'admin_email' => $admin_email
            ]);
        }
        
    } catch (Exception $e) {
        event_log_error('Failed to send registration notifications', [
            'user_id' => $user_id,
            'event_id' => $event_id,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Check if user can join event
 */
function event_can_user_join($user_id, $event_id) {
    try {
        // Check if event exists
        if (!get_post($event_id) || get_post_type($event_id) !== 'tribe_events') {
            return false;
        }
        
        // Check if event has passed
        $event_end_date = tribe_get_end_date($event_id, false, 'Y-m-d H:i:s');
        if ($event_end_date && current_time('Y-m-d H:i:s') > $event_end_date) {
            return false;
        }
        
        // Check if user is already registered
        $registered_events = get_user_meta($user_id, 'registered_event_ids', false);
        if (in_array($event_id, $registered_events)) {
            $approval_status = get_user_meta($user_id, 'event_' . $event_id . '_approval_status', true);
            return $approval_status === 'rejected';
        }
        
        return true;
        
    } catch (Exception $e) {
        event_log_error('Error checking if user can join event', [
            'user_id' => $user_id,
            'event_id' => $event_id,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Get event registration status for user
 */
function event_get_user_registration_status($user_id, $event_id) {
    try {
        $registered_events = get_user_meta($user_id, 'registered_event_ids', false);
        
        if (!in_array($event_id, $registered_events)) {
            return 'not_registered';
        }
        
        $approval_status = get_user_meta($user_id, 'event_' . $event_id . '_approval_status', true);
        return $approval_status ?: 'pending';
        
    } catch (Exception $e) {
        event_log_error('Error getting user registration status', [
            'user_id' => $user_id,
            'event_id' => $event_id,
            'error' => $e->getMessage()
        ]);
        return 'error';
    }
}

/**
 * Get event attendees with caching
 */
function event_get_attendees_cached($event_id) {
    $cache_key = 'event_attendees_' . $event_id;
    $cached = wp_cache_get($cache_key, 'events');
    
    if (false === $cached) {
        $cached = event_get_attendees($event_id);
        wp_cache_set($cache_key, $cached, 'events', 3600); // 1 hour cache
    }
    
    return $cached;
}

/**
 * Get event attendees
 */
function event_get_attendees($event_id) {
    global $wpdb;
    
    try {
        $query = $wpdb->prepare("
            SELECT DISTINCT u.ID, u.display_name, u.user_email,
                   um1.meta_value as check_in_time,
                   um2.meta_value as cpd_points,
                   um3.meta_value as approval_status
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id 
                AND um1.meta_key = %s
            LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id 
                AND um2.meta_key = %s
            LEFT JOIN {$wpdb->usermeta} um3 ON u.ID = um3.user_id 
                AND um3.meta_key = %s
            WHERE um.meta_key = 'registered_event_ids' 
            AND um.meta_value = %s
            ORDER BY um1.meta_value DESC
        ", 
            'event_' . $event_id . '_check_in_time',
            'event_' . $event_id . '_cpd_points',
            'event_' . $event_id . '_approval_status',
            $event_id
        );
        
        return $wpdb->get_results($query);
        
    } catch (Exception $e) {
        event_log_error('Error getting event attendees', [
            'event_id' => $event_id,
            'error' => $e->getMessage()
        ]);
        return [];
    }
}

/**
 * Clear event cache
 */
function event_clear_cache($event_id = null) {
    if ($event_id) {
        wp_cache_delete('event_attendees_' . $event_id, 'events');
    } else {
        wp_cache_flush_group('events');
    }
}


