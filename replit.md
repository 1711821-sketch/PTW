# PTW System (Permit To Work)

## Overview
A web-based PTW (Permit To Work) system for coordinating work among administrators, entrepreneurs (contractors), task managers, and operations personnel. It features PTW creation, multi-stage approval workflows, comprehensive Safety Job Analysis (SJA) with version history, time tracking, and role-based access control. The system aims to streamline work order management and enhance safety compliance.

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