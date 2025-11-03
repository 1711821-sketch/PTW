<?php
// Central navigation bar - used across all pages
// Requires $role and $_SESSION['user'] to be set before including this file

// Get current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['user'] ?? '';
$role = $role ?? $_SESSION['role'] ?? '';

// Load module configuration (fallback if not already loaded)
if (!isset($modules)) {
    $modules = include __DIR__ . '/../config/modules.php';
}
?>
<nav class="navbar">
    <div class="navbar-mobile">
        <button class="navbar-toggle" aria-label="Toggle navigation">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
        <span class="nav-user nav-user-mobile"><?php echo htmlspecialchars($username); ?></span>
    </div>
    <div class="navbar-links">
        <?php if ($modules['ptw']): ?>
            <a href="view_wo.php" <?php echo ($current_page === 'view_wo.php') ? 'class="active"' : ''; ?>>PTW-oversigt</a>
            <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
                <a href="create_wo.php" <?php echo ($current_page === 'create_wo.php') ? 'class="active"' : ''; ?>>Opret ny PTW</a>
            <?php endif; ?>
            <a href="map_wo.php" <?php echo ($current_page === 'map_wo.php') ? 'class="active"' : ''; ?>>Kort</a>
        <?php endif; ?>
        <a href="dashboard.php" <?php echo ($current_page === 'dashboard.php') ? 'class="active"' : ''; ?>>Dashboard</a>
        <?php if ($modules['sja']): ?>
            <a href="view_sja.php" <?php echo ($current_page === 'view_sja.php') ? 'class="active"' : ''; ?>>SJA-oversigt</a>
            <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
                <a href="create_sja.php" <?php echo ($current_page === 'create_sja.php') ? 'class="active"' : ''; ?>>Opret ny SJA</a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
            <?php if ($modules['tidsregistrering']): ?>
                <a href="time_overblik.php" <?php echo ($current_page === 'time_overblik.php') ? 'class="active"' : ''; ?>>Timeoverblik</a>
            <?php endif; ?>
            <a href="admin.php" <?php echo ($current_page === 'admin.php') ? 'class="active"' : ''; ?>>Admin</a>
            <a href="sms_admin.php" <?php echo ($current_page === 'sms_admin.php') ? 'class="active"' : ''; ?>>SMS Notifikationer</a>
            <a href="admin/modules.php" <?php echo ($current_page === 'modules.php') ? 'class="active"' : ''; ?>>Modulstyring</a>
        <?php endif; ?>
        <span class="nav-user nav-user-desktop">Logget ind som <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)</span>
        <a class="logout-link" href="logout.php">Log ud</a>
    </div>
</nav>
