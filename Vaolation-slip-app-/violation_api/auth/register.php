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

// RFID is only available for admin and teacher roles, not for guards
$rfid = null;
if (in_array($role, ['admin', 'teacher']) && isset($data->rfid)) {
    $rfid = validateInput($data->rfid, 'string', 50);
}

if (!$username || !$email || !$password) {
    sendResponse(false, "Invalid input format");
}

// Validate role-specific requirements
if ($role === 'guard' && isset($data->rfid) && !empty($data->rfid)) {
    sendResponse(false, "RFID is not allowed for guard role");
}

if (in_array($role, ['admin', 'teacher']) && isset($data->rfid) && !empty($data->rfid) && !$rfid) {
    sendResponse(false, "Invalid RFID format");
}

$database = new Database();
$conn = $database->getViolationConnection();

if (!$conn) {
    sendResponse(false, "Database connection failed");
}

try {
    // Check if user already exists in admins or guards tables
    $adminQuery = "SELECT rfid FROM admins WHERE email = :email OR name = :username";
    $adminStmt = $conn->prepare($adminQuery);
    $adminStmt->bindParam(":email", $email);
    $adminStmt->bindParam(":username", $username);
    $adminStmt->execute();
    
    $guardQuery = "SELECT id FROM guards WHERE email = :email OR name = :username";
    $guardStmt = $conn->prepare($guardQuery);
    $guardStmt->bindParam(":email", $email);
    $guardStmt->bindParam(":username", $username);
    $guardStmt->execute();

    if ($adminStmt->rowCount() > 0 || $guardStmt->rowCount() > 0) {
        sendResponse(false, "User with this email or username already exists");
    }

    // Hash password securely
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user based on role
    if (in_array($role, ['admin', 'teacher'])) {
        // Insert into admins table (RFID available for admin/teacher)
        if ($rfid) {
            // If RFID is provided, store it in the admins table
            $query = "INSERT INTO admins (name, email, password, role, image) VALUES (:username, :email, :password, :role, :rfid)";
            $stmt = $conn->prepare($query);
            
            $stmt->bindParam(":username", $username);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":password", $hashed_password);
            $admin_role = ($role === 'teacher') ? 'Teacher' : 'Admin';
            $stmt->bindParam(":role", $admin_role);
            $stmt->bindParam(":rfid", $rfid); // Store RFID in image field for now
        } else {
            $query = "INSERT INTO admins (name, email, password, role) VALUES (:username, :email, :password, :role)";
            $stmt = $conn->prepare($query);
            
            $stmt->bindParam(":username", $username);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":password", $hashed_password);
            $admin_role = ($role === 'teacher') ? 'Teacher' : 'Admin';
            $stmt->bindParam(":role", $admin_role);
        }
        
        if ($stmt->execute()) {
            $user_id = $conn->lastInsertId();
        } else {
            sendResponse(false, "Registration failed");
        }
    } else {
        // Insert into guards table (NO RFID for guards)
        $query = "INSERT INTO guards (name, email, password) VALUES (:username, :email, :password)";
        $stmt = $conn->prepare($query);
        
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":password", $hashed_password);
        
        if ($stmt->execute()) {
            $user_id = $conn->lastInsertId();
            // Force RFID to null for guards
            $rfid = null;
        } else {
            sendResponse(false, "Registration failed");
        }
    }
        
    $user_data = array(
        "id" => $user_id,
        "username" => $username,
        "email" => $email,
        "role" => $role,
        "rfid" => $rfid
    );

    sendResponse(true, "Registration successful", $user_data);

} catch(PDOException $exception) {
    error_log("Registration error: " . $exception->getMessage());
    sendResponse(false, "Registration failed");
}
?>