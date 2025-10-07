<?php
// Information screen page for the Danish work permit system
// Allows administrators to share important messages with all users
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'user';
$username = $_SESSION['user'];

// Load messages from JSON file
$data_file = __DIR__ . '/info_data.json';
$messages = [];
if (file_exists($data_file)) {
    $messages = json_decode(file_get_contents($data_file), true);
    if (!is_array($messages)) {
        $messages = [];
    }
}

// Sort messages by date (newest first)
usort($messages, function($a, $b) {
    return strtotime($b['created_date']) - strtotime($a['created_date']);
});

// Function to get message type icon and class
function getMessageTypeInfo($type) {
    switch($type) {
        case 'important':
            return ['icon' => '⚠️', 'class' => 'message-important', 'label' => 'Vigtigt'];
        case 'normal':
            return ['icon' => '📢', 'class' => 'message-normal', 'label' => 'Normal'];
        case 'info':
            return ['icon' => 'ℹ️', 'class' => 'message-info', 'label' => 'Information'];
        default:
            return ['icon' => '📝', 'class' => 'message-normal', 'label' => 'Meddelelse'];
    }
}

// Function to format Danish date
function formatDanishDate($date) {
    $timestamp = strtotime($date);
    return date('d.m.Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Informationer - Arbejdstilladelsessystem</title>
    <?php include 'pwa-head.php'; ?>
    <link rel="stylesheet" href="style.css">
    <script src="navigation.js"></script>
    <style>
        /* Additional styles for info page */
        .messages-container {
            display: grid;
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .message-card {
            background: var(--background-primary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .message-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .message-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .message-important::before {
            background: linear-gradient(90deg, var(--danger-color) 0%, #f97316 100%);
        }
        
        .message-normal::before {
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--primary-light) 100%);
        }
        
        .message-info::before {
            background: linear-gradient(90deg, var(--secondary-color) 0%, #06b6d4 100%);
        }
        
        .message-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .message-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
            margin-top: 0.25rem;
        }
        
        .message-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            flex-grow: 1;
        }
        
        .message-type {
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            flex-shrink: 0;
        }
        
        .message-important .message-type {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-dark);
        }
        
        .message-normal .message-type {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-dark);
        }
        
        .message-info .message-type {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary-dark);
        }
        
        .message-content {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .message-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--text-light);
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
        }
        
        .message-author {
            font-weight: 500;
        }
        
        .message-date {
            margin-left: auto;
        }
        
        .no-messages {
            text-align: center;
            padding: 3rem 2rem;
            background: var(--background-secondary);
            border-radius: var(--radius-lg);
            border: 2px dashed var(--border-color);
            color: var(--text-secondary);
        }
        
        .no-messages-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .page-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        .page-icon {
            font-size: 2rem;
        }
        
        .page-description {
            color: var(--text-secondary);
            margin-bottom: 2rem;
            font-size: 1.1rem;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <!-- Navigation bar with hamburger menu -->
    <nav class="navbar">
        <button class="navbar-toggle" aria-label="Toggle navigation">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
        <div class="navbar-links">
            <a href="index.php">Forside</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="view_wo.php">Oversigt over arbejdstilladelser</a>
            <a href="view_sja.php">SJA Oversigt</a>
            <a href="info.php">Informationer</a>
            <?php if ($role === 'admin'): ?>
                <a href="time_overblik.php">Timeoverblik</a>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <span class="nav-user">
                Logget ind som <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)
            </span>
            <a class="logout-link" href="logout.php">Log ud</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <span class="page-icon">📢</span>
            <h1>Informationer</h1>
        </div>
        
        <p class="page-description">
            Her finder du vigtige meddelelser og informationer fra administrationen. 
            Meddelelserne er sorteret med de nyeste først.
        </p>

        <?php if (count($messages) > 0): ?>
            <div class="messages-container">
                <?php foreach ($messages as $message): 
                    $typeInfo = getMessageTypeInfo($message['type']);
                ?>
                    <article class="message-card <?php echo $typeInfo['class']; ?>">
                        <div class="message-header">
                            <span class="message-icon"><?php echo $typeInfo['icon']; ?></span>
                            <h2 class="message-title"><?php echo htmlspecialchars($message['title']); ?></h2>
                            <span class="message-type"><?php echo $typeInfo['label']; ?></span>
                        </div>
                        
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                        </div>
                        
                        <div class="message-meta">
                            <span class="message-author">
                                👤 <?php echo htmlspecialchars($message['author']); ?>
                            </span>
                            <span class="message-date">
                                📅 <?php echo formatDanishDate($message['created_date']); ?>
                            </span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-messages">
                <div class="no-messages-icon">📭</div>
                <h3>Ingen meddelelser</h3>
                <p>Der er endnu ingen informationer at vise. 
                   Administratoren kan tilføje meddelelser, som vil blive vist her.</p>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-light);">
            <a href="index.php" class="button button-secondary">🏠 Tilbage til forsiden</a>
            <a href="dashboard.php" class="button" style="margin-left: 1rem;">📊 Se Dashboard</a>
        </div>
    </div>
</body>
</html>