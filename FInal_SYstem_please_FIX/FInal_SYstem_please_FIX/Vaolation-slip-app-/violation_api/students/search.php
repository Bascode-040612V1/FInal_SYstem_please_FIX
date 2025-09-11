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
    
    // First, try to find student in student_violation_db.students table
    if ($violationConn) {
        $query = "SELECT 
                    id,
                    student_id, 
                    student_name, 
                    year_level, 
                    course, 
                    section 
                  FROM students 
                  WHERE student_id = :student_id";
        $stmt = $violationConn->prepare($query);
        $stmt->bindParam(":student_id", $student_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            $student['source'] = 'student_violation_db';
            
            // Try to get profile image from RFID database if available
            if ($rfidConn) {
                $imageQuery = "SELECT image FROM students WHERE student_number = :student_id";
                $imageStmt = $rfidConn->prepare($imageQuery);
                $imageStmt->bindParam(":student_id", $student_id);
                $imageStmt->execute();
                
                if ($imageStmt->rowCount() > 0) {
                    $imageData = $imageStmt->fetch(PDO::FETCH_ASSOC);
                    $student['image'] = $imageData['image'];
                    
                    // Construct full image URL if image exists and is not default
                    if (!empty($student['image']) && $student['image'] !== 'assets/default-profile.png') {
                        $student['image_url'] = 'http://' . $_SERVER['HTTP_HOST'] . '/violation_api/' . $student['image'];
                    } else {
                        $student['image_url'] = null;
                    }
                } else {
                    $student['image'] = null;
                    $student['image_url'] = null;
                }
            } else {
                $student['image'] = null;
                $student['image_url'] = null;
            }
        }
    }
    
    // If not found in students table, try searching in violations table as fallback
    if (!$student && $violationConn) {
        try {
            $query = "SELECT DISTINCT student_id, student_name, year_level, course, section 
                     FROM violations 
                     WHERE student_id = :student_id 
                     ORDER BY recorded_at DESC 
                     LIMIT 1";
            $stmt = $violationConn->prepare($query);
            $stmt->bindParam(":student_id", $student_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                $student['id'] = 0; // No ID available from violations table
                $student['image'] = null;
                $student['image_url'] = null;
                $student['source'] = 'violations_table_fallback';
            }
        } catch(PDOException $e) {
            error_log("Fallback student search failed: " . $e->getMessage());
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