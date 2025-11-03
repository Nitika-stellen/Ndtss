<?php if (!defined('ABSPATH')) { exit; } ?>
<?php
// Get the current renewal method from URL parameter or default to CPD
$current_renewal_method = isset($_GET['renewal_method']) ? sanitize_text_field($_GET['renewal_method']) : 'CPD';
$current_renewal_method = strtoupper($current_renewal_method);

// Ensure valid method
if (!in_array($current_renewal_method, ['CPD', 'EXAM'])) {
    $current_renewal_method = 'CPD';
}

$isRec = isset($is_recertification) && $is_recertification;
?>
<div class="renew-options">
    <div class="renew-header">
        <h2><i class="dashicons dashicons-awards"></i> <?php echo $isRec ? 'Recertify Your Certification' : 'Renew Your Certification'; ?></h2>
        <p class="renew-subtitle"><?php echo $isRec ? 'Choose your preferred recertification method' : 'Choose your preferred renewal method to continue your professional development'; ?></p>
    </div>
    
    <div class="renew-methods">
        <div class="method-card <?php echo ($current_renewal_method === 'CPD') ? 'active' : ''; ?>" data-method="CPD">
            <div class="method-icon">
                <i class="dashicons dashicons-chart-line"></i>
            </div>
            <h3><?php echo $isRec ? 'CPD Recertification' : 'CPD Renewal'; ?></h3>
            <p><?php echo $isRec ? 'Recertify when CPD points are insufficient for direct renewal' : 'Renew through Continuing Professional Development points earned over the past 5 years'; ?></p>
            <ul class="method-features">
                <li>✓ Submit CPD points by category</li>
                <li>✓ Upload supporting documents</li>
                <li>✓ Flexible timeline</li>
            </ul>
            <button type="button" class="method-button renewal-method-btn <?php echo ($current_renewal_method === 'CPD') ? 'button-primary' : ''; ?>" data-method="CPD">
                <?php echo ($current_renewal_method === 'CPD') ? 'Selected' : 'Choose CPD'; ?>
            </button>
        </div>
        
        <div class="method-card <?php echo ($current_renewal_method === 'EXAM') ? 'active' : ''; ?>" data-method="EXAM">
            <div class="method-icon">
                <i class="dashicons dashicons-clipboard"></i>
            </div>
            <h3><?php echo $isRec ? 'Exam Recertification' : 'Exam Renewal'; ?></h3>
            <p><?php echo $isRec ? 'Recertify by taking a comprehensive examination' : 'Renew by taking a comprehensive examination to demonstrate current knowledge'; ?></p>
            <ul class="method-features">
                <li>✓ Comprehensive assessment</li>
                <li>✓ Immediate certification</li>
                <li>✓ Structured process</li>
            </ul>
            <button type="button" class="method-button renewal-method-btn <?php echo ($current_renewal_method === 'EXAM') ? 'button-primary' : ''; ?>" data-method="EXAM">
                <?php echo ($current_renewal_method === 'EXAM') ? 'Selected' : 'Choose Exam'; ?>
            </button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    /**
     * Get current URL parameters as an object
     */
    function getUrlParams() {
        var params = {};
        var urlParams = new URLSearchParams(window.location.search);
        for (var pair of urlParams.entries()) {
            params[pair[0]] = pair[1];
        }
        return params;
    }
    
    /**
     * Update URL with new renewal method parameter while preserving other parameters
     */
    function updateUrlWithMethod(method) {
        var params = getUrlParams();
        params.renewal_method = method;
        
        var newUrl = window.location.pathname + '?' + new URLSearchParams(params).toString();
        
        // Use replaceState to avoid adding to browser history
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', newUrl);
        }
        
        console.log('URL updated with method:', method, 'New URL:', newUrl);
    }
    
    /**
     * Get the current renewal method from URL or localStorage backup
     */
    function getCurrentMethod() {
        var urlParams = getUrlParams();
        var urlMethod = urlParams.renewal_method;
        
        // Check URL parameter first
        if (urlMethod && (urlMethod.toUpperCase() === 'CPD' || urlMethod.toUpperCase() === 'EXAM')) {
            // Store in localStorage as backup
            localStorage.setItem('renewal_method_' + (urlParams.cert_id || 'default'), urlMethod.toUpperCase());
            return urlMethod.toUpperCase();
        }
        
        // Fallback to localStorage if URL doesn't have method
        var storedMethod = localStorage.getItem('renewal_method_' + (urlParams.cert_id || 'default'));
        if (storedMethod && (storedMethod === 'CPD' || storedMethod === 'EXAM')) {
            return storedMethod;
        }
        
        // Default to CPD
        return 'CPD';
    }
    
    /**
     * Initialize renewal process based on current method selection
     */
    function initializeRenewalProcess() {
        var currentMethod = getCurrentMethod();
        console.log('Initializing renewal process with method:', currentMethod);
        
        // Show the forms container
        $('#renewal-form-section').show();
        
        // Show appropriate form based on current method
        if (currentMethod === 'EXAM') {
            showExamForm();
        } else {
            showCPDForm();
        }
        
        console.log('Renewal process initialized for method:', currentMethod);
    }

    /**
     * Show CPD form and hide exam form
     */
    function showCPDForm() {
        console.log('Showing CPD form...');
        
        // Update form visibility
        $('#cpd-form-section').show();
        $('#exam-form-section').hide();
        
        // Clear any Gravity Forms validation errors that might be lingering
        $('.gform_validation_error').remove();
        $('.gfield_error').removeClass('gfield_error');
        
        // Initialize CPD form functions
        setTimeout(function() {
            if (window.initializeCPDTable) {
                window.initializeCPDTable();
            }
            if (window.initializeFileUploads) {
                window.initializeFileUploads();
            }
            if (window.updateAllCalculations) {
                window.updateAllCalculations();
            }
            console.log('CPD form shown and initialized');
        }, 100);
    }

    /**
     * Show exam form and hide CPD form
     */
    function showExamForm() {
        console.log('Showing Exam form...');
        
        // Update form visibility
        $('#cpd-form-section').hide();
        $('#exam-form-section').show();
        
        // Ensure Gravity Forms are visible and functional
        setTimeout(function() {
            var $gformWrapper = $('#exam-form-section .gform_wrapper');
            
            $gformWrapper.show().css({
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1'
            });
            
            // Re-initialize Gravity Forms if needed
            if (typeof gform !== 'undefined' && gform.initializeOnLoaded) {
                gform.initializeOnLoaded();
            }
            
            // Trigger Gravity Forms post render event
            if (typeof gform !== 'undefined') {
                $(document).trigger('gform_post_render');
            }
            
            // Scroll to form if there are validation errors
            if ($gformWrapper.find('.gform_validation_error').length > 0) {
                $('html, body').animate({
                    scrollTop: $gformWrapper.offset().top - 100
                }, 500);
            }
            
            console.log('Exam form shown and initialized');
        }, 100);
    }
    
    /**
     * Handle method selection with URL persistence
     */
    function selectMethod(method) {
        console.log('Method selected:', method);
        
        // Store method in localStorage as backup
        var urlParams = getUrlParams();
        localStorage.setItem('renewal_method_' + (urlParams.cert_id || 'default'), method);
        
        // Update URL to persist selection
        updateUrlWithMethod(method);
        
        // Update UI states
        $('.method-card').removeClass('active');
        $('.method-button').removeClass('button-primary').text(function() {
            return $(this).data('method') === 'CPD' ? 'Choose CPD' : 'Choose Exam';
        });

        // Set active state for selected method
        var $selectedCard = $('.method-card[data-method="' + method + '"]');
        var $selectedButton = $selectedCard.find('.renewal-method-btn');
        
        $selectedCard.addClass('active');
        $selectedButton.addClass('button-primary').text('Selected');

        // Show appropriate form
        if (method === 'CPD') {
            showCPDForm();
        } else if (method === 'EXAM') {
            showExamForm();
        }
    }

    // Click handler for method selection buttons
    $('.renewal-method-btn').on('click', function(e) {
        e.preventDefault();
        var selectedMethod = $(this).data('method');
        selectMethod(selectedMethod);
    });
    
    // Handle browser back/forward navigation
    window.addEventListener('popstate', function(event) {
        console.log('Browser navigation detected, reinitializing...');
        setTimeout(function() {
            initializeRenewalProcess();
        }, 100);
    });
    
    // Handle Gravity Forms submission completion
    $(document).on('gform_confirmation_loaded', function(event, formId) {
        if (formId == 36) { // Form 31 is the exam form
            console.log('Form 31 submission completed, ensuring exam form stays visible');
            // Keep exam form visible even after submission
            $('#exam-form-section').show();
            $('#cpd-form-section').hide();
        }
    });
    
    // Handle Gravity Forms validation errors
    $(document).on('gform_post_render', function(event, formId, currentPage) {
        if (formId == 36) { // Form 36 is the exam form
            console.log('Form 36 rendered on page', currentPage, ', ensuring exam method is selected');
            var currentMethod = getCurrentMethod();
            if (currentMethod === 'EXAM') {
                // Ensure exam form stays visible on validation errors
                setTimeout(function() {
                    showExamForm();
                    
                    // Update URL to maintain method selection
                    updateUrlWithMethod('EXAM');
                    
                    // Update UI to show EXAM is selected
                    $('.method-card').removeClass('active');
                    $('.method-button').removeClass('button-primary').text(function() {
                        return $(this).data('method') === 'CPD' ? 'Choose CPD' : 'Choose Exam';
                    });
                    
                    var $examCard = $('.method-card[data-method="EXAM"]');
                    var $examButton = $examCard.find('.renewal-method-btn');
                    $examCard.addClass('active');
                    $examButton.addClass('button-primary').text('Selected');
                }, 50);
            }
        }
    });
    
    // Handle Gravity Forms page navigation (for multi-step forms)
    $(document).on('gform_page_loaded', function(event, formId, currentPage) {
        if (formId == 36) {
            console.log('Form 36 page', currentPage, 'loaded, maintaining exam method selection');
            var currentMethod = getCurrentMethod();
            if (currentMethod === 'EXAM') {
                setTimeout(function() {
                    showExamForm();
                    updateUrlWithMethod('EXAM');
                }, 100);
            }
        }
    });
    
    // Handle form submission start
    $(document).on('gform_pre_submission', function(event, formData, formId, currentPage) {
        if (formId == 31) {
            console.log('Form 31 submission starting, preserving exam method');
            // Store the method in localStorage before submission
            var urlParams = getUrlParams();
            localStorage.setItem('renewal_method_' + (urlParams.cert_id || 'default'), 'EXAM');
        }
    });

    // Initialize on page load
    initializeRenewalProcess();
    
    console.log('Renewal method selection system initialized');
});
</script>
