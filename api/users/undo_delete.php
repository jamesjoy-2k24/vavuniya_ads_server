<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

const UNDO_DELETE_AD = "UPDATE ads SET status = 'active', deleted_at = NULL WHERE id = ? AND user_id = ? AND status = 'deleted'";

try {
    $userId = $GLOBALS['user_id'];

    $conn = getDbConnection();
    $conn->begin_transaction();

    $data = getInputData();
    $adId = $data['id'] ?? null;


    // Undo delete logic
    if (!$adId) {
        sendResponse(['error' => 'Missing ad ID'], 400);
        }

    $stmt = $conn->prepare(UNDO_DELETE_AD);
    if (!$stmt)
        throw new Exception('Failed to prepare undo query: ' . $conn->error);

    $stmt->bind_param('ii', $adId, $userId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $conn->commit();
        sendResponse(['message' => 'Ad restored successfully']);
        }
    else {
        $conn->rollback();
        sendResponse(['error' => 'Ad not found or not deleted'], 404);
        }

    }
catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
        }
    sendResponse(['error' => $e->getMessage()], 500);
    } finally {
    if (isset($conn)) {
        $conn->close();
        }
    }
