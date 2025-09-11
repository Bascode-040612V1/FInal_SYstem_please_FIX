<?php
require_once '../config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>Student Search Test</title><style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: green; }
.error { color: red; }
.info { color: blue; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
</style></head><body>";

echo "<h1>🔍 Student Search Test After Fix</h1>";

// Test validation function
$testStudentIds = ['220342', '123456', '2021-0001', '2021-0002', 'invalid!@#'];

echo "<h2>1. Testing Input Validation</h2>";
foreach ($testStudentIds as $testId) {
    $validated = validateInput($testId, 'student_id', 20);
    echo "<p>Student ID '$testId': " . ($validated ? "<span class='success'>✅ VALID ($validated)</span>" : "<span class='error'>❌ INVALID</span>") . "</p>";
}

// Test database connection
echo "<h2>2. Testing Database Connection</h2>";
$database = new Database();
$violationConn = $database->getViolationConnection();
$rfidConn = $database->getRfidConnection();

if ($violationConn) {
    echo "<p class='success'>✅ Connected to student_violation_db</p>";
} else {
    echo "<p class='error'>❌ Failed to connect to student_violation_db</p>";
}

if ($rfidConn) {
    echo "<p class='success'>✅ Connected to rfid_system</p>";
} else {
    echo "<p class='info'>ℹ️ RFID connection not available (images won't work)</p>";
}

// Test search functionality
echo "<h2>3. Testing Search Functionality</h2>";

$validTestIds = ['220342', '123456', '2021-0001'];

foreach ($validTestIds as $studentId) {
    echo "<h3>Testing Student ID: $studentId</h3>";
    
    // Call the actual search endpoint
    $searchUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/violation_api/students/search.php?student_id=' . urlencode($studentId);
    
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['success'])) {
                if ($data['success']) {
                    echo "<p class='success'>✅ Student found!</p>";
                    echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                } else {
                    echo "<p class='error'>❌ Student not found: " . $data['message'] . "</p>";
                }
            } else {
                echo "<p class='error'>❌ Invalid API response</p>";
                echo "<pre>" . htmlspecialchars($response) . "</pre>";
            }
        } else {
            echo "<p class='error'>❌ API call failed (HTTP $httpCode)</p>";
        }
    } else {
        echo "<p class='info'>ℹ️ Test API manually: <a href='$searchUrl' target='_blank'>$searchUrl</a></p>";
    }
    
    echo "<hr>";
}

// Direct database test
echo "<h2>4. Direct Database Test</h2>";
if ($violationConn) {
    $query = "SELECT student_id, student_name, year_level, course, section FROM students ORDER BY id";
    $stmt = $violationConn->prepare($query);
    $stmt->execute();
    
    echo "<p class='info'>Students in database:</p>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Student ID</th><th>Name</th><th>Year</th><th>Course</th><th>Section</th></tr>";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>{$row['student_id']}</td>";
        echo "<td>{$row['student_name']}</td>";
        echo "<td>{$row['year_level']}</td>";
        echo "<td>{$row['course']}</td>";
        echo "<td>{$row['section']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "</body></html>";
?>