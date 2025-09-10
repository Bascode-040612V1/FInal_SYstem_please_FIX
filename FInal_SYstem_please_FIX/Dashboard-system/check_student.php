<?php
include 'config.php';

$rfid = isset($_GET['rfid']) ? trim($_GET['rfid']) : '';

$response = ["status" => "not_found"];

if (!empty($rfid)) {
    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM students WHERE rfid = ?");
    $stmt->bind_param("s", $rfid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $response["status"] = "found";
    }
    $stmt->close();
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($response);
?>
