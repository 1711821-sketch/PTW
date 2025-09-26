<?php
// Displays a list of all Safety Job Analysis (SJA) entries. Provides
// options to view/print each entry and to edit existing entries. Only
// authenticated users can access this page.

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$role = $_SESSION['role'] ?? 'user';
$data_file = __DIR__ . '/sja_data.json';
$entries = [];
if (file_exists($data_file)) {
    $entries = json_decode(file_get_contents($data_file), true);
    if (!is_array($entries)) {
        $entries = [];
    }
}

// Handle deletion for admin
$msg = '';
if ($role === 'admin' && isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $new_entries = [];
    $deleted = false;
    foreach ($entries as $entry) {
        if (isset($entry['id']) && $entry['id'] === $delete_id) {
            $deleted = true;
            continue;
        }
        $new_entries[] = $entry;
    }
    if ($deleted) {
        file_put_contents($data_file, json_encode($new_entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $entries = $new_entries;
        $msg = 'SJA er blevet slettet.';
    } else {
        $msg = 'Kunne ikke finde SJA til sletning.';
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alle Sikker Job Analyser</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 0.4rem; text-align: left; }
        th { background-color: #f2f2f2; }
        a.button { display: inline-block; padding: 0.3rem 0.6rem; margin: 0 0.2rem; background-color: #007bff; color: #fff; text-decoration: none; border-radius: 3px; }
        /* Status label styles */
        .status-active { color: #155724; background-color: #d4edda; font-weight: bold; padding: 0.2rem 0.4rem; border-radius: 3px; }
        .status-completed { color: #004085; background-color: #cce5ff; font-weight: bold; padding: 0.2rem 0.4rem; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Liste over Sikker Job Analyser (SJA)</h1>
    <?php if ($msg): ?>
        <p style="color: green;"><?php echo $msg; ?></p>
    <?php endif; ?>
    <?php if (count($entries) > 0): ?>
        <!-- Filter controls to show/hide SJA by status -->
        <div style="margin-bottom: 1rem;">
            <label><input type="checkbox" id="filterActive" checked> Vis aktive</label>
            <label style="margin-left:1rem;"><input type="checkbox" id="filterCompleted" checked> Vis afsluttede</label>
        </div>
        <table id="sjaTable">
            <tr>
                <th>Opgave</th>
                <th>Dato udførelse</th>
                <th>Planlagt af</th>
                <th>Udføres af</th>
                <th>Status</th>
                <th>Handlinger</th>
            </tr>
            <?php foreach ($entries as $entry):
                $basic = $entry['basic'] ?? [];
                // Determine status (default to active if not set)
                $status = $entry['status'] ?? 'active';
                $statusLabel = ($status === 'completed') ? 'Afsluttet' : 'Aktiv';
                $statusClass = ($status === 'completed') ? 'status-completed' : 'status-active';
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
                    <?php if ($role === 'admin'): ?>
                        <a class="button button-danger button-sm" href="view_sja.php?delete_id=<?php echo urlencode($entry['id']); ?>" onclick="return confirm('Er du sikker på, at du vil slette denne SJA?');">🗑️ Slet</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
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
        <p>Der er endnu ingen SJA'er oprettet.</p>
    <?php endif; ?>
    <p><a href="create_sja.php">Opret ny SJA</a></p>
    <p><a href="map_overview.php">Se oversigtskort</a></p>
    <p><a href="index.php">Tilbage til forsiden</a></p>
</body>
</html>
