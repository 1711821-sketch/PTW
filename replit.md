# PTW System (Permit To Work)

## Overview
A web-based PTW (Permit To Work) system designed for coordinating work between administrators, entrepreneurs (contractors), task managers, and operations personnel. The system facilitates PTW creation, multi-stage approval workflows, comprehensive Safety Job Analysis (SJA) with version history, and time tracking. It features role-based access control and aims to streamline work order management and safety compliance.

## User Preferences
Preferred communication style: Simple, everyday language.

## Recent Changes
- **October 21, 2025**:
  - **Ultra-Compact Mobile Header with Inline Navigation**: Completely redesigned mobile navigation for maximum space efficiency
    - Replaced fixed hamburger menu with inline dot menu (⋮) positioned in navbar flow
    - Compact header structure: Dot menu (⋮) | Username - all in one clean line
    - Username always visible in mobile header (removed from foldable menu to avoid duplication)
    - Reduced menu button size from 48x48px to 36x36px (25% smaller footprint)
    - Sticky navbar with optimal 52px height for mobile devices
    - Foldable menu contains only navigation links + log out (username moved to header)
    - Implemented across ALL 11 pages: dashboard.php, view_wo.php, create_wo.php, map_wo.php, admin.php, time_overblik.php, view_sja.php, print_wo.php, sja_history.php, sja_compare.php, sms_admin.php
    - Responsive CSS classes: nav-user-mobile (visible <480px), nav-user-desktop (visible ≥480px)
    - Desktop view unchanged: full "Logget ind som [name] (role)" text shown in navbar
  - **PWA App Icon Update**: Replaced app icons with new professional design featuring safety helmet and PTW branding
    - New icon-192.png (13 KB) and icon-512.png (133 KB) created with blue background and white safety helmet logo
    - Service worker cache version updated to 'arbejdstilladelse-v3' to force client updates
    - Icons optimized for PWA installation on mobile devices and home screen
  - **Billedsletning og udvidet formatunderstøttelse (print_wo.php)**:
    - Entreprenører kan nu slette uploadede billeder med bekræftelsesdialog
    - Filstørrelsesgrænse øget fra 10MB til 50MB for højopløselige smartphone-billeder
    - Udvidet formatunderstøttelse: JPEG, PNG, GIF, WebP, AVIF (kun browser-renderable formater)
    - Slet-knap (🗑️) tilføjet til hvert billede i galleriet for autoriserede brugere
    - Sikkerhedstjek: Kun entreprenører kan slette billeder fra deres eget firmas PTW'er
    - Sletning fjerner både fil fra disk og reference fra database atomisk
    - UI-vejledning tilføjet for iPhone-brugere om at indstille kamera til JPEG-format
    - Print-funktionalitet skjuler slet-knapper automatisk
    - SVG, HEIC, TIFF og BMP ekskluderet for at undgå XSS-sårbarheder og browser-kompatibilitetsproblemer
  - **Kollapsible sektioner i print_wo.php**: Alle hovedsektioner er nu fold-ud elementer for bedre pladsudnyttelse
  - **Basisinformation** (📋), **Godkendelsesproces** (✅), **Godkendelseshistorik** (📜), **Tilknyttede SJA'er** (📝), **Timeforbrug** (⏱️), og **Dokumentationsbilleder** (📸) er alle kollapsible top-level sektioner
  - Godkendelsesproces bruges direkte fra approval_workflow_widget.php (har egen kollapsibel wrapper)
  - Godkendelseshistorik er flyttet ud som separat top-level sektion ved siden af de andre
  - Clickable headers med ikoner og toggle-pil (▼) der roterer ved udvid/luk
  - Blå baggrundsfarve med hover-effekt på section headers
  - Generisk JavaScript toggleSection(woId, sectionName) funktion for de fleste sektioner
  - toggleApprovalWorkflow(woId) funktion for approval workflow widget
  - Alle sektioner er som standard skjult/lukket for at spare plads på siden
  - Print-funktionalitet sikrer at alle sektioner altid vises ved print
  - CSS-animation (slideDown) for glat åbning af sektionerne
