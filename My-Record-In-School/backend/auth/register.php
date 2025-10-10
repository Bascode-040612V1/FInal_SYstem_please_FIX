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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
        !isset($input['name']) || 
        !isset($input['password']) || 
        !isset($input['year']) || 
        !isset($input['course']) || 
        !isset($input['section'])) {
        echo json_encode(array(
            "success" => false,
            "message" => "All required fields must be provided (student_id, name, password, year, course, section)"
        ));
        exit();
    }
    
    $student_id = trim($input['student_id']);
    $name = trim($input['name']);
    $password = $input['password']; // Keep original password for storage
    $year = trim($input['year']);
    $course = trim($input['course']);
    $section = trim($input['section']);
    $rfid = isset($input['rfid']) ? trim($input['rfid']) : null; 
    
    // Parse name into components for new field structure
    $name_parts = explode(',', $name, 2);
    if (count($name_parts) === 2) {
        $lastname = trim($name_parts[0]);
        $firstname_middlename = trim($name_parts[1]);
        $name_sub_parts = explode(' ', $firstname_middlename, 2);
        $firstname = trim($name_sub_parts[0]);
        $middlename = isset($name_sub_parts[1]) ? trim($name_sub_parts[1]) : '';
    } else {
        // Fallback: treat as firstname only
        $lastname = '';
        $firstname = $name;
        $middlename = '';
    }
    
    // Hash the password for security (but keep backward compatibility)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if student already exists
    $check_query = "SELECT student_number FROM students WHERE student_number = :student_number LIMIT 1";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':student_number', $student_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        echo json_encode(array(
            "success" => false,
            "message" => "Student ID already exists"
        ));
        exit();
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Insert into the combined database using new field structure
        $insert_query = "INSERT INTO students (student_number, lastname, firstname, middlename, course, yearlevel, section, rfid, password) 
                        VALUES (:student_number, :lastname, :firstname, :middlename, :course, :yearlevel, :section, :rfid, :password)";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bindParam(':student_number', $student_id);
        $stmt->bindParam(':lastname', $lastname);
        $stmt->bindParam(':firstname', $firstname);
        $stmt->bindParam(':middlename', $middlename);
        $stmt->bindParam(':course', $course);
        $stmt->bindParam(':yearlevel', $year);
        $stmt->bindParam(':section', $section);
        $stmt->bindParam(':rfid', $rfid);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Get the created student data for response
        $get_student_query = "SELECT student_number, lastname, firstname, middlename, course, yearlevel, section, created_at, updated_at 
                             FROM students WHERE student_number = :student_number LIMIT 1";
        $get_stmt = $conn->prepare($get_student_query);
        $get_stmt->bindParam(':student_number', $student_id);
        $get_stmt->execute();
        $student = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Construct full name for response using new field structure
        $full_name = trim($student['lastname'] . ', ' . $student['firstname'] . ' ' . ($student['middlename'] ?: ''));
        
        echo json_encode(array(
            "success" => true,
            "message" => "Registration successful",
            "student" => array(
                "id" => intval($student['student_number']),
                "student_id" => strval($student['student_number']),
                "name" => $full_name,
                "year" => $student['yearlevel'] ?: '',
                "course" => $student['course'] ?: '',
                "section" => $student['section'] ?: '',
                "created_at" => $student['created_at'] ?: date('Y-m-d H:i:s'),
                "updated_at" => $student['updated_at'] ?: date('Y-m-d H:i:s')
            )
        ));
        
        
    } catch(Exception $e) {
        // Rollback transaction
        $conn->rollback();
        throw $e;
    }
    
} catch(Exception $e) {
    // Log the error for debugging
    error_log("Registration error: " . $e->getMessage());
    
    echo json_encode(array(
        "success" => false,
        "message" => "Registration error: " . $e->getMessage(),
        "debug_info" => array(
            "line" => $e->getLine(),
            "file" => $e->getFile()
        )
    ));
}
?>