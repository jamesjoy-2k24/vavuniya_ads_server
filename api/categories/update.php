<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$conn = getDbConnection();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id']) || !isset($data['name']))
        throw new Exception('Missing category ID or name');

    $category_id = (int) $data['id'];
    $name        = trim($data['name']);
    if (empty($name))
        throw new Exception('Category name cannot be empty');

    // Check if category exists
    $stmt = $conn->prepare("SELECT id FROM categories WHERE id = ?");
    $stmt->bind_param('i', $category_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc())
        throw new Exception('Category not found');

    // Check for duplicate name
    $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
    $stmt->bind_param('si', $name, $category_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc())
        throw new Exception('Category name already exists');

    $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('si', $name, $category_id);
    if (!$stmt->execute())
        throw new Exception('Execute failed: ' . $stmt->error);

    sendResponse(['status' => 'success', 'message' => 'Category updated']);
    }
catch (Exception $e) {
    error_log("Category Update Error: " . $e->getMessage());
    sendResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
    } finally {
    $conn->close();
    }
