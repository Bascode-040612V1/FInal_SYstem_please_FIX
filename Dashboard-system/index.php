<?php
// Database connection and performance optimization
include 'config.php';
include 'performance_config.php';

// Set response headers for caching
ResponseOptimizer::setHeaders();

// Try to get data from cache first
$cacheKey = 'top_students_' . date('Y-m-d-H'); // Cache for 1 hour
$students = SimpleCache::get($cacheKey);

if ($students === false) {
    // Cache miss - fetch from database with optimized query
    $pool = DatabasePool::getInstance();
    $conn = $pool->getConnection();
    
    // Optimized query with proper indexing hints and limit
    $sql = "
      SELECT SQL_CACHE s.name, s.student_number, s.id AS student_id, 
             COUNT(DISTINCT sa.saved_date) AS present_days
      FROM students s
      LEFT JOIN saved_attendance sa ON s.id = sa.student_id
      GROUP BY s.id, s.name, s.student_number
      ORDER BY present_days DESC
      LIMIT 10
    ";
    
    $result = $conn->query($sql);
    $students = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }
    
    // Cache the results for 1 hour
    SimpleCache::set($cacheKey, $students, 3600);
    
    // Release connection back to pool
    $pool->releaseConnection($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>RFID Attendance System</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600&display=swap" />
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Segoe UI', sans-serif;
      background-image: url('images/room.jpg');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      color: #333;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    .header-menu {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 40px;
      background: rgba(41, 128, 185, 0.8);
      color: white;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      position: sticky;
      top: 0;
      z-index: 10;
    }

    .header-menu h1 { font-size: 24px; }

    .nav-links {
      display: flex;
    }

    .nav-links a {
      padding: 12px 18px;
      color: white;
      text-decoration: none;
      font-size: 16px;
      margin-left: 20px;
      display: flex;
      align-items: center;
      transition: background 0.3s;
    }

    .nav-links a:hover {
      background-color: #1f6fa2;
      border-radius: 6px;
    }

    .nav-links a img {
      width: 20px;
      height: 20px;
      margin-right: 8px;
    }

    .main-content {
      display: flex;
      flex-wrap: wrap;
      gap: 30px;
      padding: 40px;
      justify-content: center;
      flex: 1;
    }

    .video-section {
      flex: 1 1 500px;
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      overflow: hidden;
      position: relative;
    }

    .video-section video {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .mute-btn {
      position: absolute;
      top: 10px;
      left: 10px;
      background: rgba(0, 0, 0, 0.6);
      color: white;
      border: none;
      padding: 10px;
      border-radius: 5px;
      cursor: pointer;
    }

    .mute-btn:hover { background: rgba(0, 0, 0, 0.8); }

    .right-panel {
      flex: 1 1 300px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .clock {
      background: white;
      border-radius: 12px;
      padding: 30px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .clock h1 { font-size: 48px; }
    .clock p { font-size: 20px; color: #666; margin-top: 10px; }

    .card-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
    }

    .card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .card img { width: 32px; margin-bottom: 10px; }
    .card h3 { font-size: 18px; margin-bottom: 8px; }
    .card p { font-size: 14px; color: #666; }

    .student-table {
      padding: 20px 40px;
      background: rgba(255,255,255,0.95);
      margin: 30px 40px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .student-table h2 {
      font-size: 22px;
      margin-bottom: 15px;
    }

    .student-table table {
      width: 100%;
      border-collapse: collapse;
    }

    .student-table th, .student-table td {
      padding: 12px 15px;
      text-align: center;
      border-bottom: 1px solid #ddd;
    }

    .student-table th {
      background-color: #3498db;
      color: white;
    }

    .student-table tr:hover {
      background-color: #f2f2f2;
    }

    .footer {
      background-color: rgba(0, 0, 0, 0.7);
      color: white;
      text-align: center;
      padding: 20px 0;
      margin-top: auto;
    }

    @media (max-width: 768px) {
      .main-content { flex-direction: column; align-items: center; }
      .card-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="header-menu">
    <h1>DEVSYST RFID Attendance System</h1>
    <div class="nav-links">
      <a href="index.php"><img src="images/homm.png" alt="Home"> Home</a>
      <a href="about.php"><img src="images/bout.png" alt="About"> About</a>
      <a href="contact.php"><img src="images/contact.png" alt="Contact"> Contact</a>
      <a href="time.php"><img src="images/time.png" alt="Time"> Time In / Out</a>
      <a href="admin.php"><img src="images/admin.png" alt="Admin"> Admin</a>
    </div>
  </div>

  <div class="main-content">
    <div class="video-section">
      <video id="video" src="images/vide.mp4" autoplay loop muted playsinline></video>
      <button id="mute-btn" class="mute-btn">Unmute</button>
    </div>

    <div class="right-panel">
      <div class="clock">
        <h1 id="clock-time">10:45:30 AM</h1>
        <p id="clock-date">May 01, 2025</p>
      </div>

      <div class="card-grid">
        <div class="card"><img src="images/bout.png" alt="About"><h3>About</h3><p>Learn about the system and how it works.</p></div>
        <div class="card"><img src="images/contact.png" alt="Contact"><h3>Contact</h3><p>Get in touch with support or developers.</p></div>
        <div class="card"><img src="images/time.png" alt="Time"><h3>Time In / Out</h3><p>Track attendance via RFID scans.</p></div>
        <div class="card"><img src="images/admin.png" alt="Admin"><h3>Admin</h3><p>Manage records and system settings.</p></div>
      </div>
    </div>
  </div>

  <!-- Most Present Students Table -->
  <div class="student-table">
    <h2>Most Present Students</h2>
    <table id="topStudentsTable">
      <thead>
        <tr>
          <th>Name</th>
          <th>Student Number</th>
          <th>Present Days</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $student): ?>
          <tr>
            <td><?= $student['name']; ?></td>
            <td><?= $student['student_number']; ?></td>
            <td><?= $student['present_days']; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="footer">
    <p>© 2025 DEVSYST. All Rights Reserved. Built with XAMPP, VS Code, and RFID Technology.
Developed by DEVSYST. 
</p>
  </div>

  <script>
    function updateClock() {
      const timeElem = document.getElementById("clock-time");
      const dateElem = document.getElementById("clock-date");
      const now = new Date();
      timeElem.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      dateElem.textContent = now.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: '2-digit' });
    }

    setInterval(updateClock, 1000);
    updateClock();

    const video = document.getElementById("video");
    const muteBtn = document.getElementById("mute-btn");
    muteBtn.addEventListener("click", () => {
      video.muted = !video.muted;
      muteBtn.textContent = video.muted ? "Unmute" : "Mute";
    });
  </script>
</body>
</html>
