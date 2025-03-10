<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$conn = getDbConnection();

try {
    $category_id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    if (!$category_id)
        throw new Exception('Missing category ID');

    $stmt = $conn->prepare("SELECT id, name, created_at, updated_at FROM categories WHERE id = ?");
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('i', $category_id);
    if (!$stmt->execute())
        throw new Exception('Execute failed: ' . $stmt->error);
    $category = $stmt->get_result()->fetch_assoc();

    if (!$category)
        throw new Exception('Category not found');
    respond(['status' => 'success', 'data' => $category]);
    }
catch (Exception $e) {
    error_log("Category Show Error: " . $e->getMessage());
    respond(['status' => 'error', 'message' => $e->getMessage()], 404);
    } finally {
    $conn->close();
    }
