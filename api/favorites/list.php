<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';

header('Content-Type: application/json; charset=UTF-8');

const SELECT_FAVORITES = "SELECT f.ad_id, a.title, a.description, a.price FROM favorites f JOIN ads a ON f.ad_id = a.id WHERE f.user_id = ?";

$userId = $GLOBALS['user_id'];

$conn = getDbConnection();

try {
    $stmt = $conn->prepare(SELECT_FAVORITES);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $favorites = [];
    while ($row = $result->fetch_assoc()) {
        $favorites[] = $row;
        }

    sendResponse([
        'message'   => 'Your favorites retrieved successfully',
        'favorites' => $favorites
    ], 200);
    }
catch (Exception $e) {
    sendResponse(['error' => $e->getMessage()], 500);
    } finally {
    $conn->close();
    }
