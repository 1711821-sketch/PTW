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

// Set Danish timezone
date_default_timezone_set('Europe/Copenhagen');

// Redirect unauthenticated users to login
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Determine the role of the current user
$role = $_SESSION['role'] ?? '';

// Include database connection
require_once 'database.php';

// Load work orders from PostgreSQL database
$db = Database::getInstance();
$entries = $db->fetchAll("SELECT * FROM work_orders ORDER BY created_at DESC");

// If the user is an entrepreneur, restrict entries to their own firm
// and only show work orders that are active and approved today
if (strtolower($role) === 'entreprenor') {
    $firma = $_SESSION['entreprenor_firma'] ?? '';
    if ($firma !== '') {
        $today = date('d-m-Y');
        // Only load work orders for this entrepreneur's firm that are:
        // 1. In 'active' status
        // 2. Approved by both opgaveansvarlig and drift TODAY
        $entries = $db->fetchAll(
            "SELECT * FROM work_orders 
             WHERE entreprenor_firma = ? 
             AND status = 'active'
             AND approvals::jsonb->>'opgaveansvarlig' = ?
             AND approvals::jsonb->>'drift' = ?
             ORDER BY created_at DESC", 
            [$firma, $today, $today]
        );
        
        error_log("MAP - Entrepreneur: " . $_SESSION['user'] . ", Firma: $firma, Today: $today, Count: " . count($entries));
    } else {
        // If no firm is defined, show nothing
        $entries = [];
        error_log("MAP - Entrepreneur: " . $_SESSION['user'] . ", No firma defined");
    }
}

// Get list of work order IDs that have associated SJAs
$workOrdersWithSJA = [];
try {
    $sjaResults = $db->fetchAll("SELECT DISTINCT work_order_id FROM sja_entries WHERE work_order_id IS NOT NULL");
    $workOrdersWithSJA = array_column($sjaResults, 'work_order_id');
} catch (Exception $e) {
    // If SJA table doesn't exist or query fails, continue without SJA data
    $workOrdersWithSJA = [];
}

?><!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Oversigtskort over WO</title>
    <?php include 'pwa-head.php'; ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .map-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            background: var(--background-primary);
        }
        
        .map-controls {
            background: var(--background-primary);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-light);
            box-shadow: var(--shadow-sm);
        }
        
        .search-box {
            width: 100%;
            max-width: 400px;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            transition: var(--transition);
            background: var(--background-primary);
            color: var(--text-primary);
        }
        
        .search-box:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        
        .filter-controls {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius-md);
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid var(--border-light);
            transition: var(--transition);
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .filter-option:hover {
            background: rgba(59, 130, 246, 0.05);
            transform: translateY(-1px);
        }
        
        .filter-option input[type="checkbox"] {
            margin: 0;
            width: 16px;
            height: 16px;
        }

        /* Custom marker styles for SJA indicators with black circles */
        div.custom-marker-black {
            width: 25px !important;
            height: 41px !important;
            position: relative !important;
            background-size: 25px 41px !important;
            background-repeat: no-repeat !important;
            background-position: center !important;
        }
        
        div.custom-marker-black::before {
            content: '●' !important;
            position: absolute !important;
            top: 2px !important;
            right: 2px !important;
            font-size: 12px !important;
            color: #000 !important;
            text-shadow: 0 0 2px #fff, 0 0 4px #fff !important;
            z-index: 1000 !important;
            display: block !important;
        }

        div.marker-green-black {
            background-image: url('https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png') !important;
        }

        div.marker-blue-black {
            background-image: url('https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png') !important;
        }

        div.marker-gray-black {
            background-image: url('https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-grey.png') !important;
        }
        
        /* Work status indicator (added via JavaScript content) */
        div.marker-work-status::after {
            position: absolute !important;
            top: 1px !important;
            left: 1px !important;
            font-size: 15px !important;
            text-shadow: 0 0 3px #fff, 0 0 6px #fff !important;
            z-index: 1000 !important;
            display: block !important;
        }
        
        /* Pulsating animation for active work markers */
        @keyframes markerPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7);
            }
            50% {
                box-shadow: 0 0 0 8px rgba(76, 175, 80, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0);
            }
        }
        
        /* Apply pulsating effect to markers with active work status */
        div.marker-working-pulse {
            animation: markerPulse 2s ease-out infinite !important;
            border-radius: 50% !important;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-weight: 500;
        }
        
        .status-indicator.planning { color: var(--primary-color); }
        .status-indicator.active { color: var(--secondary-color); }
        .status-indicator.completed { color: var(--text-secondary); }
        
        #map {
            flex: 1;
            min-height: 0;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        
        .map-info {
            position: absolute;
            bottom: 1rem;
            right: 1rem;
            background: var(--background-primary);
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            font-size: 0.875rem;
            color: var(--text-secondary);
            z-index: 1000;
        }
        
        /* Custom popup styling */
        .leaflet-popup-content-wrapper {
            border-radius: var(--radius-lg) !important;
            box-shadow: var(--shadow-lg) !important;
            border: 1px solid var(--border-light) !important;
        }
        
        .leaflet-popup-content {
            margin: 0 !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }
        
        .wo-popup {
            padding: 1.5rem;
            min-width: 280px;
        }
        
        .wo-popup-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-light);
        }
        
        .wo-popup-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        
        .wo-popup-status {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .wo-popup-status.planning {
            background: rgba(30, 64, 175, 0.1);
            color: var(--primary-color);
        }
        
        .wo-popup-status.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary-color);
        }
        
        .wo-popup-status.completed {
            background: rgba(107, 114, 128, 0.1);
            color: var(--text-secondary);
        }
        
        .wo-popup-details {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .wo-popup-detail {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .wo-popup-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            min-width: 80px;
            flex-shrink: 0;
        }
        
        .wo-popup-value {
            font-size: 0.875rem;
            color: var(--text-primary);
            line-height: 1.4;
        }
        
        .wo-popup-actions {
            display: flex;
            gap: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-light);
        }
        
        .wo-popup-btn {
            flex: 1;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .wo-popup-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
            color: #fff;
            text-decoration: none;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .map-controls {
                padding: 1rem;
            }
            
            .filter-controls {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }
            
            .filter-option {
                justify-content: center;
            }
            
            .map-info {
                position: relative;
                top: auto;
                right: auto;
                margin: 1rem;
            }
            
            .wo-popup {
                min-width: 250px;
                padding: 1rem;
            }
        }
    </style>
    <script src="navigation.js"></script>
