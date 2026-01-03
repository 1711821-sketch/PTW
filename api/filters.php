<?php
/**
 * Filters API for PTW System
 * Handles saving and loading user filter presets
 */

session_start();
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    $db = Database::getInstance();

    // Use cached user_id from session for better performance
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    switch ($action) {
        case 'save':
            // Save new filter
            $name = trim($input['name'] ?? '');
            $filters = $input['filters'] ?? [];

            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Filter name required']);
                exit;
            }

            if (strlen($name) > 100) {
                echo json_encode(['success' => false, 'message' => 'Filter name too long']);
                exit;
            }

            // Check for duplicate name
            $existing = $db->fetch("
                SELECT id FROM saved_filters
                WHERE user_id = ? AND name = ?
            ", [$userId, $name]);

            if ($existing) {
                // Update existing
                $db->execute("
                    UPDATE saved_filters
                    SET filters = ?
                    WHERE id = ?
                ", [json_encode($filters), $existing['id']]);

                echo json_encode(['success' => true, 'id' => $existing['id'], 'updated' => true]);
            } else {
                // Create new
                $db->execute("
                    INSERT INTO saved_filters (user_id, name, filters)
                    VALUES (?, ?, ?)
                ", [$userId, $name, json_encode($filters)]);

                $newId = $db->lastInsertId();
                echo json_encode(['success' => true, 'id' => $newId]);
            }
            break;

        case 'get':
            // Get all saved filters for user
            $filters = $db->fetchAll("
                SELECT id, name, filters, created_at
                FROM saved_filters
                WHERE user_id = ?
                ORDER BY name
            ", [$userId]);

            echo json_encode(['success' => true, 'filters' => $filters]);
            break;

        case 'load':
            // Load specific filter
            $filterId = $input['id'] ?? $_GET['id'] ?? null;

            if (!$filterId) {
                echo json_encode(['success' => false, 'message' => 'Filter ID required']);
                exit;
            }

            $filter = $db->fetch("
                SELECT id, name, filters
                FROM saved_filters
                WHERE id = ? AND user_id = ?
            ", [$filterId, $userId]);

            if (!$filter) {
                echo json_encode(['success' => false, 'message' => 'Filter not found']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'filter' => [
                    'id' => $filter['id'],
                    'name' => $filter['name'],
                    'filters' => json_decode($filter['filters'], true)
                ]
            ]);
            break;

        case 'delete':
            // Delete filter
            $filterId = $input['id'] ?? null;

            if (!$filterId) {
                echo json_encode(['success' => false, 'message' => 'Filter ID required']);
                exit;
            }

            $db->execute("
                DELETE FROM saved_filters
                WHERE id = ? AND user_id = ?
            ", [$filterId, $userId]);

            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("Filters API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
