<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

const SOFT_DELETE_AD  = "UPDATE ads SET status = 'deleted' WHERE id = ? AND user_id = ? AND status != 'deleted'";
const CHECK_AD_EXISTS = "SELECT status FROM ads WHERE id = ? AND user_id = ?";

// User ID from JWT (set by router.php)
$userId = $GLOBALS['user_id'];

$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Get and validate input
    $data           = getInputData();
    $requiredFields = ['id'];
    $optionalFields = [];
    $sanitizedData  = validateInput($data, $requiredFields, $optionalFields);
    $adId           = (int) $sanitizedData['id'];

    // Check if ad exists and isn’t already deleted
    $stmt = $conn->prepare(CHECK_AD_EXISTS);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('ii', $adId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Ad not found or you do not have permission to delete it', 404);
        }
    $ad = $result->fetch_assoc();
    if ($ad['status'] === 'deleted') {
        throw new Exception('Ad is already deleted', 400);
        }
    $stmt->close();

    // Soft delete the ad
    $stmt = $conn->prepare(SOFT_DELETE_AD);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('ii', $adId, $userId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete ad', 500);
        }
    if ($stmt->affected_rows === 0) {
        throw new Exception('Ad not found or already deleted', 404);
        }

    $conn->commit();
    error_log("Ad deleted (soft): ID=$adId, UserID=$userId");
    sendResponse(['message' => 'Ad deleted successfully']);
    }
catch (Exception $e) {
    $conn->rollback();
    $code    = $e->getCode() ?: 400;
    $message = $e->getMessage();

    switch ($message) {
        case 'Invalid JSON data':
        case 'Ad is already deleted':
            $code = 400;
            break;
        case 'Ad not found or you do not have permission to delete it':
        case 'Ad not found or already deleted':
            $code = 404;
            break;
        case 'Database error':
        case 'Failed to delete ad':
            $message = 'Unable to delete ad. Please try again.';
            $code = 500;
            break;
        default:
            $message = 'An unexpected error occurred.';
            $code = 500;
        }

    error_log("Ad Delete Error: " . $e->getMessage() . " | AdID: $adId, UserID: $userId");
    sendResponse(['error' => $message], $code);
    } finally {
    if (isset($stmt)) {
        $stmt->close();
        }
    $conn->close();
    }
