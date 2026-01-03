<?php
/**
 * Tutorial Center - Video Library & Tutorial Overview
 */

session_start();
require_once 'auth_check.php';
require_once 'database.php';

$db = Database::getInstance();
$role = $_SESSION['role'] ?? '';
$username = $_SESSION['user'] ?? '';

// Load tutorial configuration
$tutorials = include 'config/tutorials.php';

// Get role-specific content
$rolePath = $tutorials['role_paths'][$role] ?? null;
$videos = $rolePath['videos'] ?? [];
$recommendedTours = $rolePath['recommended_tours'] ?? [];

// Get user's progress
$userId = null;
$completedTours = [];
try {
    $user = $db->fetch("SELECT id FROM users WHERE username = ?", [$username]);
    if ($user) {
        $userId = $user['id'];
        $progress = $db->fetchAll("SELECT tutorial_id FROM tutorial_progress WHERE user_id = ?", [$userId]);
        $completedTours = array_column($progress, 'tutorial_id');
    }
} catch (Exception $e) {
    // Silently fail
}

// Get all available tours for this role
$availableTours = [];
foreach ($tutorials['tours'] as $key => $tour) {
    if (in_array($role, $tour['roles']) || in_array('all', $tour['roles'])) {
        $tourId = $tour['id'] ?? $key . '_tour';
        $availableTours[] = [
            'id' => $key,
            'tour_id' => $tourId,
            'title' => $tour['title'],
            'description' => $tour['description'] ?? '',
            'step_count' => count($tour['steps']),
            'completed' => in_array($tourId, $completedTours)
        ];
    }
}

$completedCount = count(array_filter($availableTours, fn($t) => $t['completed']));
$totalTours = count($availableTours);

