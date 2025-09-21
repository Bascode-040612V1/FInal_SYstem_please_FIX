-- Migration script to add test data for the violation app
-- Run this after your main database.sql file

USE `aics_bicutan_system_db`;

-- Add test admin user
INSERT IGNORE INTO admins (role, name, email, password) VALUES 
('Admin', 'Test Admin', 'admin@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- password: password

-- Add test guard user  
INSERT IGNORE INTO guards (name, email, password) VALUES 
('Test Guard', 'guard@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- password: password

-- Add test students
INSERT IGNORE INTO students (surname, firstname, lastname, course, yearlevel, section, rfid, password) VALUES
('Dela Cruz', 'Juan', 'Santos', 'BSIT', '3rd Year', 'BSIT-3A', 'RFID001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Garcia', 'Maria', 'Lopez', 'BSCS', '2nd Year', 'BSCS-2B', 'RFID002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Santos', 'Jose', 'Rizal', 'BSIT', '4th Year', 'BSIT-4A', 'RFID003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Add some test RFID scans
INSERT IGNORE INTO rfid_registration_scans (user_type, rfid) VALUES
('admin', 'RFID_ADMIN_001'),
('student', 'RFID001'),
('student', 'RFID002');

COMMIT;

-- Show summary
SELECT 'Database migration completed' as Status;
SELECT COUNT(*) as 'Total Admins' FROM admins;
SELECT COUNT(*) as 'Total Guards' FROM guards; 
SELECT COUNT(*) as 'Total Students' FROM students;
SELECT COUNT(*) as 'Total Violation Types' FROM violation_types;
SELECT COUNT(*) as 'Total Penalty Matrix Rules' FROM penalty_matrix;