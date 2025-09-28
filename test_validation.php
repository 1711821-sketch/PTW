<?php
// Focused validation test
session_start();
$_SESSION['user'] = 'entreprenor_test';
$_SESSION['role'] = 'entreprenor';
$_SESSION['entreprenor_firma'] = 'Test Firma A/S';

echo "=== VALIDATION TESTS ===\n";

// Test invalid hours (not quarter-hour)
echo "Testing invalid hours (3.33)...\n";
$_POST = [
    'action' => 'save_time_entry',
    'work_order_id' => 39,
    'entry_date' => date('Y-m-d'),
    'hours' => 3.33,
    'description' => 'Invalid hours test'
];

ob_start();
include 'time_entry_handler.php';
$result = ob_get_clean();
echo "Result: " . (strpos($result, 'kvarte-times') !== false ? "PASS - Rejected invalid hours" : "FAIL") . "\n";

// Test future date
echo "Testing future date...\n";
$_POST = [
    'action' => 'save_time_entry',
    'work_order_id' => 39,
    'entry_date' => date('Y-m-d', strtotime('+1 day')),
    'hours' => 4.0,
    'description' => 'Future date test'
];

ob_start();
include 'time_entry_handler.php';
$result = ob_get_clean();
echo "Result: " . (strpos($result, 'fremtiden') !== false ? "PASS - Rejected future date" : "FAIL") . "\n";

// Test admin access to any work order
$_SESSION['user'] = 'admin_test';
$_SESSION['role'] = 'admin';
unset($_SESSION['entreprenor_firma']);

echo "Testing admin access to work order 41...\n";
$_POST = [
    'action' => 'save_time_entry',
    'work_order_id' => 41,
    'entry_date' => date('Y-m-d'),
    'hours' => 2.5,
    'description' => 'Admin test entry'
];

ob_start();
include 'time_entry_handler.php';
$result = ob_get_clean();
echo "Result: " . (strpos($result, '"success":true') !== false ? "PASS - Admin can access any work order" : "FAIL") . "\n";

echo "=== VALIDATION TESTS COMPLETED ===\n";
?>