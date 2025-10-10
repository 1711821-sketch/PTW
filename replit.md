# PTW System (Permit To Work)

## Overview
A web-based PTW (Permit To Work) system designed for coordinating work between administrators, entrepreneurs (contractors), task managers, and operations personnel. The system facilitates PTW creation, multi-stage approval workflows, comprehensive Safety Job Analysis (SJA) with version history, and time tracking. It features role-based access control and aims to streamline work order management and safety compliance.

## User Preferences
Preferred communication style: Simple, everyday language.

## Recent Changes
- **October 10, 2025**: 
  - Removed "Opret ny PTW" link from print_wo.php navigation bar for cleaner interface
  - Removed "Opret ny PTW?" link from bottom of view_wo.php (kept in navigation bar)

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