/**
 * Tutorial System for Sikkerjob
 * Provides guided tours, contextual tooltips, and progress tracking
 */

(function() {
    'use strict';

    const STORAGE_KEYS = {
        COMPLETED_TOURS: 'sikkerjob-completed-tours',
        DISMISSED_TOOLTIPS: 'sikkerjob-dismissed-tooltips',
        VIDEO_PROGRESS: 'sikkerjob-video-progress'
    };

    /**
     * GuidedTour Class - Step-by-step walkthrough
     */
    class GuidedTour {
        constructor(tourId, steps, options = {}) {
            this.tourId = tourId;
            this.steps = steps;
            this.currentStep = 0;
            this.options = {
                onComplete: options.onComplete || null,
                onSkip: options.onSkip || null,
                overlayColor: 'rgba(0, 0, 0, 0.75)',
                highlightPadding: 8,
                animationDuration: 300,
                ...options
            };
            this.overlay = null;
            this.tooltip = null;
            this.isActive = false;
            this.boundKeyHandler = this.handleKeyDown.bind(this);
        }

        start() {
            if (this.isActive || this.steps.length === 0) return;
            this.isActive = true;
            document.addEventListener('keydown', this.boundKeyHandler);
            this.createOverlay();
            this.showStep(0);
        }

        createOverlay() {
            this.overlay = document.createElement('div');
            this.overlay.className = 'tour-overlay';
            this.overlay.innerHTML = `
                <svg class="tour-overlay-svg" width="100%" height="100%">
                    <defs>
                        <mask id="tour-mask-${this.tourId}">
                            <rect width="100%" height="100%" fill="white"/>
                            <rect class="tour-highlight-cutout" fill="black" rx="8" ry="8"/>
                        </mask>
                    </defs>
                    <rect width="100%" height="100%" fill="${this.options.overlayColor}"
                          mask="url(#tour-mask-${this.tourId})"/>
                </svg>
            `;
            document.body.appendChild(this.overlay);

            // Click overlay to close
            this.overlay.addEventListener('click', (e) => {
                if (e.target === this.overlay || e.target.classList.contains('tour-overlay-svg')) {
                    this.skip();
                }
            });
        }

        showStep(index) {
            if (index >= this.steps.length) {
                this.complete();
                return;
            }

            this.currentStep = index;
            const step = this.steps[index];

            // Try multiple selectors
            const selectors = step.target.split(',').map(s => s.trim());
            let target = null;

            for (const selector of selectors) {
                target = document.querySelector(selector);
                if (target) break;
            }

            if (!target) {
                console.warn(`Tour target not found: ${step.target}`);
                // Skip to next step
                this.showStep(index + 1);
                return;
            }

            // Scroll element into view
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
                inline: 'center'
            });

            // Wait for scroll, then position highlight and tooltip
            setTimeout(() => {
                this.highlightElement(target);
                this.showTooltip(target, step, index);
            }, 350);
        }

        highlightElement(element) {
            const rect = element.getBoundingClientRect();
            const padding = this.options.highlightPadding;
            const scrollX = window.scrollX || window.pageXOffset;
            const scrollY = window.scrollY || window.pageYOffset;

            const cutout = this.overlay.querySelector('.tour-highlight-cutout');
            cutout.setAttribute('x', rect.left - padding + scrollX);
            cutout.setAttribute('y', rect.top - padding + scrollY);
            cutout.setAttribute('width', rect.width + padding * 2);
            cutout.setAttribute('height', rect.height + padding * 2);

            // Update SVG size to cover full page
            const svg = this.overlay.querySelector('.tour-overlay-svg');
            svg.setAttribute('width', Math.max(document.documentElement.scrollWidth, window.innerWidth));
            svg.setAttribute('height', Math.max(document.documentElement.scrollHeight, window.innerHeight));

            // Add highlight class to element
            element.classList.add('tour-highlighted');
        }

        showTooltip(target, step, index) {
            // Remove existing tooltip
            if (this.tooltip) {
                this.tooltip.remove();
            }

            const isLastStep = index === this.steps.length - 1;
            const isFirstStep = index === 0;

            this.tooltip = document.createElement('div');
            this.tooltip.className = `tour-tooltip tour-tooltip-${step.position || 'bottom'}`;
            this.tooltip.innerHTML = `
                <div class="tour-tooltip-header">
                    <span class="tour-step-indicator">${index + 1} / ${this.steps.length}</span>
                    <button class="tour-close" aria-label="Luk rundvisning">&times;</button>
                </div>
                <h4 class="tour-tooltip-title">${this.escapeHtml(step.title)}</h4>
                <p class="tour-tooltip-content">${this.escapeHtml(step.content)}</p>
                <div class="tour-tooltip-actions">
                    ${!isFirstStep ? '<button class="tour-btn tour-btn-prev">Tilbage</button>' : ''}
                    <button class="tour-btn tour-btn-skip">Spring over</button>
                    <button class="tour-btn tour-btn-next tour-btn-primary">
                        ${isLastStep ? 'Afslut' : 'Naeste'}
                    </button>
                </div>
            `;

            document.body.appendChild(this.tooltip);
            this.positionTooltip(target, step.position || 'bottom');
            this.bindTooltipEvents();
        }

        positionTooltip(target, position) {
            const rect = target.getBoundingClientRect();
            const tooltip = this.tooltip;
            const tooltipRect = tooltip.getBoundingClientRect();
            const margin = 16;
            const scrollX = window.scrollX || window.pageXOffset;
            const scrollY = window.scrollY || window.pageYOffset;

            // Check if mobile
            const isMobile = window.innerWidth < 768;

            if (isMobile) {
                // On mobile, position at bottom of viewport
                tooltip.style.position = 'fixed';
                tooltip.style.bottom = '100px';
                tooltip.style.left = '1rem';
                tooltip.style.right = '1rem';
                tooltip.style.top = 'auto';
                tooltip.style.width = 'auto';
                tooltip.style.maxWidth = 'none';
                return;
            }

            let top, left;

            switch (position) {
                case 'top':
                    top = rect.top - tooltipRect.height - margin + scrollY;
                    left = rect.left + (rect.width - tooltipRect.width) / 2 + scrollX;
                    break;
                case 'bottom':
                    top = rect.bottom + margin + scrollY;
                    left = rect.left + (rect.width - tooltipRect.width) / 2 + scrollX;
                    break;
                case 'left':
                    top = rect.top + (rect.height - tooltipRect.height) / 2 + scrollY;
                    left = rect.left - tooltipRect.width - margin + scrollX;
                    break;
                case 'right':
                    top = rect.top + (rect.height - tooltipRect.height) / 2 + scrollY;
                    left = rect.right + margin + scrollX;
                    break;
                default:
                    top = rect.bottom + margin + scrollY;
                    left = rect.left + (rect.width - tooltipRect.width) / 2 + scrollX;
            }

            // Keep tooltip on screen
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;

            left = Math.max(margin, Math.min(left, viewportWidth - tooltipRect.width - margin + scrollX));
            top = Math.max(margin + scrollY, top);

            // If tooltip goes below viewport, move it above
            if (top + tooltipRect.height > viewportHeight + scrollY - margin) {
                top = rect.top - tooltipRect.height - margin + scrollY;
            }

            tooltip.style.position = 'absolute';
            tooltip.style.top = `${top}px`;
            tooltip.style.left = `${left}px`;
        }

        bindTooltipEvents() {
            this.tooltip.querySelector('.tour-close').addEventListener('click', () => this.skip());
            this.tooltip.querySelector('.tour-btn-skip').addEventListener('click', () => this.skip());
            this.tooltip.querySelector('.tour-btn-next').addEventListener('click', () => this.next());

            const prevBtn = this.tooltip.querySelector('.tour-btn-prev');
            if (prevBtn) {
                prevBtn.addEventListener('click', () => this.prev());
            }
        }

        handleKeyDown(e) {
            if (!this.isActive) return;

            switch (e.key) {
                case 'Escape':
                    e.preventDefault();
                    this.skip();
                    break;
                case 'Enter':
                case 'ArrowRight':
                    e.preventDefault();
                    this.next();
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    this.prev();
                    break;
            }
        }

        next() {
            this.clearHighlight();
            this.showStep(this.currentStep + 1);
        }

        prev() {
            if (this.currentStep > 0) {
                this.clearHighlight();
                this.showStep(this.currentStep - 1);
            }
        }

        skip() {
            this.cleanup();
            if (this.options.onSkip) this.options.onSkip();
        }

        complete() {
            this.cleanup();
            this.markCompleted();
            if (this.options.onComplete) this.options.onComplete();

            // Show completion message
            this.showCompletionMessage();
        }

        markCompleted() {
            // Save to localStorage
            const completed = this.getCompletedTours();
            if (!completed.includes(this.tourId)) {
                completed.push(this.tourId);
                localStorage.setItem(STORAGE_KEYS.COMPLETED_TOURS, JSON.stringify(completed));
            }

            // Save to server
            fetch('api/tutorial.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=complete_tour&tour_id=${encodeURIComponent(this.tourId)}`
            }).catch(err => console.warn('Failed to save tour progress:', err));
        }

        getCompletedTours() {
            try {
                return JSON.parse(localStorage.getItem(STORAGE_KEYS.COMPLETED_TOURS) || '[]');
            } catch {
                return [];
            }
        }

        showCompletionMessage() {
            const message = document.createElement('div');
            message.className = 'tour-completion-message';
            message.innerHTML = `
                <div class="tour-completion-icon">&#10003;</div>
                <div class="tour-completion-text">Rundvisning faerdig!</div>
            `;
            document.body.appendChild(message);

            setTimeout(() => {
                message.classList.add('fade-out');
                setTimeout(() => message.remove(), 300);
            }, 2000);
        }

        clearHighlight() {
            document.querySelectorAll('.tour-highlighted').forEach(el => {
                el.classList.remove('tour-highlighted');
            });
        }

        cleanup() {
            this.isActive = false;
            document.removeEventListener('keydown', this.boundKeyHandler);
            this.clearHighlight();
            if (this.overlay) this.overlay.remove();
            if (this.tooltip) this.tooltip.remove();
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    /**
     * TooltipSystem Class - Contextual hints
     */
    class TooltipSystem {
        constructor() {
            this.tooltips = [];
            this.dismissedIds = this.loadDismissed();
            this.activeTooltips = [];
        }

        loadDismissed() {
            try {
                return JSON.parse(localStorage.getItem(STORAGE_KEYS.DISMISSED_TOOLTIPS) || '[]');
            } catch {
                return [];
            }
        }

        saveDismissed() {
            localStorage.setItem(STORAGE_KEYS.DISMISSED_TOOLTIPS, JSON.stringify(this.dismissedIds));
        }

        showAll() {
            // Clear dismissed tooltips
            this.dismissedIds = [];
            this.saveDismissed();

            // Hide any existing tooltips first
            this.hideAll();

            // Fetch tooltips from config and show them
            fetch('api/tutorial.php?action=get_tooltips')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.tooltips) {
                        let shownCount = 0;
                        data.tooltips.forEach(tip => {
                            if (this.show(tip)) {
                                shownCount++;
                            }
                        });

                        if (shownCount > 0) {
                            this.showMessage(`${shownCount} vejledningstips vist`);
                        } else {
                            this.showMessage('Ingen elementer fundet paa denne side');
                        }
                    }
                })
                .catch(err => {
                    console.warn('Failed to load tooltips:', err);
                    this.showMessage('Kunne ikke indlaese tips');
                });
        }

        show(config) {
            if (this.dismissedIds.includes(config.id)) return false;

            // Try each selector until we find a visible element
            const selectors = config.target.split(',').map(s => s.trim());
            let target = null;

            for (const selector of selectors) {
                const elements = document.querySelectorAll(selector);
                for (const el of elements) {
                    // Check if element is visible
                    const rect = el.getBoundingClientRect();
                    if (rect.width > 0 && rect.height > 0 && rect.top >= 0 && rect.top < window.innerHeight) {
                        target = el;
                        break;
                    }
                }
                if (target) break;
            }

            if (!target) return false;

            const tooltip = this.createTooltip(target, config);
            if (tooltip) {
                this.activeTooltips.push({ element: tooltip, id: config.id });
                return true;
            }
            return false;
        }

        createTooltip(target, config) {
            // Check if tooltip already exists for this config id
            if (this.activeTooltips.some(t => t.id === config.id)) return null;

            const tooltip = document.createElement('div');
            tooltip.className = 'contextual-tooltip';
            tooltip.innerHTML = `
                <span class="tooltip-content">${this.escapeHtml(config.content)}</span>
                <button class="tooltip-dismiss" aria-label="Luk">OK</button>
            `;

            // Add to body first to measure
            document.body.appendChild(tooltip);

            const rect = target.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();
            const scrollY = window.scrollY || window.pageYOffset;
            const scrollX = window.scrollX || window.pageXOffset;
            const margin = 10;

            // Calculate positions
            let top, left;

            // Check if tooltip fits above the element
            if (rect.top > tooltipRect.height + margin) {
                // Position above
                top = rect.top - tooltipRect.height - margin + scrollY;
            } else {
                // Position below
                top = rect.bottom + margin + scrollY;
            }

            // Center horizontally, but keep on screen
            left = rect.left + (rect.width - tooltipRect.width) / 2 + scrollX;
            left = Math.max(margin, Math.min(left, window.innerWidth - tooltipRect.width - margin + scrollX));

            tooltip.style.position = 'absolute';
            tooltip.style.top = `${top}px`;
            tooltip.style.left = `${left}px`;
            tooltip.style.zIndex = '10001';

            // Add highlight to target element
            target.classList.add('tooltip-target-highlight');

            // Dismiss handler
            tooltip.querySelector('.tooltip-dismiss').addEventListener('click', (e) => {
                e.stopPropagation();
                tooltip.classList.add('fade-out');
                target.classList.remove('tooltip-target-highlight');
                setTimeout(() => {
                    tooltip.remove();
                    this.dismissedIds.push(config.id);
                    this.saveDismissed();
                }, 200);
            });

            // Auto-dismiss after 15 seconds
            setTimeout(() => {
                if (tooltip.parentNode) {
                    tooltip.classList.add('fade-out');
                    target.classList.remove('tooltip-target-highlight');
                    setTimeout(() => tooltip.remove(), 200);
                }
            }, 15000);

            return tooltip;
        }

        showMessage(text) {
            const msg = document.createElement('div');
            msg.className = 'tooltip-message';
            msg.textContent = text;
            document.body.appendChild(msg);

            setTimeout(() => {
                msg.classList.add('fade-out');
                setTimeout(() => msg.remove(), 300);
            }, 2000);
        }

        hideAll() {
            // Remove all tooltip elements
            this.activeTooltips.forEach(({ element }) => {
                if (element && element.parentNode) {
                    element.remove();
                }
            });
            this.activeTooltips = [];

            // Remove all highlights
            document.querySelectorAll('.tooltip-target-highlight').forEach(el => {
                el.classList.remove('tooltip-target-highlight');
            });
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    /**
     * Main Tutorial Manager
     */
    const TutorialManager = {
        activeTour: null,
        tooltipSystem: new TooltipSystem(),

        startTour: function(tourId) {
            if (this.activeTour) {
                this.activeTour.cleanup();
            }

            // Fetch tour steps from server
            fetch(`api/tutorial.php?action=get_tour&id=${encodeURIComponent(tourId)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.steps && data.steps.length > 0) {
                        this.activeTour = new GuidedTour(tourId, data.steps, {
                            onComplete: () => {
                                this.activeTour = null;
                            },
                            onSkip: () => {
                                this.activeTour = null;
                            }
                        });
                        this.activeTour.start();
                    } else {
                        console.warn('No tour steps found for:', tourId);
                        this.showMessage('Ingen rundvisning tilgaengelig for denne side');
                    }
                })
                .catch(err => {
                    console.error('Failed to load tour:', err);
                    this.showMessage('Kunne ikke indlaese rundvisning');
                });
        },

        showTooltips: function() {
            this.tooltipSystem.showAll();
        },

        hideTooltips: function() {
            this.tooltipSystem.hideAll();
        },

        showMessage: function(text) {
            const msg = document.createElement('div');
            msg.className = 'tour-message';
            msg.textContent = text;
            document.body.appendChild(msg);

            setTimeout(() => {
                msg.classList.add('fade-out');
                setTimeout(() => msg.remove(), 300);
            }, 3000);
        },

        getProgress: function() {
            return fetch('api/tutorial.php?action=get_progress')
                .then(r => r.json())
                .then(data => data.success ? data.completed : []);
        }
    };

    // Expose globally
    window.SikkerjobTutorial = TutorialManager;

    // Auto-initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('[Tutorial] System initialized');
    });

})();
