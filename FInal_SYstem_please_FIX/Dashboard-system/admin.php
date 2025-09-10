<?php
// Start session to check if admin is authenticated
session_start();

// Check if admin is authenticated
if (!isset($_SESSION['is_admin_authenticated']) || $_SESSION['is_admin_authenticated'] !== true) {
    header("Location: admin_auth.php"); // Redirect to authentication page if not authenticated
    exit();
}

// Sample admin data (replace with session or DB values as needed)
$admin_name = "Lester Sam Duremdes";
$admin_role = "Admin"; // or "Teacher"
$admin_rfid = "3870770196";
$admin_image = "images/sam (2).jpg"; // Path to admin image

// Clear session and redirect to the index page when returning home
if (isset($_GET['home'])) {
    session_unset(); 
    session_destroy(); 
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            background: url('images/room.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .dashboard {
            background: rgba(255, 255, 255, 0.9);
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 500px;
            text-align: center;
        }

        .profile-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #3498db;
        }

        .admin-name {
            font-size: 24px;
            font-weight: bold;
            margin-top: 15px;
            color: #333;
        }

        .admin-role {
            font-size: 16px;
            color: #555;
        }

        .rfid-number {
            font-size: 14px;
            color: #888;
            margin-bottom: 25px;
        }

        .dashboard a {
            display: block;
            margin: 15px auto;
            padding: 14px 20px;
            font-size: 16px;
            color: #fff;
            background-color: #3498db;
            text-decoration: none;
            border-radius: 10px;
            transition: background-color 0.3s, transform 0.2s;
            font-weight: bold;
            max-width: 300px;
        }

        .dashboard a:hover {
            background-color: #2980b9;
            transform: scale(1.03);
        }

        .return-home-button {
            margin-top: 30px;
            background-color: #e74c3c;
        }

        .return-home-button:hover {
            background-color: #c0392b;
        }
    </style>
</head>
<body>

<div class="dashboard">
    <!-- Profile Section -->
    <img src="<?php echo $admin_image; ?>" alt="Admin Image" class="profile-img">
    <div class="admin-name"><?php echo $admin_name; ?></div>
    <div class="admin-role"><?php echo $admin_role; ?></div>
    <div class="rfid-number">RFID: <?php echo $admin_rfid; ?></div>

    <!-- Action Boxes -->
    <a href="register.php">📋 Register a Student</a>
    <a href="admin_register.php">🏷️ Register Admin RFID</a>
    <a href="attendance.php">📊 View Attendance</a>
    <a href="registered_students.php">👥 View Registered Students</a>
    <a href="student_dashboard.php">⚠️ Student Violations Dashboard</a>
    <a href="admin.php?home=true" class="return-home-button">🏠 Return Home</a>
</div>

</body>
</html>
