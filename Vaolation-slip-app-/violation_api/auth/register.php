<?php
require_once '../config/database.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, "Only POST method is allowed");
}

$database = new Database();
$conn = $database->getViolationConnection();

if (!$conn) {
    sendResponse(false, "Database connection failed");
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendResponse(false, "Invalid JSON input");
}

// Extract and validate input data
$role = isset($input['role']) ? trim($input['role']) : '';
$name = isset($input['username']) ? trim($input['username']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';
$rfid = isset($input['rfid']) ? trim($input['rfid']) : null;

// Validate required fields
if (empty($role) || empty($name) || empty($email) || empty($password)) {
    sendResponse(false, "Role, name, email, and password are required");
}

// Validate role
if (!in_array($role, ['Guidance Admin', 'Guard'])) {
    sendResponse(false, "Invalid role. Must be 'Guidance Admin' or 'Guard'");
}

// Validate role-specific requirements
if ($role === 'Guard' && !empty($rfid)) {
    sendResponse(false, "RFID is not allowed for Guard role");
}

if ($role === 'Guidance Admin' && empty($rfid)) {
    sendResponse(false, "RFID is required for Guidance Admin role");
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, "Invalid email format");
}

try {
    // Check if email already exists
    $checkEmail = "SELECT rfid FROM admins WHERE email = ?";
    $stmt = $conn->prepare($checkEmail);
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        sendResponse(false, "Email already registered");
    }
    
    // For Guidance Admin with RFID, verify RFID exists in scans and is not used
    if ($role === 'Guidance Admin' && !empty($rfid)) {
        $checkRfid = "SELECT id FROM rfid_admin_scans WHERE rfid_number = ? AND is_registered = 0";
        $stmt = $conn->prepare($checkRfid);
        $stmt->execute([$rfid]);
        
        if ($stmt->rowCount() === 0) {
            sendResponse(false, "RFID not found or already used. Please scan a new RFID.");
        }
        
        // Check if RFID is already registered to another admin
        $checkRfidUsed = "SELECT rfid FROM admins WHERE rfid = ?";
        $stmt = $conn->prepare($checkRfidUsed);
        $stmt->execute([$rfid]);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(false, "RFID already registered to another admin");
        }
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Insert new admin
    $insertAdmin = "INSERT INTO admins (role, name, email, password, rfid) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertAdmin);
    $stmt->execute([$role, $name, $email, $hashedPassword, $rfid]);
    
    $adminId = $conn->lastInsertId();
    
    // If RFID was provided, mark it as registered in rfid_admin_scans
    if (!empty($rfid)) {
        $updateRfidScan = "UPDATE rfid_admin_scans SET is_registered = 1, admin_username = ?, admin_role = ? WHERE rfid_number = ?";
        $stmt = $conn->prepare($updateRfidScan);
        $stmt->execute([$name, $role, $rfid]);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response with user data
    $userData = [
        'id' => $adminId,
        'role' => $role,
        'username' => $name,
        'email' => $email,
        'rfid' => $rfid
    ];
    
    sendResponse(true, "Registration successful", $userData);
    
} catch(PDOException $exception) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Registration error: " . $exception->getMessage());
    sendResponse(false, "Registration failed. Please try again.");
}
?>