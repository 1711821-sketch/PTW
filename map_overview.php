<?php
// Map overview page to display all SJA entries on an interactive map.
// Only logged-in users can access this page.

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$data_file = __DIR__ . '/sja_data.json';
$entries = [];
if (file_exists($data_file)) {
    $data = json_decode(file_get_contents($data_file), true);
    if (is_array($data)) {
        $entries = $data;
    }
}

?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Oversigtskort over SJA</title>
    <?php include 'pwa-head.php'; ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { margin:0; font-family: Arial, sans-serif; }
        #map { width: 100%; height: 80vh; }
        .controls { padding: 0.5rem; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .controls input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        a.back { display: inline-block; margin: 1rem; color: #0070C0; text-decoration: none; }
    </style>
</head>
<body>
    <div class="controls">
        <input type="text" id="search" placeholder="Søg opgave…" />
        <!-- Status filter checkboxes -->
        <label style="margin-left:0.5rem;"><input type="checkbox" id="showActive" checked> Aktive</label>
        <label style="margin-left:0.5rem;"><input type="checkbox" id="showCompleted" checked> Afsluttede</label>
    </div>
    <div id="map"></div>
    <a class="back" href="index.php">Tilbage til forsiden</a>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Load entries from PHP into a JS variable
        var entries = <?php echo json_encode($entries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        // Initialize map centered over Stigsnæs Gulfhavn Olie Terminal.  Since
        // the majority of tasks take place within this industrial area, it
        // makes sense to start the overview map here.  The coordinates are
        // approximately 55.19952 N latitude and 11.25736 E longitude【388675536267527†L64-L70】.
        var map = L.map('map').setView([55.19952, 11.25736], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);
        // Define coloured marker icons for active (green) and completed (blue)
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
        var allMarkers = [];
        var activeMarkers = [];
        var completedMarkers = [];
        // Build markers from a list of entries and store them by status
        function buildMarkers(list) {
            // Remove previous markers from map
            allMarkers.forEach(function(m) { map.removeLayer(m); });
            allMarkers = [];
            activeMarkers = [];
            completedMarkers = [];
            list.forEach(function(e) {
                var lat = e.latitude;
                var lng = e.longitude;
                if (lat && lng) {
                    var status = e.status ? e.status : 'active';
                    var markerOptions = { icon: (status === 'completed') ? blueIcon : greenIcon };
                    var marker = L.marker([parseFloat(lat), parseFloat(lng)], markerOptions);
                    var opgave = e.basic && e.basic.opgave ? e.basic.opgave : '';
                    var dato = e.basic && e.basic.dato_udfoer ? e.basic.dato_udfoer : '';
                    var popup = '<strong>' + opgave + '</strong><br>Dato: ' + dato + '<br>' +
                        '<a href="print_sja.php?id=' + encodeURIComponent(e.id) + '" target="_blank">Åbn SJA</a>';
                    marker.bindPopup(popup);
                    allMarkers.push(marker);
                    if (status === 'completed') {
                        completedMarkers.push(marker);
                    } else {
                        activeMarkers.push(marker);
                    }
                }
            });
        }
        // Function to add markers to map according to filters
        function updateMapMarkers() {
            // Remove all markers first
            allMarkers.forEach(function(m) { map.removeLayer(m); });
            var showActive = document.getElementById('showActive').checked;
            var showCompleted = document.getElementById('showCompleted').checked;
            // Add based on filter
            if (showActive) {
                activeMarkers.forEach(function(m) { m.addTo(map); });
            }
            if (showCompleted) {
                completedMarkers.forEach(function(m) { m.addTo(map); });
            }
        }
        // Build initial markers
        buildMarkers(entries);
        updateMapMarkers();
        // Handle search and rebuild marker lists
        document.getElementById('search').addEventListener('input', function() {
            var term = this.value.toLowerCase();
            var filtered = entries.filter(function(e) {
                var opgave = e.basic && e.basic.opgave ? e.basic.opgave.toLowerCase() : '';
                return opgave.indexOf(term) !== -1;
            });
            buildMarkers(filtered);
            updateMapMarkers();
        });
        // Add listeners for status checkboxes
        document.getElementById('showActive').addEventListener('change', updateMapMarkers);
        document.getElementById('showCompleted').addEventListener('change', updateMapMarkers);
    </script>
</body>
</html>