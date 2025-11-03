<?php
// Displays a list of all PTW?r stored in the system. Entries can
// be filtered by status and edited or deleted (admin only). Only
// authenticated users can access this page.

session_start();
require_once 'auth_check.php';

// Set Danish timezone
date_default_timezone_set('Europe/Copenhagen');

// CSRF Token Functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requireCSRFToken() {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die('CSRF token validation failed. Refresh the page and try again.');
    }
}

// Generate CSRF token for this session
$csrf_token = generateCSRFToken();

// Include approval workflow widget
require_once 'approval_workflow_widget.php';

// SECURITY FIX: Handle AJAX approval requests with database access control
if (isset($_POST['ajax_approve']) && isset($_POST['approve_id']) && isset($_POST['role'])) {
    header('Content-Type: application/json');
    
    $approveId = $_POST['approve_id'];
    $approveRole = $_POST['role'];
    $sessionRole = $_SESSION['role'] ?? '';
    $currentUser = $_SESSION['user'] ?? '';
    
    // Normalize role names to lowercase for comparison
    $approveRoleLc = strtolower($approveRole);
    $sessionRoleLc = strtolower($sessionRole);
    
    // Check permissions
    if ($sessionRoleLc !== 'admin' && $sessionRoleLc !== $approveRoleLc) {
        error_log("AJAX approval denied - Insufficient permissions - User: $currentUser ($sessionRoleLc), WO ID: $approveId, Required Role: $approveRoleLc");
        echo json_encode([
            'success' => false,
            'message' => 'Du har ikke tilladelse til at godkende som denne rolle.'
        ]);
        exit();
    }
    
    try {
        require_once 'database.php';
        $db = Database::getInstance();
        
        // CRITICAL SECURITY FIX: Use database with proper access control
        $workOrder = $db->fetch("SELECT * FROM work_orders WHERE id = ?", [$approveId]);
        
        if (!$workOrder) {
            error_log("AJAX approval failed - Work order not found - User: $currentUser, WO ID: $approveId");
            echo json_encode([
                'success' => false,
                'message' => 'PTW ikke fundet.'
            ]);
            exit();
        }
        
        // CRITICAL: Check if entrepreneur is trying to approve another firm's work order
        if ($sessionRoleLc === 'entreprenor') {
            $userFirma = $_SESSION['entreprenor_firma'] ?? '';
            if ($workOrder['entreprenor_firma'] !== $userFirma) {
                error_log("SECURITY VIOLATION: Entrepreneur attempted AJAX approval of another firm's work order - User: $currentUser, User Firma: $userFirma, WO Firma: " . $workOrder['entreprenor_firma'] . ", WO ID: $approveId");
                echo json_encode([
                    'success' => false,
                    'message' => 'Du har ikke tilladelse til at godkende denne PTW.'
                ]);
                exit();
            }
        }
        
        // CRITICAL: Only allow approvals for ACTIVE work orders
        $woStatus = $workOrder['status'] ?? 'planning';
        if ($woStatus !== 'active') {
            error_log("AJAX approval denied - Work order not active - User: $currentUser, WO ID: $approveId, Status: $woStatus");
            $statusText = $woStatus === 'planning' ? 'Planlagt' : ($woStatus === 'completed' ? 'Afsluttet' : $woStatus);
            echo json_encode([
                'success' => false,
                'message' => 'Godkendelse er kun muligt for aktive PTW\'er. Denne PTW har status: ' . $statusText . '.'
            ]);
            exit();
        }
        
        $today = date('d-m-Y');
        $now = date('d-m-Y H:i');
        
        // Parse existing approvals and approval history
        $approvals = json_decode($workOrder['approvals'] ?? '{}', true) ?? [];
        $approvalHistory = json_decode($workOrder['approval_history'] ?? '[]', true) ?? [];
        
        // Check if already approved today
        if (isset($approvals[$approveRoleLc]) && $approvals[$approveRoleLc] === $today) {
            echo json_encode([
                'success' => false,
                'message' => 'PTW\'en er allerede godkendt for denne rolle i dag.'
            ]);
            exit();
        }
        
        // Add new approval
        $approvals[$approveRoleLc] = $today;
        $historyEntry = [
            'timestamp' => $now,
            'user' => $currentUser,
            'role' => $approveRoleLc
        ];
        
        // For entrepreneurs, add company name to approval history
        if ($sessionRoleLc === 'entreprenor') {
            $historyEntry['company'] = $_SESSION['entreprenor_firma'] ?? '';
        }
        
        $approvalHistory[] = $historyEntry;
        
        // Check if all three approvals are now in place (for today)
        $allApproved = (
            isset($approvals['opgaveansvarlig']) && $approvals['opgaveansvarlig'] === $today &&
            isset($approvals['drift']) && $approvals['drift'] === $today &&
            isset($approvals['entreprenor']) && $approvals['entreprenor'] === $today
        );
        
        // If all three have approved, automatically start work
        $autoStartFields = '';
        $autoStartParams = [];
        if ($allApproved) {
            $autoStartFields = ", status_dag = ?, ikon = ?, starttid = ?";
            $autoStartParams = ['aktiv_dag', 'green_pulse', date('Y-m-d H:i:s')];
        }
        
        // Update database
        $updated = $db->execute("
            UPDATE work_orders 
            SET approvals = ?, approval_history = ?, updated_at = NOW()" . $autoStartFields . "
            WHERE id = ?
        ", array_merge([
            json_encode($approvals),
            json_encode($approvalHistory)
        ], $autoStartParams, [$approveId]));
        
        if ($updated) {
            error_log("AJAX approval successful - User: $currentUser ($sessionRoleLc), WO ID: $approveId, Role: $approveRoleLc");
            echo json_encode([
                'success' => true,
                'message' => 'PTW\'en er blevet godkendt som ' . ucfirst($approveRole) . '.'
            ]);
        } else {
            error_log("AJAX approval failed - Database update failed - User: $currentUser, WO ID: $approveId");
            echo json_encode([
                'success' => false,
                'message' => 'Fejl ved gemning af godkendelse.'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("AJAX approval error - User: $currentUser, WO ID: $approveId, Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Der opstod en fejl ved godkendelse. Prøv igen.'
        ]);
    }
    
    exit();
}

// AJAX handler for updating daily work status (entrepreneurs only)
if (isset($_POST['ajax_work_status']) && isset($_POST['wo_id']) && isset($_POST['status'])) {
    header('Content-Type: application/json');
    
    $woId = $_POST['wo_id'];
    $newStatus = $_POST['status']; // 'working' or 'stopped'
    $sessionRole = $_SESSION['role'] ?? '';
    $currentUser = $_SESSION['user'] ?? '';
    
    // Only entrepreneurs can update work status
    if (strtolower($sessionRole) !== 'entreprenor') {
        echo json_encode([
            'success' => false,
            'message' => 'Kun entreprenører kan opdatere arbejdsstatus.'
        ]);
        exit();
    }
    
    try {
        require_once 'database.php';
        $db = Database::getInstance();
        
        // Get work order and verify ownership
        $workOrder = $db->fetch("SELECT * FROM work_orders WHERE id = ?", [$woId]);
        
        if (!$workOrder) {
            echo json_encode([
                'success' => false,
                'message' => 'PTW ikke fundet.'
            ]);
            exit();
        }
        
        // Verify entrepreneur owns this work order
        $userFirma = $_SESSION['entreprenor_firma'] ?? '';
        if ($workOrder['entreprenor_firma'] !== $userFirma) {
            error_log("SECURITY VIOLATION: Entrepreneur attempted to update work status of another firm's work order - User: $currentUser, User Firma: $userFirma, WO Firma: " . $workOrder['entreprenor_firma'] . ", WO ID: $woId");
            echo json_encode([
                'success' => false,
                'message' => 'Du har ikke tilladelse til at opdatere denne PTW.'
            ]);
            exit();
        }
        
        // Validate status
        if (!in_array($newStatus, ['working', 'stopped'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Ugyldig status.'
            ]);
            exit();
        }
        
        $today = date('d-m-Y');
        $now = date('Y-m-d H:i:s');
        
        // Create work status object
        $workStatus = [
            'date' => $today,
            'status' => $newStatus,
            'updated_at' => $now,
            'updated_by' => $currentUser,
            'firma' => $userFirma
        ];
        
        // Determine status_dag and ikon based on new status
        $status_dag = $newStatus === 'stopped' ? 'pause_dag' : 'aktiv_dag';
        $ikon = $newStatus === 'stopped' ? 'yellow' : 'green_pulse';
        $sluttid = $newStatus === 'stopped' ? $now : null;
        
        // Update database
        $updated = $db->execute("
            UPDATE work_orders 
            SET daily_work_status = ?, status_dag = ?, ikon = ?, sluttid = ?, updated_at = NOW()
            WHERE id = ?
        ", [
            json_encode($workStatus),
            $status_dag,
            $ikon,
            $sluttid,
            $woId
        ]);
        
        if ($updated) {
            $statusText = $newStatus === 'working' ? 'Arbejder på opgaven' : 'Stoppet for i dag';
            error_log("Work status updated - User: $currentUser, WO ID: $woId, Status: $newStatus");
            echo json_encode([
                'success' => true,
                'message' => 'Arbejdsstatus opdateret: ' . $statusText,
                'status' => $newStatus,
                'icon' => $newStatus === 'working' ? '🔨' : '⏹️'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Fejl ved opdatering af arbejdsstatus.'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Work status update error - User: $currentUser, WO ID: $woId, Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Der opstod en fejl. Prøv igen.'
        ]);
    }
    
    exit();
}

// Handle image upload from card view
$upload_success = '';
$upload_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image']) && isset($_POST['wo_id'])) {
    // SECURITY: Validate CSRF token first
    requireCSRFToken();
    
    $woId = $_POST['wo_id'];
    $currentUser = $_SESSION['user'] ?? '';
    $currentRole = $_SESSION['role'] ?? '';
    $userFirma = $_SESSION['entreprenor_firma'] ?? '';
    
    require_once 'database.php';
    $db = Database::getInstance();
    
    // Get work order to verify ownership
    $workOrder = $db->fetch("SELECT * FROM work_orders WHERE id = ?", [$woId]);
    
    if (!$workOrder) {
        $upload_error = 'PTW ikke fundet.';
    }
    // Security check: Only entrepreneurs can upload
    elseif ($currentRole !== 'entreprenor') {
        $upload_error = 'Kun entreprenører kan uploade billeder.';
    }
    // Security check: Entrepreneur can only upload to their own firm's work orders
    elseif ($workOrder['entreprenor_firma'] !== $userFirma) {
        $upload_error = 'Du kan kun uploade billeder til dit eget firmas PTW\'er.';
        error_log("SECURITY VIOLATION: Entrepreneur $currentUser attempted to upload image to another firm's work order. User Firma: $userFirma, WO Firma: " . $workOrder['entreprenor_firma']);
    }
    // SECURITY CHECK: Verify PTW is still active
    elseif ($workOrder['status'] !== 'active') {
        $upload_error = 'Upload af billeder er kun muligt for aktive PTW\'er.';
        error_log("Upload rejected - PTW not active - User: $currentUser, WO ID: $woId, Status: " . $workOrder['status']);
    }
    // Check if file was uploaded
    elseif (!isset($_FILES['completion_image']) || $_FILES['completion_image']['error'] === UPLOAD_ERR_NO_FILE) {
        $upload_error = 'Ingen fil valgt.';
    }
    elseif ($_FILES['completion_image']['error'] !== UPLOAD_ERR_OK) {
        $upload_error = 'Fejl ved upload af fil.';
    }
    else {
        $file = $_FILES['completion_image'];
        
        // Validate MIME type (browser-friendly images only)
        $allowed_mime_types = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $mime_to_extension = $allowed_mime_types;
        
        if (!isset($mime_to_extension[$mime_type])) {
            $upload_error = 'Kun billedfiler der kan vises i browsere er tilladt (JPEG, PNG, GIF, WebP, AVIF). iPhone-brugere: Indstil kameraet til at gemme som JPEG i Indstillinger → Kamera → Formater → Mest kompatibel.';
        }
        // Validate file size (max 50MB for high-resolution smartphone images)
        elseif ($file['size'] > 52428800) {
            $upload_error = 'Billedet må ikke være større end 50MB.';
        }
        else {
            // SECURITY: Use extension based on MIME type, NOT user-supplied filename
            $safe_extension = $mime_to_extension[$mime_type];
            
            // Generate secure random filename with fallback error handling
            try {
                $random_suffix = bin2hex(random_bytes(8));
            } catch (Exception $e) {
                $upload_error = 'Systemfejl ved generering af sikkert filnavn. Prøv igen.';
                error_log("Failed to generate random bytes for filename - User: $currentUser, WO ID: " . $workOrder['id'] . ", Error: " . $e->getMessage());
                $random_suffix = null;
            }
            
            if ($random_suffix === null) {
                // Error already set above, skip upload
            } else {
                $filename = 'wo_' . $workOrder['id'] . '_' . time() . '_' . $random_suffix . '.' . $safe_extension;
                $upload_path = __DIR__ . '/uploads/work_order_images/' . $filename;
            
            // Ensure upload directory exists
            $upload_dir = __DIR__ . '/uploads/work_order_images';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Add filename to completion_images array in database
                $current_images = $workOrder['completion_images'] ?? [];
                if (is_string($current_images)) {
                    $current_images = json_decode($current_images, true) ?? [];
                }
                $current_images[] = $filename;
                
                try {
                    $db->execute("
                        UPDATE work_orders 
                        SET completion_images = ?, updated_at = NOW()
                        WHERE id = ?
                    ", [json_encode($current_images), $workOrder['id']]);
                    
                    $upload_success = 'Billedet er uploadet med succes!';
                    error_log("Image uploaded successfully - User: $currentUser, WO ID: " . $workOrder['id'] . ", Filename: $filename");
                } catch (Exception $e) {
                    $upload_error = 'Fejl ved gemning af billedet i databasen.';
                    error_log("Error saving uploaded image to database: " . $e->getMessage());
                    @unlink($upload_path);
                }
            } else {
                $upload_error = 'Fejl ved gemning af filen.';
                error_log("Failed to move uploaded file - User: $currentUser, WO ID: " . $workOrder['id']);
            }
            }
        }
    }
}

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$role = $_SESSION['role'] ?? 'user';

