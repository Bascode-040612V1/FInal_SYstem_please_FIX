<?php
// Database configuration and connection management
// This file should only be included, not executed directly

class Database {
    private $host = "localhost";
    private $db_name = "aics_bicutan_system_db";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                )
            );
        } catch(PDOException $exception) {
            http_response_code(500);
            echo json_encode(array(
                "success" => false,
                "message" => "Database connection error: " . $exception->getMessage(),
                "error_code" => $exception->getCode()
            ));
            exit();
        }
        return $this->conn;
    }

    // Backward compatibility methods
    public function getViolationsConnection() {
        return $this->getConnection();
    }
    
    public function getRfidConnection() {
        return $this->getConnection();
    }

    public function testConnections() {
        try {
            // Test the combined database
            $conn = $this->getConnection();
            $students_test = $conn->query("SELECT COUNT(*) as count FROM students");
            $students_result = $students_test->fetch(PDO::FETCH_ASSOC);
            
            $violations_test = $conn->query("SELECT COUNT(*) as count FROM violations");
            $violations_result = $violations_test->fetch(PDO::FETCH_ASSOC);
            
            $attendance_test = $conn->query("SELECT COUNT(*) as count FROM attendance");
            $attendance_result = $attendance_test->fetch(PDO::FETCH_ASSOC);
            
            return array(
                "success" => true,
                "message" => "Combined database connected successfully",
                "details" => array(
                    "database" => $this->db_name,
                    "students" => $students_result['count'] . " students",
                    "violations" => $violations_result['count'] . " violations",
                    "attendance" => $attendance_result['count'] . " attendance records",
                    "timestamp" => date('Y-m-d H:i:s')
                )
            );
        } catch(Exception $e) {
            return array(
                "success" => false,
                "message" => "Connection test failed: " . $e->getMessage(),
                "error_details" => array(
                    "line" => $e->getLine(),
                    "file" => basename($e->getFile())
                )
            );
        }
    }
    
    // Helper method to ensure student exists and get student_number
    public function ensureStudentExists($student_number) {
        try {
            $conn = $this->getConnection();
            
            // Check if student exists
            $check_query = "SELECT student_number FROM students WHERE student_number = :student_number LIMIT 1";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(':student_number', $student_number);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                return $student_number;
            } else {
                throw new Exception("Student not found: " . $student_number);
            }
        } catch(Exception $e) {
            throw new Exception("Failed to verify student: " . $e->getMessage());
        }
    }
}

// Set appropriate headers when this file is accessed directly
if (!defined('INCLUDED_FROM_API')) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit(0);
    }
}
?>