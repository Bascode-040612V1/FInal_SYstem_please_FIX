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
$rfidConn = $database->getRfidConnection();
$violationConn = $database->getViolationConnection();

if (!$rfidConn && !$violationConn) {
    sendResponse(false, "Database connection failed");
}

try {
    $student = null;
    
    // First, try to find student in RFID database
    if ($rfidConn) {
        // Map the actual database fields to expected API fields
        $query = "SELECT 
                    student_number as student_id, 
                    name as student_name, 
                    '' as year_level, 
                    '' as course, 
                    '' as section, 
                    image 
                  FROM students 
                  WHERE student_number = :student_id";
        $stmt = $rfidConn->prepare($query);
        $stmt->bindParam(":student_id", $student_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            $student['source'] = 'rfid_system';
            
            // Construct full image URL if image exists and is not default
            if (!empty($student['image']) && $student['image'] !== 'assets/default-profile.png') {
                $student['image_url'] = '/violation_api/' . $student['image'];
            } else {
                $student['image_url'] = null;
            }
        }
    }
    
    // If not found in RFID database, try student_violation_db
    if (!$student && $violationConn) {
        // Check if there's a students table in violation database or get from violations table
        try {
            $query = "SELECT DISTINCT student_id, student_name, student_year as year_level, student_course as course, student_section as section 
                     FROM violations 
                     WHERE student_id = :student_id 
                     ORDER BY created_at DESC 
                     LIMIT 1";
            $stmt = $violationConn->prepare($query);
            $stmt->bindParam(":student_id", $student_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                $student['image'] = null; // No image in violation database
                $student['source'] = 'student_violation_db';
            }
        } catch(PDOException $e) {
            // If violations table doesn't have student info, try alternative approach
            error_log("Alternative student search failed: " . $e->getMessage());
        }
    }
    
    if ($student) {
        // Get offense counts from violation database
        if ($violationConn) {
            $offenseQuery = "SELECT violation_type, offense_count FROM student_violation_offense_counts WHERE student_id = ?";
            $offenseStmt = $violationConn->prepare($offenseQuery);
            $offenseStmt->execute([$student_id]);
            
            $offenseCounts = [];
            while ($row = $offenseStmt->fetch(PDO::FETCH_ASSOC)) {
                $offenseCounts[$row['violation_type']] = (int)$row['offense_count'];
            }
            
            $student['offense_counts'] = $offenseCounts;
        }
        
        sendResponse(true, "Student found", $student);
    } else {
        sendResponse(false, "Student not found in any database");
    }
    
} catch(PDOException $exception) {
    error_log("Student search error: " . $exception->getMessage());
    sendResponse(false, "Search failed: " . $exception->getMessage());
}
?>