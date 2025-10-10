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
    // Get student violations with violation type names - FIXED to match DBMODIFY.sql schema (lastname, firstname, middlename)
    $query = "SELECT 
        v.violation_id as id,
        v.student_number as student_id,
        CONCAT_WS(' ', s.firstname, IFNULL(CONCAT(s.middlename, ' '), ''), s.lastname) as student_name,
        s.yearlevel as year_level,
        s.course,
        s.section,
        v.offense_count,
        v.penalty,
        CASE 
            WHEN v.recorded_by_role = 'admin' THEN CONCAT('Admin: ', a.name)
            WHEN v.recorded_by_role = 'guard' THEN CONCAT('Guard: ', g.name)
            ELSE 'Unknown'
        END as recorded_by,
        v.created_at as recorded_at,
        v.acknowledged,
        vt.violation_name as violation_type,
        vt.category
    FROM violations v
    JOIN students s ON v.student_number = s.student_number
    JOIN violation_types vt ON v.violation_type_id = vt.id
    LEFT JOIN admins a ON v.recorded_by_role = 'admin' AND v.recorded_by_id = a.rfid
    LEFT JOIN guards g ON v.recorded_by_role = 'guard' AND v.recorded_by_id = g.id
    WHERE v.student_number = ? 
    ORDER BY v.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute([$student_id]);
    
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(true, "Student violations retrieved successfully", $violations);
    
} catch(PDOException $exception) {
    error_log("Student violations error: " . $exception->getMessage());
    sendResponse(false, "Failed to retrieve student violations");
}
?>