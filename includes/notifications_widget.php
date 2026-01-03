<?php
/**
 * Notifications Widget for Navigation
 * Displays notification bell with dropdown
 */

// Get unread count (if database is available)
// Use cached user_id from session for better performance
$unreadCount = 0;
try {
    if (isset($db) && isset($_SESSION['user_id'])) {
        $countResult = $db->fetch("
            SELECT COUNT(*) as count
            FROM notifications
            WHERE user_id = ?
            AND read_at IS NULL
        ", [$_SESSION['user_id']]);
        $unreadCount = (int)($countResult['count'] ?? 0);
    }
} catch (Exception $e) {
    // Table might not exist yet
    $unreadCount = 0;
}
?>

<!-- Notification Bell -->
<div class="notification-wrapper">
    <button class="notification-bell" aria-label="Notifikationer" onclick="toggleNotificationDropdown()">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        <span class="notification-badge" style="<?php echo $unreadCount > 0 ? '' : 'display: none;'; ?>">
            <?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?>
        </span>
    </button>

    <!-- Notification Dropdown -->
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <h4>Notifikationer</h4>
            <button class="mark-all-read" onclick="markAllNotificationsRead()">Marker alle som laest</button>
        </div>
        <div class="notification-list" id="notificationList">
            <div class="notification-loading">Indlaeser...</div>
        </div>
        <div class="notification-footer">
            <a href="notifications.php">Se alle notifikationer</a>
        </div>
    </div>
</div>

<style>
/* Notification Widget Styles */
.notification-wrapper {
    position: relative;
    display: inline-flex;
}

.notification-bell {
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: var(--radius-md);
    color: var(--text-primary);
    transition: var(--transition);
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-bell:hover {
    background: var(--background-secondary);
}

.notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: var(--danger-color);
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    transform: translate(25%, -25%);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 360px;
    max-height: 480px;
    background: var(--background-primary);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    border: 1px solid var(--border-light);
    z-index: 10000;
    display: none;
    flex-direction: column;
    overflow: hidden;
}

.notification-dropdown.open {
    display: flex;
    animation: dropdownSlide 0.2s ease;
}

@keyframes dropdownSlide {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid var(--border-light);
}

.notification-header h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.mark-all-read {
    background: none;
    border: none;
    color: var(--primary-color);
    font-size: 0.85rem;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius-sm);
    transition: var(--transition);
}

.mark-all-read:hover {
    background: rgba(30, 64, 175, 0.1);
}

.notification-list {
    flex: 1;
    overflow-y: auto;
    max-height: 350px;
}

.notification-item {
    display: flex;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    border-bottom: 1px solid var(--border-light);
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    color: inherit;
}

.notification-item:hover {
    background: var(--background-secondary);
}

.notification-item.unread {
    background: rgba(30, 64, 175, 0.05);
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--primary-color);
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
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

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-primary);
    margin: 0 0 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.notification-message {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.notification-time {
    font-size: 0.75rem;
    color: var(--text-light);
    margin-top: 0.25rem;
}

.notification-empty {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

.notification-empty-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

.notification-loading {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

.notification-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid var(--border-light);
    text-align: center;
}

.notification-footer a {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
}

.notification-footer a:hover {
    text-decoration: underline;
}

/* Mobile adjustments */
@media (max-width: 768px) {
    .notification-dropdown {
        position: fixed;
        top: 60px;
        right: 0.5rem;
        left: 0.5rem;
        width: auto;
        max-height: calc(100vh - 140px);
    }
}
</style>

<script>
// Notification Widget JavaScript
let notificationDropdownOpen = false;

function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    notificationDropdownOpen = !notificationDropdownOpen;

    if (notificationDropdownOpen) {
        dropdown.classList.add('open');
        loadNotifications();
    } else {
        dropdown.classList.remove('open');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const wrapper = document.querySelector('.notification-wrapper');
    if (wrapper && !wrapper.contains(e.target) && notificationDropdownOpen) {
        toggleNotificationDropdown();
    }
});

function loadNotifications() {
    const list = document.getElementById('notificationList');
    list.innerHTML = '<div class="notification-loading">Indlaeser...</div>';

    fetch('<?php echo $base ?? ''; ?>api/notifications.php?action=get&limit=10')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderNotifications(data.notifications);
                updateBadge(data.unread_count);
            } else {
                list.innerHTML = '<div class="notification-empty"><div class="notification-empty-icon">📭</div>Ingen notifikationer</div>';
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
            list.innerHTML = '<div class="notification-empty">Fejl ved indlaesning</div>';
        });
}

function renderNotifications(notifications) {
    const list = document.getElementById('notificationList');

    if (!notifications || notifications.length === 0) {
        list.innerHTML = '<div class="notification-empty"><div class="notification-empty-icon">📭</div>Ingen nye notifikationer</div>';
        return;
    }

    list.innerHTML = notifications.map(n => `
        <a href="${n.link || '#'}" class="notification-item unread" data-id="${n.id}" onclick="markNotificationRead(${n.id})">
            <div class="notification-icon ${getIconClass(n.type)}">
                ${getIcon(n.type)}
            </div>
            <div class="notification-content">
                <p class="notification-title">${escapeHtml(n.title)}</p>
                ${n.message ? `<p class="notification-message">${escapeHtml(n.message)}</p>` : ''}
                <span class="notification-time">${formatTime(n.created_at)}</span>
            </div>
        </a>
    `).join('');
}

function getIconClass(type) {
    switch (type) {
        case 'approval': return 'approval';
        case 'warning': return 'warning';
        default: return 'info';
    }
}

function getIcon(type) {
    switch (type) {
        case 'approval': return '✓';
        case 'new_ptw': return '📋';
        case 'warning': return '⚠️';
        default: return '🔔';
    }
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;

    if (diff < 60000) return 'Lige nu';
    if (diff < 3600000) return Math.floor(diff / 60000) + ' min siden';
    if (diff < 86400000) return Math.floor(diff / 3600000) + ' timer siden';
    return date.toLocaleDateString('da-DK');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function markNotificationRead(id) {
    fetch('<?php echo $base ?? ''; ?>api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=mark_read&id=${id}`
    }).then(() => {
        const item = document.querySelector(`[data-id="${id}"]`);
        if (item) item.classList.remove('unread');
        updateBadgeCount(-1);
    });
}

function markAllNotificationsRead() {
    fetch('<?php echo $base ?? ''; ?>api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_all_read'
    }).then(() => {
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.classList.remove('unread');
        });
        updateBadge(0);
    });
}

function updateBadge(count) {
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

function updateBadgeCount(delta) {
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        let count = parseInt(badge.textContent) || 0;
        count = Math.max(0, count + delta);
        updateBadge(count);
    }
}

// Refresh badge count periodically
setInterval(() => {
    if (!notificationDropdownOpen) {
        fetch('<?php echo $base ?? ''; ?>api/notifications.php?action=count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateBadge(data.count);
                }
            })
            .catch(() => {});
    }
}, 60000); // Every minute
</script>
