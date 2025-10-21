<?php
// Fast router for PHP built-in server with instant health check support

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Fast health check endpoint - return immediately
if ($uri === '/health' || $uri === '/health.php') {
    http_response_code(200);
    echo 'OK';
    exit();
}

// Fast root endpoint - handle health checks and user requests
if ($uri === '/') {
    // HEAD requests (health checks) - return 200 OK immediately
    if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
        http_response_code(200);
        exit();
    }
    
    // GET requests - serve index.php for all users (first-time and logged-in)
    require 'index.php';
    exit();
}

// Serve static files directly
if (file_exists(__DIR__ . $uri) && is_file(__DIR__ . $uri)) {
    return false; // Let PHP built-in server handle it
}

// Route PHP files
$file = __DIR__ . $uri;
if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    require $file;
    exit();
}

// Fallback to login
http_response_code(200);
echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=/login.php"><title>PTW System</title></head><body>Loading...</body></html>';
exit();
