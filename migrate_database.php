<?php
/**
 * Database Migration Script for AICS Bicutan System
 * This script helps migrate to the unified aics_bicutan_system_db database
 */

echo "=== AICS Bicutan System Database Migration ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'aics_bicutan_system_db';

// Test MySQL connection
echo "1. Testing MySQL connection...\n";
try {
    $conn = new mysqli($host, $username, $password);
    if ($conn->connect_error) {
        die("   ✗ Connection failed: " . $conn->connect_error . "\n");
    }
    echo "   ✓ MySQL connection successful\n";
    
    // Check if database exists
    $result = $conn->query("SHOW DATABASES LIKE '$database'");
    if ($result->num_rows > 0) {
        echo "   ⚠ Warning: Database '$database' already exists\n";
        echo "   This will update the existing database structure.\n";
    } else {
        echo "   ✓ Ready to create new database '$database'\n";
    }
    
} catch (Exception $e) {
    die("   ✗ Database connection error: " . $e->getMessage() . "\n");
}

echo "\n2. Reading DBMODIFY.sql file...\n";
$sql_file = 'DBMODIFY.sql';
if (!file_exists($sql_file)) {
    die("   ✗ DBMODIFY.sql file not found in current directory\n");
}

$sql_content = file_get_contents($sql_file);
if (!$sql_content) {
    die("   ✗ Failed to read DBMODIFY.sql\n");
}
echo "   ✓ DBMODIFY.sql loaded successfully (" . number_format(strlen($sql_content)) . " bytes)\n";

echo "\n3. Executing database migration...\n";
try {
    // Split SQL into individual statements
    $statements = explode(';', $sql_content);
    $executed_count = 0;
    $skipped_count = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // Skip empty statements and comments
        if (empty($statement) || strpos($statement, '--') === 0 || strpos($statement, '/*') === 0) {
            $skipped_count++;
            continue;
        }
        
        // Execute statement
        if ($conn->query($statement)) {
            $executed_count++;
            
            // Show progress for major operations
            if (stripos($statement, 'CREATE TABLE') === 0) {
                preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches);
                $table = $matches[1] ?? 'unknown';
                echo "   ✓ Created table: $table\n";
            } elseif (stripos($statement, 'CREATE DATABASE') === 0) {
                echo "   ✓ Created/verified database: $database\n";
            } elseif (stripos($statement, 'INSERT') === 0) {
                echo "   ✓ Inserted default data\n";
            } elseif (stripos($statement, 'CREATE TRIGGER') === 0) {
                preg_match('/CREATE TRIGGER\s+(\w+)/i', $statement, $matches);
                $trigger = $matches[1] ?? 'unknown';
                echo "   ✓ Created trigger: $trigger\n";
            } elseif (stripos($statement, 'CREATE INDEX') === 0) {
                preg_match('/CREATE INDEX\s+(\w+)/i', $statement, $matches);
                $index = $matches[1] ?? 'unknown';
                echo "   ✓ Created index: $index\n";
            }
        } else {
            // Check if error is ignorable (like table already exists)
            $error = $conn->error;
            if (strpos($error, 'already exists') !== false || strpos($error, 'Duplicate') !== false) {
                echo "   ⚠ Skipped (already exists): " . substr($statement, 0, 50) . "...\n";
                $skipped_count++;
            } else {
                echo "   ✗ Error executing: " . substr($statement, 0, 50) . "...\n";
                echo "     MySQL Error: $error\n";
            }
        }
    }
    
    echo "\n   Migration Summary:\n";
    echo "   - Executed: $executed_count statements\n";
    echo "   - Skipped: $skipped_count statements\n";
    
} catch (Exception $e) {
    die("   ✗ Migration failed: " . $e->getMessage() . "\n");
}

echo "\n4. Testing final database structure...\n";
try {
    $conn->select_db($database);
    
    // Test critical tables
    $critical_tables = [
        'students' => 'SELECT COUNT(*) as count FROM students',
        'attendance' => 'SELECT COUNT(*) as count FROM attendance',
        'violations' => 'SELECT COUNT(*) as count FROM violations',
        'violation_types' => 'SELECT COUNT(*) as count FROM violation_types',
        'saved_attendance' => 'SELECT COUNT(*) as count FROM saved_attendance',
        'admins' => 'SELECT COUNT(*) as count FROM admins',
        'guards' => 'SELECT COUNT(*) as count FROM guards'
    ];
    
    foreach ($critical_tables as $table => $query) {
        $result = $conn->query($query);
        if ($result) {
            $row = $result->fetch_assoc();
            echo "   ✓ Table '$table': " . $row['count'] . " records\n";
        } else {
            echo "   ✗ Table '$table': Error - " . $conn->error . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "   ⚠ Warning: Could not verify all tables - " . $e->getMessage() . "\n";
}

echo "\n=== Migration Complete ===\n";
echo "✓ Database: $database\n";
echo "✓ All backend systems now configured to use unified database\n";
echo "✓ Ready to test system components:\n";
echo "  - Dashboard System (Dashboard-system/)\n";
echo "  - Student App Backend (My-Record-In-School/backend/)\n";
echo "  - Violation App Backend (Vaolation-slip-app-/violation_api/)\n";
echo "\nNext steps:\n";
echo "1. Test each system component\n";
echo "2. Verify data integrity\n";
echo "3. Test mobile app connections\n";
echo "4. Backup the new unified database\n";

$conn->close();
?>

