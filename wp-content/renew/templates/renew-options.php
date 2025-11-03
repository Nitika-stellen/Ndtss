<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="renew-options">
    <div class="renew-header">
        <h2><i class="dashicons dashicons-awards"></i> Renew Your Certification</h2>
        <p class="renew-subtitle">Choose your preferred renewal method to continue your professional development</p>
    </div>
    
    <div class="renew-methods">
        <div class="method-card <?php echo (isset($_GET['method']) && strtoupper($_GET['method']) === 'CPD') ? 'active' : ''; ?>">
            <div class="method-icon">
                <i class="dashicons dashicons-chart-line"></i>
            </div>
            <h3>CPD Renewal</h3>
            <p>Renew through Continuing Professional Development points earned over the past 5 years</p>
            <ul class="method-features">
                <li>✓ Submit CPD points by category</li>
                <li>✓ Upload supporting documents</li>
                <li>✓ Flexible timeline</li>
            </ul>
            <a class="method-button <?php echo (isset($_GET['method']) && strtoupper($_GET['method']) === 'CPD') ? 'button-primary' : 'button'; ?>" 
               href="<?php echo esc_url(add_query_arg(array('method' => 'CPD'), get_permalink())); ?>">
                <?php echo (isset($_GET['method']) && strtoupper($_GET['method']) === 'CPD') ? 'Selected' : 'Choose CPD'; ?>
            </a>
        </div>
        
        <div class="method-card <?php echo (isset($_GET['method']) && strtoupper($_GET['method']) === 'EXAM') ? 'active' : ''; ?>">
            <div class="method-icon">
                <i class="dashicons dashicons-clipboard"></i>
            </div>
            <h3>Exam Renewal</h3>
            <p>Renew by taking a comprehensive examination to demonstrate current knowledge</p>
            <ul class="method-features">
                <li>✓ Comprehensive assessment</li>
                <li>✓ Immediate certification</li>
                <li>✓ Structured process</li>
            </ul>
            <a class="method-button <?php echo (isset($_GET['method']) && strtoupper($_GET['method']) === 'EXAM') ? 'button-primary' : 'button'; ?>" 
               href="<?php echo esc_url(add_query_arg(array('method' => 'EXAM'), get_permalink())); ?>">
                <?php echo (isset($_GET['method']) && strtoupper($_GET['method']) === 'EXAM') ? 'Selected' : 'Choose Exam'; ?>
            </a>
        </div>
    </div>
</div>


