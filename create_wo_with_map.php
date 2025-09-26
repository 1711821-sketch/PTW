<?php
// ---------------------------------------------------------------------
// create_wo_with_map.php
//
// This simplified work order creation page demonstrates how to combine
// the PDF parsing and field-highlighting logic from our earlier
// prototype with a basic map for selecting the job location.  Unlike
// the original `create_wo.php` in your project, this file does not
// interact with JSON storage or enforce login.  Instead it stores
// field values in the PHP session so you can test functionality
// without database dependencies.  When integrating back into your
// production system, merge the form markup and JavaScript into your
// existing create/edit page rather than replacing it entirely.

session_start();

// Load existing values from the session if present.  This allows you
// to reload the page and retain values that were previously parsed or
// entered by hand.  If no session data exists, initialise an empty
// array with sensible defaults for each field.  Additional fields can
// be added here if desired (e.g. job responsible, components list).
$current = isset($_SESSION['current_wo']) ? $_SESSION['current_wo'] : [
    'wo_number'           => '',
    'description'         => '',
    'p_number'            => '',
    'mps_nr'              => '',
    'responsible'         => '',
    'entreprenor_firma'   => '',
    'entreprenor_kontakt' => '',
    'status'              => 'planning',
    'komponent'           => '',
    'notes'               => '',
    'latitude'            => '',
    'longitude'           => ''
];

// When the user clicks "Gem WO" the form posts back to this page.
// Collect all fields from $_POST, sanitise and save into the session.
if (isset($_POST['save_wo'])) {
    $fields = [
        'wo_number'           => trim($_POST['wo_number'] ?? ''),
        'description'         => trim($_POST['description'] ?? ''),
        'p_number'            => trim($_POST['p_number'] ?? ''),
        'mps_nr'              => trim($_POST['mps_nr'] ?? ''),
        'responsible'         => trim($_POST['responsible'] ?? ''),
        'entreprenor_firma'   => trim($_POST['entreprenor_firma'] ?? ''),
        'entreprenor_kontakt' => trim($_POST['entreprenor_kontakt'] ?? ''),
        'status'              => trim($_POST['status'] ?? 'planning'),
        'komponent'           => trim($_POST['komponent'] ?? ''),
        'notes'               => trim($_POST['notes'] ?? ''),
        // Capture the hidden latitude/longitude values from the map
        'latitude'            => trim($_POST['latitude'] ?? ''),
        'longitude'           => trim($_POST['longitude'] ?? '')
    ];
    // Remove the "Afhjælpende (ZCOR)" marker from the description if it
    // slipped through.  Leading whitespace is captured to prevent double
    // spaces after removal and the description is normalised to single
    // spaces.
    if (!empty($fields['description'])) {
        $fields['description'] = preg_replace('/\s*Afhjælpende\s*\(ZCOR\)/i', '', $fields['description']);
        $fields['description'] = trim(preg_replace('/\s+/', ' ', $fields['description']));
    }
    $current = $fields;
    $_SESSION['current_wo'] = $fields;
}

// Default map centre if no coordinates are stored.  These values
// correspond to the Stigsnæs Gulfhavn Olie Terminal used in your
// existing system.  Change them to suit your environment.  When the
// page loads with stored latitude/longitude, those values override
// these defaults.
$defaultLat = 55.19952;
$defaultLng = 11.25736;
?>
<!DOCTYPE html>
<html lang="da">
<head>
<meta charset="UTF-8">
<title>Opret WO med kort</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.14.305/pdf.min.js"></script>
<!-- Leaflet CSS/JS for the interactive map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
  body { font-family: Arial, sans-serif; margin: 20px; }
  label { display:block; margin-top:0.5em; font-weight:bold; }
  input, textarea, select { width:100%; padding:0.3em; margin-top:0.2em; }
  .field-ok {
    border: 2px solid green !important;
    background: #f6fff6 url('data:image/svg+xml;utf8,✔') no-repeat right 8px center;
    background-size: 14px;
  }
  .field-missing {
    border: 2px solid red !important;
    background: #fff6f6 url('data:image/svg+xml;utf8,✘') no-repeat right 8px center;
    background-size: 14px;
  }
  .found { color: green; }
  .missing { color: red; }
  #map { height: 300px; margin-top: 10px; }
</style>
</head>
<body>
<h1>Opret Work Order</h1>

