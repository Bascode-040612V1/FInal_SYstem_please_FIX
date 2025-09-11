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

// Enable output compression
if (extension_loaded('zlib') && !ob_get_level()) {
    ob_start('ob_gzhandler');
}

// Set caching headers
$etag = md5($student_id . date('Y-m-d-H')); // Cache for 1 hour
header("ETag: \"$etag\"");
header('Cache-Control: public, max-age=3600'); // 1 hour cache

// Check if client has cached version
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === "\"$etag\"") {
    http_response_code(304);
    exit();
}

$database = new Database();
$conn = $database->getRfidConnection();

if (!$conn) {
    sendResponse(false, "Database connection failed");
}

try {
    // Optimized query with better indexing
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
              WHERE student_number = :student_id 
              LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":student_id", $student_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Try to get additional student info from violation database if available
        $violationConn = $database->getViolationConnection();
        if ($violationConn) {
            // Optimized query for additional student info
            $studentInfoQuery = "SELECT year_level, course, section FROM students WHERE student_id = ? LIMIT 1";
            $studentInfoStmt = $violationConn->prepare($studentInfoQuery);
            $studentInfoStmt->execute([$student_id]);
            
            if ($studentInfoStmt->rowCount() > 0) {
                $additionalInfo = $studentInfoStmt->fetch(PDO::FETCH_ASSOC);
                $student['year_level'] = $additionalInfo['year_level'];
                $student['course'] = $additionalInfo['course'];
                $student['section'] = $additionalInfo['section'];
            }
            
            // Optimized offense counts query with index
            $offenseQuery = "SELECT violation_type, offense_count 
                           FROM student_violation_offense_counts 
                           WHERE student_id = ? 
                           ORDER BY violation_type";
            $offenseStmt = $violationConn->prepare($offenseQuery);
            $offenseStmt->execute([$student_id]);
            
            $offenseCounts = [];
            while ($row = $offenseStmt->fetch(PDO::FETCH_ASSOC)) {
                $offenseCounts[$row['violation_type']] = (int)$row['offense_count'];
            }
            
            $student['offense_counts'] = $offenseCounts;
            
            // Add performance metrics
            $student['cached_at'] = time();
            $student['cache_duration'] = 3600; // 1 hour
        } else {
            $student['offense_counts'] = [];
        }
        
        // Add compression info
        header('X-Content-Encoding: gzip');
        header('X-Cache-Status: MISS');
        
        sendResponse(true, "Student found (optimized)", $student);
    } else {
        sendResponse(false, "Student not found");
    }
    
} catch(PDOException $exception) {
    error_log("Optimized student search error: " . $exception->getMessage());
    sendResponse(false, "Search failed: " . $exception->getMessage());
}
?>