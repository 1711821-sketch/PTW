<?php
// This page displays an individual SJA entry in a printer friendly format.
// It expects an `id` query parameter referencing an entry in the data file.
// Only authenticated users may view this page.

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
    $dbEntry = $db->fetch("SELECT * FROM sja_entries WHERE id = ?", [$id]);
    
    if ($dbEntry) {
        // Convert database format to expected format
        $entry = [
            'id' => $dbEntry['id'],
            'basic' => json_decode($dbEntry['basic_info'] ?? '{}', true) ?: [],
            'risici' => json_decode($dbEntry['risks'] ?? '{}', true) ?: [],
            'tilladelser' => json_decode($dbEntry['permissions'] ?? '{}', true) ?: [],
            'vaernemidler' => json_decode($dbEntry['ppe'] ?? '{}', true) ?: [],
            'udstyr' => json_decode($dbEntry['equipment'] ?? '{}', true) ?: [],
            'taenkt' => json_decode($dbEntry['considerations'] ?? '{}', true) ?: [],
            'cancer' => json_decode($dbEntry['cancer_substances'] ?? '{}', true) ?: [],
            'bem' => $dbEntry['remarks'] ?? '',
            'deltagere' => json_decode($dbEntry['participants'] ?? '[]', true) ?: [],
            'status' => $dbEntry['status'] ?? 'active',
            'latitude' => $dbEntry['latitude'] ?? '',
            'longitude' => $dbEntry['longitude'] ?? '',
            'work_order_id' => $dbEntry['work_order_id'] ?? '',
            'created_at' => $dbEntry['created_at'] ?? '',
            'updated_at' => $dbEntry['updated_at'] ?? ''
        ];
    }
} catch (Exception $e) {
    error_log("Error loading SJA for print: " . $e->getMessage());
    echo '<p>Fejl ved indlæsning af SJA.</p>';
    echo '<p><a href="view_sja.php">Tilbage til liste</a></p>';
    exit();
}

if (!$entry) {
    echo '<p>SJA ikke fundet.</p>';
    echo '<p><a href="view_sja.php">Tilbage til liste</a></p>';
    exit();
}

// Retrieve arrays for dynamic content – these arrays mirror those used in create_sja.php
$risk_items = [
    'atex'      => 'ATEX/Zoneklassifikation? (Husk instruktion)',
    'tryk'      => 'Medier under tryk',
    'stoffer'   => 'Stoffer og materialer',
    'cse'       => 'Snævre rum (CSE)',
    'flugteveje'=> 'Adgangs-/flugtveje',
    'etsning'   => 'Ætsning',
    'faldrisiko'=> 'Faldrisiko, arbejde i højder, arbejde i flere niveauer',
    'alene'     => 'Alene-arbejde',
    'stoj'      => 'Støj',
    'varme'     => 'Varme/kulde',
    'kvaelning' => 'Kvælning',
    'udledning' => 'Udledning jord, vand, luft',
    'stov'      => 'Støv',
    'tunge'     => 'Tunge løft',
    'stoe'      => 'Elektrisk stød',
    'andre'     => 'Andre risici',
    'intro'     => 'Gennemgået sikkerhedsintro inden for 1. år'
];
$permission_items = [
    'sikring'   => 'Sikring af anlæg',
    'varmt'     => 'Varmt arbejde',
    'koblinger' => 'Koblinger jf. SB. 5',
    'grave'     => 'Gravetilladelse',
    'aendring'  => 'Anlægsændringer',
    'andre_till'=> 'Andre tilladelser'
];
$ppe_items = [
    'handsker' => 'Beskyttelseshandsker jf. APB',
    'hore'     => 'Høreværn',
    'dragter'  => 'Støvdragter/kemidragter',
    'aanded'   => 'Åndedrætsværn jf. APB',
    'filter'   => 'Filtermasker jf. APB',
    'fald'     => 'Faldsikring',
    'laus'     => 'LAUS-udstyr',
    'andet_ud' => 'Andet udstyr'
];
$equipment_items = [
    'afspaerring' => 'Afspærringsmaterialer',
    'gas'         => 'Gas-tester',
    'net'         => 'Sikkerhedsnet',
    'forstehj'    => 'Førstehjælp/øjenskyller',
    'bjaerg'      => 'Bjærgningsudstyr',
    'svejse'      => 'Svejseudsugning'
];
$consider_items = [
    'frie'    => 'Adgangs-/flugtveje er frie',
    'afspaer' => 'Afspærring/afmærkning etableret',
    'exudstyr'=> 'EX-udstyr / værktøj korrekt',
    'brand'   => 'Brandvagt ved varmt arbejde',
    'vaerktoj'=> 'Værktøj & maskiner OK (inspektion)',
    'loto'    => 'LOTO / isolering gennemført',
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
    <title>Vis/Print SJA</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 1rem; color: #222; }
        h1 { margin-top: 0; font-size: 1.6em; text-align: center; }
        h2 { margin-top: 1.2rem; font-size: 1.3em; border-bottom: 2px solid #0070C0; padding-bottom: 0.2rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        th, td { border: 1px solid #ccc; padding: 0.4rem; text-align: left; vertical-align: top; }
        th { background-color: #f5f5f5; }
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
    <a href="#" class="print-btn" onclick="window.print();return false;">Print</a>
    <h1>Sikker Job Analyse</h1>
    <h2>Basisinformation</h2>
    <table>
        <tr><th>Opgave</th><td><?php echo htmlspecialchars($entry['basic']['opgave'] ?? ''); ?></td></tr>
        <tr><th>WO/PO</th><td><?php echo htmlspecialchars($entry['basic']['wo_po'] ?? ''); ?></td></tr>
        <tr><th>Dato udførelse</th><td><?php echo htmlspecialchars($entry['basic']['dato_udfoer'] ?? ''); ?></td></tr>
        <tr><th>Planlagt af</th><td><?php echo htmlspecialchars($entry['basic']['planlagt_af'] ?? ''); ?></td></tr>
        <tr><th>Udføres af</th><td><?php echo htmlspecialchars($entry['basic']['udfoeres_af'] ?? ''); ?></td></tr>
        <tr><th>Dato planlagt</th><td><?php echo htmlspecialchars($entry['basic']['dato_planlagt'] ?? ''); ?></td></tr>
        <tr><th>Koordinator</th><td><?php echo htmlspecialchars($entry['basic']['koordinator'] ?? ''); ?></td></tr>
        <tr><th>Status</th><td><?php
            $statusVal = $entry['status'] ?? 'active';
            echo ($statusVal === 'completed') ? 'Afsluttet' : 'Aktiv';
        ?></td></tr>
        <tr><th>Lokation (lat,lng)</th><td>
            <?php
                $lat = $entry['latitude'] ?? '';
                $lng = $entry['longitude'] ?? '';
                echo ($lat && $lng) ? htmlspecialchars($lat . ', ' . $lng) : '—';
            ?>
        </td></tr>
    </table>
    <h2>Risici</h2>
    <table>
        <tr><th>Punkt</th><th>Status</th><th>Bemærkning</th></tr>
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
        <tr><th>Punkt</th><th>Status</th><th>Bemærkning</th></tr>
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
    <h2>Personlige værnemidler</h2>
    <table>
        <tr><th>Punkt</th><th>Status</th><th>Bemærkning</th></tr>
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
    <h2>Sikkerhedsudstyr</h2>
    <table>
        <tr><th>Punkt</th><th>Status</th><th>Bemærkning</th></tr>
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
