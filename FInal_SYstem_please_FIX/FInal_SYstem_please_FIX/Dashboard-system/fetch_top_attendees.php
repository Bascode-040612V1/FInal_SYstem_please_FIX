<?php
// Include database connection
require_once 'db_connection.php';

// Query to get the top most present students (adjust as needed)
$query = "SELECT student_name, student_number, COUNT(*) AS present_days, student_image
          FROM saved_attendance
          GROUP BY student_number
          ORDER BY present_days DESC
          LIMIT 10"; // Adjust the LIMIT as needed

$result = $conn->query($query);

// Initialize an array to store the data
$students = [];

while ($row = $result->fetch_assoc()) {
    $students[] = [
        'name' => $row['student_name'],
        'student_number' => $row['student_number'],
        'present_days' => $row['present_days'],
        'image' => $row['student_image'] // Make sure the image path is correct
    ];
}

// Return the data as JSON
echo json_encode($students);
?>
