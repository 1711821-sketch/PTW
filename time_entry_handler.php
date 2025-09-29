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
        echo json_encode(['success' => false, 'message' => 'Bruger ikke fundet. Log venligst ud og ind igen.']);
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
            
            // Enhanced date validation - no future dates beyond today, no dates older than 1 year
            $entryDateTime = DateTime::createFromFormat('Y-m-d', $entryDate);
            $today = new DateTime();
            $oneYearAgo = (new DateTime())->sub(new DateInterval('P1Y'));
            
            if (!$entryDateTime || $entryDateTime > $today || $entryDateTime < $oneYearAgo) {
                echo json_encode(['success' => false, 'message' => 'Dato skal være inden for det sidste år og ikke i fremtiden.']);
                exit();
            }
            
            // Enhanced hours validation - allow quarter-hour increments, reasonable daily limits
            if ($hours < 0 || $hours > 24) {
                echo json_encode(['success' => false, 'message' => 'Timer skal være mellem 0 og 24.']);
                exit();
            }
            
            // Check for quarter-hour increments (0, 0.25, 0.5, 0.75, 1.0, etc.)
            $quarterHours = $hours * 4;
            if ($quarterHours !== floor($quarterHours)) {
                echo json_encode(['success' => false, 'message' => 'Timer skal være i kvarte-times intervaller (0.25, 0.5, 0.75, osv.).']);
                exit();
            }
            
            // Sanitize description input
            $description = htmlspecialchars(strip_tags($description), ENT_QUOTES, 'UTF-8');
            
            // Enhanced work order access validation
            $workOrder = $db->fetch("SELECT id, entreprenor_firma, status FROM work_orders WHERE id = ?", [$workOrderId]);
            if (!$workOrder) {
                echo json_encode(['success' => false, 'message' => 'Arbejdstilladelse ikke fundet.']);
                exit();
            }
            
            // Check if user has permission to register time for this work order
            if ($currentRole === 'entreprenor') {
                $firma = $_SESSION['entreprenor_firma'] ?? '';
                if ($workOrder['entreprenor_firma'] !== $firma) {
                    error_log("Unauthorized time entry attempt - User: $currentUser, Work Order: $workOrderId, User Firma: $firma, WO Firma: " . $workOrder['entreprenor_firma']);
                    echo json_encode(['success' => false, 'message' => 'Du har ikke tilladelse til at registrere timer på denne arbejdstilladelse.']);
                    exit();
                }
            } elseif (!in_array($currentRole, ['admin'])) {
                error_log("Unauthorized time entry attempt - User: $currentUser, Role: $currentRole, Work Order: $workOrderId");
                echo json_encode(['success' => false, 'message' => 'Kun admin og entreprenører kan registrere timer.']);
                exit();
            }
            
            // Rate limiting check - max 10 time entries per user per minute
            $recentEntries = $db->fetch("
                SELECT COUNT(*) as count 
                FROM time_entries 
                WHERE user_id = ? AND created_at > NOW() - INTERVAL '1 minute'
            ", [$userId]);
            
            if ($recentEntries && $recentEntries['count'] >= 10) {
                error_log("Rate limit exceeded - User: $currentUser, Recent entries: " . $recentEntries['count']);
                echo json_encode(['success' => false, 'message' => 'For mange registreringer. Vent venligst et øjeblik.']);
                exit();
            }
            
            // Save time entry
            $timeEntry->addTimeEntry($workOrderId, $userId, $entryDate, $hours, $description);
            
            // Log successful time entry for audit trail
            error_log("Time entry saved - User: $currentUser ($currentRole), Work Order: $workOrderId, Date: $entryDate, Hours: $hours");
            
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
            
            // Enhanced work order validation for viewing
            $workOrder = $db->fetch("SELECT id, entreprenor_firma, status FROM work_orders WHERE id = ?", [$workOrderId]);
            if (!$workOrder) {
                echo json_encode(['success' => false, 'message' => 'Arbejdstilladelse ikke fundet.']);
                exit();
            }
            
            // Check permission to view time entries
            if ($currentRole === 'entreprenor') {
                $firma = $_SESSION['entreprenor_firma'] ?? '';
                if ($workOrder['entreprenor_firma'] !== $firma) {
                    error_log("Unauthorized time view attempt - User: $currentUser, Work Order: $workOrderId, User Firma: $firma, WO Firma: " . $workOrder['entreprenor_firma']);
                    echo json_encode(['success' => false, 'message' => 'Du har ikke tilladelse til at se timer på denne arbejdstilladelse.']);
                    exit();
                }
            } elseif (!in_array($currentRole, ['admin', 'opgaveansvarlig', 'drift'])) {
                error_log("Unauthorized time view attempt - User: $currentUser, Role: $currentRole, Work Order: $workOrderId");
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
            
            // Enhanced work order validation for user time entry
            $workOrder = $db->fetch("SELECT id, entreprenor_firma, status FROM work_orders WHERE id = ?", [$workOrderId]);
            if (!$workOrder) {
                echo json_encode(['success' => false, 'message' => 'Arbejdstilladelse ikke fundet.']);
                exit();
            }
            
            // Check permission
            if ($currentRole === 'entreprenor') {
                $firma = $_SESSION['entreprenor_firma'] ?? '';
                if ($workOrder['entreprenor_firma'] !== $firma) {
                    error_log("Unauthorized user time entry view attempt - User: $currentUser, Work Order: $workOrderId, User Firma: $firma, WO Firma: " . $workOrder['entreprenor_firma']);
                    echo json_encode(['success' => false, 'message' => 'Du har ikke tilladelse til at se timer på denne arbejdstilladelse.']);
                    exit();
                }
            } elseif (!in_array($currentRole, ['admin', 'opgaveansvarlig', 'drift'])) {
                error_log("Unauthorized user time entry view attempt - User: $currentUser, Role: $currentRole, Work Order: $workOrderId");
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