<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

const UPDATE_CATEGORY       = "UPDATE categories SET name = ?, parent_id = ?, updated_at = NOW() WHERE id = ?";
const CHECK_DUPLICATE       = "SELECT id FROM categories WHERE name = ? AND (parent_id = ? OR (parent_id IS NULL AND ? IS NULL)) AND id != ?";
const CHECK_PARENT_EXISTS   = "SELECT id FROM categories WHERE id = ?";
const CHECK_CATEGORY_EXISTS = "SELECT id, parent_id FROM categories WHERE id = ?";

// Authentication and role check
checkAuthAndRole('admin');

$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Get and validate input
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('Invalid JSON data', 400);
        }

    $requiredFields = ['id'];
    $optionalFields = ['name' => null, 'parent_id' => null];
    $sanitizedData  = validateInput($data, $requiredFields, $optionalFields);

    $categoryId = (int) $sanitizedData['id'];
    $name       = isset($sanitizedData['name']) ? trim($sanitizedData['name']) : null;
    $parentId   = $sanitizedData['parent_id'] !== null ? (int) $sanitizedData['parent_id'] : null;

    // Check if category exists
    $stmt = $conn->prepare(CHECK_CATEGORY_EXISTS);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('i', $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Category not found', 404);
        }
    $current = $result->fetch_assoc();
    $stmt->close();

    // Prevent self-referencing
    if ($parentId === $categoryId) {
        throw new Exception('A category cannot be its own parent', 400);
        }

    // Validate parent_id if provided
    if ($parentId !== null) {
        $stmt = $conn->prepare(CHECK_PARENT_EXISTS);
        if (!$stmt) {
            throw new Exception('Database error', 500);
            }
        $stmt->bind_param('i', $parentId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception('Parent category not found', 400);
            }
        $stmt->close();
        }

    // Use current values if not provided
    $newName     = $name !== null ? $name : $current['name'];
    $newParentId = $parentId !== null ? $parentId : $current['parent_id'];

    if (empty($newName) || strlen($newName) > 255) {
        throw new Exception('Name must be between 1 and 255 characters', 400);
        }

    // Check for duplicates
    $stmt = $conn->prepare(CHECK_DUPLICATE);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('siii', $newName, $newParentId, $newParentId, $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('Category name already exists under this parent', 409);
        }
    $stmt->close();

    // Update category
    $stmt = $conn->prepare(UPDATE_CATEGORY);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('sii', $newName, $newParentId, $categoryId);
    if (!$stmt->execute()) {
        if ($conn->errno === 1062) {
            throw new Exception('Category name already exists under this parent', 409);
            }
        throw new Exception('Failed to update category: ' . $conn->error, 500);
        }
    if ($stmt->affected_rows === 0) {
        throw new Exception('No changes made to category', 400);
        }

    $conn->commit();
    error_log("Category updated: ID=$categoryId, Name=$newName, ParentID=" . ($newParentId ?? 'none') . ", UserID=$userId");

    sendResponse(['message' => 'Category updated successfully']);
    }
catch (Exception $e) {
    $conn->rollback();
    $code    = $e->getCode() ?: 500;
    $message = $e->getMessage();

    if ($message === 'Invalid JSON data' || $message === 'Name must be between 1 and 255 characters' || $message === 'Parent category not found' || $message === 'A category cannot be its own parent' || $message === 'No changes made to category') {
        $code = 400;
        }
    elseif ($message === 'Category not found') {
        $code = 404;
        }
    elseif ($message === 'Category name already exists under this parent') {
        $code = 409;
        }
    elseif (strpos($message, 'Database error') === 0 || strpos($message, 'Failed to update category') === 0) {
        $message = 'Unable to update category. Please try again.';
        $code    = 500;
        }
    else {
        $message = 'An unexpected error occurred.';
        $code    = 500;
        }

    error_log("Category Update Error: " . $e->getMessage() . " | UserID: $userId");
    sendResponse(['error' => $message], $code);
    } finally {
    if (isset($stmt)) {
        $stmt->close();
        }
    $conn->close();
    }
