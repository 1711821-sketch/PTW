<?php
// ---------------------------------------------------------------------------
// create_wo_fixed.php
//
// This page replaces the older create_wo.php with a version that includes
// improved client‑side PDF parsing and a Leaflet map for selecting a work
// location.  It preserves the original server‑side behaviour for creating
// and editing work orders stored in a JSON file (wo_data.json) while
// switching to the new field names expected by the parser.  The form
// exposes inputs for the most common work order fields (work order number,
// description, P‑number, MPS number, job responsible, telephone, creator
// ID, creation date, components, contractor and contact person, status,
// latitude/longitude and notes).  When the user submits the form, the
// entry is saved to a JSON file.  When a PDF is uploaded and parsed via
// pdf.js, the form fields are auto‑populated, and a log shows which
// fields were found or missing.
// ---------------------------------------------------------------------------

session_start();

// Only allow authenticated users to create or edit WOs.  If the session
// does not contain a 'user' key, redirect to the login page.
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Get user role for permission checks
$role = $_SESSION['role'] ?? 'user';

// Path to the JSON file where work orders are stored.  If this file
// doesn’t exist it will be created on first save.
$data_file = __DIR__ . '/wo_data.json';

// Load existing entries from the data file.
$entries = [];
if (file_exists($data_file)) {
    $data = json_decode(file_get_contents($data_file), true);
    if (is_array($data)) {
        $entries = $data;
    }
}

// Determine if we're editing an existing work order.  If the id query
// parameter is present, try to locate the corresponding entry in the data
// file; otherwise prepare a blank current entry.
$edit_id = isset($_GET['id']) ? $_GET['id'] : null;

// Provide sensible defaults for all fields used in the form.  These keys
// correspond to the names used in the form below and to the values
// persisted in the JSON file.  Additional keys may be added later as
// requirements evolve.
$current = [
    'id'                  => $edit_id ?? '',
    'work_order_no'       => '',
    'p_number'            => '',
    'mps_nr'              => '',
    'description'         => '',
    // Beskrivelse associated with the P-number (second description in the PDF)
    'p_description'       => '',
    'jobansvarlig'        => '',
    'telefon'             => '',
    'oprettet_af'         => '',
    'oprettet_dato'       => '',
    'components'          => '',
    'entreprenor_firma'   => '',
    'entreprenor_kontakt' => '',
    'status'              => 'planning',
    'latitude'            => '',
    'longitude'           => '',
    'notes'               => ''
];

// If editing, copy the values from the existing entry into $current.
if ($edit_id) {
    foreach ($entries as $entry) {
        if ((string)$entry['id'] === (string)$edit_id) {
            $current = array_merge($current, $entry);
            break;
        }
    }
}

// When the form is submitted (via POST) and the save button is clicked,
// collect all posted values, merge them into $current and persist them to
// the JSON data file.  After saving the entry, redirect back to the
// overview page so that users don't accidentally resubmit the form.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_wo'])) {
    // Trim all incoming values to avoid leading/trailing spaces.
    $current['work_order_no']       = trim($_POST['work_order_no'] ?? '');
    $current['description']         = trim($_POST['description'] ?? '');
    // Capture the P‑number description separately
    $current['p_description']       = trim($_POST['p_description'] ?? '');
    $current['p_number']            = trim($_POST['p_number'] ?? '');
    $current['mps_nr']              = trim($_POST['mps_nr'] ?? '');
    $current['jobansvarlig']        = trim($_POST['jobansvarlig'] ?? '');
    $current['telefon']             = trim($_POST['telefon'] ?? '');
    $current['oprettet_af']         = trim($_POST['oprettet_af'] ?? '');
    $current['oprettet_dato']       = trim($_POST['oprettet_dato'] ?? '');
    $current['components']          = trim($_POST['components'] ?? '');
    $current['entreprenor_firma']   = trim($_POST['entreprenor_firma'] ?? '');
    $current['entreprenor_kontakt'] = trim($_POST['entreprenor_kontakt'] ?? '');
    $current['notes']               = trim($_POST['notes'] ?? '');
    $current['status']              = ($_POST['status'] ?? 'planning');
    $current['latitude']            = trim($_POST['latitude'] ?? '');
    $current['longitude']           = trim($_POST['longitude'] ?? '');

    // When editing an existing entry, update it in place.  Otherwise
    // assign a new id sequentially and append the entry to the list.
    if ($edit_id) {
        foreach ($entries as &$entry) {
            if ((string)$entry['id'] === (string)$edit_id) {
                $entry = $current;
                break;
            }
        }
    } else {
        // Determine next id by inspecting existing entries.  If no entries
        // exist, start at 1.  Cast ids to integers for comparison.
        $max_id = 0;
        foreach ($entries as $entry) {
            $id_int = isset($entry['id']) ? (int)$entry['id'] : 0;
            if ($id_int > $max_id) $max_id = $id_int;
        }
        $current['id'] = (string)($max_id + 1);
        $entries[] = $current;
    }
    // Persist entries to the JSON file.  Use JSON_PRETTY_PRINT for
    // readability and ensure the file is created if it doesn't exist.
    file_put_contents($data_file, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Redirect to the overview page after saving to prevent duplicate
    // submissions if the user refreshes the page.
    header('Location: view_wo.php');
    exit();
}

