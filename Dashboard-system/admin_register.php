<?php
// Simplified Admin RFID Registration - Only RFID required
require_once 'config.php';

// Require admin authentication to access this page
requireAdminAuth();

$response = ['status' => 'error', 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $rfid = sanitizeInput($_POST['rfid'] ?? '');

    // Validate RFID (exactly 10 digits)
    if (!validateRFID($rfid)) {
        $response['message'] = "RFID must be exactly 10 digits.";
    } else {
        // Check if RFID already exists in rfid_admin_scans
        $existing_scan = fetchSingleResult($conn, "SELECT id FROM rfid_admin_scans WHERE rfid_number = ?", [$rfid], "s");
        
        if ($existing_scan) {
            $response['message'] = "RFID already registered.";
        } else {
            // Insert into rfid_admin_scans table
            $stmt = executeQuery($conn, "INSERT INTO rfid_admin_scans (rfid_number) VALUES (?)", [$rfid], "s");
            
            if ($stmt) {
                $response['status'] = 'success';
                $response['message'] = 'RFID scan recorded. Please check your app for admin registration.';
                logActivity("Admin RFID scanned for registration: $rfid");
            } else {
                $response['message'] = "Registration failed. Please try again.";
            }
        }
    }
    
    // Return JSON response for AJAX requests
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RFID Admin Registration</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: url('images/room.jpg') no-repeat center center fixed;
            background-size: cover;
            padding: 50px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            position: relative;
        }

        .top-bar {
            position: absolute;
            top: 20px;
            left: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .home-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: white;
            color: #3498db;
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: bold;
            text-decoration: none;
            transition: background 0.3s;
        }

        .home-btn:hover {
            background: #ecf0f1;
        }

        .container {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 500px;
            text-align: center;
        }

        h2 {
            margin-bottom: 30px;
            color: #2c3e50;
            font-size: 28px;
        }

        .instruction {
            font-size: 16px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .input-field {
            width: 100%;
            padding: 15px;
            font-size: 24px;
            text-align: center;
            border: 3px solid #e74c3c;
            border-radius: 10px;
            letter-spacing: 2px;
            font-family: monospace;
            margin-bottom: 20px;
        }

        .input-field:focus {
            outline: none;
            border-color: #c0392b;
            box-shadow: 0 0 10px rgba(231, 76, 60, 0.3);
        }

        .status-message {
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-weight: bold;
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .error {
            background-color: #e74c3c;
            color: white;
        }

        .success {
            background-color: #27ae60;
            color: white;
        }

        .loading {
            background-color: #f39c12;
            color: white;
        }

        .loading-animation {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .fade-out {
            opacity: 0;
            transition: opacity 1s ease-out;
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="admin.php" class="home-btn">
        <img src="images/return.png" alt="Return" style="width: 20px; height: 20px;">
        Return to Admin
    </a>
</div>

<div class="container">
    <h2>🔑 RFID Admin Registration</h2>
    <p class="instruction">Scan or enter the 10-digit admin RFID number</p>
    
    <form id="rfidForm">
        <input type="text" 
               id="rfidInput" 
               name="rfid" 
               class="input-field" 
               placeholder="Admin RFID Number"
               maxlength="10" 
               pattern="[0-9]{10}"
               autocomplete="off"
               autofocus>
    </form>

    <div id="statusMessage" class="status-message hidden"></div>
</div>

<script>
class AdminRFIDRegistration {
    constructor() {
        this.rfidInput = document.getElementById('rfidInput');
        this.statusMessage = document.getElementById('statusMessage');
        this.currentRFID = null;
        this.checkInterval = null;
        
        this.init();
    }

    init() {
        // Auto-focus input on page load and keep it focused
        this.rfidInput.focus();
        
        // Re-focus if user clicks elsewhere
        document.addEventListener('click', () => {
            setTimeout(() => this.rfidInput.focus(), 100);
        });
        
        // Handle input changes
        this.rfidInput.addEventListener('input', (e) => {
            this.handleInput(e.target.value);
        });
        
        // Prevent form submission
        document.getElementById('rfidForm').addEventListener('submit', (e) => {
            e.preventDefault();
        });
    }

    handleInput(value) {
        // Only allow digits
        value = value.replace(/[^0-9]/g, '');
        this.rfidInput.value = value;
        
        // Auto-submit when 10 digits are entered
        if (value.length === 10) {
            this.submitRFID(value);
        } else {
            this.hideStatus();
        }
    }

    async submitRFID(rfid) {
        this.currentRFID = rfid;
        this.showLoading();
        
        try {
            const formData = new FormData();
            formData.append('rfid', rfid);
            formData.append('ajax', '1');
            
            const response = await fetch('admin_register.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                this.startRegistrationCheck();
            } else {
                this.showError(result.message);
                this.resetForm();
            }
        } catch (error) {
            this.showError('Network error. Please try again.');
            this.resetForm();
        }
    }

    startRegistrationCheck() {
        let attempts = 0;
        const maxAttempts = 60; // 2 minutes max
        
        this.checkInterval = setInterval(async () => {
            attempts++;
            
            try {
                // Check if admin RFID appears in admins table
                const response = await fetch(`check_admin.php?rfid=${this.currentRFID}`);
                const result = await response.json();
                
                if (result.status === 'found') {
                    this.showSuccess();
                    this.stopRegistrationCheck();
                    this.resetFormDelayed();
                } else if (attempts >= maxAttempts) {
                    this.showError('Registration timeout. Please try again or check your app.');
                    this.stopRegistrationCheck();
                    this.resetForm();
                }
            } catch (error) {
                console.error('Check error:', error);
            }
        }, 2000); // Check every 2 seconds
    }

    stopRegistrationCheck() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
    }

    showLoading() {
        this.statusMessage.className = 'status-message loading';
        this.statusMessage.innerHTML = `
            <div class="loading-animation"></div>
            Please check your admin registration app for RFID: ${this.currentRFID}
        `;
    }

    showSuccess() {
        this.statusMessage.className = 'status-message success';
        this.statusMessage.innerHTML = '✅ Admin registration successful! Ready for next scan.';
    }

    showError(message) {
        this.statusMessage.className = 'status-message error';
        this.statusMessage.innerHTML = `❌ ${message}`;
    }

    hideStatus() {
        this.statusMessage.className = 'status-message hidden';
    }

    resetForm() {
        this.rfidInput.value = '';
        this.rfidInput.focus();
        this.currentRFID = null;
        
        setTimeout(() => {
            this.hideStatus();
        }, 3000);
    }

    resetFormDelayed() {
        setTimeout(() => {
            this.resetForm();
        }, 3000);
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    new AdminRFIDRegistration();
});
</script>

</body>
</html>