<form method="post">
  <label for="wo_number">WO nummer</label>
  <input type="text" id="wo_number" name="wo_number" value="<?php echo htmlspecialchars($current['wo_number'] ?? ''); ?>">

  <label for="description">Beskrivelse</label>
  <textarea id="description" name="description" rows="2"><?php echo htmlspecialchars($current['description'] ?? ''); ?></textarea>

  <label for="p_number">P-nummer</label>
  <input type="text" id="p_number" name="p_number" value="<?php echo htmlspecialchars($current['p_number'] ?? ''); ?>">

  <label for="mps_nr">MPS-nr.</label>
  <input type="text" id="mps_nr" name="mps_nr" value="<?php echo htmlspecialchars($current['mps_nr'] ?? ''); ?>">

  <label for="responsible">Jobansvarlig</label>
  <input type="text" id="responsible" name="responsible" value="<?php echo htmlspecialchars($current['responsible'] ?? ''); ?>">

  <label for="entreprenor_firma">Entreprenør</label>
  <input type="text" id="entreprenor_firma" name="entreprenor_firma" value="<?php echo htmlspecialchars($current['entreprenor_firma'] ?? ''); ?>">

  <label for="entreprenor_kontakt">Entreprenør kontaktperson</label>
  <input type="text" id="entreprenor_kontakt" name="entreprenor_kontakt" value="<?php echo htmlspecialchars($current['entreprenor_kontakt'] ?? ''); ?>">

  <label for="status">Status</label>
  <select id="status" name="status">
    <option value="planning"  <?php echo (($current['status'] ?? '')==='planning')?'selected':''; ?>>Planlagt</option>
    <option value="active"    <?php echo (($current['status'] ?? '')==='active')?'selected':''; ?>>Aktiv</option>
    <option value="completed" <?php echo (($current['status'] ?? '')==='completed')?'selected':''; ?>>Afsluttet</option>
  </select>

  <label for="komponent">Komponent nr.</label>
  <input type="text" id="komponent" name="komponent" value="<?php echo htmlspecialchars($current['komponent'] ?? ''); ?>">

  <label for="notes">Bemærkninger</label>
  <textarea id="notes" name="notes" rows="3"><?php echo htmlspecialchars($current['notes'] ?? ''); ?></textarea>

  <!-- Map for selecting the job location -->
  <label>Arbejdssted (klik på kortet for at vælge)</label>
  <div id="map"></div>
  <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($current['latitude'] ?? ''); ?>">
  <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($current['longitude'] ?? ''); ?>">

  <button type="submit" name="save_wo">Gem WO</button>
</form>

<hr>
<h2>Parse PDF</h2>
<input type="file" id="pdfFile">
<button type="button" id="parseBtn">Parse PDF</button>

<!-- Log area shows which fields were extracted from the PDF.  Lines marked
     with a green check were found; lines in red were missing. -->
<div id="parseLog" style="margin-top:1em; padding:0.5em; border:1px solid #ccc; background:#f9f9f9; font-family:monospace; white-space:pre-wrap;"></div>

<script>
// Initialise the Leaflet map.  Use the stored coordinates if available,
// otherwise fall back to the default centre defined in PHP.  This map
// includes a click handler to place a marker and update hidden form
// fields for latitude and longitude.
var initLat = <?php echo ($current['latitude'] !== '') ? json_encode($current['latitude']) : $defaultLat; ?>;
var initLng = <?php echo ($current['longitude'] !== '') ? json_encode($current['longitude']) : $defaultLng; ?>;
var initZoom = (<?php echo ($current['latitude'] !== '') && ($current['longitude'] !== '') ? 'true' : 'false'; ?>) ? 16 : 15;
var map = L.map('map').setView([initLat, initLng], initZoom);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);
var marker = null;
function setMarker(lat, lng) {
    if (marker) { map.removeLayer(marker); }
    marker = L.marker([lat, lng]).addTo(map);
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
}
// If lat/long values are present, show the marker
if (<?php echo ($current['latitude'] !== '' && $current['longitude'] !== '') ? 'true' : 'false'; ?>) {
    setMarker(initLat, initLng);
}
map.on('click', function(e) {
    setMarker(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6));
});

// Utility to mark a form field as found or missing.  When found, it adds
// a green border and updates the value if provided.  When missing, a
// red border is applied.  The field must already exist in the DOM.
function markField(fieldId, found, value="") {
  var el = document.getElementById(fieldId) || document.querySelector('[name="' + fieldId + '"]');
  if (el) {
    if (found) {
      el.classList.remove("field-missing");
      el.classList.add("field-ok");
      if (value !== undefined && value !== null && value !== '') el.value = value;
    } else {
      el.classList.remove("field-ok");
      el.classList.add("field-missing");
    }
  }
}

