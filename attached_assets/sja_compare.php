<?php
// This page compares two versions of an SJA entry side by side.
// It expects `id`, `v1` (current/newer version), and `v2` (older version) query parameters.
// Only authenticated users may view this page.

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Helper function to check if two values are different
function isDifferent($val1, $val2) {
    return $val1 !== $val2;
}

// Helper function to highlight different cells
function getCellClass($val1, $val2) {
    return isDifferent($val1, $val2) ? 'changed' : '';
}

// Function to display comparison for a section
function displaySectionComparison($section_name, $items, $data_v1, $data_v2, $v1, $v2) {
    echo '<div class="section-header">' . htmlspecialchars($section_name) . '</div>';
    echo '<table class="comparison-table">';
    echo '<tr><th class="field-name">Punkt</th><th>Version ' . htmlspecialchars($v2) . '</th><th>Version ' . htmlspecialchars($v1) . '</th></tr>';
    
    $has_changes = false;
    foreach ($items as $key => $label) {
        $status1 = $data_v1[$key]['status'] ?? '';
        $remark1 = $data_v1[$key]['remark'] ?? '';
        $status2 = $data_v2[$key]['status'] ?? '';
        $remark2 = $data_v2[$key]['remark'] ?? '';
        
        $status_changed = getCellClass($status1, $status2);
        $remark_changed = getCellClass($remark1, $remark2);
        
        if ($status1 || $remark1 || $status2 || $remark2) {
            $has_changes = true;
            echo '<tr>';
            echo '<td class="field-name">' . htmlspecialchars($label) . '</td>';
            echo '<td class="' . $status_changed . '"><strong>Status:</strong> ' . htmlspecialchars($status2) . '<br><strong>Bemærkning:</strong> ' . htmlspecialchars($remark2) . '</td>';
            echo '<td class="' . $remark_changed . '"><strong>Status:</strong> ' . htmlspecialchars($status1) . '<br><strong>Bemærkning:</strong> ' . htmlspecialchars($remark1) . '</td>';
            echo '</tr>';
        }
    }
    
    if (!$has_changes) {
        echo '<tr><td colspan="3" class="no-changes">Ingen data i denne sektion</td></tr>';
    }
    
    echo '</table>';
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
$v1 = $_GET['v1'] ?? '';  // newer/current version
$v2 = $_GET['v2'] ?? '';  // older version

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

// Get version 1 data (current or from history)
$data_v1 = null;
if ($v1 == ($entry['version'] ?? 1)) {
    // This is the current version
    $data_v1 = $entry;
} else {
    // Look in history
    foreach (($entry['history'] ?? []) as $hist) {
        if (isset($hist['version']) && $hist['version'] == $v1 && isset($hist['data'])) {
            $data_v1 = $hist['data'];
            break;
        }
    }
}

// Get version 2 data (from history)
$data_v2 = null;
foreach (($entry['history'] ?? []) as $hist) {
    if (isset($hist['version']) && $hist['version'] == $v2 && isset($hist['data'])) {
        $data_v2 = $hist['data'];
        break;
    }
}

if (!$data_v1 || !$data_v2) {
    echo '<p>En eller begge versioner ikke fundet.</p>';
    exit();
}

// Get opgave name for display
$opgave_name = $data_v1['basic']['opgave'] ?? $data_v2['basic']['opgave'] ?? 'Unavngiven SJA';

// Define items for each section
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
    <title>Sammenlign SJA Versioner</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 1rem; color: #222; }
        h1 { font-size: 1.6em; margin-bottom: 0.5rem; }
        .subtitle { color: #666; margin-bottom: 1rem; }
        .version-header { 
            background-color: #f0f0f0; 
            padding: 1rem; 
            margin-bottom: 1rem;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .version-badge { 
            display: inline-block;
            padding: 0.3rem 0.6rem;
            background-color: #0070C0;
            color: white;
            border-radius: 3px;
            font-weight: bold;
        }
        .comparison-table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
        .comparison-table th { background-color: #0070C0; color: white; padding: 0.6rem; text-align: left; }
        .comparison-table td { border: 1px solid #ccc; padding: 0.5rem; vertical-align: top; }
        .comparison-table .field-name { font-weight: bold; background-color: #f5f5f5; width: 25%; }
        .comparison-table .changed { background-color: #fff3cd; }
        .section-header { 
            background-color: #0070C0; 
            color: white; 
            padding: 0.5rem; 
            margin-top: 1.5rem;
            font-weight: bold;
        }
        .no-changes { color: #28a745; font-style: italic; }
        a.back-link { display: inline-block; margin: 1rem 0; color: #0070C0; text-decoration: none; }
        a.back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>Sammenlign SJA Versioner</h1>
    <p class="subtitle">SJA: <strong><?php echo htmlspecialchars($opgave_name); ?></strong></p>

    <div class="version-header">
        <div>
            <span class="version-badge">Version <?php echo htmlspecialchars($v2); ?></span>
            <span style="margin: 0 1rem;">→</span>
            <span class="version-badge" style="background-color: #28a745;">Version <?php echo htmlspecialchars($v1); ?></span>
        </div>
        <div>
            <em>Ændringer fremhævet med gul baggrund</em>
        </div>
    </div>

    <div class="section-header">Basisinformation</div>
    <table class="comparison-table">
        <tr>
            <th class="field-name">Felt</th>
            <th>Version <?php echo htmlspecialchars($v2); ?></th>
            <th>Version <?php echo htmlspecialchars($v1); ?></th>
        </tr>
        <?php
        $basic_fields = ['opgave', 'wo_po', 'dato_udfoer', 'planlagt_af', 'udfoeres_af', 'dato_planlagt', 'koordinator'];
        $basic_labels = [
            'opgave' => 'Opgave',
            'wo_po' => 'WO/PO',
            'dato_udfoer' => 'Dato udførelse',
            'planlagt_af' => 'Planlagt af',
            'udfoeres_af' => 'Udføres af',
            'dato_planlagt' => 'Dato planlagt',
            'koordinator' => 'Koordinator'
        ];
        foreach ($basic_fields as $field):
            $val1 = $data_v1['basic'][$field] ?? '';
            $val2 = $data_v2['basic'][$field] ?? '';
            $changed = getCellClass($val1, $val2);
        ?>
        <tr>
            <td class="field-name"><?php echo htmlspecialchars($basic_labels[$field]); ?></td>
            <td class="<?php echo $changed; ?>"><?php echo htmlspecialchars($val2); ?></td>
            <td class="<?php echo $changed; ?>"><?php echo htmlspecialchars($val1); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php
        $status1 = $data_v1['status'] ?? 'active';
        $status2 = $data_v2['status'] ?? 'active';
        $changed = getCellClass($status1, $status2);
        ?>
        <tr>
            <td class="field-name">Status</td>
            <td class="<?php echo $changed; ?>"><?php echo $status2 === 'completed' ? 'Afsluttet' : 'Aktiv'; ?></td>
            <td class="<?php echo $changed; ?>"><?php echo $status1 === 'completed' ? 'Afsluttet' : 'Aktiv'; ?></td>
        </tr>
    </table>

    <?php
    displaySectionComparison('Risici', $risk_items, $data_v1['risici'] ?? [], $data_v2['risici'] ?? [], $v1, $v2);
    displaySectionComparison('Tilladelser', $permission_items, $data_v1['tilladelser'] ?? [], $data_v2['tilladelser'] ?? [], $v1, $v2);
    displaySectionComparison('Personlige værnemidler', $ppe_items, $data_v1['vaernemidler'] ?? [], $data_v2['vaernemidler'] ?? [], $v1, $v2);
    displaySectionComparison('Sikkerhedsudstyr', $equipment_items, $data_v1['udstyr'] ?? [], $data_v2['udstyr'] ?? [], $v1, $v2);
    displaySectionComparison('Har du tænkt på…', $consider_items, $data_v1['taenkt'] ?? [], $data_v2['taenkt'] ?? [], $v1, $v2);
    ?>

    <div class="section-header">Øvrige bemærkninger</div>
    <table class="comparison-table">
        <tr>
            <th>Version <?php echo htmlspecialchars($v2); ?></th>
            <th>Version <?php echo htmlspecialchars($v1); ?></th>
        </tr>
        <?php
        $bem1 = $data_v1['bem'] ?? '';
        $bem2 = $data_v2['bem'] ?? '';
        $changed = getCellClass($bem1, $bem2);
        ?>
        <tr>
            <td class="<?php echo $changed; ?>"><?php echo nl2br(htmlspecialchars($bem2)); ?></td>
            <td class="<?php echo $changed; ?>"><?php echo nl2br(htmlspecialchars($bem1)); ?></td>
        </tr>
    </table>

    <a href="sja_history.php?id=<?php echo urlencode($id); ?>" class="back-link">← Tilbage til versionshistorik</a>
</body>
</html>
