<?php
/**
 * Secure Database Configuration
 * Uses environment variables for database credentials
 */

class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    
    private $connection;
    
    public function __construct() {
        // Load configuration from environment variables or .env file
        $this->loadEnvironmentConfig();
        
        // Enforce HTTPS if configured
        $this->enforceHTTPS();
    }
    
    private function loadEnvironmentConfig() {
        // Try to load .env file if it exists
        $env_file = __DIR__ . '/.env';
        if (file_exists($env_file)) {
            $this->loadEnvFile($env_file);
        }
        
        // Get configuration from environment variables (with fallbacks)
        $this->host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
        $this->port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
        $this->db_name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'aics_bicutan_system_db';
        $this->username = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
    }
    
    private function loadEnvFile($file_path) {
        if (!file_exists($file_path)) {
            return;
        }
        
        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue; // Skip comments
            }
            
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
        }
    }
    
    private function enforceHTTPS() {
        $force_https = $_ENV['FORCE_HTTPS'] ?? getenv('FORCE_HTTPS') ?: 'false';
        
        if (strtolower($force_https) === 'true') {
            if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
                $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                header("Location: $redirect_url", true, 301);
                exit();
            }
        }
    }
    
    public function getConnection() {
        try {
            if ($this->connection == null) {
                $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name;
                $this->connection = new PDO($dsn, $this->username, $this->password);
                $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            }
            return $this->connection;
        } catch(PDOException $exception) {
            $this->logError("Database Connection Error: " . $exception->getMessage());
            return null;
        }
    }
    
    // For backward compatibility, alias getViolationConnection to getConnection
    public function getViolationConnection() {
        return $this->getConnection();
    }
    
    // For backward compatibility, alias getRfidConnection to getConnection
    public function getRfidConnection() {
        return $this->getConnection();
    }
    
    private function logError($message) {
        $log_errors = $_ENV['LOG_ERRORS'] ?? getenv('LOG_ERRORS') ?: 'true';
        if (strtolower($log_errors) === 'true') {
            error_log($message);
        }
    }
}

// Enhanced response function with security headers and compression
function sendResponse($success, $message, $data = null) {
    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Enable gzip compression if available
    if (extension_loaded('zlib') && !ob_get_level()) {
        ob_start('ob_gzhandler');
    }
    
    header('Content-Type: application/json; charset=utf-8');
    
    $response = array(
        'success' => $success,
        'message' => $message
    );
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    // Add timestamp
    $response['timestamp'] = date('Y-m-d H:i:s');
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    exit();
}

// Security validation function
function validateInput($data, $type = 'string', $max_length = 255) {
    if (empty($data)) {
        return false;
    }
    
    // Sanitize
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    
    // Length check
    if (strlen($data) > $max_length) {
        return false;
    }
    
    // Type validation
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL);
        case 'numeric':
            return is_numeric($data);
        case 'alphanumeric':
            return ctype_alnum($data);
        default:
            return $data;
    }
}
?>