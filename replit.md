# Student Attendance Management System - Algiers University

## Overview
A web-based Attendance Management System for Algiers University that streamlines and automates student attendance tracking. The system provides role-based access for students, professors, and administrators.

## Project Structure

```
├── index.php                 # Main entry point & router
├── login.php                 # Authentication page
├── logout.php                # Session termination
├── profile.php               # User profile management
├── unauthorized.php          # Access denied page
├── includes/
│   ├── config.php            # Configuration & session management
│   ├── database.php          # Database connection class (PDO)
│   ├── init_db.php           # Database schema initialization
│   ├── header.php            # Common header with navigation
│   └── footer.php            # Common footer
├── pages/
│   ├── professor/
│   │   ├── home.php          # Professor dashboard
│   │   ├── sessions.php      # Session management
│   │   ├── mark_attendance.php # Mark student attendance
│   │   └── summary.php       # Attendance summary & reports
│   ├── student/
│   │   ├── home.php          # Student dashboard
│   │   └── attendance.php    # View attendance & submit justifications
│   └── admin/
│       ├── home.php          # Admin dashboard & justification review
│       ├── statistics.php    # System-wide statistics & charts
│       └── students.php      # Student management & import/export
├── api/
│   └── get_groups.php        # API endpoint for group data
└── public/
    ├── css/style.css         # Responsive mobile-first styles
    ├── js/app.js             # jQuery application logic
    └── uploads/              # Justification file uploads
```

## Technologies Used

- **Frontend**: jQuery, Responsive CSS (Mobile-first design)
- **Backend**: PHP 8.2
- **Database**: PostgreSQL (via PDO)
- **Charts**: Chart.js for statistics visualization

## Demo Accounts

| Role      | Email                    | Password    |
|-----------|--------------------------|-------------|
| Admin     | admin@univ-alger.dz      | admin123    |
| Professor | prof@univ-alger.dz       | prof123     |
| Student   | student1@univ-alger.dz   | student123  |

## Features

### Professor Features
- View assigned courses and sessions
- Create and manage attendance sessions (lecture, TD, TP, exam)
- Mark student attendance (present, absent, late)
- Track participation scores
- View attendance summary by group/course

### Student Features
- View enrolled courses
- Check attendance status per course
- Submit absence justifications with file upload
- Track justification approval status

### Administrator Features
- System-wide statistics dashboard
- Review and approve/reject justifications
- Manage students (add, remove, edit)
- Import/Export student lists (CSV/Progres format)
- View attendance analytics with charts

## Recent Changes
- Initial setup: Complete attendance management system
- Database schema with proper relationships
- Role-based authentication
- Mobile-responsive design
- File upload for justifications
- CSV import/export functionality

## Running the Project
The project runs on PHP's built-in development server on port 5000:
```bash
php -S 0.0.0.0:5000
```

## Database
Using PostgreSQL with PDO. The database is automatically initialized on first run with sample data including demo users, courses, and groups.
