<?php
// End-to-end test script for time tracking functionality
session_start();

// Simulate authenticated sessions for testing different roles
function testWithUser($username, $role, $firma = null) {
    $_SESSION['user'] = $username;
    $_SESSION['role'] = $role;
    if ($firma) {
        $_SESSION['entreprenor_firma'] = $firma;
    }
    
    echo "\n=== Testing as $username ($role" . ($firma ? ", $firma" : "") . ") ===\n";
}

function testTimeEntrySubmission($workOrderId, $date, $hours, $description) {
    echo "Testing time entry submission: WO=$workOrderId, Date=$date, Hours=$hours\n";
    
    // Simulate POST data
    $_POST['action'] = 'save_time_entry';
    $_POST['work_order_id'] = $workOrderId;
    $_POST['entry_date'] = $date;
    $_POST['hours'] = $hours;
    $_POST['description'] = $description;
    
    ob_start();
    include 'time_entry_handler.php';
    $output = ob_get_clean();
    
    echo "Response: $output\n";
    
    // Clear POST data for next test
    unset($_POST['action'], $_POST['work_order_id'], $_POST['entry_date'], $_POST['hours'], $_POST['description']);
    
    return json_decode($output, true);
}

function testTimeEntryRetrieval($workOrderId) {
    echo "Testing time entry retrieval for WO=$workOrderId\n";
    
    $_POST['action'] = 'get_time_entries';
    $_POST['work_order_id'] = $workOrderId;
    
    ob_start();
    include 'time_entry_handler.php';
    $output = ob_get_clean();
    
    echo "Response: $output\n";
    
    unset($_POST['action'], $_POST['work_order_id']);
    
    return json_decode($output, true);
}

echo "=== TIME TRACKING END-TO-END TESTS ===\n";

// Test 1: Valid entrepreneur submitting time for their own work order
testWithUser('entreprenor_test', 'entreprenor', 'Test Firma A/S');
$result1 = testTimeEntrySubmission(39, date('Y-m-d'), 3.5, 'Test submission - valid entrepreneur');
$retrieval1 = testTimeEntryRetrieval(39);

// Test 2: Entrepreneur trying to submit time for another firm's work order (should fail)
testWithUser('entreprenor_test', 'entreprenor', 'Test Firma A/S');
$result2 = testTimeEntrySubmission(41, date('Y-m-d'), 2.0, 'Test submission - unauthorized access attempt');

// Test 3: Admin user (should have access to all work orders)
testWithUser('admin_test', 'admin');
$result3 = testTimeEntrySubmission(41, date('Y-m-d'), 1.5, 'Test submission - admin user');

// Test 4: Invalid hours (not quarter-hour increment)
testWithUser('entreprenor_test', 'entreprenor', 'Test Firma A/S');
$result4 = testTimeEntrySubmission(39, date('Y-m-d'), 3.33, 'Test submission - invalid hours');

// Test 5: Future date (should fail)
testWithUser('entreprenor_test', 'entreprenor', 'Test Firma A/S');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$result5 = testTimeEntrySubmission(39, $tomorrow, 4.0, 'Test submission - future date');

echo "\n=== TEST RESULTS SUMMARY ===\n";
echo "Test 1 (Valid entrepreneur): " . ($result1['success'] ? "PASS" : "FAIL") . "\n";
echo "Test 2 (Unauthorized access): " . (!$result2['success'] ? "PASS" : "FAIL") . "\n";
echo "Test 3 (Admin access): " . ($result3['success'] ? "PASS" : "FAIL") . "\n";
echo "Test 4 (Invalid hours): " . (!$result4['success'] ? "PASS" : "FAIL") . "\n";
echo "Test 5 (Future date): " . (!$result5['success'] ? "PASS" : "FAIL") . "\n";

echo "\n=== DETAILED VALIDATION ===\n";
if ($retrieval1 && $retrieval1['success']) {
    $totalHours = $retrieval1['data']['total_hours'];
    echo "Total hours for WO-39: $totalHours\n";
    echo "Number of entries: " . count($retrieval1['data']['entries']) . "\n";
}

echo "\nEnd-to-end tests completed!\n";
?>