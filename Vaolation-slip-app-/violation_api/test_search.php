<?php
// Save this as: violation_api/test_search.php
require_once 'config/database.php';

header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing student search...\n\n";

$database = new Database();
$conn = $database->getViolationConnection();

if (!$conn) {
    echo "Database connection failed!\n";
    exit;
}

// Get a real student ID from the database
$stmt = $conn->query("SELECT student_number FROM students ORDER BY student_number LIMIT 1");
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "No students found in database!\n";
    exit;
}

$test_id = $student['student_number'];
echo "Testing with student ID: " . $test_id . "\n";
echo "Type: " . gettype($test_id) . "\n\n";

// Test the validation
$validated = validateInput($test_id, 'numeric', 20);
echo "Validation result: '" . $validated . "'\n";
echo "Validation success: " . ($validated !== false ? "YES" : "NO") . "\n\n";

if ($validated !== false) {
    // Test the search query
    $query = "SELECT 
        student_number as student_id, 
        CONCAT_WS(' ', firstname, IFNULL(CONCAT(middlename, ' '), ''), lastname) as student_name, 
        yearlevel as year_level, 
        course, 
        section, 
        image 
    FROM students 
    WHERE student_number = :student_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":student_id", $validated, PDO::PARAM_INT);
    $stmt->execute();
    
    echo "Query executed successfully\n";
    echo "Rows found: " . $stmt->rowCount() . "\n\n";
    
    if ($stmt->rowCount() > 0) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "SUCCESS! Found student:\n";
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "PROBLEM: Query executed but no results found\n";
        
        // Try simple query
        echo "Trying simple query...\n";
        $simple = $conn->prepare("SELECT * FROM students WHERE student_number = ?");
        $simple->execute([$test_id]);
        echo "Simple query rows: " . $simple->rowCount() . "\n";
    }
} else {
    echo "PROBLEM: Validation failed\n";
    echo "Raw input: '" . $test_id . "'\n";
    echo "is_numeric() result: " . (is_numeric($test_id) ? "true" : "false") . "\n";
}
?>