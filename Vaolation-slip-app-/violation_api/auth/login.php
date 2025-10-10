<?php
require_once '../config/database.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, "Only POST method is allowed");
}

// Get and validate input
$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->email) || !isset($data->password)) {
    sendResponse(false, "Email/Full name and password are required");
}

// For login, we accept both email and full name, so don't validate as email strictly
$emailOrName = validateInput($data->email, 'string', 255);
$password = validateInput($data->password, 'string', 128);

if (!$emailOrName || !$password) {
    sendResponse(false, "Invalid email/name or password format");
}

$database = new Database();
$conn = $database->getViolationConnection();

if (!$conn) {
    sendResponse(false, "Database connection failed");
}

try {
    $user = null;
    $user_type = null;

    // First try to find user in admins table by email OR name
    $admin_query = "SELECT rfid as id, name as username, email, password, role, rfid FROM admins WHERE email = :identifier OR name = :identifier";
    $admin_stmt = $conn->prepare($admin_query);
    $admin_stmt->bindParam(":identifier", $emailOrName);
    $admin_stmt->execute();

    if ($admin_stmt->rowCount() > 0) {
        $user = $admin_stmt->fetch(PDO::FETCH_ASSOC);
        $user_type = 'admin';
    } else {
        // Try to find user in guards table by email OR name
        $guard_query = "SELECT id, name as username, email, password, 'Guard' as role, NULL as rfid FROM guards WHERE email = :identifier OR name = :identifier";
        $guard_stmt = $conn->prepare($guard_query);
        $guard_stmt->bindParam(":identifier", $emailOrName);
        $guard_stmt->execute();

        if ($guard_stmt->rowCount() > 0) {
            $user = $guard_stmt->fetch(PDO::FETCH_ASSOC);
            $user_type = 'guard';
        }
    }

    if ($user) {
        // Verify password
        $password_valid = false;
        
        if (!empty($user['password'])) {
            // Check if it's already hashed
            if (password_verify($password, $user['password'])) {
                $password_valid = true;
            }
        } else {
            // If no password set, allow any password and hash it
            if (!empty($password)) {
                $password_valid = true;
                
                // Hash and store the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                if ($user_type === 'admin') {
                    $update_query = "UPDATE admins SET password = :password WHERE rfid = :id";
                } else {
                    $update_query = "UPDATE guards SET password = :password WHERE id = :id";
                }
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bindParam(":password", $hashed_password);
                $update_stmt->bindParam(":id", $user['id']);
                $update_stmt->execute();
            }
        }
        
        if ($password_valid) {
            unset($user['password']); // Don't send password back
            sendResponse(true, "Login successful", $user);
        } else {
            sendResponse(false, "Invalid credentials");
        }
    } else {
        sendResponse(false, "User not found");
    }
    
} catch(PDOException $exception) {
    error_log("Login error: " . $exception->getMessage());
    sendResponse(false, "Login failed");
}
?>