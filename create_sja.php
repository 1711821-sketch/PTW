<?php
// This script renders a comprehensive Safety Job Analysis (SJA) form and handles
// both creation and editing of SJA entries. Each entry is stored in a JSON
// file with a unique identifier. When an entry ID is provided via GET,
// the form pre‑loads the existing data for editing. On submission the
// entry is either appended or updated and persisted. Only authenticated
// users (via session) can access this page.

session_start();

// If the user is not logged in redirect them to the login page.
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Use database instead of JSON files
require_once 'database.php';

// Determine if we are editing an existing entry
$edit_id = isset($_GET['id']) ? $_GET['id'] : null;

// Initialise an array to populate form values
// Extend the default structure with location fields for map support
$current = [
    'id' => $edit_id ?? '',
    // Basic information about the task
    'basic' => [
        'opgave'        => '',
        'wo_po'         => '',
        'dato_udfoer'   => '',
        'planlagt_af'   => '',
        'udfoeres_af'   => '',
        'dato_planlagt' => '',
        'koordinator'   => '',
    ],
    // Collections for the different sections of the form
    'risici' => [],
    'tilladelser' => [],
    'vaernemidler' => [],
    'udstyr' => [],
    'taenkt' => [],
    'cancer' => [],
    'bem' => '',
    'deltagere' => [],
    // Status of the SJA. New entries default to active if not set.
    'status' => 'active',
    // Latitude/longitude for location (saved when user clicks on map)
    'latitude' => '',
    'longitude' => ''
];

// Define the structured lists for form sections
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

// Default selection options for yes/no/IR
$status_options = [
    ''  => '—',
    'Ja' => 'Ja',
    'Nej' => 'Nej',
    'IR' => 'IR'
];

