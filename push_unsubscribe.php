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

if (!$data || !isset($data['endpoint'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$endpoint = $data['endpoint'];

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
    
    // SECURITY: Verify that the subscription belongs to the current user before deleting
    $subscription = $db->fetch("SELECT id, user_id FROM push_subscriptions WHERE endpoint = ?", [$endpoint]);
    
    if (!$subscription) {
        echo json_encode(['success' => true]); // Already deleted, no error
        exit();
    }
    
    if ($subscription['user_id'] !== $userId) {
        error_log("SECURITY VIOLATION: User $userId attempted to delete subscription belonging to user " . $subscription['user_id']);
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not authorized to delete this subscription']);
        exit();
    }
    
    // Delete subscription
    $db->execute("DELETE FROM push_subscriptions WHERE endpoint = ?", [$endpoint]);
    
    echo json_encode(['success' => true]);
    error_log("Push subscription removed: $endpoint");
    
} catch (Exception $e) {
    error_log("Error removing push subscription: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
