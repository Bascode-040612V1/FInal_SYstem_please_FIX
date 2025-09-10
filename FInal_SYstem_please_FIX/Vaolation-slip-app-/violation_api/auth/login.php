<?php
require_once '../config/database.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, "Only POST method is allowed");
}

// Get and validate input
$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->email) || !isset($data->password)) {
    sendResponse(false, "Email/Username and password are required");
}

// Validate input format - allow both email and username
$emailOrUsername = validateInput($data->email, 'string', 100);
$password = validateInput($data->password, 'string', 128);

if (!$emailOrUsername || !$password) {
    sendResponse(false, "Invalid email/username or password format");
}

$database = new Database();
$conn = $database->getViolationConnection();

if (!$conn) {
    sendResponse(false, "Database connection failed");
}

try {
    // Check if input is email or username/full name
    $isEmail = filter_var($emailOrUsername, FILTER_VALIDATE_EMAIL);
    
    if ($isEmail) {
        // Login with email
        $query = "SELECT * FROM users WHERE email = :email";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":email", $emailOrUsername);
    } else {
        // Login with username or full name
        $query = "SELECT * FROM users WHERE username = :username OR CONCAT(TRIM(username), ' ') LIKE :fullname OR username LIKE :partial";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":username", $emailOrUsername);
        $fullnamePattern = "%" . $emailOrUsername . "%";
        $stmt->bindParam(":fullname", $fullnamePattern);
        $stmt->bindParam(":partial", $fullnamePattern);
    }
    
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Secure password verification with backward compatibility
        $password_valid = false;
        
        // Check if password is already hashed (starts with $2y$)
        if (password_get_info($user['password'])['algo'] !== null) {
            // Password is hashed, use password_verify
            $password_valid = password_verify($password, $user['password']);
        } else {
            // Legacy plaintext password, verify and upgrade
            if ($user['password'] === $password) {
                $password_valid = true;
                
                // Upgrade to hashed password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = :password WHERE id = :id";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bindParam(":password", $hashed_password);
                $update_stmt->bindParam(":id", $user['id']);
                $update_stmt->execute();
            }
        }
        
        if ($password_valid) {
            unset($user['password']); // Don't send password back
            
            // Get admin image from RFID database if user exists
            $rfidConn = $database->getRfidConnection();
            if ($rfidConn && isset($user['username'])) {
                try {
                    $adminQuery = "SELECT image FROM admins WHERE username = :username";
                    $adminStmt = $rfidConn->prepare($adminQuery);
                    $adminStmt->bindParam(':username', $user['username']);
                    $adminStmt->execute();
                    
                    if ($adminStmt->rowCount() > 0) {
                        $adminData = $adminStmt->fetch(PDO::FETCH_ASSOC);
                        if (!empty($adminData['image']) && $adminData['image'] !== 'assets/default-profile.png') {
                            $user['image_url'] = '/violation_api/' . $adminData['image'];
                        } else {
                            $user['image_url'] = null;
                        }
                    }
                } catch(PDOException $e) {
                    // Log error but don't fail login
                    error_log("Error fetching admin image: " . $e->getMessage());
                    $user['image_url'] = null;
                }
            }
            
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