<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
validateInput($data, ['phone']);
$phone = trim($data['phone']);

$conn = getDbConnection();

try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;

    respond(['exists' => $exists]);
    }
catch (Exception $e) {
    error_log("User Exists Check Error: " . $e->getMessage());
    respond(['error' => 'Failed to check user existence'], 500);
    } finally {
    $conn->close();
    }
