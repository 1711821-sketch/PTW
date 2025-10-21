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

<!-- Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    try {
      const registration = await navigator.serviceWorker.register('/service-worker.js');
      console.log('ServiceWorker registered:', registration);
    } catch (error) {
      console.error('ServiceWorker registration failed:', error);
    }
  });
}
</script>
