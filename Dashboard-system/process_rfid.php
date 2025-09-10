<?php
// Include the database connection
include('db_connection.php');

// Check if RFID is set
if (!isset($_POST['rfid'])) {
    header("Location: time.php?error=missing-rfid");
    exit();
}

$rfid = $_POST['rfid'];

// Check if RFID exists in the students table
$stmt = $mysqli->prepare("SELECT * FROM students WHERE rfid = ?");
$stmt->bind_param("s", $rfid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $student = $result->fetch_assoc();
    $student_id = $student['id'];

    // Check if the student already has a time-in with no time-out
    $checkStmt = $mysqli->prepare("SELECT * FROM attendance WHERE student_id = ? AND time_out IS NULL");
    $checkStmt->bind_param("i", $student_id);
    $checkStmt->execute();
    $attendanceResult = $checkStmt->get_result();

    if ($attendanceResult->num_rows == 0) {
        // Record time-in
        $time_in = date('Y-m-d H:i:s');
        $insertStmt = $mysqli->prepare("INSERT INTO attendance (student_id, rfid, time_in) VALUES (?, ?, ?)");
        $insertStmt->bind_param("iss", $student_id, $rfid, $time_in);
        if ($insertStmt->execute()) {
            header("Location: time.php?rfid=$rfid");
            exit();
        } else {
            header("Location: time.php?error=time-in-fail");
            exit();
        }
    } else {
        // Record time-out
        $time_out = date('Y-m-d H:i:s');
        $updateStmt = $mysqli->prepare("UPDATE attendance SET time_out = ? WHERE student_id = ? AND time_out IS NULL");
        $updateStmt->bind_param("si", $time_out, $student_id);
        if ($updateStmt->execute()) {
            header("Location: time.php?rfid=$rfid");
            exit();
        } else {
            header("Location: time.php?error=time-out-fail");
            exit();
        }
    }

} else {
    // RFID is not registered
    header("Location: time.php?error=not-registered&rfid=$rfid");
    exit();
}
?>
