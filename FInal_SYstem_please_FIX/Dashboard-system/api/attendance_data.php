<?php
// api/attendance_data.php - Fast JSON API for attendance data
include '../config.php';
include '../performance_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Set caching headers
ResponseOptimizer::setHeaders();

$action = $_GET['action'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d');

// Try cache first
$cacheKey = 'api_' . $action . '_' . $date . '_' . date('H:i');
$data = SimpleCache::get($cacheKey);

if ($data === false) {
    $pool = DatabasePool::getInstance();
    $conn = $pool->getConnection();
    
    switch ($action) {
        case 'daily_stats':
            $stmt = $conn->prepare("SELECT SQL_CACHE 
                COUNT(*) as total_present, 
                COUNT(CASE WHEN time_out IS NOT NULL THEN 1 END) as completed_checkout,
                COUNT(CASE WHEN time_out IS NULL THEN 1 END) as pending_checkout
                FROM attendance WHERE DATE(time_in) = ?");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();
            break;
            
        case 'top_students':
            $limit = min((int)($_GET['limit'] ?? 5), 20); // Max 20 students
            $stmt = $conn->prepare("SELECT SQL_CACHE s.name, s.student_number, 
                COUNT(DISTINCT sa.saved_date) as present_days
                FROM students s 
                LEFT JOIN saved_attendance sa ON s.id = sa.student_id
                GROUP BY s.id, s.name, s.student_number
                ORDER BY present_days DESC 
                LIMIT ?");
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            break;
            
        case 'current_attendance':
            $limit = min((int)($_GET['limit'] ?? 10), 50); // Max 50 records
            $stmt = $conn->prepare("SELECT SQL_CACHE a.id, s.name, s.student_number, 
                a.time_in, a.time_out,
                CASE WHEN a.time_out IS NULL THEN 'checked_in' ELSE 'completed' END as status
                FROM attendance a 
                JOIN students s ON a.student_id = s.id
                WHERE DATE(a.time_in) = ?
                ORDER BY a.time_in DESC 
                LIMIT ?");
            $stmt->bind_param("si", $date, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            break;
            
        case 'student_search':
            $query = $_GET['query'] ?? '';
            if (strlen($query) >= 2) {
                $searchTerm = '%' . $query . '%';
                $stmt = $conn->prepare("SELECT SQL_CACHE id, name, student_number, rfid 
                    FROM students 
                    WHERE name LIKE ? OR student_number LIKE ? 
                    ORDER BY name ASC 
                    LIMIT 10");
                $stmt->bind_param("ss", $searchTerm, $searchTerm);
                $stmt->execute();
                $result = $stmt->get_result();
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                $stmt->close();
            } else {
                $data = ['error' => 'Query too short'];
            }
            break;
            
        default:
            $data = ['error' => 'Invalid action'];
    }
    
    // Set appropriate cache time based on data type
    $cacheTTL = 300; // 5 minutes default
    if ($action === 'current_attendance' || $action === 'daily_stats') {
        $cacheTTL = 60; // 1 minute for real-time data
    } elseif ($action === 'top_students') {
        $cacheTTL = 3600; // 1 hour for stable data
    }
    
    SimpleCache::set($cacheKey, $data, $cacheTTL);
    $pool->releaseConnection($conn);
}

// Send compressed JSON response
ResponseOptimizer::sendCompressedJSON($data);
?>