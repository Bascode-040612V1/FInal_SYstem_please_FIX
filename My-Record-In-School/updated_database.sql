-- CREATE DATABASE
CREATE DATABASE IF NOT EXISTS `aics_bicutan_system_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `aics_bicutan_system_db`;

-- ADMINS
CREATE TABLE IF NOT EXISTS admins (
  rfid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  role ENUM('Admin','Teacher') NOT NULL DEFAULT 'Admin',
  name VARCHAR(200) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  image VARCHAR(512),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- GUARDS (security staff who use the guard app)
CREATE TABLE IF NOT EXISTS guards (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- STUDENTS
CREATE TABLE IF NOT EXISTS students (
  student_number INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  surname VARCHAR(120) NOT NULL,
  firstname VARCHAR(120) NOT NULL,
  lastname VARCHAR(120),
  course VARCHAR(100),
  yearlevel VARCHAR(50),
  section VARCHAR(80),
  rfid VARCHAR(100) UNIQUE, -- some RFID tags are strings; adjust if numeric
  password VARCHAR(255),
  image VARCHAR(512),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RFID SCANS (registration / login scanner)
-- Updated to match the backend code expectations
CREATE TABLE IF NOT EXISTS rfid_registration_scans (
  scan_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  rfid_number VARCHAR(100) NOT NULL,
  user_type ENUM('admin','student') NOT NULL,
  time_scanned TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (rfid_number),
  INDEX (user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ATTENDANCE
CREATE TABLE IF NOT EXISTS attendance (
  attendance_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_number INT NOT NULL,
  time_in DATETIME,
  time_out DATETIME,
  date DATE NOT NULL,
  status ENUM('Present','Absent') NOT NULL DEFAULT 'Present',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_number) REFERENCES students(student_number) ON DELETE CASCADE,
  UNIQUE KEY student_date_unique (student_number, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VIOLATION TYPES (master list)
CREATE TABLE IF NOT EXISTS violation_types (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  violation_name VARCHAR(255) NOT NULL UNIQUE,
  category VARCHAR(100),
  severity_level ENUM('Minor','Major','Severe','Conduct','Dress Code','Miscellaneous') DEFAULT 'Minor',
  default_penalty TEXT,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PENALTY MATRIX (per violation type + offense count)
CREATE TABLE IF NOT EXISTS penalty_matrix (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  violation_type_id INT NOT NULL,
  offense_count TINYINT NOT NULL, -- 1,2,3
  penalty_description TEXT,
  severity_level ENUM('Minor','Major','Severe','Conduct','Dress Code','Miscellaneous') DEFAULT 'Minor',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (violation_type_id) REFERENCES violation_types(id) ON DELETE CASCADE,
  UNIQUE KEY violation_offense_unique (violation_type_id, offense_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- STUDENT OFFENSE COUNTS (keeps latest count per student per violation type)
CREATE TABLE IF NOT EXISTS student_offense_counts (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  studentnumber INT NOT NULL,
  violation_type_id INT NOT NULL,
  offense_count TINYINT NOT NULL DEFAULT 0,
  last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (studentnumber) REFERENCES students(student_number) ON DELETE CASCADE,
  FOREIGN KEY (violation_type_id) REFERENCES violation_types(id) ON DELETE CASCADE,
  UNIQUE KEY student_violation_unique (studentnumber, violation_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VIOLATIONS (individual recorded violation events)
CREATE TABLE IF NOT EXISTS violations (
  violation_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_number INT NOT NULL,
  violation_type_id INT NOT NULL,
  violation_description TEXT,
  offense_count TINYINT NOT NULL DEFAULT 1, -- computed by trigger to cycle 1-3
  penalty TEXT,
  recorded_by_role ENUM('admin','guard') NOT NULL,
  recorded_by_id INT NOT NULL, -- references admins.rfid for admin or guards.id for guard (store role in recorded_by_role)
  acknowledged TINYINT(1) NOT NULL DEFAULT 0,
  acknowledged_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_number) REFERENCES students(student_number) ON DELETE CASCADE,
  FOREIGN KEY (violation_type_id) REFERENCES violation_types(id) ON DELETE RESTRICT,
  INDEX (student_number),
  INDEX (violation_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ----------------------------------------------------------------
-- Insert provided violation types (de-duplicated & normalized)
-- ----------------------------------------------------------------
INSERT IGNORE INTO violation_types (violation_name, category, severity_level, default_penalty)
VALUES
('No ID', 'Dress Code', 'Dress Code', 'Warning/Grounding/Suspension'),
('Wearing of rubber slippers', 'Dress Code', 'Dress Code', 'Warning/Grounding/Suspension'),
('Improper wearing of uniform', 'Dress Code', 'Dress Code', 'Warning/Grounding/Suspension'),
('Non-prescribed haircut', 'Dress Code', 'Dress Code', 'Warning/Grounding/Suspension'),
('Wearing of earrings', 'Dress Code', 'Dress Code', 'Warning/Grounding/Suspension'),
('Wearing of multiple earrings', 'Dress Code', 'Dress Code', 'Warning/Grounding/Suspension'),

('Using cellphones/ gadgets during class hours', 'Minor','Minor','Warning/Grounding/Suspension'),
('Eating inside the laboratories', 'Minor','Minor','Warning/Grounding/Community Service'),
('Improper not wearing/ tampering of ID', 'Minor','Minor','Warning/Grounding/Suspension'),
('Improper/tampered ID', 'Minor','Minor','Warning/Grounding/Suspension'),
('Improper hairstyle', 'Minor','Minor','Warning/Grounding/Suspension'),
('Improper Uniform', 'Minor','Minor','Warning/Grounding/Suspension'),

('Stealing', 'Major','Major','Suspension/Non-readmission/Expulsion'),
('Vandalism', 'Major','Major','Community Service/Suspension/Non-readmission'),
('Verbal assault', 'Major','Major','Grounding/Suspension/Non-readmission'),
('Organizing/joining fraternity activities', 'Major','Major','Suspension/Non-readmission/Expulsion'),

('Cutting Classes', 'Conduct','Conduct','Warning/Probation/Suspension'),
('Cheating/Academic Dishonesty', 'Conduct','Conduct','Probation/Suspension/Non-readmission'),
('Theft/Stealing', 'Conduct','Conduct','Suspension/Non-readmission/Expulsion'),
('Inflicting/Direct Assault', 'Conduct','Conduct','Suspension/Non-readmission/Expulsion'),
('Gambling', 'Conduct','Conduct','Suspension/Non-readmission/Expulsion'),
('Smoking within the school vicinity', 'Conduct','Conduct','Grounding/Suspension/Non-readmission'),
('Possession/Use of Prohibited Drugs', 'Conduct','Conduct','Suspension/Expulsion'),
('Possession/Use of Liquor/Alcoholic Beverages', 'Conduct','Conduct','Suspension/Non-readmission'),

('Others', 'Miscellaneous','Miscellaneous','Varies');

-- ----------------------------------------------------------------
-- Insert penalty_matrix based on your general penalty structure
-- (maps violation_type -> offense_count -> penalty description)
-- ----------------------------------------------------------------
-- We'll fill a reasonable default: 1->Warning, 2->Grounding/Probation/Guidance, 3->Suspension/Community Service/Expulsion
-- For each violation type: three rows
INSERT IGNORE INTO penalty_matrix (violation_type_id, offense_count, penalty_description, severity_level)
SELECT id, 1, 'Verbal/Written Warning', severity_level FROM violation_types
ON DUPLICATE KEY UPDATE penalty_description = VALUES(penalty_description);

INSERT IGNORE INTO penalty_matrix (violation_type_id, offense_count, penalty_description, severity_level)
SELECT id, 2, 'Grounding / Guidance Consultation / Probation', severity_level FROM violation_types
ON DUPLICATE KEY UPDATE penalty_description = VALUES(penalty_description);

INSERT IGNORE INTO penalty_matrix (violation_type_id, offense_count, penalty_description, severity_level)
SELECT id, 3, 'Suspension / Community Service / Further Disciplinary Action (see admin)', severity_level FROM violation_types
ON DUPLICATE KEY UPDATE penalty_description = VALUES(penalty_description);


-- ----------------------------------------------------------------
-- TRIGGERS to maintain offense counts and cycle 1->2->3->1
-- Workflow:
--   BEFORE INSERT on violations:
--     - read student_offense_counts for this student & violation_type_id
--     - compute new offense_count = (current + 1) or 1 if none, and cycle to 1 after 3
--     - set NEW.offense_count
--   AFTER INSERT on violations:
--     - upsert student_offense_counts with the new count
-- ----------------------------------------------------------------

DELIMITER $$

CREATE TRIGGER trg_violations_before_insert
BEFORE INSERT ON violations
FOR EACH ROW
BEGIN
  DECLARE current_count INT DEFAULT 0;
  -- get current offense_count for this student & violation type
  SELECT offense_count INTO current_count
    FROM student_offense_counts
    WHERE studentnumber = NEW.student_number
      AND violation_type_id = NEW.violation_type_id
    LIMIT 1;

  IF current_count IS NULL THEN
    SET current_count = 0;
  END IF;

  -- compute next count: cycle 1..3
  SET current_count = current_count + 1;
  IF current_count > 3 THEN
    SET current_count = 1;
  END IF;

  SET NEW.offense_count = current_count;

  -- set penalty from penalty_matrix if available (optional)
  DECLARE penalty_text TEXT DEFAULT NULL;
  SELECT penalty_description INTO penalty_text
    FROM penalty_matrix
    WHERE violation_type_id = NEW.violation_type_id
      AND offense_count = NEW.offense_count
    LIMIT 1;

  IF penalty_text IS NOT NULL THEN
    SET NEW.penalty = penalty_text;
  END IF;

  -- default acknowledged remains 0, acknowledged_at NULL
END$$

CREATE TRIGGER trg_violations_after_insert
AFTER INSERT ON violations
FOR EACH ROW
BEGIN
  -- upsert student_offense_counts with the new offense_count
  INSERT INTO student_offense_counts (studentnumber, violation_type_id, offense_count, last_updated)
  VALUES (NEW.student_number, NEW.violation_type_id, NEW.offense_count, NOW())
  ON DUPLICATE KEY UPDATE offense_count = VALUES(offense_count), last_updated = NOW();
END$$

DELIMITER ;

-- ----------------------------------------------------------------
-- Helpful indexes for searching
-- ----------------------------------------------------------------
CREATE INDEX idx_students_rfid ON students (rfid);
CREATE INDEX idx_students_name ON students (surname, firstname, lastname);
CREATE INDEX idx_violations_student ON violations (student_number);
CREATE INDEX idx_violation_type ON violation_types (violation_name);

-- ----------------------------------------------------------------
-- Example: add admin/guard/student accounts (commented out) 
-- ----------------------------------------------------------------
/*
INSERT INTO admins (role, name, email, password) VALUES ('Admin','Main Admin','admin@example.com','[hash]');
INSERT INTO guards (name,email,password) VALUES ('Guard 1','guard1@example.com','[hash]');
INSERT INTO students (surname,firstname,lastname,course,yearlevel,section,rfid,password) VALUES
('Dela Cruz','Juan','S','BSCS','1','BS1MA','RFID12345','[hash]');
*/

-- End of script