// PDF parsing and field extraction.  When the user clicks "Parse PDF"
// this function reads the uploaded PDF client-side using pdf.js and
// attempts to extract relevant fields: description, P number, MPS number
// and contractor details.  A log is printed in the parseLog div and
// form fields are updated accordingly.
document.getElementById('parseBtn').addEventListener('click', async () => {
  var fileInput = document.getElementById('pdfFile');
  if (!fileInput || !fileInput.files.length) return;
  var file = fileInput.files[0];
  var arrayBuffer = await file.arrayBuffer();
  var pdf = await pdfjsLib.getDocument({data: arrayBuffer}).promise;
  var textContent = '';
  for (let i=1; i<=pdf.numPages; i++) {
    var page = await pdf.getPage(i);
    var txt  = await page.getTextContent();
    textContent += txt.items.map(t => t.str).join(' ') + '\n';
  }
  var flat = textContent.replace(/\s+/g, ' ');
  var logHtml = [];
  // Extract description: capture text after 'Beskrivelse' until next header
  var descMatch = textContent.match(/Beskrivelse\s+([\s\S]+?)(Jobansvarlig|MPS|Komponent|Status|WO oprettet|Sikringsliste|INDLEDENDE)/i);
  if (descMatch) {
    var desc = descMatch[1].replace(/\s+/g, ' ').trim().replace(/\s*Afhjælpende\s*\(ZCOR\)/i, '').trim();
    markField('description', true, desc);
    logHtml.push('<span class="found">✔ Beskrivelse fundet</span>');
  } else {
    markField('description', false);
    logHtml.push('<span class="missing">✘ Beskrivelse IKKE fundet</span>');
  }
  // P number: look for "P" followed by digits
  var pMatch = flat.match(/\bP\s*([0-9]{4,})\b/i);
  if (pMatch) {
    markField('p_number', true, 'P' + pMatch[1]);
    logHtml.push('<span class="found">✔ P-nummer: P' + pMatch[1] + '</span>');
  } else {
    markField('p_number', false);
    logHtml.push('<span class="missing">✘ P-nummer IKKE fundet</span>');
  }
  // MPS number: handle variations like "MPS-nr." or "MPS nr"
  var mpsMatch = flat.match(/MPS-?nr\.?\s*:?\s*([A-Za-z0-9\.\-\/]+)/i);
  if (mpsMatch) {
    var mpsVal = mpsMatch[1];
    markField('mps_nr', true, mpsVal);
    logHtml.push('<span class="found">✔ MPS-nr.: ' + mpsVal + '</span>');
  } else {
    markField('mps_nr', false);
    logHtml.push('<span class="missing">✘ MPS-nr. IKKE fundet</span>');
  }
  // Entreprenør (company) and contact person.  Try simple regexes to pick up
  // lines containing "Entreprenør" and "Entreprenør kontaktperson".  These
  // may need adjustment depending on your PDF layout.
  var entFirmaMatch = flat.match(/Entreprenør\s+([^\n\r]+?)\s+Entreprenør\s*kontaktperson/i);
  if (entFirmaMatch) {
    var firma = entFirmaMatch[1].trim();
    markField('entreprenor_firma', true, firma);
    logHtml.push('<span class="found">✔ Entreprenør: ' + firma + '</span>');
  } else {
    markField('entreprenor_firma', false);
    logHtml.push('<span class="missing">✘ Entreprenør IKKE fundet</span>');
  }
  var entKontaktMatch = flat.match(/Entreprenør\s*kontaktperson\s*([A-Za-zæøåÆØÅ\.\-]+)/i);
  if (entKontaktMatch) {
    var kontakt = entKontaktMatch[1].trim();
    markField('entreprenor_kontakt', true, kontakt);
    logHtml.push('<span class="found">✔ Entreprenør kontaktperson: ' + kontakt + '</span>');
  } else {
    markField('entreprenor_kontakt', false);
    logHtml.push('<span class="missing">✘ Entreprenør kontaktperson IKKE fundet</span>');
  }
  // Display the parse log
  document.getElementById('parseLog').innerHTML = logHtml.join('<br>');
});

// Remove the red mark when typing manually.  If a user enters a value
// into a previously missing field, we flip it to the green style.
document.querySelectorAll('input, textarea').forEach(function(el) {
  el.addEventListener('input', function() {
    if (el.value.trim() !== '') {
      el.classList.remove('field-missing');
      if (!el.classList.contains('field-ok')) {
        el.classList.add('field-ok');
      }
    } else {
      el.classList.remove('field-ok');
      el.classList.add('field-missing');
    }
  });
});
</script>
</body>
</html>