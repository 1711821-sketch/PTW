<?php
// Displays a list of all Work Orders (WO) stored in the system. Entries can
// be filtered by status and edited or deleted (admin only). Only
// authenticated users can access this page.

session_start();
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
// corresponding WO entry. Approvals are stored in the entry's 'approvals'
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
        $msg = 'WO er blevet slettet.';
    } else {
        $msg = 'Kunne ikke finde WO til sletning.';
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alle Arbejdstilladelser / WO</title>
    <!-- Import the global stylesheet for a modern look -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Top navigation bar -->
    <nav class="navbar">
        <a href="view_wo.php">WO Oversigt</a>
        <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
            <a href="create_wo.php">Opret ny WO</a>
        <?php endif; ?>
        <a href="map_wo.php">Kort</a>
        <!-- Link to the dashboard for quick access to statistics -->
        <a href="dashboard.php">Dashboard</a>
        <span class="nav-user">Logget ind som <?php echo htmlspecialchars($_SESSION['user'] ?? ''); ?> (<?php echo htmlspecialchars($role ?? ''); ?>)</span>
        <a class="logout-link" href="logout.php">Log ud</a>
    </nav>
    <div class="container">
    <h1>Liste over Arbejdstilladelser (WO)</h1>
    <?php if ($msg): ?>
        <p style="color: green;"><?php echo $msg; ?></p>
    <?php endif; ?>
    <?php if (count($entries) > 0): ?>
        <div style="margin-bottom: 1rem;">
            <label><input type="checkbox" id="filterPlanning" checked> Vis planlagte</label>
            <label style="margin-left:1rem;"><input type="checkbox" id="filterActive" checked> Vis aktive</label>
            <label style="margin-left:1rem;"><input type="checkbox" id="filterCompleted" checked> Vis afsluttede</label>
        </div>
        <table id="woTable">
            <tr>
                <th>WO Nr.</th>
                <th>Beskrivelse</th>
                <th>P-beskrivelse</th>
                <th>Jobansvarlig</th>
                <th>Entreprenør</th>
                <th>Dato oprettet</th>
                <th>Status</th>
                <th>Godkendelser (dagens status)</th>
                <th>Handlinger</th>
            </tr>
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
                <td><?php echo htmlspecialchars($entry['oprettet_dato'] ?? ''); ?></td>
                <td><span class="<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                <td style="white-space: nowrap;">
                    <div style="margin-bottom: 0.3rem;">
                        <strong>Opgaveansvarlig:</strong> 
                        <span><?php echo $oaApproved ? '✅' : '❌'; ?></span>
                        <?php if (!$oaApproved && ($role === 'admin' || $role === 'opgaveansvarlig')): ?>
                            <a class="button" style="display: inline; margin-left: 0.5rem;" href="view_wo.php?approve_id=<?php echo urlencode($entry['id']); ?>&role=opgaveansvarlig">Godkend</a>
                        <?php endif; ?>
                    </div>
                    <div style="margin-bottom: 0.3rem;">
                        <strong>Driften:</strong> 
                        <span><?php echo $driftApproved ? '✅' : '❌'; ?></span>
                        <?php if (!$driftApproved && ($role === 'admin' || $role === 'drift')): ?>
                            <a class="button" style="display: inline; margin-left: 0.5rem;" href="view_wo.php?approve_id=<?php echo urlencode($entry['id']); ?>&role=drift">Godkend</a>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong>Entreprenør:</strong> 
                        <span><?php echo $entApproved ? '✅' : '❌'; ?></span>
                        <?php if (!$entApproved && ($role === 'admin' || $role === 'entreprenor')): ?>
                            <a class="button" style="display: inline; margin-left: 0.5rem;" href="view_wo.php?approve_id=<?php echo urlencode($entry['id']); ?>&role=entreprenor">Godkend</a>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <a class="button" href="print_wo.php?id=<?php echo urlencode($entry['id']); ?>" target="_blank">Print / Vis</a>
                    <a class="button" href="create_wo.php?id=<?php echo urlencode($entry['id']); ?>">Rediger</a>
                    <?php if ($role === 'admin'): ?>
                        <a class="button" style="background-color:#d9534f" href="view_wo.php?delete_id=<?php echo urlencode($entry['id']); ?>" onclick="return confirm('Er du sikker på, at du vil slette denne WO?');">Slet</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <script>
        function filterRows() {
            var showPlanning = document.getElementById('filterPlanning').checked;
            var showActive = document.getElementById('filterActive').checked;
            var showCompleted = document.getElementById('filterCompleted').checked;
            var rows = document.querySelectorAll('#woTable tr[data-status]');
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
        }
        document.getElementById('filterPlanning').addEventListener('change', filterRows);
        document.getElementById('filterActive').addEventListener('change', filterRows);
        document.getElementById('filterCompleted').addEventListener('change', filterRows);
        filterRows();
        </script>
    <?php else: ?>
        <p>Der er endnu ingen WO'er oprettet.</p>
    <?php endif; ?>
    <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
        <p><a href="create_wo.php">Opret ny WO</a></p>
    <?php endif; ?>
    <p><a href="map_wo.php">Se oversigtskort</a></p>
    <p><a href="index.php">Tilbage til forsiden</a></p>
    </div><!-- /.container -->
</body>
</html>