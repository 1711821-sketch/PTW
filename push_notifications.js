// Push Notification Management
// Handles subscription and permission requests for web push notifications

const VAPID_PUBLIC_KEY = 'BNpKU4mtC8ncduygP2NhC05lf6v-6kXtXIynIFjJVU6f217ki7mZTTDcSrywmXdEEfvWSKxBQv4tsAYrK1CJwBs';

// Convert VAPID key from base64 to Uint8Array
function urlBase64ToUint8Array(base64String) {
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
}

// Check if push notifications are supported
function isPushNotificationSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

// Get current subscription status
async function getPushSubscription() {
    if (!isPushNotificationSupported()) {
        return null;
    }
    
    try {
        const registration = await navigator.serviceWorker.ready;
        return await registration.pushManager.getSubscription();
    } catch (error) {
        console.error('Error getting push subscription:', error);
        return null;
    }
}

// Subscribe to push notifications
async function subscribeToPush() {
    if (!isPushNotificationSupported()) {
        console.log('Push notifications are not supported');
        return false;
    }
    
    try {
        // Request notification permission
        const permission = await Notification.requestPermission();
        
        if (permission !== 'granted') {
            console.log('Notification permission denied');
            return false;
        }
        
        // Wait for service worker to be ready
        const registration = await navigator.serviceWorker.ready;
        
        // Subscribe to push manager
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
        });
        
        // Send subscription to backend
        const response = await fetch('/push_subscribe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(subscription.toJSON())
        });
        
        if (!response.ok) {
            throw new Error('Failed to save subscription');
        }
        
        const data = await response.json();
        console.log('Push subscription saved:', data);
        
        // Update UI
        updateNotificationUI(true);
        
        return true;
        
    } catch (error) {
        console.error('Error subscribing to push:', error);
        return false;
    }
}

// Unsubscribe from push notifications
async function unsubscribeFromPush() {
    try {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        
        if (subscription) {
            await subscription.unsubscribe();
            console.log('Unsubscribed from push notifications');
        }
        
        updateNotificationUI(false);
        return true;
        
    } catch (error) {
        console.error('Error unsubscribing from push:', error);
        return false;
    }
}

// Update UI based on subscription status
function updateNotificationUI(isSubscribed) {
    const enableBtn = document.getElementById('enable-notifications-btn');
    const disableBtn = document.getElementById('disable-notifications-btn');
    const statusText = document.getElementById('notification-status');
    
    if (enableBtn && disableBtn) {
        if (isSubscribed) {
            enableBtn.style.display = 'none';
            disableBtn.style.display = 'inline-block';
            if (statusText) {
                statusText.textContent = '✅ Notifikationer er aktiveret';
                statusText.style.color = '#28a745';
            }
        } else {
            enableBtn.style.display = 'inline-block';
            disableBtn.style.display = 'none';
            if (statusText) {
                statusText.textContent = '🔔 Aktiver notifikationer for at modtage opdateringer';
                statusText.style.color = '#666';
            }
        }
    }
}

// Initialize push notifications on page load
async function initializePushNotifications() {
    if (!isPushNotificationSupported()) {
        console.log('Push notifications not supported in this browser');
        return;
    }
    
    // Check current subscription status
    const subscription = await getPushSubscription();
    updateNotificationUI(subscription !== null);
    
    // Add event listeners to buttons
    const enableBtn = document.getElementById('enable-notifications-btn');
    const disableBtn = document.getElementById('disable-notifications-btn');
    
    if (enableBtn) {
        enableBtn.addEventListener('click', async () => {
            enableBtn.disabled = true;
            enableBtn.textContent = 'Aktiverer...';
            
            await subscribeToPush();
            
            enableBtn.disabled = false;
            enableBtn.textContent = '🔔 Aktiver notifikationer';
        });
    }
    
    if (disableBtn) {
        disableBtn.addEventListener('click', async () => {
            if (confirm('Er du sikker på, at du vil deaktivere notifikationer?')) {
                disableBtn.disabled = true;
                disableBtn.textContent = 'Deaktiverer...';
                
                await unsubscribeFromPush();
                
                disableBtn.disabled = false;
                disableBtn.textContent = '🔕 Deaktiver notifikationer';
            }
        });
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePushNotifications);
} else {
    initializePushNotifications();
}
