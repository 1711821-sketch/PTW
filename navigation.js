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
    
    // Create backdrop element dynamically
    const backdrop = document.createElement('div');
    backdrop.className = 'navbar-backdrop';
    document.body.appendChild(backdrop);
    
    // Function to close the menu
    function closeMenu() {
        hamburgerButton.classList.remove('active');
        navbarLinks.classList.remove('active');
        backdrop.classList.remove('active');
        
        // Show PTW counter again when menu closes
        const cardCounter = document.querySelector('.card-counter');
        if (cardCounter) {
            cardCounter.classList.remove('hidden-by-menu');
        }
    }
    
    // Function to open the menu
    function openMenu() {
        hamburgerButton.classList.add('active');
        navbarLinks.classList.add('active');
        backdrop.classList.add('active');
        
        // Hide PTW counter when menu is open
        const cardCounter = document.querySelector('.card-counter');
        if (cardCounter) {
            cardCounter.classList.add('hidden-by-menu');
        }
    }
    
    // Add click event listener to hamburger button
    hamburgerButton.addEventListener('click', function() {
        // Toggle menu state
        if (navbarLinks.classList.contains('active')) {
            closeMenu();
        } else {
            openMenu();
        }
    });
    
    // Close menu when clicking on the backdrop
    backdrop.addEventListener('click', function() {
        closeMenu();
    });
    
    // Close menu when clicking on a navigation link (better UX on mobile)
    const navLinks = navbarLinks.querySelectorAll('a');
    navLinks.forEach(function(link) {
        link.addEventListener('click', function() {
            closeMenu();
        });
    });
    
    // Handle window resize - ensure menu state is correct when switching between desktop/mobile
    window.addEventListener('resize', function() {
        // If window becomes larger than mobile breakpoint, ensure menu is in correct state
        if (window.innerWidth > 480) {
            closeMenu();
        }
    });
});