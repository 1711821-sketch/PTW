/**
 * Pull to Refresh functionality
 * Enables native-like pull-to-refresh on mobile devices
 */

(function() {
    'use strict';

    const PULL_THRESHOLD = 80; // Distance to trigger refresh
    const MAX_PULL = 150; // Maximum pull distance

    class PullToRefresh {
        constructor(options = {}) {
            this.options = {
                container: options.container || document.body,
                onRefresh: options.onRefresh || this.defaultRefresh,
                pullText: options.pullText || 'Træk ned for at opdatere',
                releaseText: options.releaseText || 'Slip for at opdatere',
                refreshingText: options.refreshingText || 'Opdaterer...',
                ...options
            };

            this.pullElement = null;
            this.startY = 0;
            this.currentY = 0;
            this.isPulling = false;
            this.isRefreshing = false;
            this.canPull = true;

            this.init();
        }

        init() {
            // Only enable on touch devices
            if (!('ontouchstart' in window)) return;

            this.createPullElement();
            this.bindEvents();
        }

        createPullElement() {
            this.pullElement = document.createElement('div');
            this.pullElement.className = 'pull-to-refresh';
            this.pullElement.innerHTML = `
                <div class="pull-to-refresh-content">
                    <svg class="pull-to-refresh-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span class="pull-to-refresh-text">${this.options.pullText}</span>
                </div>
            `;

            document.body.insertBefore(this.pullElement, document.body.firstChild);
        }

        bindEvents() {
            document.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: true });
            document.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
            document.addEventListener('touchend', this.handleTouchEnd.bind(this), { passive: true });
        }

        handleTouchStart(e) {
            // Only allow pull when at top of page
            if (window.scrollY > 0 || this.isRefreshing) {
                this.canPull = false;
                return;
            }

            this.canPull = true;
            this.startY = e.touches[0].clientY;
            this.isPulling = false;
        }

        handleTouchMove(e) {
            if (!this.canPull || this.isRefreshing) return;

            const currentY = e.touches[0].clientY;
            const deltaY = currentY - this.startY;

            // Only activate when pulling down
            if (deltaY < 0) {
                this.isPulling = false;
                return;
            }

            // Check if page is at top
            if (window.scrollY > 0) {
                this.isPulling = false;
                return;
            }

            // Start pulling
            if (deltaY > 10 && !this.isPulling) {
                this.isPulling = true;
                this.pullElement.classList.add('pulling');
            }

            if (this.isPulling) {
                e.preventDefault();

                // Apply resistance to pull
                const resistance = deltaY > PULL_THRESHOLD ? 0.3 : 0.6;
                this.currentY = Math.min(deltaY * resistance, MAX_PULL);

                // Update pull element position
                this.pullElement.style.setProperty('--pull-distance', `${this.currentY}px`);
                this.pullElement.style.setProperty('--pull-progress', Math.min(this.currentY / PULL_THRESHOLD, 1));

                // Update text based on threshold
                const textElement = this.pullElement.querySelector('.pull-to-refresh-text');
                if (this.currentY >= PULL_THRESHOLD) {
                    textElement.textContent = this.options.releaseText;
                } else {
                    textElement.textContent = this.options.pullText;
                }
            }
        }

        handleTouchEnd(e) {
            if (!this.isPulling) return;

            this.isPulling = false;
            this.pullElement.classList.remove('pulling');

            if (this.currentY >= PULL_THRESHOLD) {
                this.triggerRefresh();
            } else {
                this.resetPull();
            }
        }

        triggerRefresh() {
            this.isRefreshing = true;
            this.pullElement.classList.add('visible', 'refreshing');

            const textElement = this.pullElement.querySelector('.pull-to-refresh-text');
            textElement.textContent = this.options.refreshingText;

            // Call refresh handler
            Promise.resolve(this.options.onRefresh())
                .then(() => {
                    this.completeRefresh();
                })
                .catch((error) => {
                    console.error('Refresh failed:', error);
                    this.completeRefresh();
                });
        }

        completeRefresh() {
            setTimeout(() => {
                this.resetPull();
                this.isRefreshing = false;
            }, 500);
        }

        resetPull() {
            this.pullElement.classList.remove('visible', 'pulling', 'refreshing');
            this.pullElement.style.setProperty('--pull-distance', '0px');
            this.pullElement.style.setProperty('--pull-progress', '0');
            this.currentY = 0;
        }

        defaultRefresh() {
            // Default behavior: reload page
            return new Promise((resolve) => {
                window.location.reload();
                resolve();
            });
        }

        // Manual refresh trigger
        refresh() {
            if (!this.isRefreshing) {
                this.triggerRefresh();
            }
        }
    }

    /**
     * Initialize pull-to-refresh on pages with lists
     */
    function init() {
        // Only enable on specific pages with list content
        const listPages = ['view_wo.php', 'view_sja.php', 'dashboard.php'];
        const currentPage = window.location.pathname.split('/').pop();

        if (!listPages.includes(currentPage)) return;

        // Create pull-to-refresh with AJAX refresh
        const ptr = new PullToRefresh({
            onRefresh: () => {
                return new Promise((resolve) => {
                    // Try to refresh content via AJAX if available
                    if (typeof refreshWorkOrders === 'function') {
                        refreshWorkOrders().then(resolve);
                    } else if (typeof loadContent === 'function') {
                        loadContent().then(resolve);
                    } else {
                        // Fallback: reload page
                        window.location.reload();
                        resolve();
                    }
                });
            }
        });

        // Expose for external use
        window.pullToRefresh = ptr;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose class for custom implementations
    window.PullToRefresh = PullToRefresh;

})();
