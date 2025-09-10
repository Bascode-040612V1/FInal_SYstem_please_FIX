<?php
// config.php - Unified database configuration and connection management
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'rfid_system');
define('DB_VIOLATION', 'student_violation_db');

// Create database connection using mysqli
function getDatabaseConnection($database = DB_NAME) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, $database);
    
    // Check connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Database connection failed. Please try again later.");
    }
    
    // Set charset to prevent character encoding issues
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

// Create global connection for backward compatibility
$conn = getDatabaseConnection();

// Violation database connection
function getViolationDatabaseConnection() {
    return getDatabaseConnection(DB_VIOLATION);
}

// Security configurations
ini_set('display_errors', 0); // Don't display errors in production
ini_set('log_errors', 1); // Log errors for debugging
error_reporting(E_ALL); // Report all errors to log

// Session security settings
if (session_status() == PHP_SESSION_NONE) {
    // Prevent session fixation attacks
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    // Set session timeout (30 minutes)
    ini_set('session.gc_maxlifetime', 1800);
    
    // Start session with secure settings
    session_start();
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Password utility functions
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Input sanitization functions
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateRFID($rfid) {
    return preg_match('/^[0-9]{10}$/', $rfid);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Database utility functions
function executeQuery($conn, $query, $params = [], $types = "") {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $result = $stmt->execute();
    if (!$result) {
        error_log("Execute failed: " . $stmt->error);
        return false;
    }
    
    return $stmt;
}

function fetchSingleResult($conn, $query, $params = [], $types = "") {
    $stmt = executeQuery($conn, $query, $params, $types);
    if (!$stmt) return false;
    
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function fetchAllResults($conn, $query, $params = [], $types = "") {
    $stmt = executeQuery($conn, $query, $params, $types);
    if (!$stmt) return false;
    
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

// Admin authentication functions
function isAdminAuthenticated() {
    return isset($_SESSION['is_admin_authenticated']) && $_SESSION['is_admin_authenticated'] === true;
}

function requireAdminAuth() {
    if (!isAdminAuthenticated()) {
        header("Location: admin_auth.php");
        exit();
    }
}

// Logging function
function logActivity($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user = $_SESSION['admin_username'] ?? 'guest';
    $log_entry = "[$timestamp] [$type] [$user@$ip] $message" . PHP_EOL;
    error_log($log_entry, 3, 'activity.log');
}
?>