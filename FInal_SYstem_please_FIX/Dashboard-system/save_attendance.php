<?php
include 'config.php';

// Copy today's attendance to saved_attendance
$conn->query("
    INSERT INTO saved_attendance (rfid, time_in, time_out, date_saved)
    SELECT rfid, time_in, time_out, NOW()
    FROM attendance
    WHERE DATE(time_in) = CURDATE()
");

// Clear current attendance
$conn->query("DELETE FROM attendance");

$conn->close();

header("Location: attendance.php");
exit;
?>
