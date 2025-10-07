<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once 'database.php';

$id = $_GET['id'] ?? '';
$v1 = isset($_GET['v1']) ? intval($_GET['v1']) : 0;
$v2 = isset($_GET['v2']) ? intval($_GET['v2']) : 0;

function getVersion($db, $id, $version) {
    $dbEntry = $db->fetch("SELECT * FROM sja_entries WHERE id = ?", [$id]);
    if (!$dbEntry) return null;
    
    $current_version = $dbEntry['version'] ?? 1;
    
    if ($version == $current_version || $version == 0) {
        return [
            'version' => $current_version,
            'basic' => json_decode($dbEntry['basic_info'] ?? '{}', true),
            'risici' => json_decode($dbEntry['risks'] ?? '{}', true),
            'tilladelser' => json_decode($dbEntry['permissions'] ?? '{}', true),
            'vaernemidler' => json_decode($dbEntry['ppe'] ?? '{}', true),
            'udstyr' => json_decode($dbEntry['equipment'] ?? '{}', true),
            'taenkt' => json_decode($dbEntry['considerations'] ?? '{}', true),
            'cancer' => json_decode($dbEntry['cancer_substances'] ?? '{}', true),
            'bem' => $dbEntry['remarks'] ?? '',
            'deltagere' => json_decode($dbEntry['participants'] ?? '[]', true),
            'modified_by' => $dbEntry['modified_by'] ?? $dbEntry['created_by'] ?? '',
            'updated_at' => $dbEntry['updated_at'] ?? $dbEntry['created_at']
        ];
    }
    
    $history = json_decode($dbEntry['history'] ?? '[]', true);
    foreach ($history as $hist) {
        if (isset($hist['version']) && $hist['version'] == $version && isset($hist['data'])) {
            $data = $hist['data'];
            return [
                'version' => $version,
                'basic' => json_decode($data['basic_info'] ?? '{}', true),
                'risici' => json_decode($data['risks'] ?? '{}', true),
                'tilladelser' => json_decode($data['permissions'] ?? '{}', true),
                'vaernemidler' => json_decode($data['ppe'] ?? '{}', true),
                'udstyr' => json_decode($data['equipment'] ?? '{}', true),
                'taenkt' => json_decode($data['considerations'] ?? '{}', true),
                'cancer' => json_decode($data['cancer_substances'] ?? '{}', true),
                'bem' => $data['remarks'] ?? '',
                'deltagere' => json_decode($data['participants'] ?? '[]', true),
                'modified_by' => $hist['modified_by'] ?? '',
                'updated_at' => $hist['timestamp'] ?? ''
            ];
        }
    }
    return null;
}

try {
    $db = Database::getInstance();
    $entry1 = getVersion($db, $id, $v1);
    $entry2 = getVersion($db, $id, $v2);
} catch (Exception $e) {
    error_log("Error comparing SJA versions: " . $e->getMessage());
    $entry1 = null;
    $entry2 = null;
}

if (!$entry1 || !$entry2) {
    echo '<p>Kunne ikke finde versioner til sammenligning.</p>';
    echo '<p><a href="sja_history.php?id=' . htmlspecialchars($id) . '">Tilbage til historik</a></p>';
    exit();
}