// The default centre for the map if no coordinates have been provided.
// Centre the map on Stigsnæs Gulfhavn oil terminal (approximate coordinates).
// These values correspond to the latitude and longitude of the facility (55.205903, 11.264111).
$defaultLat = $current['latitude'] !== '' ? $current['latitude'] : '55.205903';
$defaultLng = $current['longitude'] !== '' ? $current['longitude'] : '11.264111';
?>
<!DOCTYPE html>
<html lang="da">
<head>
<meta charset="UTF-8">
<title><?php echo $edit_id ? 'Rediger arbejdstilladelse' : 'Opret ny arbejdstilladelse'; ?></title>
<!-- Include global stylesheet for modern responsive design -->
<link rel="stylesheet" href="style.css">
  <!-- Script to update the status select styling based on its value -->
  <script>
    function updateStatusClass() {
      var select = document.getElementById('status');
      if (!select) return;
      // Remove any previous status classes
      select.classList.remove('status-planlagt', 'status-aktiv', 'status-afsluttet');
      var val = select.value;
      if (val === 'planning') {
        select.classList.add('status-planlagt');
      } else if (val === 'active') {
        select.classList.add('status-aktiv');
      } else if (val === 'completed') {
        select.classList.add('status-afsluttet');
      }
    }
    document.addEventListener('DOMContentLoaded', function() {
      var statusSelect = document.getElementById('status');
      if (statusSelect) {
        updateStatusClass();
        statusSelect.addEventListener('change', updateStatusClass);
      }
    });
  </script>
<!-- Include Leaflet for the interactive map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Remove page-specific inline styles; all base styling lives in style.css -->
</head>
<body>
    <!-- Top navigation bar -->
    <nav class="navbar">
        <a href="view_wo.php">Oversigt over arbejdstilladelser</a>
        <?php if (in_array($role ?? '', ['admin','opgaveansvarlig','drift'])): ?>
            <a href="create_wo.php">Opret ny arbejdstilladelse</a>
        <?php endif; ?>
        <a href="map_wo.php">Kort</a>
        <!-- Add link to the dashboard so users can access statistics -->
        <a href="dashboard.php">Dashboard</a>
        <span class="nav-user">Logget ind som <?php echo htmlspecialchars($_SESSION['user'] ?? ''); ?> (<?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?>)</span>
        <a class="logout-link" href="logout.php">Log ud</a>
    </nav>
    <div class="container">
<h1><?php echo $edit_id ? 'Rediger arbejdstilladelse' : 'Opret ny arbejdstilladelse'; ?></h1>
<p>Udfyld formularen. Kortet kan bruges til at vælge positionen for arbejdet.</p>

