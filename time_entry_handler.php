<?php
session_start();
require_once 'database.php';

// Only handle AJAX requests
if (!isset($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ingen handling specificeret.']);
    exit();
}

// Check authentication
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Du skal være logget ind.']);
    exit();
}

header('Content-Type: application/json');

$action = $_POST['action'];
$currentUser = $_SESSION['user'];
$currentRole = $_SESSION['role'] ?? '';

try {
    $timeEntry = new TimeEntry();
    
    // Get user ID from database
    $db = Database::getInstance();
    $user = $db->fetch("SELECT id FROM users WHERE username = ?", [$currentUser]);
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Bruger ikke fundet.']);
        exit();
    }
    $userId = $user['id'];
    
    switch ($action) {
        case 'save_time_entry':
            $workOrderId = intval($_POST['work_order_id'] ?? 0);
            $entryDate = $_POST['entry_date'] ?? '';
            $hours = floatval($_POST['hours'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            
            // Validate input
            if ($workOrderId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Ugyldig arbejdstilladelse ID.']);
                exit();
            }
            
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
                echo json_encode(['success' => false, 'message' => 'Ugyldig dato format.']);
                exit();
            }
            
            if ($hours < 0 || $hours > 24) {
                echo json_encode(['success' => false, 'message' => 'Timer skal være mellem 0 og 24.']);
                exit();
            }
            
            // Check if user has permission to register time for this work order
            if ($currentRole === 'entreprenor') {
                $firma = $_SESSION['entreprenor_firma'] ?? '';
                $woCheck = $db->fetch("SELECT id FROM work_orders WHERE id = ? AND entreprenor_firma = ?", [$workOrderId, $firma]);
                if (!$woCheck) {
                    echo json_encode(['success' => false, 'message' => 'Du har ikke tilladelse til at registrere timer på denne arbejdstilladelse.']);
                    exit();
                }
            } elseif (!in_array($currentRole, ['admin', 'opgaveansvarlig', 'drift'])) {
                echo json_encode(['success' => false, 'message' => 'Du har ikke tilladelse til at registrere timer.']);
                exit();
            }
            
            // Save time entry
            $timeEntry->addTimeEntry($workOrderId, $userId, $entryDate, $hours, $description);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Tidsregistrering gemt.',
                'data' => [
                    'work_order_id' => $workOrderId,
                    'entry_date' => $entryDate,
                    'hours' => $hours,
                    'description' => $description
                ]
            ]);
            break;
            
        case 'get_time_entries':
            $workOrderId = intval($_POST['work_order_id'] ?? 0);
            
            if ($workOrderId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Ugyldig arbejdstilladelse ID.']);
                exit();
            }
            
            // Check permission to view time entries
            if ($currentRole === 'entreprenor') {
                $firma = $_SESSION['entreprenor_firma'] ?? '';
                $woCheck = $db->fetch("SELECT id FROM work_orders WHERE id = ? AND entreprenor_firma = ?", [$workOrderId, $firma]);
                if (!$woCheck) {
                    echo json_encode(['success' => false, 'message' => 'Du har ikke tilladelse til at se timer på denne arbejdstilladelse.']);
                    exit();
                }
            } elseif (!in_array($currentRole, ['admin', 'opgaveansvarlig', 'drift'])) {
                echo json_encode(['success' => false, 'message' => 'Du har ikke tilladelse til at se timer.']);
                exit();
            }
            
            $entries = $timeEntry->getTimeEntriesForWorkOrder($workOrderId);
            $totalHours = $timeEntry->getTotalHoursForWorkOrder($workOrderId);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'entries' => $entries,
                    'total_hours' => $totalHours
                ]
            ]);
            break;
            
        case 'get_user_time_entry':
            $workOrderId = intval($_POST['work_order_id'] ?? 0);
            $entryDate = $_POST['entry_date'] ?? '';
            
            if ($workOrderId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
                echo json_encode(['success' => false, 'message' => 'Ugyldige parametre.']);
                exit();
            }
            
            // Check permission
            if ($currentRole === 'entreprenor') {
                $firma = $_SESSION['entreprenor_firma'] ?? '';
                $woCheck = $db->fetch("SELECT id FROM work_orders WHERE id = ? AND entreprenor_firma = ?", [$workOrderId, $firma]);
                if (!$woCheck) {
                    echo json_encode(['success' => false, 'message' => 'Du har ikke tilladelse til at se timer på denne arbejdstilladelse.']);
                    exit();
                }
            } elseif (!in_array($currentRole, ['admin', 'opgaveansvarlig', 'drift'])) {
                echo json_encode(['success' => false, 'message' => 'Du har ikke tilladelse til at se timer.']);
                exit();
            }
            
            $entry = $timeEntry->getTimeEntryForDate($workOrderId, $userId, $entryDate);
            
            echo json_encode([
                'success' => true,
                'data' => $entry
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ukendt handling.']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Time entry error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Der opstod en fejl. Prøv igen.']);
}
?>