// If editing, load existing values into $current
if ($edit_id) {
    try {
        $db = Database::getInstance();
        $entry = $db->fetch("SELECT * FROM sja_entries WHERE id = ?", [$edit_id]);
        
        if ($entry) {
            // Convert database format back to form format
            $current = [
                'id' => $entry['id'],
                'basic' => json_decode($entry['basic_info'] ?? '{}', true) ?: [],
                'risici' => json_decode($entry['risks'] ?? '{}', true) ?: [],
                'tilladelser' => json_decode($entry['permissions'] ?? '{}', true) ?: [],
                'vaernemidler' => json_decode($entry['ppe'] ?? '{}', true) ?: [],
                'udstyr' => json_decode($entry['equipment'] ?? '{}', true) ?: [],
                'taenkt' => json_decode($entry['considerations'] ?? '{}', true) ?: [],
                'cancer' => json_decode($entry['cancer_substances'] ?? '{}', true) ?: [],
                'bem' => $entry['remarks'] ?? '',
                'deltagere' => json_decode($entry['participants'] ?? '[]', true) ?: [],
                'status' => $entry['status'] ?? 'active',
                'latitude' => $entry['latitude'] ?? '',
                'longitude' => $entry['longitude'] ?? ''
            ];
        }
    } catch (Exception $e) {
        error_log("Error loading SJA for edit: " . $e->getMessage());
    }
}

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect posted values safely
    $posted_id = $_POST['entry_id'] ?? '';
    $basic     = $_POST['basic'] ?? [];
    $risici    = $_POST['risici'] ?? [];
    $till      = $_POST['tilladelser'] ?? [];
    $ppe       = $_POST['vaernemidler'] ?? [];
    $udstyr    = $_POST['udstyr'] ?? [];
    $taenkt    = $_POST['taenkt'] ?? [];
    $cancer    = $_POST['cancer'] ?? [];
    $bem       = $_POST['bem'] ?? '';
    $deltagere = $_POST['deltagere'] ?? [];

    // Status submitted from the form. Default to 'active' when not provided.
    $status    = isset($_POST['status']) ? htmlspecialchars(trim((string)$_POST['status']), ENT_QUOTES, 'UTF-8') : 'active';

    // Sanitize the data recursively
    $sanitize = function($value) use (&$sanitize) {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $sanitize($v);
            }
            return $out;
        }
        return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
    };
    $basic     = $sanitize($basic);
    $risici    = $sanitize($risici);
    $till      = $sanitize($till);
    $ppe       = $sanitize($ppe);
    $udstyr    = $sanitize($udstyr);
    $taenkt    = $sanitize($taenkt);
    $cancer    = $sanitize($cancer);
    $bem       = htmlspecialchars(trim((string)$bem), ENT_QUOTES, 'UTF-8');
    $deltagere = $sanitize($deltagere);

    // Capture map coordinates if provided
    $lat = isset($_POST['latitude']) ? htmlspecialchars(trim((string)$_POST['latitude']), ENT_QUOTES, 'UTF-8') : '';
    $lng = isset($_POST['longitude']) ? htmlspecialchars(trim((string)$_POST['longitude']), ENT_QUOTES, 'UTF-8') : '';

    // Save to PostgreSQL database
    try {
        $db = Database::getInstance();
        $work_order_id = $_POST['wo_id'] ?? null;
        // Convert empty string to NULL for integer column
        if (empty($work_order_id)) {
            $work_order_id = null;
        } else {
            $work_order_id = intval($work_order_id);
        }
        $sja_title = $basic['opgave'] ?? 'SJA';
        
        // Prepare data for database
        $basic_info = json_encode($basic, JSON_UNESCAPED_UNICODE);
        $risks = json_encode($risici, JSON_UNESCAPED_UNICODE);
        $permissions = json_encode($till, JSON_UNESCAPED_UNICODE);
        $ppe_data = json_encode($ppe, JSON_UNESCAPED_UNICODE);
        $equipment_data = json_encode($udstyr, JSON_UNESCAPED_UNICODE);
        $considerations = json_encode($taenkt, JSON_UNESCAPED_UNICODE);
        $cancer_substances = json_encode($cancer, JSON_UNESCAPED_UNICODE);
        $participants = json_encode($deltagere, JSON_UNESCAPED_UNICODE);
        
        if ($posted_id && is_numeric($posted_id)) {
            // Update existing SJA
            $db->execute("
                UPDATE sja_entries SET
                    work_order_id = ?,
                    sja_title = ?,
                    basic_info = ?,
                    risks = ?,
                    permissions = ?,
                    ppe = ?,
                    equipment = ?,
                    considerations = ?,
                    cancer_substances = ?,
                    remarks = ?,
                    participants = ?,
                    status = ?,
                    latitude = ?,
                    longitude = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ", [
                $work_order_id, $sja_title, $basic_info, $risks, $permissions, 
                $ppe_data, $equipment_data, $considerations, $cancer_substances, 
                $bem, $participants, $status, 
                $lat ? floatval($lat) : null, $lng ? floatval($lng) : null, 
                $posted_id
            ]);
            $entry_id = $posted_id;
            $message = 'SJA opdateret!';
        } else {
            // Insert new SJA
            $result = $db->fetch("
                INSERT INTO sja_entries (
                    work_order_id, sja_title, basic_info, risks, permissions, 
                    ppe, equipment, considerations, cancer_substances, 
                    remarks, participants, status, latitude, longitude
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id
            ", [
                $work_order_id, $sja_title, $basic_info, $risks, $permissions, 
                $ppe_data, $equipment_data, $considerations, $cancer_substances, 
                $bem, $participants, $status, 
                $lat ? floatval($lat) : null, $lng ? floatval($lng) : null
            ]);
            $entry_id = $result['id'];
            $message = 'SJA gemt!';
        }
        
        // Update current data for form display
        $current = [
            'id' => $entry_id,
            'basic' => $basic,
            'risici' => $risici,
            'tilladelser' => $till,
            'vaernemidler' => $ppe,
            'udstyr' => $udstyr,
            'taenkt' => $taenkt,
            'cancer' => $cancer,
            'bem' => $bem,
            'deltagere' => $deltagere,
            'status' => $status,
            'latitude' => $lat,
            'longitude' => $lng
        ];
        
        // Set edit_id for after-submit view
        $edit_id = $entry_id;
        
    } catch (Exception $e) {
        error_log("Error saving SJA: " . $e->getMessage());
        $message = 'Fejl ved gemning af SJA: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $edit_id ? 'Rediger SJA' : 'Opret SJA'; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 1rem; }
        h1 { margin-bottom: 0.5rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; overflow-x: auto; display: block; }
        th, td { border: 1px solid #ccc; padding: 0.4rem; text-align: left; }
        th { background-color: #f2f2f2; }
        select, input[type="text"], input[type="date"] { width: 100%; box-sizing: border-box; }
        textarea { width: 100%; height: 4rem; box-sizing: border-box; }
        .section { margin-top: 2rem; }
        .success { color: green; }
        .btn { margin-top: 1rem; padding: 0.5rem 1rem; }
        /* Responsive adjustments: stack table rows on small screens */
        @media (max-width: 768px) {
            table, tbody, tr, th, td { display: block; width: 100%; }
            tr { margin-bottom: 1rem; }
            th { background-color: transparent; font-weight: bold; }
            th, td { border: none; padding: 0.3rem 0; }
            td input, td select { margin-top: 0.2rem; }
        }
    </style>

    <!-- Removed Leaflet map dependencies -->
</head>
<body>
    <h1><?php echo $edit_id ? 'Rediger Sikker Job Analyse' : 'Opret Sikker Job Analyse (SJA)'; ?></h1>
    <?php if ($message): ?><p class="success"><?php echo $message; ?></p><?php endif; ?>
    <form method="post" action="">
        <!-- Hidden field to preserve ID when editing -->
        <input type="hidden" name="entry_id" value="<?php echo htmlspecialchars($current['id']); ?>">
        <!-- Hidden field to carry WO ID so that the SJA can be linked to a work order.  If no wo_id is supplied in the URL, this will be empty. -->
        <input type="hidden" name="wo_id" value="<?php echo htmlspecialchars($_GET['wo_id'] ?? ''); ?>">
        <div class="section">
            <h2>Basisinformation</h2>
            <table>
                <tr>
                    <td>Opgave</td>
                    <td><input type="text" name="basic[opgave]" value="<?php echo htmlspecialchars($current['basic']['opgave']); ?>" required></td>
                </tr>
                <tr>
                    <td>WO/PO</td>
                    <td><input type="text" name="basic[wo_po]" value="<?php echo htmlspecialchars($current['basic']['wo_po']); ?>"></td>
                </tr>
                <tr>
                    <td>Dato udførelse</td>
                    <td><input type="date" name="basic[dato_udfoer]" value="<?php echo htmlspecialchars($current['basic']['dato_udfoer']); ?>"></td>
                </tr>
                <tr>
                    <td>Planlagt af</td>
                    <td><input type="text" name="basic[planlagt_af]" value="<?php echo htmlspecialchars($current['basic']['planlagt_af']); ?>"></td>
                </tr>
                <tr>
                    <td>Udføres af</td>
                    <td><input type="text" name="basic[udfoeres_af]" value="<?php echo htmlspecialchars($current['basic']['udfoeres_af']); ?>"></td>
                </tr>
                <tr>
                    <td>Dato planlagt</td>
                    <td><input type="date" name="basic[dato_planlagt]" value="<?php echo htmlspecialchars($current['basic']['dato_planlagt']); ?>"></td>
                </tr>
                <tr>
                    <td>Koordinator</td>
                    <td><input type="text" name="basic[koordinator]" value="<?php echo htmlspecialchars($current['basic']['koordinator']); ?>"></td>
                </tr>
            </table>
        </div>

        <!-- Location selection section removed.  The map has been removed per user request. -->

        <!-- Status selection section -->
        <div class="section">
            <h2>Status</h2>
            <select name="status" required>
                <option value="active" <?php echo (($current['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Aktiv</option>
                <option value="completed" <?php echo (($current['status'] ?? 'active') === 'completed') ? 'selected' : ''; ?>>Afsluttet</option>
            </select>
        </div>

        <div class="section">
            <h2>Risici</h2>
            <table>
                <tr>
                    <th>Punkt</th>
                    <th>Status</th>
                    <th>Bemærkning</th>
                </tr>
                <?php foreach ($risk_items as $key => $label):
                    $sel = $current['risici'][$key]['status'] ?? '';
                    $remark = $current['risici'][$key]['remark'] ?? '';
                ?>
                <tr>
                    <td><?php echo $label; ?></td>
                    <td>
                        <select name="risici[<?php echo $key; ?>][status]">
                            <?php foreach ($status_options as $val => $opt_label): ?>
                                <option value="<?php echo $val; ?>" <?php echo ($sel === $val ? 'selected' : ''); ?>><?php echo $opt_label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" name="risici[<?php echo $key; ?>][remark]" value="<?php echo htmlspecialchars($remark); ?>"></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="section">
            <h2>Tilladelser</h2>
            <table>
                <tr><th>Punkt</th><th>Status</th><th>Bemærkning</th></tr>
                <?php foreach ($permission_items as $key => $label):
                    $sel = $current['tilladelser'][$key]['status'] ?? '';
                    $remark = $current['tilladelser'][$key]['remark'] ?? '';
                ?>
                <tr>
                    <td><?php echo $label; ?></td>
                    <td>
                        <select name="tilladelser[<?php echo $key; ?>][status]">
                            <?php foreach ($status_options as $val => $opt_label): ?>
                                <option value="<?php echo $val; ?>" <?php echo ($sel === $val ? 'selected' : ''); ?>><?php echo $opt_label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" name="tilladelser[<?php echo $key; ?>][remark]" value="<?php echo htmlspecialchars($remark); ?>"></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="section">
            <h2>Personlige værnemidler</h2>
            <table>
                <tr><th>Punkt</th><th>Status</th><th>Bemærkning</th></tr>
                <?php foreach ($ppe_items as $key => $label):
                    $sel = $current['vaernemidler'][$key]['status'] ?? '';
                    $remark = $current['vaernemidler'][$key]['remark'] ?? '';
                ?>
                <tr>
                    <td><?php echo $label; ?></td>
                    <td>
                        <select name="vaernemidler[<?php echo $key; ?>][status]">
                            <?php foreach ($status_options as $val => $opt_label): ?>
                                <option value="<?php echo $val; ?>" <?php echo ($sel === $val ? 'selected' : ''); ?>><?php echo $opt_label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" name="vaernemidler[<?php echo $key; ?>][remark]" value="<?php echo htmlspecialchars($remark); ?>"></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="section">
            <h2>Sikkerhedsudstyr</h2>
            <table>
                <tr><th>Punkt</th><th>Status</th><th>Bemærkning</th></tr>
                <?php foreach ($equipment_items as $key => $label):
                    $sel = $current['udstyr'][$key]['status'] ?? '';
                    $remark = $current['udstyr'][$key]['remark'] ?? '';
                ?>
                <tr>
                    <td><?php echo $label; ?></td>
                    <td>
                        <select name="udstyr[<?php echo $key; ?>][status]">
                            <?php foreach ($status_options as $val => $opt_label): ?>
                                <option value="<?php echo $val; ?>" <?php echo ($sel === $val ? 'selected' : ''); ?>><?php echo $opt_label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" name="udstyr[<?php echo $key; ?>][remark]" value="<?php echo htmlspecialchars($remark); ?>"></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="section">
            <h2>Har du tænkt på…</h2>
            <table>
                <tr><th>Punkt</th><th>Status</th><th>Bemærkning</th></tr>
                <?php foreach ($consider_items as $key => $label):
                    $sel = $current['taenkt'][$key]['status'] ?? '';
                    $remark = $current['taenkt'][$key]['remark'] ?? '';
                ?>
                <tr>
                    <td><?php echo $label; ?></td>
                    <td>
                        <select name="taenkt[<?php echo $key; ?>][status]">
                            <?php foreach ($status_options as $val => $opt_label): ?>
                                <option value="<?php echo $val; ?>" <?php echo ($sel === $val ? 'selected' : ''); ?>><?php echo $opt_label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" name="taenkt[<?php echo $key; ?>][remark]" value="<?php echo htmlspecialchars($remark); ?>"></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="section">
            <h2>Kræftfremkaldende stoffer</h2>
            <p>Angiv navn, CAS nr., grænseværdi, sikkerhedsdatablad og foranstaltninger for op til 5 stoffer.</p>
            <table>
                <tr><th>#</th><th>Navn</th><th>CAS nr.</th><th>Grænseværdi</th><th>Sikkerhedsdatablad</th><th>Foranstaltninger</th></tr>
                <?php
                $rows = max(3, count($current['cancer']));
                for ($i = 0; $i < $rows; $i++):
                    $c = $current['cancer'][$i] ?? ['name'=>'','cas'=>'','limit'=>'','datasheet'=>'','measures'=>''];
                ?>
                <tr>
                    <td><?php echo $i+1; ?></td>
                    <td><input type="text" name="cancer[<?php echo $i; ?>][name]" value="<?php echo htmlspecialchars($c['name']); ?>"></td>
                    <td><input type="text" name="cancer[<?php echo $i; ?>][cas]" value="<?php echo htmlspecialchars($c['cas']); ?>"></td>
                    <td><input type="text" name="cancer[<?php echo $i; ?>][limit]" value="<?php echo htmlspecialchars($c['limit']); ?>"></td>
                    <td><input type="text" name="cancer[<?php echo $i; ?>][datasheet]" value="<?php echo htmlspecialchars($c['datasheet']); ?>"></td>
                    <td><input type="text" name="cancer[<?php echo $i; ?>][measures]" value="<?php echo htmlspecialchars($c['measures']); ?>"></td>
                </tr>
                <?php endfor; ?>
            </table>
        </div>

        <div class="section">
            <h2>Øvrige bemærkninger</h2>
            <textarea name="bem"><?php echo htmlspecialchars($current['bem']); ?></textarea>
        </div>

        <div class="section">
            <h2>Deltagere (navn & telefon)</h2>
            <p>Du kan angive op til 10 deltagere.</p>
            <table>
                <tr><th>#</th><th>Navn</th><th>Telefon</th></tr>
                <?php
                for ($i = 0; $i < 10; $i++):
                    $d = $current['deltagere'][$i] ?? ['navn'=>'','telefon'=>''];
                ?>
                <tr>
                    <td><?php echo $i+1; ?></td>
                    <td><input type="text" name="deltagere[<?php echo $i; ?>][navn]" value="<?php echo htmlspecialchars($d['navn']); ?>"></td>
                    <td><input type="text" name="deltagere[<?php echo $i; ?>][telefon]" value="<?php echo htmlspecialchars($d['telefon']); ?>"></td>
                </tr>
                <?php endfor; ?>
            </table>
        </div>

        <button type="submit" name="save_sja" class="button button-success button-lg">
            <?php echo $edit_id ? '💾 Gem ændringer' : '💾 Gem SJA'; ?>
        </button>
    </form>

    <p><a href="view_sja.php">Se eksisterende SJA'er</a></p>
    <p><a href="index.php">Tilbage til forsiden</a></p>

    <!-- Map initialization script removed -->
</body>
</html>
