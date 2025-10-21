<?php
// Health check - respond immediately for deployment health checks
if (php_sapi_name() === 'cli-server' && $_SERVER['REQUEST_URI'] === '/') {
    // Check if this is a simple health check (no cookies/session)
    if (empty($_COOKIE)) {
        http_response_code(200);
        echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=login.php"></head><body>Redirecting...</body></html>';
        exit();
    }
}

session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: view_wo.php');
    exit();
}

// Redirect to login page
header('Location: login.php');
exit();
