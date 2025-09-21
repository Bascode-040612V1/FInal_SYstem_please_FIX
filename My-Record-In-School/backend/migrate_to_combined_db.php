<?php
// Migration script to populate the new combined database with sample data
// This script helps test the new database structure

define('INCLUDED_FROM_API', true);
include_once 'config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Insert sample students
    $sample_students = [
        [
            'student_number' => 2023001,
            'surname' => 'Dela Cruz',
            'firstname' => 'Juan',
            'lastname' => 'Santos',
            'course' => 'BSCS',
            'yearlevel' => 'Grade 12',
            'section' => 'BS1MA',
            'rfid' => 'RFID12345',
            'password' => password_hash('2023001', PASSWORD_DEFAULT)
        ],
        [
            'student_number' => 2023002,
            'surname' => 'Garcia',
            'firstname' => 'Maria',
            'lastname' => 'Lopez',
            'course' => 'ICT',
            'yearlevel' => 'Grade 11',
            'section' => 'IC1MA',
            'rfid' => 'RFID12346',
            'password' => password_hash('2023002', PASSWORD_DEFAULT)
        ],
        [
            'student_number' => 2023003,
            'surname' => 'Rodriguez',
            'firstname' => 'Pedro',
            'lastname' => 'Martinez',
            'course' => 'BSIT',
            'yearlevel' => 'Grade 12',
            'section' => 'IT1MA',
            'rfid' => 'RFID12347',
            'password' => password_hash('2023003', PASSWORD_DEFAULT)
        ]
    ];
    
    $inserted_students = 0;
    foreach ($sample_students as $student) {
        try {
            $stmt = $conn->prepare("INSERT INTO students (student_number, surname, firstname, lastname, course, yearlevel, section, rfid, password) 
                                   VALUES (:student_number, :surname, :firstname, :lastname, :course, :yearlevel, :section, :rfid, :password)");
            $stmt->execute($student);
            $inserted_students++;
        } catch (PDOException $e) {
            if ($e->getCode() != 23000) { // Not a duplicate entry error
                throw $e;
            }
            // Student already exists, skip
        }
    }
    
    // Insert sample violations
    $sample_violations = [
        [
            'student_number' => 2023001,
            'violation_type_id' => 1, // No ID
            'violation_description' => 'Student was found without proper school identification',
            'recorded_by_role' => 'admin',
            'recorded_by_id' => 1
        ],
        [
            'student_number' => 2023002,
            'violation_type_id' => 7, // Using cellphones during class
            'violation_description' => 'Student was using cellphone during Mathematics class',
            'recorded_by_role' => 'admin',
            'recorded_by_id' => 1
        ]
    ];
    
    $inserted_violations = 0;
    foreach ($sample_violations as $violation) {
        try {
            $stmt = $conn->prepare("INSERT INTO violations (student_number, violation_type_id, violation_description, recorded_by_role, recorded_by_id) 
                                   VALUES (:student_number, :violation_type_id, :violation_description, :recorded_by_role, :recorded_by_id)");
            $stmt->execute($violation);
            $inserted_violations++;
        } catch (PDOException $e) {
            // Skip if already exists or other issues
        }
    }
    
    // Insert sample attendance
    $sample_attendance = [
        [
            'student_number' => 2023001,
            'time_in' => date('Y-m-d') . ' 07:45:00',
            'time_out' => date('Y-m-d') . ' 16:30:00',
            'date' => date('Y-m-d'),
            'status' => 'Present'
        ],
        [
            'student_number' => 2023002,
            'time_in' => date('Y-m-d') . ' 08:15:00',
            'time_out' => date('Y-m-d') . ' 16:30:00',
            'date' => date('Y-m-d'),
            'status' => 'Present'
        ]
    ];
    
    $inserted_attendance = 0;
    foreach ($sample_attendance as $attendance) {
        try {
            $stmt = $conn->prepare("INSERT INTO attendance (student_number, time_in, time_out, date, status) 
                                   VALUES (:student_number, :time_in, :time_out, :date, :status)");
            $stmt->execute($attendance);
            $inserted_attendance++;
        } catch (PDOException $e) {
            if ($e->getCode() != 23000) { // Not a duplicate entry error
                throw $e;
            }
            // Attendance already exists for this date, skip
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Sample data migration completed successfully',
        'details' => [
            'students_inserted' => $inserted_students,
            'violations_inserted' => $inserted_violations,
            'attendance_inserted' => $inserted_attendance,
            'database' => 'aics_bicutan_system_db'
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Migration failed: ' . $e->getMessage(),
        'error_details' => [
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]
    ]);
}
?>