<?php
// ---------------------------------------------------------------------------
// map_wo.php
//
// Displays an overview map of all work orders (WO) using Leaflet.  Each WO
// with valid latitude and longitude appears as a marker on the map.  The
// markers are coloured based on the WO's status (green for active or
// planning, blue for completed).  A search box allows filtering markers
// by description, job responsible, contractor firm or contact.  Checkboxes
// control whether active or completed markers are shown.
//
// Entrepreneurs should only see work orders belonging to their own
// contractor firm.  The contractor firm is stored in $_SESSION['entreprenor_firma']
// during login.  When the logged-in role is 'entreprenor', the list of
// work orders is filtered accordingly.
//
// Only authenticated users may access this page.  If no user is logged in,
// the user is redirected to the login page.
// ---------------------------------------------------------------------------

session_start();

// Redirect unauthenticated users to login
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Determine the role of the current user
$role = $_SESSION['role'] ?? '';

// Load work orders from JSON file
$entries = [];
$data_file = __DIR__ . '/wo_data.json';
if (file_exists($data_file)) {
    $data = json_decode(file_get_contents($data_file), true);
    if (is_array($data)) {
        $entries = $data;
    }
}

// If the user is an entrepreneur, restrict entries to their own firm
if (strtolower($role) === 'entreprenor') {
    $firma = $_SESSION['entreprenor_firma'] ?? '';
    if ($firma !== '') {
        $entries = array_filter($entries, function ($e) use ($firma) {
            return isset($e['entreprenor_firma']) && $e['entreprenor_firma'] === $firma;
        });
        $entries = array_values($entries);
    } else {
        // If no firm is defined, show nothing
        $entries = [];
    }
}

?><!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oversigtskort over WO</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        body { margin: 0; font-family: Arial, sans-serif; }
        #map { width: 100%; height: 80vh; }
        .controls { padding: 0.5rem; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .controls input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .controls label { margin-left: 0.5rem; }
        a.back { display: inline-block; margin: 1rem; color: #0070C0; text-decoration: none; }
    </style>
</head>
<body>
    <div class="controls">
        <input type="text" id="search" placeholder="Søg i beskrivelse eller jobansvarlig…">
        <label><input type="checkbox" id="showPlanning" checked> Planlagte</label>
        <label><input type="checkbox" id="showActive" checked> Aktive</label>
        <label><input type="checkbox" id="showCompleted" checked> Afsluttede</label>
    </div>
    <div id="map"></div>
    <a class="back" href="index.php">Tilbage til forsiden</a>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    // Initialise the list of entries passed from PHP.  JSON_UNESCAPED_UNICODE
    // ensures that Danish characters are output correctly.
    var entries = <?php echo json_encode($entries, JSON_UNESCAPED_UNICODE); ?>;

    // Create the map centred on Stigsnæs Gulfhavn Olie Terminal.  Adjust the
    // zoom level to show enough detail.
    var map = L.map('map').setView([55.205903, 11.264111], 15);

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // Define custom marker icons for planning (blue), active (green) and completed (grey)
    var greenIcon = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });
    var blueIcon = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });
    var grayIcon = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-grey.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    var allMarkers = [];
    var planningMarkers = [];
    var activeMarkers = [];
    var completedMarkers = [];

    // Build markers for a list of entries.  Removes any existing markers from the
    // map and then adds new markers based on the entries provided.
    function buildMarkers(list) {
        // Remove all current markers from the map
        allMarkers.forEach(function(m) { map.removeLayer(m); });
        allMarkers = [];
        planningMarkers = [];
        activeMarkers = [];
        completedMarkers = [];
        list.forEach(function(e) {
            var lat = e.latitude;
            var lng = e.longitude;
            if (lat && lng) {
                var status = e.status ? e.status : 'planning';
                var icon;
                if (status === 'planning') {
                    icon = blueIcon;
                } else if (status === 'active') {
                    icon = greenIcon;
                } else {
                    icon = grayIcon;
                }
                var marker = L.marker([parseFloat(lat), parseFloat(lng)], { icon: icon });
                var popup = '<strong>WO Nr: ' + (e.work_order_no || '') + '</strong><br>' +
                    'Beskrivelse: ' + (e.description || '') + '<br>' +
                    'Entreprenør: ' + (e.entreprenor_firma || '') +
                    (e.entreprenor_kontakt ? ' (' + e.entreprenor_kontakt + ')' : '') + '<br>' +
                    '<a href="print_wo.php?id=' + encodeURIComponent(e.id) + '" target="_blank">Åbn WO</a>';
                marker.bindPopup(popup);
                allMarkers.push(marker);
                if (status === 'planning') {
                    planningMarkers.push(marker);
                } else if (status === 'active') {
                    activeMarkers.push(marker);
                } else {
                    completedMarkers.push(marker);
                }
            }
        });
    }

    // Update which markers are shown on the map based on the active/completed
    // checkboxes.  Called after buildMarkers and whenever the checkboxes change.
    function updateMapMarkers() {
        allMarkers.forEach(function(m) { map.removeLayer(m); });
        var showPlanning = document.getElementById('showPlanning').checked;
        var showActive = document.getElementById('showActive').checked;
        var showCompleted = document.getElementById('showCompleted').checked;
        if (showPlanning) {
            planningMarkers.forEach(function(m) { m.addTo(map); });
        }
        if (showActive) {
            activeMarkers.forEach(function(m) { m.addTo(map); });
        }
        if (showCompleted) {
            completedMarkers.forEach(function(m) { m.addTo(map); });
        }
    }

    // Build the initial set of markers and add them to the map
    buildMarkers(entries);
    updateMapMarkers();

    // Filter markers based on search input.  Matches against description,
    // job responsible, contractor firm and contact person fields.
    document.getElementById('search').addEventListener('input', function() {
        var term = this.value.toLowerCase();
        var filtered = entries.filter(function(e) {
            var desc = (e.description || '').toLowerCase();
            var job  = (e.jobansvarlig || '').toLowerCase();
            var firm = (e.entreprenor_firma || '').toLowerCase();
            var kont = (e.entreprenor_kontakt || '').toLowerCase();
            return desc.indexOf(term) !== -1 || job.indexOf(term) !== -1 || firm.indexOf(term) !== -1 || kont.indexOf(term) !== -1;
        });
        buildMarkers(filtered);
        updateMapMarkers();
    });

    // Toggle marker visibility when status checkboxes change
    document.getElementById('showPlanning').addEventListener('change', updateMapMarkers);
    document.getElementById('showActive').addEventListener('change', updateMapMarkers);
    document.getElementById('showCompleted').addEventListener('change', updateMapMarkers);
    </script>
</body>
</html>