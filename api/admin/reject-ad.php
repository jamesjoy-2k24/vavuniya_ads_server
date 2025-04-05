<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=UTF-8');

// SQL Query to reject an ad
const REJECT_AD = "UPDATE ads SET status = 'rejected', rejection_reason = ? WHERE id = ? AND status = 'pending'";


$userId   = $GLOBALS['user_id'];
$userRole = $GLOBALS['user_role'];

if (!$userId || $userRole !== 'admin') {
    sendResponse(['error' => 'Unauthorized: Admin access required'], 403);
    }

$conn = getDbConnection();
$conn->begin_transaction();

try {
    $input           = getInputData();
    $adId            = $input['id'] ?? null;
    $rejectionReason = $input['rejection_reason'] ?? null;

    if (!$adId || !$rejectionReason) {
        sendResponse(['error' => 'Missing ad ID or rejection reason'], 400);
        }

    $stmt = $conn->prepare(REJECT_AD);
    if (!$stmt)
        throw new Exception('Failed to prepare update query: ' . $conn->error);
    $stmt->bind_param('si', $rejectionReason, $adId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $conn->commit();
        sendResponse(['message' => 'Ad rejected successfully']);
        }
    else {
        sendResponse(['error' => 'Ad not found or not in pending status'], 404);
        }
    }
catch (Exception $e) {
    $conn->rollback();
    sendResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
    } finally {
    if (isset($stmt))
        $stmt->close();
    $conn->close();
    }