// Load modules config
$modules = include 'config/modules.php';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Hjaelp & Vejledning - PTW System</title>
    <link rel="stylesheet" href="style.css">
    <?php include 'pwa-head.php'; ?>
    <style>
        .tutorial-center {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .tutorial-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .tutorial-header h1 {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .tutorial-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .progress-overview {
            background: var(--background-secondary);
            border-radius: var(--radius-lg);
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .progress-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: conic-gradient(
                var(--primary-color) <?php echo $totalTours > 0 ? ($completedCount / $totalTours * 360) : 0; ?>deg,
                var(--border-light) 0deg
            );
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .progress-circle::before {
            content: '';
            position: absolute;
            width: 60px;
            height: 60px;
            background: var(--background-secondary);
            border-radius: 50%;
        }

        .progress-circle span {
            position: relative;
            z-index: 1;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .progress-info h3 {
            margin: 0 0 0.25rem;
            color: var(--text-primary);
        }

        .progress-info p {
            margin: 0;
            color: var(--text-secondary);
        }

        .section-title {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-light);
        }

        .tours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .tour-card {
            background: var(--background-primary);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
        }

        .tour-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .tour-card.completed {
            border-color: var(--success-color);
        }

        .tour-card.completed::before {
            content: '';
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 24px;
            height: 24px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tour-card.completed::after {
            content: '\\2713';
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 24px;
            height: 24px;
            color: white;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tour-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-light);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .tour-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 0.5rem;
        }

        .tour-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0 0 1rem;
        }

        .tour-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.85rem;
            color: var(--text-light);
        }

        .tour-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .videos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .video-card {
            background: var(--background-primary);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: var(--transition);
        }

        .video-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .video-thumbnail {
            position: relative;
            background: var(--background-secondary);
            padding-top: 56.25%; /* 16:9 aspect ratio */
        }

        .video-thumbnail-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 64px;
            height: 64px;
            background: rgba(0, 0, 0, 0.6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: var(--transition);
        }

        .video-card:hover .video-thumbnail-icon {
            background: var(--primary-color);
            transform: translate(-50%, -50%) scale(1.1);
        }

        .video-duration {
            position: absolute;
            bottom: 0.5rem;
            right: 0.5rem;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
        }

        .video-info {
            padding: 1rem;
        }

        .video-title {
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 0.25rem;
        }

        .video-description {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin: 0;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .btn-start-tour {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-start-tour:hover {
            background: var(--primary-dark);
        }

        @media (max-width: 768px) {
            .progress-overview {
                flex-direction: column;
                text-align: center;
            }

            .tours-grid,
            .videos-grid {
                grid-template-columns: 1fr;
            }

            .tutorial-center {
                padding-bottom: 100px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <main class="container tutorial-center">
        <div class="tutorial-header">
            <h1>Hjaelp & Vejledning</h1>
            <p>Laer at bruge PTW-systemet med interaktive rundvisninger og videoer</p>
        </div>

        <div class="progress-overview">
            <div class="progress-circle">
                <span><?php echo $completedCount; ?>/<?php echo $totalTours; ?></span>
            </div>
            <div class="progress-info">
                <h3>Din fremgang</h3>
                <p>Du har gennemfoert <?php echo $completedCount; ?> af <?php echo $totalTours; ?> tutorials</p>
            </div>
        </div>

        <h2 class="section-title">Interaktive Rundvisninger</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
            Klik paa et kort for at starte en guidet rundvisning af den paagaeldende side.
        </p>

        <?php if (count($availableTours) > 0): ?>
        <div class="tours-grid">
            <?php foreach ($availableTours as $tour): ?>
            <div class="tour-card <?php echo $tour['completed'] ? 'completed' : ''; ?>"
                 onclick="navigateAndStartTour('<?php echo htmlspecialchars($tour['id']); ?>')">
                <div class="tour-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                    </svg>
                </div>
                <h3 class="tour-title"><?php echo htmlspecialchars($tour['title']); ?></h3>
                <p class="tour-description"><?php echo htmlspecialchars($tour['description']); ?></p>
                <div class="tour-meta">
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <?php echo $tour['step_count']; ?> trin
                    </span>
                    <?php if ($tour['completed']): ?>
                    <span style="color: var(--success-color);">Gennemfoert</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">&#128218;</div>
            <p>Ingen tutorials tilgaengelige for din rolle</p>
        </div>
        <?php endif; ?>

        <h2 class="section-title">Instruktionsvideoer</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
            Korte videoer der forklarer de vigtigste funktioner.
        </p>

        <?php if (count($videos) > 0): ?>
        <div class="videos-grid">
            <?php foreach ($videos as $video): ?>
            <div class="video-card" onclick="playVideo('<?php echo htmlspecialchars($video['id']); ?>')">
                <div class="video-thumbnail">
                    <div class="video-thumbnail-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" width="32" height="32">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </div>
                    <span class="video-duration"><?php echo floor($video['duration'] / 60); ?>:<?php echo str_pad($video['duration'] % 60, 2, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="video-info">
                    <h4 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h4>
                    <p class="video-description">Klik for at se videoen</p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">&#127909;</div>
            <p>Ingen videoer tilgaengelige endnu</p>
        </div>
        <?php endif; ?>

        <!-- Quick link to existing instruction video -->
        <div style="margin-top: 2rem; text-align: center;">
            <a href="instruktionsvideo.php" class="btn-start-tour">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Se introduktionsvideo
            </a>
        </div>
    </main>

    <script>
    // Navigate to page and start tour
    function navigateAndStartTour(tourId) {
        const pageMap = {
            'view_wo': 'view_wo.php',
            'create_wo': 'create_wo.php',
            'dashboard': 'dashboard.php',
            'map_wo': 'map_wo.php',
            'entreprenor_dashboard': 'dashboard.php'
        };

        const page = pageMap[tourId];
        if (page) {
            // Store tour to start in sessionStorage
            sessionStorage.setItem('startTour', tourId);
            window.location.href = page;
        }
    }

    // Play video (placeholder - would open video modal)
    function playVideo(videoId) {
        alert('Video afspilning kommer snart: ' + videoId);
        // TODO: Implement video player modal
    }

    // Check if we should start a tour (coming from tutorial center)
    document.addEventListener('DOMContentLoaded', function() {
        const tourToStart = sessionStorage.getItem('startTour');
        if (tourToStart && window.SikkerjobTutorial) {
            sessionStorage.removeItem('startTour');
            setTimeout(() => {
                window.SikkerjobTutorial.startTour(tourToStart);
            }, 500);
        }
    });
    </script>
</body>
</html>
