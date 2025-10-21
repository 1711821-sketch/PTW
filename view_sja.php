<?php
// Displays a list of all Safety Job Analysis (SJA) entries. Provides
// options to view/print each entry and to edit existing entries. Only
// authenticated users can access this page.

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Use database instead of JSON files
require_once 'database.php';

$role = $_SESSION['role'] ?? 'user';
$entries = [];

// Handle deletion for admin
$msg = '';
if ($role === 'admin' && isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    try {
        $db = Database::getInstance();
        $result = $db->execute("DELETE FROM sja_entries WHERE id = ?", [$delete_id]);
        if ($result) {
            $msg = 'SJA er blevet slettet.';
        } else {
            $msg = 'Kunne ikke finde SJA til sletning.';
        }
    } catch (Exception $e) {
        error_log("Error deleting SJA: " . $e->getMessage());
        $msg = 'Fejl ved sletning af SJA.';
    }
}

// Load all SJA entries from database
try {
    $db = Database::getInstance();
    $dbEntries = $db->fetchAll("SELECT * FROM sja_entries ORDER BY created_at DESC");
    
    // Convert database format to expected format for display
    foreach ($dbEntries as $dbEntry) {
        $entries[] = [
            'id' => $dbEntry['id'],
            'basic' => json_decode($dbEntry['basic_info'] ?? '{}', true) ?: [],
            'status' => $dbEntry['status'] ?? 'active',
            'created_at' => $dbEntry['created_at'] ?? '',
            'updated_at' => $dbEntry['updated_at'] ?? ''
        ];
    }
} catch (Exception $e) {
    error_log("Error loading SJAs: " . $e->getMessage());
    $msg = 'Fejl ved indlæsning af SJA-liste.';
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Alle Sikker Job Analyser</title>
    <?php include 'pwa-head.php'; ?>
    <link rel="stylesheet" href="style.css">
    <script src="navigation.js"></script>
</head>
<body>
    <!-- Navigation bar with hamburger menu -->
    <nav class="navbar">
        <button class="navbar-toggle" aria-label="Toggle navigation">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
        <span class="nav-user nav-user-mobile"><?php echo htmlspecialchars($_SESSION['user']); ?></span>
        <div class="navbar-links">
            <a href="view_sja.php">SJA Oversigt</a>
            <a href="create_sja.php">Opret ny SJA</a>
            <a href="view_wo.php">PTW-oversigt</a>
            <a href="dashboard.php">Dashboard</a>
            <?php if ($role === 'admin'): ?>
                <a href="time_overblik.php">Timeoverblik</a>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <span class="nav-user nav-user-desktop">Logget ind som <?php echo htmlspecialchars($_SESSION['user']); ?> (<?php echo htmlspecialchars($role); ?>)</span>
            <a class="logout-link" href="logout.php">Log ud</a>
        </div>
    </nav>

    <div class="container">
        <h1>📋 Liste over Sikker Job Analyser (SJA)</h1>
        <?php if ($msg): ?>
            <div class="alert alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
    <?php if (count($entries) > 0): ?>
        <div class="filter-controls">
            <div class="filter-group">
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
        <div class="table-wrapper">
            <table id="sjaTable">
                <thead>
                    <tr>
                        <th>📋 Opgave</th>
                        <th>📅 Dato udførelse</th>
                        <th>👤 Planlagt af</th>
                        <th>🔧 Udføres af</th>
                        <th>📊 Status</th>
                        <th>⚡ Handlinger</th>
                    </tr>
                </thead>
                <tbody>
            <?php foreach ($entries as $entry):
                $basic = $entry['basic'] ?? [];
                // Determine status (default to active if not set)
                $status = $entry['status'] ?? 'active';
                $statusLabel = ($status === 'completed') ? 'Afsluttet' : 'Aktiv';
                $statusClass = ($status === 'completed') ? 'status-afsluttet' : 'status-aktiv';
            ?>
            <tr data-status="<?php echo htmlspecialchars($status); ?>">
                <td><?php echo htmlspecialchars($basic['opgave'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($basic['dato_udfoer'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($basic['planlagt_af'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($basic['udfoeres_af'] ?? ''); ?></td>
                <td><span class="<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                <td>
                    <a class="button button-secondary button-sm" href="print_sja.php?id=<?php echo urlencode($entry['id']); ?>" target="_blank">🖨️ Print</a>
                    <a class="button button-sm" href="create_sja.php?id=<?php echo urlencode($entry['id']); ?>">✏️ Rediger</a>
                    <a class="button button-secondary button-sm" href="sja_history.php?id=<?php echo urlencode($entry['id']); ?>">📜 Historik</a>
                    <?php if ($role === 'admin'): ?>
                        <a class="button button-danger button-sm" href="view_sja.php?delete_id=<?php echo urlencode($entry['id']); ?>" onclick="return confirm('Er du sikker på, at du vil slette denne SJA?');">🗑️ Slet</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
        // Filtering function to show/hide rows based on checkbox selection
        function filterRows() {
            var showActive = document.getElementById('filterActive').checked;
            var showCompleted = document.getElementById('filterCompleted').checked;
            var rows = document.querySelectorAll('#sjaTable tr[data-status]');
            rows.forEach(function(row) {
                var status = row.getAttribute('data-status');
                if ((status === 'active' && !showActive) || (status === 'completed' && !showCompleted)) {
                    row.style.display = 'none';
                } else {
                    row.style.display = '';
                }
            });
        }
        document.getElementById('filterActive').addEventListener('change', filterRows);
        document.getElementById('filterCompleted').addEventListener('change', filterRows);
        // Initial filtering on page load
        filterRows();
        </script>
    <?php else: ?>
        <div class="alert alert-info">
            📋 Der er endnu ingen SJA'er oprettet. <a href="create_sja.php">Opret den første SJA</a>
        </div>
    <?php endif; ?>
    
        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-light);">
            <a href="create_sja.php" class="button button-primary">➕ Opret ny SJA</a>
            <a href="map_overview.php" class="button button-secondary" style="margin-left: 1rem;">🗺️ Se oversigtskort</a>
            <a href="view_wo.php" class="button button-secondary" style="margin-left: 1rem;">🏠 Tilbage til PTW-oversigt</a>
        </div>
    </div>
</body>
</html>
