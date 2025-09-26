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
    <title>Beskyttet område</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; }
        .container { max-width: 700px; margin: 80px auto; padding: 20px; background: #fff;
                     border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; font-size: 1.6em; }
        a { color: #0070C0; text-decoration: none; display: inline-block; margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Velkommen, <?= htmlspecialchars($username) ?>!</h1>
        <p>Du er nu logget ind og kan arbejde med Sikker Job Analyse (SJA) og Arbejdstilladelser (WO).</p>
        <p>
            <a href="create_sja.php">Opret en ny SJA</a><br>
            <a href="view_sja.php">Se eksisterende SJA'er</a><br>
            <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
                <a href="create_wo.php">Opret ny WO</a><br>
            <?php endif; ?>
            <a href="view_wo.php">Se eksisterende WO'er</a><br>
            <a href="map_wo.php">Se WO-oversigtskort</a><br>
            <!-- Provide direct access to the dashboard with statistics -->
            <a href="dashboard.php">Dashboard</a><br>
            <?php if ($role === 'admin'): ?>
                <a href="admin.php">Administrer brugere</a><br>
            <?php endif; ?>
            <a href="logout.php">Log ud</a>
        </p>
    </div>
</body>
</html>