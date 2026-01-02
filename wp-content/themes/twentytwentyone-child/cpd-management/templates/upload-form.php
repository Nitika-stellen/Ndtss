<?php
/**
 * CPD Upload Form (User Frontend)
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$current_year = date('Y');
?>

<div class="cpd-upload-wrapper">
    <div class="cpd-upload-header">
        <p>Upload your Continuous Professional Development attendance records for review and point allocation.</p>
    </div>
    
    <form id="cpd-upload-form" method="post" enctype="multipart/form-data">
        <div class="cpd-form-section">
            <h3>Activity Information</h3>
            
            <div class="cpd-info-notice" style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                <p style="margin: 0; color: #333;">
                    <strong>📋 Requirement:</strong> You need 150 CPD points over any 5-year period to be eligible for certificate renewal or recertification. Points are accumulated over time and count towards your 5-year total.
                </p>
            </div>
            
            <div class="form-field">
                <label for="activity_date">Activity Date <span class="required">*</span></label>
                <input type="date" 
                       id="activity_date" 
                       name="activity_date" 
                       required 
                       max="<?php echo esc_attr(date('Y-m-d')); ?>" />
                <p class="description">Select the date when the activity took place.</p>
            </div>
            
            <div class="form-field">
                <label for="activity_category">CPD Category</label>
                <select id="activity_category" name="activity_category" class="regular-text">
                    <option value="">Select Category</option>
                    <option value="A1">A1 - Performing NDT Activity</option>
                    <option value="A2">A2 - Theoretical Training</option>
                    <option value="A3">A3 - Practical Training</option>
                    <option value="A4">A4 - Delivery of Training</option>
                    <option value="A5">A5 - Research Activities</option>
                    <option value="6">6 - Technical Seminar/Paper</option>
                    <option value="7">7 - Presenting Technical Seminar</option>
                    <option value="8">8 - Society Membership</option>
                    <option value="9">9 - Technical Oversight</option>
                    <option value="10">10 - Committee Participation</option>
                    <option value="11">11 - Certification Body Role</option>
                </select>
                <p class="description">Select the appropriate CPD category for this activity.</p>
            </div>
            
            <div class="form-field">
                <label for="description">Description</label>
                <textarea id="description" 
                          name="description" 
                          rows="5" 
                          class="large-text"></textarea>
                <p class="description">Provide a brief description of the activity and your participation.</p>
            </div>
            
            <div class="form-field">
                <label for="points_requested">Points Requested</label>
                <input type="number" 
                       id="points_requested" 
                       name="points_requested" 
                       step="0.1" 
                       min="0" 
                       class="small-text" />
                <p class="description">Enter the number of CPD points you believe this activity warrants (optional).</p>
            </div>
        </div>
        
        <div class="cpd-form-section">
            <h3>Supporting Document</h3>
            
            <div class="form-field">
                <label for="cpd_document">CPD Document <span class="required">*</span></label>
                <input type="file" 
                       id="cpd_document" 
                       name="cpd_document" 
                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" 
                       required />
                <p class="description">Upload a certificate, attendance record, or other document proving your participation. Accepted formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB)</p>
            </div>
        </div>
        
        <div class="cpd-form-actions">
            <button type="submit" class="button button-primary button-large">
                <span class="submit-text">Upload Entry</span>
                <span class="loading-spinner" style="display:none;">
                    <span class="spinner is-active"></span> Uploading...
                </span>
            </button>
        </div>
        
        <div class="cpd-form-message" id="cpd-form-message" style="display:none;"></div>
    </form>
    
    <div class="cpd-upload-help">
        <h3>Need Help?</h3>
        <ul>
            <li>Upload clear, legible documents that show your participation in the activity.</li>
            <li>Ensure the activity date is accurate.</li>
            <li>Select the appropriate CPD category for your activity.</li>
            <li>Your entry will be reviewed by an administrator who will allocate points.</li>
        </ul>
    </div>
</div>

