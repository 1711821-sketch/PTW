<?php
/**
 * Server-Sent Events Handler for PTW System
 * Provides real-time updates for approvals and status changes
 */

session_start();
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../database.php';

// CRITICAL: Close session early to prevent blocking other requests
// PHP file-based sessions lock the session file, blocking all other requests
// from the same user until the session is released.
$currentUser = $_SESSION['user'] ?? '';
$currentRole = $_SESSION['role'] ?? '';
$userFirma = $_SESSION['entreprenor_firma'] ?? '';
$userId = $_SESSION['user_id'] ?? null;
session_write_close(); // Release session lock immediately

// Set SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Store last check timestamp
$lastCheck = isset($_GET['last']) ? (int)$_GET['last'] : time();

// Send initial connection event
sendEvent('connected', [
    'message' => 'Connected to PTW real-time updates',
    'timestamp' => time()
]);

// Keep connection alive and check for updates
$maxRuntime = 30; // Close connection after 30 seconds (client will reconnect)
$startTime = time();
$checkInterval = 3; // Check every 3 seconds

try {
    $db = Database::getInstance();

    while ((time() - $startTime) < $maxRuntime) {
        // Check for new approvals since last check
        $newApprovals = checkNewApprovals($db, $lastCheck, $currentRole, $userFirma);
        if (!empty($newApprovals)) {
            sendEvent('approval_update', $newApprovals);
        }

        // Check for status changes
        $statusChanges = checkStatusChanges($db, $lastCheck, $currentRole, $userFirma);
        if (!empty($statusChanges)) {
            sendEvent('status_change', $statusChanges);
        }

        // Check for new PTWs
        $newPTWs = checkNewPTWs($db, $lastCheck, $currentRole, $userFirma);
        if (!empty($newPTWs)) {
            sendEvent('new_ptw', $newPTWs);
        }

        // Check for new notifications for this user
        $notifications = checkNewNotifications($db, $lastCheck, $currentUser);
        if (!empty($notifications)) {
            sendEvent('notification', $notifications);
        }

        // Update last check time
        $lastCheck = time();

        // Send heartbeat to keep connection alive
        sendEvent('heartbeat', ['timestamp' => time()]);

        // Flush output
        if (ob_get_level()) {
            ob_flush();
        }
        flush();

        // Wait before next check
        sleep($checkInterval);

        // Check if client disconnected
        if (connection_aborted()) {
            break;
        }
    }
} catch (Exception $e) {
    sendEvent('error', ['message' => 'Server error occurred']);
    error_log("SSE Error: " . $e->getMessage());
}

// Send disconnect event
sendEvent('disconnect', ['message' => 'Connection timeout, please reconnect']);

/**
 * Send SSE event
 */
function sendEvent($event, $data) {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";

    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

/**
 * Check for new approvals
 */
function checkNewApprovals($db, $lastCheck, $role, $firma) {
    $timestamp = date('Y-m-d H:i:s', $lastCheck);

    $query = "
        SELECT id, work_order_no, approvals, updated_at
        FROM work_orders
        WHERE updated_at > ?
        AND status = 'active'
    ";

    $params = [$timestamp];

    // Filter by firma for entrepreneurs
    if (strtolower($role) === 'entreprenor' && $firma) {
        $query .= " AND entreprenor_firma = ?";
        $params[] = $firma;
    }

    $results = $db->fetchAll($query, $params);
    $updates = [];

    foreach ($results as $row) {
        $approvals = json_decode($row['approvals'] ?? '{}', true);
        if (!empty($approvals)) {
            $updates[] = [
                'id' => $row['id'],
                'work_order_no' => $row['work_order_no'],
                'approvals' => $approvals,
                'updated_at' => $row['updated_at']
            ];
        }
    }

    return $updates;
}

/**
 * Check for status changes
 */
function checkStatusChanges($db, $lastCheck, $role, $firma) {
    $timestamp = date('Y-m-d H:i:s', $lastCheck);

    $query = "
        SELECT id, work_order_no, status, status_dag, updated_at
        FROM work_orders
        WHERE updated_at > ?
    ";

    $params = [$timestamp];

    if (strtolower($role) === 'entreprenor' && $firma) {
        $query .= " AND entreprenor_firma = ?";
        $params[] = $firma;
    }

    $results = $db->fetchAll($query, $params);
    $changes = [];

    foreach ($results as $row) {
        $changes[] = [
            'id' => $row['id'],
            'work_order_no' => $row['work_order_no'],
            'status' => $row['status'],
            'status_dag' => $row['status_dag'],
            'updated_at' => $row['updated_at']
        ];
    }

    return $changes;
}

/**
 * Check for new PTWs
 */
function checkNewPTWs($db, $lastCheck, $role, $firma) {
    $timestamp = date('Y-m-d H:i:s', $lastCheck);

    $query = "
        SELECT id, work_order_no, description, entreprenor_firma, created_at
        FROM work_orders
        WHERE created_at > ?
    ";

    $params = [$timestamp];

    if (strtolower($role) === 'entreprenor' && $firma) {
        $query .= " AND entreprenor_firma = ?";
        $params[] = $firma;
    }

    $results = $db->fetchAll($query, $params);
    $newPTWs = [];

    foreach ($results as $row) {
        $newPTWs[] = [
            'id' => $row['id'],
            'work_order_no' => $row['work_order_no'],
            'description' => substr($row['description'] ?? '', 0, 100),
            'entreprenor_firma' => $row['entreprenor_firma'],
            'created_at' => $row['created_at']
        ];
    }

    return $newPTWs;
}

/**
 * Check for new notifications
 */
function checkNewNotifications($db, $lastCheck, $username) {
    // Check if notifications table exists
    try {
        $timestamp = date('Y-m-d H:i:s', $lastCheck);

        $results = $db->fetchAll("
            SELECT id, type, title, message, link, created_at
            FROM notifications
            WHERE user_id = (SELECT id FROM users WHERE username = ? LIMIT 1)
            AND created_at > ?
            AND read_at IS NULL
            ORDER BY created_at DESC
            LIMIT 10
        ", [$username, $timestamp]);

        return $results;
    } catch (Exception $e) {
        // Table doesn't exist yet
        return [];
    }
}
