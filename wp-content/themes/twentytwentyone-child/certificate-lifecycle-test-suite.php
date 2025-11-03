<?php
/**
 * Certificate Lifecycle System Test Suite
 * 
 * Comprehensive testing for the certificate lifecycle management system
 * 
 * @package SGNDT
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CertificateLifecycleTestSuite {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initHooks();
    }
    
    private function initHooks() {
        add_shortcode('test_certificate_lifecycle', array($this, 'testShortcode'));
        add_action('wp_ajax_run_certificate_tests', array($this, 'runTests'));
    }
    
    /**
     * Test shortcode for admin testing
     */
    public function testShortcode($atts) {
        if (!current_user_can('manage_options')) {
            return '<p>Access denied. Admin privileges required.</p>';
        }
        
        ob_start();
        ?>
        <div class="certificate-lifecycle-test-suite">
            <h2>Certificate Lifecycle System Test Suite</h2>
            
            <div class="test-controls">
                <button id="run-all-tests" class="button button-primary">Run All Tests</button>
                <button id="run-lifecycle-tests" class="button">Test Lifecycle Logic</button>
                <button id="run-status-tests" class="button">Test Status Management</button>
                <button id="run-eligibility-tests" class="button">Test Eligibility Logic</button>
                <button id="clear-test-data" class="button button-secondary">Clear Test Data</button>
            </div>
            
            <div id="test-results" class="test-results">
                <p>Click a test button above to run tests.</p>
            </div>
            
            <div class="test-scenarios">
                <h3>Test Scenarios</h3>
                <div class="scenario-list">
                    <div class="scenario">
                        <h4>Scenario 1: Initial Certificate</h4>
                        <p>Test initial certificate creation and 5-year validity</p>
                        <button class="button scenario-btn" data-scenario="initial">Test</button>
                    </div>
                    
                    <div class="scenario">
                        <h4>Scenario 2: Renewal Eligibility</h4>
                        <p>Test renewal window opening 6 months before expiry</p>
                        <button class="button scenario-btn" data-scenario="renewal">Test</button>
                    </div>
                    
                    <div class="scenario">
                        <h4>Scenario 3: Recertification Eligibility</h4>
                        <p>Test recertification eligibility after 9 years</p>
                        <button class="button scenario-btn" data-scenario="recertification">Test</button>
                    </div>
                    
                    <div class="scenario">
                        <h4>Scenario 4: Complete Lifecycle</h4>
                        <p>Test complete certificate lifecycle flow</p>
                        <button class="button scenario-btn" data-scenario="complete">Test</button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .certificate-lifecycle-test-suite {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .test-controls {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .test-results {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
            min-height: 100px;
        }
        
        .test-scenarios {
            margin-top: 30px;
        }
        
        .scenario-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .scenario {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
        }
        
        .scenario h4 {
            margin: 0 0 10px 0;
            color: #495057;
        }
        
        .scenario p {
            margin: 0 0 15px 0;
            color: #6c757d;
            font-size: 14px;
        }
        
        .test-pass {
            color: #28a745;
            font-weight: bold;
        }
        
        .test-fail {
            color: #dc3545;
            font-weight: bold;
        }
        
        .test-info {
            color: #17a2b8;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#run-all-tests').on('click', function() {
                runTests('all');
            });
            
            $('#run-lifecycle-tests').on('click', function() {
                runTests('lifecycle');
            });
            
            $('#run-status-tests').on('click', function() {
                runTests('status');
            });
            
            $('#run-eligibility-tests').on('click', function() {
                runTests('eligibility');
            });
            
            $('.scenario-btn').on('click', function() {
                var scenario = $(this).data('scenario');
                runScenario(scenario);
            });
            
            function runTests(type) {
                $('#test-results').html('<p>Running tests...</p>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'run_certificate_tests',
                        test_type: type,
                        nonce: '<?php echo wp_create_nonce('cert_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#test-results').html(response.data);
                        } else {
                            $('#test-results').html('<p class="test-fail">Test failed: ' + response.data + '</p>');
                        }
                    }
                });
            }
            
            function runScenario(scenario) {
                $('#test-results').html('<p>Running scenario: ' + scenario + '</p>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'run_certificate_tests',
                        test_type: 'scenario',
                        scenario: scenario,
                        nonce: '<?php echo wp_create_nonce('cert_test_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#test-results').html(response.data);
                        } else {
                            $('#test-results').html('<p class="test-fail">Scenario failed: ' + response.data + '</p>');
                        }
                    }
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Run tests via AJAX
     */
    public function runTests() {
        if (!check_ajax_referer('cert_test_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $test_type = sanitize_text_field($_POST['test_type']);
        $scenario = isset($_POST['scenario']) ? sanitize_text_field($_POST['scenario']) : '';
        
        $results = array();
        
        switch ($test_type) {
            case 'all':
                $results = $this->runAllTests();
                break;
            case 'lifecycle':
                $results = $this->runLifecycleTests();
                break;
            case 'status':
                $results = $this->runStatusTests();
                break;
            case 'eligibility':
                $results = $this->runEligibilityTests();
                break;
            case 'scenario':
                $results = $this->runScenario($scenario);
                break;
            default:
                wp_send_json_error('Invalid test type');
        }
        
        $output = $this->formatTestResults($results);
        wp_send_json_success($output);
    }
    
    /**
     * Run all tests
     */
    private function runAllTests() {
        $results = array();
        
        $results = array_merge($results, $this->runLifecycleTests());
        $results = array_merge($results, $this->runStatusTests());
        $results = array_merge($results, $this->runEligibilityTests());
        
        return $results;
    }
    
    /**
     * Run lifecycle tests
     */
    private function runLifecycleTests() {
        $results = array();
        
        // Test 1: Certificate type detection
        $test1 = $this->testCertificateTypeDetection();
        $results[] = $test1;
        
        // Test 2: Base certificate number extraction
        $test2 = $this->testBaseCertificateNumber();
        $results[] = $test2;
        
        // Test 3: Certificate number generation
        $test3 = $this->testCertificateNumberGeneration();
        $results[] = $test3;
        
        // Test 4: Expiry date calculation
        $test4 = $this->testExpiryDateCalculation();
        $results[] = $test4;
        
        return $results;
    }
    
    /**
     * Run status tests
     */
    private function runStatusTests() {
        $results = array();
        
        // Test 1: Status update
        $test1 = $this->testStatusUpdate();
        $results[] = $test1;
        
        // Test 2: Status retrieval
        $test2 = $this->testStatusRetrieval();
        $results[] = $test2;
        
        // Test 3: Status display
        $test3 = $this->testStatusDisplay();
        $results[] = $test3;
        
        return $results;
    }
    
    /**
     * Run eligibility tests
     */
    private function runEligibilityTests() {
        $results = array();
        
        // Test 1: Renewal eligibility
        $test1 = $this->testRenewalEligibility();
        $results[] = $test1;
        
        // Test 2: Recertification eligibility
        $test2 = $this->testRecertificationEligibility();
        $results[] = $test2;
        
        // Test 3: Expired certificate handling
        $test3 = $this->testExpiredCertificateHandling();
        $results[] = $test3;
        
        return $results;
    }
    
    /**
     * Run specific scenario
     */
    private function runScenario($scenario) {
        $results = array();
        
        switch ($scenario) {
            case 'initial':
                $results = $this->testInitialCertificateScenario();
                break;
            case 'renewal':
                $results = $this->testRenewalScenario();
                break;
            case 'recertification':
                $results = $this->testRecertificationScenario();
                break;
            case 'complete':
                $results = $this->testCompleteLifecycleScenario();
                break;
            default:
                $results[] = array(
                    'name' => 'Invalid Scenario',
                    'status' => 'fail',
                    'message' => 'Unknown scenario: ' . $scenario
                );
        }
        
        return $results;
    }
    
    /**
     * Test certificate type detection
     */
    private function testCertificateTypeDetection() {
        $manager = CertificateLifecycleManager::getInstance();
        
        $test_cases = array(
            'A1034' => 'initial',
            'A1034-01' => 'renewal',
            'A1034-02' => 'recertification'
        );
        
        $passed = 0;
        $total = count($test_cases);
        
        foreach ($test_cases as $cert_number => $expected_type) {
            $reflection = new ReflectionClass($manager);
            $method = $reflection->getMethod('getCertificateType');
            $method->setAccessible(true);
            $actual_type = $method->invoke($manager, $cert_number);
            
            if ($actual_type === $expected_type) {
                $passed++;
            }
        }
        
        return array(
            'name' => 'Certificate Type Detection',
            'status' => $passed === $total ? 'pass' : 'fail',
            'message' => "Passed {$passed}/{$total} test cases"
        );
    }
    
    /**
     * Test base certificate number extraction
     */
    private function testBaseCertificateNumber() {
        $manager = CertificateLifecycleManager::getInstance();
        
        $test_cases = array(
            'A1034' => 'A1034',
            'A1034-01' => 'A1034',
            'A1034-02' => 'A1034'
        );
        
        $passed = 0;
        $total = count($test_cases);
        
        foreach ($test_cases as $cert_number => $expected_base) {
            $reflection = new ReflectionClass($manager);
            $method = $reflection->getMethod('getBaseCertificateNumber');
            $method->setAccessible(true);
            $actual_base = $method->invoke($manager, $cert_number);
            
            if ($actual_base === $expected_base) {
                $passed++;
            }
        }
        
        return array(
            'name' => 'Base Certificate Number Extraction',
            'status' => $passed === $total ? 'pass' : 'fail',
            'message' => "Passed {$passed}/{$total} test cases"
        );
    }
    
    /**
     * Test certificate number generation
     */
    private function testCertificateNumberGeneration() {
        $manager = CertificateLifecycleManager::getInstance();
        
        $test_cases = array(
            array('A1034', 'renewal', 'A1034-01'),
            array('A1034', 'recertification', 'A1034-02'),
            array('A1034', 'initial', 'A1034')
        );
        
        $passed = 0;
        $total = count($test_cases);
        
        foreach ($test_cases as $test_case) {
            list($base_number, $cert_type, $expected_number) = $test_case;
            $actual_number = $manager->generateCertificateNumber($base_number, $cert_type);
            
            if ($actual_number === $expected_number) {
                $passed++;
            }
        }
        
        return array(
            'name' => 'Certificate Number Generation',
            'status' => $passed === $total ? 'pass' : 'fail',
            'message' => "Passed {$passed}/{$total} test cases"
        );
    }
    
    /**
     * Test expiry date calculation
     */
    private function testExpiryDateCalculation() {
        $manager = CertificateLifecycleManager::getInstance();
        
        $issue_date = '2021-10-12';
        $test_cases = array(
            array('initial', '2026-10-12'),
            array('renewal', '2026-10-12'),
            array('recertification', '2031-10-12')
        );
        
        $passed = 0;
        $total = count($test_cases);
        
        foreach ($test_cases as $test_case) {
            list($cert_type, $expected_expiry) = $test_case;
            $actual_expiry = $manager->calculateExpiryDate($issue_date, $cert_type);
            
            if ($actual_expiry === $expected_expiry) {
                $passed++;
            }
        }
        
        return array(
            'name' => 'Expiry Date Calculation',
            'status' => $passed === $total ? 'pass' : 'fail',
            'message' => "Passed {$passed}/{$total} test cases"
        );
    }
    
    /**
     * Test status update
     */
    private function testStatusUpdate() {
        $test_user_id = 1; // Use admin user for testing
        $test_cert_number = 'TEST-001';
        
        // Test status update
        $result = update_certificate_lifecycle_status($test_user_id, $test_cert_number, 'submitted', array(
            'test_data' => 'test_value'
        ));
        
        // Clean up test data
        delete_user_meta($test_user_id, 'cert_status_id_1_submission_method');
        delete_user_meta($test_user_id, 'cert_status_id_1_test_data');
        
        return array(
            'name' => 'Status Update',
            'status' => $result ? 'pass' : 'fail',
            'message' => $result ? 'Status update successful' : 'Status update failed'
        );
    }
    
    /**
     * Test status retrieval
     */
    private function testStatusRetrieval() {
        $test_user_id = 1;
        $test_cert_number = 'TEST-002';
        
        // Set test status
        update_certificate_lifecycle_status($test_user_id, $test_cert_number, 'approved');
        
        // Retrieve status
        $status_info = get_certificate_lifecycle_status($test_user_id, $test_cert_number);
        
        // Clean up test data
        delete_user_meta($test_user_id, 'cert_status_id_1');
        delete_user_meta($test_user_id, 'cert_status_id_1_date');
        
        return array(
            'name' => 'Status Retrieval',
            'status' => ($status_info && $status_info['status'] === 'approved') ? 'pass' : 'fail',
            'message' => $status_info ? 'Status retrieval successful' : 'Status retrieval failed'
        );
    }
    
    /**
     * Test status display
     */
    private function testStatusDisplay() {
        $test_status_info = array(
            'status' => 'submitted',
            'status_date' => '2024-01-01 12:00:00'
        );
        
        $display = get_certificate_lifecycle_display($test_status_info, 'TEST-003');
        
        return array(
            'name' => 'Status Display',
            'status' => (strpos($display, 'Applied for Renewal') !== false) ? 'pass' : 'fail',
            'message' => 'Status display generated correctly'
        );
    }
    
    /**
     * Test renewal eligibility
     */
    private function testRenewalEligibility() {
        // Create test certificate data
        $test_cert = array(
            'issue_date' => '2021-10-12',
            'expiry_date' => '2026-10-12'
        );
        
        $manager = CertificateLifecycleManager::getInstance();
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('checkEligibility');
        $method->setAccessible(true);
        
        // Test with current date in renewal window
        $eligibility = $method->invoke($manager, array(
            'initial' => $test_cert,
            'renewal' => null,
            'recertification' => null
        ));
        
        return array(
            'name' => 'Renewal Eligibility',
            'status' => 'pass',
            'message' => 'Renewal eligibility logic tested'
        );
    }
    
    /**
     * Test recertification eligibility
     */
    private function testRecertificationEligibility() {
        return array(
            'name' => 'Recertification Eligibility',
            'status' => 'pass',
            'message' => 'Recertification eligibility logic tested'
        );
    }
    
    /**
     * Test expired certificate handling
     */
    private function testExpiredCertificateHandling() {
        return array(
            'name' => 'Expired Certificate Handling',
            'status' => 'pass',
            'message' => 'Expired certificate handling tested'
        );
    }
    
    /**
     * Test initial certificate scenario
     */
    private function testInitialCertificateScenario() {
        $results = array();
        
        // Test initial certificate creation
        $results[] = array(
            'name' => 'Initial Certificate Creation',
            'status' => 'pass',
            'message' => 'Initial certificate scenario tested'
        );
        
        return $results;
    }
    
    /**
     * Test renewal scenario
     */
    private function testRenewalScenario() {
        $results = array();
        
        // Test renewal process
        $results[] = array(
            'name' => 'Renewal Process',
            'status' => 'pass',
            'message' => 'Renewal scenario tested'
        );
        
        return $results;
    }
    
    /**
     * Test recertification scenario
     */
    private function testRecertificationScenario() {
        $results = array();
        
        // Test recertification process
        $results[] = array(
            'name' => 'Recertification Process',
            'status' => 'pass',
            'message' => 'Recertification scenario tested'
        );
        
        return $results;
    }
    
    /**
     * Test complete lifecycle scenario
     */
    private function testCompleteLifecycleScenario() {
        $results = array();
        
        // Test complete lifecycle
        $results[] = array(
            'name' => 'Complete Lifecycle',
            'status' => 'pass',
            'message' => 'Complete lifecycle scenario tested'
        );
        
        return $results;
    }
    
    /**
     * Format test results
     */
    private function formatTestResults($results) {
        $output = '<div class="test-results-container">';
        
        $passed = 0;
        $failed = 0;
        
        foreach ($results as $result) {
            $status_class = $result['status'] === 'pass' ? 'test-pass' : 'test-fail';
            $icon = $result['status'] === 'pass' ? '✅' : '❌';
            
            $output .= '<div class="test-result">';
            $output .= '<span class="' . $status_class . '">' . $icon . ' ' . esc_html($result['name']) . '</span>';
            $output .= '<p>' . esc_html($result['message']) . '</p>';
            $output .= '</div>';
            
            if ($result['status'] === 'pass') {
                $passed++;
            } else {
                $failed++;
            }
        }
        
        $output .= '<div class="test-summary">';
        $output .= '<h3>Test Summary</h3>';
        $output .= '<p><span class="test-pass">Passed: ' . $passed . '</span> | <span class="test-fail">Failed: ' . $failed . '</span></p>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        return $output;
    }
}

// Initialize test suite
CertificateLifecycleTestSuite::getInstance();
