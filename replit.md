# PTW System (Permit To Work)

## Overview
A web-based PTW (Permit To Work) system for coordinating work among administrators, entrepreneurs (contractors), task managers, and operations personnel. It features PTW creation, multi-stage approval workflows, comprehensive Safety Job Analysis (SJA) with version history, time tracking, and role-based access control. The system aims to streamline work order management and enhance safety compliance.

## Recent Changes
**2025-10-26**: Instructional video integration:
- **Login Page Enhancement**: Added instructional video link for entrepreneurs on login page
  - Created `instruktionsvideo.php` page with HTML5 video player for viewing training content
  - Extended `router.php` to serve videos from `/assets/videos/` directory with proper security
  - Added prominent link on `login.php` below registration: "📹 Instruktionsvideo for brugen af PTW system"
  - Includes recommendation text: "Anbefales at se video inden man starter at bruge appen"
  - Video accessible without login for easy onboarding of new entrepreneurs
  - Responsive video player with 16:9 aspect ratio and modern styling
  - Back-to-login navigation link for easy return

**2025-10-25**: Image serving infrastructure:
- **Router Implementation**: Created `router.php` to properly serve uploaded images from PHP's built-in server
  - Handles static file serving for `/uploads/work_order_images/` directory
  - Implements security measures: directory traversal prevention, realpath validation
  - Sets appropriate MIME types for image formats (JPEG, PNG, GIF, WebP, AVIF)
  - Includes caching headers for optimal performance
  - Updated workflow to use `php -S 0.0.0.0:5000 router.php` instead of direct file serving
  - Resolves 404 errors when entrepreneurs upload images and other users view them

**2025-10-25**: Mobile swipe improvements:
- **Enhanced Card Snapping**: Improved swipe functionality in card view (boksvisning) on mobile
  - Added CSS `scroll-snap-stop: always` to prevent partial card views
  - Implemented JavaScript backup snap function for precise card alignment
  - Cards now always snap to show one complete PTW at a time when swiping
  - Smooth animations with 150ms delay for better user experience

**2025-10-25**: Status-based approval restrictions:
- **Approval Workflow Security Enhancement**: Godkendelser (approvals) are now strictly limited to work orders with status "AKTIV" (active)
  - Frontend: `approval_workflow_widget.php` hides approval buttons when status is not 'active'
  - Backend: `view_wo.php` AJAX handler validates status and rejects approval attempts for non-active PTWs
  - Status validation prevents approvals for "PLANLAGT" (planning) and "AFSLUTTET" (completed) work orders
  - User-friendly error messages display current status when approval is attempted on non-active PTW
  - This ensures approvals only occur during the active work phase, improving workflow safety and compliance

**2025-10-25**: Major updates to card view (boksvisning) in `view_wo.php` and print view in `print_wo.php`:

**Design Updates:**
- Redesigned card view with modern collapsible dropdown sections
- New modern card header with blue gradient and status badges
- Organized content into four collapsible sections: Basisinformation, Godkendelsesproces, Tidsregistrering, and Dokumentationsbilleder
- Implemented smooth animations and responsive design for both desktop and mobile
- Unified all dropdown sections with consistent styling (same height, font, spacing, background color, and toggle animations)
- Refactored Godkendelsesproces section in both `view_wo.php` and `print_wo.php` to match visual design of other dropdowns while preserving approval status display

**Card View Approval Redesign (`view_wo.php`):**
- Replaced horizontal flow-diagram with modern vertical approval list
- Each approval step displayed as individual card with:
  - Green checkmark icon (✓) for approved steps, gray circle (○) for pending
  - Role name (Opgaveansvarlig, Drift, Entreprenør)
  - Timestamp display for approved steps
  - "Afventer godkendelse" status text for pending steps
  - "Godkend" button for users with appropriate permissions
- Real-time AJAX approval updates without page reload:
  - Visual state change from pending to approved
  - Icon update from gray circle to green checkmark
  - Timestamp display with current date/time
  - Button removal after approval
  - Approval count update in section header ("Godkendt X/3")
