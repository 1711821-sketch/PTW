<?php
// Handle push notification unsubscription
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

// Get endpoint from request
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['endpoint'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request data']);
    exit();
}

require_once 'database.php';

try {
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    $endpoint = $data['endpoint'];
    
    // Delete subscription from database
    $db->execute(
        "DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?",
        [$userId, $endpoint]
    );
    
    error_log("Deleted push subscription for user ID: $userId, endpoint: $endpoint");
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Subscription removed successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error removing push subscription: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to remove subscription']);
}
