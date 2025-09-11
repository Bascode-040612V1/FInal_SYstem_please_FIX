<?php
require_once '../config/database.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, "Only GET method is allowed");
}

$database = new Database();
$rfidConn = $database->getRfidConnection();

if (!$rfidConn) {
    sendResponse(false, "RFID database connection failed");
}

try {
    // Get the latest unused RFID scan from rfid_admin_scans table
    $query = "SELECT rfid_number, scanned_at FROM rfid_admin_scans 
              WHERE (is_used = 0 OR is_used IS NULL)
              ORDER BY scanned_at DESC 
              LIMIT 1";
    $stmt = $rfidConn->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $data = [
            'rfid_number' => $result['rfid_number'],
            'scanned_at' => $result['scanned_at'],
            'status' => 'available'
        ];
        sendResponse(true, "RFID number retrieved successfully", $data);
    } else {
        sendResponse(false, "No unused RFID scans available. Please scan your RFID card first.");
    }
    
} catch(PDOException $exception) {
    error_log("RFID fetch error: " . $exception->getMessage());
    sendResponse(false, "Failed to retrieve RFID number: " . $exception->getMessage());
}
?>