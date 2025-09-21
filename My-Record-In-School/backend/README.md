# My Record in School - Backend API (Updated for Combined Database)

This is the updated backend API for the My Record in School Android application. It now uses a single combined database (`aics_bicutan_system_db`) instead of separate databases.

## 🆕 What's New

- **Combined Database**: Single database `aics_bicutan_system_db` instead of separate `student_violation_db` and `rfid_system`
- **Enhanced Schema**: Better normalized structure with proper relationships
- **Automated Triggers**: Offense count tracking with automatic penalty calculation
- **Multi-role Support**: Supports admins, guards, and students
- **Improved Security**: Better password hashing and data validation

## Setup Instructions

### 1. XAMPP Installation
1. Download and install XAMPP from https://www.apachefriends.org/
2. Start Apache and MySQL services from XAMPP Control Panel

### 2. Database Setup
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. **Import the new combined database:**
   - Create new database: `aics_bicutan_system_db`
   - Import: `database.sql` (from project root)
3. This will create the new combined database with all tables, relationships, triggers, and sample data

### 3. Backend Deployment
1. Copy the entire `backend` folder to your XAMPP `htdocs` directory
   - Path: `C:\xampp\htdocs\backend` (Windows)
   - Path: `/Applications/XAMPP/htdocs/backend` (Mac)
2. Ensure proper file permissions (755 for directories, 644 for files)

### 4. Migration and Testing
1. **Test Connection**: Visit `http://localhost:8080/backend/test_connection.php`
2. **Run Migration**: Visit `http://localhost:8080/backend/migrate_to_combined_db.php`
3. **Test APIs**: Open `http://localhost:8080/backend/test_combined_api.html`

## Database Schema

### Key Tables

#### students
- `student_number` - Primary key (INT)
- `surname`, `firstname`, `lastname` - Name components
- `course`, `yearlevel`, `section` - Academic info
- `rfid` - RFID tag for attendance
- `password` - Hashed password
- `image` - Profile image path
- `created_at`, `updated_at` - Timestamps

#### violations
- `violation_id` - Primary key
- `student_number` - Foreign key to students
- `violation_type_id` - Foreign key to violation_types
- `offense_count` - Auto-calculated (1-3, cycles)
- `penalty` - Auto-calculated from penalty_matrix
- `recorded_by_role` - 'admin' or 'guard'
- `recorded_by_id` - ID of recording staff
- `acknowledged` - Student acknowledgment status
- `created_at` - Timestamp

#### attendance
- `attendance_id` - Primary key
- `student_number` - Foreign key to students
- `time_in`, `time_out` - Entry/exit times
- `date` - Attendance date
- `status` - Present/Absent
- `created_at` - Timestamp

#### violation_types
- `id` - Primary key
- `violation_name` - Name of violation
- `category` - Dress Code, Conduct, Minor, Major, etc.
- `severity_level` - Severity classification
- `default_penalty` - Default penalty description

#### penalty_matrix
- Maps `violation_type_id` + `offense_count` → `penalty_description`
- Enables flexible penalty management

## API Endpoints

### Base URL
```
http://localhost:8080/backend/
```

### Authentication

#### Login
- **POST** `/auth/login.php`
- **Body:**
  ```json
  {
    "student_id": "2023001",
    "password": "2023001"
  }
  ```

#### Register
- **POST** `/auth/register.php`
- **Body:**
  ```json
  {
    "student_id": "2023004",
    "name": "Surname, Firstname Lastname",
    "password": "2023004",
    "year": "Grade 11",
    "course": "ICT",
    "section": "IC1MA",
    "rfid": "RFID12348" // optional
  }
  ```

### Student Management

#### Update Student Info
- **PUT** `/student/update.php`
- **Body:**
  ```json
  {
    "student_id": "2023001",
    "year": "Grade 12",
    "course": "BSCS",
    "section": "BS1MA"
  }
  ```

### Violations

#### Get Student Violations
- **GET** `/violations/{student_id}`
- **Example:** `/violations/2023001`
- **Optional Query Parameters:**
  - `since` - Timestamp for delta sync
  - `limit` - Limit number of results

