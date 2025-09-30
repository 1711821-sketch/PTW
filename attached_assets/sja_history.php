<?php
// This page displays the version history of a specific SJA entry.
// It shows all previous versions and allows comparison between versions.
// Only authenticated users may view this page.

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$data_file = __DIR__ . '/sja_data.json';

// Read the data
$entries = [];
if (file_exists($data_file)) {
    $entries = json_decode(file_get_contents($data_file), true);
    if (!is_array($entries)) {
        $entries = [];
    }
}

$id = $_GET['id'] ?? '';
$entry = null;
foreach ($entries as $e) {
    if (isset($e['id']) && $e['id'] === $id) {
        $entry = $e;
        break;
    }
}

if (!$entry) {
    echo '<p>SJA ikke fundet.</p>';
    exit();
}

// Normalize legacy entries that don't have version info
$current_version = $entry['version'] ?? 1;
$updated_at = $entry['updated_at'] ?? 'Ukendt';
$modified_by = $entry['modified_by'] ?? ($entry['created_by'] ?? 'Ukendt');
$history = $entry['history'] ?? [];

// Get opgave name for display
$opgave_name = $entry['basic']['opgave'] ?? 'Unavngiven SJA';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Versionshistorik for SJA</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; }
        h1 { margin-bottom: 0.5rem; }
        .subtitle { color: #666; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #ccc; padding: 0.6rem; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .current-version { background-color: #d4edda; }
        .version-badge { 
            display: inline-block;
            padding: 0.2rem 0.5rem;
            background-color: #0070C0;
            color: white;
            border-radius: 3px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .current-badge {
            background-color: #28a745;
        }
        a.button { 
            display: inline-block; 
            padding: 0.4rem 0.8rem; 
            margin: 0 0.2rem; 
            background-color: #007bff; 
            color: #fff; 
            text-decoration: none; 
            border-radius: 3px;
            font-size: 0.9em;
        }
        a.button:hover { background-color: #0056b3; }
        .back-link { margin-top: 1.5rem; display: inline-block; }
    </style>
</head>
<body>
    <h1>Versionshistorik</h1>
    <p class="subtitle">SJA: <strong><?php echo htmlspecialchars($opgave_name); ?></strong> (ID: <?php echo htmlspecialchars($id); ?>)</p>

    <?php if (count($history) === 0): ?>
        <p>Denne SJA har ingen tidligere versioner. Den nuværende version er den første.</p>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Version</th>
                <th>Tidsstempel</th>
                <th>Ændret af</th>
                <th>Handlinger</th>
            </tr>
        </thead>
        <tbody>
            <!-- Current version -->
            <tr class="current-version">
                <td>
                    <span class="version-badge current-badge">v<?php echo $current_version; ?></span>
                    <em>(nuværende)</em>
                </td>
                <td><?php echo htmlspecialchars($updated_at); ?></td>
                <td><?php echo htmlspecialchars($modified_by); ?></td>
                <td>
                    <a class="button" href="print_sja.php?id=<?php echo urlencode($id); ?>" target="_blank">Vis</a>
                    <?php if (count($history) > 0): ?>
                        <a class="button" href="sja_compare.php?id=<?php echo urlencode($id); ?>&v1=<?php echo $current_version; ?>&v2=<?php echo $history[count($history)-1]['version']; ?>">Sammenlign</a>
                    <?php endif; ?>
                </td>
            </tr>

            <!-- Historical versions (newest first) -->
            <?php 
            $reversed_history = array_reverse($history);
            foreach ($reversed_history as $idx => $hist_entry): 
                $hist_version = $hist_entry['version'] ?? '?';
                $hist_timestamp = $hist_entry['timestamp'] ?? 'Ukendt';
                $hist_modified_by = $hist_entry['modified_by'] ?? 'Ukendt';
            ?>
            <tr>
                <td><span class="version-badge">v<?php echo htmlspecialchars($hist_version); ?></span></td>
                <td><?php echo htmlspecialchars($hist_timestamp); ?></td>
                <td><?php echo htmlspecialchars($hist_modified_by); ?></td>
                <td>
                    <a class="button" href="sja_view_version.php?id=<?php echo urlencode($id); ?>&version=<?php echo urlencode($hist_version); ?>" target="_blank">Vis</a>
                    <a class="button" href="sja_compare.php?id=<?php echo urlencode($id); ?>&v1=<?php echo $current_version; ?>&v2=<?php echo urlencode($hist_version); ?>">Sammenlign</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="back-link">
        <a href="view_sja.php">← Tilbage til SJA-liste</a> |
        <a href="print_sja.php?id=<?php echo urlencode($id); ?>">← Tilbage til SJA</a>
    </p>
</body>
</html>
