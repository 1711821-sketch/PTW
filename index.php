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
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Velkommen - Arbejdstilladelsessystem</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Modern navigation bar -->
    <nav class="navbar">
        <a href="index.php">Forside</a>
        <a href="create_sja.php">Opret SJA</a>
        <a href="view_sja.php">SJA Oversigt</a>
        <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
            <a href="create_wo.php">Opret arbejdstilladelse</a>
        <?php endif; ?>
        <a href="view_wo.php">Oversigt over arbejdstilladelser</a>
        <a href="map_wo.php">Kort</a>
        <a href="dashboard.php">Dashboard</a>
        <?php if ($role === 'admin'): ?>
            <a href="admin.php">Admin</a>
        <?php endif; ?>
        <span class="nav-user">Logget ind som <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)</span>
        <a class="logout-link" href="logout.php">Log ud</a>
    </nav>

    <div class="container">
        <h1>Velkommen, <?= htmlspecialchars($username) ?>!</h1>
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