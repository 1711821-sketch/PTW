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

// Determine base path - if we're in a subdirectory, go up
$base = '';
if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $base = '../';
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
            <a href="<?php echo $base; ?>view_wo.php" <?php echo ($current_page === 'view_wo.php') ? 'class="active"' : ''; ?>>PTW-oversigt</a>
            <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
                <a href="<?php echo $base; ?>create_wo.php" <?php echo ($current_page === 'create_wo.php') ? 'class="active"' : ''; ?>>Opret ny PTW</a>
            <?php endif; ?>
            <a href="<?php echo $base; ?>map_wo.php" <?php echo ($current_page === 'map_wo.php') ? 'class="active"' : ''; ?>>Kort</a>
        <?php endif; ?>
        <a href="<?php echo $base; ?>dashboard.php" <?php echo ($current_page === 'dashboard.php') ? 'class="active"' : ''; ?>>Dashboard</a>
        <?php if ($modules['sja']): ?>
            <a href="<?php echo $base; ?>view_sja.php" <?php echo ($current_page === 'view_sja.php') ? 'class="active"' : ''; ?>>SJA-oversigt</a>
            <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
                <a href="<?php echo $base; ?>create_sja.php" <?php echo ($current_page === 'create_sja.php') ? 'class="active"' : ''; ?>>Opret ny SJA</a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
            <?php if ($modules['tidsregistrering']): ?>
                <a href="<?php echo $base; ?>time_overblik.php" <?php echo ($current_page === 'time_overblik.php') ? 'class="active"' : ''; ?>>Timeoverblik</a>
            <?php endif; ?>
            <a href="<?php echo $base; ?>admin.php" <?php echo ($current_page === 'admin.php') ? 'class="active"' : ''; ?>>Admin</a>
            <a href="<?php echo $base; ?>sms_admin.php" <?php echo ($current_page === 'sms_admin.php') ? 'class="active"' : ''; ?>>SMS Notifikationer</a>
            <a href="<?php echo $base; ?>admin/modules.php" <?php echo ($current_page === 'modules.php') ? 'class="active"' : ''; ?>>Modulstyring</a>
        <?php endif; ?>
        <span class="nav-user nav-user-desktop">Logget ind som <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)</span>

        <!-- Notifications Widget -->
        <?php include __DIR__ . '/notifications_widget.php'; ?>

        <!-- Help Button Widget -->
        <?php include __DIR__ . '/help_button.php'; ?>

        <a class="logout-link" href="<?php echo $base; ?>logout.php">Log ud</a>
    </div>
</nav>

<!-- Voice Assistant Widget -->
<?php include __DIR__ . '/voice_widget.php'; ?>

<!-- Bottom Navigation Bar (Mobile) -->
<nav class="bottom-nav" aria-label="Mobilnavigation">
    <div class="bottom-nav-links">
        <!-- Home/PTW List -->
        <a href="<?php echo $base; ?>view_wo.php" class="bottom-nav-item <?php echo ($current_page === 'view_wo.php') ? 'active' : ''; ?>" aria-label="PTW-oversigt">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            <span>PTW</span>
        </a>

        <!-- Map -->
        <a href="<?php echo $base; ?>map_wo.php" class="bottom-nav-item <?php echo ($current_page === 'map_wo.php') ? 'active' : ''; ?>" aria-label="Kort">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
            </svg>
            <span>Kort</span>
        </a>

        <?php if (in_array($role, ['admin','opgaveansvarlig','drift'])): ?>
        <!-- Create New (Floating Action Button style) -->
        <a href="<?php echo $base; ?>create_wo.php" class="bottom-nav-item create-btn <?php echo ($current_page === 'create_wo.php') ? 'active' : ''; ?>" aria-label="Opret ny PTW">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span>Opret</span>
        </a>
        <?php endif; ?>

        <!-- Dashboard -->
        <a href="<?php echo $base; ?>dashboard.php" class="bottom-nav-item <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>" aria-label="Dashboard">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
            <span>Dashboard</span>
        </a>

        <!-- Profile/More Menu -->
        <a href="<?php echo $base; ?>change_password.php" class="bottom-nav-item <?php echo ($current_page === 'change_password.php' || $current_page === 'admin.php') ? 'active' : ''; ?>" aria-label="Profil">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span>Profil</span>
        </a>
    </div>
</nav>
