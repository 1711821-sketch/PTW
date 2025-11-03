<?php
// reset_daily_status.php
// This script resets the daily work status for all PTWs
// Should be run daily at midnight (via cron) or on first login of the day

require_once 'database.php';

try {
    $db = Database::getInstance();
    
    // Reset status_dag to 'kræver_dagsgodkendelse' for all PTWs that were active or paused
    $updated = $db->execute("
        UPDATE work_orders 
        SET status_dag = 'kræver_dagsgodkendelse', 
            ikon = 'green_static',
            sluttid = NULL
        WHERE status_dag IN ('aktiv_dag', 'pause_dag')
        AND status = 'active'
    ");
    
    $timestamp = date('Y-m-d H:i:s');
    error_log("Daily status reset completed at $timestamp - Updated rows: $updated");
    
    // If called from command line, output result
    if (php_sapi_name() === 'cli') {
        echo "Daily status reset completed at $timestamp\n";
        echo "Updated $updated work order(s)\n";
    }
    
} catch (Exception $e) {
    error_log("Daily status reset error: " . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
