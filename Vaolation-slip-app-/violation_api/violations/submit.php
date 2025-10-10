<?php
require_once '../config/database.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, "Only POST method is allowed");
}

// Get and validate input
$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->student_id) || !isset($data->violations) || empty($data->violations)) {
    sendResponse(false, "Student ID and violations are required");
}

// Validate input
$student_id = validateInput($data->student_id, 'numeric', 20);
$recorded_by = validateInput($data->recorded_by ?? 'System', 'string', 100);

if (!$student_id) {
    sendResponse(false, "Invalid student ID");
}

$database = new Database();
$conn = $database->getViolationConnection();

if (!$conn) {
    sendResponse(false, "Database connection failed");
}

try {
    $conn->beginTransaction();
    
    // Get student info first - FIXED to match DBMODIFY.sql schema (lastname, firstname, middlename)
    $studentQuery = "SELECT CONCAT_WS(' ', firstname, IFNULL(CONCAT(middlename, ' '), ''), lastname) as student_name, yearlevel as year_level, course, section FROM students WHERE student_number = ?";
    $studentStmt = $conn->prepare($studentQuery);
    $studentStmt->execute([$student_id]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        $conn->rollback();
        sendResponse(false, "Student not found");
    }
    
    // Determine recorder details based on recorded_by
    $recorded_by_role = 'guard'; // Default
    $recorded_by_id = 1; // Default guard ID
    
    if (!empty($recorded_by)) {
        // Try to find admin first
        $adminQuery = "SELECT rfid FROM admins WHERE name = ? OR email = ?";
        $adminStmt = $conn->prepare($adminQuery);
        $adminStmt->execute([$recorded_by, $recorded_by]);
        
        if ($adminStmt->rowCount() > 0) {
            $recorded_by_role = 'admin';
            $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
            $recorded_by_id = $admin['rfid'];
        }
    }
    
    // Process each violation - your triggers will handle offense counting
    $violation_ids = [];
    $highest_offense = 1;
    $penalty = "Warning";
    
    foreach ($data->violations as $violation_name) {
        // Get violation type ID
        $typeQuery = "SELECT id FROM violation_types WHERE violation_name = ?";
        $typeStmt = $conn->prepare($typeQuery);
        $typeStmt->execute([$violation_name]);
        
        if ($typeStmt->rowCount() === 0) {
            continue; // Skip unknown violation types
        }
        
        $violation_type = $typeStmt->fetch(PDO::FETCH_ASSOC);
        $violation_type_id = $violation_type['id'];
        
        // Insert violation - triggers will handle offense_count and penalty
        $violationQuery = "INSERT INTO violations (student_number, violation_type_id, recorded_by_role, recorded_by_id) VALUES (?, ?, ?, ?)";
        $violationStmt = $conn->prepare($violationQuery);
        $violationStmt->execute([$student_id, $violation_type_id, $recorded_by_role, $recorded_by_id]);
        
        $violation_id = $conn->lastInsertId();
        $violation_ids[] = $violation_id;
        
        // Get the calculated offense count and penalty from the inserted record
        $resultQuery = "SELECT offense_count, penalty FROM violations WHERE violation_id = ?";
        $resultStmt = $conn->prepare($resultQuery);
        $resultStmt->execute([$violation_id]);
        $result = $resultStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['offense_count'] > $highest_offense) {
            $highest_offense = $result['offense_count'];
            $penalty = $result['penalty'] ?? "Warning";
        }
    }
    
    $conn->commit();
    
    $response = [
        'violation_id' => $violation_ids[0] ?? 0,
        'offense_count' => $highest_offense,
        'penalty' => $penalty,
        'message' => "Violation(s) submitted successfully for " . $student['student_name']
    ];
    
    sendResponse(true, "Violations submitted successfully", $response);
    
} catch(PDOException $exception) {
    $conn->rollback();
    error_log("Violation submission error: " . $exception->getMessage());
    sendResponse(false, "Failed to submit violations");
}
?>