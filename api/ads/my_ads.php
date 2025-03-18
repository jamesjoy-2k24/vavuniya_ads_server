<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

const SELECT_MY_ADS = "SELECT id, title, description, price, images, location, status, category_id, item_condition, is_featured, created_at, updated_at FROM ads WHERE user_id = ? AND status != 'deleted'";

// User ID from JWT (set by router.php)
$userId = $GLOBALS['user_id'];

$conn = getDbConnection();

try {
    $stmt = $conn->prepare(SELECT_MY_ADS);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $ads = [];
    while ($row = $result->fetch_assoc()) {
        $row['images'] = $row['images'] ? json_decode($row['images'], true) : [];
        $ads[]         = $row;
        }

    sendResponse([
        'message' => 'Your ads retrieved successfully',
        'ads'     => $ads
    ]);
    }
catch (Exception $e) {
    $code    = $e->getCode() ?: 400;
    $message = $e->getMessage();

    switch ($message) {
        case 'Database error':
            $message = 'Unable to retrieve your ads. Please try again.';
            $code = 500;
            break;
        default:
            $message = 'An unexpected error occurred.';
            $code = 500;
        }

    error_log("My Ads Error: " . $e->getMessage() . " | UserID: $userId");
    sendResponse(['error' => $message], $code);
    } finally {
    if (isset($stmt)) {
        $stmt->close();
        }
    $conn->close();
    }
