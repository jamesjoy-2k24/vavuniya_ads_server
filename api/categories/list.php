<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

const SELECT_CATEGORIES    = "SELECT id, name, parent_id, created_at, updated_at FROM categories WHERE parent_id IS NULL ORDER BY name ASC";
const SELECT_SUBCATEGORIES = "SELECT id, name, parent_id, created_at, updated_at FROM categories WHERE parent_id = ? ORDER BY name ASC";

$conn = getDbConnection();

try {
    $parentId = isset($_GET['parent_id']) ? filter_var($_GET['parent_id'], FILTER_VALIDATE_INT) : null;
    if ($parentId === false || $parentId < 0) {
        throw new Exception('Invalid parent ID', 400);
        }

    // Choose query based on parent_id
    $query = $parentId === null ? SELECT_CATEGORIES : SELECT_SUBCATEGORIES;
    $stmt  = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }

    if ($parentId !== null) {
        $stmt->bind_param('i', $parentId);
        }
    $stmt->execute();
    $result = $stmt->get_result();

    // Build category array
    $categories  = [];
    $categoryMap = [];
    while ($row = $result->fetch_assoc()) {
        $row['subcategories']    = $parentId === null ? [] : null; // Only nest if fetching top-level
        $categoryMap[$row['id']] = $row;
        }

    // If fetching top-level, get subcategories
    if ($parentId === null) {
        $subStmt = $conn->prepare(SELECT_SUBCATEGORIES);
        if (!$subStmt) {
            throw new Exception('Database error', 500);
            }
        foreach ($categoryMap as $cat) {
            $subStmt->bind_param('i', $cat['id']);
            $subStmt->execute();
            $subResult = $subStmt->get_result();
            while ($subRow = $subResult->fetch_assoc()) {
                $subRow['subcategories']                    = []; // No deeper nesting for now
                $categoryMap[$cat['id']]['subcategories'][] = $subRow;
                }
            }
        $subStmt->close();
        }

    $responseCategories = $parentId === null ? array_values($categoryMap) : array_values($categoryMap);

    sendResponse([
        'message'    => 'Categories retrieved successfully',
        'categories' => $responseCategories
    ]);
    }
catch (Exception $e) {
    $code    = $e->getCode() ?: 400;
    $message = $e->getMessage();

    switch ($message) {
        case 'Invalid parent ID':
            $code = 400;
            break;
        case 'Database error':
            $message = 'Unable to retrieve categories. Please try again.';
            $code = 500;
            break;
        default:
            $message = 'An unexpected error occurred.';
            $code = 500;
        }

    error_log("Categories List Error: " . $e->getMessage() . " | ParentID: " . ($parentId ?? 'none'));
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
