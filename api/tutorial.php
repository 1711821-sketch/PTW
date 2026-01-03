<?php
/**
 * Tutorial API for Sikkerjob
 * Handles tour steps, tooltips, and progress tracking
 */

session_start();
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

$userRole = $_SESSION['role'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Load tutorial configuration
$tutorials = include __DIR__ . '/../config/tutorials.php';

try {
    $db = Database::getInstance();

    // Use cached user_id from session for better performance
    $userId = $_SESSION['user_id'] ?? null;

    switch ($action) {
        case 'get_tour':
            // Return tour steps for specific tour, filtered by role
            $tourId = $_GET['id'] ?? '';

            if (!isset($tutorials['tours'][$tourId])) {
                echo json_encode(['success' => false, 'message' => 'Tour not found']);
                exit;
            }

            $tour = $tutorials['tours'][$tourId];

            // Check if user's role can access this tour
            if (!in_array($userRole, $tour['roles']) && !in_array('all', $tour['roles'])) {
                echo json_encode(['success' => false, 'message' => 'Tour not available for your role']);
                exit;
            }

            // Filter steps by user role and device
            $filteredSteps = [];
            foreach ($tour['steps'] as $step) {
                // Check role filter on individual step
                if (isset($step['roles']) && !in_array($userRole, $step['roles']) && !in_array('all', $step['roles'])) {
                    continue;
                }

                // Remove role info from step (not needed on client)
                unset($step['roles']);
                unset($step['mobile_only']); // Let client handle this

                $filteredSteps[] = $step;
            }

            echo json_encode([
                'success' => true,
                'tour_id' => $tourId,
                'title' => $tour['title'],
                'description' => $tour['description'] ?? '',
                'steps' => $filteredSteps
            ]);
            break;

        case 'get_tooltips':
            // Return tooltips filtered by role
            $filteredTooltips = [];

            foreach ($tutorials['tooltips'] as $key => $tooltip) {
                $tooltipRoles = $tooltip['roles'] ?? ['all'];

                if (in_array('all', $tooltipRoles) || in_array($userRole, $tooltipRoles)) {
                    $filteredTooltips[] = [
                        'id' => $tooltip['id'],
                        'target' => $tooltip['target'],
                        'content' => $tooltip['content'],
                        'trigger' => $tooltip['trigger'] ?? 'hover'
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'tooltips' => $filteredTooltips
            ]);
            break;

        case 'complete_tour':
            // Mark tour as completed
            $tourId = $_POST['tour_id'] ?? '';

            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }

            if (empty($tourId)) {
                echo json_encode(['success' => false, 'message' => 'Tour ID required']);
                exit;
            }

            // Insert or ignore if already completed
            $db->execute("
                INSERT INTO tutorial_progress (user_id, tutorial_id)
                VALUES (?, ?)
                ON CONFLICT (user_id, tutorial_id) DO NOTHING
            ", [$userId, $tourId]);

            echo json_encode(['success' => true]);
            break;

        case 'get_progress':
            // Get user's completed tutorials
            if (!$userId) {
                echo json_encode(['success' => true, 'completed' => []]);
                exit;
            }

            $completed = $db->fetchAll("
                SELECT tutorial_id, completed_at
                FROM tutorial_progress
                WHERE user_id = ?
                ORDER BY completed_at DESC
            ", [$userId]);

            echo json_encode([
                'success' => true,
                'completed' => array_column($completed, 'tutorial_id'),
                'details' => $completed
            ]);
            break;

        case 'reset_progress':
            // Reset user's tutorial progress (for testing)
            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }

            $db->execute("DELETE FROM tutorial_progress WHERE user_id = ?", [$userId]);

            echo json_encode(['success' => true, 'message' => 'Progress reset']);
            break;

        case 'get_videos':
            // Get video list for user's role
            $rolePath = $tutorials['role_paths'][$userRole] ?? null;

            if (!$rolePath) {
                echo json_encode(['success' => true, 'videos' => []]);
                exit;
            }

            $videos = $rolePath['videos'] ?? [];

            // Add watched status from localStorage (client-side only)
            echo json_encode([
                'success' => true,
                'videos' => $videos,
                'recommended_tours' => $rolePath['recommended_tours'] ?? []
            ]);
            break;

        case 'get_all_tours':
            // Get all available tours for user's role
            $availableTours = [];

            foreach ($tutorials['tours'] as $key => $tour) {
                if (in_array($userRole, $tour['roles']) || in_array('all', $tour['roles'])) {
                    $availableTours[] = [
                        'id' => $key,
                        'title' => $tour['title'],
                        'description' => $tour['description'] ?? '',
                        'step_count' => count($tour['steps'])
                    ];
                }
            }

            // Check which are completed
            $completedIds = [];
            if ($userId) {
                $completed = $db->fetchAll("
                    SELECT tutorial_id FROM tutorial_progress WHERE user_id = ?
                ", [$userId]);
                $completedIds = array_column($completed, 'tutorial_id');
            }

            // Add completed status
            foreach ($availableTours as &$tour) {
                $tour['completed'] = in_array($tour['id'] . '_tour', $completedIds) ||
                                     in_array($tour['id'], $completedIds);
            }

            echo json_encode([
                'success' => true,
                'tours' => $availableTours,
                'total' => count($availableTours),
                'completed_count' => count(array_filter($availableTours, fn($t) => $t['completed']))
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("Tutorial API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
