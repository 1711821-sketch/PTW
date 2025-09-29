# Arbejdstilladelsessystem

## Overview

A web-based arbejdstilladelsessystem designed for coordinating work between different stakeholders including administrators, entrepreneurs (contractors), task managers (opgaveansvarlig), and operations (drift) personnel. The system handles arbejdstilladelse creation, approval workflows, safety job analysis (SJA), and role-based access control. Built with PHP backend using JSON file storage for data persistence.

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
- **Data Storage**: JSON file-based persistence (users.json, wo_data.json, sja_data.json)
- **Authentication**: Password hashing with bcrypt, role-based access control
- **Session Management**: PHP sessions for user state and role filtering

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
- **Enhanced Dashboard**: Real-time time tracking metrics with accurate calculations and contractor rankings
- **Security Implementation**: Database-based access control with role-based authorization throughout
- **Validation System**: Quarter-hour increments, date range validation, and comprehensive input sanitization
- **Mobile Optimization**: Comprehensive iPhone and mobile device optimization with improved touch targets, responsive navigation, and better mobile user experience

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

## External Dependencies

### Core Technologies
- **PHP**: Server-side scripting and session management
- **Web Standards**: HTML5, CSS3, JavaScript for frontend functionality
- **Google Fonts**: Inter font family for modern typography

### File System Dependencies
- **JSON Storage**: File-based data persistence for users, work orders, and safety analyses
- **Asset Management**: CSS and JSON file attachments in attached_assets directory

### No External Services
- System operates independently without external API dependencies
- Self-contained authentication and data storage
- No database server requirements (uses JSON files)