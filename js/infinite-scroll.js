/**
 * Infinite Scroll for PTW Cards
 * Replaces traditional pagination with seamless infinite scrolling
 */

(function() {
    'use strict';

    class InfiniteScroll {
        constructor(options = {}) {
            this.containerSelector = options.containerSelector || '.work-permit-cards';
            this.loadingSelector = options.loadingSelector || '.infinite-scroll-loading';
            this.endSelector = options.endSelector || '.infinite-scroll-end';
            this.itemsPerPage = options.itemsPerPage || 20;
            this.threshold = options.threshold || 300; // px from bottom to trigger load

            this.container = null;
            this.currentPage = 1;
            this.totalPages = 1;
            this.isLoading = false;
            this.hasMore = true;
            this.observer = null;

            this.init();
        }

        init() {
            // Only initialize if we're on the view_wo.php page with card view
            this.container = document.querySelector(this.containerSelector);
            if (!this.container) return;

            // Get initial pagination info from data attributes or URL
            this.currentPage = parseInt(this.container.dataset.currentPage) || 1;
            this.totalPages = parseInt(this.container.dataset.totalPages) || 1;
            this.hasMore = this.currentPage < this.totalPages;

            // Create loading indicator
            this.createLoadingIndicator();

            // Create end message
            this.createEndMessage();

            // Setup Intersection Observer for infinite scroll
            this.setupObserver();

            // Hide traditional pagination
            this.hidePagination();
        }

        createLoadingIndicator() {
            const loading = document.createElement('div');
            loading.className = 'infinite-scroll-loading';
            loading.innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <span>Indlaeser flere PTW'er...</span>
                </div>
            `;
            loading.style.display = 'none';
            this.container.parentNode.insertBefore(loading, this.container.nextSibling);
            this.loadingElement = loading;
        }

        createEndMessage() {
            const end = document.createElement('div');
            end.className = 'infinite-scroll-end';
            end.innerHTML = `
                <div class="end-message">
                    <span>Alle PTW'er er indlaest</span>
                </div>
            `;
            end.style.display = 'none';
            this.loadingElement.parentNode.insertBefore(end, this.loadingElement.nextSibling);
            this.endElement = end;
        }

        setupObserver() {
            // Create sentinel element at the bottom
            const sentinel = document.createElement('div');
            sentinel.className = 'infinite-scroll-sentinel';
            sentinel.style.height = '1px';
            this.loadingElement.parentNode.insertBefore(sentinel, this.loadingElement);
            this.sentinel = sentinel;

            // Setup Intersection Observer
            const options = {
                root: null,
                rootMargin: `${this.threshold}px`,
                threshold: 0
            };

            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !this.isLoading && this.hasMore) {
                        this.loadMore();
                    }
                });
            }, options);

            this.observer.observe(this.sentinel);
        }

        hidePagination() {
            const paginationElements = document.querySelectorAll('.pagination, .card-pagination');
            paginationElements.forEach(el => {
                el.style.display = 'none';
            });
        }

        async loadMore() {
            if (this.isLoading || !this.hasMore) return;

            this.isLoading = true;
            this.loadingElement.style.display = 'block';

            try {
                const nextPage = this.currentPage + 1;
                const url = new URL(window.location.href);
                url.searchParams.set('page', nextPage);
                url.searchParams.set('ajax', '1');

                const response = await fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success && data.html) {
                    // Append new cards to container
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data.html;

                    const newCards = tempDiv.querySelectorAll('.work-permit-card');
                    newCards.forEach(card => {
                        // Add entrance animation
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(20px)';
                        this.container.appendChild(card);

                        // Trigger reflow and animate in
                        requestAnimationFrame(() => {
                            card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        });
                    });

                    // Initialize swipe on new cards if available
                    if (typeof initSwipeCards === 'function') {
                        initSwipeCards();
                    }

                    this.currentPage = nextPage;
                    this.hasMore = data.hasMore !== false && nextPage < this.totalPages;

                    // Update page count display if exists
                    this.updatePageCount();
                }
            } catch (error) {
                console.error('Error loading more items:', error);
                // Show error notification if available
                if (typeof showNotification === 'function') {
                    showNotification('Fejl ved indlaesning af flere PTW\'er', 'error');
                }
            } finally {
                this.isLoading = false;
                this.loadingElement.style.display = 'none';

                if (!this.hasMore) {
                    this.showEndMessage();
                }
            }
        }

        updatePageCount() {
            const counter = document.querySelector('.card-counter');
            if (counter) {
                const currentCount = this.container.querySelectorAll('.work-permit-card').length;
                const totalItems = parseInt(this.container.dataset.totalItems) || currentCount;
                counter.innerHTML = `Viser ${currentCount} af ${totalItems} PTW'er`;
            }
        }

        showEndMessage() {
            this.endElement.style.display = 'block';
            if (this.observer) {
                this.observer.disconnect();
            }
        }

        // Manual load more (fallback button)
        static createLoadMoreButton(container) {
            const button = document.createElement('button');
            button.className = 'button button-secondary load-more-btn';
            button.innerHTML = 'Indlaes flere PTW\'er';
            button.style.display = 'block';
            button.style.margin = '1rem auto';
            button.style.padding = '0.75rem 2rem';

            button.addEventListener('click', () => {
                if (window.infiniteScroll) {
                    window.infiniteScroll.loadMore();
                }
            });

            container.parentNode.insertBefore(button, container.nextSibling);
            return button;
        }

        destroy() {
            if (this.observer) {
                this.observer.disconnect();
            }
            if (this.loadingElement) {
                this.loadingElement.remove();
            }
            if (this.endElement) {
                this.endElement.remove();
            }
            if (this.sentinel) {
                this.sentinel.remove();
            }
        }
    }

    // Initialize on DOM ready
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initInfiniteScroll);
        } else {
            initInfiniteScroll();
        }
    }

    function initInfiniteScroll() {
        // Only enable infinite scroll on pages with card view
        const cardContainer = document.querySelector('.work-permit-cards');
        if (cardContainer && cardContainer.dataset.infiniteScroll !== 'false') {
            window.infiniteScroll = new InfiniteScroll({
                containerSelector: '.work-permit-cards',
                itemsPerPage: 20,
                threshold: 400
            });
        }
    }

    // Run initialization
    init();

    // Expose for external use
    window.InfiniteScroll = InfiniteScroll;

})();
