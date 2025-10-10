<?php
require_once '../config/database.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, "Only GET method is allowed");
}

$database = new Database();
$conn = $database->getViolationConnection();

if (!$conn) {
    sendResponse(false, "Database connection failed");
}

try {
    // Get the latest ADMIN RFID scan from rfid_admin_scans table
    // This app only handles admin/guard registration, NOT students
    // Students are handled by My-Record-In-School app via rfid_registration_scans
    $query = "SELECT rfid_number FROM rfid_admin_scans 
              WHERE is_registered = 0
              ORDER BY scanned_at DESC 
              LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        sendResponse(true, "Admin RFID number retrieved successfully", $result['rfid_number']);
    } else {
        sendResponse(false, "No admin RFID scans available. Please scan an admin RFID card first.");
    }
    
} catch(PDOException $exception) {
    error_log("Admin RFID fetch error: " . $exception->getMessage());
    sendResponse(false, "Failed to retrieve admin RFID number");
}
?>