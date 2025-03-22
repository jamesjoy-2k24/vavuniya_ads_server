<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

const SELECT_CATEGORY      = "SELECT id, name, parent_id, created_at, updated_at FROM categories WHERE id = ?";
const SELECT_SUBCATEGORIES = "SELECT id, name, parent_id, created_at, updated_at FROM categories WHERE parent_id = ? ORDER BY name ASC";

$conn = getDbConnection();

try {
    $categoryId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
    if ($categoryId === false || $categoryId <= 0) {
        throw new Exception('Invalid or missing category ID', 400);
        }

    // Fetch category
    $stmt = $conn->prepare(SELECT_CATEGORY);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('i', $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Category not found', 404);
        }

    $category = $result->fetch_assoc();
    $stmt->close();

    // Fetch subcategories
    $subStmt = $conn->prepare(SELECT_SUBCATEGORIES);
    if (!$subStmt) {
        throw new Exception('Database error', 500);
        }
    $subStmt->bind_param('i', $categoryId);
    $subStmt->execute();
    $subResult = $subStmt->get_result();

    $subcategories = [];
    while ($subRow = $subResult->fetch_assoc()) {
        $subcategories[] = $subRow;
        }
    $category['subcategories'] = $subcategories;

    sendResponse([
        'message'  => 'Category retrieved successfully',
        'category' => $category
    ]);
    }
catch (Exception $e) {
    $code    = $e->getCode() ?: 400;
    $message = $e->getMessage();

    switch ($message) {
        case 'Invalid or missing category ID':
            $code = 400;
            break;
        case 'Category not found':
            $code = 404;
            break;
        case 'Database error':
            $message = 'Unable to retrieve category. Please try again.';
            $code = 500;
            break;
        default:
            $message = 'An unexpected error occurred.';
            $code = 500;
        }

    error_log("Category Show Error: " . $e->getMessage() . " | CategoryID: " . ($categoryId ?? 'unknown'));
    sendResponse(['error' => $message], $code);
    } finally {
    if (isset($stmt)) {
        $stmt->close();
        }
    if (isset($subStmt)) {
        $subStmt->close();
        }
    $conn->close();
    }
