<?php
// This file is deprecated - use config.php instead
// Redirecting to maintain backward compatibility
require_once 'config.php';

// Legacy variables for backward compatibility
$host = DB_HOST;
$user = DB_USER;
$password = DB_PASS;
$database = DB_NAME;
$mysqli = $conn;

// Note: Please update your code to use config.php directly
// This file will be removed in future versions
?>
