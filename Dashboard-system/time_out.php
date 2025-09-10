<?php
$students = [];
$attendance_records = [];
$error_message = '';
$duplicate_message = '';

// Always connect to database and load attendance records
include 'config.php';
include 'performance_config.php';

// Get database connection from pool
$pool = DatabasePool::getInstance();
$conn = $pool->getConnection();

// Load attendance records with caching
$attendanceCacheKey = 'current_attendance_' . date('Y-m-d-H-i');
$attendance_records = SimpleCache::get($attendanceCacheKey);

if ($attendance_records === false) {
    $attendance_sql = "SELECT SQL_CACHE a.*, s.name, s.student_number, s.image FROM attendance a
                        JOIN students s ON a.student_id = s.id
                        ORDER BY a.id DESC
                        LIMIT 50";
    $attendance_result = $conn->query($attendance_sql);
    $attendance_records = [];
    while ($row = $attendance_result->fetch_assoc()) {
        $attendance_records[] = $row;
    }
    
    // Cache for 2 minutes for real-time updates
    SimpleCache::set($attendanceCacheKey, $attendance_records, 120);
}

// If RFID is scanned, process time-out logic
if (isset($_GET['rfid'])) {
    $rfid = trim($_GET['rfid']);

    // Use prepared statement to find student
    $student_stmt = $conn->prepare("SELECT * FROM students WHERE rfid = ?");
    $student_stmt->bind_param("s", $rfid);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();

    if ($student_result->num_rows > 0) {
        $student = $student_result->fetch_assoc();

        // Use prepared statement to check attendance
        $attendance_check_stmt = $conn->prepare("SELECT * FROM attendance WHERE student_id = ? ORDER BY id DESC LIMIT 1");
        $attendance_check_stmt->bind_param("i", $student['id']);
        $attendance_check_stmt->execute();
        $attendance_check_result = $attendance_check_stmt->get_result();

        if ($attendance_check_result->num_rows > 0) {
            $attendance = $attendance_check_result->fetch_assoc();

            if ($attendance['time_out'] === NULL) {
                // Use prepared statement for time-out update
                $update_stmt = $conn->prepare("UPDATE attendance SET time_out = NOW() WHERE id = ?");
                $update_stmt->bind_param("i", $attendance['id']);
                
                if ($update_stmt->execute()) {
                    $duplicate_message = "Time-Out recorded successfully for " . $student['name'] . ".";
                    
                    // Clear cache to force refresh
                    SimpleCache::delete('current_attendance_' . date('Y-m-d-H-i'));
                    SimpleCache::delete('today_attendance_' . date('Y-m-d') . '_' . date('H:i'));
                } else {
                    $error_message = "Error updating time-out.";
                }
                $update_stmt->close();
            } else {
                $duplicate_message = "This student has already timed out today.";
            }
        } else {
            $duplicate_message = "This student is not marked as time-in.";
        }
        $attendance_check_stmt->close();

        // Refresh updated attendance records after time-out
        $attendance_records = [];
        $attendance_result = $conn->query($attendance_sql);
        while ($row = $attendance_result->fetch_assoc()) {
            $attendance_records[] = $row;
        }
    } else {
        $error_message = "RFID isn't registered.";
    }
    $student_stmt->close();
}

