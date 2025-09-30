<?php
require_once '../../violation_api/config/database.php';

// For CLI testing, simulate GET request
$_SERVER['REQUEST_METHOD'] = 'GET';

$database = new Database();

try {
    // Test database connection (both violation and rfid use same db now)
    $conn = $database->getViolationConnection();
    
    $results = [];
    
    if ($conn) {
        $results['database'] = 'Connected successfully to aics_bicutan_system_db';
        
        // Test queries on key tables
        $tables_to_test = ['admins', 'guards', 'students', 'violation_types', 'violations'];
        
        foreach ($tables_to_test as $table) {
            try {
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table");
                $stmt->execute();
                $count = $stmt->fetch(PDO::FETCH_ASSOC);
                $results["{$table}_count"] = $count['count'];
            } catch(PDOException $e) {
                $results["{$table}_count"] = "Table not found or error: " . $e->getMessage();
            }
        }
    } else {
        $results['database'] = 'Connection failed';
    }
    
    $results['server_time'] = date('Y-m-d H:i:s');
    $results['php_version'] = phpversion();
    
    echo "Test Results:\n";
    foreach ($results as $key => $value) {
        echo "$key: $value\n";
    }
    
} catch(Exception $e) {
    error_log("Connection test error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
?>