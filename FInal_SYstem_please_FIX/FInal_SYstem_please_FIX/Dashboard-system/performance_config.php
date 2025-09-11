<?php
// performance_config.php - Simplified performance optimization configuration

// Enable output compression
if (!ob_get_level()) {
    ob_start('ob_gzhandler');
}

// Set memory limit for better performance
ini_set('memory_limit', '128M');

// Simple file-based caching system
class SimpleCache {
    private static $cacheDir = 'cache/';
    private static $defaultTTL = 300; // 5 minutes
    
    public static function init() {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    public static function get($key) {
        self::init();
        $file = self::$cacheDir . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return false;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if ($data['expires'] < time()) {
            unlink($file);
            return false;
        }
        
        return $data['value'];
    }
    
    public static function set($key, $value, $ttl = null) {
        self::init();
        $file = self::$cacheDir . md5($key) . '.cache';
        $ttl = $ttl ?: self::$defaultTTL;
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        file_put_contents($file, serialize($data));
    }
    
    public static function delete($key) {
        self::init();
        $file = self::$cacheDir . md5($key) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    public static function clear() {
        self::init();
        $files = glob(self::$cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}

// Simple database connection management
class DatabasePool {
    private static $instance = null;
    private $connections = [];
    private $maxConnections = 3; // Reduced from 5 for simplicity
    private $currentConnections = 0;
    
    private function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        // Reuse existing connection if available
        if (!empty($this->connections)) {
            return array_pop($this->connections);
        }
        
        // Create new connection if under limit
        if ($this->currentConnections < $this->maxConnections) {
            require_once 'config.php';
            $conn = getDatabaseConnection();
            $this->currentConnections++;
            return $conn;
        }
        
        // If limit reached, return a new connection anyway (simplified)
        require_once 'config.php';
        return getDatabaseConnection();
    }
    
    public function releaseConnection($conn) {
        if ($conn && !$conn->connect_error && count($this->connections) < $this->maxConnections) {
            $this->connections[] = $conn;
        }
    }
}

// Simple response optimization
class ResponseOptimizer {
    public static function setHeaders() {
        // Set basic caching headers
        header('Cache-Control: public, max-age=300'); // 5 minutes
        header('ETag: ' . md5(serialize($_GET)));
        
        // Check if client has cached version
        $etag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($etag === md5(serialize($_GET))) {
            http_response_code(304);
            exit;
        }
    }
    
    public static function sendJSON($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}

// Initialize cache
SimpleCache::init();
?>