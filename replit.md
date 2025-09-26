# Work Order Management System

## Overview

A web-based work order management system designed for coordinating work between different stakeholders including administrators, entrepreneurs (contractors), task managers (opgaveansvarlig), and operations (drift) personnel. The system handles work order creation, approval workflows, safety job analysis (SJA), and role-based access control. Built with PHP backend using JSON file storage for data persistence.

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
- **Work Orders**: Structured data with approval status, location coordinates, and stakeholder information
- **Safety Job Analysis**: Risk assessment forms with hazard identification and control measures
- **User Management**: Role-based user accounts with company associations for entrepreneurs

### Interactive Map System
- **Modern Map Interface**: Enhanced map_wo.php with comprehensive design system integration and modern UI components
- **Advanced Popups**: Card-style information popups with structured data display and action buttons
- **Enhanced Search**: Debounced search functionality covering WO numbers, descriptions, job responsible, and P-descriptions
- **Responsive Controls**: Modern filter controls with visual feedback and mobile optimization
- **Live Updates**: Real-time marker counting and visibility management with status indicators

### Known Issues
- **Session Isolation Bug**: After entrepreneur approval, session variables cause all users to see only that entrepreneur's work orders instead of role-appropriate filtering
- **Data Filtering**: Requires cleanup of session variables and proper role-based data filtering implementation

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