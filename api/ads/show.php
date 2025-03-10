<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$conn = getDbConnection();

try {
    $ad_id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    if (!$ad_id)
        throw new Exception('Missing ad ID');

    $stmt = $conn->prepare("SELECT id, title, price, images, location, item_condition, description, status, user_id, category_id, is_featured, created_at, updated_at FROM ads WHERE id = ?");
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('i', $ad_id);
    if (!$stmt->execute())
        throw new Exception('Execute failed: ' . $stmt->error);
    $ad = $stmt->get_result()->fetch_assoc();

    if (!$ad)
        throw new Exception('Ad not found');
    $ad['images'] = json_decode($ad['images'], true);

    respond(['status' => 'success', 'data' => $ad]);
    }
catch (Exception $e) {
    error_log("Ad Show Error: " . $e->getMessage());
    respond(['status' => 'error', 'message' => $e->getMessage()], 404);
    } finally {
    $conn->close();
    }
