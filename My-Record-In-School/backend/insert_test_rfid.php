<?php
// Insert test RFID data for testing
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    include_once 'config/database.php';
    
    $database = new Database();
    $conn = $database->getRfidConnection();
    
    // Clear existing scans
    $clear_stmt = $conn->prepare("DELETE FROM rfid_registration_scans");
    $clear_stmt->execute();
    
    // Insert test RFID scan
    $test_rfid = '1234567890TEST';
    $insert_stmt = $conn->prepare("INSERT INTO rfid_registration_scans (rfid_number, user_type, time_scanned) VALUES (?, 'student', NOW())");
    $insert_stmt->execute([$test_rfid]);
    
    // Verify insertion
    $verify_stmt = $conn->prepare("SELECT * FROM rfid_registration_scans ORDER BY time_scanned DESC LIMIT 1");
    $verify_stmt->execute();
    $result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(array(
        "success" => true,
        "message" => "Test RFID data inserted successfully",
        "inserted_rfid" => $test_rfid,
        "verification" => $result,
        "total_records" => $conn->query("SELECT COUNT(*) FROM rfid_registration_scans")->fetchColumn()
    ));
    
} catch(Exception $e) {
    echo json_encode(array(
        "success" => false,
        "message" => "Error inserting test data: " . $e->getMessage(),
        "error_details" => array(
            "line" => $e->getLine(),
            "file" => basename($e->getFile())
        )
    ));
}
?>