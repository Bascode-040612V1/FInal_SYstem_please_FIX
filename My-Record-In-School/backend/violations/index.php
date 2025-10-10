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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(array("success" => false, "message" => "Method not allowed"));
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get student_id from URL path
    $path_info = $_SERVER['PATH_INFO'] ?? '';
    $path_parts = explode('/', trim($path_info, '/'));
    
    if (empty($path_parts[0])) {
        echo json_encode(array(
            "success" => false,
            "message" => "Student ID is required"
        ));
        exit();
    }
    
    $student_id = $path_parts[0];
    
    // Check for delta sync parameters for optimization
    $since_timestamp = isset($_GET['since']) ? intval($_GET['since']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
    
    // Build optimized query based on parameters
    if ($since_timestamp > 0) {
        // Delta sync - only get records modified since timestamp
        $since_date = date('Y-m-d H:i:s', $since_timestamp / 1000);
        $query = "SELECT v.violation_id as id, v.student_number as student_id, 
                         CONCAT(s.lastname, ', ', s.firstname, ' ', COALESCE(s.middlename, '')) as student_name,
                         s.yearlevel as year_level, s.course, s.section,
                         v.offense_count, v.penalty, 
                         CASE 
                             WHEN v.recorded_by_role = 'admin' THEN CONCAT('Admin ID: ', v.recorded_by_id)
                             WHEN v.recorded_by_role = 'guard' THEN CONCAT('Guard ID: ', v.recorded_by_id)
                             ELSE 'System'
                         END as recorded_by,
                         v.created_at as recorded_at, v.acknowledged,
                         vt.violation_name as violation_type,
                         v.violation_description,
                         vt.category
                  FROM violations v 
                  LEFT JOIN students s ON v.student_number = s.student_number
                  LEFT JOIN violation_types vt ON v.violation_type_id = vt.id
                  WHERE v.student_number = :student_id AND v.created_at > :since_date
                  ORDER BY v.created_at DESC";
        if ($limit > 0) {
            $query .= " LIMIT :limit";
        }
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':since_date', $since_date);
        if ($limit > 0) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        }
    } else {
        // Full sync with optional limit
        $query = "SELECT v.violation_id as id, v.student_number as student_id, 
                         CONCAT(s.lastname, ', ', s.firstname, ' ', COALESCE(s.middlename, '')) as student_name,
                         s.yearlevel as year_level, s.course, s.section,
                         v.offense_count, v.penalty, 
                         CASE 
                             WHEN v.recorded_by_role = 'admin' THEN CONCAT('Admin ID: ', v.recorded_by_id)
                             WHEN v.recorded_by_role = 'guard' THEN CONCAT('Guard ID: ', v.recorded_by_id)
                             ELSE 'System'
                         END as recorded_by,
                         v.created_at as recorded_at, v.acknowledged,
                         vt.violation_name as violation_type,
                         v.violation_description,
                         vt.category,
                         COALESCE((SELECT MAX(soc.offense_count) 
                                  FROM student_offense_counts soc 
                                  WHERE soc.student_number = v.student_number AND soc.violation_type_id = v.violation_type_id), v.offense_count) as highest_offense_count
                  FROM violations v 
                  LEFT JOIN students s ON v.student_number = s.student_number
                  LEFT JOIN violation_types vt ON v.violation_type_id = vt.id
                  WHERE v.student_number = :student_id 
                  ORDER BY v.created_at DESC";
        if ($limit > 0) {
            $query .= " LIMIT :limit";
        }
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        if ($limit > 0) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        }
    }
    
    $stmt->execute();
    
    $violations = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Map the new category system to the app's expected categories
        $category = "MINOR_OFFENSE"; // Default
        $db_category = strtoupper($row['category'] ?? '');
        
        // Map database categories to app categories
        switch ($db_category) {
            case 'DRESS CODE':
                $category = "DRESS_CODE_VIOLATION";
                break;
            case 'CONDUCT':
                $category = "CONDUCT_VIOLATION";
                break;
            case 'MINOR':
                $category = "MINOR_OFFENSE";
                break;
            case 'MAJOR':
            case 'SEVERE':
                $category = "MAJOR_OFFENSE";
                break;
            default:
                $category = "MINOR_OFFENSE";
        }
        
        $violations[] = array(
            "id" => intval($row['id']),
            "student_id" => strval($row['student_id']),
            "student_name" => $row['student_name'] ?: '',
            "year_level" => $row['year_level'] ?: '',
            "course" => $row['course'] ?: '',
            "section" => $row['section'] ?: '',
            "violation_type" => $row['violation_type'] ?: "Unknown Violation",
            "violation_description" => $row['violation_description'] ?: $row['violation_type'] ?: "No description available",
            "offense_count" => intval($row['highest_offense_count'] ?? $row['offense_count']),
            "original_offense_count" => intval($row['offense_count']),
            "penalty" => $row['penalty'] ?: "Warning",
            "recorded_by" => $row['recorded_by'] ?: "System",
            "date_recorded" => $row['recorded_at'],
            "acknowledged" => intval($row['acknowledged']),
            "category" => $category
        );
    }
    
    // Return optimized results
    echo json_encode(array(
        "success" => true,
        "message" => count($violations) > 0 ? "Violations retrieved successfully" : "No violations found for this student. Student ID: {$student_id}",
        "violations" => $violations,
        "sync_info" => array(
            "is_delta_sync" => $since_timestamp > 0,
            "since_timestamp" => $since_timestamp,
            "limit_applied" => $limit > 0 ? $limit : null,
            "server_timestamp" => time() * 1000 // Current server time in milliseconds
        ),
        "debug_info" => array(
            "student_id_searched" => $student_id,
            "violations_count" => count($violations)
        )
    ));
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        "success" => false,
        "message" => "Error retrieving violations: " . $e->getMessage(),
        "error_details" => array(
            "line" => $e->getLine(),
            "file" => basename($e->getFile())
        )
    ));
}
?>