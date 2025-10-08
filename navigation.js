/**
 * Navigation JavaScript for hamburger menu functionality
 * Handles mobile navigation toggle for the work order system
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get hamburger button and navigation links
    const hamburgerButton = document.querySelector('.navbar-toggle');
    const navbarLinks = document.querySelector('.navbar-links');
    
    // Check if elements exist (they might not be on all pages)
    if (!hamburgerButton || !navbarLinks) {
        return;
    }
    
    // Add click event listener to hamburger button
    hamburgerButton.addEventListener('click', function() {
        // Toggle active class on hamburger button for animation
        hamburgerButton.classList.toggle('active');
        
        // Toggle active class on navbar links to show/hide menu
        navbarLinks.classList.toggle('active');
        
        // Hide PTW counter when menu is open, show when closed
        const cardCounter = document.querySelector('.card-counter');
        if (cardCounter) {
            if (navbarLinks.classList.contains('active')) {
                cardCounter.style.display = 'none';
            } else {
                cardCounter.style.display = 'block';
            }
        }
    });
    
    // Close menu when clicking on a navigation link (better UX on mobile)
    const navLinks = navbarLinks.querySelectorAll('a');
    navLinks.forEach(function(link) {
        link.addEventListener('click', function() {
            // Close the menu when a link is clicked
            hamburgerButton.classList.remove('active');
            navbarLinks.classList.remove('active');
            
            // Show PTW counter again when menu closes
            const cardCounter = document.querySelector('.card-counter');
            if (cardCounter) {
                cardCounter.style.display = 'block';
            }
        });
    });
    
    // Close menu when clicking outside of navigation (optional enhancement)
    document.addEventListener('click', function(event) {
        const isClickInsideNav = navbarLinks.contains(event.target) || hamburgerButton.contains(event.target);
        
        // If click is outside navigation and menu is open, close it
        if (!isClickInsideNav && navbarLinks.classList.contains('active')) {
            hamburgerButton.classList.remove('active');
            navbarLinks.classList.remove('active');
            
            // Show PTW counter again when menu closes
            const cardCounter = document.querySelector('.card-counter');
            if (cardCounter) {
                cardCounter.style.display = 'block';
            }
        }
    });
    
    // Handle window resize - ensure menu state is correct when switching between desktop/mobile
    window.addEventListener('resize', function() {
        // If window becomes larger than mobile breakpoint, ensure menu is in correct state
        if (window.innerWidth > 480) {
            hamburgerButton.classList.remove('active');
            navbarLinks.classList.remove('active');
            
            // Show PTW counter again when switching to desktop view
            const cardCounter = document.querySelector('.card-counter');
            if (cardCounter) {
                cardCounter.style.display = 'block';
            }
        }
    });
});