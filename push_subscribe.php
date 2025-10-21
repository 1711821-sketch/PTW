<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

require_once 'database.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['subscription'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$subscription = $data['subscription'];
$endpoint = $subscription['endpoint'] ?? '';
$p256dh = $subscription['keys']['p256dh'] ?? '';
$auth = $subscription['keys']['auth'] ?? '';

if (empty($endpoint) || empty($p256dh) || empty($auth)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing subscription data']);
    exit();
}

try {
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        // Get user ID from username
        $user = $db->fetch("SELECT id FROM users WHERE username = ?", [$_SESSION['user']]);
        $userId = $user['id'] ?? null;
        $_SESSION['user_id'] = $userId;
    }
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Check if subscription already exists for this endpoint
    $existing = $db->fetch("SELECT id FROM push_subscriptions WHERE endpoint = ?", [$endpoint]);
    
    if ($existing) {
        // Update existing subscription
        $db->execute("
            UPDATE push_subscriptions 
            SET user_id = ?, p256dh_key = ?, auth_token = ?, user_agent = ?, updated_at = NOW()
            WHERE endpoint = ?
        ", [$userId, $p256dh, $auth, $userAgent, $endpoint]);
    } else {
        // Insert new subscription
        $db->execute("
            INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_token, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ", [$userId, $endpoint, $p256dh, $auth, $userAgent]);
    }
    
    echo json_encode(['success' => true]);
    error_log("Push subscription saved for user ID: $userId");
    
} catch (Exception $e) {
    error_log("Error saving push subscription: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
