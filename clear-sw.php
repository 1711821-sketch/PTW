<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ryd Service Worker Cache</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            text-align: center;
        }
        .button {
            background: #1e40af;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            margin: 10px;
        }
        .button:hover {
            background: #1e3a8a;
        }
        .status {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
        }
        .success {
            background: #dcfce7;
            color: #166534;
        }
        .info {
            background: #dbeafe;
            color: #1e40af;
        }
    </style>
</head>
<body>
    <h1>🔧 Ryd Service Worker Cache</h1>
    <p>Hvis websitet ikke loader korrekt, kan du rydde Service Worker cachen her.</p>
    
    <button class="button" onclick="clearServiceWorker()">Ryd Service Worker</button>
    <button class="button" onclick="window.location.href='view_wo.php'">Gå til PTW-oversigt</button>
    
    <div id="status"></div>

    <script>
        async function clearServiceWorker() {
            const statusDiv = document.getElementById('status');
            
            try {
                // Unregister all service workers
                if ('serviceWorker' in navigator) {
                    const registrations = await navigator.serviceWorker.getRegistrations();
                    
                    if (registrations.length === 0) {
                        statusDiv.className = 'status info';
                        statusDiv.innerHTML = '✅ Ingen Service Workers fundet. Alt er OK!';
                        return;
                    }
                    
                    for (let registration of registrations) {
                        await registration.unregister();
                    }
                    
                    // Clear all caches
                    const cacheNames = await caches.keys();
                    for (let cacheName of cacheNames) {
                        await caches.delete(cacheName);
                    }
                    
                    statusDiv.className = 'status success';
                    statusDiv.innerHTML = `
                        ✅ Service Worker og cache ryddet!<br>
                        <strong>Trin 1:</strong> Luk denne fane helt<br>
                        <strong>Trin 2:</strong> Åbn en ny fane og gå til websitet igen
                    `;
                } else {
                    statusDiv.className = 'status info';
                    statusDiv.innerHTML = '❌ Din browser understøtter ikke Service Workers';
                }
            } catch (error) {
                statusDiv.className = 'status info';
                statusDiv.innerHTML = '⚠️ Fejl: ' + error.message;
            }
        }
        
        // Auto-check on load
        window.addEventListener('load', async () => {
            if ('serviceWorker' in navigator) {
                const registrations = await navigator.serviceWorker.getRegistrations();
                const statusDiv = document.getElementById('status');
                
                if (registrations.length > 0) {
                    statusDiv.className = 'status info';
                    statusDiv.innerHTML = `⚠️ Fundet ${registrations.length} Service Worker(s). Klik på knappen for at rydde.`;
                }
            }
        });
    </script>
</body>
</html>
