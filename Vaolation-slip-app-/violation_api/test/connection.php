<?php
require_once '../config/database.php';

// For CLI testing, simulate GET request
$_SERVER['REQUEST_METHOD'] = 'GET';

echo "=== VIOLATION API CONNECTION TEST ===\n";
echo "Testing connection to unified database: aics_bicutan_system_db\n\n";

$database = new Database();

try {
    // Test database connection
    $conn = $database->getViolationConnection();
    
    $results = [];
    
    if ($conn) {
        $results['database'] = ' Connected successfully to aics_bicutan_system_db';
        
        // Test queries on key tables with proper schema (lastname, firstname, middlename)
        $tables_to_test = [
            'admins' => 'Admin accounts',
            'guards' => 'Security guards', 
            'students' => 'Student records',
            'violation_types' => 'Violation categories',
            'violations' => 'Violation records',
            'student_offense_counts' => 'Offense tracking',
            'penalty_matrix' => 'Penalty rules'
        ];
        
        foreach ($tables_to_test as $table => $description) {
            try {
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table");
                $stmt->execute();
                $count = $stmt->fetch(PDO::FETCH_ASSOC);
                $results["{$table}_count"] = " {$count['count']} records ({$description})";
            } catch(PDOException $e) {
                $results["{$table}_count"] = "Error: " . $e->getMessage();
            }
        }
        
        // Test student table schema specifically (lastname, firstname, middlename)
        try {
            $stmt = $conn->prepare("DESCRIBE students");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $column_names = array_column($columns, 'Field');
            
            $expected_columns = ['lastname', 'firstname', 'middlename'];
            $schema_check = [];
            foreach ($expected_columns as $col) {
                $schema_check[] = in_array($col, $column_names) ? "✅ $col" : "❌ $col missing";
            }
            $results['schema_check'] = implode(', ', $schema_check);
        } catch(PDOException $e) {
            $results['schema_check'] = " Schema check failed: " . $e->getMessage();
        }
        
        // Test triggers
        try {
            $stmt = $conn->prepare("SHOW TRIGGERS LIKE 'trg_violations_%'");
            $stmt->execute();
            $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $results['triggers_count'] = " " . count($triggers) . " violation triggers found";
        } catch(PDOException $e) {
            $results['triggers_count'] = " Trigger check failed: " . $e->getMessage();
        }
        
        // Test sample student search (if data exists)
        try {
            $stmt = $conn->prepare("SELECT student_number, CONCAT_WS(' ', firstname, IFNULL(CONCAT(middlename, ' '), ''), lastname) as full_name FROM students LIMIT 1");
            $stmt->execute();
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($student) {
                $results['sample_student'] = " Sample: ID {$student['student_number']} - {$student['full_name']}";
            } else {
                $results['sample_student'] = " No student data found";
            }
        } catch(PDOException $e) {
            $results['sample_student'] = " Student query failed: " . $e->getMessage();
        }
        
    } else {
        $results['database'] = ' Connection failed to aics_bicutan_system_db';
    }
    
    $results['server_time'] = date('Y-m-d H:i:s');
    $results['php_version'] = "PHP " . phpversion();
    
    echo "Test Results:\n";
    echo str_repeat("-", 50) . "\n";
    foreach ($results as $key => $value) {
        echo sprintf("%-20s: %s\n", $key, $value);
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo " = Working correctly\n";
    echo " = Needs attention\n"; 
    echo " = Warning/Info\n";
    
} catch(Exception $e) {
    error_log("Connection test error: " . $e->getMessage());
    echo "Critical Error: " . $e->getMessage() . "\n";
}
?>