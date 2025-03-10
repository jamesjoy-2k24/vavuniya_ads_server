<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$conn = getDbConnection();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id']))
        throw new Exception('Missing category ID');

    $category_id = (int) $data['id'];

    // Check if category exists
    $stmt = $conn->prepare("SELECT id FROM categories WHERE id = ?");
    $stmt->bind_param('i', $category_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc())
        throw new Exception('Category not found');

    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('i', $category_id);
    if (!$stmt->execute())
        throw new Exception('Execute failed: ' . $stmt->error);

    respond(['status' => 'success', 'message' => 'Category deleted']);
    }
catch (Exception $e) {
    error_log("Category Delete Error: " . $e->getMessage());
    respond(['status' => 'error', 'message' => $e->getMessage()], 400);
    } finally {
    $conn->close();
    }
