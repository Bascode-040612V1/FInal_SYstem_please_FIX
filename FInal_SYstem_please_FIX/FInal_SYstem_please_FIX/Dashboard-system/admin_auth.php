<?php
// Start session to store admin status
session_start();

// Include database connection
include 'config.php';

// Step 1: RFID verification
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['rfid_number']) && !isset($_POST['password'])) {
        $rfid_number = trim($_POST['rfid_number']);
        
        // Check if RFID exists in admin table using prepared statement
        $stmt = $conn->prepare("SELECT id, username, rfid FROM admins WHERE rfid = ?");
        $stmt->bind_param("s", $rfid_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            $_SESSION['pending_admin_rfid'] = $rfid_number;
            $_SESSION['pending_admin_id'] = $admin['id'];
            $show_password_form = true;
        } else {
            $error_message = "Invalid RFID number. Please try again.";
            $_POST['rfid_number'] = '';
        }
        $stmt->close();
    }

    // Step 2: Password verification
    elseif (isset($_POST['password']) && isset($_SESSION['pending_admin_rfid'])) {
        $password = $_POST['password'];
        $admin_id = $_SESSION['pending_admin_id'];
        
        // Get admin password hash from database
        $stmt = $conn->prepare("SELECT password, username FROM admins WHERE id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            
            // Verify password using password_verify for hashed passwords
            // For backward compatibility, also check plain text (remove this after updating all passwords)
            if (password_verify($password, $admin['password']) || $password === $admin['password']) {
                $_SESSION['is_admin_authenticated'] = true;
                $_SESSION['admin_username'] = $admin['username'];
                unset($_SESSION['pending_admin_rfid']);
                unset($_SESSION['pending_admin_id']);
                header("Location: admin.php");
                exit();
            } else {
                $show_password_form = true;
                $error_message = "Incorrect password. Please try again.";
            }
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Authentication</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: url('images/room.jpg') no-repeat center center fixed;
            background-size: cover;
            padding: 40px 20px;
            text-align: center;
            color: black;
        }

        .auth-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            margin: auto;
        }

        .auth-container input {
            width: 80%;
            padding: 10px;
            margin: 10px 0;
            font-size: 16px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }

        .auth-container button {
            width: 85%;
            padding: 10px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }

        .auth-container button:hover {
            background-color: #2980b9;
        }

        .error-message {
            color: black;
            margin-top: 10px;
        }

        .top-bar {
            position: absolute;
            top: 20px;
            left: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .top-bar img {
            width: 20px;
            height: 20px;
        }

        .home-btn {
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

        .home-btn:hover {
            background: #ecf0f1;
        }
    </style>
</head>
<body>

<h1>Admin Authentication</h1>

<div class="auth-container">
    <?php if (!isset($show_password_form)) : ?>
        <h3>Please scan your RFID</h3>
        <form action="admin_auth.php" method="post">
            <!-- RFID input field -->
            <input type="text" id="rfid_input" name="rfid_number" placeholder="Enter RFID Number..." required autofocus autocomplete="off"
                value="<?php echo isset($_POST['rfid_number']) ? $_POST['rfid_number'] : ''; ?>">
            <button type="submit">Verify RFID</button>
        </form>
    <?php else: ?>
        <h3>Enter Password</h3>
        <form action="admin_auth.php" method="post">
            <input type="password" name="password" placeholder="Enter Password..." required>
            <button type="submit">Login</button>
        </form>
    <?php endif; ?>

    <?php if (isset($error_message)) : ?>
        <p class="error-message"><?= $error_message ?></p>
    <?php endif; ?>
</div>

<div class="top-bar">
    <a href="index.php" class="home-btn">
        <img src="images/return.png" alt="Home Icon">
        Home
    </a>
</div>

<script>
    // Focus RFID input if present
    const rfidField = document.getElementById('rfid_input');
    if (rfidField) {
        rfidField.focus();

        // Optionally auto-submit if RFID is 10 digits
        rfidField.addEventListener('input', function () {
            if (this.value.length === 10) {
                this.form.submit();
            }
        });
    }
</script>

</body>
</html>
