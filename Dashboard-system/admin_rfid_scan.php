<?php
// admin_rfid_scan.php - Handle RFID scanning for admin registration
require_once 'config.php';

// Require admin authentication to access this page
requireAdminAuth();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['rfid_scan'])) {
    $rfid = sanitizeInput($_POST['rfid_scan']);
    
    // Validate RFID format
    if (validateRFID($rfid)) {
        // Check if RFID already exists in admin_scans
        $existing = fetchSingleResult($conn, "SELECT id FROM rfid_admin_scans WHERE rfid_number = ?", [$rfid], "s");
        
        if (!$existing) {
            // Insert new RFID scan
            $stmt = executeQuery($conn, "INSERT INTO rfid_admin_scans (rfid_number) VALUES (?)", [$rfid], "s");
            
            if ($stmt) {
                logActivity("New admin RFID scanned: $rfid");
                $_SESSION['scan_message'] = "RFID $rfid added successfully!";
            } else {
                $_SESSION['scan_error'] = "Failed to add RFID scan.";
            }
        } else {
            $_SESSION['scan_error'] = "RFID $rfid already exists in scan records.";
        }
    } else {
        $_SESSION['scan_error'] = "Invalid RFID format. Must be exactly 10 digits.";
    }
}

// Redirect back to admin registration page
header("Location: admin_register.php");
exit();
?>