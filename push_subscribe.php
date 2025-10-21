<?php
// Handle push notification subscription
session_start();

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get subscription data from request
$input = file_get_contents('php://input');
$subscription = json_decode($input, true);

if (!$subscription || !isset($subscription['endpoint'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid subscription data']);
    exit();
}

require_once 'database.php';

try {
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    
    // Extract subscription details
    $endpoint = $subscription['endpoint'];
    $p256dhKey = $subscription['keys']['p256dh'] ?? '';
    $authToken = $subscription['keys']['auth'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Check if subscription already exists
    $existing = $db->fetch(
        "SELECT id FROM push_subscriptions WHERE endpoint = ?",
        [$endpoint]
    );
    
    if ($existing) {
        // Update existing subscription
        $db->execute(
            "UPDATE push_subscriptions 
             SET user_id = ?, p256dh_key = ?, auth_token = ?, user_agent = ?, updated_at = NOW()
             WHERE endpoint = ?",
            [$userId, $p256dhKey, $authToken, $userAgent, $endpoint]
        );
        
        error_log("Updated push subscription for user ID: $userId");
    } else {
        // Insert new subscription
        $db->execute(
            "INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_token, user_agent) 
             VALUES (?, ?, ?, ?, ?)",
            [$userId, $endpoint, $p256dhKey, $authToken, $userAgent]
        );
        
        error_log("Created new push subscription for user ID: $userId");
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Subscription saved successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error saving push subscription: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save subscription']);
}
