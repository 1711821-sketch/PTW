<?php
// Displays a single arbejdstilladelse in a print friendly layout.  Requires
// authentication and an `id` query parameter referencing the WO entry.

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$data_file = __DIR__ . '/wo_data.json';
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
    if (isset($e['id']) && (string)$e['id'] === (string)$id) {
        $entry = $e;
        break;
    }
}
if (!$entry) {
    echo '<p>Arbejdstilladelse ikke fundet.</p>';
    exit();
}

// Determine status label
$statusVal = $entry['status'] ?? 'planning';
// Determine the current user role for navigation bar (if available)
$role = $_SESSION['role'] ?? '';
if ($statusVal === 'planning') {
    $statusLabel = 'Planlagt';
} elseif ($statusVal === 'active') {
    $statusLabel = 'Aktiv';
} else {
    $statusLabel = 'Afsluttet';
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vis/Print arbejdstilladelse</title>
    <!-- Import global stylesheet for consistent typography and navigation -->
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 1rem; color: #222; }
        h1 { margin-top: 0; font-size: 1.6em; text-align: center; }
        h2 { margin-top: 1.2rem; font-size: 1.3em; border-bottom: 2px solid #0070C0; padding-bottom: 0.2rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        th, td { border: 1px solid #ccc; padding: 0.4rem; text-align: left; vertical-align: top; }
        th { background-color: #f5f5f5; width: 25%; }
        .print-btn { padding: 0.5rem 1rem; background-color: #0070C0; color: #fff; text-decoration: none; border-radius: 3px; margin-bottom: 1rem; display: inline-block; }
        @media print {
            .print-btn { display: none; }
            body { margin: 0; }
            h1 { font-size: 1.4em; }
            h2 { font-size: 1.2em; border-color: #000; }
        }
    </style>
</head>
<body>
    <!-- Top navigation bar -->
    <nav class="navbar">
        <a href="view_wo.php">Oversigt over arbejdstilladelser</a>
        <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
            <a href="create_wo.php">Opret ny arbejdstilladelse</a>
        <?php endif; ?>
        <a href="map_wo.php">Kort</a>
        <!-- Include Dashboard link for consistency with other pages -->
        <a href="dashboard.php">Dashboard</a>
        <span class="nav-user">Logget ind som <?php echo htmlspecialchars($_SESSION['user'] ?? ''); ?> (<?php echo htmlspecialchars($role); ?>)</span>
        <a class="logout-link" href="logout.php">Log ud</a>
    </nav>
    <div class="container">
    <a href="#" class="print-btn" onclick="window.print();return false;">Print</a>
    <h1>Arbejdstilladelse</h1>
    <h2>Basisinformation</h2>
    <table>
        <tr><th>Arbejdstilladelse Nr.</th><td><?php echo htmlspecialchars($entry['work_order_no'] ?? ''); ?></td></tr>
        <tr><th>Beskrivelse</th><td><?php echo nl2br(htmlspecialchars($entry['description'] ?? '')); ?></td></tr>
        <tr><th>P-nr beskrivelse</th><td><?php echo nl2br(htmlspecialchars($entry['p_description'] ?? '')); ?></td></tr>
        <tr><th>Jobansvarlig</th><td><?php echo htmlspecialchars($entry['jobansvarlig'] ?? ''); ?></td></tr>
        <tr><th>Telefon</th><td><?php echo htmlspecialchars($entry['telefon'] ?? ''); ?></td></tr>
        <tr><th>Arbejdstilladelse oprettet af</th><td><?php echo htmlspecialchars($entry['oprettet_af'] ?? ''); ?></td></tr>
        <tr><th>Arbejdstilladelse oprettet dato</th><td><?php echo htmlspecialchars($entry['oprettet_dato'] ?? ''); ?></td></tr>
        <tr><th>Entreprenør firma</th><td><?php echo htmlspecialchars($entry['entreprenor_firma'] ?? ''); ?></td></tr>
        <tr><th>Entreprenør kontakt</th><td><?php echo htmlspecialchars($entry['entreprenor_kontakt'] ?? ''); ?></td></tr>
        <tr><th>Komponent nr.</th><td><?php echo nl2br(htmlspecialchars($entry['components'] ?? '')); ?></td></tr>
        <tr><th>Status</th><td><?php echo $statusLabel; ?></td></tr>
        <tr><th>Lokation (lat,lng)</th><td><?php
            $lat = $entry['latitude'] ?? '';
            $lng = $entry['longitude'] ?? '';
            echo ($lat && $lng) ? htmlspecialchars($lat . ', ' . $lng) : '—';
        ?></td></tr>
        <tr><th>Bemærkninger</th><td><?php echo nl2br(htmlspecialchars($entry['notes'] ?? '')); ?></td></tr>
        <?php if (!empty($entry['pdf_file'])): ?>
        <tr><th>Original PDF</th><td><a href="uploads/<?php echo urlencode($entry['pdf_file']); ?>" target="_blank">Download</a></td></tr>
        <?php endif; ?>
    </table>
    <?php if (!empty($entry['approval_history']) && is_array($entry['approval_history'])): ?>
        <h2>Godkendelseshistorik</h2>
        <table>
            <tr><th>Tidspunkt</th><th>Bruger</th><th>Rolle</th></tr>
            <?php foreach ($entry['approval_history'] as $hist): ?>
                <tr>
                    <td><?php echo htmlspecialchars($hist['timestamp'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($hist['user'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($hist['role'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <h2>Godkendelseshistorik</h2>
        <p>Ingen godkendelser endnu.</p>
    <?php endif; ?>

    <!-- Section for displaying SJA entries linked to this WO -->
    <h2>Tilknyttede SJA'er</h2>
    <?php
        // Load all SJA entries
        $sja_file = __DIR__ . '/sja_data.json';
        $sja_entries = [];
        if (file_exists($sja_file)) {
            $sja_entries = json_decode(file_get_contents($sja_file), true);
            if (!is_array($sja_entries)) {
                $sja_entries = [];
            }
        }
        // Filter SJA entries that reference this work order
        $attached_sja = [];
        foreach ($sja_entries as $sja) {
            if (!empty($sja['wo_id']) && (string)$sja['wo_id'] === (string)$entry['id']) {
                $attached_sja[] = $sja;
            }
        }
    ?>
    <?php if (!empty($attached_sja)): ?>
        <ul>
        <?php foreach ($attached_sja as $sja): ?>
            <li><a href="print_sja.php?id=<?php echo urlencode($sja['id']); ?>"><?php echo htmlspecialchars($sja['basic']['opgave'] ?? ('SJA ' . $sja['id'])); ?></a></li>
        <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Ingen tilknyttede SJA'er fundet for arbejdstilladelse <?php echo htmlspecialchars($entry['id']); ?>.</p>
    <?php endif; ?>
    <p><a href="create_sja.php?wo_id=<?php echo urlencode($entry['id']); ?>">Opret ny SJA til denne arbejdstilladelse</a></p>
    <p><a href="view_wo.php">Tilbage til liste</a></p>
    </div><!-- /.container -->
</body>
</html>