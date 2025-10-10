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
    // Search student in database - FIXED to match DBMODIFY.sql schema (lastname, firstname, middlename)
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
    $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get offense counts from student_offense_counts table
        $offenseQuery = "SELECT 
            vt.violation_name as violation_type, 
            soc.offense_count 
        FROM student_offense_counts soc 
        JOIN violation_types vt ON soc.violation_type_id = vt.id 
        WHERE soc.student_number = ?";
        $offenseStmt = $conn->prepare($offenseQuery);
        $offenseStmt->execute([$student_id]);
        
        $offenseCounts = [];
        while ($row = $offenseStmt->fetch(PDO::FETCH_ASSOC)) {
            $offenseCounts[$row['violation_type']] = (int)$row['offense_count'];
        }
        
        $student['offense_counts'] = $offenseCounts;
        
        sendResponse(true, "Student found", $student);
    } else {
        sendResponse(false, "Student not found");
    }
    
} catch(PDOException $exception) {
    error_log("Student search error: " . $exception->getMessage());
    sendResponse(false, "Search failed");
}
?>