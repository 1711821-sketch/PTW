<?php
// Router for PHP's built-in server to serve static files

$request_uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve instructional videos from assets/videos/
if (preg_match('/^\/assets\/videos\/(.+)$/', $request_uri, $matches)) {
    $filename = $matches[1];
    $filepath = __DIR__ . '/assets/videos/' . $filename;
    
    // Security: prevent directory traversal
    $realpath = realpath($filepath);
    $videos_dir = realpath(__DIR__ . '/assets/videos/');
    
    if ($realpath && $videos_dir && strpos($realpath, $videos_dir) === 0 && is_file($realpath)) {
        // Get file extension
        $ext = strtolower(pathinfo($realpath, PATHINFO_EXTENSION));
        
        // Set appropriate MIME type for videos
        $mime_types = [
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'mov' => 'video/quicktime'
        ];
        
        $mime_type = $mime_types[$ext] ?? 'application/octet-stream';
        
        // Send headers
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . filesize($realpath));
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=86400');
        
        // Output file
        readfile($realpath);
        exit;
    } else {
        // File not found
        http_response_code(404);
        echo '404 Not Found';
        exit;
    }
}

// Serve uploaded images FIRST (before general file_exists check)
if (preg_match('/^\/uploads\/work_order_images\/(.+)$/', $request_uri, $matches)) {
    $filename = $matches[1];
    $filepath = __DIR__ . '/uploads/work_order_images/' . $filename;
    
    // Security: prevent directory traversal
    $realpath = realpath($filepath);
    $uploads_dir = realpath(__DIR__ . '/uploads/work_order_images/');
    
    if ($realpath && $uploads_dir && strpos($realpath, $uploads_dir) === 0 && is_file($realpath)) {
        // Get file extension
        $ext = strtolower(pathinfo($realpath, PATHINFO_EXTENSION));
        
        // Set appropriate MIME type
        $mime_types = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif'
        ];
        
        $mime_type = $mime_types[$ext] ?? 'application/octet-stream';
        
        // Send headers
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . filesize($realpath));
        header('Cache-Control: public, max-age=31536000');
        
        // Output file
        readfile($realpath);
        exit;
    } else {
        // File not found
        http_response_code(404);
        echo '404 Not Found';
        exit;
    }
}

// If the request is for a PHP file, let PHP handle it
if (preg_match('/\.php$/', $request_uri)) {
    return false;
}

// For all other requests (CSS, JS, etc.), let PHP serve them normally
return false;
