<?php
// Test dashboard calculations
session_start();
$_SESSION['user'] = 'admin_test';
$_SESSION['role'] = 'admin';

echo "=== DASHBOARD CALCULATION TEST ===\n";

require_once 'database.php';
$db = Database::getInstance();

// Test the exact queries used by dashboard
$workOrderHours = $db->fetchAll("
    SELECT wo.work_order_no, wo.entreprenor_firma,
           COALESCE(SUM(te.hours), 0) as total_hours,
           COUNT(DISTINCT te.user_id) as unique_users
    FROM work_orders wo
    LEFT JOIN time_entries te ON wo.id = te.work_order_id
    WHERE wo.work_order_no IN ('WO-001', 'WO-002', 'WO-003')
    GROUP BY wo.id, wo.work_order_no, wo.entreprenor_firma
    ORDER BY total_hours DESC
");

echo "Dashboard Work Order Statistics:\n";
foreach ($workOrderHours as $wo) {
    echo "- {$wo['work_order_no']} ({$wo['entreprenor_firma']}): {$wo['total_hours']} hours, {$wo['unique_users']} users\n";
}

// Test total active workers calculation  
$totalActiveWorkers = $db->fetch("SELECT COUNT(DISTINCT te.user_id) as total_workers FROM time_entries te");
echo "\nTotal Active Workers: " . $totalActiveWorkers['total_workers'] . "\n";

// Test contractor aggregation
$contractorStats = $db->fetchAll("
    SELECT wo.entreprenor_firma,
           COALESCE(SUM(te.hours), 0) as total_hours,
           COUNT(DISTINCT CASE WHEN te.hours IS NOT NULL THEN te.work_order_id END) as projects_with_time
    FROM work_orders wo
    LEFT JOIN time_entries te ON wo.id = te.work_order_id
    WHERE wo.entreprenor_firma IS NOT NULL AND wo.entreprenor_firma != ''
    GROUP BY wo.entreprenor_firma
    HAVING SUM(te.hours) > 0
    ORDER BY total_hours DESC
");

echo "\nTop Contractors by Hours:\n";
foreach ($contractorStats as $contractor) {
    echo "- {$contractor['entreprenor_firma']}: {$contractor['total_hours']} hours, {$contractor['projects_with_time']} projects\n";
}

echo "\n=== DASHBOARD TEST COMPLETED ===\n";
?>