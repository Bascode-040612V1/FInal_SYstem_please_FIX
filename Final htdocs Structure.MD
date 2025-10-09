C:\xampp\htdocs\
├── Dashboard-system/           # Main web dashboard
│   ├── index.php
│   ├── admin.php
│   ├── config.php
│   ├── migrate_database.php
│   └── ... (all dashboard files)
├── student-api/               # Student mobile app backend
│   ├── config/database.php
│   ├── attendance/
│   ├── auth/
│   ├── rfid/
│   └── test/
└── violation-api/             # Violation mobile app backend
    ├── config/database.php
    ├── violations/
    ├── auth/
    ├── students/
    └── test/

    Verification Checklist:
Start XAMPP: Apache + MySQL services running
Database Migration: Run migrate_database.php successfully
Dashboard Access: http://localhost/Dashboard-system/ loads
API Testing: Both API endpoints respond correctly
Mobile Apps: Update API URLs and test connectivity
