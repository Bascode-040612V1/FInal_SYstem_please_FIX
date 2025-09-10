<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Export Test Page</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 40px;
            background: #f4f4f4;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .export-section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        button {
            padding: 12px 20px;
            margin: 5px;
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-blue { background: #3498db; }
        .btn-green { background: #27ae60; }
        .btn-orange { background: #e67e22; }
        .btn-purple { background: #9b59b6; }
    </style>
</head>
<body>

<div class="container">
    <h1>📊 Export Functionality Test Page</h1>
    
    <div class="export-section">
        <h3>🚨 Violation Reports</h3>
        <p>Export student violation data to Excel format:</p>
        <button class="btn-green" onclick="window.open('export_violations.php?type=summary', '_blank')">
            📊 Export Violation Summary
        </button>
        <button class="btn-orange" onclick="window.open('export_violations.php?type=detailed', '_blank')">
            📋 Export Detailed Violations
        </button>
    </div>
    
    <div class="export-section">
        <h3>📅 Attendance Reports</h3>
        <p>Export attendance data to Excel format:</p>
        <button class="btn-blue" onclick="window.open('export_attendance.php?type=current&date=<?php echo date('Y-m-d'); ?>&include_absentees=yes', '_blank')">
            📊 Export Current Day Attendance
        </button>
        <button class="btn-green" onclick="window.open('export_attendance.php?type=saved&date=<?php echo date('Y-m-d'); ?>&include_absentees=yes', '_blank')">
            📋 Export Saved Attendance
        </button>
        <button class="btn-orange" onclick="window.open('export_attendance.php?type=summary&include_absentees=yes', '_blank')">
            📈 Export Attendance Summary
        </button>
    </div>
    
    <div class="export-section">
        <h3>🔗 Navigation</h3>
        <p>Go to main application pages:</p>
        <button class="btn-purple" onclick="window.location.href='admin.php'">
            🏠 Admin Dashboard
        </button>
        <button class="btn-blue" onclick="window.location.href='student_dashboard.php'">
            👥 Student Dashboard
        </button>
        <button class="btn-green" onclick="window.location.href='attendance.php'">
            📊 Attendance Page
        </button>
    </div>
    
    <div class="export-section">
        <h3>📝 Instructions</h3>
        <ol>
            <li>Click any export button to download the corresponding Excel file</li>
            <li>The files will be downloaded as .xls format (Excel compatible)</li>
            <li>Violation exports include student details, violation types, and penalties</li>
            <li>Attendance exports include time in/out data and absent students</li>
            <li>Summary reports provide overview statistics</li>
        </ol>
    </div>
</div>

</body>
</html>