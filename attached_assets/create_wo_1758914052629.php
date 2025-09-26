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
<title><?php echo $edit_id ? 'Rediger WO' : 'Opret ny WO'; ?></title>
<!-- Include global stylesheet for modern responsive design -->
<link rel="stylesheet" href="style.css">
<!-- Include pdf.js and its worker for client‑side PDF parsing -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.14.305/pdf.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc =
  'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.14.305/pdf.worker.min.js';
</script>
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
        <a href="view_wo.php">WO Oversigt</a>
        <?php if (in_array($role ?? '', ['admin','opgaveansvarlig','drift'])): ?>
            <a href="create_wo.php">Opret ny WO</a>
        <?php endif; ?>
        <a href="map_wo.php">Kort</a>
        <!-- Add link to the dashboard so users can access statistics -->
        <a href="dashboard.php">Dashboard</a>
        <span class="nav-user">Logget ind som <?php echo htmlspecialchars($_SESSION['user'] ?? ''); ?> (<?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?>)</span>
        <a class="logout-link" href="logout.php">Log ud</a>
    </nav>
    <div class="container">
<h1><?php echo $edit_id ? 'Rediger Arbejdstilladelse / WO' : 'Opret ny Arbejdstilladelse (WO)'; ?></h1>
<p>Udfyld formularen manuelt, eller upload en PDF for at få felterne udfyldt automatisk. Kortet kan bruges til at vælge positionen for arbejdet.</p>

<!-- The form posts back to this page.  Use enctype="multipart/form-data" so that file inputs work when we introduce server‑side parsing in the future. -->
<form method="post">
  <div>
    <label for="pdfFile">Upload WO (PDF)</label>
    <input type="file" id="pdfFile" accept="application/pdf">
    <button type="button" id="parseBtn">Parse PDF</button>
  </div>
  <div>
    <label for="work_order_no">Arbejdstilladelse Nr.</label>
    <input type="text" id="work_order_no" name="work_order_no" value="<?php echo htmlspecialchars($current['work_order_no']); ?>">
  </div>
  <div>
    <label for="description">Beskrivelse</label>
    <textarea id="description" name="description"><?php echo htmlspecialchars($current['description']); ?></textarea>
  </div>
  <!-- Additional description tied to the P-number -->
  <div>
    <label for="p_description">Beskrivelse (P-nummer)</label>
    <textarea id="p_description" name="p_description"><?php echo htmlspecialchars($current['p_description']); ?></textarea>
  </div>
  <div>
    <label for="p_number">P-nummer</label>
    <input type="text" id="p_number" name="p_number" value="<?php echo htmlspecialchars($current['p_number']); ?>">
  </div>
  <div>
    <label for="mps_nr">MPS-nr.</label>
    <input type="text" id="mps_nr" name="mps_nr" value="<?php echo htmlspecialchars($current['mps_nr']); ?>">
  </div>
  <div>
    <label for="jobansvarlig">Jobansvarlig</label>
    <input type="text" id="jobansvarlig" name="jobansvarlig" value="<?php echo htmlspecialchars($current['jobansvarlig']); ?>">
  </div>
  <div>
    <label for="telefon">Jobansvarlig telefon</label>
    <input type="tel" id="telefon" name="telefon" value="<?php echo htmlspecialchars($current['telefon']); ?>">
  </div>
  <div>
    <label for="oprettet_af">WO oprettet af</label>
    <input type="text" id="oprettet_af" name="oprettet_af" value="<?php echo htmlspecialchars($current['oprettet_af']); ?>">
  </div>
  <div>
    <label for="oprettet_dato">WO oprettet dato</label>
    <!-- Note: input type="date" expects ISO format (YYYY-MM-DD).  Values that cannot be parsed by browsers will be displayed as blank. -->
    <input type="date" id="oprettet_dato" name="oprettet_dato" value="<?php echo htmlspecialchars($current['oprettet_dato']); ?>">
  </div>
  <div>
    <label for="components">Komponent nr. (én pr. linje)</label>
    <textarea id="components" name="components"><?php echo htmlspecialchars($current['components']); ?></textarea>
  </div>
  <div>
    <label for="entreprenor_firma">Entreprenør</label>
    <input type="text" id="entreprenor_firma" name="entreprenor_firma" value="<?php echo htmlspecialchars($current['entreprenor_firma']); ?>">
  </div>
  <div>
    <label for="entreprenor_kontakt">Entreprenør kontaktperson</label>
    <input type="text" id="entreprenor_kontakt" name="entreprenor_kontakt" value="<?php echo htmlspecialchars($current['entreprenor_kontakt']); ?>">
  </div>
  <div>
    <label for="status">Status</label>
    <select id="status" name="status">
      <option value="planning"  <?php echo ($current['status'] === 'planning')  ? 'selected' : ''; ?>>Planlagt</option>
      <option value="active"    <?php echo ($current['status'] === 'active')    ? 'selected' : ''; ?>>Aktiv</option>
      <option value="completed" <?php echo ($current['status'] === 'completed') ? 'selected' : ''; ?>>Afsluttet</option>
    </select>
  </div>
  <div>
    <label for="map">Placering (klik på kortet for at vælge)</label>
    <div id="map"></div>
    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($current['latitude'] ?: $defaultLat); ?>">
    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($current['longitude'] ?: $defaultLng); ?>">
  </div>
  <div>
    <label for="notes">Bemærkninger</label>
    <textarea id="notes" name="notes"><?php echo htmlspecialchars($current['notes']); ?></textarea>
  </div>
  <button type="submit" name="save_wo">Gem Work Order</button>
