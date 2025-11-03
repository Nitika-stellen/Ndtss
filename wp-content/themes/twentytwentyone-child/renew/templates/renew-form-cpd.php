<?php if (!defined('ABSPATH')) { exit; }
    $user = wp_get_current_user();
    // Get certification details from URL parameters (passed from user profile)
   
    $cert_id = isset($_GET['cert_id']) ? sanitize_text_field($_GET['cert_id']) : '';
    $cert_method = isset($_GET['cert_method']) ? sanitize_text_field($_GET['cert_method']) : '';
    $cert_number = isset($_GET['cert_number']) ? sanitize_text_field($_GET['cert_number']) : '';
    $name   = isset($_GET['name']) ? sanitize_text_field($_GET['name']) : ($user->display_name ?? '');
    $level  = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
    $sector = isset($_GET['sector']) ? sanitize_text_field($_GET['sector']) : '';
    
    // Check if renewal has already been submitted for this certificate
    // Skip check for AJAX requests to avoid conflicts
    // if (!empty($cert_id) && !isset($_GET['ajax_load'])) {
    //     $existing_renewal = renew_check_existing_renewal($user->ID, $cert_number);
    //     if ($existing_renewal) {
    //         echo '<div class="renewal-already-exists">';
    //         echo '<div class="notice notice-info">';
    //         $already_title = (isset($is_recertification) && $is_recertification) ? 'Recertification Already Submitted' : 'Renewal Already Submitted';
    //         $already_text = (isset($is_recertification) && $is_recertification) ? 'recertification' : 'renewal';
    //         echo '<h3><i class="dashicons dashicons-info"></i> ' . esc_html($already_title) . '</h3>';
    //         echo '<p>You have already submitted a ' . esc_html($already_text) . ' for certificate <strong>' . esc_html($cert_number) . '</strong>.</p>';
    //         echo '<p><strong>Status:</strong> ' . ucfirst($existing_renewal['status']) . '</p>';
    //         if (!empty($existing_renewal['submission_date'])) {
    //             echo '<p><strong>Submitted on:</strong> ' . date('d/m/Y', strtotime($existing_renewal['submission_date'])) . '</p>';
    //         }
    //         echo '<p>Please check your user profile for the current status of your ' . esc_html($already_text) . ' application.</p>';
    //         echo '<a href="' . home_url('/user-profile#final-certificate-section') . '" class="button button-primary">View My Certificates</a>';
    //         echo '</div>';
    //         echo '</div>';
    //         echo '<style>';
    //         echo '.renewal-already-exists { max-width: 800px; margin: 40px auto; padding: 20px; }';
    //         echo '.renewal-already-exists .notice { padding: 20px; border-left: 4px solid #0073aa; }';
    //         echo '.renewal-already-exists h3 { margin-top: 0; color: #0073aa; }';
    //         echo '.renewal-already-exists .dashicons { margin-right: 8px; }';
    //         echo '</style>';
    //         return;
    //     }
    // }
