<?php
require_once '../config/database.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, "Only GET method is allowed");
}

// For now, we'll accept the admin ID from a header or GET parameter
// In production, you should implement proper authentication
$admin_id = $_GET['admin_id'] ?? null;

if (!$admin_id) {
    sendResponse(false, "Admin ID is required");
}

$database = new Database();
$rfidConn = $database->getRfidConnection();

if (!$rfidConn) {
    sendResponse(false, "Database connection failed");
}

try {
    // Get admin profile data
    $query = "SELECT id, username, email, image, created_at FROM admins WHERE id = :admin_id";
    $stmt = $rfidConn->prepare($query);
    $stmt->bindParam(":admin_id", $admin_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Construct full image URL if image exists and is not default
        if (!empty($admin['image']) && $admin['image'] !== 'assets/default-profile.png') {
            $admin['image_url'] = '/violation_api/' . $admin['image'];
        } else {
            $admin['image_url'] = null;
        }
        
        sendResponse(true, "Admin profile retrieved successfully", [
            'admin' => $admin
        ]);
    } else {
        sendResponse(false, "Admin profile not found");
    }
    
} catch (Exception $e) {
    error_log("Get admin profile error: " . $e->getMessage());
    sendResponse(false, "An error occurred while retrieving admin profile");
}
?>