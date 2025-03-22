<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

const DELETE_CATEGORY       = "DELETE FROM categories WHERE id = ?";
const CHECK_CATEGORY_EXISTS = "SELECT id FROM categories WHERE id = ?";

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
    $optionalFields = [];
    $sanitizedData  = validateInput($data, $requiredFields, $optionalFields);
    $categoryId     = (int) $sanitizedData['id'];

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
    $stmt->close();

    // Delete category (subcategories cascade)
    $stmt = $conn->prepare(DELETE_CATEGORY);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('i', $categoryId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete category: ' . $conn->error, 500);
        }
    if ($stmt->affected_rows === 0) {
        throw new Exception('Category not found', 404);
        }

    $conn->commit();
    error_log("Category deleted: ID=$categoryId, UserID=$userId");

    sendResponse(['message' => 'Category deleted successfully']);
    }
catch (Exception $e) {
    $conn->rollback();
    $code    = $e->getCode() ?: 500;
    $message = $e->getMessage();

    if ($message === 'Invalid JSON data') {
        $code = 400;
        }
    elseif ($message === 'Category not found') {
        $code = 404;
        }
    elseif (strpos($message, 'Database error') === 0 || strpos($message, 'Failed to delete category') === 0) {
        $message = 'Unable to delete category. Please try again.';
        $code    = 500;
        }
    else {
        $message = 'An unexpected error occurred.';
        $code    = 500;
        }

    error_log("Category Delete Error: " . $e->getMessage() . " | UserID: $userId");
    sendResponse(['error' => $message], $code);
    } finally {
    if (isset($stmt)) {
        $stmt->close();
        }
    $conn->close();
    }
