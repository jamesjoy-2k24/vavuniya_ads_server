<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

const INSERT_CATEGORY     = "INSERT INTO categories (name, parent_id) VALUES (?, ?)";
const CHECK_PARENT_EXISTS = "SELECT id FROM categories WHERE id = ?";
const CHECK_DUPLICATE     = "SELECT id FROM categories WHERE name = ? AND (parent_id = ? OR (parent_id IS NULL AND ? IS NULL))";

// Authentication and role check
if (!isset($GLOBALS['user_id']) || !$GLOBALS['user_id']) {
    sendResponse(['error' => 'Authentication required'], 401);
    }
$userId = $GLOBALS['user_id'];

if (!isset($GLOBALS['user_role']) || $GLOBALS['user_role'] !== 'admin') {
    sendResponse(['error' => 'Admin access required'], 403);
    }

$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Get and validate input
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('Invalid JSON data', 400);
        }

    $requiredFields = ['name'];
    $optionalFields = ['parent_id' => null];
    $sanitizedData  = validateInput($data, $requiredFields, $optionalFields);

    $name     = trim($sanitizedData['name']);
    $parentId = $sanitizedData['parent_id'] !== null ? (int) $sanitizedData['parent_id'] : null;

    if (empty($name) || strlen($name) > 255) {
        throw new Exception('Name must be between 1 and 255 characters', 400);
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

    // Pre-check for duplicates
    $stmt = $conn->prepare(CHECK_DUPLICATE);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('sii', $name, $parentId, $parentId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('Category name already exists under this parent', 409);
        }
    $stmt->close();

    // Insert category
    $stmt = $conn->prepare(INSERT_CATEGORY);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('si', $name, $parentId);
    if (!$stmt->execute()) {
        if ($conn->errno === 1062) {
            throw new Exception('Category name already exists under this parent', 409);
            }
        throw new Exception('Failed to create category: ' . $conn->error, 500);
        }

    $categoryId = $conn->insert_id;
    $conn->commit();
    error_log("Category created: ID=$categoryId, Name=$name, ParentID=" . ($parentId ?? 'none') . ", UserID=$userId");

    sendResponse([
        'message'     => 'Category created successfully',
        'category_id' => $categoryId
    ], 201);
    }
catch (Exception $e) {
    $conn->rollback();
    $code    = $e->getCode() ?: 500;
    $message = $e->getMessage();

    if ($message === 'Invalid JSON data' || $message === 'Name must be between 1 and 255 characters' || $message === 'Parent category not found') {
        $code = 400;
        }
    elseif ($message === 'Category name already exists under this parent') {
        $code = 409;
        }
    elseif (strpos($message, 'Database error') === 0 || strpos($message, 'Failed to create category') === 0) {
        $message = 'Unable to create category. Please try again.';
        $code    = 500;
        }
    else {
        $message = 'An unexpected error occurred.';
        $code    = 500;
        }

    error_log("Category Create Error: " . $e->getMessage() . " | UserID: $userId");
    sendResponse(['error' => $message], $code);
    } finally {
    if (isset($stmt)) {
        $stmt->close();
        }
    $conn->close();
    }