?>
<div class="cpd-form-wrapper full-width">
    <div class="form-header">
        <?php $isRec = isset($is_recertification) && $is_recertification; ?>
        <h3><i class="dashicons dashicons-chart-line"></i> <?php echo $isRec ? 'CPD Recertification Form' : 'CPD Renewal Form'; ?></h3>
        <p><?php echo $isRec ? 'Complete your CPD recertification application' : 'Complete your Continuing Professional Development renewal application'; ?></p>
    </div>


    <form id="cpd-renew-form" method="post" enctype="multipart/form-data" class="cpd-form">
        <input type="hidden" name="action" value="submit_cpd_form" />
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('renew_nonce')); ?>" />
        <input type="hidden" name="method" value="<?php echo $isRec ? 'RECERT' : 'CPD'; ?>" />
        <?php if ($cert_id): ?>
        <input type="hidden" name="cert_id" value="<?php echo esc_attr($cert_id); ?>" />
        <?php endif; ?>

        <div class="form-section">
            <h4><i class="dashicons dashicons-admin-users"></i> Personal Information</h4>
            <div class="form-grid">
                <div class="field-group">
                    <label for="name">Full Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo esc_attr($name); ?>" required />
                </div>
                <?php if ($cert_number): ?>
                <div class="field-group">
                    <label for="cert_number">Certificate Number</label>
                    <input type="text" id="cert_number" name="cert_number" value="<?php echo esc_attr($cert_number); ?>" readonly style="background: #f8f9fa;" />
                </div>
                <?php endif; ?>
                <?php if ($cert_method): ?>
                <div class="field-group">
                    <label for="cert_method">Certification Method</label>
                    <input type="text" id="cert_method" name="cert_method" value="<?php echo esc_attr($cert_method); ?>" readonly style="background: #f8f9fa;" />
                </div>
                <?php endif; ?>
                <div class="field-group">
                    <label for="level">Certification Level <span class="required">*</span></label>
                    <input type="text" id="level" name="level" value="<?php echo esc_attr($level); ?>" <?php echo $level ? 'readonly style="background: #f8f9fa;"' : ''; ?> required />
                </div>
                <div class="field-group">
                    <label for="sector">Sector <span class="required">*</span></label>
                    <input type="text" id="sector" name="sector" value="<?php echo esc_attr($sector); ?>" <?php echo $sector ? 'readonly style="background: #f8f9fa;"' : ''; ?> required />
                </div>
            </div>
        </div>

        <div class="form-section cpd-points-section full-width">
            <h4><i class="dashicons dashicons-chart-area"></i> CPD Points by Category (Past 5 Years)</h4>
            <p class="section-description">Enter your CPD points for each category over the past 5 years. Each cell has a maximum limit. Total minimum required: 150 points.</p>
            
            <?php 
            // CPD Categories with their maximum points as per reference table
            $cpd_categories = array(
                'A1' => array(
                    'title' => 'Performing NDT Activity',
                    'description' => '2 points per day, 25 Max per year',
                    'max_points' => 95,
                    'max_per_year' => 25
                ),
                'A2' => array(
                    'title' => 'Theoretical Training',
                    'description' => 'Completion of theoretical training in the method (1 point per day) - 5 Max per year',
                    'max_points' => 15,
                    'max_per_year' => 5
                ),
                'A3' => array(
                    'title' => 'Practical Training',
                    'description' => 'Completion of practical training in the method (2 points per day) - 10 Max per year',
                    'max_points' => 25,
                    'max_per_year' => 10
                ),
                'A4' => array(
                    'title' => 'Delivery of Training',
                    'description' => 'Delivery of practical or theoretical training in NDT (1 point per day) - 15 Max per year (L2 & L3 only)',
                    'max_points' => 75,
                    'max_per_year' => 15
                ),
                'A5' => array(
                    'title' => 'Research Activities',
                    'description' => 'Participation in research activities in NDT field (1 point per week) - 15 Max per year',
                    'max_points' => 60,
                    'max_per_year' => 15
                ),
                '6' => array(
                    'title' => 'Technical Seminar/Paper',
                    'description' => 'Participation to a technical seminar/paper (1 per day) - 2 Max per year',
                    'max_points' => 10,
                    'max_per_year' => 2
                ),
                '7' => array(
                    'title' => 'Presenting Technical Seminar',
                    'description' => 'Presenting a technical seminar/paper (1 per presentation) - 3 Max per year',
                    'max_points' => 15,
                    'max_per_year' => 3
                ),
                '8' => array(
                    'title' => 'Society Membership',
                    'description' => 'Current individual membership in NDT or NDT related society (1 per membership) - 2 Max per year',
                    'max_points' => 5,
                    'max_per_year' => 2
                ),
                '9' => array(
                    'title' => 'Technical Oversight',
                    'description' => 'Technical oversight and mentoring of NDT personnel (2 per mentee) - 10 Max per year',
                    'max_points' => 40,
                    'max_per_year' => 10
                ),
                '10' => array(
                    'title' => 'Committee Participation',
                    'description' => 'Participation in standardization and technical committees (1 per committee) - 4 Max per year',
                    'max_points' => 20,
                    'max_per_year' => 4
                ),
                '11' => array(
                    'title' => 'Certification Body Role',
                    'description' => 'Performing a technical NDT role within a certification body (2 per activity) - 10 Max per year',
                    'max_points' => 40,
                    'max_per_year' => 10
                )
            );
            
            // Current year for calculation
            $current_year = date('Y');
            $years = array();
            for ($i = 4; $i >= 0; $i--) {
                $years[] = $current_year - $i;
            }
            ?>
            
            <div class="cpd-table-container">
                <table class="cpd-points-table" id="cpd-points-table">
                    <thead>
                        <tr>
                            <th class="category-header">S.No.</th>
                            <th class="description-header">Category Description</th>
                            <?php foreach ($years as $index => $year): ?>
                                <th class="year-header">Year <?php echo ($index + 1); ?><br><small>(<?php echo $year; ?>)</small></th>
                            <?php endforeach; ?>
                            <th class="total-header">Total Points</th>
                            <th class="max-header">Max Allowed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cpd_categories as $code => $category): ?>
                            <tr class="category-row" data-category="<?php echo esc_attr($code); ?>">
                                <td class="category-code"><?php echo esc_html($code); ?></td>
                                <td class="category-description">
                                    <strong><?php echo esc_html($category['title']); ?></strong>
                                    <br><small><?php echo esc_html($category['description']); ?></small>
                                </td>
                                <?php foreach ($years as $index => $year): ?>
                                    <td class="year-input">
                                        <input 
                                            type="number" 
                                            name="cpd_points[<?php echo esc_attr($code); ?>][<?php echo ($index + 1); ?>]" 
                                            class="cpd-input-table" 
                                            data-category="<?php echo esc_attr($code); ?>" 
                                            data-year="<?php echo ($index + 1); ?>" 
                                            data-max="<?php echo esc_attr($category['max_per_year']); ?>" 
                                            min="0" 
                                            max="<?php echo esc_attr($category['max_per_year']); ?>" 
                                            step="0.1" 
                                            placeholder="0"
                                        />
                                        <div class="validation-message" style="display:none;"></div>
                                    </td>
                                <?php endforeach; ?>
                                <td class="category-total">
                                    <span class="total-display" id="total-<?php echo esc_attr($code); ?>">0.0</span>
                                </td>
                                <td class="category-max">
                                    <span class="max-display"><?php echo esc_html($category['max_points']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="totals-row">
                            <td colspan="2" class="totals-label"><strong>YEARLY TOTALS</strong></td>
                            <?php foreach ($years as $index => $year): ?>
                                <td class="year-total">
                                    <strong><span id="year-total-<?php echo ($index + 1); ?>">0.0</span></strong>
                                </td>
                            <?php endforeach; ?>
                            <td class="grand-total">
                                <strong><span id="grand-total">0.0</span></strong>
                            </td>
                            <td class="min-required">
                                <strong>150</strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="cpd-summary">
                <h5><i class="dashicons dashicons-analytics"></i> CPD Points Summary</h5>
                <div class="summary-grid">
                    <div class="summary-item">
                        <span class="summary-label">Total Points (5 Years):</span>
                        <span class="summary-value" id="total-all-years">0</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Minimum Required:</span>
                        <span class="summary-value">150</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Status:</span>
                        <span class="summary-value status-indicator" id="cpd-status">Insufficient</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h4><i class="dashicons dashicons-paperclip"></i> Supporting Documents</h4>
            <p class="section-description">Upload relevant documents to support your CPD points and <?php echo $isRec ? 'recertification' : 'renewal'; ?> application. All file uploads are mandatory. Accepted formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB per file)</p>
            
            <div class="upload-groups">
                <div class="upload-group">
                    <label for="cpd_files">CPD Proof Documents <span class="required">*</span></label>
                    <p class="upload-help">Upload certificates, training records, completion certificates, and other documents that prove your CPD activities.</p>
                    <div class="file-upload-wrapper">
                        <input type="file" id="cpd_files" name="cpd_files[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required />
                        <div class="file-upload-text">
                            <i class="dashicons dashicons-cloud-upload"></i>
                            <span>Click to select files or drag and drop</span>
                            <small>You can select multiple files at once</small>
                        </div>
                    </div>
                    <div id="cpd-files-list" class="files-list">
                        <div class="no-files">No files selected</div>
                    </div>
                </div>
                
                <div class="upload-group">
                    <label for="previous_certificates">Previous Certificates <span class="required">*</span></label>
                    <p class="upload-help">Upload your current/previous NDT certificates that you want to renew.</p>
                    <div class="file-upload-wrapper">
                        <input type="file" id="previous_certificates" name="previous_certificates[]" multiple accept=".pdf,.jpg,.jpeg,.png" required />
                        <div class="file-upload-text">
                            <i class="dashicons dashicons-awards"></i>
                            <span>Click to select certificate files or drag and drop</span>
                            <small>You can upload multiple certificate files</small>
                        </div>
                    </div>
                    <div id="previous-certificates-list" class="files-list">
                        <div class="no-files">No files selected</div>
                    </div>
                </div>
                
                <div class="upload-group">
                    <label for="support_docs">Additional Supporting Documents</label>
                    <p class="upload-help">Upload any additional documents that support your <?php echo $isRec ? 'recertification' : 'renewal'; ?> application (optional).</p>
                    <div class="file-upload-wrapper">
                        <input type="file" id="support_docs" name="support_docs[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                        <div class="file-upload-text">
                            <i class="dashicons dashicons-media-document"></i>
                            <span>Click to select files or drag and drop</span>
                            <small>Optional - any additional supporting documents</small>
                        </div>
                    </div>
                    <div id="support-files-list" class="files-list">
                        <div class="no-files">No files selected</div>
                    </div>
                </div>
            </div>
            
            <div class="file-upload-requirements">
                <h6><i class="dashicons dashicons-info"></i> File Upload Requirements</h6>
                <ul>
                    <li><strong>File Formats:</strong> PDF, DOC, DOCX, JPG, JPEG, PNG</li>
                    <li><strong>Maximum File Size:</strong> 10MB per file</li>
                    <li><strong>CPD Proof Documents:</strong> Required - Must include evidence of your CPD activities</li>
                    <li><strong>Previous Certificates:</strong> Required - Current certificates you want to renew</li>
                    <li><strong>Additional Documents:</strong> Optional - Any other supporting materials</li>
                </ul>
            </div>
        </div>

        <div class="form-actions">
            <div class="validation-summary" id="validation-summary" style="display:none;">
                <h6><i class="dashicons dashicons-warning"></i> Please correct the following errors:</h6>
                <ul id="validation-errors"></ul>
            </div>
            
            <div class="form-buttons">
                <button type="button" id="validate-form" class="button button-secondary">
                    <i class="dashicons dashicons-yes-alt"></i>
                    Validate Form
                </button>
                <button type="submit" class="submit-button" id="submit-renewal">
                    <i class="dashicons dashicons-yes"></i>
                    <span class="submit-text"><?php echo $isRec ? 'Submit CPD Recertification Application' : 'Submit CPD Renewal Application'; ?></span>
                    <span class="loading-spinner" style="display:none;">
                        <i class="dashicons dashicons-update-alt spinning"></i> Submitting...
                    </span>
                </button>
            </div>
            
            <div class="renewal-message" id="renewal-message" style="display:none;"></div>
            
            <div id="form-progress" class="form-progress" style="display:none;">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="progress-text">Submitting your application...</div>
            </div>
        </div>
    </form>
</div>


