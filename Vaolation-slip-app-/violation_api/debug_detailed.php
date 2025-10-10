<?php
// Save this as: violation_api/debug_detailed.php
require_once 'config/database.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== DETAILED STUDENT SEARCH DEBUG ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

$database = new Database();
$conn = $database->getViolationConnection();

if (!$conn) {
    echo "❌ Database connection failed!\n";
    exit;
}

echo "✅ Database connected successfully\n\n";

// 1. Show database configuration
echo "1. DATABASE CONFIGURATION\n";
echo str_repeat("-", 40) . "\n";
try {
    $stmt = $conn->query("SELECT DATABASE() as current_db");
    $db = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Current database: " . $db['current_db'] . "\n";
} catch(Exception $e) {
    echo "Error getting database name: " . $e->getMessage() . "\n";
}
echo "\n";

// 2. Check students table structure
echo "2. STUDENTS TABLE STRUCTURE\n";
echo str_repeat("-", 40) . "\n";
try {
    $stmt = $conn->query("DESCRIBE students");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo sprintf("%-15s %-15s %-8s %-8s\n", 
            $col['Field'], 
            $col['Type'], 
            $col['Null'], 
            $col['Key']
        );
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Count and show all students
echo "3. ALL STUDENTS DATA\n";
echo str_repeat("-", 40) . "\n";
try {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM students");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total students: " . $count['total'] . "\n\n";
    
    if ($count['total'] > 0) {
        $stmt = $conn->query("SELECT 
            student_number, 
            firstname, 
            lastname, 
            middlename, 
            course, 
            yearlevel 
        FROM students 
        ORDER BY student_number 
        LIMIT 10");
        
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo sprintf("%-12s %-20s %-10s %-10s\n", "ID", "Name", "Course", "Year");
        echo str_repeat("-", 60) . "\n";
        
        foreach ($students as $student) {
            $fullName = trim(($student['firstname'] ?? '') . ' ' . 
                           ($student['middlename'] ? $student['middlename'] . ' ' : '') . 
                           ($student['lastname'] ?? ''));
            
            echo sprintf("%-12s %-20s %-10s %-10s\n", 
                $student['student_number'] ?? 'NULL',
                substr($fullName, 0, 20),
                $student['course'] ?? 'N/A',
                $student['yearlevel'] ?? 'N/A'
            );
        }
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Test validateInput function
echo "4. TESTING VALIDATION FUNCTION\n";
echo str_repeat("-", 40) . "\n";
// Get first student ID for testing
try {
    $stmt = $conn->query("SELECT student_number FROM students ORDER BY student_number LIMIT 1");
    $firstStudent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($firstStudent) {
        $testId = $firstStudent['student_number'];
        echo "Testing with actual student ID: " . $testId . "\n";
        echo "Type of student_number: " . gettype($testId) . "\n";
        echo "Value: '" . $testId . "'\n\n";
        
        // Test different input formats
        $testCases = [
            (string)$testId,           // String version
            $testId,                   // Original type
            (int)$testId,             // Integer version
            '0' . $testId,            // Leading zero
            ' ' . $testId . ' ',      // With spaces
        ];
        
        foreach ($testCases as $index => $test) {
            echo "Test case " . ($index + 1) . ": ";
            echo "Input: '" . $test . "' (type: " . gettype($test) . ") -> ";
            
            $validated = validateInput($test, 'numeric', 20);
            echo "Result: '" . $validated . "' -> ";
            echo ($validated !== false ? "✅ PASS" : "❌ FAIL") . "\n";
        }
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Test exact search query
echo "5. TESTING SEARCH QUERY\n";
echo str_repeat("-", 40) . "\n";
try {
    $stmt = $conn->query("SELECT student_number FROM students ORDER BY student_number LIMIT 3");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($students as $student) {
        $testId = $student['student_number'];
        echo "Testing search for student ID: " . $testId . "\n";
        
        // Validate input first
        $validated = validateInput($testId, 'numeric', 20);
        echo "  Validation: " . ($validated !== false ? "✅ PASS" : "❌ FAIL") . " (result: '$validated')\n";
        
        if ($validated !== false) {
            // Test the exact query from search.php
            $query = "SELECT 
                student_number as student_id, 
                CONCAT_WS(' ', firstname, IFNULL(CONCAT(middlename, ' '), ''), lastname) as student_name, 
                yearlevel as year_level, 
                course, 
                section, 
                image 
            FROM students 
            WHERE student_number = :student_id";
            
            $stmt2 = $conn->prepare($query);
            $stmt2->bindParam(":student_id", $validated, PDO::PARAM_INT);
            $stmt2->execute();
            
            echo "  Query result: " . $stmt2->rowCount() . " rows\n";
            
            if ($stmt2->rowCount() > 0) {
                $result = $stmt2->fetch(PDO::FETCH_ASSOC);
                echo "  ✅ Found: " . $result['student_name'] . "\n";
            } else {
                echo "  ❌ Not found\n";
                
                // Try alternative queries
                echo "  Testing alternatives:\n";
                
                // Test 1: Direct comparison
                $stmt3 = $conn->prepare("SELECT * FROM students WHERE student_number = ?");
                $stmt3->execute([$testId]);
                echo "    Direct comparison: " . $stmt3->rowCount() . " rows\n";
                
                // Test 2: String comparison
                $stmt4 = $conn->prepare("SELECT * FROM students WHERE CAST(student_number AS CHAR) = ?");
                $stmt4->execute([(string)$testId]);
                echo "    String comparison: " . $stmt4->rowCount() . " rows\n";
                
                // Test 3: Check data types
                $stmt5 = $conn->prepare("SELECT student_number, TYPEOF(student_number) as type FROM students WHERE student_number = ?");
                $stmt5->execute([$testId]);
                if ($stmt5->rowCount() > 0) {
                    $typeCheck = $stmt5->fetch(PDO::FETCH_ASSOC);
                    echo "    Data type in DB: " . ($typeCheck['type'] ?? 'unknown') . "\n";
                }
            }
        }
        echo "\n";
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// 6. Test HTTP request simulation
echo "6. SIMULATING HTTP REQUEST\n";
echo str_repeat("-", 40) . "\n";
try {
    $stmt = $conn->query("SELECT student_number FROM students ORDER BY student_number LIMIT 1");
    $firstStudent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($firstStudent) {
        $testId = $firstStudent['student_number'];
        echo "Simulating: GET students/search.php?student_id=" . $testId . "\n";
        
        // Simulate the GET request
        $_GET['student_id'] = (string)$testId;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        echo "GET parameter: '" . $_GET['student_id'] . "'\n";
        echo "Parameter type: " . gettype($_GET['student_id']) . "\n";
        
        $student_id = validateInput($_GET['student_id'] ?? '', 'numeric', 20);
        echo "Validated result: '" . $student_id . "'\n";
        echo "Validation " . ($student_id !== false ? "✅ PASSED" : "❌ FAILED") . "\n";
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Debug complete. Check the results above to identify the issue.\n";
?>