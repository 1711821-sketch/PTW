<?php
// Displays a list of all arbejdstilladelser stored in the system. Entries can
// be filtered by status and edited or deleted (admin only). Only
// authenticated users can access this page.

session_start();

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
                'message' => 'Arbejdstilladelse ikke fundet.'
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
                    'message' => 'Du har ikke tilladelse til at godkende denne arbejdstilladelse.'
                ]);
                exit();
            }
        }
        
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i');
        
        // Parse existing approvals and approval history
        $approvals = json_decode($workOrder['approvals'] ?? '{}', true) ?? [];
        $approvalHistory = json_decode($workOrder['approval_history'] ?? '[]', true) ?? [];
        
        // Check if already approved today
        if (isset($approvals[$approveRoleLc]) && $approvals[$approveRoleLc] === $today) {
            echo json_encode([
                'success' => false,
                'message' => 'Arbejdstilladelsen er allerede godkendt for denne rolle i dag.'
            ]);
            exit();
        }
        
        // Add new approval
        $approvals[$approveRoleLc] = $today;
        $approvalHistory[] = [
            'timestamp' => $now,
            'user' => $currentUser,
            'role' => $approveRoleLc
        ];
        
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
                'message' => 'Arbejdstilladelsen er blevet godkendt som ' . ucfirst($approveRole) . '.'
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
            // Only load work orders for this entrepreneur's firm
            $entries = $db->fetchAll("
                SELECT * FROM work_orders 
                WHERE entreprenor_firma = ? 
                ORDER BY created_at DESC
            ", [$firma]);
            
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
    
} catch (Exception $e) {
    error_log("Database error in view_wo.php: " . $e->getMessage());
    $entries = [];
}

// For legacy compatibility, also set the data_file variable for approval handling
$data_file = __DIR__ . '/wo_data.json';

$today = date('Y-m-d');
$now = date('Y-m-d H:i');

// Handle approval actions: if an approval request is present and the
// current user has permission to approve for that role, update the
// corresponding arbejdstilladelse entry. Approvals are stored in the entry's 'approvals'
// associative array keyed by role with the current date as the value. Only
// administrators or users whose role matches the approval role may
// approve.
if (isset($_GET['approve_id']) && isset($_GET['role'])) {
    $approveId   = $_GET['approve_id'];
    $approveRole = $_GET['role'];
    $sessionRole = $_SESSION['role'] ?? '';
    // Normalise role names to lowercase for comparison
    $approveRoleLc = strtolower($approveRole);
    $sessionRoleLc = strtolower($sessionRole);
    if ($sessionRoleLc === 'admin' || $sessionRoleLc === $approveRoleLc) {
        try {
            // SECURITY FIX: Use database with proper access control for approvals
            $workOrder = $db->fetch("SELECT * FROM work_orders WHERE id = ?", [$approveId]);
            
            if (!$workOrder) {
                error_log("Approval attempt failed - Work order not found - User: $currentUser, WO ID: $approveId");
                header('Location: view_wo.php?error=not_found');
                exit();
            }
            
            // CRITICAL: Check if entrepreneur is trying to approve another firm's work order
            if ($sessionRoleLc === 'entreprenor') {
                $userFirma = $_SESSION['entreprenor_firma'] ?? '';
                if ($workOrder['entreprenor_firma'] !== $userFirma) {
                    error_log("SECURITY VIOLATION: Entrepreneur attempted to approve another firm's work order - User: $currentUser, User Firma: $userFirma, WO Firma: " . $workOrder['entreprenor_firma'] . ", WO ID: $approveId");
                    header('Location: view_wo.php?error=unauthorized');
                    exit();
                }
            }
            
            // Parse existing approvals and approval history
            $approvals = json_decode($workOrder['approvals'] ?? '{}', true) ?? [];
            $approvalHistory = json_decode($workOrder['approval_history'] ?? '[]', true) ?? [];
            
            // Add new approval
            $approvals[$approveRoleLc] = $today;
            $approvalHistory[] = [
                'timestamp' => $now,
                'user' => $_SESSION['user'] ?? $sessionRole,
                'role' => $approveRoleLc
            ];
            
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
                error_log("Approval successful - User: $currentUser ($sessionRoleLc), WO ID: $approveId, Role: $approveRoleLc");
            } else {
                error_log("Approval failed - Database update failed - User: $currentUser, WO ID: $approveId");
            }
            
        } catch (Exception $e) {
            error_log("Approval error - User: $currentUser, WO ID: $approveId, Error: " . $e->getMessage());
        }
    } else {
        error_log("Approval denied - Insufficient permissions - User: $currentUser ($sessionRoleLc), WO ID: $approveId, Required Role: $approveRoleLc");
    }
    header('Location: view_wo.php');
    exit();
}

