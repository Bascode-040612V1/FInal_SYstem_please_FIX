<?php
require_once '../config/database.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, "Only POST method is allowed");
}

// For now, we'll accept the admin ID from a header or POST parameter
// In production, you should implement proper authentication
$admin_id = $_POST['admin_id'] ?? $_GET['admin_id'] ?? null;

if (!$admin_id) {
    sendResponse(false, "Admin ID is required");
}

// Check if image file was uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    sendResponse(false, "No image file uploaded or upload failed");
}

$image = $_FILES['image'];

// Validate file type
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $image['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    sendResponse(false, "Invalid file type. Only JPG, PNG, and GIF images are allowed");
}

// Validate file size (max 5MB)
$max_size = 5 * 1024 * 1024; // 5MB in bytes
if ($image['size'] > $max_size) {
    sendResponse(false, "File size too large. Maximum allowed size is 5MB");
}

$database = new Database();
$rfidConn = $database->getRfidConnection();

if (!$rfidConn) {
    sendResponse(false, "Database connection failed");
}

try {
    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/admin/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_extension = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
    $filename = 'admin_' . $admin_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;
    $relative_path = 'uploads/admin/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($image['tmp_name'], $file_path)) {
        sendResponse(false, "Failed to save uploaded file");
    }
    
    // Update admin image in database
    $query = "UPDATE admins SET image = :image WHERE id = :admin_id";
    $stmt = $rfidConn->prepare($query);
    $stmt->bindParam(":image", $relative_path);
    $stmt->bindParam(":admin_id", $admin_id);
    
    if ($stmt->execute()) {
        // Get updated admin data
        $adminQuery = "SELECT id, username, email, image FROM admins WHERE id = :admin_id";
        $adminStmt = $rfidConn->prepare($adminQuery);
        $adminStmt->bindParam(":admin_id", $admin_id);
        $adminStmt->execute();
        
        if ($adminStmt->rowCount() > 0) {
            $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
            $admin['image_url'] = !empty($admin['image']) && $admin['image'] !== 'assets/default-profile.png' 
                ? '/violation_api/' . $admin['image'] 
                : null;
            
            sendResponse(true, "Profile image updated successfully", [
                'admin' => $admin,
                'image_filename' => $filename
            ]);
        } else {
            sendResponse(false, "Failed to retrieve updated admin data");
        }
    } else {
        // Remove uploaded file if database update failed
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        sendResponse(false, "Failed to update admin image in database");
    }
    
} catch (Exception $e) {
    // Remove uploaded file if error occurred
    if (isset($file_path) && file_exists($file_path)) {
        unlink($file_path);
    }
    
    error_log("Admin image upload error: " . $e->getMessage());
    sendResponse(false, "An error occurred while uploading the image");
}
?>