</form>

<hr>
<h2>Parse PDF</h2>
<p>Upload en PDF-arbejdsordre og tryk "Parse PDF". Felterne bliver udfyldt automatisk.</p>
<div id="parseLog"></div>

<script>
// Helper: mark fields as found or missing.  When a value is marked as
// missing the existing value is cleared to avoid stale data from
// previous parses or manual edits.
function markField(fieldId, found, value = '') {
  const el = document.getElementById(fieldId) || document.querySelector('[name="' + fieldId + '"]');
  if (!el) return;
  if (found) {
    el.classList.remove('field-missing');
    el.classList.add('field-ok');
    if (value !== undefined && value !== null) el.value = value;
  } else {
    el.classList.remove('field-ok');
    el.classList.add('field-missing');
    el.value = '';
  }
}

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

// PDF parsing logic.  This handler runs when the Parse PDF button is
// clicked.  It uses pdf.js to extract the text from the selected PDF
// and then applies regular expressions to find the various fields.  It
// populates the form and displays a log showing which fields were
// found or missing.  If a field is marked missing, its value is
// cleared.
document.getElementById('parseBtn').addEventListener('click', async function () {
  const fileInput = document.getElementById('pdfFile');
  if (!fileInput || !fileInput.files.length) {
    alert('Vælg venligst en PDF-fil først.');
    return;
  }
  const file = fileInput.files[0];
  if (file.type !== 'application/pdf') {
    alert('Filen skal være en PDF.');
    return;
  }
  const arrayBuffer = await file.arrayBuffer();
  const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
  let rawText = '';
  for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
    const page = await pdf.getPage(pageNum);
    const content = await page.getTextContent();
    rawText += content.items.map(item => item.str).join(' ') + '\n';
  }
  const flat = rawText.replace(/\s+/g, ' ').trim();
  let logLines = [];
  // 1) Work order number (Arbejdstilladelse Nr.).  Try Danish and English labels.
  let arMatch = flat.match(/Arbejdstilladelse\s*Nr\.?\s*([0-9][A-Za-z0-9\-]*)/i);
  let enMatch = null;
  if (!arMatch) enMatch = flat.match(/Work\s*Order\s*(?:No\.?|Number)?\s*([0-9][A-Za-z0-9\-]*)/i);
  const woValue = arMatch ? arMatch[1] : (enMatch ? enMatch[1] : null);
  if (woValue) {
    markField('work_order_no', true, woValue);
    logLines.push('<span class="found">✔ Arbejdstilladelse Nr.: ' + woValue + '</span>');
  } else {
    markField('work_order_no', false);
    logLines.push('<span class="missing">✘ Arbejdstilladelse Nr. IKKE fundet</span>');
  }
  // 2) Beskrivelser: capture multiple "Beskrivelse" sections. The first
  // occurrence is tied to the Work Order description and any second
  // occurrence (after status) is tied to the P‑number description. Use
  // a global regular expression to find all matches.
  const descRegex = /Beskrivelse\s*:?:?\s*(?:P\s*\d+\s*)?(.*?)(?=Jobansvarlig|Komponent|MPS|Entreprenør|Oprettet|WO\s*oprettet|Status|Planning|\b\w+\s*ansvarlig|$)/gi;
  let descMatches = [];
  let m;
  while ((m = descRegex.exec(flat)) !== null) {
    descMatches.push(m[1]);
  }
  if (descMatches.length > 0) {
    // Clean and assign the first description
    let d1 = descMatches[0].replace(/\s*(?:Afhjælpende|Ekstraordinært)\s*\(Z[^)]*\)/gi, '').trim();
    markField('description', true, d1);
    logLines.push('<span class="found">✔ Beskrivelse fundet</span>');
  } else {
    markField('description', false);
    logLines.push('<span class="missing">✘ Beskrivelse IKKE fundet</span>');
  }
  if (descMatches.length > 1) {
    // Assign second description to P-number description
    let d2 = descMatches[1].trim();
    markField('p_description', true, d2);
    logLines.push('<span class="found">✔ P‑nr. beskrivelse fundet</span>');
  } else {
    markField('p_description', false);
    logLines.push('<span class="missing">✘ P‑nr. beskrivelse IKKE fundet</span>');
  }
  // 3) P-nummer
  let pMatch = flat.match(/\bP\s*([0-9]{5,})\b/i);
  if (pMatch) {
    const pNum = 'P' + pMatch[1];
    markField('p_number', true, pNum);
    logLines.push('<span class="found">✔ P-nummer: ' + pNum + '</span>');
  } else {
    markField('p_number', false);
    logLines.push('<span class="missing">✘ P-nummer IKKE fundet</span>');
  }
  // 4) MPS-nummer with validation
  let mpsMatch = flat.match(/MPS[\s-]*nr\.?\s*:??\s*([A-Za-z0-9.\-\/]+)/i);
  if (mpsMatch) {
    markField('mps_nr', true, mpsMatch[1]);
    logLines.push('<span class="found">✔ MPS-nr.: ' + mpsMatch[1] + '</span>');
  } else {
    markField('mps_nr', false);
    logLines.push('<span class="missing">✘ MPS-nr. IKKE fundet</span>');
  }
  // Validate MPS: reject values without a hyphen and fewer than three digits
  try {
    const mpsEl = document.getElementById('mps_nr');
    if (mpsEl && mpsEl.value) {
      const mpsVal = mpsEl.value;
      const digitsOnly = mpsVal.replace(/[^0-9]/g, '');
      if (!mpsVal.includes('-') && digitsOnly.length < 3) {
        markField('mps_nr', false);
        logLines.push('<span class="missing">✘ MPS-nr. IKKE fundet</span>');
      }
    }
  } catch (ex) {}
  // 5) Jobansvarlig and phone
  let jobMatch = flat.match(/Jobansvarlig\s*:?:?\s*([A-Za-zÆØÅæøå.,\-\s]{2,}?)(?=Telefon|Tlf\.?|Mobil|Mob\.?|Email|E-mail|MPS|P\s*\d|Entreprenør|Komponent|Oprettet|Status|Planning|Beskrivelse|$)/i);
  if (jobMatch) {
    let name = jobMatch[1].trim().replace(/[\s,;:.]+$/, '').trim();
    markField('jobansvarlig', true, name);
    logLines.push('<span class="found">✔ Jobansvarlig: ' + name + '</span>');
  } else {
    markField('jobansvarlig', false);
    logLines.push('<span class="missing">✘ Jobansvarlig IKKE fundet</span>');
  }
  let phoneMatch = flat.match(/(?:Telefon|Tlf\.?|Mobil|Mob\.?)\s*[:.]?\s*([0-9\s()+\-]{6,})/i);
  if (phoneMatch) {
    let phoneRaw = phoneMatch[1];
    let phone = phoneRaw.replace(/[^0-9+]/g, '');
    markField('telefon', true, phone);
    logLines.push('<span class="found">✔ Jobansvarlig telefon: ' + phone + '</span>');
  } else {
    markField('telefon', false);
    logLines.push('<span class="missing">✘ Jobansvarlig telefon IKKE fundet</span>');
  }
  // 6) Oprettet af
  let createdByMatch = flat.match(/(?:WO\s*oprettet\s*af|Oprettet\s*af|Created\s*by)\s*:??\s*([A-Za-zÆØÅæøå0-9.,\-\s]{2,}?)(?=\s*(?:WO\s*oprettet|Oprettet\s*dato|Created\s*date|Date|Dato|Komponent|Jobansvarlig|Status|Beskrivelse|$))/i);
  if (createdByMatch) {
    let cb = createdByMatch[1].trim();
    markField('oprettet_af', true, cb);
    logLines.push('<span class="found">✔ Oprettet af: ' + cb + '</span>');
  } else {
    markField('oprettet_af', false);
    logLines.push('<span class="missing">✘ Oprettet af IKKE fundet</span>');
  }
  // 7) Oprettet dato (date) with fallback
  let dateMatch = flat.match(/(?:WO\s*oprettet\s*dato|WO\s*oprettet\s*Dato|Oprettet\s*dato|Created\s*date|Date)\s*:??\s*([0-9]{2}[\/\.\-][0-9]{2}[\/\.\-][0-9]{2,4}|[0-9]{4}[\/\.\-][0-9]{2}[\/\.\-][0-9]{2})/i);
  if (dateMatch) {
    let d = dateMatch[1].trim();
    // Convert dd-mm-yy or dd-mm-yyyy to ISO (YYYY-MM-DD)
    if (/^[0-9]{2}[\/\.\-][0-9]{2}[\/\.\-][0-9]{2}$/.test(d)) {
      const parts = d.split(/[\/\.\-]/);
      let y = parseInt(parts[2], 10);
      y = y < 50 ? 2000 + y : 1900 + y;
      d = `${y}-${parts[1].padStart(2,'0')}-${parts[0].padStart(2,'0')}`;
    } else if (/^[0-9]{2}[\/\.\-][0-9]{2}[\/\.\-][0-9]{4}$/.test(d)) {
      const parts = d.split(/[\/\.\-]/);
      d = `${parts[2]}-${parts[1].padStart(2,'0')}-${parts[0].padStart(2,'0')}`;
    } else if (/^[0-9]{4}[\/\.\-][0-9]{2}[\/\.\-][0-9]{2}$/.test(d)) {
      // Already ISO but ensure proper padding
      const parts = d.split(/[\/\.\-]/);
      d = `${parts[0]}-${parts[1].padStart(2,'0')}-${parts[2].padStart(2,'0')}`;
    }
    markField('oprettet_dato', true, d);
    logLines.push('<span class="found">✔ Oprettet dato: ' + d + '</span>');
  } else {
    markField('oprettet_dato', false);
    logLines.push('<span class="missing">✘ Oprettet dato IKKE fundet</span>');
  }
  // Fallback: if no date found, search for any date pattern in the text
  try {
    const dateField = document.getElementById('oprettet_dato');
    if (dateField && !dateField.value) {
      const fallbackMatch = flat.match(/([0-9]{2}[\/\.\-][0-9]{2}[\/\.\-][0-9]{2,4}|[0-9]{4}[\/\.\-][0-9]{2}[\/\.\-][0-9]{2})/);
      if (fallbackMatch) {
        let fd = fallbackMatch[1] || fallbackMatch[0];
        if (/^[0-9]{2}[\/\.\-][0-9]{2}[\/\.\-][0-9]{2}$/.test(fd)) {
          const parts = fd.split(/[\/\.\-]/);
          let y = parseInt(parts[2], 10);
          y = y < 50 ? 2000 + y : 1900 + y;
          fd = `${y}-${parts[1].padStart(2,'0')}-${parts[0].padStart(2,'0')}`;
        } else if (/^[0-9]{2}[\/\.\-][0-9]{2}[\/\.\-][0-9]{4}$/.test(fd)) {
          const parts = fd.split(/[\/\.\-]/);
          fd = `${parts[2]}-${parts[1].padStart(2,'0')}-${parts[0].padStart(2,'0')}`;
        } else if (/^[0-9]{4}[\/\.\-][0-9]{2}[\/\.\-][0-9]{2}$/.test(fd)) {
          const parts = fd.split(/[\/\.\-]/);
          fd = `${parts[0]}-${parts[1].padStart(2,'0')}-${parts[2].padStart(2,'0')}`;
        }
        markField('oprettet_dato', true, fd);
        logLines.push('<span class="found">✔ Oprettet dato (fallback): ' + fd + '</span>');
      }
    }
  } catch (ex) {
    console.error('Date fallback error', ex);
  }
  // 8) Components
  let compMatch = flat.match(/Komponent\s*nr\.?\s*:??\s*([\s\S]*?)(?=Entreprenør|Jobansvarlig|MPS|P\s*\d|WO\s*oprettet|Oprettet|Status|Planning|Beskrivelse|HSE|Vis\s*instruktioner|Terminal|$)/i);
  if (compMatch) {
    let comps = compMatch[1].trim();
    comps = comps.replace(/\s*[A-ZÆØÅ\s]{3,}\s*$/, '').trim();
    comps = comps.replace(/\s*[,;]\s*/g, '\n');
    markField('components', true, comps);
    logLines.push('<span class="found">✔ Komponent nr. fundet</span>');
  } else {
    markField('components', false);
    logLines.push('<span class="missing">✘ Komponent nr. IKKE fundet</span>');
  }
  // 9) Entreprenør firm name
  let entMatch = flat.match(/Entreprenør\s*:??\s*([\s\S]*?)(?=\s*(?:Entreprenør\s*kontaktperson|Kontaktperson|Jobansvarlig|MPS|P\s*\d|WO\s*oprettet|Oprettet|Komponent|Vis\s*instruktioner|Status|Planning|Beskrivelse|HSE|Terminal|INDLEDENDE|DAGLIG|Daily|$))/i);
  if (entMatch) {
    let ent = entMatch[1].trim();
    const cutTokens = ['Entreprenør kontaktperson','Kontaktperson','Status','Planning','Beskrivelse','Komponent','Vis','INDLEDENDE','DAGLIG','Daily'];
    for (const tok of cutTokens) {
      const idx = ent.toLowerCase().indexOf(tok.toLowerCase());
      if (idx > -1) {
        ent = ent.substring(0, idx).trim();
      }
    }
    ent = ent.replace(/[,:;]+$/,'').trim();
    markField('entreprenor_firma', true, ent);
    logLines.push('<span class="found">✔ Entreprenør: ' + ent + '</span>');
  } else {
    markField('entreprenor_firma', false);
    logLines.push('<span class="missing">✘ Entreprenør IKKE fundet</span>');
  }
  // 10) Entreprenør kontaktperson
  let contactMatch = flat.match(/Entreprenør\s*kontaktperson\s*:??\s*([A-Za-zÆØÅæøå.,\-\s]{2,}?)(?=Telefon|Tlf\.?|Mobil|Mob\.?|Email|E-mail|Jobansvarlig|MPS|P\s*\d|WO\s*oprettet|Oprettet|Komponent|Vis\s*instruktioner|Status|Planning|Beskrivelse|HSE|Terminal|$)/i);
  if (contactMatch) {
    let cp = contactMatch[1].trim();
    if (/^(status|planning)/i.test(cp)) {
      markField('entreprenor_kontakt', false);
      logLines.push('<span class="missing">✘ Entreprenør kontaktperson IKKE fundet</span>');
    } else {
      markField('entreprenor_kontakt', true, cp);
      logLines.push('<span class="found">✔ Entreprenør kontaktperson: ' + cp + '</span>');
    }
  } else {
    markField('entreprenor_kontakt', false);
    logLines.push('<span class="missing">✘ Entreprenør kontaktperson IKKE fundet</span>');
  }
  // Display the parse log
  document.getElementById('parseLog').innerHTML = logLines.join('\n');
});
</script>
    </div><!-- /.container -->
</body>
</html>