<?php
require_once '../config/database.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, "Only POST method is allowed");
}

// Get and validate input
$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->student_ids) || !is_array($data->student_ids)) {
    sendResponse(false, "student_ids array is required");
}

// Limit batch size for performance
if (count($data->student_ids) > 20) {
    sendResponse(false, "Maximum 20 students can be searched at once");
}

// Validate all student IDs
$student_ids = [];
foreach ($data->student_ids as $id) {
    $validated_id = validateInput($id, 'alphanumeric', 20);
    if ($validated_id) {
        $student_ids[] = $validated_id;
    }
}

if (empty($student_ids)) {
    sendResponse(false, "No valid student IDs provided");
}

$database = new Database();
$conn = $database->getRfidConnection();

if (!$conn) {
    sendResponse(false, "Database connection failed");
}

try {
    // Prepare placeholders for IN clause
    $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
    
    // Batch query for multiple students from RFID database
    $query = "SELECT 
                student_number as student_id, 
                name as student_name, 
                id, 
                rfid, 
                image
              FROM students 
              WHERE student_number IN ($placeholders)
              ORDER BY student_number";
    $stmt = $conn->prepare($query);
    $stmt->execute($student_ids);
    
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $studentsById = [];
    
    // Index students by ID for easy lookup
    foreach ($students as $student) {
        $student['year_level'] = 'N/A';
        $student['course'] = 'N/A';
        $student['section'] = 'N/A';
        $student['offense_counts'] = [];
        $studentsById[$student['student_id']] = $student;
    }
    
    // Get additional info from violation database if available
    $violationConn = $database->getViolationConnection();
    if ($violationConn && !empty($students)) {
        // Batch query for additional student info
        $studentInfoQuery = "SELECT student_id, year_level, course, section 
                           FROM students 
                           WHERE student_id IN ($placeholders)";
        $studentInfoStmt = $violationConn->prepare($studentInfoQuery);
        $studentInfoStmt->execute($student_ids);
        
        while ($row = $studentInfoStmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($studentsById[$row['student_id']])) {
                $studentsById[$row['student_id']]['year_level'] = $row['year_level'];
                $studentsById[$row['student_id']]['course'] = $row['course'];
                $studentsById[$row['student_id']]['section'] = $row['section'];
            }
        }
        
        // Batch query for offense counts
        $offenseQuery = "SELECT student_id, violation_type, offense_count 
                       FROM student_violation_offense_counts 
                       WHERE student_id IN ($placeholders)
                       ORDER BY student_id, violation_type";
        $offenseStmt = $violationConn->prepare($offenseQuery);
        $offenseStmt->execute($student_ids);
        
        while ($row = $offenseStmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($studentsById[$row['student_id']])) {
                $studentsById[$row['student_id']]['offense_counts'][$row['violation_type']] = (int)$row['offense_count'];
            }
        }
    }
    
    // Prepare response with statistics
    $response_data = [
        'students' => array_values($studentsById),
        'found_count' => count($studentsById),
        'requested_count' => count($student_ids),
        'not_found' => array_diff($student_ids, array_keys($studentsById)),
        'batch_processed_at' => date('Y-m-d H:i:s')
    ];
    
    sendResponse(true, "Batch student search completed", $response_data);
    
} catch(PDOException $exception) {
    error_log("Batch student search error: " . $exception->getMessage());
    sendResponse(false, "Batch search failed: " . $exception->getMessage());
}
?>