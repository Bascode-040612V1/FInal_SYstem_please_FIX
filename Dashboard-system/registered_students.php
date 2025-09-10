<?php
// Start the session
session_start();

// Check if admin is authenticated
if (!isset($_SESSION['is_admin_authenticated']) || $_SESSION['is_admin_authenticated'] !== true) {
    header("Location: admin_auth.php"); // Redirect to authentication page if not authenticated
    exit();
}

// Connect to the database
include 'config.php';

$message = '';

// Handle update request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $student_id = (int)$_POST['student_id'];
    $name = trim($_POST['name']);
    $student_number = trim($_POST['student_number']);
    $rfid = trim($_POST['rfid']);

    $image_path = $_POST['current_image']; // default to existing image

    if (isset($_FILES['new_image']) && $_FILES['new_image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true); // Create directory if it doesn't exist
        }
        $target_file = $target_dir . basename($_FILES["new_image"]["name"]);
        if (move_uploaded_file($_FILES["new_image"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
        }
    }

    // Use prepared statement for update
    $update_stmt = $conn->prepare("UPDATE students SET name=?, student_number=?, rfid=?, image=? WHERE id=?");
    $update_stmt->bind_param("ssssi", $name, $student_number, $rfid, $image_path, $student_id);

    if ($update_stmt->execute()) {
        $message = "Student updated successfully!";
    } else {
        $message = "Error updating record: " . $update_stmt->error;
    }
    $update_stmt->close();
}

// Handle delete request
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $delete_stmt = $conn->prepare("DELETE FROM students WHERE id=?");
    $delete_stmt->bind_param("i", $delete_id);

    if ($delete_stmt->execute()) {
        $message = "Student deleted successfully!";
    } else {
        $message = "Error deleting record: " . $delete_stmt->error;
    }
    $delete_stmt->close();
}

// Fetch all students from the database
$sql = "SELECT * FROM students ORDER BY name ASC";
$result = $conn->query($sql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registered Students</title>
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

        .container {
            background: rgba(255, 255, 255, 0.8);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 80%;
            max-width: 1000px;
            text-align: center;
        }

        .container h1 {
            margin-bottom: 30px;
            font-size: 28px;
            color: #333;
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

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th, .table td {
            padding: 12px;
            border: 1px solid #ddd;
        }

        .table th {
            background-color: #3498db;
            color: white;
        }

        .table td img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
        }

        .edit-btn, .delete-btn {
            padding: 10px 20px;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin: 5px;
            font-size: 16px;
            display: inline-block;
            text-align: center;
            width: 120px;
            box-sizing: border-box;
        }

        .edit-btn {
            background-color: #3498db;
        }

        .edit-btn:hover {
            background-color: #2980b9;
        }

        .delete-btn {
            background-color: red;
            text-decoration: none;
        }

        .delete-btn:hover {
            background-color: darkred;
        }

        .success-message {
            margin-bottom: 20px;
            color: green;
        }

        .edit-form {
            display: none;
            margin-top: 10px;
            padding: 20px;
            background-color: #f1f1f1;
            border-radius: 10px;
            width: 100%; 
            max-width: 350px;
            margin-left: auto;
            margin-right: auto;
            box-sizing: border-box;
        }

        .edit-form input[type="text"],
        .edit-form input[type="file"] {
            margin-top: 10px;
            margin-bottom: 15px;
            width: 100%; 
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            box-sizing: border-box;
        }

        .edit-form button[type="submit"] {
            margin-top: 10px;
        }

        /* Zoom modal styling */
        .zoom-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .zoom-modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
        }

        .zoom-modal .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 30px;
            color: white;
            cursor: pointer;
        }
    </style>
</head>
<body>

<!-- Top Bar with Home Button -->
<div class="top-bar">
    <a href="admin.php" class="home-btn" id="returnBtn">
        <img src="images/return.png" alt="Home Icon">
        Return
    </a>
</div>

<div class="container">
    <h1>Registered Students</h1>

    <!-- Display success message -->
    <?php if (isset($message)): ?>
        <div class="success-message"><?php echo $message; ?></div>
    <?php endif; ?>

    <table class="table">
        <thead>
        <tr>
            <th>Image</th>
            <th>Name</th>
            <th>Student Number</th>
            <th>RFID</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><img class="profile-img" src="<?php echo (!empty($row['image']) && file_exists($row['image'])) ? $row['image'] : 'images/pfp.jpg'; ?>" alt="Profile Picture" onclick="zoomImage(this)"></td>
                    <td><?php echo $row['name']; ?></td>
                    <td><?php echo $row['student_number']; ?></td>
                    <td><?php echo $row['rfid']; ?></td>
                    <td>
                        <button class="edit-btn" id="edit-btn-<?php echo $row['id']; ?>" onclick="toggleEditForm(<?php echo $row['id']; ?>)">Edit</button>
                        <a href="registered_students.php?delete_id=<?php echo $row['id']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this student?')">Delete</a>

                        <div id="edit-form-<?php echo $row['id']; ?>" class="edit-form">
                            <form action="registered_students.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="student_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="current_image" value="<?php echo $row['image']; ?>">
                                <input type="text" name="name" value="<?php echo $row['name']; ?>" required>
                                <input type="text" name="student_number" value="<?php echo $row['student_number']; ?>" required>
                                <input type="text" name="rfid" value="<?php echo $row['rfid']; ?>" required>
                                <label>Change Picture:</label>
                                <input type="file" name="new_image" accept="image/*">
                                <button type="submit" name="update" class="edit-btn">Update</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5">No students found</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Zoom Modal -->
<div id="zoom-modal" class="zoom-modal">
    <span class="close-btn" onclick="closeZoom()">X</span>
    <img id="zoom-img" src="" alt="Zoomed Image">
</div>

<script>
    function toggleEditForm(studentId) {
        var form = document.getElementById('edit-form-' + studentId);
        var button = document.getElementById('edit-btn-' + studentId);

        if (form.style.display === 'none' || form.style.display === '') {
            form.style.display = 'block';
            button.textContent = 'Cancel';
        } else {
            form.style.display = 'none';
            button.textContent = 'Edit';
        }
    }

    // Function to zoom the image
    function zoomImage(img) {
        var zoomModal = document.getElementById('zoom-modal');
        var zoomImg = document.getElementById('zoom-img');
        zoomImg.src = img.src;  // Set zoomed image source
        zoomModal.style.display = "flex";  // Show modal
    }

    // Function to close the zoom modal
    function closeZoom() {
        var zoomModal = document.getElementById('zoom-modal');
        zoomModal.style.display = "none";  // Hide modal
    }
</script>

</body>
</html>
