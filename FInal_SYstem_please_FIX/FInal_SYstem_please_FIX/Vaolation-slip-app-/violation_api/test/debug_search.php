<?php
require_once '../config/database.php';

// Test the validation function with different student IDs
$testStudentIds = ['220342', '123456', '2021-0001', '2021-0002'];

echo "<!DOCTYPE html><html><head><title>Debug Student Search</title></head><body>";
echo "<h1>Debug Student Search - Validation Test</h1>";

foreach ($testStudentIds as $studentId) {
    echo "<h3>Testing Student ID: $studentId</h3>";
    
    // Test current validation
    $validated = validateInput($studentId, 'alphanumeric', 20);
    echo "<p>Current validation result: " . ($validated ? "PASSED ($validated)" : "FAILED") . "</p>";
    
    // Test individual validation methods
    echo "<p>ctype_alnum(): " . (ctype_alnum($studentId) ? "PASSED" : "FAILED") . "</p>";
    echo "<p>is_numeric(): " . (is_numeric($studentId) ? "PASSED" : "FAILED") . "</p>";
    echo "<p>Length: " . strlen($studentId) . "</p>";
    
    // Test if student exists in database
    $database = new Database();
    $violationConn = $database->getViolationConnection();
    
    if ($violationConn) {
        $query = "SELECT student_id, student_name FROM students WHERE student_id = :student_id";
        $stmt = $violationConn->prepare($query);
        $stmt->bindParam(":student_id", $studentId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p style='color: green;'>✅ Found in database: {$student['student_name']}</p>";
        } else {
            echo "<p style='color: red;'>❌ Not found in database</p>";
        }
    }
    echo "<hr>";
}

echo "</body></html>";
?>