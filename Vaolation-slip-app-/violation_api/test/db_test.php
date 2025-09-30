<?php
require_once '../../violation_api/config/database.php';

// Test the new single database connection
$database = new Database();

try {
    // Test the new getConnection method
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "SUCCESS: Connected to database using new single connection method\n";
        
        // Test a simple query
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "SUCCESS: Students table has " . $result['count'] . " records\n";
        
        // Test the backward compatibility methods
        $conn1 = $database->getViolationConnection();
        $conn2 = $database->getRfidConnection();
        
        if ($conn1 === $conn && $conn2 === $conn) {
            echo "SUCCESS: Backward compatibility methods work correctly\n";
        } else {
            echo "WARNING: Backward compatibility methods don't return the same connection\n";
        }
        
    } else {
        echo "ERROR: Failed to connect to database\n";
    }
    
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>