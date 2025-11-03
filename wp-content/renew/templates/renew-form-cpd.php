<?php if (!defined('ABSPATH')) { exit; }
    $user = wp_get_current_user();
    $name   = isset($_GET['name']) ? sanitize_text_field($_GET['name']) : ($user->display_name ?? '');
    $dob    = isset($_GET['dob']) ? sanitize_text_field($_GET['dob']) : '';
    $level  = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
    $sector = isset($_GET['sector']) ? sanitize_text_field($_GET['sector']) : '';
?>
<div class="cpd-form-wrapper">
    <div class="form-header">
        <h3><i class="dashicons dashicons-chart-line"></i> CPD Renewal Form</h3>
        <p>Complete your Continuing Professional Development renewal application</p>
    </div>

    <form id="cpd-renew-form" enctype="multipart/form-data" class="cpd-form">
        <input type="hidden" name="action" value="submit_cpd_form" />
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('renew_nonce')); ?>" />
        <input type="hidden" name="method" value="CPD" />

        <div class="form-section">
            <h4><i class="dashicons dashicons-admin-users"></i> Personal Information</h4>
            <div class="form-grid">
                <div class="field-group">
                    <label for="name">Full Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo esc_attr($name); ?>" required />
                </div>
                <div class="field-group">
                    <label for="dob">Date of Birth <span class="required">*</span></label>
                    <input type="date" id="dob" name="dob" value="<?php echo esc_attr($dob); ?>" required />
                </div>
                <div class="field-group">
                    <label for="level">Certification Level <span class="required">*</span></label>
                    <select id="level" name="level" required>
                        <option value="">Select Level</option>
                        <option value="Level 1" <?php selected($level, 'Level 1'); ?>>Level 1</option>
                        <option value="Level 2" <?php selected($level, 'Level 2'); ?>>Level 2</option>
                        <option value="Level 3" <?php selected($level, 'Level 3'); ?>>Level 3</option>
                        <option value="Senior Level" <?php selected($level, 'Senior Level'); ?>>Senior Level</option>
                    </select>
                </div>
                <div class="field-group">
                    <label for="sector">Sector <span class="required">*</span></label>
                    <select id="sector" name="sector" required>
                        <option value="">Select Sector</option>
                        <option value="Aerospace" <?php selected($sector, 'Aerospace'); ?>>Aerospace</option>
                        <option value="Automotive" <?php selected($sector, 'Automotive'); ?>>Automotive</option>
                        <option value="Construction" <?php selected($sector, 'Construction'); ?>>Construction</option>
                        <option value="Marine" <?php selected($sector, 'Marine'); ?>>Marine</option>
                        <option value="Oil & Gas" <?php selected($sector, 'Oil & Gas'); ?>>Oil & Gas</option>
                        <option value="Power Generation" <?php selected($sector, 'Power Generation'); ?>>Power Generation</option>
                        <option value="Railway" <?php selected($sector, 'Railway'); ?>>Railway</option>
                        <option value="Other" <?php selected($sector, 'Other'); ?>>Other</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h4><i class="dashicons dashicons-chart-area"></i> CPD Points by Category (Past 5 Years)</h4>
            <p class="section-description">Enter your CPD points earned in each category for the past 5 years. Points should be entered as decimal numbers (e.g., 1.5, 2.0).</p>
            
            <div class="cpd-years-container">
                <?php for ($y = 1; $y <= 5; $y++): ?>
                    <div class="cpd-year-card" data-year="<?php echo $y; ?>">
                        <div class="year-header">
                            <h5>Year <?php echo $y; ?></h5>
                            <span class="year-total">Total: <span class="total-points">0</span></span>
                        </div>
                        <div class="cpd-categories">
                            <div class="category-group">
                                <label for="training_<?php echo $y; ?>">Training</label>
                                <input type="number" id="training_<?php echo $y; ?>" step="0.1" min="0" name="years[<?php echo $y; ?>][training]" placeholder="0.0" />
                            </div>
                            <div class="category-group">
                                <label for="workshops_<?php echo $y; ?>">Workshops</label>
                                <input type="number" id="workshops_<?php echo $y; ?>" step="0.1" min="0" name="years[<?php echo $y; ?>][workshops]" placeholder="0.0" />
                            </div>
                            <div class="category-group">
                                <label for="seminars_<?php echo $y; ?>">Seminars</label>
                                <input type="number" id="seminars_<?php echo $y; ?>" step="0.1" min="0" name="years[<?php echo $y; ?>][seminars]" placeholder="0.0" />
                            </div>
                            <div class="category-group">
                                <label for="publications_<?php echo $y; ?>">Publications</label>
                                <input type="number" id="publications_<?php echo $y; ?>" step="0.1" min="0" name="years[<?php echo $y; ?>][publications]" placeholder="0.0" />
                            </div>
                            <div class="category-group">
                                <label for="other_<?php echo $y; ?>">Other</label>
                                <input type="number" id="other_<?php echo $y; ?>" step="0.1" min="0" name="years[<?php echo $y; ?>][other]" placeholder="0.0" />
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="form-section">
            <h4><i class="dashicons dashicons-paperclip"></i> Supporting Documents</h4>
            <p class="section-description">Upload relevant documents to support your CPD points. Accepted formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB per file)</p>
            
            <div class="upload-groups">
                <div class="upload-group">
                    <label for="cpd_files">CPD Proof Documents <span class="required">*</span></label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="cpd_files" name="cpd_files[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                        <div class="file-upload-text">
                            <i class="dashicons dashicons-cloud-upload"></i>
                            <span>Click to select files or drag and drop</span>
                        </div>
                    </div>
                    <div id="cpd-files-list" class="files-list"></div>
                </div>
                
                <div class="upload-group">
                    <label for="support_docs">Additional Supporting Documents</label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="support_docs" name="support_docs[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                        <div class="file-upload-text">
                            <i class="dashicons dashicons-cloud-upload"></i>
                            <span>Click to select files or drag and drop</span>
                        </div>
                    </div>
                    <div id="support-files-list" class="files-list"></div>
                </div>
                
                <div class="upload-group">
                    <label for="previous_certificate">Previous Certificate</label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="previous_certificate" name="previous_certificate" accept=".pdf,.jpg,.jpeg,.png" />
                        <div class="file-upload-text">
                            <i class="dashicons dashicons-cloud-upload"></i>
                            <span>Click to select file or drag and drop</span>
                        </div>
                    </div>
                    <div id="certificate-file-list" class="files-list"></div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" id="test-ajax" class="button" style="margin-right: 10px;">
                Test AJAX Connection
            </button>
            <button type="submit" class="submit-button">
                <i class="dashicons dashicons-yes"></i>
                Submit CPD Renewal Application
            </button>
            <div class="renew-message" style="display:none;"></div>
        </div>
    </form>
</div>


