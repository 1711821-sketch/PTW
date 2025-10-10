# PTW System (Permit To Work)

## Overview
A web-based PTW (Permit To Work) system designed for coordinating work between administrators, entrepreneurs (contractors), task managers, and operations personnel. The system facilitates PTW creation, multi-stage approval workflows, comprehensive Safety Job Analysis (SJA) with version history, and time tracking. It features role-based access control and aims to streamline work order management and safety compliance.

## User Preferences
Preferred communication style: Simple, everyday language.

## Recent Changes
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
  - **Zone Classification Plan Overlay**: Added zoneklassifikationsplan as toggleable map overlay in map_wo.php
  - Converted PDF zone plan to high-res PNG (7021x4967px) stored in assets/maps/zoneplan_sgot.png
  - Implemented Leaflet imageOverlay with 0.65 opacity, positioned over SGOT terminal area
  - Added layer control (topright) allowing users to toggle "Zoneklassifikationsplan" on/off
  - Overlay is non-interactive and semi-transparent to show both zones and background map
  - Moved map info box ("X af X arbejdsordrer vises") from top-right to bottom-right to prevent overlap with layer control
  - **Interactive Calibration System**: Added calibration UI control (topleft) for precise overlay alignment
  - Two-click calibration: Click bottom-left corner then top-right corner to set overlay bounds
  - Opacity slider (0.3-0.9) for adjusting overlay transparency
  - Bounds and opacity persist in localStorage across page loads
  - Cache-buster on overlay image URL ensures fresh loading
  - Overlay placed in tilePane (z-order) to keep PTW markers and popups on top
  - Error handling prevents crashes from corrupted localStorage data

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