// Handle deletion (admin only) - SECURITY FIX: Use database with proper access control
$msg = '';
if ($role === 'admin' && isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    try {
        // SECURITY FIX: Use database with proper access control for deletions
        $workOrder = $db->fetch("SELECT * FROM work_orders WHERE id = ?", [$delete_id]);
        
        if (!$workOrder) {
            error_log("Delete attempt failed - Work order not found - User: $currentUser, WO ID: $delete_id");
            $msg = 'Kunne ikke finde arbejdstilladelse til sletning.';
        } else {
            // Delete work order and all related time entries
            $db->execute("DELETE FROM time_entries WHERE work_order_id = ?", [$delete_id]);
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
                
                $msg = 'Arbejdstilladelse er blevet slettet.';
            } else {
                error_log("Delete failed - Database error - User: $currentUser, WO ID: $delete_id");
                $msg = 'Fejl ved sletning af arbejdstilladelse.';
            }
        }
        
    } catch (Exception $e) {
        error_log("Delete error - User: $currentUser, WO ID: $delete_id, Error: " . $e->getMessage());
        $msg = 'Fejl ved sletning af arbejdstilladelse.';
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alle Arbejdstilladelser</title>
    <!-- Import the global stylesheet for a modern look -->
    <link rel="stylesheet" href="style.css">
    <script src="navigation.js"></script>
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
            <a href="index.php">Forside</a>
            <a href="view_wo.php">Oversigt over arbejdstilladelser</a>
            <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
                <a href="create_wo.php">Opret ny arbejdstilladelse</a>
            <?php endif; ?>
            <a href="map_wo.php">Kort</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="info.php">Informationer</a>
            <?php if ($role === 'admin'): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <span class="nav-user">Logget ind som <?php echo htmlspecialchars($_SESSION['user'] ?? ''); ?> (<?php echo htmlspecialchars($role ?? ''); ?>)</span>
            <a class="logout-link" href="logout.php">Log ud</a>
        </div>
    </nav>
    <div class="container">
    <div class="header-section">
        <h1>Oversigt over arbejdstilladelser</h1>
        <div class="view-toggle">
            <button id="listViewBtn" class="view-btn active" data-view="list">
                📋 Liste
            </button>
            <button id="cardViewBtn" class="view-btn" data-view="card">
                🗂️ Kort
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
            <table id="arbejdstilladelseTable">
                <thead>
                    <tr>
                        <th>🔢 Arbejdstilladelse Nr.</th>
                        <th>📋 Beskrivelse</th>
                        <th>📝 P-beskrivelse</th>
                        <th>👤 Jobansvarlig</th>
                        <th>🏢 Entreprenør</th>
                        <th>📊 Status</th>
                        <th>✅ Godkendelser<br>(dagens status)</th>
                        <th>⚡ Handlinger</th>
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
                <td><span class="<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                <td>
                    <div>
                        <strong>OA:</strong> <span><?php echo $oaApproved ? '✅' : '❌'; ?></span>
                        <?php if (!$oaApproved && ($role === 'admin' || $role === 'opgaveansvarlig')): ?>
                            <a class="button button-success button-sm" href="view_wo.php?approve_id=<?php echo urlencode($entry['id']); ?>&role=opgaveansvarlig">✓</a>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong>Drift:</strong> <span><?php echo $driftApproved ? '✅' : '❌'; ?></span>
                        <?php if (!$driftApproved && ($role === 'admin' || $role === 'drift')): ?>
                            <a class="button button-success button-sm" href="view_wo.php?approve_id=<?php echo urlencode($entry['id']); ?>&role=drift">✓</a>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong>Ent:</strong> <span><?php echo $entApproved ? '✅' : '❌'; ?></span>
                        <?php if (!$entApproved && ($role === 'admin' || $role === 'entreprenor')): ?>
                            <a class="button button-success button-sm" href="view_wo.php?approve_id=<?php echo urlencode($entry['id']); ?>&role=entreprenor">✓</a>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <a class="button button-secondary button-sm" href="print_wo.php?id=<?php echo urlencode($entry['id']); ?>" target="_blank">🖨️ Print</a>
                    <?php if ($role !== 'entreprenor'): ?>
                        <a class="button button-sm" href="create_wo.php?id=<?php echo urlencode($entry['id']); ?>">✏️ Rediger</a>
                    <?php endif; ?>
                    <?php if ($role === 'admin'): ?>
                        <a class="button button-danger button-sm" href="view_wo.php?delete_id=<?php echo urlencode($entry['id']); ?>" onclick="return confirm('Er du sikker på, at du vil slette denne arbejdstilladelse?');">🗑️ Slet</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Card View -->
        <div id="cardView" class="card-view" style="display: none;">
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
                
                $firma = $entry['entreprenor_firma'] ?? '';
                $kontakt = $entry['entreprenor_kontakt'] ?? '';
            ?>
            <div class="work-permit-card" data-status="<?php echo htmlspecialchars($status); ?>">
                <div class="card-header">
                    <div class="card-title">
                        <h3><?php echo htmlspecialchars($entry['work_order_no'] ?? ''); ?></h3>
                        <span class="card-status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                    </div>
                    <div class="card-id">ID: <?php echo htmlspecialchars($entry['id'] ?? ''); ?></div>
                </div>
                
                <div class="card-content">
                    <div class="card-description">
                        <h4>📋 Beskrivelse</h4>
                        <p><?php echo htmlspecialchars($entry['description'] ?? ''); ?></p>
                        <?php if (!empty($entry['p_description'])): ?>
                            <div class="p-description">
                                <strong>P-beskrivelse:</strong> <?php echo htmlspecialchars($entry['p_description']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
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
                        <?php if (!empty($entry['telefon'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">📞 Telefon:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($entry['telefon']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-approvals">
                        <h4>✅ Godkendelser</h4>
                        <div class="approval-grid">
                            <div class="approval-item">
                                <span class="approval-label">Opgaveansvarlig:</span>
                                <span class="approval-status <?php echo $oaApproved ? 'approved' : 'pending'; ?>" id="oa-status-<?php echo $entry['id']; ?>">
                                    <?php echo $oaApproved ? '✅ Godkendt' : '❌ Mangler'; ?>
                                </span>
                                <?php if (!$oaApproved && ($role === 'admin' || $role === 'opgaveansvarlig')): ?>
                                    <button class="button button-success button-sm ajax-approve-btn" 
                                            data-id="<?php echo htmlspecialchars($entry['id']); ?>" 
                                            data-role="opgaveansvarlig"
                                            id="oa-btn-<?php echo $entry['id']; ?>">
                                        ✓ Godkend som Opgaveansvarlig
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="approval-item">
                                <span class="approval-label">Drift:</span>
                                <span class="approval-status <?php echo $driftApproved ? 'approved' : 'pending'; ?>" id="drift-status-<?php echo $entry['id']; ?>">
                                    <?php echo $driftApproved ? '✅ Godkendt' : '❌ Mangler'; ?>
                                </span>
                                <?php if (!$driftApproved && ($role === 'admin' || $role === 'drift')): ?>
                                    <button class="button button-success button-sm ajax-approve-btn" 
                                            data-id="<?php echo htmlspecialchars($entry['id']); ?>" 
                                            data-role="drift"
                                            id="drift-btn-<?php echo $entry['id']; ?>">
                                        ✓ Godkend som Drift
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="approval-item">
                                <span class="approval-label">Entreprenør:</span>
                                <span class="approval-status <?php echo $entApproved ? 'approved' : 'pending'; ?>" id="ent-status-<?php echo $entry['id']; ?>">
                                    <?php echo $entApproved ? '✅ Godkendt' : '❌ Mangler'; ?>
                                </span>
                                <?php if (!$entApproved && ($role === 'admin' || $role === 'entreprenor')): ?>
                                    <button class="button button-success button-sm ajax-approve-btn" 
                                            data-id="<?php echo htmlspecialchars($entry['id']); ?>" 
                                            data-role="entreprenor"
                                            id="ent-btn-<?php echo $entry['id']; ?>">
                                        ✓ Godkend som Entreprenør
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
                    <a class="button button-secondary button-sm" href="print_wo.php?id=<?php echo urlencode($entry['id']); ?>" target="_blank">🖨️ Print</a>
                    <?php if ($role !== 'entreprenor'): ?>
                        <a class="button button-sm" href="create_wo.php?id=<?php echo urlencode($entry['id']); ?>">✏️ Rediger</a>
                    <?php endif; ?>
                    <?php if ($role === 'admin'): ?>
                        <a class="button button-danger button-sm" href="view_wo.php?delete_id=<?php echo urlencode($entry['id']); ?>" onclick="return confirm('Er du sikker på, at du vil slette denne arbejdstilladelse?');">🗑️ Slet</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
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
                    // Update status display
                    const statusElement = document.getElementById(`${role === 'opgaveansvarlig' ? 'oa' : role}-status-${id}`);
                    if (statusElement) {
                        statusElement.textContent = '✅ Godkendt';
                        statusElement.className = 'approval-status approved';
                    }
                    
                    // Hide the button
                    buttonElement.style.display = 'none';
                    
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
            var rows = document.querySelectorAll('#arbejdstilladelseTable tr[data-status]');
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
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const role = this.getAttribute('data-role');
                    approveWorkPermit(id, role, this);
                });
            });
            
            // Load saved view preference
            const savedView = localStorage.getItem('workPermitViewType') || 'list';
            switchView(savedView);
        });
        </script>
    <?php else: ?>
        <p>Der er endnu ingen arbejdstilladelser oprettet.</p>
    <?php endif; ?>
    <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
        <p><a href="create_wo.php">Opret ny arbejdstilladelse</a></p>
    <?php endif; ?>
    <p><a href="map_wo.php">Se oversigtskort</a></p>
    <p><a href="index.php">Tilbage til forsiden</a></p>
    </div><!-- /.container -->
</body>
</html>