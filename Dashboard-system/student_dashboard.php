<?php
// student_dashboard.php
include 'config.php'; // DB connection
include 'performance_config.php';

// Input validation and sanitization
function validateInput($input, $type = 'string') {
    $input = trim($input);
    if ($type === 'string') {
        return filter_var($input, FILTER_SANITIZE_STRING);
    }
    return $input;
}

// Pagination settings
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Capture and validate filters
$year = isset($_GET['year']) ? validateInput($_GET['year']) : '';
$strand = isset($_GET['strand']) ? validateInput($_GET['strand']) : '';
$section = isset($_GET['section']) ? validateInput($_GET['section']) : '';
$search = isset($_GET['search']) ? validateInput($_GET['search']) : '';

// Create cache key based on filters
$cacheKey = 'students_' . md5($year . $strand . $section . $search . $page);
$cachedData = SimpleCache::get($cacheKey);

if ($cachedData === false) {
    // Get database connection from pool
    $pool = DatabasePool::getInstance();
    $conn = $pool->getConnection();
    
    // Build query using prepared statements for security
    $whereConditions = [];
    $params = [];
    $types = '';
    
    // Add conditions based on filters
    if (!empty($year)) {
        $whereConditions[] = "year_level = ?";
        $params[] = $year;
        $types .= 's';
    }
    if (!empty($strand)) {
        $whereConditions[] = "strand_course = ?";
        $params[] = $strand;
        $types .= 's';
    }
    if (!empty($section)) {
        $whereConditions[] = "section = ?";
        $params[] = $section;
        $types .= 's';
    }
    if (!empty($search)) {
        $whereConditions[] = "student_number LIKE ?";
        $params[] = '%' . $search . '%';
        $types .= 's';
    }
    
    // Build final query with pagination
    $whereClause = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";
    $query = "SELECT SQL_CACHE id, name, student_number, year_level, strand_course, section, image FROM students" . $whereClause . " ORDER BY name ASC LIMIT ? OFFSET ?";
    
    // Add pagination parameters
    $params[] = $perPage;
    $params[] = $offset;
    $types .= 'ii';
    
    // Execute prepared statement
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM students" . $whereClause;
    if (!empty($whereConditions)) {
        $countStmt = $conn->prepare($countQuery);
        $countParams = array_slice($params, 0, -2); // Remove LIMIT and OFFSET
        $countTypes = substr($types, 0, -2);
        if (!empty($countParams)) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalRecords = $countResult->fetch_assoc()['total'];
        $countStmt->close();
    } else {
        $totalRecords = $conn->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'];
    }
    
    $totalPages = ceil($totalRecords / $perPage);
    
    $cachedData = [
        'students' => $students,
        'totalPages' => $totalPages,
        'currentPage' => $page,
        'totalRecords' => $totalRecords
    ];
    
    // Cache for 10 minutes
    SimpleCache::set($cacheKey, $cachedData, 600);
    
    // Release connection back to pool
    $pool->releaseConnection($conn);
}

$students = $cachedData['students'];
$totalPages = $cachedData['totalPages'];
$totalRecords = $cachedData['totalRecords'];
?>﻿

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            background: url('images/room.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }

        .dashboard {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            margin: 40px auto;
            width: 95%;
            max-width: 1200px;
        }

        h1 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }

        .filters {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .filters select, .filters button, .filters input {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            cursor: pointer;
            font-size: 14px;
        }

        .filters input {
            width: 180px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            font-size: 14px;
        }

        th {
            background: #3498db;
            color: white;
        }

        tr:nth-child(even) {
            background: #f9f9f9;
        }

        .eye-open {
            color: green;
            font-weight: bold;
        }
        .eye-closed {
            color: red;
            font-weight: bold;
        }
        .penalty {
            color: #e74c3c;
            font-weight: bold;
        }

        .return-home-button {
            display: inline-block;
            margin-top: 20px;
            background: #e74c3c;
            color: #fff;
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }

        .return-home-button:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>

<div class="dashboard">
    <h1>Student Dashboard</h1>

    <!-- Filters & Search -->
    <form method="GET" class="filters">
        <select name="year" id="year">
            <option value="" disabled <?= $year==''?'selected':'' ?>>Year</option>
            <option <?= $year=="Grade 11"?'selected':'' ?>>Grade 11</option>
            <option <?= $year=="Grade 12"?'selected':'' ?>>Grade 12</option>
            <option <?= $year=="1st Year"?'selected':'' ?>>1st Year</option>
            <option <?= $year=="2nd Year"?'selected':'' ?>>2nd Year</option>
            <option <?= $year=="3rd Year"?'selected':'' ?>>3rd Year</option>
            <option <?= $year=="4th Year"?'selected':'' ?>>4th Year</option>
        </select>

        <select name="strand" id="strand">
            <option value="" disabled <?= $strand==''?'selected':'' ?>>Strand / Course</option>
            <option <?= $strand=="ICT"?'selected':'' ?>>ICT</option>
            <option <?= $strand=="BSCS"?'selected':'' ?>>BSCS</option>
            <option <?= $strand=="BSEntrep"?'selected':'' ?>>BSEntrep</option>
        </select>

        <select name="section" id="section">
            <option value="" disabled <?= $section==''?'selected':'' ?>>Section</option>
        </select>

        <!-- Search Input -->
        <input type="text" name="search" placeholder="Search Student Number" value="<?= htmlspecialchars($search) ?>">

        <button type="submit">Apply Filters</button>
        <button type="button" id="resetFilters" style="padding:8px 12px; border-radius:8px; border:1px solid #ccc; background:#f1f1f1; cursor:pointer;">Reset</button>
        
        <!-- Export Buttons -->
        <button type="button" onclick="exportViolations('summary')" style="padding:8px 12px; border-radius:8px; border:none; background:#27ae60; color:white; cursor:pointer; margin-left:10px;">📊 Export Violation Summary</button>
        <button type="button" onclick="exportViolations('detailed')" style="padding:8px 12px; border-radius:8px; border:none; background:#e67e22; color:white; cursor:pointer;">📋 Export Detailed Violations</button>
    </form>

    <!-- Student Table -->
    <table>
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Student Number</th>
                <th>Year</th>
                <th>Strand / Course</th>
                <th>Section</th>
                <th colspan="2">Violations</th>
                <th>Offense Number</th>
                <th>Acknowledgement</th>
                <th>Penalty</th>
            </tr>
            <tr>
                <th colspan="5"></th>
                <th>Conduct Violations</th>
                <th>Dress Code Violations</th>
                <th colspan="3"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($students)): ?>
                <?php foreach($students as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['student_number']) ?></td>
                        <td><?= htmlspecialchars($row['year_level']) ?></td>
                        <td><?= htmlspecialchars($row['strand_course']) ?></td>
                        <td><?= htmlspecialchars($row['section']) ?></td>
                        <td>0</td>
                        <td>0</td>
                        <td>0</td>
                        <td class="eye-closed">🙈 Not Seen</td>
                        <td class="penalty">None</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10">No students found with the selected filters.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
    <div style="text-align: center; margin: 20px 0;">
        <div style="display: inline-block; background: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <span style="margin-right: 10px;">Page <?= $page ?> of <?= $totalPages ?> (<?= $totalRecords ?> total students)</span>
            
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                   style="padding: 5px 10px; margin: 0 2px; background: #3498db; color: white; text-decoration: none; border-radius: 4px;">Previous</a>
            <?php endif; ?>
            
            <?php 
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++): 
            ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                   style="padding: 5px 10px; margin: 0 2px; background: <?= $i == $page ? '#2c3e50' : '#3498db' ?>; color: white; text-decoration: none; border-radius: 4px;"><?= $i ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                   style="padding: 5px 10px; margin: 0 2px; background: #3498db; color: white; text-decoration: none; border-radius: 4px;">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div style="text-align:center;">
        <a href="admin.php" class="return-home-button">Return Home</a>
    </div>
