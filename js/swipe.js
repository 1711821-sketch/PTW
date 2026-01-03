/**
 * Swipe Gestures for PTW Cards
 * Enables swipe-to-approve and swipe-to-view-details on mobile
 */

(function() {
    'use strict';

    const SWIPE_THRESHOLD = 80; // Minimum swipe distance to trigger action
    const SWIPE_VELOCITY_THRESHOLD = 0.3; // Minimum velocity for quick swipes

    class SwipeCard {
        constructor(element, options = {}) {
            this.element = element;
            this.content = element.querySelector('.swipe-card-content') || element;
            this.options = {
                onSwipeLeft: options.onSwipeLeft || null,
                onSwipeRight: options.onSwipeRight || null,
                leftAction: options.leftAction || 'approve',
                rightAction: options.rightAction || 'details',
                ...options
            };

            this.startX = 0;
            this.startY = 0;
            this.currentX = 0;
            this.startTime = 0;
            this.isDragging = false;
            this.isHorizontalSwipe = null;

            this.init();
        }

        init() {
            // Only enable on touch devices
            if (!('ontouchstart' in window)) return;

            this.element.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: true });
            this.element.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
            this.element.addEventListener('touchend', this.handleTouchEnd.bind(this), { passive: true });
            this.element.addEventListener('touchcancel', this.handleTouchEnd.bind(this), { passive: true });
        }

        handleTouchStart(e) {
            if (e.touches.length !== 1) return;

            const touch = e.touches[0];
            this.startX = touch.clientX;
            this.startY = touch.clientY;
            this.currentX = 0;
            this.startTime = Date.now();
            this.isDragging = true;
            this.isHorizontalSwipe = null;

            this.element.classList.add('swiping');
        }

        handleTouchMove(e) {
            if (!this.isDragging || e.touches.length !== 1) return;

            const touch = e.touches[0];
            const deltaX = touch.clientX - this.startX;
            const deltaY = touch.clientY - this.startY;

            // Determine swipe direction on first significant movement
            if (this.isHorizontalSwipe === null && (Math.abs(deltaX) > 10 || Math.abs(deltaY) > 10)) {
                this.isHorizontalSwipe = Math.abs(deltaX) > Math.abs(deltaY);
            }

            // If vertical scroll, let it happen
            if (this.isHorizontalSwipe === false) {
                this.element.classList.remove('swiping');
                return;
            }

            // Prevent vertical scroll during horizontal swipe
            if (this.isHorizontalSwipe) {
                e.preventDefault();
            }

            this.currentX = deltaX;

            // Apply transform with resistance at edges
            const resistance = 0.5;
            const transform = deltaX * resistance;
            this.content.style.transform = `translateX(${transform}px)`;

            // Update visual feedback
            this.updateSwipeFeedback(deltaX);
        }

        handleTouchEnd(e) {
            if (!this.isDragging) return;

            this.isDragging = false;
            this.element.classList.remove('swiping');

            const deltaTime = Date.now() - this.startTime;
            const velocity = Math.abs(this.currentX) / deltaTime;

            // Check if swipe threshold or velocity met
            const isValidSwipe = Math.abs(this.currentX) > SWIPE_THRESHOLD || velocity > SWIPE_VELOCITY_THRESHOLD;

            if (isValidSwipe) {
                if (this.currentX > 0 && this.options.onSwipeRight) {
                    // Swipe right - approve
                    this.animateSwipe('right');
                    this.options.onSwipeRight(this.element);
                } else if (this.currentX < 0 && this.options.onSwipeLeft) {
                    // Swipe left - details
                    this.animateSwipe('left');
                    this.options.onSwipeLeft(this.element);
                } else {
                    this.resetPosition();
                }
            } else {
                this.resetPosition();
            }

            this.clearSwipeFeedback();
        }

        updateSwipeFeedback(deltaX) {
            // Add visual class based on swipe direction
            this.element.classList.remove('swipe-approve', 'swipe-details');

            if (deltaX > SWIPE_THRESHOLD / 2) {
                this.element.classList.add('swipe-approve');
            } else if (deltaX < -SWIPE_THRESHOLD / 2) {
                this.element.classList.add('swipe-details');
            }
        }

        clearSwipeFeedback() {
            this.element.classList.remove('swipe-approve', 'swipe-details');
        }

        animateSwipe(direction) {
            const distance = direction === 'right' ? 100 : -100;
            this.content.style.transition = 'transform 0.3s ease-out, opacity 0.3s ease-out';
            this.content.style.transform = `translateX(${distance}px)`;
            this.content.style.opacity = '0.5';

            setTimeout(() => {
                this.resetPosition();
            }, 300);
        }

        resetPosition() {
            this.content.style.transition = 'transform 0.3s ease-out';
            this.content.style.transform = 'translateX(0)';
            this.content.style.opacity = '1';

            setTimeout(() => {
                this.content.style.transition = '';
            }, 300);
        }
    }

    /**
     * Initialize swipe on all PTW cards
     */
    function initSwipeCards() {
        const cards = document.querySelectorAll('.work-permit-card');

        cards.forEach(card => {
            // Skip if already initialized
            if (card.dataset.swipeInit) return;
            card.dataset.swipeInit = 'true';

            // Add swipe-card class
            card.classList.add('swipe-card');

            // Wrap content if needed
            if (!card.querySelector('.swipe-card-content')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'swipe-card-content';
                while (card.firstChild) {
                    wrapper.appendChild(card.firstChild);
                }
                card.appendChild(wrapper);
            }

            // Get approve button if exists
            const approveBtn = card.querySelector('.approve-btn, [data-action="approve"]');
            const detailsLink = card.querySelector('a[href*="view_wo"], a[href*="detail"]');

            new SwipeCard(card, {
                onSwipeRight: (el) => {
                    // Trigger approve action if button exists and user can approve
                    if (approveBtn && !approveBtn.disabled) {
                        approveBtn.click();
                        showSwipeToast('Godkendelse sendt');
                    }
                },
                onSwipeLeft: (el) => {
                    // Navigate to details
                    if (detailsLink) {
                        window.location.href = detailsLink.href;
                    } else {
                        // Expand card details if available
                        const expandBtn = card.querySelector('.expand-btn, [data-action="expand"]');
                        if (expandBtn) expandBtn.click();
                    }
                }
            });
        });
    }

    /**
     * Show toast notification for swipe actions
     */
    function showSwipeToast(message) {
        // Use existing toast system if available
        if (typeof showToast === 'function') {
            showToast(message, 'success');
            return;
        }

        // Create simple toast
        const toast = document.createElement('div');
        toast.className = 'swipe-toast';
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 0.9rem;
            z-index: 10000;
            animation: fadeInUp 0.3s ease-out;
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'fadeIn 0.3s ease-out reverse forwards';
            setTimeout(() => toast.remove(), 300);
        }, 2000);
    }

    /**
     * Initialize on DOM ready and after AJAX updates
     */
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSwipeCards);
        } else {
            initSwipeCards();
        }

        // Re-initialize after AJAX content updates
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.addedNodes.length) {
                    initSwipeCards();
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Run initialization
    init();

    // Expose for external use
    window.SwipeCard = SwipeCard;
    window.initSwipeCards = initSwipeCards;

})();
