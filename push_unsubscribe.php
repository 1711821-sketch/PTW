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
    $db->execute("DELETE FROM push_subscriptions WHERE endpoint = ?", [$endpoint]);
    
    echo json_encode(['success' => true]);
    error_log("Push subscription removed: $endpoint");
    
} catch (Exception $e) {
    error_log("Error removing push subscription: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
