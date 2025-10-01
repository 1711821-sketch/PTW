# Arbejdstilladelsessystem

## Overview

A web-based arbejdstilladelsessystem designed for coordinating work between different stakeholders including administrators, entrepreneurs (contractors), task managers (opgaveansvarlig), and operations (drift) personnel. The system handles arbejdstilladelse creation, approval workflows, safety job analysis (SJA) with comprehensive version history, and role-based access control. Built with PHP backend using PostgreSQL database for data persistence.

## User Preferences

Preferred communication style: Simple, everyday language.

## System Architecture

### Frontend Architecture
- **Technology**: HTML, CSS, JavaScript with modern styling using Inter font family
- **Design Pattern**: Server-side rendered pages with CSS Grid/Flexbox layouts
- **Styling Approach**: CSS variables for consistent theming, modern design with shadows, gradients, and responsive components
- **User Interface**: Role-based navigation with approval status indicators and interactive forms

### Backend Architecture
- **Technology**: PHP with session-based authentication
- **Data Storage**: PostgreSQL database for all persistent data (work orders, SJA entries, time tracking)
- **Authentication**: Password hashing with bcrypt, role-based access control
- **Session Management**: PHP sessions for user state and role filtering
- **Database Layer**: Custom Database class with PDO for secure parameterized queries

### Role-Based Access Control
- **Admin**: Full system access and management capabilities
- **Entrepreneur**: Company-specific work order access after approval
- **Opgaveansvarlig (Task Manager)**: Work order approval and oversight
- **Drift (Operations)**: Operational approval and monitoring

### Approval Workflow System
- **Multi-stage Approval**: Sequential approval process involving entrepreneur, task manager, and operations
- **Approval Tracking**: Timestamped approval history with user attribution
- **Status Management**: Work order status transitions based on approval completion

### Data Architecture
- **Arbejdstilladelser**: Structured data with approval status, location coordinates, and stakeholder information
- **Safety Job Analysis**: Risk assessment forms with hazard identification and control measures
- **User Management**: Role-based user accounts with company associations for entrepreneurs

### Interactive Map System
- **Modern Map Interface**: Enhanced map_wo.php with comprehensive design system integration and modern UI components
- **Advanced Popups**: Card-style information popups with structured data display and action buttons
- **Enhanced Search**: Debounced search functionality covering WO numbers, descriptions, job responsible, and P-descriptions
- **Responsive Controls**: Modern filter controls with visual feedback and mobile optimization
- **Live Updates**: Real-time marker counting and visibility management with status indicators
- **SJA Visual Indicators**: Work orders with associated Safety Job Analyses display black circle indicators (●) on map markers for immediate safety compliance visibility

### Recent Implementation (September 2025)
- **Time Tracking System**: Complete contractor time registration solution implemented
- **Database Migration**: Migrated from JSON files to PostgreSQL for enhanced security and data integrity
  - Work orders: Fully migrated to work_orders table
  - SJA entries: Fully migrated to sja_entries table with JSON column structure
  - All view and print pages updated to use database queries with proper JSON parsing
- **Enhanced Dashboard**: Real-time time tracking metrics with accurate calculations and contractor rankings
- **Security Implementation**: Database-based access control with role-based authorization throughout
- **Validation System**: Quarter-hour increments, date range validation, and comprehensive input sanitization
- **Mobile Optimization**: Comprehensive iPhone and mobile device optimization with improved touch targets, responsive navigation, and better mobile user experience
- **SJA Version History**: Complete version control system for Safety Job Analyses with automatic versioning, historical snapshots, side-by-side comparison, and legacy entry support
- **Entrepreneur Access Control Enhancement**: Entrepreneurs can now only view work orders that meet all criteria (September 30, 2025):
  - Status must be 'active'
  - Approved by both opgaveansvarlig (task manager) and drift (operations)
  - Still restricted to their own firm's work orders
  - Implemented via PostgreSQL JSON operators in view_wo.php query
- **Work Order Image Upload**: Entrepreneurs can upload completion documentation images (September 30, 2025):
  - Secure file upload system with MIME-type validation and extension mapping
  - Entrepreneurs can upload images only to their firm's work orders
  - All roles can view uploaded images as documentation
  - Image gallery with responsive grid layout and lightbox functionality
  - Security: PHP execution disabled in uploads directory, no user-supplied extensions used
  - Images stored in uploads/work_order_images/ with database references in completion_images column
