<?php
// Displays a list of all PTW?r stored in the system. Entries can
// be filtered by status and edited or deleted (admin only). Only
// authenticated users can access this page.

session_start();

// Set Danish timezone
date_default_timezone_set('Europe/Copenhagen');

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
        
        // Update database
        $updated = $db->execute("
            UPDATE work_orders 
            SET approvals = ?, approval_history = ?, updated_at = NOW()
            WHERE id = ?
        ", [
            json_encode($approvals),
            json_encode($approvalHistory),
            $approveId
        ]);
        
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
        
        // Update database
        $updated = $db->execute("
            UPDATE work_orders 
            SET daily_work_status = ?, updated_at = NOW()
            WHERE id = ?
        ", [
            json_encode($workStatus),
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

try {
    $db = Database::getInstance();
    
    // Apply role-based access control at database level
    if ($currentRole === 'entreprenor') {
        $firma = $_SESSION['entreprenor_firma'] ?? '';
        if ($firma !== '') {
            $today = date('d-m-Y');
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
            ", [$firma, $today, $today]);
            
            error_log("Work order access - Entrepreneur: $currentUser, Firma: $firma, Count: " . count($entries));
        } else {
            // If no firm is defined, show no entries
            $entries = [];
            error_log("Work order access denied - Entrepreneur: $currentUser, No firma defined");
        }
    } elseif (in_array($currentRole, ['admin', 'opgaveansvarlig', 'drift'])) {
        // Admin, opgaveansvarlig and drift can see all work orders
        $entries = $db->fetchAll("
            SELECT * FROM work_orders 
            ORDER BY created_at DESC
        ");
        
        error_log("Work order access - User: $currentUser, Role: $currentRole, Count: " . count($entries));
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

        /* Modern Card Slider Styles */
        .card-view-wrapper {
            position: relative;
            width: 100%;
            padding: 1rem 0;
        }

        .slider-container {
            position: relative;
            width: 100%;
            overflow-x: hidden;
        }

        .card-slider {
            display: flex;
            gap: 0;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            padding: 0;
            width: 100%;
        }

        /* Navigation Buttons - positioned outside the card */
        .slider-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            display: flex;
            justify-content: space-between;
            pointer-events: none;
            padding: 0 0.5rem;
        }

        .slider-nav button {
            pointer-events: all;
            background: rgba(0, 0, 0, 0.5);
            border: none;
            color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .slider-nav button:hover {
            background: rgba(0, 0, 0, 0.7);
            transform: scale(1.1);
        }

        .slider-nav button:active {
            transform: scale(0.95);
        }

        .slider-nav button:disabled {
            opacity: 0.3;
            cursor: not-allowed;
            background: rgba(0, 0, 0, 0.3);
        }

        .card-slider::-webkit-scrollbar {
            height: 8px;
        }

        .card-slider::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 4px;
        }

        .card-slider::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
        }

        .card-slider::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.3);
        }

        .work-permit-card {
            flex: 0 0 100%;
            width: 100%;
            min-width: 100%;
            background: var(--background-primary);
            border-radius: 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            scroll-snap-align: start;
            display: flex;
            flex-direction: column;
            transition: transform 0.4s ease, opacity 0.3s ease, box-shadow 0.2s ease;
        }

        .work-permit-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        /* Desktop: centered card with max-width */
        @media (min-width: 1024px) {
            .card-slider {
                scroll-padding-inline: calc((100vw - 800px) / 2);
                padding-inline: calc((100vw - 800px) / 2);
            }

            .work-permit-card {
                flex: 0 0 800px;
                width: 800px;
                min-width: 800px;
                max-width: 800px;
                scroll-snap-align: center;
                border-radius: 16px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }

            .slider-nav {
                padding: 0;
            }
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
        }

        .card-title {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }

        .card-title-text {
            flex: 1;
        }

        .card-title-text h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            color: var(--text-primary);
            font-weight: 600;
        }

        .card-header-description {
            margin: 0;
            font-size: 0.95rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .card-status {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .card-status.status-planlagt {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .card-status.status-aktiv {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .card-status.status-afsluttet {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
        }

        .card-p-description {
            margin-top: 0.75rem;
            padding: 0.75rem;
            background: rgba(59, 130, 246, 0.05);
            border-left: 3px solid var(--primary-color);
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .card-p-description strong {
            color: var(--primary-color);
        }

        .card-divider {
            margin: 0;
            border: none;
            border-top: 1px solid var(--border-light);
        }

        .card-content {
            padding: 1.5rem;
            flex: 1;
        }

        .card-details {
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 10px;
        }

        .detail-item:last-child {
            margin-bottom: 0;
        }

        .detail-label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
            flex-shrink: 0;
            min-width: 130px;
        }

        .detail-value {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .card-approvals {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.03) 0%, rgba(16, 185, 129, 0.03) 100%);
            border-radius: 12px;
        }

        .card-approvals h4 {
            margin: 0 0 1rem 0;
            font-size: 1.1rem;
            color: var(--text-primary);
        }

        .approval-grid {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .approval-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .approval-label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
            min-width: 140px;
        }

        .approval-status {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .approval-status.approved {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .approval-status.pending {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .approval-item .button {
            margin-left: auto;
        }

        .card-time-tracking {
            padding: 1rem;
            background: rgba(249, 115, 22, 0.03);
            border-radius: 12px;
        }

        .card-time-tracking h4 {
            margin: 0 0 1rem 0;
            font-size: 1.1rem;
            color: var(--text-primary);
        }

        .card-actions {
            display: flex;
            gap: 0.75rem;
            padding: 1.5rem;
            border-top: 1px solid var(--border-light);
            background: rgba(0, 0, 0, 0.01);
        }

        .card-actions .button {
            flex: 1;
            min-height: 48px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .card-actions .button:active {
            transform: scale(0.98);
        }

        /* Mobile optimizations */
        @media (max-width: 768px) {
            .work-permit-card {
                border-radius: 0;
                max-width: 100%;
                padding: 0;
            }

            .card-title-text h3 {
                font-size: 1.3rem;
            }

            .card-status {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }

            .detail-label {
                min-width: 110px;
                font-size: 0.85rem;
            }

            .detail-value {
                font-size: 0.85rem;
            }

            .card-actions {
                flex-direction: column;
            }

            .card-actions .button {
                width: 100%;
                min-height: 52px;
            }

            /* Hide navigation buttons on mobile - swipe is natural */
            .slider-nav button {
                display: none;
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

        /* Mobile Optimizations for New Buttons */
        @media (max-width: 768px) {
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
    </style>
</head>
<body>
    <!-- Top navigation bar with hamburger menu -->
    <nav class="navbar">
        <button class="navbar-toggle" aria-label="Toggle navigation">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
        <div class="navbar-links">
            <a href="view_wo.php">PTW-oversigt</a>
            <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
                <a href="create_wo.php">Opret ny PTW?</a>
            <?php endif; ?>
            <a href="map_wo.php">Kort</a>
            <a href="dashboard.php">Dashboard</a>
            <?php if ($role === 'admin'): ?>
                <a href="time_overblik.php">Timeoverblik</a>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <span class="nav-user">Logget ind som <?php echo htmlspecialchars($_SESSION['user'] ?? ''); ?> (<?php echo htmlspecialchars($role ?? ''); ?>)</span>
            <a class="logout-link" href="logout.php">Log ud</a>
        </div>
    </nav>
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
                        <th>P-beskrivelse</th>
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
                        <?php if (!$oaApproved && ($role === 'admin' || $role === 'opgaveansvarlig')): ?>
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
                        <?php if (!$driftApproved && ($role === 'admin' || $role === 'drift')): ?>
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
                        <?php if (!$entApproved && ($role === 'admin' || $role === 'entreprenor')): ?>
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
                        <?php if ($role === 'entreprenor' && $status === 'active'): ?>
                            <?php if (!$workStatusToday || $workStatus['status'] === 'stopped'): ?>
                                <button id="work-btn-<?php echo $entry['id']; ?>" class="btn-work btn-start" onclick="updateWorkStatus(<?php echo $entry['id']; ?>, 'working')" title="Start arbejde">
                                    <span class="icon">🔨</span>
                                    <span class="text">Start</span>
                                </button>
                            <?php else: ?>
                                <button id="work-btn-<?php echo $entry['id']; ?>" class="btn-work btn-stop" onclick="updateWorkStatus(<?php echo $entry['id']; ?>, 'stopped')" title="Stop arbejde">
                                    <span class="icon">⏹️</span>
                                    <span class="text">Stop</span>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
                </tbody>
            </table>
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
                <div class="card-header">
                    <div class="card-title">
                        <div class="card-title-text">
                            <h3><?php echo htmlspecialchars($entry['work_order_no'] ?? ''); ?></h3>
                            <p class="card-header-description"><?php echo htmlspecialchars($entry['description'] ?? ''); ?></p>
                        </div>
                        <span class="card-status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                        <?php if ($workStatusIcon): ?>
                            <span id="card-work-status-icon-<?php echo $entry['id']; ?>" title="<?php echo htmlspecialchars($workStatusText); ?>" style="margin-left: 0.5rem; font-size: 1.3em;"><?php echo $workStatusIcon; ?></span>
                        <?php else: ?>
                            <span id="card-work-status-icon-<?php echo $entry['id']; ?>"></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($entry['p_description'])): ?>
                        <div class="card-p-description">
                            <strong>P-beskrivelse:</strong> <?php echo htmlspecialchars($entry['p_description']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <hr class="card-divider">
                
                <div class="card-content">
                    <div class="card-details">
                        <div class="detail-item">
                            <span class="detail-label">👤 Jobansvarlig:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($entry['jobansvarlig'] ?? ''); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">🏢 Entreprenør:</span>
                            <span class="detail-value">
                                <?php echo htmlspecialchars($firma); ?>
                                <?php if ($kontakt): ?>
                                    <br><small><?php echo htmlspecialchars($kontakt); ?></small>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-approvals">
                        <h4>✅ Godkendelser</h4>
                        <div class="approval-grid">
                            <div class="approval-item">
                                <span class="approval-label" title="Opgaveansvarlige">OA:</span>
                                <span class="approval-status <?php echo $oaApproved ? 'approved' : 'pending'; ?>" id="oa-status-<?php echo $entry['id']; ?>">
                                    <?php echo $oaApproved ? '✅ Godkendt' : '❌ Mangler'; ?>
                                </span>
                                <?php if (!$oaApproved && ($role === 'admin' || $role === 'opgaveansvarlig')): ?>
                                    <button class="btn-approve-card ajax-approve-btn" 
                                            data-id="<?php echo htmlspecialchars($entry['id']); ?>" 
                                            data-role="opgaveansvarlig"
                                            id="oa-btn-<?php echo $entry['id']; ?>"
                                            title="Godkend som Opgaveansvarlig">
                                        ✓ Godkend
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="approval-item">
                                <span class="approval-label" title="Driften">Drift:</span>
                                <span class="approval-status <?php echo $driftApproved ? 'approved' : 'pending'; ?>" id="drift-status-<?php echo $entry['id']; ?>">
                                    <?php echo $driftApproved ? '✅ Godkendt' : '❌ Mangler'; ?>
                                </span>
                                <?php if (!$driftApproved && ($role === 'admin' || $role === 'drift')): ?>
                                    <button class="btn-approve-card ajax-approve-btn" 
                                            data-id="<?php echo htmlspecialchars($entry['id']); ?>" 
                                            data-role="drift"
                                            id="drift-btn-<?php echo $entry['id']; ?>"
                                            title="Godkend som Drift">
                                        ✓ Godkend
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="approval-item">
                                <span class="approval-label" title="Entreprenør">Ent:</span>
                                <span class="approval-status <?php echo $entApproved ? 'approved' : 'pending'; ?>" id="ent-status-<?php echo $entry['id']; ?>">
                                    <?php echo $entApproved ? '✅ Godkendt' : '❌ Mangler'; ?>
                                </span>
                                <?php if (!$entApproved && ($role === 'admin' || $role === 'entreprenor')): ?>
                                    <button class="btn-approve-card ajax-approve-btn" 
                                            data-id="<?php echo htmlspecialchars($entry['id']); ?>" 
                                            data-role="entreprenor"
                                            id="ent-btn-<?php echo $entry['id']; ?>"
                                            title="Godkend som Entreprenør">
                                        ✓ Godkend
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (in_array($role, ['entreprenor', 'admin', 'opgaveansvarlig', 'drift'])): ?>
                    <div class="card-time-tracking">
                        <h4>⏱️ Tidsregistrering</h4>
                        <div class="time-entry-section" id="time-section-<?php echo $entry['id']; ?>">
                            
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
                </div>
                
                <div class="card-actions">
                    <a class="button button-secondary button-sm handlinger-btn" href="print_wo.php?id=<?php echo urlencode($entry['id']); ?>">Vis</a>
                    <?php if ($role !== 'entreprenor'): ?>
                        <a class="button button-sm handlinger-btn" href="create_wo.php?id=<?php echo urlencode($entry['id']); ?>">Rediger</a>
                    <?php endif; ?>
                    <?php if ($role === 'admin'): ?>
                        <a class="button button-danger button-sm handlinger-btn" href="view_wo.php?delete_id=<?php echo urlencode($entry['id']); ?>" onclick="return confirm('Er du sikker på, at du vil slette denne PTW??');">Slet</a>
                    <?php endif; ?>
                    <?php if ($role === 'entreprenor' && $status === 'active'): ?>
                        <?php if (!$workStatusToday || $workStatus['status'] === 'stopped'): ?>
                            <button id="card-work-btn-<?php echo $entry['id']; ?>" class="button button-primary button-sm" onclick="updateWorkStatus(<?php echo $entry['id']; ?>, 'working')">🔨 Start arbejde</button>
                        <?php else: ?>
                            <button id="card-work-btn-<?php echo $entry['id']; ?>" class="button button-warning button-sm" onclick="updateWorkStatus(<?php echo $entry['id']; ?>, 'stopped')">⏹️ Stop arbejde</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
                </div>
                <div class="slider-nav">
                    <button id="prevCardBtn" aria-label="Forrige PTW">◀</button>
                    <button id="nextCardBtn" aria-label="Næste PTW">▶</button>
                </div>
            </div>
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
        
        // Initialize time entries for today's date when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const dateInputs = document.querySelectorAll('.time-date-input');
            dateInputs.forEach(input => {
                const workOrderId = input.id.replace('time-date-', '');
                loadTimeEntry(workOrderId, input.value);
            });
        });

        // AJAX approval function
        function approveWorkPermit(id, role, buttonElement) {
            // Special confirmation for entrepreneurs
            if (role === 'entreprenor') {
                showEntrepreneurConfirmModal(function(confirmed) {
                    if (confirmed) {
                        performApproval(id, role, buttonElement);
                    }
                });
                return;
            }
            
            // For other roles, proceed directly
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
                    
                    // Card View element
                    const cardViewElement = document.querySelector(`#cardView #${rolePrefix}-status-${id}`);
                    if (cardViewElement) {
                        cardViewElement.textContent = '✅ Godkendt';
                        cardViewElement.className = 'approval-status approved';
                    }
                    
                    // Hide the button that was clicked
                    buttonElement.style.display = 'none';
                    
                    // Also hide corresponding button in the other view if it exists
                    // List View buttons have 'list-' prefix, Card View buttons don't
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
        
        // Custom modal for entrepreneur confirmation
        function showEntrepreneurConfirmModal(callback) {
            const modal = document.createElement('div');
            modal.className = 'custom-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <p>Du har nu godkendt PTW for denne opgave, være opmærksom på at du først kan påbegynde opgaven når Driften og Opgaveansvarlig også har godkendt opgaven. Jeg bekræfter at jeg først påbegynder opgaven når Driften og Opgaveansvarlige også har godkendt opgaven</p>
                    <div class="modal-buttons">
                        <button class="modal-btn btn-yes">OK - Jeg forstår</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            modal.querySelector('.btn-yes').onclick = function() {
                document.body.removeChild(modal);
                callback(true);
            };
            
            // Close modal when clicking outside
            modal.onclick = function(e) {
                if (e.target === modal) {
                    document.body.removeChild(modal);
                    callback(false);
                }
            };
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
            
            if (viewType === 'list') {
                listView.style.display = 'block';
                cardView.style.display = 'none';
                listBtn.classList.add('active');
                cardBtn.classList.remove('active');
            } else {
                listView.style.display = 'none';
                cardView.style.display = 'block';
                listBtn.classList.remove('active');
                cardBtn.classList.add('active');
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
            
            if (cardSlider && prevBtn && nextBtn) {
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
                
                // Listen to scroll events
                cardSlider.addEventListener('scroll', updateNavButtons);
                
                // Initial button state
                updateNavButtons();
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
        });
        </script>
    <?php else: ?>
        <p>Der er endnu ingen PTW?r oprettet.</p>
    <?php endif; ?>
    <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
        <p><a href="create_wo.php">Opret ny PTW?</a></p>
    <?php endif; ?>
    <p><a href="map_wo.php">Se oversigtskort</a></p>
    </div><!-- /.container -->
</body>
</html>