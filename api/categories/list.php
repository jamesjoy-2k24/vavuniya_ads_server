<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$conn = getDbConnection();

try {
    $stmt = $conn->prepare("SELECT id, name, created_at, updated_at FROM categories");
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    if (!$stmt->execute())
        throw new Exception('Execute failed: ' . $stmt->error);
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    sendResponse(['status' => 'success', 'data' => $categories]);
    }
catch (Exception $e) {
    error_log("Categories List Error: " . $e->getMessage());
    sendResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
    } finally {
    $conn->close();
    }
