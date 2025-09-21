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
    // Get the latest RFID scan from rfid_registration_scans table
    $query = "SELECT rfid FROM rfid_registration_scans 
              WHERE user_type IN ('admin', 'student') 
              ORDER BY time_scanned DESC 
              LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        sendResponse(true, "RFID number retrieved successfully", $result['rfid']);
    } else {
        sendResponse(false, "No RFID scans available");
    }
    
} catch(PDOException $exception) {
    error_log("RFID fetch error: " . $exception->getMessage());
    sendResponse(false, "Failed to retrieve RFID number");
}
?>