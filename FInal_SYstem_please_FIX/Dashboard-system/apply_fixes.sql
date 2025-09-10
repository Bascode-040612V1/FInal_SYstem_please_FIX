-- apply_fixes.sql
-- SQL script to apply all the fixes to an existing database

-- 1. Add RFID column to admins table if it doesn't exist
ALTER TABLE `admins` ADD COLUMN `rfid` VARCHAR(50) UNIQUE AFTER `username`;

-- 2. Update existing admin passwords to use proper hashing and add RFID
UPDATE `admins` SET 
    `rfid` = '3870770196',
    `password` = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE `username` = 'ajJ';

UPDATE `admins` SET 
    `rfid` = '3870770197',
    `password` = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE `username` = 'Guard';

-- 3. Create rfid_admin_scans table
CREATE TABLE IF NOT EXISTS `rfid_admin_scans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rfid_number` varchar(50) NOT NULL,
  `admin_username` varchar(50) DEFAULT NULL,
  `admin_role` varchar(20) DEFAULT 'admin',
  `scanned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_registered` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `rfid_number` (`rfid_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Insert sample admin scan records
INSERT INTO `rfid_admin_scans` (`rfid_number`, `admin_username`, `admin_role`, `is_registered`) VALUES
('3870770196', 'ajJ', 'admin', 1),
('3870770197', 'Guard', 'admin', 1);

-- 5. Add unique constraint to admin RFID if not exists
ALTER TABLE `admins` ADD UNIQUE KEY `rfid` (`rfid`);