<?php
// Router for PHP built-in server with health check support

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Health check endpoint - return 200 OK for HEAD and GET on root
if ($uri === '/' && in_array($_SERVER['REQUEST_METHOD'], ['HEAD', 'GET'])) {
    // For HEAD requests (health checks), just return 200 OK
    if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
        http_response_code(200);
        exit();
    }
    
    // For GET requests on root without cookies (health checks), return 200 OK
    if (empty($_COOKIE) && $_SERVER['REQUEST_METHOD'] === 'GET') {
        http_response_code(200);
        echo '<!DOCTYPE html><html><head><title>PTW System</title></head><body><h1>PTW System Online</h1><p><a href="/login.php">Login</a></p></body></html>';
        exit();
    }
    
    // For GET requests with cookies (user browsers), serve index.php which redirects
    require 'index.php';
    exit();
}

// Health check endpoint
if ($uri === '/health' || $uri === '/health.php') {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'timestamp' => time()]);
    exit();
}

// Serve static files directly
if (file_exists(__DIR__ . $uri) && is_file(__DIR__ . $uri)) {
    return false; // Let PHP built-in server handle it
}

// Route everything else through the requested PHP file
$file = __DIR__ . $uri;
if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    require $file;
    exit();
}

// If nothing matches, serve index.php
require 'index.php';