- Responsive design with mobile-optimized sizing and spacing
- Preserved daily reapproval business logic (approvals reset at midnight)

**Print View Standardization (`print_wo.php`):**
- Replaced `renderApprovalWorkflowWidget()` with inline standard collapsible structure
- All dropdown sections now use shared `toggleSection()` function with consistent section IDs
- Removed deprecated `toggleApprovalWorkflow()` function
- Removed CSS overrides; approval section now inherits same styling as other sections
- Preserved original business logic: PTW approvals require daily reapproval for safety (approvals reset at midnight)
- Approval count and button visibility based on current day's approvals only
- Added null-checks in `toggleSection()` JavaScript function to prevent errors when elements don't exist

**Upload Functionality:**
- Changed Dokumentationsbilleder section from image display to image upload interface in card view
- Implemented secure upload functionality with comprehensive security measures:
  - CSRF token protection against cross-site request forgery
  - MIME type validation (JPEG, PNG, GIF, WebP, AVIF only)
  - File size limit (max 50MB)
  - Secure filename generation with error handling
  - Entrepreneur-only access to their own firm's work orders
  - Active PTW status validation - uploads only allowed for active work orders
- Added success/error messaging for upload operations
- Modern, responsive upload form with custom file input styling

## User Preferences
Preferred communication style: Simple, everyday language.

## System Architecture

### Frontend
- **Technology**: HTML, CSS, JavaScript, with a focus on modern styling using the Inter font family and CSS variables.
- **Design**: Server-side rendered, responsive design leveraging CSS Grid/Flexbox. Implements Progressive Web App (PWA) features for an app-like experience (installable on home screens, standalone display mode).
- **UI/UX**: Features role-based navigation, interactive forms, a modern map interface with advanced popups, debounced search, and extensive mobile optimization (touch-friendly interactions, responsive typography, ultra-compact mobile spacing, and a streamlined navigation bar).

### Backend
- **Technology**: PHP with session-based authentication.
- **Data Storage**: PostgreSQL database for all persistent data.
- **Authentication**: Uses bcrypt for password hashing and implements role-based access control with PHP sessions managing user state. Includes a forced password change mechanism for newly created admin users.
- **Database Layer**: Custom PDO-based class for secure database interactions.
- **Email Notifications**: Integrated with SendGrid via a Replit connector for transactional emails.

### Core Features
- **Role-Based Access Control**: Granular access levels for Admin, Entrepreneur, Task Manager, and Operations roles. Entrepreneurs can only access approved, active work orders relevant to their firm.
- **Approval Workflow**: A multi-stage sequential approval process (Opgaveansvarlig → Drift → Entreprenør) with visual tracking, timestamps, and status indicators.
- **PTW Management**: Creation, viewing, and editing of PTWs, uniquely identified by "Indkøbsordre nummer" with "PTW Nr." allowing duplicates.
- **Safety Job Analysis (SJA)**: Comprehensive SJA forms with automatic versioning, historical snapshots, and side-by-side comparison capabilities.
- **Time Tracking**: Contractors log daily hours; administrators access a reporting page (`time_overblik.php`) with filtering, statistics, and charts.
- **Work Order Image Upload**: Secure system for entrepreneurs to upload completion documentation, with support for various image formats and an increased file size limit.
- **Mapping System**: Uses Leaflet with CRS.Simple to display a zone classification plan (PNG image) as the primary map, allowing precise PTW placement using image coordinates. Includes a coordinate transformation system for legacy geographic data.
- **User Management**: Creation and management of users by administrators, including email notifications for new user registrations.

## External Dependencies

### Core Technologies
- **PHP**: Server-side scripting language.
- **Web Standards**: HTML5, CSS3, JavaScript.

### Database Dependencies
- **PostgreSQL**: The primary relational database system.
- **Neon PostgreSQL**: Managed PostgreSQL service, integrated through Replit.

### Email Services
- **SendGrid**: Used for sending transactional emails, specifically admin notifications for new user registrations, integrated via Replit connector.

### Asset Management
- **Google Fonts**: Utilized for the "Inter" font family.