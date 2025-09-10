<?php
include 'config.php';
include 'performance_config.php';

$attendance_records = [];
$absentees = [];
$error_message = '';
$success_message = '';
$selected_date = '';
$today = date('Y-m-d');
$formatted_date_header = '';

// Set caching headers
ResponseOptimizer::setHeaders();

// Get database connection from pool
$pool = DatabasePool::getInstance();
$conn = $pool->getConnection();

// Cache available dates (refresh every hour)
$datesCacheKey = 'available_dates';
$dates = SimpleCache::get($datesCacheKey);

if ($dates === false) {
    $date_query = "SELECT DISTINCT saved_date FROM saved_attendance ORDER BY saved_date DESC LIMIT 30";
    $date_result = $conn->query($date_query);
    $dates = [];
    while ($row = $date_result->fetch_assoc()) {
        $dates[] = $row['saved_date'];
    }
    SimpleCache::set($datesCacheKey, $dates, 3600); // Cache for 1 hour
}

// Save today's attendance
if (isset($_POST['save_attendance'])) {
    $fetch_query = "SELECT a.*, s.name, s.student_number, s.image
                    FROM attendance a
                    JOIN students s ON a.student_id = s.id
                    WHERE DATE(a.time_in) = ?";
    $fetch_stmt = $conn->prepare($fetch_query);
    $fetch_stmt->bind_param("s", $today);
    $fetch_stmt->execute();
    $fetch_result = $fetch_stmt->get_result();
    
    while ($row = $fetch_result->fetch_assoc()) {
        $student_id = $row['student_id'];
        $name = $row['name'];
        $student_number = $row['student_number'];
        $image = $row['image'];
        $saved_time_in = $row['time_in'];
        $saved_time_out = $row['time_out'];
        
        $insert_stmt = $conn->prepare("INSERT INTO saved_attendance (student_id, name, student_number, image, saved_time_in, saved_time_out, saved_date)
                   VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("issssss", $student_id, $name, $student_number, $image, $saved_time_in, $saved_time_out, $today);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    $fetch_stmt->close();

    $delete_stmt = $conn->prepare("DELETE FROM attendance WHERE DATE(time_in) = ?");
    $delete_stmt->bind_param("s", $today);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    $success_message = "Today's attendance saved and cleared successfully!";
}

// Get attendance records and absentees with caching
if (isset($_POST['selected_date']) && $_POST['selected_date'] != '') {
    $selected_date = $_POST['selected_date'];
    $formatted_date_header = date('F d, Y', strtotime($selected_date)); // Format for the header

    // Try cache first for historical data
    $attendanceCacheKey = 'attendance_' . $selected_date;
    $attendance_records = SimpleCache::get($attendanceCacheKey);
    
    if ($attendance_records === false) {
        // Fetch saved attendance using prepared statement
        $stmt = $conn->prepare("SELECT SQL_CACHE * FROM saved_attendance WHERE saved_date = ? ORDER BY saved_time_in DESC");
        $stmt->bind_param("s", $selected_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $attendance_records = [];
        while ($row = $result->fetch_assoc()) {
            $attendance_records[] = $row;
        }
        $stmt->close();
        
        // Cache historical data for 1 hour
        SimpleCache::set($attendanceCacheKey, $attendance_records, 3600);
    }

    // Fetch absentees for saved date using prepared statement with caching
    $absenteesCacheKey = 'absentees_' . $selected_date;
    $absentees = SimpleCache::get($absenteesCacheKey);
    
    if ($absentees === false) {
        $absent_stmt = $conn->prepare("SELECT SQL_CACHE * FROM students 
            WHERE id NOT IN (
                SELECT student_id FROM saved_attendance WHERE saved_date = ?
            )");
        $absent_stmt->bind_param("s", $selected_date);
        $absent_stmt->execute();
        $absent_result = $absent_stmt->get_result();
        $absentees = [];
        while ($row = $absent_result->fetch_assoc()) {
            $absentees[] = $row;
        }
        $absent_stmt->close();
        
        // Cache absentees for 1 hour
        SimpleCache::set($absenteesCacheKey, $absentees, 3600);
    }
} else {
    // Today's attendance - cache for shorter time for real-time updates
    $todayCacheKey = 'today_attendance_' . $today . '_' . date('H:i');
    $attendance_records = SimpleCache::get($todayCacheKey);
    
    if ($attendance_records === false) {
        // Fetch today's attendance using prepared statement
        $stmt = $conn->prepare("SELECT SQL_CACHE a.*, s.name, s.student_number, s.image
                FROM attendance a
                JOIN students s ON a.student_id = s.id
                WHERE DATE(a.time_in) = ?
                ORDER BY a.time_in DESC");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $attendance_records = [];
        while ($row = $result->fetch_assoc()) {
            $attendance_records[] = $row;
        }
        $stmt->close();
        
        // Cache for 5 minutes for real-time updates
        SimpleCache::set($todayCacheKey, $attendance_records, 300);
    }

    // Fetch absentees for today using prepared statement with caching
    $todayAbsenteesCacheKey = 'today_absentees_' . $today . '_' . date('H');
    $absentees = SimpleCache::get($todayAbsenteesCacheKey);
    
    if ($absentees === false) {
        $absent_stmt = $conn->prepare("SELECT SQL_CACHE * FROM students 
            WHERE id NOT IN (
                SELECT student_id FROM attendance WHERE DATE(time_in) = ?
            )");
        $absent_stmt->bind_param("s", $today);
        $absent_stmt->execute();
        $absent_result = $absent_stmt->get_result();
        $absentees = [];
        while ($row = $absent_result->fetch_assoc()) {
            $absentees[] = $row;
        }
        $absent_stmt->close();
        
        // Cache absentees for 1 hour
        SimpleCache::set($todayAbsenteesCacheKey, $absentees, 3600);
    }
    
    $formatted_date_header = date('F d, Y', strtotime($today)); // Default header text for today
}

// Release connection back to pool
$pool->releaseConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance Records</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: url('images/room.jpg') no-repeat center center fixed;
      background-size: cover;
      padding: 20px;
    }

    .header {
      background: rgba(52, 152, 219, 0.95);
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
      margin: 0;
    }

    .return-btn {
      position: absolute;
      left: 20px;
      top: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
      background: white;
      color: #3498db;
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

    .attendance-container {
      background: rgba(255, 255, 255, 0.85);
      padding: 20px;
      border-radius: 15px;
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .select-date-form {
      display: flex;
      justify-content: space-between;
      margin-bottom: 20px;
      align-items: center;
    }

    .select-date-form select {
      padding: 10px;
      font-size: 16px;
      border-radius: 5px;
      width: 200px;
    }

    .attendance-table, .absentees-table {
      width: 100%;
      border-collapse: collapse;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }

    th, td {
      padding: 12px;
      text-align: center;
      border-bottom: 1px solid #ddd;
    }

    th {
      background-color: #3498db;
      color: white;
    }

    .notification {
      margin-top: 15px;
      color: green;
      font-weight: bold;
    }

    .save-button-container {
      text-align: right;
      margin-top: 20px;
    }

    button {
      padding: 10px 15px;
      font-size: 16px;
      cursor: pointer;
      border-radius: 5px;
      background-color: #3498db;
      color: white;
      border: none;
      transition: background-color 0.3s;
    }

    button:hover {
      background-color: #2980b9;
    }

    .absentees-table td {
      color: #e74c3c;
    }

    .absentees-header {
      font-size: 20px;
      font-weight: bold;
      color: #e74c3c;
      margin-top: 20px;
    }

    /* Hide absent students table if no attendance records exist for selected date */
    .absentees-table-container {
      display: <?php echo empty($attendance_records) ? 'none' : 'block'; ?>;
    }
  </style>
</head>
<body>

<div class="header">
  <a href="admin.php" class="return-btn">
    <img src="images/return.png" alt="Return Icon">
    Return
  </a>
  <h1>Attendance</h1>
</div>

<div class="attendance-container">
  <div class="select-date-form">
    <label for="attendance_date">Select Attendance Date:</label>
    <form method="POST">
      <select name="selected_date" id="attendance_date" onchange="this.form.submit()">
        <option value="">-- Select a Date --</option>
        <?php foreach ($dates as $date): ?>
          <option value="<?php echo $date; ?>" <?php echo ($selected_date == $date) ? 'selected' : ''; ?>>
            <?php echo date('F d, Y', strtotime($date)); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
    
    <!-- Export Buttons -->
    <div style="margin-top: 15px; text-align: center;">
      <button type="button" onclick="exportAttendance('current')" style="padding:10px 15px; border-radius:8px; border:none; background:#3498db; color:white; cursor:pointer; margin:0 5px;">
        📊 Export Current Day
      </button>
      <button type="button" onclick="exportAttendance('saved')" style="padding:10px 15px; border-radius:8px; border:none; background:#27ae60; color:white; cursor:pointer; margin:0 5px;">
        📋 Export Selected Date
      </button>
      <button type="button" onclick="exportAttendance('summary')" style="padding:10px 15px; border-radius:8px; border:none; background:#e67e22; color:white; cursor:pointer; margin:0 5px;">
        📄 Export Summary Report
      </button>
    </div>
  </div>

  <!-- Attendance Table -->
  <h2 id="attendance-header">Attendance for <?php echo $formatted_date_header; ?></h2>

  <?php if (!empty($attendance_records)): ?>
    <table class="attendance-table">
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
        <?php foreach ($attendance_records as $row): ?>
          <tr>
            <td><img src="<?php echo $row['image'] ?? 'assets/default-profile.png'; ?>" width="50" height="50" style="border-radius: 50%;"></td>
            <td><?php echo $row['name']; ?></td>
            <td><?php echo $row['student_number']; ?></td>
            <td><?php echo date('H:i:s', strtotime($row['saved_time_in'] ?? $row['time_in'])); ?></td>
            <td><?php echo date('H:i:s', strtotime($row['saved_time_out'] ?? $row['time_out'])) ?: 'Still in'; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>No attendance records for the selected date.</p>
  <?php endif; ?>

  <?php if (empty($selected_date)): ?>
    <div class="save-button-container">
      <form method="POST" onsubmit="return confirm('Are you sure you want to save and clear today\'s attendance?');">
        <button type="submit" name="save_attendance">Save Attendance</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($success_message): ?>
    <div class="notification"><?php echo $success_message; ?></div>
  <?php elseif ($error_message): ?>
    <div class="notification"><?php echo $error_message; ?></div>
  <?php endif; ?>

  <!-- Absent Students -->
  <div class="absentees-table-container">
    <?php if (!empty($absentees)): ?>
      <div class="absentees-header">Absent Students for <?php echo $formatted_date_header; ?></div>
      <table class="absentees-table">
        <thead>
          <tr>
            <th>Picture</th>
            <th>Name</th>
            <th>Student Number</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($absentees as $row): ?>
            <tr>
              <td><img src="<?php echo $row['image'] ?? 'assets/default-profile.png'; ?>" width="50" height="50" style="border-radius: 50%;"></td>
              <td><?php echo $row['name']; ?></td>
              <td><?php echo $row['student_number']; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p>No absentee records for the selected date.</p>
    <?php endif; ?>
  </div>
</div>

<script>
  document.getElementById('attendance_date').addEventListener('change', function() {
    var selectedDate = this.value;
    var formattedDate = new Date(selectedDate);
    var dateOptions = { year: 'numeric', month: 'long', day: 'numeric' };
    var formattedDateString = formattedDate.toLocaleDateString(undefined, dateOptions);
    document.getElementById('attendance-header').innerHTML = 'Attendance for ' + formattedDateString;
  });
  
  // Export functions
  function exportAttendance(type) {
    const selectedDate = document.getElementById('attendance_date').value;
    const currentDate = '<?php echo date('Y-m-d'); ?>';
    
    let exportUrl = 'export_attendance.php?type=' + type;
    
    if (type === 'current') {
      exportUrl += '&date=' + currentDate;
    } else if (type === 'saved' && selectedDate) {
      exportUrl += '&date=' + selectedDate;
    } else if (type === 'saved' && !selectedDate) {
      exportUrl += '&date=' + currentDate;
    }
    
    // Always include absentees
    exportUrl += '&include_absentees=yes';
    
    // Open export in new window/tab for download
    window.open(exportUrl, '_blank');
  }
</script>

</body>
</html>
