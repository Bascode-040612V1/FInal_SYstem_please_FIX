<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contact | RFID Attendance System</title>
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

    /* Contact Content */
    .main-content {
      flex-grow: 1;
      padding: 40px;
      overflow-y: auto;
      position: relative;
      z-index: 1;
      margin-top: 80px; /* Adjust space to account for header */
    }

    .contact-container {
      background: white;
      border-radius: 15px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.1);
      display: flex;
      max-width: 900px;
      overflow: hidden;
      flex-wrap: wrap;
      margin-left: auto;
      margin-right: auto;
    }

    .contact-image {
      flex: 1 1 300px;
      min-height: 300px;
    }

    .contact-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .contact-text {
      flex: 2 1 400px;
      padding: 30px;
      color: #333;
    }

    .contact-text h2 {
      margin-bottom: 20px;
      font-size: 28px;
      color: #2c3e50;
    }

    .contact-text p {
      line-height: 1.6;
      font-size: 16px;
      margin-bottom: 10px;
    }

    .contact-text ul {
      margin-bottom: 15px;
      padding-left: 20px;
    }

    @media (max-width: 768px) {
      .contact-container {
        flex-direction: column;
      }

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

  <!-- Main Content -->
  <div class="main-content">
    <!-- Contact Content -->
    <div class="contact-container">
      <div class="contact-image">
        <img src="images/devsyst.jpg" alt="Contact Us">
      </div>
      <div class="contact-text">
        <h2>Contact Us</h2>
        <p>If you have any questions or need support, feel free to reach out to us! You can contact us through the following ways:</p>    
        <ul>
          <li><strong>Email:</strong> duremdeslester4@gmail.com</li>
          <li><strong>Phone:</strong> +63 962 813 7889</li>
          <li><strong>Address:</strong> 05-A Manggahan St. Western Bicutan Taguig City</li>
        </ul>
        <p>We are here to help and look forward to hearing from you.</p>
      </div>
    </div>
  </div>

</body>
</html>
