<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Time-In/Time-Out | RFID Attendance System</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600&display=swap">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background: url('images/room.jpg') no-repeat center center fixed;
      background-size: cover;
      padding: 0;
      display: flex;
      flex-direction: column;
      height: 100vh;
      color: white;
    }

    /* Header / Menu Bar */
    .header-menu {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 40px;
      background: rgba(41, 128, 185, 0.8); /* Blue background with transparency */
      color: white;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      position: sticky;
      top: 0;
      z-index: 10;
    }

    .header-menu h1 {
      font-size: 24px;
    }

    .nav-links {
      display: flex;
    }

    .nav-link {
      padding: 12px 18px;
      color: white;
      text-decoration: none;
      font-size: 16px;
      margin-left: 20px;
      display: flex;
      align-items: center;
      transition: background 0.3s;
    }

    .nav-link:hover {
      background-color: #1f6fa2;
      border-radius: 6px;
    }

    .nav-link img {
      width: 20px;
      height: 20px;
      margin-right: 8px;
    }

    /* Time-In and Time-Out Boxes */
    .container {
      display: flex;
      justify-content: center;
      gap: 30px;
      flex-wrap: wrap;
      margin-top: 80px;
      padding: 30px;
      z-index: 1;
    }

    .box {
      background: white;
      padding: 20px;
      border-radius: 15px;
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
      width: 300px;
      text-align: center;
      transition: transform 0.3s;
    }

    .box:hover {
      transform: translateY(-5px);
    }

    .box h2 {
      margin-bottom: 20px;
      font-size: 24px;
      color: #3498db;
    }

    .box img {
      width: 200px;
      height: 200px;
      margin-bottom: 20px;
      object-fit: contain;
    }

    .btn-time-in,
    .btn-time-out {
      display: inline-block;
      padding: 12px 20px;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      cursor: pointer;
      text-decoration: none;
      font-weight: bold;
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    .btn-time-in {
      background-color: #3498db;
    }

    .btn-time-in:hover {
      background-color: #2980b9;
    }

    .btn-time-out {
      background-color: #e74c3c;
    }

    .btn-time-out:hover {
      background-color: #c0392b;
    }

    @media (max-width: 768px) {
      .header-menu {
        flex-direction: column;
        align-items: center;
        padding: 15px;
      }

      .nav-links {
        flex-direction: column;
        margin-top: 20px;
      }

      .nav-link {
        margin: 10px 0;
      }

      .container {
        flex-direction: column;
        align-items: center;
        padding: 20px;
      }

      .box {
        width: 90%;
      }
    }
  </style>
</head>
<body>

  <!-- Header / Menu Bar -->
  <div class="header-menu">
    <h1>RFID Attendance System</h1>
    <div class="nav-links">
      <a class="nav-link" href="index.php"><img src="images/homm.png" alt="Home"> Home</a>
      <a class="nav-link" href="about.php"><img src="images/bout.png" alt="About"> About</a>
      <a class="nav-link" href="contact.php"><img src="images/contact.png" alt="Contact"> Contact</a>
      <a class="nav-link" href="time.php"><img src="images/time.png" alt="Time"> Time In / Out</a>
      <a class="nav-link" href="admin.php"><img src="images/admin.png" alt="Admin"> Admin</a>
    </div>
  </div>

  <!-- Main Container for Time-In and Time-Out -->
  <div class="container">
    <!-- Time-In Box -->
    <div class="box">
      <img src="images/in.png" alt="Time-In Icon">
      <h2>Time-In</h2>
      <a href="time_in.php" class="btn-time-in">Enter Time-In Attendance</a>
    </div>

    <!-- Time-Out Box -->
    <div class="box">
      <img src="images/out.png" alt="Time-Out Icon">
      <h2>Time-Out</h2>
      <a href="time_out.php" class="btn-time-out">Enter Time-Out Attendance</a>
    </div>
  </div>

</body>
</html>
