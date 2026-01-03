<!-- PWA Meta Tags -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="PTW">
<meta name="application-name" content="PTW">
<meta name="theme-color" content="#1e40af">
<meta name="msapplication-TileColor" content="#1e40af">
<meta name="msapplication-tap-highlight" content="no">

<!-- PWA Icons -->
<link rel="icon" type="image/png" sizes="192x192" href="icon-192.png">
<link rel="icon" type="image/png" sizes="512x512" href="icon-512.png">
<link rel="apple-touch-icon" href="icon-192.png">
<link rel="apple-touch-icon" sizes="192x192" href="icon-192.png">
<link rel="apple-touch-icon" sizes="512x512" href="icon-512.png">

<!-- PWA Manifest -->
<link rel="manifest" href="manifest.json">

<?php
// Determine base path for JS files
$js_base = '';
if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $js_base = '../';
}
?>

<!-- Mobile Experience Scripts -->
<script src="<?php echo $js_base; ?>js/swipe.js" defer></script>
<script src="<?php echo $js_base; ?>js/pull-refresh.js" defer></script>
<script src="<?php echo $js_base; ?>js/infinite-scroll.js" defer></script>
<script src="<?php echo $js_base; ?>js/sse.js" defer></script>
<script src="<?php echo $js_base; ?>js/tutorial.js" defer></script>
<?php
$modules = include __DIR__ . '/config/modules.php';
if ($modules['voice_assistant'] ?? false):
?>
<script src="<?php echo $js_base; ?>js/voice-assistant.js" defer></script>
<?php endif; ?>

<!-- Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
        try {
            // Get base path for service worker
            const swPath = '<?php echo $js_base; ?>service-worker.js';

            // Register service worker
            const registration = await navigator.serviceWorker.register(swPath, {
                scope: '<?php echo $js_base ?: "/"; ?>'
            });

            console.log('[PWA] Service Worker registered:', registration.scope);

            // Check for updates periodically
            setInterval(() => {
                registration.update();
            }, 60 * 60 * 1000); // Check every hour

            // Handle updates
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                console.log('[PWA] New Service Worker found');

                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        // New version available
                        console.log('[PWA] New version available');
                        showUpdateNotification();
                    }
                });
            });

        } catch (error) {
            console.log('[PWA] Service Worker registration failed:', error);
        }
    });

    // Show update notification
    function showUpdateNotification() {
        const notification = document.createElement('div');
        notification.className = 'sw-update-notification';
        notification.innerHTML = `
            <span>En ny version er tilgaengelig</span>
            <button onclick="location.reload()">Opdater</button>
            <button onclick="this.parentElement.remove()">Senere</button>
        `;
        notification.style.cssText = `
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: #1e40af;
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            display: flex;
            gap: 0.75rem;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10000;
            font-size: 0.9rem;
        `;
        notification.querySelectorAll('button').forEach(btn => {
            btn.style.cssText = `
                background: rgba(255,255,255,0.2);
                border: none;
                color: white;
                padding: 0.4rem 0.75rem;
                border-radius: 4px;
                cursor: pointer;
                font-size: 0.85rem;
            `;
        });
        document.body.appendChild(notification);
    }
}
</script>
