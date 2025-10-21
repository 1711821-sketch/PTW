// Push Notification Manager
const PushNotificationManager = {
    publicKey: 'BNpKU4mtC8ncduygP2NhC05lf6v-6kXtXIynIFjJVU6f217ki7mZTTDcSrywmXdEEfvWSKxBQv4tsAYrK1CJwBs',
    
    // Convert VAPID key from base64 to Uint8Array
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');
        
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    },
    
    // Check if push notifications are supported
    isSupported() {
        return 'serviceWorker' in navigator && 
               'PushManager' in window && 
               'Notification' in window;
    },
    
    // Get current notification permission status
    getPermission() {
        if (!('Notification' in window)) {
            return 'unsupported';
        }
        return Notification.permission;
    },
    
    // Request notification permission
    async requestPermission() {
        if (!this.isSupported()) {
            throw new Error('Push notifications are not supported');
        }
        
        const permission = await Notification.requestPermission();
        return permission === 'granted';
    },
    
    // Subscribe to push notifications
    async subscribe() {
        try {
            if (!this.isSupported()) {
                throw new Error('Push notifications are not supported');
            }
            
            // Request permission first
            const permissionGranted = await this.requestPermission();
            if (!permissionGranted) {
                throw new Error('Notification permission denied');
            }
            
            // Get service worker registration
            const registration = await navigator.serviceWorker.ready;
            
            // Subscribe to push manager
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.publicKey)
            });
            
            // Send subscription to server
            const response = await fetch('/push_subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ subscription })
            });
            
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.error || 'Failed to save subscription');
            }
            
            console.log('Push subscription successful');
            return true;
            
        } catch (error) {
            console.error('Push subscription error:', error);
            throw error;
        }
    },
    
    // Unsubscribe from push notifications
    async unsubscribe() {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            
            if (!subscription) {
                return true;
            }
            
            // Unsubscribe from push manager
            await subscription.unsubscribe();
            
            // Notify server
            await fetch('/push_unsubscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ endpoint: subscription.endpoint })
            });
            
            console.log('Push unsubscription successful');
            return true;
            
        } catch (error) {
            console.error('Push unsubscription error:', error);
            throw error;
        }
    },
    
    // Check if user is currently subscribed
    async isSubscribed() {
        try {
            if (!this.isSupported()) {
                return false;
            }
            
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            return subscription !== null;
            
        } catch (error) {
            console.error('Error checking subscription:', error);
            return false;
        }
    },
    
    // Show notification banner/prompt
    showNotificationPrompt() {
        const permission = this.getPermission();
        
        if (permission === 'denied') {
            alert('Notifikationer er blokeret. Aktiver dem i browserindstillinger for at modtage opdateringer om PTW-godkendelser.');
            return;
        }
        
        if (permission === 'granted') {
            this.subscribe().catch(err => {
                alert('Kunne ikke aktivere notifikationer: ' + err.message);
            });
        } else {
            // Show custom prompt
            const banner = document.createElement('div');
            banner.id = 'notification-banner';
            banner.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: #007bff;
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 10000;
                max-width: 500px;
                text-align: center;
            `;
            
            banner.innerHTML = `
                <p style="margin: 0 0 0.5rem 0; font-weight: 500;">🔔 Modtag notifikationer om PTW-godkendelser</p>
                <p style="margin: 0 0 1rem 0; font-size: 0.9em;">Bliv notificeret når dine arbejdstilladelser bliver godkendt.</p>
                <button id="enable-notifications" style="
                    background: white;
                    color: #007bff;
                    border: none;
                    padding: 0.5rem 1.5rem;
                    border-radius: 4px;
                    font-weight: 600;
                    cursor: pointer;
                    margin-right: 0.5rem;
                ">Aktiver</button>
                <button id="dismiss-notifications" style="
                    background: transparent;
                    color: white;
                    border: 1px solid white;
                    padding: 0.5rem 1.5rem;
                    border-radius: 4px;
                    cursor: pointer;
                ">Senere</button>
            `;
            
            document.body.appendChild(banner);
            
            document.getElementById('enable-notifications').addEventListener('click', async () => {
                banner.remove();
                try {
                    await this.subscribe();
                    alert('✅ Notifikationer er aktiveret!');
                } catch (err) {
                    alert('Kunne ikke aktivere notifikationer: ' + err.message);
                }
            });
            
            document.getElementById('dismiss-notifications').addEventListener('click', () => {
                banner.remove();
                localStorage.setItem('notification-prompt-dismissed', Date.now());
            });
        }
    }
};

// Auto-prompt for entrepreneurs after 5 seconds if not already subscribed
document.addEventListener('DOMContentLoaded', async () => {
    // Only show for entrepreneurs (normalize text for case-insensitive match)
    const userRole = document.querySelector('.nav-user')?.textContent;
    const normalizedRole = userRole?.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    if (!normalizedRole || !normalizedRole.includes('entreprenor')) {
        return;
    }
    
    // Check if already subscribed
    const isSubscribed = await PushNotificationManager.isSubscribed();
    if (isSubscribed) {
        return;
    }
    
    // Check if user dismissed recently (within last 7 days)
    const dismissedAt = localStorage.getItem('notification-prompt-dismissed');
    if (dismissedAt && (Date.now() - parseInt(dismissedAt)) < 7 * 24 * 60 * 60 * 1000) {
        return;
    }
    
    // Show prompt after 5 seconds
    setTimeout(() => {
        if (PushNotificationManager.isSupported()) {
            PushNotificationManager.showNotificationPrompt();
        }
    }, 5000);
});

// Expose to window for manual triggering
window.PushNotificationManager = PushNotificationManager;
