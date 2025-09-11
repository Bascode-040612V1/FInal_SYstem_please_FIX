<?php
/**
 * Setup script to create necessary upload directories and verify permissions
 */

$base_dir = dirname(__FILE__);
$upload_dirs = [
    $base_dir . '/uploads',
    $base_dir . '/uploads/admin',
    $base_dir . '/uploads/students',
    $base_dir . '/uploads/temp'
];

echo "Setting up upload directories...\n";

foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✓ Created directory: $dir\n";
        } else {
            echo "✗ Failed to create directory: $dir\n";
        }
    } else {
        echo "✓ Directory already exists: $dir\n";
    }
    
    // Check if directory is writable
    if (is_writable($dir)) {
        echo "✓ Directory is writable: $dir\n";
    } else {
        echo "✗ Directory is not writable: $dir\n";
        // Try to fix permissions
        if (chmod($dir, 0755)) {
            echo "✓ Fixed permissions for: $dir\n";
        } else {
            echo "✗ Failed to fix permissions for: $dir\n";
        }
    }
}

// Create .htaccess file to protect uploads directory
$htaccess_content = "# Protect uploads directory
Options -Indexes
<Files ~ \"\\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$\">
    Order allow,deny
    Deny from all
</Files>

# Allow only image files
<FilesMatch \"\\.(jpg|jpeg|png|gif|bmp|webp)$\">
    Order allow,deny
    Allow from all
</FilesMatch>";

$htaccess_path = $base_dir . '/uploads/.htaccess';
if (file_put_contents($htaccess_path, $htaccess_content)) {
    echo "✓ Created .htaccess file for uploads security\n";
} else {
    echo "✗ Failed to create .htaccess file\n";
}

echo "\nSetup completed!\n";
echo "Make sure your web server has read/write permissions to the uploads directory.\n";
echo "For production, consider using a separate subdomain or CDN for serving uploaded files.\n";
?>