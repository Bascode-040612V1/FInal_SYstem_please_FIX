<?php
// security_helpers.php - Security utility functions

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data, $type = 'string') {
    $data = trim($data);
    
    switch ($type) {
        case 'string':
            return filter_var($data, FILTER_SANITIZE_STRING);
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        default:
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validate RFID format
 */
function validateRFID($rfid) {
    $rfid = trim($rfid);
    // RFID should be exactly 10 digits
    return preg_match('/^\d{10}$/', $rfid);
}

/**
 * Validate student number format
 */
function validateStudentNumber($student_number) {
    $student_number = trim($student_number);
    // Student number should be 6 digits (adjust as needed)
    return preg_match('/^\d{6}$/', $student_number);
}

/**
 * Validate name format
 */
function validateName($name) {
    $name = trim($name);
    // Name should contain only letters, spaces, and common punctuation
    return preg_match('/^[a-zA-Z\s\.\-\']{2,100}$/', $name);
}

/**
 * Check if user is authenticated admin
 */
function isAdminAuthenticated() {
    return isset($_SESSION['is_admin_authenticated']) && $_SESSION['is_admin_authenticated'] === true;
}

/**
 * Redirect to login if not authenticated
 */
function requireAdminAuth() {
    if (!isAdminAuthenticated()) {
        header("Location: admin_auth.php");
        exit();
    }
}

/**
 * Rate limiting for login attempts
 */
function checkRateLimit($identifier, $max_attempts = 5, $window = 300) {
    $key = 'rate_limit_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $rate_data = $_SESSION[$key];
    
    // Reset if window has passed
    if (time() - $rate_data['first_attempt'] > $window) {
        $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
        return true;
    }
    
    // Check if limit exceeded
    if ($rate_data['count'] >= $max_attempts) {
        return false;
    }
    
    // Increment counter
    $_SESSION[$key]['count']++;
    return true;
}

/**
 * Log security events
 */
function logSecurityEvent($event, $details = '') {
    $log_entry = date('Y-m-d H:i:s') . " - " . $event . " - " . $details . " - IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
    error_log($log_entry, 3, 'security.log');
}
?>