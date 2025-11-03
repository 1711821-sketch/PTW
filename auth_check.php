<?php
/**
 * Authentication Check - Enforces mandatory password change
 * Include this file at the top of every protected page
 * 
 * This prevents users from bypassing the password change requirement
 * by directly navigating to other pages after login.
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    // Not logged in, redirect to login page
    header('Location: login.php');
    exit();
}

// Check if user must change password (skip this check on change_password.php itself)
$current_script = basename($_SERVER['PHP_SELF']);
if ($current_script !== 'change_password.php' && $current_script !== 'logout.php') {
    require_once __DIR__ . '/database.php';
    
    try {
        $db = Database::getInstance();
        $user = $db->fetch("SELECT must_change_password FROM users WHERE username = ?", [$_SESSION['user']]);
        
        if ($user && isset($user['must_change_password']) && (bool)$user['must_change_password'] === true) {
            // User must change password, redirect to change_password.php
            header('Location: change_password.php');
            exit();
        }
        
        // Daily status reset: Check if we need to reset work status
        // This runs once per day when the first user logs in after midnight
        $lastResetDate = $_SESSION['last_status_reset'] ?? '';
        $today = date('Y-m-d');
        
        if ($lastResetDate !== $today) {
            // Reset daily work status for PTWs where approvals are NOT all for today
            // Only reset if they don't have all three current approvals
            $todayDanish = date('d-m-Y');
            $db->execute("
                UPDATE work_orders 
                SET status_dag = 'kræver_dagsgodkendelse', 
                    ikon = 'green_static',
                    sluttid = NULL
                WHERE status_dag IN ('aktiv_dag', 'pause_dag')
                AND status = 'active'
                AND NOT (
                    approvals::jsonb->>'opgaveansvarlig' = ?
                    AND approvals::jsonb->>'drift' = ?
                    AND approvals::jsonb->>'entreprenor' = ?
                )
            ", [$todayDanish, $todayDanish, $todayDanish]);
            
            // Mark that we've reset for today
            $_SESSION['last_status_reset'] = $today;
            error_log("Daily status reset completed on login at " . date('Y-m-d H:i:s'));
        }
        
    } catch (Exception $e) {
        // FAIL CLOSED: On database error, destroy session and redirect to login
        error_log('Auth check error: ' . $e->getMessage());
        session_destroy();
        header('Location: login.php?error=db');
        exit();
    }
}

// Load module configuration
// This makes the $modules array available globally to all pages
$modules = include __DIR__ . '/config/modules.php';
?>