// Release connection back to pool
$pool->releaseConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>RFID Attendance Tracker</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Segoe UI', sans-serif;
      background: url('images/room.jpg') no-repeat center center fixed;
      background-size: cover;
      padding: 20px;
    }

    .header {
      background: rgba(46, 204, 113, 0.95);
      color: white;
      padding: 20px;
      display: flex;
      justify-content: center;
      align-items: center;
      border-radius: 10px;
      margin-bottom: 30px;
      position: relative;
    }

    .header h1 {
      font-size: 28px;
      text-align: center;
      margin: 0;
    }

    .clock-box {
      background: rgba(255, 255, 255, 0.7);
      padding: 15px;
      font-size: 20px;
      text-align: center;
      border-radius: 8px;
      margin-bottom: 20px;
    }

    .clock {
      font-size: 20px;
    }

    .return-btn {
      position: absolute;
      left: 20px;
      top: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
      background: white;
      color: #e74c3c;
      padding: 8px 12px;
      border-radius: 20px;
      font-weight: bold;
      text-decoration: none;
      transition: background 0.3s;
    }

    .return-btn img {
      width: 20px;
      height: 20px;
    }

    .return-btn:hover {
      background: #ecf0f1;
    }

    .container {
      display: flex;
      gap: 30px;
      backdrop-filter: blur(5px);
    }

    .left-box, .right-box {
      background: white;
      padding: 20px;
      border-radius: 15px;
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .left-box {
      flex: 1;
    }

    .left-box h2 {
      margin-bottom: 15px;
    }

    .rfid-input {
      padding: 10px;
      width: 100%;
      font-size: 16px;
      margin-top: 10px;
      border: 1px solid #ccc;
      border-radius: 8px;
    }

    .right-box {
      flex: 2;
    }

    .student-image {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      object-fit: cover;
      margin-bottom: 20px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      padding: 10px;
      text-align: center;
      border-bottom: 1px solid #ddd;
    }

    th {
      background-color: #e74c3c;
      color: white;
    }

    .notification {
      margin-top: 15px;
      color: red;
      font-weight: bold;
    }
  </style>

  <script>
    function updateClock() {
      const now = new Date();
      const date = now.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
      const time = now.toLocaleTimeString();
      document.getElementById('clock').innerHTML = date + ' | ' + time;
    }
    setInterval(updateClock, 1000);
    window.onload = updateClock;

    function fetchAttendanceData(rfid) {
      var xhr = new XMLHttpRequest();
      xhr.open("GET", "time_out.php?rfid=" + encodeURIComponent(rfid), true);
      xhr.onload = function () {
        if (xhr.status === 200) {
          document.open();
          document.write(xhr.responseText);
          document.close();
        }
      };
      xhr.send();
    }

    document.addEventListener("DOMContentLoaded", function () {
      const input = document.querySelector("input[name='rfid']");
      const rfidLength = 10; // Adjust to your RFID scanner length

      input.focus();
      input.addEventListener("input", function () {
        if (input.value.length >= rfidLength) {
          fetchAttendanceData(input.value);
          input.value = ""; // Clear for next scan
        }
      });
    });
  </script>
</head>
<body>
<div class="header">
  <a href="time.php" class="return-btn">
    <img src="images/return.png" alt="Return Icon">
    Return
  </a>
  <h1>RFID Attendance Tracker</h1>
</div>

<div class="clock-box">
  <div class="clock" id="clock"></div>
</div>

<div class="container">
  <div class="left-box">
    <h2>Scan your RFID here:</h2>
    <form>
      <input type="text" name="rfid" class="rfid-input" placeholder="Enter RFID Number..." autofocus required>
    </form>
    <?php if ($error_message): ?>
      <div class="notification"><?php echo $error_message; ?></div>
    <?php elseif ($duplicate_message): ?>
      <div class="notification"><?php echo $duplicate_message; ?></div>
    <?php endif; ?>
  </div>

  <div class="right-box">
    <table id="attendanceTable">
      <thead>
        <tr>
          <th>Picture</th>
          <th>Name</th>
          <th>Student Number</th>
          <th>Time In</th>
          <th>Time Out</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($attendance_records as $attendance): ?>
          <tr>
            <td><img src="<?php echo $attendance['image'] ?? 'assets/default-profile.png'; ?>" width="50" height="50" style="border-radius: 50%;"></td>
            <td><?php echo $attendance['name']; ?></td>
            <td><?php echo $attendance['student_number']; ?></td>
            <td><?php echo $attendance['time_in']; ?></td>
            <td><?php echo $attendance['time_out'] ? $attendance['time_out'] : 'Still in'; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
