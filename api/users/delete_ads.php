<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=UTF-8');

const CHECK_AD_EXISTS = "SELECT status FROM ads WHERE id = ? AND user_id = ?";
const SOFT_DELETE_AD  = "UPDATE ads SET status = 'deleted', deleted_at = NOW() WHERE id = ? AND user_id = ? AND status = 'active'";

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

    // Schedule the ad for deletion after 1 day
    $stmt = $conn->prepare(SOFT_DELETE_AD);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('ii', $adId, $userId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to schedule ad for deletion', 500);
        }
    if ($stmt->affected_rows === 0) {
        throw new Exception('Ad not found or already deleted', 404);
        }

    $conn->commit();
    error_log("Ad deleted: ID=$adId, UserID=$userId");
    sendResponse(['message' => 'Ad deleted successfully']);
    }
catch (Exception $e) {
    $conn->rollback();
    $code    = $e->getCode() ?: 400;
    $message = $e->getMessage() ?: 'An error occurred';
    sendResponse(['error' => $message], $code);
    }
