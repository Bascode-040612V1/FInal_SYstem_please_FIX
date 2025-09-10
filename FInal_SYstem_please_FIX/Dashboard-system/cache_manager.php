<?php
// cache_manager.php - Cache management utility

include 'config.php';
include 'performance_config.php';

// Check if admin is authenticated
session_start();
if (!isset($_SESSION['is_admin_authenticated']) || $_SESSION['is_admin_authenticated'] !== true) {
    header("Location: admin_auth.php");
    exit();
}

$action = $_GET['action'] ?? '';
$message = '';

if ($action === 'clear_all') {
    SimpleCache::clear();
    $message = "All cache cleared successfully!";
} elseif ($action === 'clear_students') {
    // Clear student-related caches
    $cacheDir = 'cache/';
    $files = glob($cacheDir . '*.cache');
    $cleared = 0;
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if (strpos($content, 'students') !== false || strpos($content, 'attendance') !== false) {
            unlink($file);
            $cleared++;
        }
    }
    $message = "Cleared $cleared student/attendance cache files!";
} elseif ($action === 'view_stats') {
    // Get cache statistics
    $cacheDir = 'cache/';
    $files = glob($cacheDir . '*.cache');
    $totalFiles = count($files);
    $totalSize = 0;
    $expired = 0;
    
    foreach ($files as $file) {
        $totalSize += filesize($file);
        $data = unserialize(file_get_contents($file));
        if ($data['expires'] < time()) {
            $expired++;
        }
    }
    
    $totalSize = round($totalSize / 1024, 2); // KB
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cache Manager</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 20px;
            background: url('images/room.jpg') no-repeat center center fixed;
            background-size: cover;
        }
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .stats {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Cache Manager</h1>
    
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php if ($action === 'view_stats'): ?>
        <div class="stats">
            <h3>Cache Statistics</h3>
            <p><strong>Total Cache Files:</strong> <?= $totalFiles ?></p>
            <p><strong>Total Cache Size:</strong> <?= $totalSize ?> KB</p>
            <p><strong>Expired Files:</strong> <?= $expired ?></p>
        </div>
    <?php endif; ?>
    
    <div>
        <a href="?action=view_stats" class="btn">View Cache Stats</a>
        <a href="?action=clear_students" class="btn">Clear Student Cache</a>
        <a href="?action=clear_all" class="btn btn-danger" onclick="return confirm('Are you sure you want to clear all cache?')">Clear All Cache</a>
    </div>
    
    <div style="margin-top: 30px;">
        <h3>Cache Information</h3>
        <p>The system uses file-based caching to improve performance:</p>
        <ul>
            <li><strong>Student Data:</strong> Cached for 10 minutes</li>
            <li><strong>Attendance Records:</strong> Cached for 1-5 minutes</li>
            <li><strong>Top Students:</strong> Cached for 1 hour</li>
            <li><strong>Historical Data:</strong> Cached for 1 hour</li>
        </ul>
        
        <p><strong>Recommendation:</strong> Clear cache when you notice outdated data or after making significant changes to student records.</p>
    </div>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="admin.php" class="btn">Back to Admin Dashboard</a>
    </div>
</div>

</body>
</html>