// Enhanced access control using database instead of JSON files
require_once 'database.php';

$entries = [];
$currentUser = $_SESSION['user'] ?? '';
$currentRole = $_SESSION['role'] ?? '';

// Pagination settings
$itemsPerPage = 20;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;
$totalItems = 0;
$totalPages = 0;

try {
    $db = Database::getInstance();
    
    // Apply role-based access control at database level
    if ($currentRole === 'entreprenor') {
        $firma = $_SESSION['entreprenor_firma'] ?? '';
        if ($firma !== '') {
            $today = date('d-m-Y');
            
            // Get total count for pagination
            $countResult = $db->fetch("
                SELECT COUNT(*) as total FROM work_orders 
                WHERE entreprenor_firma = ? 
                AND status = 'active'
                AND approvals::jsonb->>'opgaveansvarlig' = ?
                AND approvals::jsonb->>'drift' = ?
            ", [$firma, $today, $today]);
            $totalItems = $countResult['total'] ?? 0;
            $totalPages = ceil($totalItems / $itemsPerPage);
            
            // Only load work orders for this entrepreneur's firm that are:
            // 1. In 'active' status
            // 2. Approved by both opgaveansvarlig and drift TODAY
            $entries = $db->fetchAll("
                SELECT * FROM work_orders 
                WHERE entreprenor_firma = ? 
                AND status = 'active'
                AND approvals::jsonb->>'opgaveansvarlig' = ?
                AND approvals::jsonb->>'drift' = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ", [$firma, $today, $today, $itemsPerPage, $offset]);
            
            error_log("Work order access - Entrepreneur: $currentUser, Firma: $firma, Page: $currentPage, Count: " . count($entries) . ", Total: $totalItems");
        } else {
            // If no firm is defined, show no entries
            $entries = [];
            error_log("Work order access denied - Entrepreneur: $currentUser, No firma defined");
        }
    } elseif (in_array($currentRole, ['admin', 'opgaveansvarlig', 'drift'])) {
        // Get total count for pagination
        $countResult = $db->fetch("SELECT COUNT(*) as total FROM work_orders");
        $totalItems = $countResult['total'] ?? 0;
        $totalPages = ceil($totalItems / $itemsPerPage);
        
        // Admin, opgaveansvarlig and drift can see all work orders
        $entries = $db->fetchAll("
            SELECT * FROM work_orders 
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ", [$itemsPerPage, $offset]);
        
        error_log("Work order access - User: $currentUser, Role: $currentRole, Page: $currentPage, Count: " . count($entries) . ", Total: $totalItems");
    } else {
        // Unknown or unauthorized role
        $entries = [];
        error_log("Work order access denied - User: $currentUser, Role: $currentRole, Unauthorized role");
    }
    
    // Convert database result to format expected by frontend
    foreach ($entries as &$entry) {
        // Ensure all expected fields exist
        if (!isset($entry['approvals'])) {
            $entry['approvals'] = [];
        }
        if (!isset($entry['approval_history'])) {
            $entry['approval_history'] = [];
        }
        // Convert JSON strings back to arrays if needed
        if (is_string($entry['approvals'])) {
            $entry['approvals'] = json_decode($entry['approvals'], true) ?? [];
        }
        if (is_string($entry['approval_history'])) {
            $entry['approval_history'] = json_decode($entry['approval_history'], true) ?? [];
        }
    }
    // CRITICAL: Unset reference to prevent PHP from keeping $entry bound to the last element
    unset($entry);
    
} catch (Exception $e) {
    error_log("Database error in view_wo.php: " . $e->getMessage());
    $entries = [];
}

// For legacy compatibility, also set the data_file variable for approval handling
$data_file = __DIR__ . '/wo_data.json';

$today = date('d-m-Y');
$now = date('d-m-Y H:i');

// Note: GET-based approval system removed to prevent conflicts with AJAX approval system
// All approvals now handled via AJAX requests for better user experience

// Handle deletion (admin only) - SECURITY FIX: Use database with proper access control
$msg = '';
if ($role === 'admin' && isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    try {
        // SECURITY FIX: Use database with proper access control for deletions
        $workOrder = $db->fetch("SELECT * FROM work_orders WHERE id = ?", [$delete_id]);
        
        if (!$workOrder) {
            error_log("Delete attempt failed - Work order not found - User: $currentUser, WO ID: $delete_id");
            $msg = 'Kunne ikke finde PTW til sletning.';
        } else {
            // Delete work order and all related entries (time entries and SJA entries)
            $db->execute("DELETE FROM time_entries WHERE work_order_id = ?", [$delete_id]);
            $db->execute("DELETE FROM sja_entries WHERE work_order_id = ?", [$delete_id]);
            $deleted = $db->execute("DELETE FROM work_orders WHERE id = ?", [$delete_id]);
            
            if ($deleted) {
                error_log("Work order deleted successfully - User: $currentUser, WO ID: $delete_id, WO Firma: " . $workOrder['entreprenor_firma']);
                
                // Update the entries array for display purposes by reloading from database
                if ($currentRole === 'entreprenor') {
                    $firma = $_SESSION['entreprenor_firma'] ?? '';
                    if ($firma !== '') {
                        $entries = $db->fetchAll("SELECT * FROM work_orders WHERE entreprenor_firma = ? ORDER BY created_at DESC", [$firma]);
                    } else {
                        $entries = [];
                    }
                } elseif (in_array($currentRole, ['admin', 'opgaveansvarlig', 'drift'])) {
                    $entries = $db->fetchAll("SELECT * FROM work_orders ORDER BY created_at DESC");
                } else {
                    $entries = [];
                }
                
                $msg = 'PTW er blevet slettet.';
            } else {
                error_log("Delete failed - Database error - User: $currentUser, WO ID: $delete_id");
                $msg = 'Fejl ved sletning af PTW.';
            }
        }
        
    } catch (Exception $e) {
        error_log("Delete error - User: $currentUser, WO ID: $delete_id, Error: " . $e->getMessage());
        $msg = 'Fejl ved sletning af PTW.';
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Alle PTW'er</title>
    <?php include 'pwa-head.php'; ?>
    <!-- Import the global stylesheet for a modern look -->
    <link rel="stylesheet" href="style.css">
    <script src="navigation.js"></script>
    <style>
        /* Time Modal Styles */
        .time-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .time-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        .time-modal-content {
            position: relative;
            background: var(--background-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            z-index: 10001;
        }
        
        .time-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
        }
        
        .time-modal-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.25rem;
        }
        
        .time-modal-close {
            background: none;
            border: none;
            font-size: 2rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            transition: var(--transition);
        }
        
        .time-modal-close:hover {
            background: rgba(0, 0, 0, 0.05);
            color: var(--text-primary);
        }
        
        .time-modal-body {
            padding: 1.5rem;
        }

        /* Card Counter Bar - Desktop (top position) */
        .card-counter {
            position: sticky;
            top: 0;
            z-index: 100;
            width: 100%;
            background: #f5f5f5;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            font-size: 13px;
            font-weight: 500;
            color: #555;
            padding: 8px 1rem;
            margin-bottom: 0.25rem;
            display: none;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        
        /* Mobile: bottom sticky footer with compact design */
        @media (max-width: 768px) {
            .card-counter {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                top: auto;
                margin: 0;
                padding: 8px 0.75rem;
                padding-bottom: calc(8px + env(safe-area-inset-bottom, 0px));
                background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
                color: white;
                font-size: 13px;
                font-weight: 500;
                box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.12);
                gap: 0.75rem;
                border-radius: 0;
            }
        }

        .card-counter.visible {
            display: flex;
        }
        
        .counter-text {
            text-align: center;
            user-select: none;
        }
        
        .counter-nav-btn {
            background: #e0e0e0;
            border: none;
            color: #555;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }
        
        /* Mobile: compact buttons with white styling */
        @media (max-width: 768px) {
            .counter-nav-btn {
                background: rgba(255, 255, 255, 0.95);
                color: #1976d2;
                width: 32px;
                height: 32px;
                font-size: 1rem;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
            }
            
            .counter-nav-btn:active {
                background: rgba(255, 255, 255, 0.85);
            }
        }
        
        .counter-nav-btn:hover {
            background: #d0d0d0;
            transform: scale(1.05);
        }
        
        .counter-nav-btn:active {
            transform: scale(0.95);
        }
        
        .counter-nav-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
            background: #e0e0e0;
        }
        
        @media (max-width: 768px) {
            .counter-nav-btn:disabled {
                background: rgba(255, 255, 255, 0.4);
            }
        }

        /* Modern Card Slider Styles */
        .card-view-wrapper {
            position: relative;
            width: 100%;
            padding: 0.25rem 0;
        }
        
        @media (max-width: 768px) {
            .card-view-wrapper {
                padding: 0;
                padding-bottom: calc(52px + env(safe-area-inset-bottom, 0px));
            }
        }

        .slider-container {
            position: relative;
            width: 100%;
            overflow: hidden;
        }

        .card-slider {
            display: flex;
            gap: 0;
            overflow-x: scroll;
            overflow-y: hidden;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            padding: 0;
            width: 100%;
            scrollbar-width: none;
            -ms-overflow-style: none;
            scroll-padding: 0;
        }

        .card-slider::-webkit-scrollbar {
            display: none;
        }


        .work-permit-card {
            flex: 0 0 100%;
            width: 100%;
            min-width: 100%;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 0;
            margin: 10px 0;
            scroll-snap-align: start;
            scroll-snap-stop: always;
            display: flex;
            flex-direction: column;
            transition: transform 0.4s ease, opacity 0.3s ease, box-shadow 0.2s ease;
            overflow: hidden;
        }

        .work-permit-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }
        
        /* Modern Card Header */
        .card-header-modern {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            padding: 1.25rem;
            color: white;
        }
        
        .card-title-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .card-main-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
        }
        
        .card-status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .work-status-badge {
            font-size: 1.3em;
            margin-left: auto;
        }
        
        /* Card Content Container */
        .card-content-modern {
            padding: 1rem;
        }
        
        /* Card Section (Collapsible Sections) */
        .card-section {
            margin-bottom: 0.75rem;
            border-radius: 8px;
            overflow: hidden;
            background: #f8f9fa;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            background: rgba(25, 118, 210, 0.05);
            border-left: 4px solid #1976d2;
            cursor: pointer;
            user-select: none;
            transition: all 0.2s ease;
        }
        
        .section-header:hover {
            background: rgba(25, 118, 210, 0.1);
        }
        
        .section-header h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #1976d2;
        }
        
        .toggle-icon {
            font-size: 0.9rem;
            transition: transform 0.3s ease;
            color: #1976d2;
            font-weight: bold;
        }
        
        .section-content {
            display: none;
            padding: 1rem;
            background: white;
            animation: fadeSlideDown 0.3s ease;
            opacity: 0;
        }
        
        .section-content.expanded {
            display: block;
            animation: fadeSlideDown 0.3s ease forwards;
            opacity: 1;
        }
        
        @keyframes fadeSlideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Info Grid for Basic Information */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .info-item.full-width {
            grid-column: 1 / -1;
        }
        
        .info-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 0.95rem;
            color: #333;
            font-weight: 500;
        }
        
        /* Images Grid for Documentation Images */
        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .image-item {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .image-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }
        
        .image-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            display: block;
        }
        
        .no-images-text {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 2rem 0;
            margin: 0;
        }
        
        .upload-hint {
            margin-top: 1rem;
            padding: 0.75rem;
            background: rgba(25, 118, 210, 0.05);
            border-radius: 6px;
            border-left: 3px solid #1976d2;
        }
        
        .upload-hint p {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
        }
        
        .images-count {
            font-size: 0.85rem;
            color: #666;
            font-weight: 500;
        }
        
        /* Time Summary Badge */
        .time-summary {
            font-size: 0.85rem;
            color: #666;
            font-weight: 500;
        }

        /* Desktop: centered card with max-width */
        @media (min-width: 1024px) {
            .slider-container {
                max-width: 800px;
                margin: 0 auto;
            }

            .card-slider {
                scroll-padding: 0;
                padding: 0;
            }

            .work-permit-card {
                flex: 0 0 800px;
                width: 800px;
                min-width: 800px;
                max-width: 800px;
                scroll-snap-align: start;
                border-radius: 16px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }

            .card-counter {
                padding: 8px 1rem;
            }
            
            .counter-nav-btn {
                width: 36px;
                height: 36px;
            }
        }

        .card-header {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-light);
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
            flex-wrap: wrap;
        }

        .card-title-text {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0;
            width: 100%;
        }

        .card-title-text h3 {
            margin: 0;
            font-size: 18px;
            color: #1976d2;
            font-weight: 600;
            display: inline-block;
            margin-right: 8px;
        }

        .card-header-description {
            margin: 0;
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            width: 100%;
            margin-top: 0.3rem;
        }
        
        .card-header-description.expanded {
            -webkit-line-clamp: unset;
        }
        
        .description-toggle {
            color: #1976d2;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: underline;
            display: inline-block;
            margin-top: 0.25rem;
        }

        .card-status {
            display: inline-block;
            align-items: center;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .card-status.status-planlagt {
            background: #1976d2;
            color: white;
        }

        .card-status.status-aktiv {
            background: #2ecc71;
            color: white;
        }

        .card-status.status-afsluttet {
            background: #6b7280;
            color: white;
        }

        .card-p-description {
            margin-top: 0.4rem;
            padding: 0.4rem;
            background: #fafafa;
            border-left: 3px solid #1976d2;
            border-radius: 4px;
            font-size: 0.85rem;
        }

        .card-p-description strong {
            color: #1976d2;
        }

        .card-divider {
            margin: 0;
            border: none;
            border-top: 1px solid var(--border-light);
        }

        .card-content {
            padding: 0.75rem;
            flex: 1;
        }

        .card-details {
            margin-bottom: 0.75rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }

        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 0.4rem;
            padding: 10px;
            background: #f7f7f7;
            border-radius: 10px;
            margin-top: 8px;
        }

        .detail-item:last-child {
            margin-bottom: 0;
        }

        .detail-label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .detail-value {
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.3;
        }

        .card-approvals {
            margin-bottom: 0.75rem;
            padding: 0;
        }

        .card-approvals-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.03) 0%, rgba(16, 185, 129, 0.03) 100%);
            border-radius: 6px;
            cursor: pointer;
            user-select: none;
        }
        
        .card-approvals-header:hover {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.06) 0%, rgba(16, 185, 129, 0.06) 100%);
        }

        .card-approvals h4 {
            margin: 0;
            font-size: 0.95rem;
            color: var(--text-primary);
        }
        
        .approvals-summary {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .toggle-icon {
            transition: transform 0.2s ease;
        }
        
        .toggle-icon.expanded {
            transform: rotate(180deg);
        }

        .approval-grid {
            display: none;
            flex-direction: column;
            gap: 0.5rem;
            padding: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .approval-grid.expanded {
            display: flex;
        }

        .approval-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .approval-label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.85rem;
            min-width: 100px;
        }

        .approval-status {
            display: inline-flex;
            align-items: center;
            padding: 0.3rem 0.6rem;
            border-radius: 16px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .approval-status.approved {
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 8px;
            padding: 8px 10px;
            margin-top: 10px;
            font-weight: 500;
        }

        .approval-status.pending {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .approval-item .button {
            margin-left: auto;
        }

        .card-time-tracking {
            padding: 0;
            margin-bottom: 0.75rem;
        }

        .card-time-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            background: rgba(249, 115, 22, 0.03);
            border-radius: 6px;
            cursor: pointer;
            user-select: none;
        }
        
        .card-time-header:hover {
            background: rgba(249, 115, 22, 0.06);
        }

        .card-time-tracking h4 {
            margin: 0;
            font-size: 0.95rem;
            color: var(--text-primary);
        }
        
        .time-summary {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .time-entry-section {
            display: none;
            padding: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .time-entry-section.expanded {
            display: block;
        }

        .card-actions {
            display: flex;
            gap: 6px;
            padding: 0.75rem;
            border-top: 1px solid var(--border-light);
            background: rgba(0, 0, 0, 0.01);
            margin-top: 12px;
            align-items: center;
        }

        .card-actions .button {
            flex: 1;
            min-height: 32px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.2s ease;
            padding: 10px;
        }

        .card-actions .button:active {
            transform: scale(0.98);
        }

        /* Mobile optimizations */
        @media (max-width: 768px) {
            .work-permit-card {
                max-width: 100%;
            }

            .card-title-text h3 {
                font-size: 18px;
            }

            .card-status {
                padding: 2px 10px;
                font-size: 13px;
            }
            
            /* Single column layout for details on mobile */
            .card-details {
                grid-template-columns: 1fr;
            }

            .detail-label {
                min-width: 110px;
                font-size: 0.85rem;
            }

            .detail-value {
                font-size: 0.85rem;
            }

            .card-actions {
                flex-wrap: wrap;
            }

            .card-actions .button {
                flex: 1 1 auto;
                min-height: 36px;
            }
            
            /* Reducer afstand mellem topbjælke og navigation kun på mobil */
            .container {
                padding-top: 0.5rem;
                margin-top: 0;
            }
            
            .header-section {
                margin-bottom: 0.75rem;
            }
        }

        /* ========== NEW MODERN BUTTON STYLES ========== */
        
        /* Action Buttons Group (List View) */
        .action-buttons-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Icon Buttons */
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            background: var(--background-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            font-size: 1.1rem;
        }

        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-icon:active {
            transform: translateY(0);
        }

        .btn-icon .icon {
            display: block;
        }

        .btn-view {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-edit {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .btn-time {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .btn-time:hover {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
        }

        /* Work Status Buttons (Start/Stop) */
        .btn-work {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: none;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-work .icon {
            font-size: 1.1rem;
        }

        .btn-start {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-start:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            transform: translateY(-2px);
        }

        .btn-stop {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .btn-stop:hover {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
            transform: translateY(-2px);
        }

        /* Approval Rows (List View) */
        .approval-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
        }

        .approval-row:last-child {
            margin-bottom: 0;
        }

        .approval-label {
            font-weight: 600;
            color: var(--text-secondary);
            min-width: 38px;
            font-size: 0.85rem;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        .approval-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .approval-badge.approved {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .approval-badge.pending {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        /* NEW: Vertical Approval List for Card View */
        .approval-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 0.5rem 0;
        }
        
        .approval-step {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        }
        
        .approval-step.approved {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-color: #059669;
        }
        
        .approval-step.pending {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        .approval-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.25rem;
            font-weight: bold;
        }
        
        .approval-step.approved .approval-icon {
            background: #10b981;
        }
        
        .approval-step.pending .approval-icon {
            background: #e5e7eb;
        }
        
        .check-icon {
            color: white;
            font-size: 1.25rem;
        }
        
        .pending-icon {
            color: #9ca3af;
            font-size: 1.5rem;
        }
        
        .approval-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .approval-name {
            font-weight: 600;
            font-size: 1rem;
            color: #1f2937;
            margin-bottom: 0.25rem;
            line-height: 1.3;
        }
        
        .approval-date {
            font-size: 0.8rem;
            color: #6b7280;
            font-weight: 400;
        }
        
        .approval-status-text {
            font-size: 0.8rem;
            color: #9ca3af;
            font-style: italic;
        }
        
        .approval-btn {
            flex-shrink: 0;
            padding: 0.6rem 1.25rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        
        .approval-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .approval-btn:active {
            transform: translateY(0);
        }
        
        .approval-count {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 600;
        }
        
        /* Mobile responsive for approval list */
        @media (max-width: 640px) {
            .approval-list {
                gap: 1.25rem;
            }
            
            .approval-step {
                padding: 1rem;
                gap: 0.75rem;
                flex-wrap: wrap;
            }
            
            .approval-icon {
                width: 36px;
                height: 36px;
                font-size: 1.1rem;
            }
            
            .approval-name {
                font-size: 0.95rem;
            }
            
            .approval-date,
            .approval-status-text {
                font-size: 0.75rem;
            }
            
            .approval-btn {
                width: 100%;
                padding: 0.75rem;
                font-size: 0.9rem;
                margin-top: 0.5rem;
            }
        }

        /* Approve Buttons (List View) */
        .btn-approve {
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            border: none;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-approve:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(16, 185, 129, 0.3);
        }

        .btn-approve:active {
            transform: translateY(0);
        }

        /* Approve Buttons (Card View) */
        .btn-approve-card {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: none;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-left: auto;
            white-space: nowrap;
        }

        .btn-approve-card:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-approve-card:active {
            transform: scale(0.98);
        }

        /* Mobile Responsive Styles for New Card Design */
        @media (max-width: 768px) {
            .card-header-modern {
                padding: 1rem;
            }
            
            .card-main-title {
                font-size: 1.25rem;
            }
            
            .card-content-modern {
                padding: 0.75rem;
            }
            
            .section-header {
                padding: 0.6rem 0.8rem;
            }
            
            .section-header h4 {
                font-size: 0.9rem;
            }
            
            .section-content {
                padding: 0.75rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .info-label {
                font-size: 0.75rem;
            }
            
            .info-value {
                font-size: 0.9rem;
            }
            
            .images-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 0.75rem;
            }
            
            .image-item img {
                height: 120px;
            }
            
            .card-actions {
                padding: 0.75rem;
                gap: 0.5rem;
            }
            
            .action-buttons-group {
                gap: 0.4rem;
            }

            .btn-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }

            .btn-work {
                padding: 0.6rem 1.2rem;
                font-size: 1rem;
                min-height: 44px;
            }

            .btn-approve {
                padding: 0.4rem 0.85rem;
                font-size: 0.9rem;
                min-height: 40px;
            }

            .btn-approve-card {
                padding: 0.6rem 1.2rem;
                font-size: 1rem;
                min-height: 44px;
            }

            /* Hide filter buttons on smartphones */
            .filter-group {
                display: none;
            }

            /* Hide header title and view toggle on smartphones */
            .header-section h1 {
                display: none;
            }
            
            .view-toggle {
                display: none;
            }
        }
        
        /* ========== IMAGE UPLOAD FORM STYLING ========== */
        .image-upload-section {
            padding: 0.75rem;
        }
        
        .upload-form-card {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .upload-input-group {
            flex: 1;
            min-width: 200px;
            position: relative;
        }
        
        .file-input {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }
        
        .file-label {
            display: inline-block;
            padding: 0.6rem 1.2rem;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            text-align: center;
            width: 100%;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
        }
        
        .file-label:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }
        
        .file-label:active {
            transform: translateY(0);
        }
        
        .upload-restricted-notice {
            color: #666;
            font-size: 0.9rem;
            padding: 0.75rem;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 6px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .upload-form-card {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .upload-input-group {
                width: 100%;
                min-width: 0;
            }
            
            .upload-form-card .button {
                width: 100%;
            }
        }
        
        /* Pagination Styles */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
            margin: 2rem 0;
            padding: 1.5rem;
            background: var(--background-primary);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }
        
        .card-pagination {
            margin-top: 1rem;
        }
        
        .pagination-btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-md);
            font-weight: 500;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }
        
        .pagination-btn:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .pagination-btn:active {
            transform: translateY(0);
        }
        
        .pagination-info {
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .pagination {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
            
            .pagination-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navigation.php'; ?>
    <div class="container">
    <div class="header-section">
        <h1>PTW-oversigt</h1>
        <div class="view-toggle">
            <button id="listViewBtn" class="view-btn active" data-view="list">
                📋 Liste
            </button>
            <button id="cardViewBtn" class="view-btn" data-view="card">
                🗂️ Boksvisning
            </button>
        </div>
    </div>
    
    <!-- Notification system for AJAX feedback -->
    <div id="notificationContainer" class="notification-container"></div>
    
    <!-- Upload success/error messages -->
    <?php if ($upload_success): ?>
        <div class="alert alert-success" style="margin-bottom: 1rem;">
            ✅ <?php echo htmlspecialchars($upload_success); ?>
        </div>
    <?php endif; ?>
    <?php if ($upload_error): ?>
        <div class="alert alert-danger" style="margin-bottom: 1rem;">
            ❌ <?php echo htmlspecialchars($upload_error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($msg): ?>
        <div class="alert alert-success"><?php echo $msg; ?></div>
    <?php endif; ?>
    <?php if (count($entries) > 0): ?>
        <div class="filter-controls">
            <div class="filter-group">
                <label class="filter-label">
                    <input type="checkbox" id="filterPlanning" checked> 
                    <span class="status-planlagt">📋 Vis planlagte</span>
                </label>
                <label class="filter-label">
                    <input type="checkbox" id="filterActive" checked> 
                    <span class="status-aktiv">🔥 Vis aktive</span>
                </label>
                <label class="filter-label">
                    <input type="checkbox" id="filterCompleted" checked> 
                    <span class="status-afsluttet">✅ Vis afsluttede</span>
                </label>
            </div>
        </div>
        <!-- List View -->
        <div id="listView" class="table-wrapper">
            <table id="ptwTable">
                <thead>
                    <tr>
                        <th title="PTW NR.">PTW nr.</th>
                        <th>Beskrivelse</th>
                        <th>Indkøbsordre<br>beskrivelse</th>
                        <th>Jobansvarlig</th>
                        <th>Entreprenør</th>
                        <th>Status</th>
                        <th>Godkendelser<br>Dagens Status</th>
                        <th>Handlinger</th>
                    </tr>
                </thead>
                <tbody>
            <?php foreach ($entries as $entry):
                $status = $entry['status'] ?? 'planning';
                // Map internal status codes to Danish labels and CSS classes
                if ($status === 'planning') {
                    $statusLabel = 'Planlagt';
                    $statusClass = 'status-planlagt';
                } elseif ($status === 'active') {
                    $statusLabel = 'Aktiv';
                    $statusClass = 'status-aktiv';
                } else { // completed
                    $statusLabel = 'Afsluttet';
                    $statusClass = 'status-afsluttet';
                }
                // Fetch approvals for the current day for this entry
                $approvals = $entry['approvals'] ?? [];
                $oaApproved = (isset($approvals['opgaveansvarlig']) && $approvals['opgaveansvarlig'] === $today);
                $driftApproved = (isset($approvals['drift']) && $approvals['drift'] === $today);
                $entApproved = (isset($approvals['entreprenor']) && $approvals['entreprenor'] === $today);
                
                // Parse daily work status
                $workStatus = json_decode($entry['daily_work_status'] ?? '{}', true) ?? [];
                $workStatusToday = (isset($workStatus['date']) && $workStatus['date'] === $today);
                $workStatusIcon = '';
                $workStatusText = '';
                if ($workStatusToday) {
                    if ($workStatus['status'] === 'working') {
                        $workStatusIcon = '🔨';
                        $workStatusText = 'Arbejder';
                    } elseif ($workStatus['status'] === 'stopped') {
                        $workStatusIcon = '⏹️';
                        $workStatusText = 'Stoppet';
                    }
                }
            ?>
            <tr data-status="<?php echo htmlspecialchars($status); ?>">
                <td><?php echo htmlspecialchars($entry['work_order_no'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($entry['description'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($entry['p_description'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($entry['jobansvarlig'] ?? ''); ?></td>
                <td><?php
                    $firma = $entry['entreprenor_firma'] ?? '';
                    $kontakt = $entry['entreprenor_kontakt'] ?? '';
                    echo htmlspecialchars($firma);
                    if ($kontakt) {
                        echo '<br><small>' . htmlspecialchars($kontakt) . '</small>';
                    }
                ?></td>
                <td>
                    <span class="<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                    <?php if ($workStatusIcon): ?>
                        <span id="work-status-icon-<?php echo $entry['id']; ?>" title="<?php echo htmlspecialchars($workStatusText); ?>" style="margin-left: 0.5rem; font-size: 1.1em;"><?php echo $workStatusIcon; ?></span>
                    <?php else: ?>
                        <span id="work-status-icon-<?php echo $entry['id']; ?>"></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="approval-row">
                        <span class="approval-label" title="Opgaveansvarlige">OA:</span>
                        <span id="oa-status-<?php echo $entry['id']; ?>" class="approval-badge <?php echo $oaApproved ? 'approved' : 'pending'; ?>">
                            <?php echo $oaApproved ? '✅' : '❌'; ?>
                        </span>
                        <?php if ($status === 'active' && !$oaApproved && ($role === 'admin' || $role === 'opgaveansvarlig')): ?>
                            <button type="button" class="btn-approve" 
                                    id="list-oa-btn-<?php echo $entry['id']; ?>"
                                    onclick="approveWorkPermit(<?php echo $entry['id']; ?>, 'opgaveansvarlig', this);"
                                    title="Godkend som Opgaveansvarlig">
                                Godkend
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="approval-row">
                        <span class="approval-label" title="Driften">Drift:</span>
                        <span id="drift-status-<?php echo $entry['id']; ?>" class="approval-badge <?php echo $driftApproved ? 'approved' : 'pending'; ?>">
                            <?php echo $driftApproved ? '✅' : '❌'; ?>
                        </span>
                        <?php if ($status === 'active' && !$driftApproved && ($role === 'admin' || $role === 'drift')): ?>
                            <button type="button" class="btn-approve" 
                                    id="list-drift-btn-<?php echo $entry['id']; ?>"
                                    onclick="approveWorkPermit(<?php echo $entry['id']; ?>, 'drift', this);"
                                    title="Godkend som Drift">
                                Godkend
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="approval-row">
                        <span class="approval-label" title="Entreprenør">Ent:</span>
                        <span id="ent-status-<?php echo $entry['id']; ?>" class="approval-badge <?php echo $entApproved ? 'approved' : 'pending'; ?>">
                            <?php echo $entApproved ? '✅' : '❌'; ?>
                        </span>
                        <?php if ($status === 'active' && !$entApproved && ($role === 'admin' || $role === 'entreprenor')): ?>
                            <button type="button" class="btn-approve" 
                                    id="list-ent-btn-<?php echo $entry['id']; ?>"
                                    onclick="approveWorkPermit(<?php echo $entry['id']; ?>, 'entreprenor', this);"
                                    title="Godkend som Entreprenør">
                                Godkend
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="col-actions">
                    <div class="action-buttons-group">
                        <a class="btn-icon btn-view" href="print_wo.php?id=<?php echo urlencode($entry['id']); ?>" title="Vis PTW">
                            <span class="icon">👁️</span>
                        </a>
                        <?php if ($role !== 'entreprenor'): ?>
                            <a class="btn-icon btn-edit" href="create_wo.php?id=<?php echo urlencode($entry['id']); ?>" title="Rediger PTW">
                                <span class="icon">✏️</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($role === 'admin'): ?>
                            <a class="btn-icon btn-delete" href="view_wo.php?delete_id=<?php echo urlencode($entry['id']); ?>" onclick="return confirm('Er du sikker på, at du vil slette denne PTW??');" title="Slet PTW">
                                <span class="icon">🗑️</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($status === 'active' && in_array($role, ['admin', 'entreprenor'])): ?>
                            <button class="btn-icon btn-time" onclick="openTimeModal(<?php echo $entry['id']; ?>)" title="Tidsregistrering">
                                <span class="icon">⏱️</span>
                            </button>
                        <?php endif; ?>
                        <?php if ($role === 'entreprenor' && $status === 'active' && $workStatusToday && $workStatus['status'] === 'working'): ?>
                            <button id="work-btn-<?php echo $entry['id']; ?>" class="btn-work btn-stop" onclick="updateWorkStatus(<?php echo $entry['id']; ?>, 'stopped')" title="Stop arbejde for i dag">
                                <span class="icon">⏹️</span>
                                <span class="text">Stop</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination Controls -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?php echo $currentPage - 1; ?>" class="pagination-btn">← Forrige</a>
                <?php endif; ?>
                
                <span class="pagination-info">
                    Side <?php echo $currentPage; ?> af <?php echo $totalPages; ?> 
                    (<?php echo $totalItems; ?> PTW'er i alt)
                </span>
                
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?php echo $currentPage + 1; ?>" class="pagination-btn">Næste →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Time Registration Modals for List View -->
        <?php foreach ($entries as $entry): 
            if ($entry['status'] === 'active' && in_array($role, ['admin', 'entreprenor'])):
        ?>
        <div id="timeModal<?php echo $entry['id']; ?>" class="time-modal" style="display: none;">
            <div class="time-modal-overlay" onclick="closeTimeModal(<?php echo $entry['id']; ?>)"></div>
            <div class="time-modal-content">
                <div class="time-modal-header">
                    <h3>⏱️ Tidsregistrering – WO <?php echo htmlspecialchars($entry['work_order_no'] ?? $entry['id']); ?></h3>
                    <button class="time-modal-close" onclick="closeTimeModal(<?php echo $entry['id']; ?>)">&times;</button>
                </div>
                <div class="time-modal-body">
                    <div class="time-entry-form">
                        <div class="time-input-group">
                            <label for="modal-time-date-<?php echo $entry['id']; ?>">📅 Dato:</label>
                            <input type="date" 
                                   id="modal-time-date-<?php echo $entry['id']; ?>" 
                                   class="time-date-input" 
                                   value="<?php echo date('Y-m-d'); ?>"
                                   max="<?php echo date('Y-m-d'); ?>"
                                   onchange="loadTimeEntryModal(<?php echo $entry['id']; ?>, this.value)">
                        </div>
                        <div class="time-input-group">
                            <label for="modal-time-hours-<?php echo $entry['id']; ?>">🕐 Timer:</label>
                            <input type="number" 
                                   id="modal-time-hours-<?php echo $entry['id']; ?>" 
                                   class="time-hours-input" 
                                   min="0" 
                                   max="24" 
                                   step="0.25" 
                                   placeholder="0.00">
                        </div>
                        <div class="time-input-group full-width">
                            <label for="modal-time-desc-<?php echo $entry['id']; ?>">📝 Beskrivelse (valgfri):</label>
                            <input type="text" 
                                   id="modal-time-desc-<?php echo $entry['id']; ?>" 
                                   class="time-desc-input" 
                                   placeholder="Beskrivelse af arbejdet...">
                        </div>
                        <div class="time-actions">
                            <button type="button" 
                                    class="button button-success button-sm save-time-btn" 
                                    onclick="saveTimeEntryModal(<?php echo $entry['id']; ?>)">
                                💾 Gem timer
                            </button>
                            <button type="button" 
                                    class="button button-secondary button-sm show-all-times-btn" 
                                    onclick="toggleTimeHistoryModal(<?php echo $entry['id']; ?>)">
                                📊 Vis alle timer
                            </button>
                        </div>
                    </div>
                    
                    <!-- Time history section -->
                    <div class="time-history" id="modal-time-history-<?php echo $entry['id']; ?>" style="display: none;">
                        <h5>📈 Tidshistorik</h5>
                        <div class="time-history-content">
                            <div class="loading">Indlæser...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php 
            endif;
        endforeach; 
        ?>
        
        <!-- Card View -->
        <div id="cardView" class="card-view-wrapper" style="display: none;">
            <!-- Card Counter Bar with Navigation -->
            <div id="cardCounter" class="card-counter">
                <button id="prevCardBtn" class="counter-nav-btn" aria-label="Forrige PTW">◀</button>
                <span class="counter-text">PTW 1 af <?php echo count($entries); ?> (Side <?php echo $currentPage; ?>)</span>
                <button id="nextCardBtn" class="counter-nav-btn" aria-label="Næste PTW">▶</button>
            </div>
            
            <div class="slider-container">
                <div class="card-slider" id="cardSlider">
            <?php foreach ($entries as $entry):
                $status = $entry['status'] ?? 'planning';
                // Map internal status codes to Danish labels and CSS classes
                if ($status === 'planning') {
                    $statusLabel = 'Planlagt';
                    $statusClass = 'status-planlagt';
                } elseif ($status === 'active') {
                    $statusLabel = 'Aktiv';
                    $statusClass = 'status-aktiv';
                } else { // completed
                    $statusLabel = 'Afsluttet';
                    $statusClass = 'status-afsluttet';
                }
                // Fetch approvals for the current day for this entry
                $approvals = $entry['approvals'] ?? [];
                $oaApproved = (isset($approvals['opgaveansvarlig']) && $approvals['opgaveansvarlig'] === $today);
                $driftApproved = (isset($approvals['drift']) && $approvals['drift'] === $today);
                $entApproved = (isset($approvals['entreprenor']) && $approvals['entreprenor'] === $today);
                
                // Parse daily work status
                $workStatus = json_decode($entry['daily_work_status'] ?? '{}', true) ?? [];
                $workStatusToday = (isset($workStatus['date']) && $workStatus['date'] === $today);
                $workStatusIcon = '';
                $workStatusText = '';
                if ($workStatusToday) {
                    if ($workStatus['status'] === 'working') {
                        $workStatusIcon = '🔨';
                        $workStatusText = 'Arbejder';
                    } elseif ($workStatus['status'] === 'stopped') {
                        $workStatusIcon = '⏹️';
                        $workStatusText = 'Stoppet';
                    }
                }
                
                $firma = $entry['entreprenor_firma'] ?? '';
                $kontakt = $entry['entreprenor_kontakt'] ?? '';
            ?>
            <div class="work-permit-card" data-status="<?php echo htmlspecialchars($status); ?>">
                <!-- Card Header with Status Badge -->
                <div class="card-header-modern">
                    <div class="card-title-row">
                        <h3 class="card-main-title">PTW <?php echo htmlspecialchars($entry['work_order_no'] ?? ''); ?></h3>
                        <span class="card-status-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                        <?php if ($workStatusIcon): ?>
                            <span id="card-work-status-icon-<?php echo $entry['id']; ?>" title="<?php echo htmlspecialchars($workStatusText); ?>" class="work-status-badge"><?php echo $workStatusIcon; ?></span>
                        <?php else: ?>
                            <span id="card-work-status-icon-<?php echo $entry['id']; ?>"></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card-content-modern">
                    <!-- Section 1: Basisinformation (Collapsible) -->
                    <div class="card-section">
                        <div class="section-header" onclick="toggleSection('basic-<?php echo $entry['id']; ?>')">
                            <h4>📋 Basisinformation</h4>
                            <span class="toggle-icon" id="toggle-basic-<?php echo $entry['id']; ?>">►</span>
                        </div>
                        <div class="section-content" id="basic-<?php echo $entry['id']; ?>">
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">PTW Nr.</span>
                                    <span class="info-value"><?php echo htmlspecialchars($entry['work_order_no'] ?? ''); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Indkøbsordre Nr.</span>
                                    <span class="info-value"><?php echo htmlspecialchars($entry['p_number'] ?? ''); ?></span>
                                </div>
                                <div class="info-item full-width">
                                    <span class="info-label">Beskrivelse</span>
                                    <span class="info-value"><?php echo htmlspecialchars($entry['description'] ?? ''); ?></span>
                                </div>
                                <?php if (!empty($entry['p_description'])): ?>
                                <div class="info-item full-width">
                                    <span class="info-label">Indkøbsordre beskrivelse</span>
                                    <span class="info-value"><?php echo htmlspecialchars($entry['p_description']); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="info-item">
                                    <span class="info-label">👤 Jobansvarlig</span>
                                    <span class="info-value"><?php echo htmlspecialchars($entry['jobansvarlig'] ?? ''); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">🏢 Entreprenør</span>
                                    <span class="info-value">
                                        <?php echo htmlspecialchars($firma); ?>
                                        <?php if ($kontakt): ?>
                                            <br><small style="color: #666;"><?php echo htmlspecialchars($kontakt); ?></small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section 2: Godkendelsesproces (Collapsible) -->
                    <?php 
                    // Calculate approval count for status summary
                    $approvalCount = 0;
                    if ($oaApproved) $approvalCount++;
                    if ($driftApproved) $approvalCount++;
                    if ($entApproved) $approvalCount++;
                    
                    // Get approval history for timestamps
                    // Note: approval_history may already be an array from Postgres JSONB
                    $approval_history_raw = $entry['approval_history'] ?? '[]';
                    $approval_history = is_array($approval_history_raw) ? $approval_history_raw : (json_decode($approval_history_raw, true) ?? []);
                    $oaTimestamp = '';
                    $driftTimestamp = '';
                    $entTimestamp = '';
                    
                    if (is_array($approval_history)) {
                        foreach ($approval_history as $hist) {
                            if (($hist['role'] ?? '') === 'opgaveansvarlig' && strpos($hist['timestamp'] ?? '', $today) === 0) {
                                $oaTimestamp = $hist['timestamp'] ?? '';
                            }
                            if (($hist['role'] ?? '') === 'drift' && strpos($hist['timestamp'] ?? '', $today) === 0) {
                                $driftTimestamp = $hist['timestamp'] ?? '';
                            }
                            if (($hist['role'] ?? '') === 'entreprenor' && strpos($hist['timestamp'] ?? '', $today) === 0) {
                                $entTimestamp = $hist['timestamp'] ?? '';
                            }
                        }
                    }
                    
                    // Determine user's ability to approve - ONLY if status is 'active'
                    $canApproveOA = ($status === 'active') && ($role === 'admin' || $role === 'opgaveansvarlig') && !$oaApproved;
                    $canApproveDrift = ($status === 'active') && ($role === 'admin' || $role === 'drift') && !$driftApproved;
                    $canApproveEnt = ($status === 'active') && ($role === 'admin' || $role === 'entreprenor') && !$entApproved;
                    ?>
                    <div class="card-section">
                        <div class="section-header" onclick="toggleSection('approval-<?php echo $entry['id']; ?>')">
                            <h4>✅ Godkendelsesproces</h4>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="approval-count" id="approval-count-<?php echo $entry['id']; ?>">Godkendt <?php echo $approvalCount; ?>/3</span>
                                <span class="toggle-icon" id="toggle-approval-<?php echo $entry['id']; ?>">▼</span>
                            </div>
                        </div>
                        <div class="section-content" id="approval-<?php echo $entry['id']; ?>">
                            <div class="approval-list">
                                <!-- Step 1: Opgaveansvarlig -->
                                <div class="approval-step <?php echo $oaApproved ? 'approved' : 'pending'; ?>">
                                    <div class="approval-icon">
                                        <?php echo $oaApproved ? '<span class="check-icon">✓</span>' : '<span class="pending-icon">○</span>'; ?>
                                    </div>
                                    <div class="approval-info">
                                        <div class="approval-name">Opgaveansvarlig</div>
                                        <?php if ($oaApproved && $oaTimestamp): ?>
                                            <div class="approval-date"><?php echo htmlspecialchars($oaTimestamp); ?></div>
                                        <?php else: ?>
                                            <div class="approval-status-text">Afventer godkendelse</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($canApproveOA): ?>
                                        <button class="approval-btn ajax-approve-btn" 
                                                data-id="<?php echo $entry['id']; ?>" 
                                                data-role="opgaveansvarlig">
                                            Godkend
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Step 2: Drift -->
                                <div class="approval-step <?php echo $driftApproved ? 'approved' : 'pending'; ?>">
                                    <div class="approval-icon">
                                        <?php echo $driftApproved ? '<span class="check-icon">✓</span>' : '<span class="pending-icon">○</span>'; ?>
                                    </div>
                                    <div class="approval-info">
                                        <div class="approval-name">Drift</div>
                                        <?php if ($driftApproved && $driftTimestamp): ?>
                                            <div class="approval-date"><?php echo htmlspecialchars($driftTimestamp); ?></div>
                                        <?php else: ?>
                                            <div class="approval-status-text">Afventer godkendelse</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($canApproveDrift): ?>
                                        <button class="approval-btn ajax-approve-btn" 
                                                data-id="<?php echo $entry['id']; ?>" 
                                                data-role="drift">
                                            Godkend
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Step 3: Entreprenør -->
                                <div class="approval-step <?php echo $entApproved ? 'approved' : 'pending'; ?>">
                                    <div class="approval-icon">
                                        <?php echo $entApproved ? '<span class="check-icon">✓</span>' : '<span class="pending-icon">○</span>'; ?>
                                    </div>
                                    <div class="approval-info">
                                        <div class="approval-name">Entreprenør</div>
                                        <?php if ($entApproved && $entTimestamp): ?>
                                            <div class="approval-date"><?php echo htmlspecialchars($entTimestamp); ?></div>
                                        <?php else: ?>
                                            <div class="approval-status-text">Afventer godkendelse</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($canApproveEnt): ?>
                                        <button class="approval-btn ajax-approve-btn" 
                                                data-id="<?php echo $entry['id']; ?>" 
                                                data-role="entreprenor">
                                            Godkend
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section 3: Tidsregistrering (Collapsible) -->
                    <?php if (in_array($role, ['entreprenor', 'admin', 'opgaveansvarlig', 'drift'])): ?>
                    <div class="card-section">
                        <div class="section-header" onclick="toggleTimeTracking(<?php echo $entry['id']; ?>)">
                            <h4>⏱️ Tidsregistrering</h4>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="time-summary" id="time-summary-<?php echo $entry['id']; ?>"></span>
                                <span class="toggle-icon" id="time-icon-<?php echo $entry['id']; ?>">▼</span>
                            </div>
                        </div>
                        <div class="section-content time-entry-section" id="time-section-<?php echo $entry['id']; ?>">
                            
                            <?php if (in_array($role, ['admin', 'entreprenor'])): ?>
                            <!-- Time entry form - only for admin and entreprenor -->
                            <div class="time-entry-form">
                                <div class="time-input-group">
                                    <label for="time-date-<?php echo $entry['id']; ?>">📅 Dato:</label>
                                    <input type="date" 
                                           id="time-date-<?php echo $entry['id']; ?>" 
                                           class="time-date-input" 
                                           value="<?php echo date('Y-m-d'); ?>"
                                           max="<?php echo date('Y-m-d'); ?>"
                                           onchange="loadTimeEntry(<?php echo $entry['id']; ?>, this.value)">
                                </div>
                                <div class="time-input-group">
                                    <label for="time-hours-<?php echo $entry['id']; ?>">🕐 Timer:</label>
                                    <input type="number" 
                                           id="time-hours-<?php echo $entry['id']; ?>" 
                                           class="time-hours-input" 
                                           min="0" 
                                           max="24" 
                                           step="0.25" 
                                           placeholder="0.00">
                                </div>
                                <div class="time-input-group full-width">
                                    <label for="time-desc-<?php echo $entry['id']; ?>">📝 Beskrivelse (valgfri):</label>
                                    <input type="text" 
                                           id="time-desc-<?php echo $entry['id']; ?>" 
                                           class="time-desc-input" 
                                           placeholder="Beskrivelse af arbejdet...">
                                </div>
                                <div class="time-actions">
                                    <button type="button" 
                                            class="button button-success button-sm save-time-btn" 
                                            onclick="saveTimeEntry(<?php echo $entry['id']; ?>)">
                                        💾 Gem timer
                                    </button>
                                    <button type="button" 
                                            class="button button-secondary button-sm show-all-times-btn" 
                                            onclick="toggleTimeHistory(<?php echo $entry['id']; ?>)">
                                        📊 Vis alle timer
                                    </button>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- View-only for opgaveansvarlig and drift -->
                            <div class="time-view-only">
                                <p class="time-view-notice">📋 Du kan se tidshistorik og totale timer, men ikke indtaste nye timer.</p>
                                <div class="time-actions">
                                    <button type="button" 
                                            class="button button-primary button-sm show-all-times-btn" 
                                            onclick="toggleTimeHistory(<?php echo $entry['id']; ?>)">
                                        📊 Vis tidshistorik og totale timer
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Time history section - available for all authorized roles -->
                            <div class="time-history" id="time-history-<?php echo $entry['id']; ?>" style="display: none;">
                                <h5>📈 Tidshistorik</h5>
                                <div class="time-history-content">
                                    <div class="loading">Indlæser...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Section 4: Dokumentationsbilleder (Collapsible) -->
                    <div class="card-section">
                        <div class="section-header" onclick="toggleSection('images-<?php echo $entry['id']; ?>')">
                            <h4>📷 Dokumentationsbilleder</h4>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="images-count">
                                    <?php 
                                    $completionImages = $entry['completion_images'] ?? [];
                                    if (is_string($completionImages)) {
                                        $completionImages = json_decode($completionImages, true) ?? [];
                                    }
                                    $imageCount = count($completionImages);
                                    echo $imageCount > 0 ? $imageCount . ' billede' . ($imageCount > 1 ? 'r' : '') : 'Ingen billeder';
                                    ?>
                                </span>
                                <span class="toggle-icon" id="toggle-images-<?php echo $entry['id']; ?>">▼</span>
                            </div>
                        </div>
                        <div class="section-content" id="images-<?php echo $entry['id']; ?>">
                            <?php if ($role === 'entreprenor' && $status === 'active'): ?>
                                <!-- Upload form for entrepreneurs -->
                                <div class="image-upload-section">
                                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 0.75rem;">
                                        📤 Upload dokumentationsbilleder til denne PTW
                                    </p>
                                    <form method="POST" enctype="multipart/form-data" class="upload-form-card">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="wo_id" value="<?php echo $entry['id']; ?>">
                                        <div class="upload-input-group">
                                            <input type="file" 
                                                   name="completion_image" 
                                                   id="upload-file-<?php echo $entry['id']; ?>"
                                                   accept="image/*" 
                                                   required 
                                                   class="file-input">
                                            <label for="upload-file-<?php echo $entry['id']; ?>" class="file-label">
                                                📁 Vælg billede
                                            </label>
                                        </div>
                                        <button type="submit" name="upload_image" value="1" class="button button-success button-sm">
                                            📤 Upload billede
                                        </button>
                                    </form>
                                    <p style="color: #999; font-size: 0.75rem; margin-top: 0.5rem;">
                                        💡 Accepterede formater: JPEG, PNG, GIF, WebP, AVIF (max 50MB)
                                    </p>
                                </div>
                            <?php else: ?>
                                <!-- View-only message for non-entrepreneurs -->
                                <p class="upload-restricted-notice">
                                    <?php if ($status !== 'active'): ?>
                                        ℹ️ Upload af billeder er kun muligt for aktive PTW'er.
                                    <?php else: ?>
                                        ℹ️ Kun entreprenører kan uploade dokumentationsbilleder.
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Card Actions (Bottom Buttons) -->
                <div class="card-actions">
                    <a class="button button-secondary button-sm handlinger-btn" href="print_wo.php?id=<?php echo urlencode($entry['id']); ?>">Vis</a>
                    <?php if ($role !== 'entreprenor'): ?>
                        <a class="button button-sm handlinger-btn" href="create_wo.php?id=<?php echo urlencode($entry['id']); ?>">Rediger</a>
                    <?php endif; ?>
                    <?php if ($role === 'admin'): ?>
                        <a class="button button-danger button-sm handlinger-btn" href="view_wo.php?delete_id=<?php echo urlencode($entry['id']); ?>" onclick="return confirm('Er du sikker på, at du vil slette denne PTW??');">Slet</a>
                    <?php endif; ?>
                    <?php if ($role === 'entreprenor' && $status === 'active' && $workStatusToday && $workStatus['status'] === 'working'): ?>
                        <button id="card-work-btn-<?php echo $entry['id']; ?>" class="button button-warning button-sm" onclick="updateWorkStatus(<?php echo $entry['id']; ?>, 'stopped')">⏹️ Stop arbejde for i dag</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Pagination Controls for Card View -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination card-pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?php echo $currentPage - 1; ?>" class="pagination-btn">← Forrige</a>
                <?php endif; ?>
                
                <span class="pagination-info">
                    Side <?php echo $currentPage; ?> af <?php echo $totalPages; ?> 
                    (<?php echo $totalItems; ?> PTW'er i alt)
                </span>
                
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?php echo $currentPage + 1; ?>" class="pagination-btn">Næste →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <script>
        // Notification system
        function showNotification(message, type = 'success') {
            const container = document.getElementById('notificationContainer');
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            
            const icon = type === 'success' ? '✅' : '❌';
            notification.innerHTML = `
                <span class="notification-icon">${icon}</span>
                <span class="notification-message">${message}</span>
                <button class="notification-close" onclick="this.parentElement.remove()">×</button>
            `;
            
            container.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
            
            // Animate in
            setTimeout(() => {
                notification.classList.add('notification-show');
            }, 100);
        }
        
        // Toggle section expansion (Generic function for all sections)
        function toggleSection(sectionId) {
            const section = document.getElementById(sectionId);
            const toggleIcon = document.getElementById('toggle-' + sectionId);
            
            if (section.classList.contains('expanded')) {
                section.classList.remove('expanded');
                if (toggleIcon) toggleIcon.textContent = '▼';
            } else {
                section.classList.add('expanded');
                if (toggleIcon) toggleIcon.textContent = '▲';
            }
        }
        
        // Toggle description expansion
        function toggleDescription(woId) {
            const desc = document.getElementById(`desc-${woId}`);
            const toggle = document.getElementById(`desc-toggle-${woId}`);
            
            if (desc.classList.contains('expanded')) {
                desc.classList.remove('expanded');
                toggle.textContent = 'Vis mere';
            } else {
                desc.classList.add('expanded');
                toggle.textContent = 'Vis mindre';
            }
        }
        
        // Toggle approvals section
        function toggleApprovals(woId) {
            const grid = document.getElementById(`approval-grid-${woId}`);
            const icon = document.getElementById(`approvals-icon-${woId}`);
            
            if (grid.classList.contains('expanded')) {
                grid.classList.remove('expanded');
                icon.classList.remove('expanded');
            } else {
                grid.classList.add('expanded');
                icon.classList.add('expanded');
            }
        }
        
        // Track which work orders have loaded time entries (lazy loading cache)
        const loadedTimeEntries = new Set();
        
        // Toggle time tracking section
        function toggleTimeTracking(woId) {
            const section = document.getElementById(`time-section-${woId}`);
            const icon = document.getElementById(`time-icon-${woId}`);
            
            if (section.classList.contains('expanded')) {
                section.classList.remove('expanded');
                if (icon) icon.textContent = '▼';
            } else {
                section.classList.add('expanded');
                if (icon) icon.textContent = '▲';
                
                // Lazy load time entry for current date when section is first opened
                if (!loadedTimeEntries.has(woId)) {
                    const dateInput = document.getElementById(`time-date-${woId}`);
                    if (dateInput) {
                        loadTimeEntry(woId, dateInput.value);
                        loadedTimeEntries.add(woId);
                    }
                }
            }
        }
        
        // Modal Functions
        function openTimeModal(workOrderId) {
            const modal = document.getElementById(`timeModal${workOrderId}`);
            if (modal) {
                modal.style.display = 'flex';
                // Load current date's time entry
                const dateInput = document.getElementById(`modal-time-date-${workOrderId}`);
                if (dateInput) {
                    loadTimeEntryModal(workOrderId, dateInput.value);
                }
                // Prevent body scroll
                document.body.style.overflow = 'hidden';
            }
        }
        
        function closeTimeModal(workOrderId) {
            const modal = document.getElementById(`timeModal${workOrderId}`);
            if (modal) {
                modal.style.display = 'none';
                // Restore body scroll
                document.body.style.overflow = '';
            }
        }
        
        function saveTimeEntryModal(workOrderId) {
            const dateInput = document.getElementById(`modal-time-date-${workOrderId}`);
            const hoursInput = document.getElementById(`modal-time-hours-${workOrderId}`);
            const descInput = document.getElementById(`modal-time-desc-${workOrderId}`);
            const modal = document.getElementById(`timeModal${workOrderId}`);
            const saveBtn = modal.querySelector('.save-time-btn');
            
            const entryDate = dateInput.value;
            const hours = parseFloat(hoursInput.value);
            
            // Check for invalid hours input (NaN from empty field)
            if (isNaN(hours) || hours <= 0) {
                showNotification('Indtast venligst et gyldigt antal timer større end 0.', 'error');
                return;
            }
            const description = descInput.value.trim();
            
            // Enhanced input validation to match backend
            if (!entryDate) {
                showNotification('Vælg venligst en dato.', 'error');
                return;
            }
            
            // Date validation - no future dates, no dates older than 1 year
            const entryDateObj = new Date(entryDate);
            const today = new Date();
            const oneYearAgo = new Date();
            oneYearAgo.setFullYear(today.getFullYear() - 1);
            
            if (entryDateObj > today) {
                showNotification('Dato kan ikke være i fremtiden.', 'error');
                return;
            }
            
            if (entryDateObj < oneYearAgo) {
                showNotification('Dato skal være inden for det sidste år.', 'error');
                return;
            }
            
            if (hours < 0 || hours > 24) {
                showNotification('Timer skal være mellem 0 og 24.', 'error');
                return;
            }
            
            // Check for quarter-hour increments (0, 0.25, 0.5, 0.75, 1.0, etc.)
            const quarterHours = hours * 4;
            if (quarterHours !== Math.floor(quarterHours)) {
                showNotification('Timer skal være i kvarte-times intervaller (0.25, 0.5, 0.75, osv.).', 'error');
                return;
            }
            
            // Show loading state
            const originalText = saveBtn.textContent;
            saveBtn.disabled = true;
            saveBtn.textContent = '⏳ Gemmer...';
            
            // Create FormData
            const formData = new FormData();
            formData.append('action', 'save_time_entry');
            formData.append('work_order_id', workOrderId);
            formData.append('entry_date', entryDate);
            formData.append('hours', hours);
            formData.append('description', description);
            
            fetch('time_entry_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Reset hours input, keep date and description
                    hoursInput.value = '';
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Der opstod en fejl ved gemning af timer. Prøv igen.', 'error');
            })
            .finally(() => {
                // Restore button state
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
            });
        }
        
        function loadTimeEntryModal(workOrderId, date) {
            const hoursInput = document.getElementById(`modal-time-hours-${workOrderId}`);
            const descInput = document.getElementById(`modal-time-desc-${workOrderId}`);
            
            if (!date) {
                hoursInput.value = '';
                descInput.value = '';
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'get_user_time_entry');
            formData.append('work_order_id', workOrderId);
            formData.append('entry_date', date);
            
            fetch('time_entry_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    hoursInput.value = data.data.hours || '';
                    descInput.value = data.data.description || '';
                } else {
                    hoursInput.value = '';
                    descInput.value = '';
                }
            })
            .catch(error => {
                console.error('Error loading time entry:', error);
            });
        }
        
        function toggleTimeHistoryModal(workOrderId) {
            const historyDiv = document.getElementById(`modal-time-history-${workOrderId}`);
            const modal = document.getElementById(`timeModal${workOrderId}`);
            const button = modal.querySelector('.show-all-times-btn');
            
            if (historyDiv.style.display === 'none') {
                // Show history and load data
                historyDiv.style.display = 'block';
                button.textContent = '📊 Skjul timer';
                loadTimeHistoryModal(workOrderId);
            } else {
                // Hide history
                historyDiv.style.display = 'none';
                button.textContent = '📊 Vis alle timer';
            }
        }
        
        function loadTimeHistoryModal(workOrderId) {
            const modal = document.getElementById(`timeModal${workOrderId}`);
            const contentDiv = modal.querySelector(`#modal-time-history-${workOrderId} .time-history-content`);
            contentDiv.innerHTML = '<div class="loading">Indlæser...</div>';
            
            const formData = new FormData();
            formData.append('action', 'get_time_entries');
            formData.append('work_order_id', workOrderId);
            
            fetch('time_entry_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const entries = data.data.entries;
                    const totalHours = data.data.total_hours;
                    
                    if (entries.length === 0) {
                        contentDiv.innerHTML = '<p>Ingen tidsregistreringer fundet.</p>';
                        return;
                    }
                    
                    let html = `<div class="time-summary"><strong>Total timer: ${totalHours}</strong></div>`;
                    html += '<div class="time-entries-list">';
                    
                    entries.forEach(entry => {
                        html += `
                            <div class="time-entry-item">
                                <div class="time-entry-date">${entry.entry_date}</div>
                                <div class="time-entry-hours">${entry.hours} timer</div>
                                <div class="time-entry-user">${entry.username}</div>
                                ${entry.description ? `<div class="time-entry-desc">${entry.description}</div>` : ''}
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    contentDiv.innerHTML = html;
                } else {
                    contentDiv.innerHTML = `<p class="error">Fejl: ${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error loading time history:', error);
                contentDiv.innerHTML = '<p class="error">Der opstod en fejl ved indlæsning af tidshistorik.</p>';
            });
        }
        
        // Time Entry Functions
        function saveTimeEntry(workOrderId) {
            const dateInput = document.getElementById(`time-date-${workOrderId}`);
            const hoursInput = document.getElementById(`time-hours-${workOrderId}`);
            const descInput = document.getElementById(`time-desc-${workOrderId}`);
            const saveBtn = document.querySelector(`#time-section-${workOrderId} .save-time-btn`);
            
            const entryDate = dateInput.value;
            const hours = parseFloat(hoursInput.value);
            
            // Check for invalid hours input (NaN from empty field)
            if (isNaN(hours) || hours <= 0) {
                showNotification('Indtast venligst et gyldigt antal timer større end 0.', 'error');
                return;
            }
            const description = descInput.value.trim();
            
            // Enhanced input validation to match backend
            if (!entryDate) {
                showNotification('Vælg venligst en dato.', 'error');
                return;
            }
            
            // Date validation - no future dates, no dates older than 1 year
            const entryDateObj = new Date(entryDate);
            const today = new Date();
            const oneYearAgo = new Date();
            oneYearAgo.setFullYear(today.getFullYear() - 1);
            
            if (entryDateObj > today) {
                showNotification('Dato kan ikke være i fremtiden.', 'error');
                return;
            }
            
            if (entryDateObj < oneYearAgo) {
                showNotification('Dato skal være inden for det sidste år.', 'error');
                return;
            }
            
            if (hours < 0 || hours > 24) {
                showNotification('Timer skal være mellem 0 og 24.', 'error');
                return;
            }
            
            // Check for quarter-hour increments (0, 0.25, 0.5, 0.75, 1.0, etc.)
            const quarterHours = hours * 4;
            if (quarterHours !== Math.floor(quarterHours)) {
                showNotification('Timer skal være i kvarte-times intervaller (0.25, 0.5, 0.75, osv.).', 'error');
                return;
            }
            
            // Show loading state
            const originalText = saveBtn.textContent;
            saveBtn.disabled = true;
            saveBtn.textContent = '⏳ Gemmer...';
            
            // Create FormData
            const formData = new FormData();
            formData.append('action', 'save_time_entry');
            formData.append('work_order_id', workOrderId);
            formData.append('entry_date', entryDate);
            formData.append('hours', hours);
            formData.append('description', description);
            
            fetch('time_entry_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Reset hours input, keep date and description
                    hoursInput.value = '';
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Der opstod en fejl ved gemning af timer. Prøv igen.', 'error');
            })
            .finally(() => {
                // Restore button state
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
            });
        }
        
        function loadTimeEntry(workOrderId, date) {
            const hoursInput = document.getElementById(`time-hours-${workOrderId}`);
            const descInput = document.getElementById(`time-desc-${workOrderId}`);
            
            if (!date) {
                hoursInput.value = '';
                descInput.value = '';
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'get_user_time_entry');
            formData.append('work_order_id', workOrderId);
            formData.append('entry_date', date);
            
            fetch('time_entry_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    hoursInput.value = data.data.hours || '';
                    descInput.value = data.data.description || '';
                } else {
                    hoursInput.value = '';
                    descInput.value = '';
                }
            })
            .catch(error => {
                console.error('Error loading time entry:', error);
            });
        }
        
        function toggleTimeHistory(workOrderId) {
            const historyDiv = document.getElementById(`time-history-${workOrderId}`);
            const button = document.querySelector(`#time-section-${workOrderId} .show-all-times-btn`);
            
            if (historyDiv.style.display === 'none') {
                // Show history and load data
                historyDiv.style.display = 'block';
                button.textContent = '📊 Skjul timer';
                loadTimeHistory(workOrderId);
            } else {
                // Hide history
                historyDiv.style.display = 'none';
                button.textContent = '📊 Vis alle timer';
            }
        }
        
        function loadTimeHistory(workOrderId) {
            const contentDiv = document.querySelector(`#time-history-${workOrderId} .time-history-content`);
            contentDiv.innerHTML = '<div class="loading">Indlæser...</div>';
            
            const formData = new FormData();
            formData.append('action', 'get_time_entries');
            formData.append('work_order_id', workOrderId);
            
            fetch('time_entry_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const entries = data.data.entries;
                    const totalHours = data.data.total_hours;
                    
                    if (entries.length === 0) {
                        contentDiv.innerHTML = '<p>Ingen tidsregistreringer fundet.</p>';
                        return;
                    }
                    
                    let html = `<div class="time-summary"><strong>Total timer: ${totalHours}</strong></div>`;
                    html += '<div class="time-entries-list">';
                    
                    entries.forEach(entry => {
                        html += `
                            <div class="time-entry-item">
                                <div class="time-entry-date">${entry.entry_date}</div>
                                <div class="time-entry-hours">${entry.hours} timer</div>
                                <div class="time-entry-user">${entry.username}</div>
                                ${entry.description ? `<div class="time-entry-desc">${entry.description}</div>` : ''}
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    contentDiv.innerHTML = html;
                } else {
                    contentDiv.innerHTML = `<p class="error">Fejl: ${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error loading time history:', error);
                contentDiv.innerHTML = '<p class="error">Der opstod en fejl ved indlæsning af tidshistorik.</p>';
            });
        }
        
        // Note: Time entries are loaded lazily (on-demand) when user interacts with date field
        // This improves page load performance by avoiding multiple simultaneous requests

        // AJAX approval function
        function approveWorkPermit(id, role, buttonElement) {
            // Proceed with approval for all roles
            performApproval(id, role, buttonElement);
        }
        
        // Perform the actual approval
        function performApproval(id, role, buttonElement) {
            // Disable button and show loading state
            buttonElement.disabled = true;
            const originalText = buttonElement.textContent;
            buttonElement.textContent = '⏳ Godkender...';
            
            // Create FormData for POST request
            const formData = new FormData();
            formData.append('ajax_approve', '1');
            formData.append('approve_id', id);
            formData.append('role', role);
            
            fetch('view_wo.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update status display - map role names to ID prefixes
                    let rolePrefix = role;
                    if (role === 'opgaveansvarlig') rolePrefix = 'oa';
                    if (role === 'entreprenor') rolePrefix = 'ent';
                    
                    // Update BOTH List View and Card View elements (both exist in DOM)
                    // List View element (in table)
                    const listViewElement = document.querySelector(`#listView #${rolePrefix}-status-${id}`);
                    if (listViewElement) {
                        listViewElement.textContent = '✅';
                        listViewElement.className = 'approval-status approved';
                    }
                    
                    // Card View element (old widget style)
                    const cardViewElement = document.querySelector(`#cardView #${rolePrefix}-status-${id}`);
                    if (cardViewElement) {
                        cardViewElement.textContent = '✅ Godkendt';
                        cardViewElement.className = 'approval-status approved';
                    }
                    
                    // NEW: Update vertical approval list in card view
                    // Find the approval step containing the clicked button
                    const approvalStep = buttonElement.closest('.approval-step');
                    if (approvalStep) {
                        // Change step class from pending to approved
                        approvalStep.classList.remove('pending');
                        approvalStep.classList.add('approved');
                        
                        // Update icon from gray circle to green checkmark
                        const iconElement = approvalStep.querySelector('.approval-icon');
                        if (iconElement) {
                            iconElement.innerHTML = '<span class="check-icon">✓</span>';
                        }
                        
                        // Update status text to show timestamp
                        const infoElement = approvalStep.querySelector('.approval-info');
                        if (infoElement) {
                            const statusElement = infoElement.querySelector('.approval-status-text');
                            if (statusElement) {
                                // Get current timestamp in format matching PHP
                                const now = new Date();
                                const day = String(now.getDate()).padStart(2, '0');
                                const month = String(now.getMonth() + 1).padStart(2, '0');
                                const year = now.getFullYear();
                                const hours = String(now.getHours()).padStart(2, '0');
                                const minutes = String(now.getMinutes()).padStart(2, '0');
                                const timestamp = `${day}-${month}-${year} ${hours}:${minutes}`;
                                
                                statusElement.className = 'approval-date';
                                statusElement.textContent = timestamp;
                            }
                        }
                        
                        // Remove the button
                        buttonElement.remove();
                    }
                    
                    // Update approval count in header
                    const countElement = document.getElementById(`approval-count-${id}`);
                    if (countElement) {
                        const currentText = countElement.textContent;
                        const match = currentText.match(/Godkendt (\d+)\/3/);
                        if (match) {
                            const currentCount = parseInt(match[1]);
                            const newCount = currentCount + 1;
                            countElement.textContent = `Godkendt ${newCount}/3`;
                        }
                    }
                    
                    // Hide corresponding buttons in other views if they exist
                    const listViewButton = document.getElementById(`list-${rolePrefix}-btn-${id}`);
                    const cardViewButton = document.getElementById(`${rolePrefix}-btn-${id}`);
                    
                    if (listViewButton) listViewButton.style.display = 'none';
                    if (cardViewButton) cardViewButton.style.display = 'none';
                    
                    // Show success notification
                    showNotification(data.message, 'success');
                } else {
                    // Show error notification
                    showNotification(data.message, 'error');
                    
                    // Re-enable button
                    buttonElement.disabled = false;
                    buttonElement.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Der opstod en fejl ved godkendelsen. Prøv igen.', 'error');
                
                // Re-enable button
                buttonElement.disabled = false;
                buttonElement.textContent = originalText;
            });
        }

        
        // Update work status (for entrepreneurs)
        function updateWorkStatus(woId, newStatus) {
            // Create FormData for POST request
            const formData = new FormData();
            formData.append('ajax_work_status', '1');
            formData.append('wo_id', woId);
            formData.append('status', newStatus);
            
            // Get button elements
            const listButton = document.getElementById(`work-btn-${woId}`);
            const cardButton = document.getElementById(`card-work-btn-${woId}`);
            
            // Disable buttons and show loading state
            if (listButton) {
                listButton.disabled = true;
                listButton.textContent = '⏳ Opdaterer...';
            }
            if (cardButton) {
                cardButton.disabled = true;
                cardButton.textContent = '⏳ Opdaterer...';
            }
            
            fetch('view_wo.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update icon in both views
                    const listIcon = document.getElementById(`work-status-icon-${woId}`);
                    const cardIcon = document.getElementById(`card-work-status-icon-${woId}`);
                    
                    if (listIcon) {
                        listIcon.textContent = data.icon;
                        listIcon.title = data.status === 'working' ? 'Arbejder' : 'Stoppet';
                        listIcon.style.marginLeft = '0.5rem';
                        listIcon.style.fontSize = '1.1em';
                    }
                    if (cardIcon) {
                        cardIcon.textContent = data.icon;
                        cardIcon.title = data.status === 'working' ? 'Arbejder' : 'Stoppet';
                        cardIcon.style.marginLeft = '0.5rem';
                        cardIcon.style.fontSize = '1.3em';
                    }
                    
                    // Update buttons based on new status
                    if (data.status === 'working') {
                        if (listButton) {
                            listButton.textContent = '⏹️ Stop';
                            listButton.className = 'button button-warning';
                            listButton.onclick = function() { updateWorkStatus(woId, 'stopped'); };
                        }
                        if (cardButton) {
                            cardButton.textContent = '⏹️ Stop arbejde';
                            cardButton.className = 'button button-warning button-sm';
                            cardButton.onclick = function() { updateWorkStatus(woId, 'stopped'); };
                        }
                    } else {
                        if (listButton) {
                            listButton.textContent = '🔨 Start';
                            listButton.className = 'button button-primary';
                            listButton.onclick = function() { updateWorkStatus(woId, 'working'); };
                        }
                        if (cardButton) {
                            cardButton.textContent = '🔨 Start arbejde';
                            cardButton.className = 'button button-primary button-sm';
                            cardButton.onclick = function() { updateWorkStatus(woId, 'working'); };
                        }
                    }
                    
                    // Re-enable buttons
                    if (listButton) listButton.disabled = false;
                    if (cardButton) cardButton.disabled = false;
                    
                    // Show success notification
                    showNotification(data.message, 'success');
                } else {
                    // Show error notification
                    showNotification(data.message, 'error');
                    
                    // Reset buttons
                    if (listButton) {
                        listButton.disabled = false;
                        listButton.textContent = newStatus === 'working' ? '🔨 Start' : '⏹️ Stop';
                    }
                    if (cardButton) {
                        cardButton.disabled = false;
                        cardButton.textContent = newStatus === 'working' ? '🔨 Start arbejde' : '⏹️ Stop arbejde';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Der opstod en fejl. Prøv igen.', 'error');
                
                // Reset buttons
                if (listButton) {
                    listButton.disabled = false;
                    listButton.textContent = newStatus === 'working' ? '🔨 Start' : '⏹️ Stop';
                }
                if (cardButton) {
                    cardButton.disabled = false;
                    cardButton.textContent = newStatus === 'working' ? '🔨 Start arbejde' : '⏹️ Stop arbejde';
                }
            });
        }
        
        // View switching functionality
        function switchView(viewType) {
            const listView = document.getElementById('listView');
            const cardView = document.getElementById('cardView');
            const listBtn = document.getElementById('listViewBtn');
            const cardBtn = document.getElementById('cardViewBtn');
            const cardCounter = document.getElementById('cardCounter');
            
            if (viewType === 'list') {
                listView.style.display = 'block';
                cardView.style.display = 'none';
                listBtn.classList.add('active');
                cardBtn.classList.remove('active');
                
                // Hide counter
                if (cardCounter) {
                    cardCounter.classList.remove('visible');
                }
            } else {
                listView.style.display = 'none';
                cardView.style.display = 'block';
                listBtn.classList.remove('active');
                cardBtn.classList.add('active');
                
                // Show counter
                if (cardCounter) {
                    cardCounter.classList.add('visible');
                }
                
                // Initialize card navigation after layout is ready
                setTimeout(function() {
                    if (typeof updateCardNavigation === 'function') {
                        updateCardNavigation();
                    }
                }, 50);
            }
            
            // Save preference to localStorage
            localStorage.setItem('workPermitViewType', viewType);
            
            // Apply filters to the current view
            filterItems();
        }
        
        // Updated filter function to work with both views
        function filterItems() {
            var showPlanning = document.getElementById('filterPlanning').checked;
            var showActive = document.getElementById('filterActive').checked;
            var showCompleted = document.getElementById('filterCompleted').checked;
            
            // Filter table rows
            var rows = document.querySelectorAll('#ptwTable tr[data-status]');
            rows.forEach(function(row) {
                var status = row.getAttribute('data-status');
                if ((status === 'planning' && !showPlanning) ||
                    (status === 'active' && !showActive) ||
                    (status === 'completed' && !showCompleted)) {
                    row.style.display = 'none';
                } else {
                    row.style.display = '';
                }
            });
            
            // Filter cards
            var cards = document.querySelectorAll('.work-permit-card[data-status]');
            cards.forEach(function(card) {
                var status = card.getAttribute('data-status');
                if ((status === 'planning' && !showPlanning) ||
                    (status === 'active' && !showActive) ||
                    (status === 'completed' && !showCompleted)) {
                    card.style.display = 'none';
                } else {
                    card.style.display = 'block';
                }
            });
        }
        
        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Set up view toggle event listeners
            document.getElementById('listViewBtn').addEventListener('click', function() {
                switchView('list');
            });
            
            document.getElementById('cardViewBtn').addEventListener('click', function() {
                switchView('card');
            });
            
            // Set up filter event listeners
            document.getElementById('filterPlanning').addEventListener('change', filterItems);
            document.getElementById('filterActive').addEventListener('change', filterItems);
            document.getElementById('filterCompleted').addEventListener('change', filterItems);
            
            // Set up AJAX approval button listeners
            document.querySelectorAll('.ajax-approve-btn').forEach(function(button) {
                button.addEventListener('click', function(event) {
                    const id = this.getAttribute('data-id');
                    const role = this.getAttribute('data-role');
                    approveWorkPermit(id, role, event.currentTarget);
                });
            });
            
            // Card slider navigation
            const cardSlider = document.getElementById('cardSlider');
            const prevBtn = document.getElementById('prevCardBtn');
            const nextBtn = document.getElementById('nextCardBtn');
            const cardCounter = document.getElementById('cardCounter');
            const counterText = cardCounter ? cardCounter.querySelector('.counter-text') : null;
            
            if (cardSlider && prevBtn && nextBtn && counterText) {
                const cards = cardSlider.querySelectorAll('.work-permit-card');
                const totalCards = cards.length;
                
                // Update card counter based on scroll position
                function updateCardCounter() {
                    if (cards.length === 0) return;
                    
                    const cardWidth = cards[0].offsetWidth;
                    // If card width is 0 or invalid, default to first card
                    if (!cardWidth || cardWidth === 0) {
                        counterText.textContent = `PTW 1 af ${totalCards}`;
                        return;
                    }
                    
                    const scrollLeft = cardSlider.scrollLeft;
                    const currentIndex = Math.round(scrollLeft / cardWidth);
                    const cardNumber = Math.min(Math.max(currentIndex + 1, 1), totalCards);
                    
                    counterText.textContent = `PTW ${cardNumber} af ${totalCards}`;
                }
                
                // Previous button click
                prevBtn.addEventListener('click', function() {
                    const cardWidth = cardSlider.querySelector('.work-permit-card').offsetWidth;
                    cardSlider.scrollBy({ left: -cardWidth, behavior: 'smooth' });
                });
                
                // Next button click
                nextBtn.addEventListener('click', function() {
                    const cardWidth = cardSlider.querySelector('.work-permit-card').offsetWidth;
                    cardSlider.scrollBy({ left: cardWidth, behavior: 'smooth' });
                });
                
                // Update button states based on scroll position
                function updateNavButtons() {
                    const isAtStart = cardSlider.scrollLeft <= 10;
                    const isAtEnd = cardSlider.scrollLeft >= cardSlider.scrollWidth - cardSlider.clientWidth - 10;
                    
                    prevBtn.disabled = isAtStart;
                    nextBtn.disabled = isAtEnd;
                }
                
                // Combined update function
                function updateCardNavigation() {
                    updateNavButtons();
                    updateCardCounter();
                }
                
                // Listen to scroll events
                cardSlider.addEventListener('scroll', updateCardNavigation);
                
                // Snap to nearest card after manual scroll ends (backup for scroll-snap)
                let scrollTimeout;
                cardSlider.addEventListener('scroll', function() {
                    clearTimeout(scrollTimeout);
                    scrollTimeout = setTimeout(function() {
                        snapToNearestCard();
                    }, 150);
                });
                
                function snapToNearestCard() {
                    const cardWidth = cardSlider.querySelector('.work-permit-card')?.offsetWidth;
                    if (!cardWidth) return;
                    
                    const scrollLeft = cardSlider.scrollLeft;
                    const nearestCardIndex = Math.round(scrollLeft / cardWidth);
                    const targetScroll = nearestCardIndex * cardWidth;
                    
                    // Only snap if we're not already aligned
                    if (Math.abs(scrollLeft - targetScroll) > 5) {
                        cardSlider.scrollTo({
                            left: targetScroll,
                            behavior: 'smooth'
                        });
                    }
                }
                
                // Touch swipe functionality for mobile
                let touchStartX = 0;
                let touchEndX = 0;
                let touchStartY = 0;
                let touchEndY = 0;
                let isSwiping = false;
                
                cardSlider.addEventListener('touchstart', function(e) {
                    touchStartX = e.changedTouches[0].screenX;
                    touchStartY = e.changedTouches[0].screenY;
                    isSwiping = false;
                }, { passive: true });
                
                cardSlider.addEventListener('touchmove', function(e) {
                    const touchCurrentX = e.changedTouches[0].screenX;
                    const touchCurrentY = e.changedTouches[0].screenY;
                    const diffX = Math.abs(touchCurrentX - touchStartX);
                    const diffY = Math.abs(touchCurrentY - touchStartY);
                    
                    // If horizontal swipe is dominant, mark as swiping
                    if (diffX > diffY && diffX > 10) {
                        isSwiping = true;
                    }
                }, { passive: true });
                
                cardSlider.addEventListener('touchend', function(e) {
                    touchEndX = e.changedTouches[0].screenX;
                    touchEndY = e.changedTouches[0].screenY;
                    handleSwipe();
                }, { passive: true });
                
                function handleSwipe() {
                    if (!isSwiping) return;
                    
                    const swipeDistance = touchEndX - touchStartX;
                    const swipeThreshold = 50; // Minimum swipe distance in pixels
                    
                    if (Math.abs(swipeDistance) > swipeThreshold) {
                        const cardWidth = cardSlider.querySelector('.work-permit-card').offsetWidth;
                        
                        if (swipeDistance > 0) {
                            // Swipe right - go to previous card
                            cardSlider.scrollBy({ left: -cardWidth, behavior: 'smooth' });
                        } else {
                            // Swipe left - go to next card
                            cardSlider.scrollBy({ left: cardWidth, behavior: 'smooth' });
                        }
                    }
                }
            }
            
            // Load saved view preference and ensure proper initialization
            // On smartphones (under 768px), default to card view
            const isMobile = window.innerWidth < 768;
            const savedView = localStorage.getItem('workPermitViewType') || (isMobile ? 'card' : 'list');
            // Clear any existing styles that might conflict
            document.getElementById('listView').style.display = 'none';
            document.getElementById('cardView').style.display = 'none';
            // Then set the correct view
            switchView(savedView);
            
            // On mobile, collapse all time tracking and approval sections by default
            if (isMobile) {
                // Collapse all time tracking sections
                document.querySelectorAll('.time-entry-section').forEach(function(section) {
                    section.classList.remove('expanded');
                });
                
                document.querySelectorAll('.time-entry-section + * .toggle-icon').forEach(function(icon) {
                    icon.classList.remove('expanded');
                });
                
                // Find all toggle icons for time sections
                document.querySelectorAll('[id^="time-icon-"]').forEach(function(icon) {
                    icon.classList.remove('expanded');
                });
                
                // Collapse all approval workflow sections
                document.querySelectorAll('.approval-section').forEach(function(section) {
                    section.classList.remove('expanded');
                });
                
                // Find all toggle icons for approval sections
                document.querySelectorAll('[id^="approval-icon-"]').forEach(function(icon) {
                    icon.classList.remove('expanded');
                });
            }
        });
        </script>
    <?php else: ?>
        <p>Der er endnu ingen PTW?r oprettet.</p>
    <?php endif; ?>
    </div><!-- /.container -->
</body>
</html>