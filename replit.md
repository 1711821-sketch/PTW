# PTW System (Permit To Work)

## Overview
A web-based PTW (Permit To Work) system designed to streamline work order management and enhance safety compliance across administrators, entrepreneurs, task managers, and operations personnel. It facilitates PTW creation, multi-stage approval workflows, comprehensive Safety Job Analysis (SJA) with version history, time tracking, and robust role-based access control. The system aims to simplify coordination and ensure safety throughout the work process.

## User Preferences
Preferred communication style: Simple, everyday language.

## System Architecture

### Frontend
- **Technology**: HTML, CSS, JavaScript, focusing on modern styling with the Inter font family and CSS variables.
- **Design**: Server-side rendered, responsive design utilizing CSS Grid/Flexbox. Implements Progressive Web App (PWA) features for an app-like experience.
- **UI/UX**: Features role-based navigation, interactive forms, a modern map interface with advanced popups, debounced search, and extensive mobile optimization (touch-friendly interactions, responsive typography, ultra-compact mobile spacing, and a streamlined navigation bar). Pagination and lazy loading are implemented for performance.

### Backend
- **Technology**: PHP with session-based authentication.
- **Data Storage**: PostgreSQL database.
- **Authentication**: Bcrypt for password hashing and role-based access control with PHP sessions. Includes a forced password change for new admin users.
- **Database Layer**: Custom PDO-based class for secure interactions.

### Core Features
- **Role-Based Access Control**: Granular access levels for Admin, Entrepreneur, Task Manager, and Operations roles. Entrepreneurs access only approved, active work orders relevant to their firm.
- **Approval Workflow**: A multi-stage sequential approval process (Task Manager → Operations → Entrepreneur) with visual tracking, timestamps, and status indicators. Approvals are restricted to 'AKTIV' status work orders and require daily re-approval. **Automatic Work Start (2025-11-03)**: When ALL three parties approve their PTW (opgaveansvarlig, drift, entreprenør), work starts automatically (status_dag='aktiv_dag', ikon='green_pulse', starttid is set). The manual "Start arbejde/hammer" button has been removed. Entrepreneurs can pause work using the "Stop arbejde for i dag" button (sets status_dag='pause_dag', ikon='yellow', sluttid).
- **PTW Management**: Creation, viewing, and editing of PTWs, uniquely identified by "Indkøbsordre nummer," allowing "PTW Nr." duplicates.
- **Safety Job Analysis (SJA)**: Comprehensive SJA forms with automatic versioning, historical snapshots, and side-by-side comparison.
- **Time Tracking**: Contractors log daily hours; administrators access a reporting page with filtering, statistics, and charts.
- **Work Order Image Upload**: Secure system for entrepreneurs to upload completion documentation, with CSRF protection, MIME type validation, file size limits, and secure filename generation.
- **Mapping System**: Uses Leaflet with CRS.Simple to display a zone classification plan (PNG image) as the primary map, allowing precise PTW placement using image coordinates. Includes a coordinate transformation system for legacy geographic data. Map markers visually reflect daily work status: green pulsing (aktiv_dag), yellow/orange (pause_dag), static green (kræver_dagsgodkendelse), blue (planning), gray (completed).
- **User Management**: Creation and management of users by administrators, including email notifications.
- **Daily Reset**: Automatic midnight reset of daily work statuses for active/paused PTWs via auth_check.php (runs on first login after midnight) or standalone reset_daily_status.php script. Resets status_dag to 'kræver_dagsgodkendelse' and ikon to 'green_static', requiring daily re-approval.

## External Dependencies

### Core Technologies
- **PHP**: Server-side scripting language.
- **Web Standards**: HTML5, CSS3, JavaScript.

### Database Dependencies
- **PostgreSQL**: The primary relational database system.
- **Neon PostgreSQL**: Managed PostgreSQL service.

### Email Services
- **SendGrid**: Used for sending transactional emails, integrated via Replit connector.

### Asset Management
- **Google Fonts**: Utilized for the "Inter" font family.