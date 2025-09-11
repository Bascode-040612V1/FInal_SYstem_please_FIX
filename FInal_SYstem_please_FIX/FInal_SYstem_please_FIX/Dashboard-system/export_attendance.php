<?php
// export_attendance.php - Export attendance records to Excel
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
    // Get database connection from pool (rfid_system database)
    $pool = DatabasePool::getInstance();
    $conn = $pool->getConnection();
    
    // Get export parameters
    $export_type = $_GET['type'] ?? 'current'; // current, saved, or specific_date
    $date_filter = $_GET['date'] ?? date('Y-m-d');
    $include_absentees = $_GET['include_absentees'] ?? 'yes';
    
    // Generate filename
    $filename = 'Attendance_Report_' . ucfirst($export_type) . '_' . $date_filter;
    
    $excel = new SimpleXLSXWriter($filename);
    
    if ($export_type === 'current') {
        // Export current day attendance
        $query = "SELECT 
                    a.id,
                    s.name,
                    s.student_number,
                    s.rfid,
                    a.time_in,
                    a.time_out,
                    a.date,
                    CASE 
                        WHEN a.time_out IS NULL THEN 'Still In' 
                        ELSE 'Completed' 
                    END as status,
                    CASE 
                        WHEN a.time_out IS NOT NULL 
                        THEN TIMEDIFF(a.time_out, a.time_in) 
                        ELSE NULL 
                    END as duration
                  FROM attendance a 
                  JOIN students s ON a.student_id = s.id 
                  WHERE DATE(a.time_in) = ?
                  ORDER BY a.time_in DESC";
        
        $headers = [
            'ID',
            'Student Name',
            'Student Number', 
            'RFID',
            'Time In',
            'Time Out',
            'Date',
            'Status',
            'Duration'
        ];
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $date_filter);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $excel->setHeaders($headers);
        
        while ($row = $result->fetch_assoc()) {
            $excel_row = [
                $row['id'],
                $row['name'],
                $row['student_number'],
                $row['rfid'] ?? 'Not Set',
                $row['time_in'],
                $row['time_out'] ?? 'Still In',
                $row['date'],
                $row['status'],
                $row['duration'] ?? 'N/A'
            ];
            $excel->addRow($excel_row);
        }
        
        $filename = 'Current_Attendance_' . $date_filter;
        
    } elseif ($export_type === 'saved') {
        // Export saved attendance for specific date
        $query = "SELECT 
                    sa.id,
                    sa.name,
                    sa.student_number,
                    sa.saved_time_in,
                    sa.saved_time_out,
                    sa.saved_date,
                    CASE 
                        WHEN sa.saved_time_out IS NULL THEN 'No Time Out' 
                        ELSE 'Completed' 
                    END as status,
                    CASE 
                        WHEN sa.saved_time_out IS NOT NULL 
                        THEN TIMEDIFF(sa.saved_time_out, sa.saved_time_in) 
                        ELSE NULL 
                    END as duration
                  FROM saved_attendance sa
                  WHERE sa.saved_date = ?
                  ORDER BY sa.saved_time_in ASC";
        
        $headers = [
            'ID',
            'Student Name',
            'Student Number',
            'Time In',
            'Time Out', 
            'Date',
            'Status',
            'Duration'
        ];
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $date_filter);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $excel->setHeaders($headers);
        
        while ($row = $result->fetch_assoc()) {
            $excel_row = [
                $row['id'],
                $row['name'],
                $row['student_number'],
                $row['saved_time_in'],
                $row['saved_time_out'] ?? 'No Time Out',
                $row['saved_date'],
                $row['status'],
                $row['duration'] ?? 'N/A'
            ];
            $excel->addRow($excel_row);
        }
        
        $filename = 'Saved_Attendance_' . $date_filter;
        
    } elseif ($export_type === 'summary') {
        // Export attendance summary for all dates
        $query = "SELECT 
                    s.name,
                    s.student_number,
                    s.rfid,
                    COUNT(DISTINCT sa.saved_date) as total_days_present,
                    MIN(sa.saved_date) as first_attendance,
                    MAX(sa.saved_date) as last_attendance,
                    AVG(CASE 
                        WHEN sa.saved_time_out IS NOT NULL 
                        THEN TIME_TO_SEC(TIMEDIFF(sa.saved_time_out, sa.saved_time_in))/3600 
                        ELSE NULL 
                    END) as avg_hours_per_day
                  FROM students s
                  LEFT JOIN saved_attendance sa ON s.id = sa.student_id
                  GROUP BY s.id, s.name, s.student_number, s.rfid
                  ORDER BY total_days_present DESC, s.name ASC";
        
        $headers = [
            'Student Name',
            'Student Number',
            'RFID',
            'Total Days Present',
            'First Attendance',
            'Last Attendance',
            'Average Hours Per Day'
        ];
        
        $result = $conn->query($query);
        $excel->setHeaders($headers);
        
        while ($row = $result->fetch_assoc()) {
            $excel_row = [
                $row['name'],
                $row['student_number'],
                $row['rfid'] ?? 'Not Set',
                $row['total_days_present'],
                $row['first_attendance'] ?? 'Never',
                $row['last_attendance'] ?? 'Never',
                $row['avg_hours_per_day'] ? round($row['avg_hours_per_day'], 2) . ' hours' : 'N/A'
            ];
            $excel->addRow($excel_row);
        }
        
        $filename = 'Attendance_Summary_' . date('Y-m-d');
    }
    
    // Add absentees sheet if requested and if it's a specific date export
    if ($include_absentees === 'yes' && in_array($export_type, ['current', 'saved'])) {
        // Add separator row
        $excel->addRow(['']); 
        $excel->addRow(['=== ABSENT STUDENTS ===']);
        $excel->addRow(['']);
        
        // Get absentees
        if ($export_type === 'current') {
            $absent_query = "SELECT s.name, s.student_number, s.rfid 
                           FROM students s 
                           WHERE s.id NOT IN (
                               SELECT DISTINCT student_id 
                               FROM attendance 
                               WHERE DATE(time_in) = ?
                           )
                           ORDER BY s.name ASC";
        } else {
            $absent_query = "SELECT s.name, s.student_number, s.rfid 
                           FROM students s 
                           WHERE s.id NOT IN (
                               SELECT DISTINCT student_id 
                               FROM saved_attendance 
                               WHERE saved_date = ?
                           )
                           ORDER BY s.name ASC";
        }
        
        $absent_stmt = $conn->prepare($absent_query);
        $absent_stmt->bind_param("s", $date_filter);
        $absent_stmt->execute();
        $absent_result = $absent_stmt->get_result();
        
        // Add absentee headers
        $excel->addRow(['Student Name', 'Student Number', 'RFID']);
        
        while ($row = $absent_result->fetch_assoc()) {
            $excel->addRow([
                $row['name'],
                $row['student_number'],
                $row['rfid'] ?? 'Not Set'
            ]);
        }
        $absent_stmt->close();
    }
    
    // Close connections
    if (isset($stmt)) {
        $stmt->close();
    }
    $pool->releaseConnection($conn);
    
    // Download Excel file
    $excel->downloadAsExcelHTML();
    
} catch (Exception $e) {
    http_response_code(500);
    die('Export failed: ' . $e->getMessage());
}
?>