<?php
// Displays a list of all arbejdstilladelser stored in the system. Entries can
// be filtered by status and edited or deleted (admin only). Only
// authenticated users can access this page.

session_start();

// Handle AJAX approval requests
if (isset($_POST['ajax_approve']) && isset($_POST['approve_id']) && isset($_POST['role'])) {
    header('Content-Type: application/json');
    
    $approveId = $_POST['approve_id'];
    $approveRole = $_POST['role'];
    $sessionRole = $_SESSION['role'] ?? '';
    
    // Normalize role names to lowercase for comparison
    $approveRoleLc = strtolower($approveRole);
    $sessionRoleLc = strtolower($sessionRole);
    
    // Check permissions
    if ($sessionRoleLc !== 'admin' && $sessionRoleLc !== $approveRoleLc) {
        echo json_encode([
            'success' => false,
            'message' => 'Du har ikke tilladelse til at godkende som denne rolle.'
        ]);
        exit();
    }
    
    $data_file = __DIR__ . '/wo_data.json';
    $allEntries = [];
    if (file_exists($data_file)) {
        $allEntries = json_decode(file_get_contents($data_file), true);
        if (!is_array($allEntries)) {
            $allEntries = [];
        }
    }
    
    $found = false;
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i');
    
    foreach ($allEntries as &$entry) {
        if ((string)($entry['id'] ?? '') === (string)$approveId) {
            // Ensure the approvals array exists
            if (!isset($entry['approvals']) || !is_array($entry['approvals'])) {
                $entry['approvals'] = [];
            }
            // Ensure the approval_history array exists
            if (!isset($entry['approval_history']) || !is_array($entry['approval_history'])) {
                $entry['approval_history'] = [];
            }
            
            // Check if already approved
            if (isset($entry['approvals'][$approveRoleLc]) && $entry['approvals'][$approveRoleLc] === $today) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Arbejdstilladelsen er allerede godkendt for denne rolle i dag.'
                ]);
                exit();
            }
            
            // Append a new record to the approval history
            $entry['approval_history'][] = [
                'timestamp' => $now,
                'user' => $_SESSION['user'] ?? $sessionRole,
                'role' => $approveRoleLc
            ];
            
            // Set approval for today for the role
            $entry['approvals'][$approveRoleLc] = $today;
            
            // Persist ALL entries back to the JSON file
            if (file_put_contents($data_file, json_encode($allEntries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                $found = true;
                echo json_encode([
                    'success' => true,
                    'message' => 'Arbejdstilladelsen er blevet godkendt som ' . ucfirst($approveRole) . '.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Fejl ved gemning af godkendelse.'
                ]);
            }
            break;
        }
    }
    
    if (!$found) {
        echo json_encode([
            'success' => false,
            'message' => 'Arbejdstilladelse ikke fundet.'
        ]);
    }
    
    exit();
}

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$role = $_SESSION['role'] ?? 'user';
$data_file = __DIR__ . '/wo_data.json';
$entries = [];
if (file_exists($data_file)) {
    $entries = json_decode(file_get_contents($data_file), true);
    if (!is_array($entries)) {
        $entries = [];
    }
}

// If the logged-in user is an entrepreneur, restrict entries to only those
// work orders that belong to their own contractor firm.  The firm name is
// stored in the session at login time.  This prevents entrepreneurs from
// viewing work orders belonging to other companies.
if (($role ?? '') === 'entreprenor') {
    $firma = $_SESSION['entreprenor_firma'] ?? '';
    if ($firma !== '') {
        $entries = array_filter($entries, function ($entry) use ($firma) {
            return isset($entry['entreprenor_firma']) && $entry['entreprenor_firma'] === $firma;
        });
        // Re-index the array so foreach loops operate on sequential keys
        $entries = array_values($entries);
    } else {
        // If no firm is defined for the entrepreneur, show no entries at all
        $entries = [];
    }
}

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
        // CRITICAL FIX: Load ALL entries from file to avoid filtering issues
        // DO NOT use the filtered $entries array as it may be limited to entrepreneur's WOs
        $allEntries = [];
        if (file_exists($data_file)) {
            $allEntries = json_decode(file_get_contents($data_file), true);
            if (!is_array($allEntries)) {
                $allEntries = [];
            }
        }
        
        foreach ($allEntries as &$entry) {
            // Skip entries whose ID does not match the approval request
            if ((string)($entry['id'] ?? '') !== (string)$approveId) {
                continue;
            }
            // Ensure the approvals array exists
            if (!isset($entry['approvals']) || !is_array($entry['approvals'])) {
                $entry['approvals'] = [];
            }
            // Ensure the approval_history array exists
            if (!isset($entry['approval_history']) || !is_array($entry['approval_history'])) {
                $entry['approval_history'] = [];
            }
            // Append a new record to the approval history with timestamp, user and role
            $entry['approval_history'][] = [
                'timestamp' => $now,
                'user' => $_SESSION['user'] ?? $sessionRole,
                'role' => $approveRoleLc
            ];
            // Set approval for today for the role
            $entry['approvals'][$approveRoleLc] = $today;
            // Persist ALL entries back to the JSON file (not just filtered ones)
            file_put_contents($data_file, json_encode($allEntries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            break;
        }
    }
    header('Location: view_wo.php');
    exit();
}

// Handle deletion (admin only)
$msg = '';
if ($role === 'admin' && isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // CRITICAL FIX: Load ALL entries from file to avoid filtering issues
    $allEntries = [];
    if (file_exists($data_file)) {
        $allEntries = json_decode(file_get_contents($data_file), true);
        if (!is_array($allEntries)) {
            $allEntries = [];
        }
    }
    
    $new_entries = [];
    $deleted = false;
    foreach ($allEntries as $entry) {
        if (isset($entry['id']) && (string)$entry['id'] === (string)$delete_id) {
            $deleted = true;
            continue;
        }
        $new_entries[] = $entry;
    }
    if ($deleted) {
        file_put_contents($data_file, json_encode($new_entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        // Update the filtered entries for display purposes
        $entries = array_filter($new_entries, function ($entry) use ($role) {
            if (($role ?? '') === 'entreprenor') {
                $firma = $_SESSION['entreprenor_firma'] ?? '';
                if ($firma !== '') {
                    return isset($entry['entreprenor_firma']) && $entry['entreprenor_firma'] === $firma;
                }
                return false;
            }
            return true;
        });
        $entries = array_values($entries);
        $msg = 'Arbejdstilladelse er blevet slettet.';
    } else {
        $msg = 'Kunne ikke finde arbejdstilladelse til sletning.';
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