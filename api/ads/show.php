<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

const SELECT_AD = "SELECT id, title, description, price, images, location, status, category_id, item_condition, is_featured, created_at, updated_at FROM ads WHERE id = ? AND status = 'active'";

$conn = getDbConnection();

try {
    // Get ad ID from query parameter (e.g., /api/ads/show?id=1)
    $adId = isset($_GET['id']) ? (int) $_GET['id'] : null;
    if (!$adId || $adId <= 0) {
        throw new Exception('Invalid or missing ad ID', 400);
        }

    // Fetch ad
    $stmt = $conn->prepare(SELECT_AD);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('i', $adId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Ad not found or not active', 404);
        }

    $ad           = $result->fetch_assoc();
    $ad['images'] = $ad['images'] ? json_decode($ad['images'], true) : [];

    sendResponse([
        'message' => 'Ad retrieved successfully',
        'ad'      => $ad
    ]);
    }
catch (Exception $e) {
    $code    = $e->getCode() ?: 400;
    $message = $e->getMessage();

    switch ($message) {
        case 'Invalid or missing ad ID':
            $code = 400;
            break;
        case 'Ad not found or not active':
            $code = 404;
            break;
        case 'Database error':
            $message = 'Unable to retrieve ad. Please try again.';
            $code = 500;
            break;
        default:
            $message = 'An unexpected error occurred.';
            $code = 500;
        }

    error_log("Ad Show Error: " . $e->getMessage() . " | AdID: " . ($adId ?? 'unknown'));
    sendResponse(['error' => $message], $code);
    } finally {
    if (isset($stmt)) {
        $stmt->close();
        }
    $conn->close();
    }
