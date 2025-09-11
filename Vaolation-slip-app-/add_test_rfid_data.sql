-- Add unused RFID numbers for testing
-- This script adds test RFID numbers that can be retrieved by the get_rfid.php endpoint

USE rfid_system;

-- Insert additional unused RFID numbers into rfid_admin_scans table
INSERT INTO `rfid_admin_scans` (`rfid_number`, `admin_username`, `admin_role`, `scanned_at`, `is_registered`) VALUES
('3870000001', NULL, 'admin', '2025-09-11 10:00:00', 0),
('3870000002', NULL, 'admin', '2025-09-11 10:05:00', 0),
('3870000003', NULL, 'admin', '2025-09-11 10:10:00', 0),
('3870000004', NULL, 'admin', '2025-09-11 10:15:00', 0),
('3870000005', NULL, 'admin', '2025-09-11 10:20:00', 0),
('3870000006', NULL, 'admin', '2025-09-11 10:25:00', 0),
('3870000007', NULL, 'admin', '2025-09-11 10:30:00', 0),
('3870000008', NULL, 'admin', '2025-09-11 10:35:00', 0),
('3870000009', NULL, 'admin', '2025-09-11 10:40:00', 0),
('3870000010', NULL, 'admin', '2025-09-11 10:45:00', 0);

-- Verify the data was inserted
SELECT COUNT(*) as unused_rfid_count FROM rfid_admin_scans WHERE is_registered = 0;

-- Show the latest unused RFID (this is what get_rfid.php will return)
SELECT rfid_number, scanned_at FROM rfid_admin_scans 
WHERE is_registered = 0 
ORDER BY scanned_at DESC 
LIMIT 1;