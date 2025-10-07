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
<link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/icon-512.png">
<link rel="apple-touch-icon" href="/icon-192.png">
<link rel="apple-touch-icon" sizes="192x192" href="/icon-192.png">
<link rel="apple-touch-icon" sizes="512x512" href="/icon-512.png">

<!-- PWA Manifest -->
<link rel="manifest" href="/manifest.json">

<!-- Service Worker Unregistration (temporarily disabled) -->
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    // Unregister any existing service workers
    const registrations = await navigator.serviceWorker.getRegistrations();
    for (let registration of registrations) {
      await registration.unregister();
      console.log('ServiceWorker unregistered');
    }
    
    // Clear all caches
    const cacheNames = await caches.keys();
    for (let cacheName of cacheNames) {
      await caches.delete(cacheName);
      console.log('Cache cleared:', cacheName);
    }
  });
}
</script>
