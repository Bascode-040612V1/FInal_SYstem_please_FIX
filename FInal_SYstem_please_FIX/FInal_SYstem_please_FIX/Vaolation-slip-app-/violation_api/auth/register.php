<?php
require_once '../config/database.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, "Only POST method is allowed");
}

// Get and validate input
$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->username) || !isset($data->email) || !isset($data->password)) {
    sendResponse(false, "Username, email and password are required");
}

// Validate input format
$username = validateInput($data->username, 'string', 50);
$email = validateInput($data->email, 'email');
$password = validateInput($data->password, 'string', 128);
$role = validateInput($data->role ?? 'guard', 'string', 20);

if (!$username || !$email || !$password) {
    sendResponse(false, "Invalid input format");
}

$database = new Database();
$conn = $database->getViolationConnection();
$rfidConn = $database->getRfidConnection();

if (!$conn) {
    sendResponse(false, "Database connection failed");
}

if (!$rfidConn) {
    sendResponse(false, "RFID database connection failed");
}

try {
    // Start transaction for both databases
    $conn->beginTransaction();
    $rfidConn->beginTransaction();
    
    // Check if user already exists
    $query = "SELECT id FROM users WHERE email = :email OR username = :username";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":username", $username);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $conn->rollBack();
        $rfidConn->rollBack();
        sendResponse(false, "User with this email or username already exists");
    }

    // Get the latest unused RFID from rfid_admin_scans
    $rfidQuery = "SELECT rfid_number, id FROM rfid_admin_scans 
                  WHERE is_used = 0 OR is_used IS NULL 
                  ORDER BY scanned_at DESC 
                  LIMIT 1";
    $rfidStmt = $rfidConn->prepare($rfidQuery);
    $rfidStmt->execute();
    
    $rfid = null;
    $rfid_scan_id = null;
    
    if ($rfidStmt->rowCount() > 0) {
        $rfidResult = $rfidStmt->fetch(PDO::FETCH_ASSOC);
        $rfid = $rfidResult['rfid_number'];
        $rfid_scan_id = $rfidResult['id'];
    } else {
        $conn->rollBack();
        $rfidConn->rollBack();
        sendResponse(false, "No RFID available for registration. Please scan your RFID card first.");
    }

    // Hash password securely
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user with RFID
    $query = "INSERT INTO users (username, email, password, role, rfid) VALUES (:username, :email, :password, :role, :rfid)";
    $stmt = $conn->prepare($query);
    
    $stmt->bindParam(":username", $username);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":password", $hashed_password);
    $stmt->bindParam(":role", $role);
    $stmt->bindParam(":rfid", $rfid);

    if ($stmt->execute()) {
        $user_id = $conn->lastInsertId();
        
        // Insert into admins table in rfid_system database
        $adminQuery = "INSERT INTO admins (username, rfid, password, image) VALUES (:username, :rfid, :password, :image)";
        $adminStmt = $rfidConn->prepare($adminQuery);
        $adminStmt->bindParam(":username", $username);
        $adminStmt->bindParam(":rfid", $rfid);
        $adminStmt->bindParam(":password", $hashed_password);
        $default_image = 'assets/default-profile.png';
        $adminStmt->bindParam(":image", $default_image);
        $adminStmt->execute();
        
        // Mark the used RFID as used
        $updateRfidQuery = "UPDATE rfid_admin_scans SET is_used = 1, admin_username = :username WHERE id = :id";
        $updateRfidStmt = $rfidConn->prepare($updateRfidQuery);
        $updateRfidStmt->bindParam(":username", $username);
        $updateRfidStmt->bindParam(":id", $rfid_scan_id);
        $updateRfidStmt->execute();
        
        // Clean up old RFID entries (keep the latest one - the one we just used)
        $cleanupQuery = "DELETE FROM rfid_admin_scans 
                        WHERE is_used = 1 
                        AND id != :keep_id 
                        AND scanned_at < (
                            SELECT scanned_at FROM (
                                SELECT scanned_at FROM rfid_admin_scans 
                                WHERE id = :keep_id_sub
                            ) as temp
                        )";
        $cleanupStmt = $rfidConn->prepare($cleanupQuery);
        $cleanupStmt->bindParam(":keep_id", $rfid_scan_id);
        $cleanupStmt->bindParam(":keep_id_sub", $rfid_scan_id);
        $cleanupStmt->execute();
        
        // Commit both transactions
        $conn->commit();
        $rfidConn->commit();
        
        $user_data = array(
            "id" => $user_id,
            "username" => $username,
            "email" => $email,
            "role" => $role,
            "rfid" => $rfid
        );

        sendResponse(true, "Registration successful", $user_data);
    } else {
        $conn->rollBack();
        $rfidConn->rollBack();
        sendResponse(false, "Registration failed");
    }

} catch(PDOException $exception) {
    $conn->rollBack();
    $rfidConn->rollBack();
    error_log("Registration error: " . $exception->getMessage());
    sendResponse(false, "Registration failed: " . $exception->getMessage());
}
?>