#### Acknowledge Violation
- **PUT** `/violations/acknowledge/{violation_id}`
- **Example:** `/violations/acknowledge/1`

### Attendance

#### Get Student Attendance
- **GET** `/attendance/{student_id}?month={month}&year={year}`
- **Example:** `/attendance/2023001?month=12&year=2024`
- **Parameters:**
  - `month` (optional): Month number (1-12)
  - `year` (optional): Year (e.g., 2024)
  - `since` (optional): Timestamp for delta sync
  - `limit` (optional): Limit results

### System

#### Test Connection
- **GET** `/test_connection.php`
- Tests database connection

#### Migration
- **GET** `/migrate_to_combined_db.php`
- Populates database with sample data

## Enhanced Features

### 1. Automated Violation Processing
- **Triggers**: Automatically calculate offense counts (1→2→3→1 cycle)
- **Penalty Matrix**: Auto-assign penalties based on violation type and offense count
- **Category Mapping**: Maps database categories to app-expected categories

### 2. Multi-Role Support
- **Admins**: Full system access
- **Guards**: Can record violations
- **Students**: View their own records

### 3. Enhanced Security
- **Password Hashing**: Uses PHP's `password_hash()` with `PASSWORD_DEFAULT`
- **Prepared Statements**: All queries use prepared statements
- **Input Validation**: Comprehensive input validation
- **CORS Support**: Proper CORS headers for web/mobile access

### 4. Performance Optimizations
- **Delta Sync**: Support for incremental data synchronization
- **Query Optimization**: Efficient queries with proper indexing
- **Pagination**: Optional limit parameters for large datasets

## Sample Data

The migration script includes:
- **3 Sample Students**: 2023001, 2023002, 2023003
- **Sample Violations**: Various violation types with proper categorization
- **Sample Attendance**: Current date attendance records
- **Pre-populated Violation Types**: Complete violation and penalty matrix

## File Structure

```
backend/
├── config/
│   └── database.php              # Updated database configuration
├── auth/
│   ├── login.php                 # Updated login endpoint
│   └── register.php              # Updated registration endpoint
├── student/
│   └── update.php                # Updated student update endpoint
├── violations/
│   ├── index.php                 # Updated violations endpoint
│   └── acknowledge.php           # Updated acknowledge endpoint
├── attendance/
│   └── index.php                 # Updated attendance endpoint
├── test_connection.php           # Database connection test
├── migrate_to_combined_db.php    # 🆕 Sample data migration
├── test_combined_api.html        # 🆕 Comprehensive API test interface
├── README.md                     # This updated documentation
└── [other legacy files...]       # Older test files (still functional)
```

## Error Handling

All endpoints return JSON responses with:
```json
{
  "success": true/false,
  "message": "Description",
  "data": {...}  // When applicable
}
```

## CORS Support

All endpoints include CORS headers to allow cross-origin requests from the Android application.

## Security Notes

- This is a development setup with basic security
- For production, implement:
  - Password hashing
  - JWT tokens
  - Input validation
  - SQL injection protection (using prepared statements)
  - Rate limiting

## Troubleshooting

### Common Issues

1. **Database connection failed**
   - Check XAMPP MySQL is running
   - Verify database credentials in `config/database.php`
   - Ensure databases are created via `schema.sql`

2. **CORS errors**
   - Ensure CORS headers are present in responses
   - Check if Apache is allowing .htaccess files

3. **404 errors**
   - Verify file paths are correct
   - Check XAMPP document root configuration
   - Ensure files have proper permissions

### Testing Endpoints

Use tools like Postman, curl, or the Android app to test endpoints:

```bash
# Test connection
curl http://localhost:8080/backend/test_connection.php

# Test login
curl -X POST http://localhost:8080/backend/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"student_id":"2023001","password":"2023001"}'
```

## Support

For issues with the backend setup:
1. Check XAMPP error logs
2. Verify database schema is imported correctly
3. Ensure all files are in the correct directory structure
4. Test endpoints individually before using with the Android app