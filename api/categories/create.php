<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$conn = getDbConnection();
$conn->begin_transaction();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['name']))
        throw new Exception('Missing category name');

    $name = trim($data['name']);
    if (empty($name))
        throw new Exception('Category name cannot be empty');

    $stmt = $conn->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?)");
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('s', $name);
    if (!$stmt->execute())
        throw new Exception('Execute failed: ' . $stmt->error);
    $existing = $stmt->get_result()->fetch_assoc();
    if ($existing) {
        throw new Exception('Category name "' . $name . '" already exists');
        }

    // Insert new category
    $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('s', $name);
    if (!$stmt->execute()) {
        if ($conn->errno === 1062) {
            throw new Exception('Category name "' . $name . '" already exists (database constraint)');
            }
        throw new Exception('Execute failed: ' . $stmt->error);
        }

    $category_id = $conn->insert_id;
    sendResponse(['status' => 'success', 'message' => 'Category created', 'category_id' => $category_id], 201);
    }
catch (Exception $e) {
    error_log("Category Create Error: " . $e->getMessage());
    sendResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
    } finally {
    $conn->close();
    }
