<?php
/**
 * Student Profile Image Upload API
 * Handles uploading and managing student profile images
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, multipart/form-data');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../config/database.php';

function uploadStudentImage($student_id, $uploaded_file) {
    $database = new Database();
    
    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/student_profiles/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    try {
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        $file_type = $uploaded_file['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            return [
                'success' => false,
                'message' => 'Invalid file type. Only JPEG, PNG, JPG, and GIF files are allowed.'
            ];
        }
        
        // Validate file size (max 5MB)
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if ($uploaded_file['size'] > $max_size) {
            return [
                'success' => false,
                'message' => 'File size too large. Maximum size is 5MB.'
            ];
        }
        
        // Generate unique filename
        $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
        $filename = $student_id . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        $db_path = 'uploads/student_profiles/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
            return [
                'success' => false,
                'message' => 'Failed to upload file.'
            ];
        }
        
        // Update both databases
        $queries = [
            "UPDATE student_violation_db.students SET image = ? WHERE student_id = ?",
            "UPDATE rfid_system.students SET image = ? WHERE student_number = ?"
        ];
        
        foreach ($queries as $query) {
            $stmt = $database->getConnection()->prepare($query);
            $stmt->execute([$db_path, $student_id]);
        }
        
        return [
            'success' => true,
            'message' => 'Image uploaded successfully.',
            'image_path' => $db_path,
            'filename' => $filename
        ];
        
    } catch (Exception $e) {
        error_log("Image upload error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Server error during image upload.'
        ];
    }
}

function getStudentImage($student_id) {
    $database = new Database();
    
    try {
        $query = "SELECT image FROM student_violation_db.students WHERE student_id = ?";
        $stmt = $database->getConnection()->prepare($query);
        $stmt->execute([$student_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'success' => true,
                'image_path' => $result['image']
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Student not found.'
            ];
        }
        
    } catch (Exception $e) {
        error_log("Get image error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Server error retrieving image.'
        ];
    }
}

// Handle requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Upload image
    if (!isset($_POST['student_id']) || !isset($_FILES['image'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters: student_id and image file.'
        ]);
        exit();
    }
    
    $student_id = $_POST['student_id'];
    $uploaded_file = $_FILES['image'];
    
    $result = uploadStudentImage($student_id, $uploaded_file);
    echo json_encode($result);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get image path
    if (!isset($_GET['student_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameter: student_id.'
        ]);
        exit();
    }
    
    $student_id = $_GET['student_id'];
    $result = getStudentImage($student_id);
    echo json_encode($result);
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
}
?>