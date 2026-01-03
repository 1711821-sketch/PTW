/**
 * Server-Sent Events Client for PTW System
 * Handles real-time updates for approvals and notifications
 */

(function() {
    'use strict';

    class SSEClient {
        constructor(options = {}) {
            this.endpoint = options.endpoint || '/api/sse_handler.php';
            this.reconnectDelay = options.reconnectDelay || 3000;
            this.maxReconnectAttempts = options.maxReconnectAttempts || 10;

            this.eventSource = null;
            this.reconnectAttempts = 0;
            this.lastEventTime = Date.now();
            this.isConnected = false;

            // Event handlers
            this.handlers = {
                approval_update: [],
                status_change: [],
                new_ptw: [],
                notification: [],
                connected: [],
                disconnected: [],
                error: []
            };

            this.init();
        }

        init() {
            // Only connect if user is authenticated (check for session indicator)
            if (document.querySelector('.navbar') || document.querySelector('.bottom-nav')) {
                this.connect();
            }

            // Handle page visibility changes
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    if (!this.isConnected) {
                        this.connect();
                    }
                }
            });

            // Reconnect on online event
            window.addEventListener('online', () => {
                if (!this.isConnected) {
                    this.connect();
                }
            });
        }

        connect() {
            if (this.eventSource) {
                this.eventSource.close();
            }

            try {
                const url = `${this.endpoint}?last=${Math.floor(this.lastEventTime / 1000)}`;
                this.eventSource = new EventSource(url);

                this.eventSource.onopen = () => {
                    console.log('[SSE] Connected');
                    this.isConnected = true;
                    this.reconnectAttempts = 0;
                    this.emit('connected', { timestamp: Date.now() });
                };

                this.eventSource.onerror = (error) => {
                    console.log('[SSE] Connection error');
                    this.isConnected = false;
                    this.emit('error', error);
                    this.handleReconnect();
                };

                // Handle specific events
                this.eventSource.addEventListener('approval_update', (e) => {
                    this.handleEvent('approval_update', e);
                });

                this.eventSource.addEventListener('status_change', (e) => {
                    this.handleEvent('status_change', e);
                });

                this.eventSource.addEventListener('new_ptw', (e) => {
                    this.handleEvent('new_ptw', e);
                });

                this.eventSource.addEventListener('notification', (e) => {
                    this.handleEvent('notification', e);
                });

                this.eventSource.addEventListener('heartbeat', (e) => {
                    this.lastEventTime = Date.now();
                });

                this.eventSource.addEventListener('disconnect', (e) => {
                    console.log('[SSE] Server requested disconnect');
                    this.eventSource.close();
                    this.isConnected = false;
                    // Reconnect after short delay
                    setTimeout(() => this.connect(), 1000);
                });

            } catch (error) {
                console.error('[SSE] Failed to connect:', error);
                this.handleReconnect();
            }
        }

        handleEvent(type, event) {
            try {
                const data = JSON.parse(event.data);
                this.lastEventTime = Date.now();

                if (Array.isArray(data) && data.length > 0) {
                    this.emit(type, data);
                    this.showNotificationForEvent(type, data);
                }
            } catch (error) {
                console.error('[SSE] Failed to parse event:', error);
            }
        }

        handleReconnect() {
            if (this.reconnectAttempts >= this.maxReconnectAttempts) {
                console.log('[SSE] Max reconnect attempts reached');
                this.emit('disconnected', { reason: 'max_attempts' });
                return;
            }

            this.reconnectAttempts++;
            const delay = this.reconnectDelay * Math.pow(1.5, this.reconnectAttempts - 1);

            console.log(`[SSE] Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);

            setTimeout(() => {
                if (!this.isConnected) {
                    this.connect();
                }
            }, delay);
        }

        showNotificationForEvent(type, data) {
            // Use existing notification system if available
            if (typeof showNotification !== 'function') return;

            switch (type) {
                case 'approval_update':
                    data.forEach(item => {
                        showNotification(`PTW ${item.work_order_no} er blevet godkendt`, 'success');
                    });
                    break;

                case 'new_ptw':
                    data.forEach(item => {
                        showNotification(`Ny PTW oprettet: ${item.work_order_no}`, 'info');
                    });
                    break;

                case 'notification':
                    data.forEach(item => {
                        showNotification(item.title || item.message, 'info');
                    });
                    break;
            }

            // Update notification badge
            this.updateNotificationBadge();
        }

        updateNotificationBadge() {
            const badge = document.querySelector('.notification-badge');
            if (badge) {
                const currentCount = parseInt(badge.textContent) || 0;
                badge.textContent = currentCount + 1;
                badge.style.display = 'flex';
            }
        }

        // Event emitter methods
        on(event, handler) {
            if (this.handlers[event]) {
                this.handlers[event].push(handler);
            }
            return this;
        }

        off(event, handler) {
            if (this.handlers[event]) {
                this.handlers[event] = this.handlers[event].filter(h => h !== handler);
            }
            return this;
        }

        emit(event, data) {
            if (this.handlers[event]) {
                this.handlers[event].forEach(handler => {
                    try {
                        handler(data);
                    } catch (error) {
                        console.error('[SSE] Handler error:', error);
                    }
                });
            }
        }

        disconnect() {
            if (this.eventSource) {
                this.eventSource.close();
                this.eventSource = null;
            }
            this.isConnected = false;
            this.emit('disconnected', { reason: 'manual' });
        }
    }

    // Auto-refresh cards when approval updates received
    function handleApprovalUpdate(updates) {
        updates.forEach(update => {
            const card = document.querySelector(`[data-wo-id="${update.id}"]`);
            if (card) {
                // Add highlight animation
                card.classList.add('card-updated');
                setTimeout(() => card.classList.remove('card-updated'), 2000);
            }
        });
    }

    // Initialize SSE client
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSSE);
        } else {
            initSSE();
        }
    }

    function initSSE() {
        // Only initialize on authenticated pages
        if (!document.querySelector('.navbar')) return;

        window.sseClient = new SSEClient({
            endpoint: getBasePath() + 'api/sse_handler.php',
            reconnectDelay: 3000,
            maxReconnectAttempts: 10
        });

        // Register default handlers
        window.sseClient.on('approval_update', handleApprovalUpdate);

        // Refresh page data on significant updates
        window.sseClient.on('new_ptw', () => {
            // If on view_wo.php, could trigger refresh
            if (window.location.pathname.includes('view_wo.php')) {
                // Optional: show "New PTWs available" banner
                showNewDataBanner();
            }
        });
    }

    function getBasePath() {
        const path = window.location.pathname;
        if (path.includes('/admin/')) {
            return '../';
        }
        return '';
    }

    function showNewDataBanner() {
        if (document.querySelector('.new-data-banner')) return;

        const banner = document.createElement('div');
        banner.className = 'new-data-banner';
        banner.innerHTML = `
            <span>Nye opdateringer tilgaengelige</span>
            <button onclick="location.reload()">Opdater</button>
            <button onclick="this.parentElement.remove()">×</button>
        `;
        banner.style.cssText = `
            position: fixed;
            top: 70px;
            left: 50%;
            transform: translateX(-50%);
            background: #1e40af;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: flex;
            gap: 0.75rem;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 9999;
            font-size: 0.9rem;
            animation: slideDown 0.3s ease;
        `;

        document.body.appendChild(banner);

        // Auto-remove after 30 seconds
        setTimeout(() => {
            if (banner.parentElement) {
                banner.remove();
            }
        }, 30000);
    }

    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }

        .card-updated {
            animation: cardPulse 0.5s ease;
        }

        @keyframes cardPulse {
            0%, 100% {
                box-shadow: var(--shadow-md);
            }
            50% {
                box-shadow: 0 0 20px rgba(34, 197, 94, 0.5);
            }
        }

        .new-data-banner button {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .new-data-banner button:hover {
            background: rgba(255,255,255,0.3);
        }
    `;
    document.head.appendChild(style);

    // Run initialization
    init();

    // Expose for external use
    window.SSEClient = SSEClient;

})();
