<?php
// Notification settings page
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$role = $_SESSION['role'] ?? '';
$user = $_SESSION['user'] ?? '';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Notifikationsindstillinger - PTW System</title>
    <?php include 'pwa-head.php'; ?>
    <link rel="stylesheet" href="style.css">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .settings-header {
            margin-bottom: 2rem;
        }
        
        .settings-header h1 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
        
        .settings-header p {
            margin: 0;
            color: #666;
            font-size: 0.95rem;
        }
        
        .notification-card {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .notification-card h2 {
            margin: 0 0 1rem 0;
            font-size: 1.2rem;
            color: #333;
        }
        
        .notification-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            background-color: #f8f9fa;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .btn-notification {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: background-color 0.2s;
            margin-right: 0.5rem;
        }
        
        .btn-enable {
            background-color: #28a745;
            color: white;
        }
        
        .btn-enable:hover {
            background-color: #218838;
        }
        
        .btn-disable {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-disable:hover {
            background-color: #c82333;
        }
        
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #0070f3;
            padding: 1rem;
            margin-top: 1rem;
            border-radius: 4px;
        }
        
        .info-box ul {
            margin: 0.5rem 0 0 0;
            padding-left: 1.5rem;
        }
        
        .info-box li {
            margin: 0.3rem 0;
        }
        
        @media (max-width: 768px) {
            .settings-container {
                margin: 1rem;
                padding: 1rem;
            }
            
            .btn-notification {
                width: 100%;
                margin: 0.5rem 0;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="view_wo.php">PTW-oversigt</a>
        <a href="map_wo.php">Kort</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="notification_settings.php" class="active">Notifikationer</a>
        <span class="nav-user">Logget ind som <?php echo htmlspecialchars($user); ?> (<?php echo htmlspecialchars($role); ?>)</span>
        <a class="logout-link" href="logout.php">Log ud</a>
    </nav>
    
    <div class="settings-container">
        <div class="settings-header">
            <h1>🔔 Notifikationsindstillinger</h1>
            <p>Modtag push-notifikationer på din telefon eller computer når vigtige begivenheder sker.</p>
        </div>
        
        <div class="notification-card">
            <h2>Push Notifikationer</h2>
            
            <div id="notification-status" class="notification-status">
                Kontrollerer status...
            </div>
            
            <div style="margin: 1.5rem 0;">
                <button id="enable-notifications-btn" class="btn-notification btn-enable" style="display: none;">
                    🔔 Aktiver notifikationer
                </button>
                <button id="disable-notifications-btn" class="btn-notification btn-disable" style="display: none;">
                    🔕 Deaktiver notifikationer
                </button>
            </div>
            
            <?php if ($role === 'entreprenor'): ?>
            <div class="info-box">
                <strong>📱 Som entreprenør modtager du notifikationer når:</strong>
                <ul>
                    <li>En PTW for dit firma bliver godkendt og aktiv</li>
                    <li>Der er vigtige opdateringer til dine PTW'er</li>
                </ul>
            </div>
            <?php else: ?>
            <div class="info-box">
                <strong>📱 Du modtager notifikationer når:</strong>
                <ul>
                    <li>Der er nye PTW'er der kræver din godkendelse</li>
                    <li>Der er vigtige opdateringer til systemet</li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="notification-card">
            <h2>💡 Sådan virker det</h2>
            <p style="margin: 0.5rem 0;">
                1. Klik på "Aktiver notifikationer" knappen ovenfor<br>
                2. Godkend tilladelsen i din browser<br>
                3. Modtag automatisk notifikationer på denne enhed<br>
            </p>
            <p style="margin: 1rem 0 0 0; color: #666; font-size: 0.9rem;">
                <strong>Bemærk:</strong> Du skal aktivere notifikationer på hver enhed (telefon, tablet, computer) hvor du ønsker at modtage dem.
            </p>
        </div>
    </div>
    
    <script src="push_notifications.js"></script>
</body>
</html>
