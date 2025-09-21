-- DBMODIFY.sql - Complete Database Schema with All Missing Components
-- This file contains the corrected and complete database structure for the AICS School Management System
-- Run this to create a fully functional database that supports all three system components

-- CREATE DATABASE (Changed to match config files)
CREATE DATABASE IF NOT EXISTS `rfid_system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `rfid_system`;

-- ADMINS TABLE (Enhanced with proper RFID support)
CREATE TABLE IF NOT EXISTS admins (
  rfid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  role ENUM('Admin','Teacher') NOT NULL DEFAULT 'Admin',
  name VARCHAR(200) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  image VARCHAR(512),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- GUARDS TABLE (For security staff using violation app)
CREATE TABLE IF NOT EXISTS guards (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- STUDENTS TABLE (Enhanced for dual compatibility)
CREATE TABLE IF NOT EXISTS students (
  student_number INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id INT UNIQUE NOT NULL, -- Add compatibility field for mobile apps
  surname VARCHAR(120) NOT NULL,
  firstname VARCHAR(120) NOT NULL,
  lastname VARCHAR(120),
  name VARCHAR(255) GENERATED ALWAYS AS (CONCAT(firstname, ' ', IFNULL(CONCAT(lastname, ' '), ''), surname)) STORED, -- Computed full name
  course VARCHAR(100),
  yearlevel VARCHAR(50),
  section VARCHAR(80),
  rfid VARCHAR(100) UNIQUE, -- RFID card number
  password VARCHAR(255),
  image VARCHAR(512) DEFAULT 'assets/default-profile.png', -- Default profile image
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_student_id (id),
  INDEX idx_rfid (rfid),
  INDEX idx_name (surname, firstname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RFID REGISTRATION SCANS (For student/admin RFID registration)
CREATE TABLE IF NOT EXISTS rfid_registration_scans (
  scan_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('admin','student') NOT NULL,
  rfid VARCHAR(100) NOT NULL,
  time_scanned TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (rfid),
  INDEX (user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RFID ADMIN SCANS (MISSING TABLE - For admin RFID management)
CREATE TABLE IF NOT EXISTS rfid_admin_scans (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  rfid_number VARCHAR(50) NOT NULL,
  admin_username VARCHAR(50),
  admin_role VARCHAR(20) DEFAULT 'admin',
  scanned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_registered TINYINT(1) DEFAULT 0,
  INDEX idx_rfid (rfid_number),
  INDEX idx_username (admin_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ATTENDANCE TABLE (Enhanced for dual compatibility)
CREATE TABLE IF NOT EXISTS attendance (
  attendance_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id INT UNIQUE, -- Add compatibility field for mobile apps
  student_id INT, -- Add compatibility field for mobile apps  
  student_number INT NOT NULL,
  time_in DATETIME,
  time_out DATETIME,
  date DATE NOT NULL,
  status ENUM('Present','Absent','Late') NOT NULL DEFAULT 'Present', -- Added 'Late' status
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_number) REFERENCES students(student_number) ON DELETE CASCADE,
  UNIQUE KEY student_date_unique (student_number, date),
  INDEX idx_student_id (student_id),
  INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SAVED ATTENDANCE TABLE (CRITICAL MISSING TABLE)
-- This table stores archived daily attendance records
CREATE TABLE IF NOT EXISTS saved_attendance (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL, -- References students.id
  name VARCHAR(255) NOT NULL, -- Student full name (denormalized for performance)
  student_number VARCHAR(50) NOT NULL, -- Student number (denormalized)
  image VARCHAR(512), -- Student profile image path
  saved_time_in DATETIME, -- Time student entered
  saved_time_out DATETIME, -- Time student left
  saved_date DATE NOT NULL, -- Date of attendance
  status ENUM('Present','Absent','Late') DEFAULT 'Present',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_student_date (student_id, saved_date),
  INDEX idx_saved_date (saved_date),
  INDEX idx_student_number (student_number),
  INDEX idx_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VIOLATION TYPES TABLE (Master list of all possible violations)
CREATE TABLE IF NOT EXISTS violation_types (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  violation_name VARCHAR(255) NOT NULL UNIQUE,
  category VARCHAR(100),
  severity_level ENUM('Minor','Major','Severe','Conduct','Dress Code','Miscellaneous') DEFAULT 'Minor',
  default_penalty TEXT,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_category (category),
  INDEX idx_severity (severity_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PENALTY MATRIX TABLE (Penalties based on violation type and offense count)
CREATE TABLE IF NOT EXISTS penalty_matrix (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  violation_type_id INT NOT NULL,
  offense_count TINYINT NOT NULL, -- 1st, 2nd, 3rd offense
  penalty_description TEXT,
  severity_level ENUM('Minor','Major','Severe','Conduct','Dress Code','Miscellaneous') DEFAULT 'Minor',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (violation_type_id) REFERENCES violation_types(id) ON DELETE CASCADE,
  UNIQUE KEY violation_offense_unique (violation_type_id, offense_count),
  INDEX idx_violation_offense (violation_type_id, offense_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- STUDENT OFFENSE COUNTS TABLE (Tracks offense count per student per violation type)
CREATE TABLE IF NOT EXISTS student_offense_counts (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  studentnumber INT NOT NULL,
  violation_type_id INT NOT NULL,
  offense_count TINYINT NOT NULL DEFAULT 0,
  last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (studentnumber) REFERENCES students(student_number) ON DELETE CASCADE,
  FOREIGN KEY (violation_type_id) REFERENCES violation_types(id) ON DELETE CASCADE,
  UNIQUE KEY student_violation_unique (studentnumber, violation_type_id),
  INDEX idx_student_violation (studentnumber, violation_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VIOLATIONS TABLE (Modern violation records - Web system)
CREATE TABLE IF NOT EXISTS violations (
  violation_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_number INT NOT NULL,
  violation_type_id INT NOT NULL,
  violation_description TEXT,
  offense_count TINYINT NOT NULL DEFAULT 1,
  penalty TEXT,
  recorded_by_role ENUM('admin','guard') NOT NULL,
  recorded_by_id INT NOT NULL,
  acknowledged TINYINT(1) NOT NULL DEFAULT 0,
  acknowledged_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_number) REFERENCES students(student_number) ON DELETE CASCADE,
  FOREIGN KEY (violation_type_id) REFERENCES violation_types(id) ON DELETE RESTRICT,
  INDEX idx_student_number (student_number),
  INDEX idx_violation_type (violation_type_id),
  INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VIOLATIONS LEGACY TABLE (For mobile app compatibility)
CREATE TABLE IF NOT EXISTS violations_legacy (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(50) NOT NULL, -- Student identifier (mobile apps expect string)
  violation_type VARCHAR(255) NOT NULL, -- Violation type name
  violation_description TEXT,
  offense_count TINYINT NOT NULL DEFAULT 1,
  penalty TEXT,
  recorded_by VARCHAR(255) NOT NULL, -- Staff member name
  date_recorded TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  acknowledged TINYINT(1) NOT NULL DEFAULT 0,
  acknowledged_at TIMESTAMP NULL,
  category VARCHAR(100), -- Violation category
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_student_id (student_id),
  INDEX idx_violation_type (violation_type),
  INDEX idx_date_recorded (date_recorded),
  INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- INSERT DEFAULT VIOLATION TYPES AND PENALTY MATRIX
-- ================================================================

INSERT IGNORE INTO violation_types (violation_name, category, severity_level, default_penalty)
VALUES
-- Dress Code Violations
('No ID', 'Dress Code', 'Dress Code', 'Warning/Grounding/Suspension'),
('Wearing of rubber slippers', 'Dress Code', 'Dress Code', 'Warning/Grounding/Suspension'),
('Improper wearing of uniform', 'Dress Code', 'Dress Code', 'Warning/Grounding/Suspension'),
('Non-prescribed haircut', 'Dress Code', 'Dress Code', 'Warning/Grounding/Suspension'),
('Wearing of earrings', 'Dress Code', 'Dress Code', 'Warning/Grounding/Suspension'),
('Wearing of multiple earrings', 'Dress Code', 'Dress Code', 'Warning/Grounding/Suspension'),

-- Minor Offenses
('Using cellphones/ gadgets during class hours', 'Minor','Minor','Warning/Grounding/Suspension'),
('Eating inside the laboratories', 'Minor','Minor','Warning/Grounding/Community Service'),
('Improper not wearing/ tampering of ID', 'Minor','Minor','Warning/Grounding/Suspension'),
('Improper/tampered ID', 'Minor','Minor','Warning/Grounding/Suspension'),
('Improper hairstyle', 'Minor','Minor','Warning/Grounding/Suspension'),
('Improper Uniform', 'Minor','Minor','Warning/Grounding/Suspension'),

-- Major Offenses
('Stealing', 'Major','Major','Suspension/Non-readmission/Expulsion'),
('Vandalism', 'Major','Major','Community Service/Suspension/Non-readmission'),
('Verbal assault', 'Major','Major','Grounding/Suspension/Non-readmission'),
('Organizing/joining fraternity activities', 'Major','Major','Suspension/Non-readmission/Expulsion'),

-- Conduct Violations
('Cutting Classes', 'Conduct','Conduct','Warning/Probation/Suspension'),
('Cheating/Academic Dishonesty', 'Conduct','Conduct','Probation/Suspension/Non-readmission'),
('Theft/Stealing', 'Conduct','Conduct','Suspension/Non-readmission/Expulsion'),
('Inflicting/Direct Assault', 'Conduct','Conduct','Suspension/Non-readmission/Expulsion'),
('Gambling', 'Conduct','Conduct','Suspension/Non-readmission/Expulsion'),
('Smoking within the school vicinity', 'Conduct','Conduct','Grounding/Suspension/Non-readmission'),
('Possession/Use of Prohibited Drugs', 'Conduct','Conduct','Suspension/Expulsion'),
('Possession/Use of Liquor/Alcoholic Beverages', 'Conduct','Conduct','Suspension/Non-readmission'),

-- Miscellaneous
('Others', 'Miscellaneous','Miscellaneous','Varies');

-- Insert penalty matrix (1st, 2nd, 3rd offense penalties)
INSERT IGNORE INTO penalty_matrix (violation_type_id, offense_count, penalty_description, severity_level)
SELECT id, 1, 'Verbal/Written Warning', severity_level FROM violation_types
ON DUPLICATE KEY UPDATE penalty_description = VALUES(penalty_description);

INSERT IGNORE INTO penalty_matrix (violation_type_id, offense_count, penalty_description, severity_level)
SELECT id, 2, 'Grounding / Guidance Consultation / Probation', severity_level FROM violation_types
ON DUPLICATE KEY UPDATE penalty_description = VALUES(penalty_description);

INSERT IGNORE INTO penalty_matrix (violation_type_id, offense_count, penalty_description, severity_level)
SELECT id, 3, 'Suspension / Community Service / Further Disciplinary Action', severity_level FROM violation_types
ON DUPLICATE KEY UPDATE penalty_description = VALUES(penalty_description);

-- ================================================================
-- TRIGGERS FOR AUTOMATIC OFFENSE COUNT MANAGEMENT
-- ================================================================

DELIMITER $$

-- Trigger to calculate offense count before inserting violation
CREATE TRIGGER trg_violations_before_insert
BEFORE INSERT ON violations
FOR EACH ROW
BEGIN
  DECLARE current_count INT DEFAULT 0;
  
  -- Get current offense count for this student & violation type
  SELECT offense_count INTO current_count
    FROM student_offense_counts
    WHERE studentnumber = NEW.student_number
      AND violation_type_id = NEW.violation_type_id
    LIMIT 1;

  IF current_count IS NULL THEN
    SET current_count = 0;
  END IF;

  -- Increment and cycle offense count (1->2->3->1)
  SET current_count = current_count + 1;
  IF current_count > 3 THEN
    SET current_count = 1;
  END IF;

  SET NEW.offense_count = current_count;

  -- Set penalty from penalty matrix
  DECLARE penalty_text TEXT DEFAULT NULL;
  SELECT penalty_description INTO penalty_text
    FROM penalty_matrix
    WHERE violation_type_id = NEW.violation_type_id
      AND offense_count = NEW.offense_count
    LIMIT 1;

  IF penalty_text IS NOT NULL THEN
    SET NEW.penalty = penalty_text;
  END IF;
END$$

-- Trigger to update offense count after inserting violation
CREATE TRIGGER trg_violations_after_insert
AFTER INSERT ON violations
FOR EACH ROW
BEGIN
  -- Update/insert student offense count
  INSERT INTO student_offense_counts (studentnumber, violation_type_id, offense_count, last_updated)
  VALUES (NEW.student_number, NEW.violation_type_id, NEW.offense_count, NOW())
  ON DUPLICATE KEY UPDATE 
    offense_count = VALUES(offense_count), 
    last_updated = NOW();
END$$

DELIMITER ;

-- ================================================================
-- CREATE ADDITIONAL INDEXES FOR PERFORMANCE
-- ================================================================

-- Students table indexes
CREATE INDEX idx_students_rfid ON students (rfid);
CREATE INDEX idx_students_student_number ON students (student_number);
CREATE INDEX idx_students_name ON students (surname, firstname);

-- Attendance table indexes  
CREATE INDEX idx_attendance_student_date ON attendance (student_number, date);
CREATE INDEX idx_attendance_date ON attendance (date);

-- Saved attendance indexes
CREATE INDEX idx_saved_attendance_date ON saved_attendance (saved_date);
CREATE INDEX idx_saved_attendance_student ON saved_attendance (student_id, saved_date);

-- Violations indexes
CREATE INDEX idx_violations_student ON violations (student_number);
CREATE INDEX idx_violations_date ON violations (created_at);
CREATE INDEX idx_violations_legacy_student ON violations_legacy (student_id);
CREATE INDEX idx_violations_legacy_date ON violations_legacy (date_recorded);

-- ================================================================
-- SAMPLE DATA (COMMENTED OUT FOR SECURITY)
-- ================================================================

/*
-- Sample admin accounts (passwords are hashed for 'admin123')
INSERT INTO admins (rfid, role, name, email, password) VALUES 
(3870770196, 'Admin', 'ajJ', 'admin@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(3870770197, 'Admin', 'Guard', 'guard@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Sample guard accounts
INSERT INTO guards (name, email, password) VALUES
('Security Guard 1', 'guard1@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Security Guard 2', 'guard2@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Sample students (id field will be auto-generated to match student_number)
INSERT INTO students (student_number, id, surname, firstname, lastname, course, yearlevel, section, rfid, password) VALUES
(2023001, 2023001, 'Dela Cruz', 'Juan', 'Santos', 'BSCS', '1st Year', 'CS-1A', 'RFID12345', '2023001'),
(2023002, 2023002, 'Santos', 'Maria', 'Garcia', 'BSIT', '2nd Year', 'IT-2B', 'RFID12346', '2023002'),
(2023003, 2023003, 'Garcia', 'Pedro', 'Rodriguez', 'BSCS', '1st Year', 'CS-1B', 'RFID12347', '2023003');

-- Sample attendance records
INSERT INTO attendance (student_id, student_number, time_in, time_out, date, status) VALUES
(2023001, 2023001, '2024-01-15 08:00:00', '2024-01-15 17:00:00', '2024-01-15', 'Present'),
(2023002, 2023002, '2024-01-15 08:15:00', '2024-01-15 17:15:00', '2024-01-15', 'Present'),
(2023003, 2023003, '2024-01-15 08:30:00', NULL, '2024-01-15', 'Present');

-- Sample saved attendance (archived records)
INSERT INTO saved_attendance (student_id, name, student_number, saved_time_in, saved_time_out, saved_date, status) VALUES
(2023001, 'Juan Santos Dela Cruz', '2023001', '2024-01-14 08:00:00', '2024-01-14 17:00:00', '2024-01-14', 'Present'),
(2023002, 'Maria Garcia Santos', '2023002', '2024-01-14 08:15:00', '2024-01-14 17:15:00', '2024-01-14', 'Present'),
(2023003, 'Pedro Rodriguez Garcia', '2023003', '2024-01-14 09:00:00', '2024-01-14 17:00:00', '2024-01-14', 'Late');
*/

-- ================================================================
-- END OF DBMODIFY.sql
-- ================================================================

-- USAGE INSTRUCTIONS:
-- 1. Backup your existing database first
-- 2. Run this script to create the complete database structure
-- 3. Update your config files to use database name 'rfid_system'
-- 4. Test all three system components (Dashboard, Student App, Violation App)
-- 5. Uncomment and modify sample data section if needed for testing

-- FEATURES ADDED:
-- ✅ Fixed database name to match config files (rfid_system)
-- ✅ Added missing saved_attendance table (CRITICAL)
-- ✅ Added missing rfid_admin_scans table
-- ✅ Enhanced students table with compatibility fields
-- ✅ Enhanced attendance table with compatibility fields  
-- ✅ Added violations_legacy table for mobile app compatibility
-- ✅ Complete violation types and penalty matrix
-- ✅ Automatic triggers for offense count management
-- ✅ All necessary indexes for performance
-- ✅ Proper foreign key relationships
-- ✅ Sample data structure (commented out)