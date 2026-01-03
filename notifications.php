<?php
/**
 * Notifications Page - Full list of all notifications
 */

session_start();
require_once 'auth_check.php';
require_once 'database.php';

$db = Database::getInstance();
$role = $_SESSION['role'] ?? '';
$username = $_SESSION['user'] ?? '';

// Use cached user_id from session for better performance
$userId = $_SESSION['user_id'] ?? null;

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notificationId = (int)$_POST['notification_id'];
    if ($userId && $notificationId) {
        $db->execute("UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?", [$notificationId, $userId]);
    }
    header("Location: notifications.php");
    exit;
}

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    if ($userId) {
        $db->execute("UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL", [$userId]);
    }
    header("Location: notifications.php");
    exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $notificationId = (int)$_POST['notification_id'];
    if ($userId && $notificationId) {
        $db->execute("DELETE FROM notifications WHERE id = ? AND user_id = ?", [$notificationId, $userId]);
    }
    header("Location: notifications.php");
    exit;
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get notifications
$notifications = [];
$totalCount = 0;
$unreadCount = 0;

if ($userId) {
    $notifications = $db->fetchAll("
        SELECT id, type, title, message, link, read_at, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ", [$userId, $perPage, $offset]);

    $countResult = $db->fetch("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?", [$userId]);
    $totalCount = (int)($countResult['total'] ?? 0);

    $unreadResult = $db->fetch("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND read_at IS NULL", [$userId]);
    $unreadCount = (int)($unreadResult['unread'] ?? 0);
}

$totalPages = ceil($totalCount / $perPage);

// Load modules config
$modules = include 'config/modules.php';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Notifikationer - PTW System</title>
    <link rel="stylesheet" href="style.css">
    <?php include 'pwa-head.php'; ?>
    <style>
        .notifications-page {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .notifications-header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--text-primary);
        }

        .notifications-stats {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .stat-badge {
            background: var(--background-secondary);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .stat-badge.unread {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .notifications-actions {
            margin-bottom: 1.5rem;
        }

        .btn-mark-all {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .btn-mark-all:hover {
            background: var(--primary-dark);
        }

        .btn-mark-all:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .notification-card {
            background: var(--background-primary);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            transition: var(--transition);
        }

        .notification-card:hover {
            border-color: var(--primary-light);
        }

        .notification-card.unread {
            background: rgba(30, 64, 175, 0.03);
            border-left: 3px solid var(--primary-color);
        }

        .notification-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .notification-icon.approval {
            background: rgba(34, 197, 94, 0.1);
        }

        .notification-icon.info {
            background: rgba(59, 130, 246, 0.1);
        }

        .notification-icon.warning {
            background: rgba(245, 158, 11, 0.1);
        }

        .notification-icon.reminder {
            background: rgba(168, 85, 247, 0.1);
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-title {
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 0.25rem;
        }

        .notification-message {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0 0 0.5rem;
        }

        .notification-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .notification-actions form {
            display: inline;
        }

        .btn-small {
            background: var(--background-secondary);
            border: 1px solid var(--border-light);
            padding: 0.35rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-secondary);
        }

        .btn-small:hover {
            background: var(--background-primary);
            border-color: var(--text-secondary);
        }

        .btn-small.danger:hover {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--danger-color);
            color: var(--danger-color);
        }

        .notification-link {
            color: var(--primary-color);
            text-decoration: none;
        }

        .notification-link:hover {
            text-decoration: underline;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .pagination a {
            background: var(--background-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-light);
        }

        .pagination a:hover {
            background: var(--primary-light);
            border-color: var(--primary-color);
        }

        .pagination span.current {
            background: var(--primary-color);
            color: white;
        }

        @media (max-width: 768px) {
            .notifications-page {
                padding-bottom: 100px;
            }

            .notification-card {
                flex-direction: column;
            }

            .notification-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <main class="container notifications-page">
        <div class="notifications-header">
            <h1>Notifikationer</h1>
            <div class="notifications-stats">
                <span class="stat-badge"><?php echo $totalCount; ?> total</span>
                <?php if ($unreadCount > 0): ?>
                <span class="stat-badge unread"><?php echo $unreadCount; ?> ulaeste</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($unreadCount > 0): ?>
        <div class="notifications-actions">
            <form method="POST" style="display: inline;">
                <button type="submit" name="mark_all_read" class="btn-mark-all">
                    Marker alle som laest
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if (count($notifications) > 0): ?>
        <div class="notifications-list">
            <?php foreach ($notifications as $notification): ?>
            <?php
                $isUnread = empty($notification['read_at']);
                $iconClass = 'info';
                $icon = '&#128276;'; // Bell

                switch ($notification['type']) {
                    case 'approval':
                        $iconClass = 'approval';
                        $icon = '&#10003;';
                        break;
                    case 'warning':
                        $iconClass = 'warning';
                        $icon = '&#9888;';
                        break;
                    case 'reminder':
                        $iconClass = 'reminder';
                        $icon = '&#128337;';
                        break;
                    case 'new_ptw':
                        $iconClass = 'info';
                        $icon = '&#128203;';
                        break;
                }

                $timeAgo = '';
                $created = strtotime($notification['created_at']);
                $diff = time() - $created;
                if ($diff < 60) {
                    $timeAgo = 'Lige nu';
                } elseif ($diff < 3600) {
                    $timeAgo = floor($diff / 60) . ' min siden';
                } elseif ($diff < 86400) {
                    $timeAgo = floor($diff / 3600) . ' timer siden';
                } else {
                    $timeAgo = date('d. M Y', $created);
                }
            ?>
            <div class="notification-card <?php echo $isUnread ? 'unread' : ''; ?>">
                <div class="notification-icon <?php echo $iconClass; ?>">
                    <?php echo $icon; ?>
                </div>
                <div class="notification-content">
                    <h4 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h4>
                    <?php if ($notification['message']): ?>
                    <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                    <?php endif; ?>
                    <div class="notification-meta">
                        <span><?php echo $timeAgo; ?></span>
                        <?php if ($notification['link']): ?>
                        <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="notification-link">Se detaljer</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="notification-actions">
                    <?php if ($isUnread): ?>
                    <form method="POST">
                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                        <button type="submit" name="mark_read" class="btn-small">Marker laest</button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" onsubmit="return confirm('Slet denne notifikation?');">
                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                        <button type="submit" name="delete" class="btn-small danger">Slet</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>">Forrige</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $page): ?>
                <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>">Naeste</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">&#128235;</div>
            <h3>Ingen notifikationer</h3>
            <p>Du har ingen notifikationer endnu.</p>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