function highlightDiff($val1, $val2) {
    if ($val1 != $val2) {
        return 'background-color: #fef3c7;';
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Sammenlign SJA Versioner</title>
    <?php include 'pwa-head.php'; ?>
    <link rel="stylesheet" href="style.css">
    <script src="navigation.js"></script>
    <style>
        .compare-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }
        .version-col {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
        }
        .version-header {
            background: #4f46e5;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .diff-highlight {
            background-color: #fef3c7 !important;
            font-weight: 600;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 0.4rem;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f5f5f5;
        }
        .section-title {
            background: #f8fafc;
            padding: 0.5rem;
            margin-top: 1rem;
            font-weight: 600;
            border-left: 4px solid #4f46e5;
        }
        @media (max-width: 768px) {
            .compare-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <button class="navbar-toggle" aria-label="Toggle navigation">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
        <div class="navbar-links">
            <a href="view_sja.php">SJA Oversigt</a>
            <a href="sja_history.php?id=<?php echo urlencode($id); ?>">← Tilbage til historik</a>
            <span class="nav-user">Logget ind som <?php echo htmlspecialchars($_SESSION['user']); ?></span>
            <a class="logout-link" href="logout.php">Log ud</a>
        </div>
    </nav>

    <div class="container">
        <h1>🔍 Sammenlign SJA Versioner</h1>

        <div class="compare-container">
            <div class="version-col">
                <div class="version-header">
                    Version <?php echo $entry1['version']; ?><br>
                    <small><?php echo htmlspecialchars($entry1['updated_at']); ?></small><br>
                    <small>Af: <?php echo htmlspecialchars($entry1['modified_by']); ?></small>
                </div>

                <div class="section-title">Basisinformation</div>
                <table>
                    <tr><th>Opgave</th><td style="<?php echo highlightDiff($entry1['basic']['opgave'] ?? '', $entry2['basic']['opgave'] ?? ''); ?>"><?php echo htmlspecialchars($entry1['basic']['opgave'] ?? ''); ?></td></tr>
                    <tr><th>WO/PO</th><td style="<?php echo highlightDiff($entry1['basic']['wo_po'] ?? '', $entry2['basic']['wo_po'] ?? ''); ?>"><?php echo htmlspecialchars($entry1['basic']['wo_po'] ?? ''); ?></td></tr>
                    <tr><th>Dato udførelse</th><td style="<?php echo highlightDiff($entry1['basic']['dato_udfoer'] ?? '', $entry2['basic']['dato_udfoer'] ?? ''); ?>"><?php echo htmlspecialchars($entry1['basic']['dato_udfoer'] ?? ''); ?></td></tr>
                    <tr><th>Planlagt af</th><td style="<?php echo highlightDiff($entry1['basic']['planlagt_af'] ?? '', $entry2['basic']['planlagt_af'] ?? ''); ?>"><?php echo htmlspecialchars($entry1['basic']['planlagt_af'] ?? ''); ?></td></tr>
                    <tr><th>Udføres af</th><td style="<?php echo highlightDiff($entry1['basic']['udfoeres_af'] ?? '', $entry2['basic']['udfoeres_af'] ?? ''); ?>"><?php echo htmlspecialchars($entry1['basic']['udfoeres_af'] ?? ''); ?></td></tr>
                </table>

                <div class="section-title">Bemærkninger</div>
                <div style="<?php echo highlightDiff($entry1['bem'] ?? '', $entry2['bem'] ?? ''); ?> padding: 0.5rem;">
                    <?php echo nl2br(htmlspecialchars($entry1['bem'] ?? '')); ?>
                </div>
            </div>

            <div class="version-col">
                <div class="version-header">
                    Version <?php echo $entry2['version']; ?><br>
                    <small><?php echo htmlspecialchars($entry2['updated_at']); ?></small><br>
                    <small>Af: <?php echo htmlspecialchars($entry2['modified_by']); ?></small>
                </div>

                <div class="section-title">Basisinformation</div>
                <table>
                    <tr><th>Opgave</th><td style="<?php echo highlightDiff($entry1['basic']['opgave'] ?? '', $entry2['basic']['opgave'] ?? ''); ?>"><?php echo htmlspecialchars($entry2['basic']['opgave'] ?? ''); ?></td></tr>
                    <tr><th>WO/PO</th><td style="<?php echo highlightDiff($entry1['basic']['wo_po'] ?? '', $entry2['basic']['wo_po'] ?? ''); ?>"><?php echo htmlspecialchars($entry2['basic']['wo_po'] ?? ''); ?></td></tr>
                    <tr><th>Dato udførelse</th><td style="<?php echo highlightDiff($entry1['basic']['dato_udfoer'] ?? '', $entry2['basic']['dato_udfoer'] ?? ''); ?>"><?php echo htmlspecialchars($entry2['basic']['dato_udfoer'] ?? ''); ?></td></tr>
                    <tr><th>Planlagt af</th><td style="<?php echo highlightDiff($entry1['basic']['planlagt_af'] ?? '', $entry2['basic']['planlagt_af'] ?? ''); ?>"><?php echo htmlspecialchars($entry2['basic']['planlagt_af'] ?? ''); ?></td></tr>
                    <tr><th>Udføres af</th><td style="<?php echo highlightDiff($entry1['basic']['udfoeres_af'] ?? '', $entry2['basic']['udfoeres_af'] ?? ''); ?>"><?php echo htmlspecialchars($entry2['basic']['udfoeres_af'] ?? ''); ?></td></tr>
                </table>

                <div class="section-title">Bemærkninger</div>
                <div style="<?php echo highlightDiff($entry1['bem'] ?? '', $entry2['bem'] ?? ''); ?> padding: 0.5rem;">
                    <?php echo nl2br(htmlspecialchars($entry2['bem'] ?? '')); ?>
                </div>
            </div>
        </div>

        <p style="margin-top: 2rem; text-align: center;">
            <a href="sja_history.php?id=<?php echo urlencode($id); ?>" class="btn">← Tilbage til versionshistorik</a>
        </p>
    </div>
</body>
</html>