- **October 10, 2025**: 
  - Added visual sequential approval workflow widget showing the approval process flow (Opgaveansvarlig → Drift → Entreprenør)
  - New widget features color-coded status indicators (green=approved, blue=current user's turn, gray=pending)
  - Integrated approval flow visualization in both card view (view_wo.php) and print view (print_wo.php)
  - Widget shows timestamps, visual arrows, and approve buttons for authorized users
  - **Ultra-compact mobile optimization (<768px)**: Widget optimized for smartphone display with all three boxes (OA, Drift, Ent) visible side-by-side without horizontal scrolling
  - Mobile layout: 29% width per step, 2px gaps, compact arrows (0.7rem with negative margins), fits perfectly on 320px+ screens
  - Abbreviated role names (OA, Drift, Ent) shown via CSS pseudo-elements using text-indent technique
  - Checkmark-only approve buttons (✓), hidden timestamps/status for space efficiency
  - Fixed CSS bugs: consolidated duplicate .step-title rules, added min-width: 0 override to allow proper flex shrinking on mobile
  - Touch-friendly with 32px minimum button height maintained
  - **Accordion functionality for approval workflow**: Wrapped approval process in fold-out section matching Tidsregistrering pattern
  - Clickable header shows "✅ Godkendelsesproces" with dynamic status (Godkendt 0/3, 1/3, 2/3, or 3/3) based on approval count
  - Toggle icon (▼) rotates when expanded, green background color (rgba(16, 185, 129, 0.03)) with hover effect
  - JavaScript toggleApprovalWorkflow() function for expand/collapse behavior
  - Removed "Opret ny PTW" link from print_wo.php navigation bar for cleaner interface
  - Removed "Opret ny PTW?" link from bottom of view_wo.php (kept in navigation bar)
  - Removed "Se oversigtskort" link from bottom of view_wo.php
  - **Map System Overhaul - CRS.Simple Image Coordinates**:
    - Converted map_wo.php from geographic coordinates (WGS84) to pure image coordinates using Leaflet CRS.Simple
    - Zoneklassifikationsplan (7021x4967px PNG) now serves as the primary "map" via L.imageOverlay
    - Removed OpenStreetMap tiles, layer control, dragging, and opacity slider - now pure drawing-based coordinate system
    - Image bounds: [[0, 0], [4967, 7021]] representing pixel coordinates
    - Map configuration: `crs: L.CRS.Simple, minZoom: -2, maxZoom: 4, zoomSnap: 0.25`
  - **Coordinate Transformation System**:
    - Implemented geoToImage() function to convert legacy geographic coordinates to image pixels
    - Uses historic bounds [[55.200, 11.258], [55.207, 11.270]] as reference for linear transformation
    - PTW markers now positioned using image coordinates via imgXY(x, y) helper (maps to L.latLng(y, x))
    - Clamping to image boundaries prevents out-of-bounds coordinates
    - Smart coordinate detection in map_wo.php: automatically detects if coordinates are geographic (transforms them) or image-based (uses directly)
    - Database columns changed from DECIMAL(10,8) to REAL to support both geographic decimals and large image pixel values
  - **Click Coordinate Feedback**:
    - Map click handler displays image coordinates in popup: "Billedkoordinater: X: [x], Y: [y]"
    - Coordinates also logged to console for easy copying
    - Enables precise PTW marker placement using pixel coordinates
  - **UI Updates**:
    - New instruction banner: "💡 Billedkoordinater: Klik på kortet for at se billedkoordinater (X, Y)"
    - Retained all existing functionality: search, filtering, status indicators, SJA markers, work status, popups
  - **Known Limitation**: Existing PTW markers use geographic coordinates from database transformed via hardcoded bounds; may require recalibration if original calibration differed
  - **PTW Creation with Zone Plan (create_wo.php)**:
    - Converted PTW creation/edit page from OpenStreetMap to zoneklassifikationsplan with CRS.Simple
    - Users now click directly on zone plan to select PTW location instead of geographic map
    - Smart coordinate detection: automatically identifies if existing PTW has geographic or image coordinates
    - Geographic coordinates (lat: 55.200-55.207, lng: 11.258-11.270) are transformed to image coordinates for display
    - New PTW saves image coordinates directly: X stored in longitude field, Y stored in latitude field
    - Visual feedback shows "Billedkoordinater: X: [x], Y: [y]" when clicking on map
    - Label updated to "Klik på zoneklassifikationsplanen for at vælge placering"
    - Backward compatibility: editing existing PTW with geographic coordinates converts them to image coordinates on save
    - Ensures consistency between creation (create_wo.php) and overview (map_wo.php) map systems

## System Architecture

### Frontend
- **Technology**: HTML, CSS, JavaScript with modern styling (Inter font family, CSS variables).
- **Design**: Server-side rendered pages using CSS Grid/Flexbox, responsive design, and PWA features for an app-like experience (installable on home screen, standalone display mode).
- **UI/UX**: Role-based navigation, interactive forms, modern map interface with advanced popups and debounced search, mobile optimization (touch-friendly interactions, responsive typography, hamburger navigation, horizontal action buttons, ultra-compact mobile spacing).

### Backend
- **Technology**: PHP with session-based authentication.
- **Data Storage**: PostgreSQL database for all persistent data (work orders, SJA entries, time tracking, user management).
- **Authentication**: Password hashing (bcrypt), role-based access control, PHP sessions for user state.
- **Database Layer**: Custom PDO-based class for secure queries.

### Core Features
- **Role-Based Access Control**: Differentiated access for Admin, Entrepreneur, Task Manager, and Operations. Entrepreneurs can only view approved, active work orders relevant to their firm.
- **Approval Workflow**: Multi-stage approval process (entrepreneur, task manager, operations) with timestamped history and status tracking.
- **Data Management**: Structured PTW data, risk assessment forms (SJA), and user management with company associations.
- **Time Tracking**: Contractors register daily hours on work permits; admins have a comprehensive reporting page (`time_overblik.php`) with filtering, statistics, and charts.
- **SJA Version History**: Automatic versioning for SJA edits, historical snapshots with metadata, version viewing, and side-by-side comparison.
- **Work Order Image Upload**: Secure image upload system for entrepreneurs to add completion documentation, viewable by all roles.

## External Dependencies

### Core Technologies
- **PHP**: Server-side scripting.
- **Web Standards**: HTML5, CSS3, JavaScript.

### Database Dependencies
- **PostgreSQL**: Primary database for all persistent project data.
- **Neon PostgreSQL**: Managed PostgreSQL database service via Replit integration.

### Asset Management
- **Google Fonts**: Inter font family for typography.
- **CSS file attachments**: Stored in `attached_assets` directory.