<?php
require_once '../config/database.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, "Only GET method is allowed");
}

try {
    // Define role requirements
    $role_requirements = [
        'admin' => [
            'rfid_available' => true,
            'rfid_required' => false,
            'description' => 'Administrator with optional RFID access'
        ],
        'teacher' => [
            'rfid_available' => true,
            'rfid_required' => false,
            'description' => 'Teacher with optional RFID access'
        ],
        'guard' => [
            'rfid_available' => false,
            'rfid_required' => false,
            'description' => 'Security guard without RFID access'
        ]
    ];
    
    sendResponse(true, "Role requirements retrieved successfully", $role_requirements);
    
} catch(Exception $exception) {
    error_log("Role requirements error: " . $exception->getMessage());
    sendResponse(false, "Failed to retrieve role requirements");
}
?>