</div>

<script>
// Sections mapping
const sections = {
    "ICT": {
        "Grade 11": ["IC1MA"],
        "Grade 12": ["IC2MA"]
    },
    "BSCS": {
        "1st Year": ["BS1MA","BS2MA","BS1AA","BS2AA","BS1EA","BS2EA"],
        "2nd Year": ["BS3MA","BS4MA","BS3AA","BS4AA","BS3EA","BS4EA"],
        "3rd Year": ["BS5MA","BS6MA","BS5AA","BS6AA","BS5EA","BS6EA"],
        "4th Year": ["BS7MA","BS8MA","BS7AA","BS8AA","BS7EA","BS8EA"]
    },
    "BSEntrep": {
        "1st Year": ["BN1MA","BN2MA","BN1AA","BN2AA","BN1EA","BN2EA"],
        "2nd Year": ["BN3MA","BN4MA","BN3AA","BN4AA","BN3EA","BN4EA"],
        "3rd Year": ["BN5MA","BN6MA","BN5AA","BN6AA","BN5EA","BN6EA"],
        "4th Year": ["BN7MA","BN8MA","BN7AA","BN8AA","BN7EA","BN8EA"]
    }
};

function updateSections() {
    const year = document.getElementById("year").value;
    const strand = document.getElementById("strand").value;
    const sectionSelect = document.getElementById("section");

    sectionSelect.innerHTML = '<option value="" disabled selected>Section</option>';

    if (sections[strand] && sections[strand][year]) {
        sections[strand][year].forEach(sec => {
            const opt = document.createElement("option");
            opt.value = sec;
            opt.textContent = sec;
            <?php if ($section): ?>
                if (sec === "<?= $section ?>") opt.selected = true;
            <?php endif; ?>
            sectionSelect.appendChild(opt);
        });
    }
}

// Run once on page load
updateSections();

// Update when year/strand changes
document.getElementById("year").addEventListener("change", updateSections);
document.getElementById("strand").addEventListener("change", updateSections);

// Reset button functionality
document.getElementById('resetFilters').addEventListener('click', function() {
    document.getElementById('year').selectedIndex = 0;
    document.getElementById('strand').selectedIndex = 0;
    document.getElementById('section').selectedIndex = 0;
    window.location.href = 'student_dashboard.php';
});

// Export functions
function exportViolations(type) {
    // Get current filters to include in export
    const urlParams = new URLSearchParams(window.location.search);
    const year = urlParams.get('year') || '';
    const strand = urlParams.get('strand') || '';
    const section = urlParams.get('section') || '';
    const search = urlParams.get('search') || '';
    
    // Build export URL
    let exportUrl = 'export_violations.php?type=' + type;
    
    // Add filters as parameters for future enhancement
    if (year) exportUrl += '&year=' + encodeURIComponent(year);
    if (strand) exportUrl += '&strand=' + encodeURIComponent(strand);
    if (section) exportUrl += '&section=' + encodeURIComponent(section);
    if (search) exportUrl += '&search=' + encodeURIComponent(search);
    
    // Open export in new window/tab for download
    window.open(exportUrl, '_blank');
}
</script>

</body>
</html>

