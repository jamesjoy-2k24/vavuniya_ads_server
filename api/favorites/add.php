<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';

header('Content-Type: application/json; charset=UTF-8');

const INSERT_FAVORITE = "INSERT INTO favorites (user_id, ad_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_at = NOW()";

$userId = $GLOBALS['user_id'];
$conn   = getDbConnection();

try {
    $adId = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['ad_id']) ? (int) $_POST['ad_id'] : null);

    if (!$adId || $adId <= 0) {
        throw new Exception('Invalid or missing ad ID', 400);
        }

    // Prepare SQL statement
    $stmt = $conn->prepare(INSERT_FAVORITE);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('ii', $userId, $adId);
    $stmt->execute();
    sendResponse(['message' => 'Added to favorites'], 200);
    }
catch (Exception $e) {
    sendResponse(['error' => $e->getMessage()], 500);
    } finally {
    $conn->close();
    }
