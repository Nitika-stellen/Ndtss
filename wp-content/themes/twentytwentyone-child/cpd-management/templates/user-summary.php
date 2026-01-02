<?php
/**
 * User CPD Summary
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

$user_id = get_current_user_id();
$cpd_manager = CPD_Manager::get_instance();

// Ensure year is set (default to current year if not set)
if (!isset($year) || empty($year)) {
    $year = date('Y');
}

// Get user entries for the selected year (for display)
$entries = $cpd_manager->get_user_entries($user_id, $year);
$year_points = $cpd_manager->get_user_year_points($user_id, $year);

// Get total points for past 5 years (for renewal/recertification eligibility)
$total_5_years_points = $cpd_manager->get_user_total_points($user_id, null);
$minimum_required = get_option('cpd_minimum_points_required', 150);
$points_needed = max(0, $minimum_required - $total_5_years_points);

// Get current year and calculate 5-year period
$current_year = date('Y');
$five_years_ago = $current_year - 4;

// Group entries by status
$pending_entries = array_filter($entries, function($e) { return $e->status === 'pending'; });
$approved_entries = array_filter($entries, function($e) { return $e->status === 'approved'; });
$rejected_entries = array_filter($entries, function($e) { return $e->status === 'rejected'; });
?>

<div class="cpd-summary-wrapper">
    <div class="cpd-summary-header">
        <div class="year-tabs">
            <ul class="cpd-year-tabs-list">
                <?php
                $current_year = date('Y');
                // Ensure year is set to current year if not already set
                if (!isset($year) || empty($year)) {
                    $year = $current_year;
                }
                for ($i = $current_year; $i >= $current_year - 5; $i--) {
                    $is_active = ($year == $i) ? 'active' : '';
                    echo '<li><a href="#" class="cpd-year-tab ' . $is_active . '" data-year="' . $i . '">' . $i . '</a></li>';
                }
                ?>
            </ul>
        </div>
    </div>
    
    <div class="cpd-eligibility-notice" style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
        <h3 style="margin: 0 0 10px 0; color: #0073aa;">📋 Renewal & Recertification Eligibility</h3>
        <p style="margin: 0; color: #333;">
            <strong>Requirement:</strong> <?php echo esc_html($minimum_required); ?> CPD points over the past 5 years (<?php echo esc_html($five_years_ago); ?> - <?php echo esc_html($current_year); ?>) to be eligible for certificate renewal or recertification.
        </p>
    </div>
    
    <div class="cpd-summary-stats">
        <div class="stat-card">
            <div class="stat-label">Total Points (5 Years)</div>
            <div class="stat-value"><?php echo esc_html(number_format($total_5_years_points, 2)); ?></div>
            <div class="stat-subtitle"><?php echo esc_html($five_years_ago); ?> - <?php echo esc_html($current_year); ?></div>
        </div>
        
        <div class="stat-card" id="cpd-year-points-card">
            <div class="stat-label">Points This Year</div>
            <div class="stat-value" id="cpd-year-points-value"><?php echo esc_html(number_format($year_points, 2)); ?></div>
            <div class="stat-subtitle" id="cpd-year-points-subtitle"><?php echo esc_html($year); ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-label">Minimum Required</div>
            <div class="stat-value"><?php echo esc_html($minimum_required); ?></div>
            <div class="stat-subtitle">Over 5 years</div>
        </div>
        
        <div class="stat-card <?php echo $points_needed > 0 ? 'warning' : 'success'; ?>">
            <div class="stat-label">Points Needed</div>
            <div class="stat-value"><?php echo esc_html(number_format($points_needed, 2)); ?></div>
            <div class="stat-subtitle"><?php echo $points_needed > 0 ? 'To meet requirement' : 'Requirement met!'; ?></div>
        </div>
    </div>
    
    <div class="cpd-summary-progress">
        <h4>Progress Towards Renewal/Recertification (5-Year Total)</h4>
        <div class="progress-bar">
            <?php 
            $progress_percentage = min(100, ($total_5_years_points / $minimum_required) * 100);
            ?>
            <div class="progress-fill" style="width: <?php echo esc_attr($progress_percentage); ?>%;"></div>
        </div>
        <p class="progress-text">
            <?php echo esc_html(number_format($total_5_years_points, 2)); ?> / <?php echo esc_html($minimum_required); ?> points
            (<?php echo esc_html(number_format($progress_percentage, 1)); ?>%)
        </p>
        <?php if ($total_5_years_points >= $minimum_required): ?>
        <p style="color: #46b450; font-weight: 600; margin-top: 10px;">
            ✅ You have met the CPD requirement and are eligible for renewal/recertification!
        </p>
        <?php else: ?>
        <p style="color: #dc3232; font-weight: 600; margin-top: 10px;">
            ⚠️ You need <?php echo esc_html(number_format($points_needed, 2)); ?> more points to meet the requirement for renewal/recertification.
        </p>
        <?php endif; ?>
    </div>
    
    <div class="cpd-entries-list">
        <h3 id="cpd-entries-heading">My CPD Entries - <?php echo esc_html($year); ?></h3>
        <p class="description" id="cpd-entries-description" style="color: #666; margin-bottom: 15px;">
            Showing entries for <?php echo esc_html($year); ?>. Points from this year contribute to your 5-year total.
        </p>
        
        <?php if (empty($entries)): ?>
        <p>No CPD entries for <?php echo esc_html($year); ?>.</p>
        <?php else: ?>
        <table class="cpd-entries-table">
            <thead>
                <tr>
                    <th>Activity Category</th>
                    <th>Date</th>
                    <th>Points</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                <tr>
                <td>
                        <?php 
                        $category_code = $entry->activity_category ?: '';
                        if ($category_code) {
                            $category_name = get_cpd_category_name($category_code);
                            echo '<strong>' . esc_html($category_code) . '</strong> - ' . esc_html($category_name);
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td><?php echo esc_html(date('M j, Y', strtotime($entry->activity_date))); ?></td>
                  
                    <td>
                        <?php if ($entry->points_allocated > 0): ?>
                            <?php echo esc_html($entry->points_allocated); ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo esc_attr($entry->status); ?>">
                            <?php echo esc_html(ucfirst($entry->status)); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($entry->uploaded_file_url): ?>
                        <a href="<?php echo esc_url($entry->uploaded_file_url); ?>" target="_blank" class="button button-small">
                            View Doc
                        </a>
                        <?php endif; ?>
                        <?php if ($entry->status === 'pending'): ?>
                        <button type="button" 
                                class="button button-small delete-entry" 
                                data-entry-id="<?php echo esc_attr($entry->cpd_id); ?>">
                            Delete
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Ensure cpdFrontend is available
    if (typeof cpdFrontend === 'undefined') {
        console.error('cpdFrontend object is not defined. Please check if the CPD frontend script is loaded.');
        return;
    }
    
    // Function to load entries
    function loadEntries(year) {
        var $wrapper = $('.cpd-summary-wrapper');
        
        // Show loading state
        var $entriesList = $wrapper.find('.cpd-entries-list');
        $entriesList.find('table, p:contains("No CPD entries"), p:contains("Loading")').remove();
        $entriesList.append('<p id="cpd-loading-message" style="text-align: center; padding: 20px;">Loading...</p>');
        $('#cpd-year-points-value').text('...');
        
        // Prepare AJAX data
        var ajaxData = {
            action: 'cpd_get_user_entries',
            year: year || '',
            nonce: cpdFrontend.nonce
        };
        
        $.ajax({
            url: cpdFrontend.ajaxUrl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var entries = data.entries || [];
                    var yearPoints = parseFloat(data.year_points || 0);
                    var selectedYear = data.year || year;
                    
                    // Update year points stat
                    $('#cpd-year-points-value').text(yearPoints.toFixed(2));
                    $('#cpd-year-points-subtitle').text(selectedYear);
                    
                    // Update entries list heading and description
                    $('#cpd-entries-heading').text('My CPD Entries - ' + selectedYear);
                    $('#cpd-entries-description').text('Showing entries for ' + selectedYear + '. Points from this year contribute to your 5-year total.');
                    
                    // Update entries table
                    var $entriesList = $wrapper.find('.cpd-entries-list');
                    // Remove existing table, no entries message, and loading message, but keep heading and description
                    $entriesList.find('table, p:contains("No CPD entries"), #cpd-loading-message').remove();
                    
                    if (entries.length === 0) {
                        $entriesList.append('<p>No CPD entries for ' + selectedYear + '.</p>');
                    } else {
                        
                        // Build new table
                        var tableHtml = '<table class="cpd-entries-table"><thead><tr>' +
                            '<th>Activity</th>' +
                            '<th>Date</th>' +
                            '<th>Category</th>' +
                            '<th>Points</th>' +
                            '<th>Status</th>' +
                            '<th>Actions</th>' +
                            '</tr></thead><tbody>';
                        
                        // Category mapping
                        var categoryNames = {
                            'A1': 'Performing NDT Activity',
                            'A2': 'Theoretical Training',
                            'A3': 'Practical Training',
                            'A4': 'Delivery of Training',
                            'A5': 'Research Activities',
                            '6': 'Technical Seminar/Paper',
                            '7': 'Presenting Technical Seminar',
                            '8': 'Society Membership',
                            '9': 'Technical Oversight',
                            '10': 'Committee Participation',
                            '11': 'Certification Body Role'
                        };
                        
                        $.each(entries, function(index, entry) {
                            var activityDate = new Date(entry.activity_date);
                            var formattedDate = activityDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                            var pointsHtml = entry.points_allocated > 0 ? parseFloat(entry.points_allocated).toFixed(2) : '<span class="text-muted">-</span>';
                            var statusBadge = '<span class="status-badge status-' + $('<div>').text(entry.status).html() + '">' + $('<div>').text(entry.status.charAt(0).toUpperCase() + entry.status.slice(1)).html() + '</span>';
                            
                            var actionsHtml = '';
                            if (entry.uploaded_file_url) {
                                var fileUrl = $('<div>').text(entry.uploaded_file_url).html();
                                actionsHtml += '<a href="' + fileUrl + '" target="_blank" class="button button-small">View Doc</a> ';
                            }
                            if (entry.status === 'pending') {
                                actionsHtml += '<button type="button" class="button button-small delete-entry" data-entry-id="' + parseInt(entry.cpd_id) + '">Delete</button>';
                            }
                            
                            var activityTitle = $('<div>').text(entry.activity_title || '-').html();
                            
                            // Get category name
                            var categoryCode = entry.activity_category || '';
                            var categoryDisplay = '-';
                            if (categoryCode) {
                                var categoryName = categoryNames[categoryCode] || categoryCode;
                                categoryDisplay = '<strong>' + $('<div>').text(categoryCode).html() + '</strong> - ' + $('<div>').text(categoryName).html();
                            }
                            var activityCategory = categoryDisplay;
                            
                            tableHtml += '<tr>' +
                                '<td>' + activityTitle + '</td>' +
                                '<td>' + formattedDate + '</td>' +
                                '<td>' + activityCategory + '</td>' +
                                '<td>' + pointsHtml + '</td>' +
                                '<td>' + statusBadge + '</td>' +
                                '<td>' + actionsHtml + '</td>' +
                                '</tr>';
                        });
                        
                        tableHtml += '</tbody></table>';
                        $entriesList.append(tableHtml);
                    }
                } else {
                    alert(response.data.message || 'Error loading entries. Please try again.');
                }
            },
            error: function() {
                alert('Error loading entries. Please try again.');
            }
        });
    }
    
    // Handle year tab clicks
    $('.cpd-year-tab').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Remove active class from all tabs
        $('.cpd-year-tab').removeClass('active');
        // Add active class to clicked tab
        $(this).addClass('active');
        
        var year = $(this).data('year');
        loadEntries(year);
    });
    
    // Bind delete entry handler (for initial page load and dynamically added entries)
    $(document).on('click', '.delete-entry', function() {
        if (!confirm('Are you sure you want to delete this entry?')) {
            return;
        }
        
        var entryId = $(this).data('entry-id');
        var $row = $(this).closest('tr');
        
        $.ajax({
            url: cpdFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cpd_delete_entry',
                entry_id: entryId,
                nonce: cpdFrontend.nonce
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(function() {
                        $(this).remove();
                        // Reload entries for current year after deletion
                        var currentYear = $('.cpd-year-tab.active').data('year') || '';
                        if (currentYear) {
                            loadEntries(currentYear);
                        }
                    });
                } else {
                    alert(response.data.message || cpdFrontend.strings.error);
                }
            }
        });
    });
});
</script>

<style>
.cpd-year-tabs {
    margin-bottom: 30px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-radius: 12px;
    padding: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.06);
    border: 1px solid #e9ecef;
}

.cpd-year-tabs-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: center;
}

.cpd-year-tabs-list li {
    margin: 0;
    padding: 0;
}

.cpd-year-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 14px 28px;
    text-decoration: none;
    color: #495057;
    background: #ffffff;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-weight: 600;
    font-size: 15px;
    position: relative;
    overflow: hidden;
    min-width: 70px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.cpd-year-tab::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    transition: left 0.6s;
}

.cpd-year-tab:hover::before {
    left: 100%;
}

.cpd-year-tab:hover {
    background: #e7f3ff;
    color: #0066cc;
    border-color: #0066cc;
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0,102,204,0.15);
}

.cpd-year-tab.active {
    background: linear-gradient(135deg, #0066cc 0%, #004d99 100%);
    color: #ffffff;
    border-color: #0066cc;
    box-shadow: 0 6px 16px rgba(0,102,204,0.25), inset 0 1px 0 rgba(255,255,255,0.2);
    transform: translateY(-3px);
    z-index: 1;
}

.cpd-year-tab.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 50%;
    transform: translateX(-50%);
    width: 70%;
    height: 4px;
    background: #ffffff;
    border-radius: 4px 4px 0 0;
    box-shadow: 0 -2px 4px rgba(0,0,0,0.1);
}

.cpd-year-tab.active:hover {
    background: linear-gradient(135deg, #0052a3 0%, #003d7a 100%);
    color: #ffffff;
    box-shadow: 0 8px 20px rgba(0,102,204,0.3), inset 0 1px 0 rgba(255,255,255,0.2);
}

/* Add a subtle pulse animation for active tab */
@keyframes pulse {
    0%, 100% {
        box-shadow: 0 6px 16px rgba(0,102,204,0.25), inset 0 1px 0 rgba(255,255,255,0.2);
    }
    50% {
        box-shadow: 0 6px 20px rgba(0,102,204,0.35), inset 0 1px 0 rgba(255,255,255,0.2);
    }
}

.cpd-year-tab.active {
    animation: pulse 2s ease-in-out infinite;
}

@media (max-width: 768px) {
    .cpd-year-tabs {
        padding: 10px;
        border-radius: 10px;
    }
    
    .cpd-year-tabs-list {
        gap: 8px;
    }
    
    .cpd-year-tab {
        padding: 12px 20px;
        font-size: 14px;
        min-width: 60px;
    }
}

@media (max-width: 480px) {
    .cpd-year-tabs {
        padding: 8px;
    }
    
    .cpd-year-tab {
        padding: 10px 16px;
        font-size: 13px;
        min-width: 55px;
    }
}
</style>

