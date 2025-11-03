<?php
/**
 * Schedule reminder emails cron job
 * This function is called by WordPress cron system
 */
function renew_schedule_reminders() {
    if (!wp_next_scheduled('renew_send_reminders')) {
        wp_schedule_event(time(), 'daily', 'renew_send_reminders');
        renew_log_info('Scheduled daily reminder emails');
    }
}
