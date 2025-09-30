<?php
// This page displays a specific historical version of an SJA entry.
// It expects an `id` and `version` query parameter.
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
$requested_version = $_GET['version'] ?? '';
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

// Find the requested version in history
$version_data = null;
$history = $entry['history'] ?? [];

foreach ($history as $hist) {
    if (isset($hist['version']) && $hist['version'] == $requested_version && isset($hist['data'])) {
        $version_data = $hist['data'];
        break;
    }
}

if (!$version_data) {
    echo '<p>Version ikke fundet.</p>';
    exit();
}

// Use the historical data
$entry = $version_data;

// Retrieve arrays for dynamic content
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
    <title>Vis SJA Version <?php echo htmlspecialchars($requested_version); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 1rem; color: #222; }
        .version-notice { 
            background-color: #fff3cd; 
            border: 1px solid #ffc107; 
            padding: 1rem; 
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        h1 { margin-top: 0; font-size: 1.6em; text-align: center; }
        h2 { margin-top: 1.2rem; font-size: 1.3em; border-bottom: 2px solid #0070C0; padding-bottom: 0.2rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        th, td { border: 1px solid #ccc; padding: 0.4rem; text-align: left; vertical-align: top; }
        th { background-color: #f5f5f5; }
        .print-btn { padding: 0.5rem 1rem; background-color: #0070C0; color: #fff; text-decoration: none; border-radius: 3px; margin-bottom: 1rem; display: inline-block; }
        @media print {
            .print-btn, .version-notice { display: none; }
            body { margin: 0; }
            h1 { font-size: 1.4em; }
            h2 { font-size: 1.2em; border-color: #000; }
        }
    </style>
</head>
<body>
    <div class="version-notice">
        <strong>⚠ Historisk version:</strong> Du ser version <?php echo htmlspecialchars($requested_version); ?> af denne SJA. 
        <a href="print_sja.php?id=<?php echo urlencode($id); ?>">Vis nuværende version</a> | 
        <a href="sja_history.php?id=<?php echo urlencode($id); ?>">Se alle versioner</a>
    </div>

    <a href="#" class="print-btn" onclick="window.print();return false;">Print</a>
    <h1>Sikker Job Analyse (Version <?php echo htmlspecialchars($requested_version); ?>)</h1>
    
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

    <h2>Status</h2>
    <p><?php echo ($entry['status'] ?? 'active') === 'completed' ? 'Afsluttet' : 'Aktiv'; ?></p>

    <h2>Risici</h2>
    <table>
        <tr><th>Punkt</th><th>Status</th><th>Bemærkning</th></tr>
        <?php foreach ($risk_items as $key => $label):
            $st = $entry['risici'][$key]['status'] ?? '';
            $rem = $entry['risici'][$key]['remark'] ?? '';
            if ($st || $rem):
        ?>
        <tr>
            <td><?php echo htmlspecialchars($label); ?></td>
            <td><?php echo htmlspecialchars($st); ?></td>
            <td><?php echo htmlspecialchars($rem); ?></td>
        </tr>
        <?php endif; endforeach; ?>
    </table>

    <h2>Tilladelser</h2>
    <table>
        <tr><th>Punkt</th><th>Status</th><th>Bemærkning</th></tr>
        <?php foreach ($permission_items as $key => $label):
            $st = $entry['tilladelser'][$key]['status'] ?? '';
            $rem = $entry['tilladelser'][$key]['remark'] ?? '';
            if ($st || $rem):
        ?>
        <tr>
            <td><?php echo htmlspecialchars($label); ?></td>
            <td><?php echo htmlspecialchars($st); ?></td>
            <td><?php echo htmlspecialchars($rem); ?></td>
        </tr>
        <?php endif; endforeach; ?>
    </table>

    <h2>Personlige værnemidler</h2>
    <table>
        <tr><th>Punkt</th><th>Status</th><th>Bemærkning</th></tr>
        <?php foreach ($ppe_items as $key => $label):
            $st = $entry['vaernemidler'][$key]['status'] ?? '';
            $rem = $entry['vaernemidler'][$key]['remark'] ?? '';
            if ($st || $rem):
        ?>
        <tr>
            <td><?php echo htmlspecialchars($label); ?></td>
            <td><?php echo htmlspecialchars($st); ?></td>
            <td><?php echo htmlspecialchars($rem); ?></td>
        </tr>
        <?php endif; endforeach; ?>
    </table>

    <h2>Sikkerhedsudstyr</h2>
    <table>
        <tr><th>Punkt</th><th>Status</th><th>Bemærkning</th></tr>
        <?php foreach ($equipment_items as $key => $label):
            $st = $entry['udstyr'][$key]['status'] ?? '';
            $rem = $entry['udstyr'][$key]['remark'] ?? '';
            if ($st || $rem):
        ?>
        <tr>
            <td><?php echo htmlspecialchars($label); ?></td>
            <td><?php echo htmlspecialchars($st); ?></td>
            <td><?php echo htmlspecialchars($rem); ?></td>
        </tr>
        <?php endif; endforeach; ?>
    </table>

    <h2>Har du tænkt på…</h2>
    <table>
        <tr><th>Punkt</th><th>Status</th><th>Bemærkning</th></tr>
        <?php foreach ($consider_items as $key => $label):
            $st = $entry['taenkt'][$key]['status'] ?? '';
            $rem = $entry['taenkt'][$key]['remark'] ?? '';
            if ($st || $rem):
        ?>
        <tr>
            <td><?php echo htmlspecialchars($label); ?></td>
            <td><?php echo htmlspecialchars($st); ?></td>
            <td><?php echo htmlspecialchars($rem); ?></td>
        </tr>
        <?php endif; endforeach; ?>
    </table>

    <?php if (!empty($entry['cancer']) && array_filter($entry['cancer'], function($c) { return !empty($c['name']); })): ?>
    <h2>Kræftfremkaldende stoffer</h2>
    <table>
        <tr><th>Navn</th><th>CAS nr.</th><th>Grænseværdi</th><th>Sikkerhedsdatablad</th><th>Foranstaltninger</th></tr>
        <?php foreach ($entry['cancer'] as $c):
            if (!empty($c['name'])):
        ?>
        <tr>
            <td><?php echo htmlspecialchars($c['name']); ?></td>
            <td><?php echo htmlspecialchars($c['cas'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($c['limit'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($c['datasheet'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($c['measures'] ?? ''); ?></td>
        </tr>
        <?php endif; endforeach; ?>
    </table>
    <?php endif; ?>

    <?php if (!empty($entry['bem'])): ?>
    <h2>Øvrige bemærkninger</h2>
    <p><?php echo nl2br(htmlspecialchars($entry['bem'])); ?></p>
    <?php endif; ?>

    <?php if (!empty($entry['deltagere']) && array_filter($entry['deltagere'], function($d) { return !empty($d['navn']); })): ?>
    <h2>Deltagere</h2>
    <table>
        <tr><th>Navn</th><th>Telefon</th></tr>
        <?php foreach ($entry['deltagere'] as $d):
            if (!empty($d['navn'])):
        ?>
        <tr>
            <td><?php echo htmlspecialchars($d['navn']); ?></td>
            <td><?php echo htmlspecialchars($d['telefon'] ?? ''); ?></td>
        </tr>
        <?php endif; endforeach; ?>
    </table>
    <?php endif; ?>

    <p><a href="sja_history.php?id=<?php echo urlencode($id); ?>">← Tilbage til versionshistorik</a></p>
</body>
</html>
