<?php
// Displays a single Work Order (WO) in a print friendly layout.  Requires
// authentication and an `id` query parameter referencing the WO entry.

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Use database instead of JSON files
require_once 'database.php';

$id = $_GET['id'] ?? '';
$entry = null;

try {
    $db = Database::getInstance();
    $entry = $db->fetch("SELECT * FROM work_orders WHERE id = ?", [$id]);
    
    if (!$entry) {
        echo '<p>Arbejdstilladelse ikke fundet.</p>';
        echo '<p><a href="view_wo.php">Tilbage til oversigt</a></p>';
        exit();
    }
    
    // Convert JSON strings back to arrays if needed
    if (is_string($entry['approvals'] ?? '')) {
        $entry['approvals'] = json_decode($entry['approvals'], true) ?? [];
    }
    if (is_string($entry['approval_history'] ?? '')) {
        $entry['approval_history'] = json_decode($entry['approval_history'], true) ?? [];
    }
    
} catch (Exception $e) {
    error_log("Error loading work order for print: " . $e->getMessage());
    echo '<p>Fejl ved indlæsning af arbejdstilladelse.</p>';
    echo '<p><a href="view_wo.php">Tilbage til oversigt</a></p>';
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
        
        /* Action buttons styling */
        .action-buttons { 
            display: flex; 
            gap: 0.5rem; 
            margin-bottom: 1rem; 
            flex-wrap: wrap;
        }
        .action-btn { 
            padding: 0.5rem 1rem; 
            color: #fff; 
            text-decoration: none; 
            border-radius: 4px; 
            display: inline-block;
            transition: background-color 0.2s;
        }
        .btn-primary { background-color: #007bff; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-secondary { background-color: #6c757d; }
        .btn-secondary:hover { background-color: #545b62; }
        
        @media print {
            .action-buttons, .navbar { display: none; }
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
    <!-- Action buttons for better navigation -->
    <div class="action-buttons">
        <a href="view_wo.php" class="action-btn btn-secondary">← Tilbage til oversigt</a>
        <a href="#" class="action-btn btn-primary" onclick="window.print();return false;">🖨️ Print</a>
    </div>
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
                    <td><?php 
                        $userDisplay = htmlspecialchars($hist['user'] ?? '');
                        // If this is an entrepreneur approval and company name is available, show it
                        if (($hist['role'] ?? '') === 'entreprenor' && !empty($hist['company'])) {
                            $userDisplay .= ' (' . htmlspecialchars($hist['company']) . ')';
                        }
                        echo $userDisplay;
                    ?></td>
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
        // Load SJA entries from database that reference this work order
        $attached_sja = [];
        try {
            // Check if sja_entries table exists and get related SJAs
            $attached_sja = $db->fetchAll("
                SELECT * FROM sja_entries 
                WHERE work_order_id = ? 
                ORDER BY created_at DESC
            ", [$entry['id']]);
        } catch (Exception $e) {
            // SJA table might not exist yet, that's okay
            error_log("SJA table not available: " . $e->getMessage());
        }
    ?>
    <?php if (!empty($attached_sja)): ?>
        <table>
            <tr><th>SJA ID</th><th>Opgave</th><th>Oprettet</th><th>Handlinger</th></tr>
            <?php foreach ($attached_sja as $sja): ?>
                <tr>
                    <td><?php echo htmlspecialchars($sja['id']); ?></td>
                    <td><?php echo htmlspecialchars($sja['opgave'] ?? 'SJA'); ?></td>
                    <td><?php echo htmlspecialchars($sja['created_at'] ?? ''); ?></td>
                    <td><a href="print_sja.php?id=<?php echo urlencode($sja['id']); ?>">Se SJA</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Ingen tilknyttede SJA'er fundet for denne arbejdstilladelse.</p>
    <?php endif; ?>
    <div class="action-buttons" style="margin-top: 2rem;">
        <a href="create_sja.php?wo_id=<?php echo urlencode($entry['id']); ?>" class="action-btn btn-primary">Opret ny SJA til denne WO</a>
    </div>
    </div><!-- /.container -->
</body>
</html>