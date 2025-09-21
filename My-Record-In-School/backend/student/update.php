<?php
// Define constant to indicate this file is being included from API
define('INCLUDED_FROM_API', true);

// Disable error reporting and clean output buffer
ini_set('display_errors', 0);
error_reporting(0);
ob_clean();

include_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(array("success" => false, "message" => "Method not allowed"));
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || 
        !isset($input['student_id']) || 
        !isset($input['year']) || 
        !isset($input['course']) || 
        !isset($input['section'])) {
        echo json_encode(array(
            "success" => false,
            "message" => "Student ID, year, course, and section are required"
        ));
        exit();
    }
    
    $student_id = trim($input['student_id']);
    $year = trim($input['year']);
    $course = trim($input['course']);
    $section = trim($input['section']);
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Update in the combined database
        $update_query = "UPDATE students SET yearlevel = :year, course = :course, section = :section, updated_at = NOW() 
                        WHERE student_number = :student_number";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bindParam(':year', $year);
        $stmt->bindParam(':course', $course);
        $stmt->bindParam(':section', $section);
        $stmt->bindParam(':student_number', $student_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Commit transaction
            $conn->commit();
            
            // Get updated student data
            $get_student_query = "SELECT student_number, surname, firstname, lastname, yearlevel, course, section, created_at, updated_at 
                                 FROM students WHERE student_number = :student_number LIMIT 1";
            $get_stmt = $conn->prepare($get_student_query);
            $get_stmt->bindParam(':student_number', $student_id);
            $get_stmt->execute();
            $student = $get_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Construct full name
            $full_name = trim($student['surname'] . ', ' . $student['firstname'] . ' ' . ($student['lastname'] ?: ''));
            
            echo json_encode(array(
                "success" => true,
                "message" => "Student information updated successfully",
                "student" => array(
                    "id" => intval($student['student_number']),
                    "student_id" => strval($student['student_number']),
                    "name" => $full_name,
                    "year" => $student['yearlevel'] ?: '',
                    "course" => $student['course'] ?: '',
                    "section" => $student['section'] ?: '',
                    "created_at" => $student['created_at'],
                    "updated_at" => $student['updated_at']
                )
            ));
        } else {
            $conn->rollback();
            echo json_encode(array(
                "success" => false,
                "message" => "Student not found or no changes made"
            ));
        }
        
    } catch(Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch(Exception $e) {
    echo json_encode(array(
        "success" => false,
        "message" => "Update error: " . $e->getMessage()
    ));
}
?>