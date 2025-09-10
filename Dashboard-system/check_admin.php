<?php
// check_admin.php - Check if admin RFID exists in admins table
require_once 'config.php';

header('Content-Type: application/json');

$response = ['status' => 'not_found'];

if (isset($_GET['rfid'])) {
    $rfid = sanitizeInput($_GET['rfid']);
    
    // Check if RFID exists in admins table
    $admin = fetchSingleResult($conn, "SELECT id, username, rfid FROM admins WHERE rfid = ?", [$rfid], "s");
    
    if ($admin) {
        $response = [
            'status' => 'found',
            'admin_id' => $admin['id'],
            'username' => $admin['username'],
            'rfid' => $admin['rfid']
        ];
        
        logActivity("Admin RFID check successful: {$admin['username']} - $rfid");
    } else {
        logActivity("Admin RFID check failed: $rfid not found in admins table");
    }
}

echo json_encode($response);
?>