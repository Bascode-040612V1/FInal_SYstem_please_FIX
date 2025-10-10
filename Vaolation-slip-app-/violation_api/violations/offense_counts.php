<?php
require_once '../config/database.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, "Only GET method is allowed");
}

$student_id = validateInput($_GET['student_id'] ?? '', 'numeric', 20);

if (!$student_id) {
    sendResponse(false, "Valid student ID is required");
}

$database = new Database();
$conn = $database->getViolationConnection();

if (!$conn) {
    sendResponse(false, "Database connection failed");
}

try {
    // Get offense counts for student with violation type names - FIXED column name
    $query = "SELECT 
        vt.violation_name as violation_type, 
        soc.offense_count 
    FROM student_offense_counts soc 
    JOIN violation_types vt ON soc.violation_type_id = vt.id 
    WHERE soc.student_number = ? 
    ORDER BY vt.violation_name";
    $stmt = $conn->prepare($query);
    $stmt->execute([$student_id]);
    
    $offenseCounts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $offenseCounts[$row['violation_type']] = (int)$row['offense_count'];
    }
    
    sendResponse(true, "Offense counts retrieved successfully", $offenseCounts);
    
} catch(PDOException $exception) {
    error_log("Offense counts error: " . $exception->getMessage());
    sendResponse(false, "Failed to retrieve offense counts");
}
?>