<!-- Modern form with organized sections -->
<form method="post" style="background: transparent; padding: 0; box-shadow: none; border: none;">
  

  <!-- Basic Information Section -->
  <div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
      <h3 style="margin: 0; color: var(--primary-color);">📋 Grundlæggende oplysninger</h3>
    </div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group">
          <label for="work_order_no">Arbejdstilladelse Nr.</label>
          <input type="text" id="work_order_no" name="work_order_no" value="<?php echo htmlspecialchars($current['work_order_no']); ?>">
        </div>
        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="planning"  <?php echo ($current['status'] === 'planning')  ? 'selected' : ''; ?>>Planlagt</option>
            <option value="active"    <?php echo ($current['status'] === 'active')    ? 'selected' : ''; ?>>Aktiv</option>
            <option value="completed" <?php echo ($current['status'] === 'completed') ? 'selected' : ''; ?>>Afsluttet</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label for="description">Beskrivelse</label>
        <textarea id="description" name="description"><?php echo htmlspecialchars($current['description']); ?></textarea>
      </div>
    </div>
  </div>

  <!-- P-Number & Technical Information Section -->
  <div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
      <h3 style="margin: 0; color: var(--primary-color);">🔧 Tekniske oplysninger</h3>
    </div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group">
          <label for="p_number">P-nummer</label>
          <input type="text" id="p_number" name="p_number" value="<?php echo htmlspecialchars($current['p_number']); ?>">
        </div>
        <div class="form-group">
          <label for="mps_nr">MPS-nr.</label>
          <input type="text" id="mps_nr" name="mps_nr" value="<?php echo htmlspecialchars($current['mps_nr']); ?>">
        </div>
      </div>
      <div class="form-group">
        <label for="p_description">Beskrivelse (P-nummer)</label>
        <textarea id="p_description" name="p_description"><?php echo htmlspecialchars($current['p_description']); ?></textarea>
      </div>
      <div class="form-group">
        <label for="components">Komponent nr. (én pr. linje)</label>
        <textarea id="components" name="components"><?php echo htmlspecialchars($current['components']); ?></textarea>
      </div>
    </div>
  </div>

  <!-- Responsibility Section -->
  <div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
      <h3 style="margin: 0; color: var(--primary-color);">👨‍💼 Ansvarlige</h3>
    </div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group">
          <label for="jobansvarlig">Jobansvarlig</label>
          <input type="text" id="jobansvarlig" name="jobansvarlig" value="<?php echo htmlspecialchars($current['jobansvarlig']); ?>">
        </div>
        <div class="form-group">
          <label for="telefon">Jobansvarlig telefon</label>
          <input type="tel" id="telefon" name="telefon" value="<?php echo htmlspecialchars($current['telefon']); ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="oprettet_af">WO oprettet af</label>
          <input type="text" id="oprettet_af" name="oprettet_af" value="<?php echo htmlspecialchars($current['oprettet_af']); ?>">
        </div>
        <div class="form-group">
          <label for="oprettet_dato">WO oprettet dato</label>
          <input type="date" id="oprettet_dato" name="oprettet_dato" value="<?php echo htmlspecialchars($current['oprettet_dato']); ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- Contractor Information Section -->
  <div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
      <h3 style="margin: 0; color: var(--secondary-color);">🏢 Entreprenør oplysninger</h3>
    </div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group">
          <label for="entreprenor_firma">Entreprenør firma</label>
          <input type="text" id="entreprenor_firma" name="entreprenor_firma" value="<?php echo htmlspecialchars($current['entreprenor_firma']); ?>">
        </div>
        <div class="form-group">
          <label for="entreprenor_kontakt">Entreprenør kontaktperson</label>
          <input type="text" id="entreprenor_kontakt" name="entreprenor_kontakt" value="<?php echo htmlspecialchars($current['entreprenor_kontakt']); ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- Location Section -->
  <div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
      <h3 style="margin: 0; color: var(--accent-color);">📍 Placering</h3>
    </div>
    <div class="card-body">
      <div class="form-group">
        <label for="map">Klik på kortet for at vælge placering</label>
        <div id="map"></div>
        <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($current['latitude'] ?: $defaultLat); ?>">
        <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($current['longitude'] ?: $defaultLng); ?>">
      </div>
    </div>
  </div>

  <!-- Notes Section -->
  <div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
      <h3 style="margin: 0; color: var(--text-primary);">📝 Bemærkninger</h3>
    </div>
    <div class="card-body">
      <div class="form-group">
        <label for="notes">Yderligere bemærkninger</label>
        <textarea id="notes" name="notes" style="min-height: 120px;"><?php echo htmlspecialchars($current['notes']); ?></textarea>
      </div>
    </div>
  </div>

  <!-- Save Button -->
  <div style="text-align: center; margin: 2rem 0;">
    <button type="submit" name="save_wo" class="button-lg" style="padding: 1rem 3rem; font-size: 1.1rem;">
      <?php echo $edit_id ? '💾 Gem ændringer' : '💾 Gem arbejdstilladelse'; ?>
    </button>
  </div>
</form>


<script>

// Initialise the Leaflet map.  Use stored coordinates if available,
// otherwise fall back to defaults defined in PHP.  A marker is added
// when coordinates are present, and clicking the map updates the
// marker and hidden latitude/longitude fields.
var latInput = document.getElementById('latitude');
var lonInput = document.getElementById('longitude');
var initLat = <?php echo ($current['latitude'] !== '') ? json_encode($current['latitude']) : $defaultLat; ?>;
var initLng = <?php echo ($current['longitude'] !== '') ? json_encode($current['longitude']) : $defaultLng; ?>;
var initZoom = (<?php echo ($current['latitude'] !== '' && $current['longitude'] !== '') ? 'true' : 'false'; ?>) ? 16 : 15;
var map = L.map('map').setView([initLat, initLng], initZoom);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);
var marker = null;
function setMarker(lat, lng) {
    if (marker) { map.removeLayer(marker); }
    marker = L.marker([lat, lng]).addTo(map);
    latInput.value = lat;
    lonInput.value = lng;
}
// If coordinates exist, show the marker at that position
if (<?php echo ($current['latitude'] !== '' && $current['longitude'] !== '') ? 'true' : 'false'; ?>) {
    setMarker(initLat, initLng);
}
// Update marker and hidden fields when the map is clicked
map.on('click', function(e) {
    setMarker(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6));
});

</script>
    </div><!-- /.container -->
</body>
</html>