<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once 'database.php';

$id = $_GET['id'] ?? '';
$version = isset($_GET['version']) ? intval($_GET['version']) : 0;
$entry = null;

try {
    $db = Database::getInstance();
    $dbEntry = $db->fetch("SELECT * FROM sja_entries WHERE id = ?", [$id]);
    
    if ($dbEntry) {
        $current_version = $dbEntry['version'] ?? 1;
        
        if ($version == $current_version || $version == 0) {
            // Show current version
            $entry = [
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
        } else {
            // Find version in history
            $history = json_decode($dbEntry['history'] ?? '[]', true);
            foreach ($history as $hist) {
                if (isset($hist['version']) && $hist['version'] == $version && isset($hist['data'])) {
                    $data = $hist['data'];
                    $entry = [
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
                    break;
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Error loading SJA version: " . $e->getMessage());
}

if (!$entry) {
    echo '<p>Version ikke fundet.</p>';
    echo '<p><a href="sja_history.php?id=' . htmlspecialchars($id) . '">Tilbage til historik</a></p>';
    exit();
}

// Arrays for display
$risk_items = [
    'atex' => 'ATEX/Zoneklassifikation? (Husk instruktion)',
    'tryk' => 'Medier under tryk',
    'stoffer' => 'Stoffer og materialer',
    'cse' => 'Snævre rum (CSE)',
    'flugteveje' => 'Adgangs-/flugtveje',
    'etsning' => 'Ætsning',
    'faldrisiko' => 'Faldrisiko, arbejde i højder, arbejde i flere niveauer',
    'alene' => 'Alene-arbejde',
    'stoj' => 'Støj',
    'varme' => 'Varme/kulde',
    'kvaelning' => 'Kvælning',
    'udledning' => 'Udledning jord, vand, luft',
    'stov' => 'Støv',
    'tunge' => 'Tunge løft',
    'stoe' => 'Elektrisk stød',
    'andre' => 'Andre risici',
    'intro' => 'Gennemgået sikkerhedsintro inden for 1. år'
];
$permission_items = [
    'sikring' => 'Sikring af anlæg',
    'varmt' => 'Varmt arbejde',
    'koblinger' => 'Koblinger jf. SB. 5',
    'grave' => 'Gravetilladelse',
    'aendring' => 'Anlægsændringer',
    'andre_till' => 'Andre tilladelser'
];
$ppe_items = [
    'handsker' => 'Beskyttelseshandsker jf. APB',
    'hore' => 'Høreværn',
    'dragter' => 'Støvdragter/kemidragter',
    'aanded' => 'Åndedrætsværn jf. APB',
    'filter' => 'Filtermasker jf. APB',
    'fald' => 'Faldsikring',
    'laus' => 'LAUS-udstyr',
    'andet_ud' => 'Andet udstyr'
];
$equipment_items = [
    'afspaerring' => 'Afspærringsmaterialer',
    'gas' => 'Gas-tester',
    'net' => 'Sikkerhedsnet',
    'forstehj' => 'Førstehjælp/øjenskyller',
    'bjaerg' => 'Bjærgningsudstyr',
    'svejse' => 'Svejseudsugning'
];
$consider_items = [
    'frie' => 'Adgangs-/flugtveje er frie',
    'afspaer' => 'Afspærring/afmærkning etableret',
    'exudstyr' => 'EX-udstyr / værktøj korrekt',
    'brand' => 'Brandvagt ved varmt arbejde',
    'vaerktoj' => 'Værktøj & maskiner OK (inspektion)',
    'loto' => 'LOTO / isolering gennemført',
    'nodstop' => 'Nødstop og nødudstyr kendt',
    'gasmaal' => 'Gasmåling før & under arbejde',
    'rydning' => 'Rydning/orden ved afslutning'
];
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SJA - Version <?php echo $entry['version']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 1rem; color: #222; }
        .version-banner { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; margin-bottom: 1rem; }
        h1 { margin-top: 0; font-size: 1.6em; text-align: center; }
        h2 { margin-top: 1.2rem; font-size: 1.3em; border-bottom: 2px solid #0070C0; padding-bottom: 0.2rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        th, td { border: 1px solid #ccc; padding: 0.4rem; text-align: left; vertical-align: top; }
        th { background-color: #f5f5f5; }
        .print-btn { padding: 0.5rem 1rem; background-color: #0070C0; color: #fff; text-decoration: none; border-radius: 3px; margin-bottom: 1rem; display: inline-block; }
        @media print {
            .print-btn, .version-banner { display: none; }
        }
    </style>
</head>
<body>
    <div class="version-banner">
        <strong>⚠️ Historisk version</strong><br>
        Du ser version <?php echo $entry['version']; ?> fra <?php echo htmlspecialchars($entry['updated_at']); ?><br>
        Redigeret af: <?php echo htmlspecialchars($entry['modified_by']); ?><br>
        <a href="sja_history.php?id=<?php echo urlencode($id); ?>">← Tilbage til versionshistorik</a>
    </div>

    <a href="#" class="print-btn" onclick="window.print();return false;">Print</a>
    
    <h1>Sikker Job Analyse (Version <?php echo $entry['version']; ?>)</h1>
    <h2>Basisinformation</h2>
    <table>
        <tr><th>Opgave</th><td><?php echo htmlspecialchars($entry['basic']['opgave'] ?? ''); ?></td></tr>
        <tr><th>WO/PO</th><td><?php echo htmlspecialchars($entry['basic']['wo_po'] ?? ''); ?></td></tr>
        <tr><th>Dato udførelse</th><td><?php echo htmlspecialchars($entry['basic']['dato_udfoer'] ?? ''); ?></td></tr>
        <tr><th>Planlagt af</th><td><?php echo htmlspecialchars($entry['basic']['planlagt_af'] ?? ''); ?></td></tr>
        <tr><th>Udføres af</th><td><?php echo htmlspecialchars($entry['basic']['udfoeres_af'] ?? ''); ?></td></tr>
        <tr><th>Dato planlagt</th><td><?php echo htmlspecialchars($entry['basic']['dato_planlagt'] ?? ''); ?></td></tr>
        <tr><th>Koordinator</th><td><?php echo htmlspecialchars($entry['basic']['koordinator'] ?? ''); ?></td></tr>
    </table>

    <h2>Risici</h2>
    <table>
        <tr><th>Risiko</th><th>Status</th><th>Bemærkning</th></tr>
        <?php foreach ($risk_items as $key => $label):
            $status = $entry['risici'][$key]['status'] ?? '';
            $remark = $entry['risici'][$key]['remark'] ?? '';
        ?>
        <tr>
            <td><?php echo $label; ?></td>
            <td><?php echo htmlspecialchars($status); ?></td>
            <td><?php echo htmlspecialchars($remark); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Tilladelser</h2>
    <table>
        <tr><th>Tilladelse</th><th>Status</th><th>Bemærkning</th></tr>
        <?php foreach ($permission_items as $key => $label):
            $status = $entry['tilladelser'][$key]['status'] ?? '';
            $remark = $entry['tilladelser'][$key]['remark'] ?? '';
        ?>
        <tr>
            <td><?php echo $label; ?></td>
            <td><?php echo htmlspecialchars($status); ?></td>
            <td><?php echo htmlspecialchars($remark); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Værnemidler</h2>
    <table>
        <tr><th>Værnemiddel</th><th>Status</th><th>Bemærkning</th></tr>
        <?php foreach ($ppe_items as $key => $label):
            $status = $entry['vaernemidler'][$key]['status'] ?? '';
            $remark = $entry['vaernemidler'][$key]['remark'] ?? '';
        ?>
        <tr>
            <td><?php echo $label; ?></td>
            <td><?php echo htmlspecialchars($status); ?></td>
            <td><?php echo htmlspecialchars($remark); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Udstyr</h2>
    <table>
        <tr><th>Udstyr</th><th>Status</th><th>Bemærkning</th></tr>
        <?php foreach ($equipment_items as $key => $label):
            $status = $entry['udstyr'][$key]['status'] ?? '';
            $remark = $entry['udstyr'][$key]['remark'] ?? '';
        ?>
        <tr>
            <td><?php echo $label; ?></td>
            <td><?php echo htmlspecialchars($status); ?></td>
            <td><?php echo htmlspecialchars($remark); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Har du tænkt på…</h2>
    <table>
        <tr><th>Punkt</th><th>Status</th><th>Bemærkning</th></tr>
        <?php foreach ($consider_items as $key => $label):
            $status = $entry['taenkt'][$key]['status'] ?? '';
            $remark = $entry['taenkt'][$key]['remark'] ?? '';
        ?>
        <tr>
            <td><?php echo $label; ?></td>
            <td><?php echo htmlspecialchars($status); ?></td>
            <td><?php echo htmlspecialchars($remark); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Kræftfremkaldende stoffer</h2>
    <?php if (!empty($entry['cancer'])): ?>
    <table>
        <tr><th>#</th><th>Navn</th><th>CAS nr.</th><th>Grænseværdi</th><th>Sikkerhedsdatablad</th><th>Foranstaltninger</th></tr>
        <?php foreach ($entry['cancer'] as $idx => $c): ?>
        <tr>
            <td><?php echo $idx + 1; ?></td>
            <td><?php echo htmlspecialchars($c['name'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($c['cas'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($c['limit'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($c['datasheet'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($c['measures'] ?? ''); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>Ingen kræftfremkaldende stoffer angivet.</p>
    <?php endif; ?>

    <h2>Øvrige bemærkninger</h2>
    <p><?php echo nl2br(htmlspecialchars($entry['bem'] ?? '')); ?></p>

    <h2>Deltagere</h2>
    <?php if (!empty($entry['deltagere'])): ?>
        <table>
            <tr><th>#</th><th>Navn</th><th>Telefon</th></tr>
            <?php foreach ($entry['deltagere'] as $idx => $d): ?>
                <?php if (empty($d['navn']) && empty($d['telefon'])) continue; ?>
                <tr>
                    <td><?php echo $idx + 1; ?></td>
                    <td><?php echo htmlspecialchars($d['navn'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($d['telefon'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Ingen deltagere angivet.</p>
    <?php endif; ?>
</body>
</html>
