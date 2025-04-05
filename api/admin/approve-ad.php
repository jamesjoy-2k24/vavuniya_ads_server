<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=UTF-8');

// SQL Query to approve an ad
const APPROVE_AD = "UPDATE ads SET status = 'active', rejection_reason = NULL WHERE id = ? AND status = 'pending'";

$userId   = $GLOBALS['user_id'];
$userRole = $GLOBALS['user_role'];

if (!$userId || $userRole !== 'admin') {
    sendResponse(['error' => 'Unauthorized: Admin access required'], 403);
    }

$conn = getDbConnection();
$conn->begin_transaction();

try {
    $input = getInputData();
    $adId  = $input['id'] ?? null;

    if (!$adId) {
        sendResponse(['error' => 'Missing ad ID'], 400);
        }

    $stmt = $conn->prepare(APPROVE_AD);
    if (!$stmt)
        throw new Exception('Failed to prepare update query: ' . $conn->error);
    $stmt->bind_param('i', $adId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $conn->commit();
        sendResponse(['message' => 'Ad approved successfully']);
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
