<?php
// Password hash generator for admin accounts
// Run this script to generate secure password hashes

echo "=== Admin Password Hash Generator ===\n\n";

// Generate hash for 'admin123'
$password1 = 'admin123';
$hash1 = password_hash($password1, PASSWORD_DEFAULT);

echo "Password: admin123\n";
echo "Hash: " . $hash1 . "\n\n";

// Generate hash for any custom password
$password2 = 'secure_password_2024';
$hash2 = password_hash($password2, PASSWORD_DEFAULT);

echo "Password: secure_password_2024\n";
echo "Hash: " . $hash2 . "\n\n";

echo "Copy these hashes and update your admin table using the SQL commands:\n\n";

echo "UPDATE admins SET password = '$hash1' WHERE username = 'ajJ';\n";
echo "UPDATE admins SET password = '$hash2' WHERE username = 'Guard';\n\n";

echo "Remember to run update_admin_table.sql first to add the RFID column!\n";
?>