- **User Management Migration**: Complete migration of user registration and approval system to PostgreSQL (September 30, 2025):
  - Migrated register.php to store new users in PostgreSQL database instead of users.json
  - Migrated admin.php to manage user approvals and deletions in PostgreSQL database
  - Fixed critical login issue where new users couldn't log in because they were stored in JSON but login.php reads from PostgreSQL
  - All user management now uses prepared statements with proper error handling
  - User approval workflow: register → admin approves → user can login
- **Bug Fixes**: 
  - Fixed print_wo.php to correctly parse SJA basic_info JSON when displaying linked SJAs (September 30, 2025)
  - Fixed create_sja.php to preserve work_order_id when editing SJAs from history view (September 30, 2025)
  - Fixed user registration and login flow to work correctly after database migration (September 30, 2025)
  - Fixed critical SQL syntax error in view_wo.php preventing entrepreneurs from viewing approved work orders - escaped JSONB `?` operator as `??` for PDO compatibility (September 30, 2025)
  - Fixed UI update issue in view_wo.php where approval checkmarks didn't appear immediately after approval (October 1, 2025):
    - Added unique IDs to approval status spans in List View
    - Added unique IDs to approval buttons in both List View (with 'list-' prefix) and Card View
    - Updated JavaScript to use view-scoped selectors to update both views simultaneously
    - Fixed text formatting to respect each view's style (List: '✅'/'❌', Card: '✅ Godkendt'/'❌ Mangler')
    - Ensured both view's buttons are hidden after approval regardless of which view is active
  - Optimized approval column width in view_wo.php List View for more compact display (October 1, 2025):
    - Fixed column header forcing excessive width - reduced from ~140px+ to exactly 110px
    - Applied triple-lock width constraint (width + min-width + max-width = 110px) to both header and data cells
    - Enabled header text wrapping with white-space: normal and overflow-wrap: anywhere
    - Added word-break protection to prevent long unbreakable strings from expanding column
    - Tablet override (≤768px) with !important to ensure wrapping on mobile devices
    - Reduced label width from 50px to 38px (OA:, Drift:, Ent:)
    - Added layout robustness with border-spacing: 0 and text-overflow protection
    - Labels use white-space: nowrap with ellipsis overflow handling

### Time Tracking Features
- **Direct Registration**: Contractors register daily hours directly on work permits
- **Role-Based Access**: Entrepreneurs restricted to their firm's work orders only
- **Admin Overview**: Complete time consumption visibility for administrators, operations, and task managers
- **Security Validation**: Database-based access control replacing JSON file vulnerabilities
- **Audit Logging**: Comprehensive tracking of all time entries and security events

### Mobile Optimization Features
- **Hamburger Navigation**: Responsive slide-out navigation menu with fixed hamburger button for mobile devices
- **Touch-Optimized Forms**: Improved input fields with 48px minimum touch targets, larger padding, and iOS-specific styling
- **Mobile-First Buttons**: Enhanced button design with better touch feedback and 48px minimum touch targets
- **Responsive Tables**: Mobile-optimized table display with horizontal scrolling and sticky headers
- **Typography Scaling**: Adaptive font sizes and line heights optimized for mobile readability
- **iPhone Compatibility**: Special handling for iOS Safari including webkit optimizations and touch-action properties
- **Animation System**: Smooth slide-in animations for mobile navigation with proper timing and easing

### SJA Version History System
- **Automatic Versioning**: Each SJA edit creates a new version with automatic version numbering
- **Historical Snapshots**: Complete snapshots of previous versions stored in history array
- **Version Metadata**: Each version includes timestamp, version number, and modified_by user
- **Version Viewing**: Dedicated interface to browse and view any historical version
- **Side-by-Side Comparison**: Compare two versions with highlighted differences across all sections
- **Legacy Support**: Backwards compatible with existing SJAs without version data
- **Data Integrity**: Deep copy snapshots and file locking prevent data corruption
- **Accessible UI**: History links in both list view and print view for easy access

## External Dependencies

### Core Technologies
- **PHP**: Server-side scripting and session management
- **Web Standards**: HTML5, CSS3, JavaScript for frontend functionality
- **Google Fonts**: Inter font family for modern typography

### Database Dependencies
- **PostgreSQL**: Neon-backed PostgreSQL database for all persistent data
- **Asset Management**: CSS file attachments in attached_assets directory

### External Services
- **Neon PostgreSQL**: Managed PostgreSQL database service via Replit integration
- Self-contained authentication and session management