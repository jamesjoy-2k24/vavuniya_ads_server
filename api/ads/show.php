<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Database connection
$conn = getDbConnection();

try {
    // Validate ad ID from GET parameter
    $ad_id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    if ($ad_id === false || $ad_id <= 0) {
        throw new Exception('Invalid ad ID', 400);
        }

    // Fetch ad details with is_favorite status
    $stmt = $conn->prepare("SELECT a.id, a.title, a.price, a.images, a.location, a.item_condition, a.description, a.status, a.user_id, a.category_id, a.is_featured, a.created_at, a.updated_at, EXISTS(SELECT 1 FROM favorites f WHERE f.ad_id = a.id AND f.user_id = ?) AS is_favorite FROM ads a WHERE a.id = ? AND a.status != 'deleted'");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error, 500);
        }
    $stmt->bind_param('ii', $user_id, $ad_id);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error, 500);
        }
    $ad = $stmt->get_result()->fetch_assoc();

    if (!$ad) {
        throw new Exception('Ad not found', 404);
        }

    // Decode JSON images field
    $ad['images'] = json_decode($ad['images'], true) ?: [];

    // Respond with ad details directly
    respond($ad, 200);

    }
catch (Exception $e) {
    error_log("Ad Show Error: " . $e->getMessage() . " | Ad ID: " . ($ad_id ?? 'unknown'));
    respond(['error' => $e->getMessage()], $e->getCode() ?: 404);
    } finally {
    if (isset($stmt)) {
        $stmt->close();
        }
    $conn->close();
    }
