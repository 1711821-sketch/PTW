<?php
// Landing page for logged-in users.  Displays welcome and links to SJA functionality.
session_start();

// If user is not logged in, redirect to login page
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['user'];
$role     = $_SESSION['role'] ?? 'user';

// Get accident counter data
require_once 'database.php';
try {
    $db = Database::getInstance();
    $counterData = $db->fetch("SELECT *, CURRENT_DATE - last_accident_date as days_since_accident FROM accident_counter LIMIT 1");
    $daysSinceAccident = $counterData ? $counterData['days_since_accident'] : 0;
} catch (Exception $e) {
    // Fallback if database is not available
    $daysSinceAccident = 502;
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Velkommen - Arbejdstilladelsessystem</title>
    <?php include 'pwa-head.php'; ?>
    <link rel="stylesheet" href="style.css">
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
            <a href="index.php">Forside</a>
            <a href="create_sja.php">Opret SJA</a>
            <a href="view_sja.php">SJA Oversigt</a>
            <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
                <a href="create_wo.php">Opret arbejdstilladelse</a>
            <?php endif; ?>
            <a href="view_wo.php">Oversigt over arbejdstilladelser</a>
            <a href="map_wo.php">Kort</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="info.php">Informationer</a>
            <?php if ($role === 'admin'): ?>
                <a href="time_overblik.php">Timeoverblik</a>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <span class="nav-user">Logget ind som <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)</span>
            <a class="logout-link" href="logout.php">Log ud</a>
        </div>
    </nav>

    <div class="container">
        <div class="header-row">
            <h1>Velkommen, <?= htmlspecialchars($username) ?>!</h1>
            <!-- Digital Safety Sign - Inline -->
            <div class="safety-sign safety-sign-inline">
                <div class="safety-sign-content">
                    <div class="safety-sign-number">
                        <?php 
                        $digits = str_split((string)$daysSinceAccident);
                        foreach ($digits as $digit) {
                            echo '<span class="digit">' . htmlspecialchars($digit) . '</span>';
                        }
                        ?>
                    </div>
                    <div class="safety-sign-text">DAGE SIDEN SIDSTE UHELD</div>
                </div>
            </div>
        </div>
        <p>Du er nu logget ind og kan arbejde med Sikker Job Analyse (SJA) og arbejdstilladelser.</p>
        
        <!-- Modern card-based navigation -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin: 2rem 0;">
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0; color: var(--primary-color);">🛡️ Sikker Job Analyse</h3>
                </div>
                <div class="card-body">
                    <p style="margin-bottom: 1rem;">Opret og administrer sikre arbejdsanalyser</p>
                    <a href="create_sja.php" class="button button-success">Opret ny SJA</a>
                    <a href="view_sja.php" class="button button-secondary">Se SJA'er</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0; color: var(--primary-color);">📋 Arbejdstilladelser</h3>
                </div>
                <div class="card-body">
                    <p style="margin-bottom: 1rem;">Administrer arbejdstilladelser</p>
                    <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
                        <a href="create_wo.php" class="button button-success">Opret ny arbejdstilladelse</a>
                    <?php endif; ?>
                    <a href="view_wo.php" class="button button-secondary">Se arbejdstilladelser</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0; color: var(--primary-color);">📊 Overblik & Kort</h3>
                </div>
                <div class="card-body">
                    <p style="margin-bottom: 1rem;">Få overblik over status og lokationer</p>
                    <a href="dashboard.php" class="button">Dashboard</a>
                    <a href="map_wo.php" class="button button-secondary">Oversigtskort</a>
                </div>
            </div>

            <?php if ($role === 'admin'): ?>
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0; color: var(--danger-color);">⚙️ Administration</h3>
                </div>
                <div class="card-body">
                    <p style="margin-bottom: 1rem;">Administrer brugere og systemindstillinger</p>
                    <a href="admin.php" class="button button-danger">Administrer brugere</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>