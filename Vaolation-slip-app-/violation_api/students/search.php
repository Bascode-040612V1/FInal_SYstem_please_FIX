<?php
require_once '../config/database.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, "Only GET method is allowed");
}

$student_id = validateInput($_GET['student_id'] ?? '', 'alphanumeric', 20);

if (!$student_id) {
    sendResponse(false, "Valid student ID is required");
}

$database = new Database();
$conn = $database->getRfidConnection();

if (!$conn) {
    sendResponse(false, "Database connection failed");
}

try {
    // Search student in RFID database using correct column names
    $query = "SELECT 
                student_number as student_id, 
                name as student_name, 
                id, 
                rfid, 
                image,
                'N/A' as year_level,
                'N/A' as course,
                'N/A' as section
              FROM students 
              WHERE student_number = :student_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":student_id", $student_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Try to get additional student info from violation database if available
        $violationConn = $database->getViolationConnection();
        if ($violationConn) {
            $studentInfoQuery = "SELECT year_level, course, section FROM students WHERE student_id = ?";
            $studentInfoStmt = $violationConn->prepare($studentInfoQuery);
            $studentInfoStmt->execute([$student_id]);
            
            if ($studentInfoStmt->rowCount() > 0) {
                $additionalInfo = $studentInfoStmt->fetch(PDO::FETCH_ASSOC);
                $student['year_level'] = $additionalInfo['year_level'];
                $student['course'] = $additionalInfo['course'];
                $student['section'] = $additionalInfo['section'];
            }
            
            // Get offense counts from violation database
            $offenseQuery = "SELECT violation_type, offense_count FROM student_violation_offense_counts WHERE student_id = ?";
            $offenseStmt = $violationConn->prepare($offenseQuery);
            $offenseStmt->execute([$student_id]);
            
            $offenseCounts = [];
            while ($row = $offenseStmt->fetch(PDO::FETCH_ASSOC)) {
                $offenseCounts[$row['violation_type']] = (int)$row['offense_count'];
            }
            
            $student['offense_counts'] = $offenseCounts;
        } else {
            // If violation database is not available, set empty offense counts
            $student['offense_counts'] = [];
        }
        
        sendResponse(true, "Student found", $student);
    } else {
        sendResponse(false, "Student not found");
    }
    
} catch(PDOException $exception) {
    error_log("Student search error: " . $exception->getMessage());
    sendResponse(false, "Search failed: " . $exception->getMessage());
}
?>