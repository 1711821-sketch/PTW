<?php
/**
 * Notifications API for PTW System
 * Handles fetching, creating, and marking notifications as read
 */

session_start();
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $db = Database::getInstance();

    // Use cached user_id from session for better performance
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    switch ($action) {
        case 'get':
            // Get unread notifications
            $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 10;
            $notifications = $db->fetchAll("
                SELECT id, type, title, message, link, created_at
                FROM notifications
                WHERE user_id = ?
                AND read_at IS NULL
                ORDER BY created_at DESC
                LIMIT ?
            ", [$userId, $limit]);

            // Get unread count
            $countResult = $db->fetch("
                SELECT COUNT(*) as count
                FROM notifications
                WHERE user_id = ?
                AND read_at IS NULL
            ", [$userId]);

            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => (int)$countResult['count']
            ]);
            break;

        case 'get_all':
            // Get all notifications (paginated)
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;

            $notifications = $db->fetchAll("
                SELECT id, type, title, message, link, read_at, created_at
                FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ", [$userId, $limit, $offset]);

            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'page' => $page
            ]);
            break;

        case 'mark_read':
            // Mark single notification as read
            $notificationId = $_POST['id'] ?? null;
            if (!$notificationId) {
                echo json_encode(['success' => false, 'message' => 'Missing notification ID']);
                exit;
            }

            $db->execute("
                UPDATE notifications
                SET read_at = NOW()
                WHERE id = ? AND user_id = ?
            ", [$notificationId, $userId]);

            echo json_encode(['success' => true]);
            break;

        case 'mark_all_read':
            // Mark all notifications as read
            $db->execute("
                UPDATE notifications
                SET read_at = NOW()
                WHERE user_id = ? AND read_at IS NULL
            ", [$userId]);

            echo json_encode(['success' => true]);
            break;

        case 'delete':
            // Delete notification
            $notificationId = $_POST['id'] ?? null;
            if (!$notificationId) {
                echo json_encode(['success' => false, 'message' => 'Missing notification ID']);
                exit;
            }

            $db->execute("
                DELETE FROM notifications
                WHERE id = ? AND user_id = ?
            ", [$notificationId, $userId]);

            echo json_encode(['success' => true]);
            break;

        case 'count':
            // Get unread count only
            $countResult = $db->fetch("
                SELECT COUNT(*) as count
                FROM notifications
                WHERE user_id = ?
                AND read_at IS NULL
            ", [$userId]);

            echo json_encode([
                'success' => true,
                'count' => (int)$countResult['count']
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("Notifications API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

/**
 * Helper function to create a notification (can be called from other PHP files)
 */
function createNotification($db, $userId, $type, $title, $message = null, $link = null) {
    try {
        $db->execute("
            INSERT INTO notifications (user_id, type, title, message, link)
            VALUES (?, ?, ?, ?, ?)
        ", [$userId, $type, $title, $message, $link]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Create notification for all users with specific role
 */
function notifyRole($db, $role, $type, $title, $message = null, $link = null) {
    try {
        $users = $db->fetchAll("SELECT id FROM users WHERE role = ?", [$role]);
        foreach ($users as $user) {
            createNotification($db, $user['id'], $type, $title, $message, $link);
        }
        return true;
    } catch (Exception $e) {
        error_log("Failed to notify role: " . $e->getMessage());
        return false;
    }
}
