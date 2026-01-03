<?php
/**
 * Help Button Widget for Navigation
 * Displays help icon with dropdown for tutorials
 */

// Get current page for tour mapping
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? '';

// Load tutorial config
$tutorials = include __DIR__ . '/../config/tutorials.php';

// Get tour for current page
$page_tour = $tutorials['page_tours'][$current_page] ?? null;
$current_tour = $page_tour ? ($tutorials['tours'][$page_tour] ?? null) : null;

// Check if tour is available for user's role
$tour_available = false;
if ($current_tour && isset($current_tour['roles'])) {
    $tour_available = in_array($user_role, $current_tour['roles']) || in_array('all', $current_tour['roles']);
}

// Get user's tutorial progress
// Use cached user_id from session for better performance
$completed_count = 0;
$total_tours = 0;
try {
    if (isset($db) && isset($_SESSION['user_id'])) {
        $progress = $db->fetch("SELECT COUNT(*) as count FROM tutorial_progress WHERE user_id = ?", [$_SESSION['user_id']]);
        $completed_count = (int)($progress['count'] ?? 0);
    }
    // Count total available tours for this role
    foreach ($tutorials['tours'] as $tour) {
        if (in_array($user_role, $tour['roles'] ?? [])) {
            $total_tours++;
        }
    }
} catch (Exception $e) {
    // Silently fail
}
?>

<!-- Help Button Widget -->
<div class="help-wrapper">
    <button class="help-trigger" aria-label="Hjælp og vejledning" title="Hjælp" onclick="toggleHelpDropdown()">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    </button>

    <!-- Help Dropdown Menu -->
    <div class="help-dropdown" id="helpDropdown">
        <div class="help-header">
            <h4>Hjælp & Vejledning</h4>
        </div>
        <div class="help-menu">
            <?php if ($tour_available): ?>
            <a href="#" class="help-menu-item" onclick="startPageTour(); return false;">
                <span class="help-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                    </svg>
                </span>
                <div class="help-item-content">
                    <span class="help-text">Start rundvisning</span>
                    <span class="help-subtext"><?php echo htmlspecialchars($current_tour['title'] ?? 'Denne side'); ?></span>
                </div>
            </a>
            <?php endif; ?>

            <a href="#" class="help-menu-item" onclick="showAllTooltips(); return false;">
                <span class="help-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                </span>
                <div class="help-item-content">
                    <span class="help-text">Vis vejledningstips</span>
                    <span class="help-subtext">Hjælpetekster ved knapper</span>
                </div>
            </a>

            <a href="<?php echo $base ?? ''; ?>tutorial-center.php" class="help-menu-item">
                <span class="help-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                </span>
                <div class="help-item-content">
                    <span class="help-text">Videobibliotek</span>
                    <span class="help-subtext">Se instruktionsvideoer</span>
                </div>
            </a>
        </div>

        <?php if ($total_tours > 0): ?>
        <div class="help-footer">
            <div class="help-progress-bar">
                <div class="help-progress-fill" style="width: <?php echo $total_tours > 0 ? ($completed_count / $total_tours * 100) : 0; ?>%"></div>
            </div>
            <span class="help-progress-text"><?php echo $completed_count; ?>/<?php echo $total_tours; ?> tutorials gennemført</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    // Help dropdown functionality - scoped to avoid conflicts
    let helpDropdownOpen = false;
    const dropdown = document.getElementById('helpDropdown');
    const trigger = document.querySelector('.help-trigger');

    // Ensure dropdown starts closed
    if (dropdown) {
        dropdown.classList.remove('open');
    }

    window.toggleHelpDropdown = function(e) {
        if (e) e.stopPropagation();
        helpDropdownOpen = !helpDropdownOpen;

        if (helpDropdownOpen) {
            dropdown.classList.add('open');
        } else {
            dropdown.classList.remove('open');
        }
    };

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const wrapper = document.querySelector('.help-wrapper');
        if (wrapper && !wrapper.contains(e.target) && helpDropdownOpen) {
            helpDropdownOpen = false;
            dropdown.classList.remove('open');
        }
    });

    // Start tour for current page
    window.startPageTour = function() {
        window.toggleHelpDropdown();
        if (window.SikkerjobTutorial) {
            window.SikkerjobTutorial.startTour('<?php echo $page_tour ?? ''; ?>');
        } else {
            console.warn('Tutorial system not loaded');
        }
    };

    // Show all tooltips
    window.showAllTooltips = function() {
        window.toggleHelpDropdown();
        if (window.SikkerjobTutorial) {
            window.SikkerjobTutorial.showTooltips();
        } else {
            console.warn('Tutorial system not loaded');
        }
    };
})();
</script>
