-- Database Migration: Add image columns to students tables
-- Date: 2025-09-10
-- Purpose: Add image column support for student profile pictures

-- Update student_violation_db database
USE student_violation_db;

-- Add image column to students table if it doesn't exist
ALTER TABLE students 
ADD COLUMN IF NOT EXISTS image VARCHAR(255) DEFAULT 'assets/default-profile.png' 
AFTER section;

-- Update existing records to have default image
UPDATE students 
SET image = 'assets/default-profile.png' 
WHERE image IS NULL OR image = '';

-- Update rfid_system database  
USE rfid_system;

-- The students table in rfid_system already has image column, so no changes needed
-- But let's ensure it has the default value properly set
UPDATE students 
SET image = 'assets/default-profile.png' 
WHERE image IS NULL OR image = '';

-- Create index for better performance on image lookups
ALTER TABLE students ADD INDEX IF NOT EXISTS idx_image (image);

-- Switch back to student_violation_db and add index there too
USE student_violation_db;
ALTER TABLE students ADD INDEX IF NOT EXISTS idx_image (image);

COMMIT;