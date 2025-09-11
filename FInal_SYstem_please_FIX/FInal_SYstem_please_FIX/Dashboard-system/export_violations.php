<?php
// export_violations.php - Export violation records to Excel
require_once 'config.php';
require_once 'performance_config.php';
require_once 'SimpleXLSXWriter.php';

// Check if user is authorized (basic security check)
session_start();

// For testing purposes, we'll allow access if admin is authenticated or if accessing from localhost
$is_authorized = false;
if (isset($_SESSION['is_admin_authenticated']) && $_SESSION['is_admin_authenticated']) {
    $is_authorized = true;
} elseif ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1') {
    // Allow localhost access for testing
    $is_authorized = true;
}

if (!$is_authorized) {
    http_response_code(403);
    die('Access denied. Please login as admin.');
}

try {
    // Get database connection from pool
    $pool = DatabasePool::getInstance();
    $conn = $pool->getConnection();
    
    // Check if we need to connect to the violation database
    // Based on the database structure, violations are in student_violation_db
    $conn->close();
    
    // Connect to the violation database
    $violation_conn = new mysqli("localhost", "root", "", "student_violation_db");
    if ($violation_conn->connect_error) {
        throw new Exception("Connection to violation database failed: " . $violation_conn->connect_error);
    }
    
    // Get export type (all violations or filtered)
    $export_type = $_GET['type'] ?? 'all';
    $date_filter = $_GET['date'] ?? '';
    
    // Build query based on export type
    if ($export_type === 'summary') {
        // Export violation summary
        $query = "SELECT 
                    v.id,
                    v.student_id,
                    v.student_name,
                    v.year_level,
                    v.course,
                    v.section,
                    v.offense_count,
                    v.penalty,
                    v.recorded_by,
                    v.recorded_at,
                    v.acknowledged,
                    GROUP_CONCAT(vd.violation_type SEPARATOR ', ') as violations
                  FROM violations v 
                  LEFT JOIN violation_details vd ON v.id = vd.violation_id";
        
        if (!empty($date_filter)) {
            $query .= " WHERE DATE(v.recorded_at) = ?";
        }
        
        $query .= " GROUP BY v.id ORDER BY v.recorded_at DESC";
        
        $headers = [
            'Violation ID',
            'Student ID', 
            'Student Name',
            'Year Level',
            'Course',
            'Section',
            'Offense Count',
            'Penalty',
            'Recorded By',
            'Date Recorded',
            'Acknowledged',
            'Violation Types'
        ];
        
    } else {
        // Export detailed violations
        $query = "SELECT 
                    v.id,
                    v.student_id,
                    v.student_name,
                    v.year_level,
                    v.course,
                    v.section,
                    v.offense_count,
                    v.penalty,
                    v.recorded_by,
                    v.recorded_at,
                    v.acknowledged,
                    vd.violation_type,
                    vd.violation_description,
                    vt.category as violation_category
                  FROM violations v 
                  LEFT JOIN violation_details vd ON v.id = vd.violation_id
                  LEFT JOIN violation_types vt ON vd.violation_type = vt.violation_name";
        
        if (!empty($date_filter)) {
            $query .= " WHERE DATE(v.recorded_at) = ?";
        }
        
        $query .= " ORDER BY v.recorded_at DESC, v.id";
        
        $headers = [
            'Violation ID',
            'Student ID',
            'Student Name', 
            'Year Level',
            'Course',
            'Section',
            'Offense Count',
            'Penalty',
            'Recorded By',
            'Date Recorded',
            'Acknowledged',
            'Violation Type',
            'Violation Description',
            'Category'
        ];
    }
    
    // Prepare and execute query
    if (!empty($date_filter)) {
        $stmt = $violation_conn->prepare($query);
        $stmt->bind_param("s", $date_filter);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $violation_conn->query($query);
    }
    
    // Create Excel writer
    $filename = 'Violation_Report_' . ($export_type === 'summary' ? 'Summary_' : 'Detailed_');
    if (!empty($date_filter)) {
        $filename .= date('Y-m-d', strtotime($date_filter));
    } else {
        $filename .= date('Y-m-d');
    }
    
    $excel = new SimpleXLSXWriter($filename);
    $excel->setHeaders($headers);
    
    // Add data rows
    while ($row = $result->fetch_assoc()) {
        $excel_row = [];
        
        if ($export_type === 'summary') {
            $excel_row = [
                $row['id'],
                $row['student_id'],
                $row['student_name'],
                $row['year_level'],
                $row['course'],
                $row['section'],
                $row['offense_count'],
                $row['penalty'] ?? 'Not Set',
                $row['recorded_by'],
                date('Y-m-d H:i:s', strtotime($row['recorded_at'])),
                $row['acknowledged'] ? 'Yes' : 'No',
                $row['violations'] ?? 'No specific violations'
            ];
        } else {
            $excel_row = [
                $row['id'],
                $row['student_id'],
                $row['student_name'],
                $row['year_level'],
                $row['course'],
                $row['section'],
                $row['offense_count'],
                $row['penalty'] ?? 'Not Set',
                $row['recorded_by'],
                date('Y-m-d H:i:s', strtotime($row['recorded_at'])),
                $row['acknowledged'] ? 'Yes' : 'No',
                $row['violation_type'] ?? 'Not Specified',
                $row['violation_description'] ?? 'No Description',
                $row['violation_category'] ?? 'Uncategorized'
            ];
        }
        
        $excel->addRow($excel_row);
    }
    
    // Generate filename
    // Close database connection
    if (isset($stmt)) {
        $stmt->close();
    }
    $violation_conn->close();
    
    // Download Excel file
    $excel->downloadAsExcelHTML();
    
} catch (Exception $e) {
    http_response_code(500);
    die('Export failed: ' . $e->getMessage());
}
?>