</head>
<body>
    <!-- Modern navigation bar with hamburger menu -->
    <nav class="navbar">
        <button class="navbar-toggle" aria-label="Toggle navigation">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
        <div class="navbar-links">
            <a href="view_wo.php">🔍 PTW-oversigt</a>
            <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
                <a href="create_wo.php">➕ Opret ny PTW</a>
            <?php endif; ?>
            <a href="map_wo.php" style="background: rgba(255, 255, 255, 0.15); border-radius: var(--radius-md);">🗺️ Kort</a>
            <a href="dashboard.php">📊 Dashboard</a>
            <?php if ($role === 'admin'): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <span class="nav-user">Logget ind som <?php echo htmlspecialchars($_SESSION['user'] ?? ''); ?> (<?php echo htmlspecialchars($role ?? ''); ?>)</span>
            <a class="logout-link" href="logout.php">Log ud</a>
        </div>
    </nav>
    
    <div class="map-container">
        <!-- Modern control panel -->
        <div class="map-controls">
            <!-- Search and legend row -->
            <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem; flex-wrap: wrap;">
                <input type="text" id="search" class="search-box" placeholder="🔍 Søg i beskrivelse, jobansvarlig, entreprenør..." style="margin-bottom: 0; flex: 1; min-width: 200px;">
                <div style="padding: 0.5rem 0.75rem; background: rgba(59, 130, 246, 0.05); border: 1px solid var(--border-light); border-radius: var(--radius-md); font-size: 0.8rem; color: var(--text-secondary); white-space: nowrap;">
                    <strong style="color: var(--text-primary);">Forklaring:</strong> ● = SJA tilknyttet | 🔨 = Arbejder | ⏹️ = Stoppet
                </div>
            </div>
            
            <div class="filter-controls">
                <label class="filter-option" for="showPlanning">
                    <input type="checkbox" id="showPlanning" checked>
                    <span class="status-indicator planning">📋 Planlagte</span>
                </label>
                <label class="filter-option" for="showActive">
                    <input type="checkbox" id="showActive" checked>
                    <span class="status-indicator active">🔥 Aktive</span>
                </label>
                <label class="filter-option" for="showCompleted">
                    <input type="checkbox" id="showCompleted" checked>
                    <span class="status-indicator completed">✅ Afsluttede</span>
                </label>
            </div>
        </div>
        
        <!-- Map container -->
        <div style="position: relative; flex: 1;">
            <div id="map"></div>
            <div class="map-info" id="mapInfo">
                <span id="markerCount">Indlæser...</span>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    // Initialise the list of entries passed from PHP.  JSON_UNESCAPED_UNICODE
    // ensures that Danish characters are output correctly.
    var entries = <?php echo json_encode($entries, JSON_UNESCAPED_UNICODE); ?>;
    
    // List of work order IDs that have associated SJAs
    var workOrdersWithSJA = <?php echo json_encode($workOrdersWithSJA); ?>;
    

    // Create the map centred on Stigsnæs Gulfhavn Olie Terminal.  Adjust the
    // zoom level to show enough detail.
    var map = L.map('map').setView([55.205903, 11.264111], 15);

    // Define base layers
    var baseLayers = {
        "OpenStreetMap": L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        })
    };

    // Zoneklassifikationskort som overlay
    var zoneOverlay = L.imageOverlay(
        'assets/maps/zoneplan_sgot.png',
        [[55.200, 11.258], [55.212, 11.270]],  // Bounds centered on SGOT terminal - adjust as needed
        { 
            opacity: 0.65, 
            interactive: false 
        }
    );

    // Define overlay layers
    var overlays = {
        "Zoneklassifikationsplan": zoneOverlay
    };

    // Add layer control
    L.control.layers(baseLayers, overlays, { 
        collapsed: true,
        position: 'topright'
    }).addTo(map);

    // Add OpenStreetMap as default base layer
    baseLayers["OpenStreetMap"].addTo(map);

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

    // Define custom DivIcon markers with black circles for SJA indicators
    var greenIconBlack = L.divIcon({
        className: 'custom-marker-black marker-green-black',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34]
    });
    
    var blueIconBlack = L.divIcon({
        className: 'custom-marker-black marker-blue-black',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34]
    });
    
    var grayIconBlack = L.divIcon({
        className: 'custom-marker-black marker-gray-black',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34]
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
                
                // Check if this work order has SJA
                var hasSJA = workOrdersWithSJA.includes(e.id);
                
                // Parse work status for today
                var workStatus = null;
                var workStatusIcon = '';
                // Format date as dd-mm-yyyy to match PHP format
                var now = new Date();
                var today = ('0' + now.getDate()).slice(-2) + '-' + ('0' + (now.getMonth() + 1)).slice(-2) + '-' + now.getFullYear();
                
                try {
                    if (e.daily_work_status) {
                        var dailyStatus = typeof e.daily_work_status === 'string' 
                            ? JSON.parse(e.daily_work_status) 
                            : e.daily_work_status;
                        
                        if (dailyStatus.date === today) {
                            workStatus = dailyStatus.status;
                            workStatusIcon = dailyStatus.status === 'working' ? '🔨' : '⏹️';
                        }
                    }
                } catch (e) {
                    // Ignore JSON parse errors
                }
                
                // Choose icon based on status, SJA presence, and work status
                var icon;
                
                // If work status exists, use custom divIcon with work status
                if (workStatus) {
                    var className = 'custom-marker-black marker-work-status';
                    if (status === 'planning') {
                        className += ' marker-blue-black';
                    } else if (status === 'active') {
                        className += ' marker-green-black';
                    } else {
                        className += ' marker-gray-black';
                    }
                    
                    // Add pulsing effect when work is active (working status)
                    if (workStatus === 'working') {
                        className += ' marker-working-pulse';
                    }
                    
                    icon = L.divIcon({
                        className: className,
                        html: '<style>.marker-work-status::after{content:"' + workStatusIcon + '";}</style>',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34]
                    });
                } else {
                    // No work status, use normal icons
                    if (status === 'planning') {
                        icon = hasSJA ? blueIconBlack : blueIcon;
                    } else if (status === 'active') {
                        icon = hasSJA ? greenIconBlack : greenIcon;
                    } else {
                        icon = hasSJA ? grayIconBlack : grayIcon;
                    }
                }
                
                var marker = L.marker([parseFloat(lat), parseFloat(lng)], { icon: icon });
                
                // Create modern popup content
                var statusText = status === 'planning' ? 'Planlagt' : status === 'active' ? 'Aktiv' : 'Afsluttet';
                var statusIcon = status === 'planning' ? '📋' : status === 'active' ? '🔥' : '✅';
                
                var popup = '<div class="wo-popup">' +
                    '<div class="wo-popup-header">' +
                        '<h3 class="wo-popup-title">' + statusIcon + ' WO ' + (e.work_order_no || 'N/A') + '</h3>' +
                        '<span class="wo-popup-status ' + status + '">' + statusText + '</span>' +
                    '</div>' +
                    '<div class="wo-popup-details">' +
                        '<div class="wo-popup-detail">' +
                            '<span class="wo-popup-label">Beskrivelse:</span>' +
                            '<span class="wo-popup-value">' + (e.description || 'Ingen beskrivelse') + '</span>' +
                        '</div>' +
                        (e.jobansvarlig ? '<div class="wo-popup-detail">' +
                            '<span class="wo-popup-label">Jobansvarlig:</span>' +
                            '<span class="wo-popup-value">' + e.jobansvarlig + '</span>' +
                        '</div>' : '') +
                        '<div class="wo-popup-detail">' +
                            '<span class="wo-popup-label">Entreprenør:</span>' +
                            '<span class="wo-popup-value">' + (e.entreprenor_firma || 'Ikke angivet') + '</span>' +
                        '</div>' +
                        (e.entreprenor_kontakt ? '<div class="wo-popup-detail">' +
                            '<span class="wo-popup-label">Kontakt:</span>' +
                            '<span class="wo-popup-value">' + e.entreprenor_kontakt + '</span>' +
                        '</div>' : '') +
                        (e.oprettet_dato ? '<div class="wo-popup-detail">' +
                            '<span class="wo-popup-label">Oprettet:</span>' +
                            '<span class="wo-popup-value">' + e.oprettet_dato + '</span>' +
                        '</div>' : '') +
                    '</div>' +
                    '<div class="wo-popup-actions">' +
                        '<a href="print_wo.php?id=' + encodeURIComponent(e.id) + '" class="wo-popup-btn">' +
                            '👁️ Se detaljer' +
                        '</a>' +
                    '</div>' +
                '</div>';
                
                marker.bindPopup(popup, {
                    maxWidth: 350,
                    className: 'modern-popup'
                });
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
    
    // Update marker count info
    function updateMarkerCount() {
        var total = allMarkers.length;
        var visible = 0;
        
        if (document.getElementById('showPlanning').checked) {
            visible += planningMarkers.length;
        }
        if (document.getElementById('showActive').checked) {
            visible += activeMarkers.length;
        }
        if (document.getElementById('showCompleted').checked) {
            visible += completedMarkers.length;
        }
        
        document.getElementById('markerCount').textContent = 
            visible + ' af ' + total + ' arbejdsordrer vises';
    }
    
    updateMarkerCount();

    // Enhanced search with debouncing for better performance
    var searchTimeout;
    document.getElementById('search').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        var searchInput = this;
        
        searchTimeout = setTimeout(function() {
            var term = searchInput.value.toLowerCase();
            var filtered = entries.filter(function(e) {
                var desc = (e.description || '').toLowerCase();
                var job  = (e.jobansvarlig || '').toLowerCase();
                var firm = (e.entreprenor_firma || '').toLowerCase();
                var kont = (e.entreprenor_kontakt || '').toLowerCase();
                var woNo = (e.work_order_no || '').toLowerCase();
                var pDesc = (e.p_description || '').toLowerCase();
                
                return desc.indexOf(term) !== -1 || 
                       job.indexOf(term) !== -1 || 
                       firm.indexOf(term) !== -1 || 
                       kont.indexOf(term) !== -1 ||
                       woNo.indexOf(term) !== -1 ||
                       pDesc.indexOf(term) !== -1;
            });
            buildMarkers(filtered);
            updateMapMarkers();
            updateMarkerCount();
        }, 300);
    });

    // Toggle marker visibility when status checkboxes change
    document.getElementById('showPlanning').addEventListener('change', function() {
        updateMapMarkers();
        updateMarkerCount();
    });
    document.getElementById('showActive').addEventListener('change', function() {
        updateMapMarkers();
        updateMarkerCount();
    });
    document.getElementById('showCompleted').addEventListener('change', function() {
        updateMapMarkers();
        updateMarkerCount();
    });
    
    // Add map click handler to close popups when clicking on map
    map.on('click', function() {
        map.closePopup();
    });
    
    // Fit map to show all markers if there are any
    if (allMarkers.length > 0) {
        var group = new L.featureGroup(allMarkers);
        map.fitBounds(group.getBounds().pad(0.1));
    }
    </script>
</body>
</html>