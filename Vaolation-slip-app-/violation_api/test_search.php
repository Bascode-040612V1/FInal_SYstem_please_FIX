<?php
// Save this as: violation_api/test_search.php
require_once 'config/database.php';

header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing student search...\n\n";

$database = new Database();
$conn = $database->getViolationConnection();

if (!$conn) {
    echo "Database connection failed!\n";
    exit;
}

// Get a real student ID from the database
$stmt = $conn->query("SELECT student_number FROM students ORDER BY student_number LIMIT 1");
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "No students found in database!\n";
    exit;
}

$test_id = $student['student_number'];
echo "Testing with student ID: " . $test_id . "\n";
echo "Type: " . gettype($test_id) . "\n\n";

// Test the validation
$validated = validateInput($test_id, 'numeric', 20);
echo "Validation result: '" . $validated . "'\n";
echo "Validation success: " . ($validated !== false ? "YES" : "NO") . "\n\n";

if ($validated !== false) {
    // Test the search query
    $query = "SELECT 
        student_number as student_id, 
        CONCAT_WS(' ', firstname, IFNULL(CONCAT(middlename, ' '), ''), lastname) as student_name, 
        yearlevel as year_level, 
        course, 
        section, 
        image 
    FROM students 
    WHERE student_number = :student_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":student_id", $validated, PDO::PARAM_INT);
    $stmt->execute();
    
    echo "Query executed successfully\n";
    echo "Rows found: " . $stmt->rowCount() . "\n\n";
    
    if ($stmt->rowCount() > 0) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "SUCCESS! Found student:\n";
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "PROBLEM: Query executed but no results found\n";
        
        // Try simple query
        echo "Trying simple query...\n";
        $simple = $conn->prepare("SELECT * FROM students WHERE student_number = ?");
        $simple->execute([$test_id]);
        echo "Simple query rows: " . $simple->rowCount() . "\n";
    }
} else {
    echo "PROBLEM: Validation failed\n";
    echo "Raw input: '" . $test_id . "'\n";
    echo "is_numeric() result: " . (is_numeric($test_id) ? "true" : "false") . "\n";
}
?>



=== DETAILED STUDENT SEARCH DEBUG ===
Time: 2025-10-10 12:34:43

✅ Database connected successfully

1. DATABASE CONFIGURATION
----------------------------------------
Current database: aics_bicutan_system_db

2. STUDENTS TABLE STRUCTURE
----------------------------------------
student_number  int(11)         NO       PRI     
lastname        varchar(120)    NO       MUL     
firstname       varchar(120)    NO               
middlename      varchar(120)    YES              
name            varchar(255)    YES              
course          varchar(100)    YES              
yearlevel       varchar(50)     YES              
section         varchar(80)     YES              
rfid            bigint(20) unsigned YES      UNI     
password        varchar(255)    YES              
image           varchar(512)    YES              
created_at      timestamp       NO               
updated_at      timestamp       YES              

3. ALL STUDENTS DATA
----------------------------------------
Total students: 11

ID           Name                 Course     Year      
------------------------------------------------------------
220342       Joshua Pavia Basco   BSCS       4th       
220343       Juan Santos Dela Cru BSCS       1st Year  
220344       Maria Garcia Santos  BSIT       2nd Year  
220345       Pedro Rodriguez Garc BSCS       1st Year  
220346       Angela Lopez Reyes   BSIT       3rd Year  
220347       Carlo Rivera Mendoza BSBA       4th Year  
220348       Jasmine Cruz Torres  BSEd       1st Year  
220349       Luis Ramos Villanuev BSCS       2nd Year  
220350       Sophia Dizon Ramos   BSIT       1st Year  
220351       Patrick Santos De Le BSBA       2nd Year  

4. TESTING VALIDATION FUNCTION
----------------------------------------
Testing with actual student ID: 220342
Type of student_number: integer
Value: '220342'

Test case 1: Input: '220342' (type: string) -> Result: '1' -> ✅ PASS
Test case 2: Input: '220342' (type: integer) -> Result: '1' -> ✅ PASS
Test case 3: Input: '220342' (type: integer) -> Result: '1' -> ✅ PASS
Test case 4: Input: '0220342' (type: string) -> Result: '1' -> ✅ PASS
Test case 5: Input: ' 220342 ' (type: string) -> Result: '1' -> ✅ PASS

5. TESTING SEARCH QUERY
----------------------------------------
Testing search for student ID: 220342
  Validation: ✅ PASS (result: '1')
  Query result: 0 rows
  ❌ Not found
  Testing alternatives:
    Direct comparison: 1 rows
    String comparison: 1 rows
Error: SQLSTATE[42000]: Syntax error or access violation: 1305 FUNCTION aics_bicutan_system_db.TYPEOF does not exist
6. SIMULATING HTTP REQUEST
----------------------------------------
Simulating: GET students/search.php?student_id=220342
GET parameter: '220342'
Parameter type: string
Validated result: '1'
Validation ✅ PASSED

==================================================
Debug complete. Check the results above to identify the issue.
