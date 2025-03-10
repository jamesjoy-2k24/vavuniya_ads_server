<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$conn = getDbConnection();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id']) || !isset($data['user_id']))
        throw new Exception('Missing ad ID or user ID');

    $ad_id   = (int) $data['id'];
    $user_id = (int) $data['user_id'];

    // Check ownership
    $stmt = $conn->prepare("SELECT user_id FROM ads WHERE id = ?");
    $stmt->bind_param('i', $ad_id);
    $stmt->execute();
    $ad = $stmt->get_result()->fetch_assoc();
    if (!$ad || $ad['user_id'] !== $user_id)
        throw new Exception('Ad not found or unauthorized');

    $stmt = $conn->prepare("UPDATE ads SET status = 'deleted' WHERE id = ?");
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('i', $ad_id);
    if (!$stmt->execute())
        throw new Exception('Execute failed: ' . $stmt->error);

    respond(['status' => 'success', 'message' => 'Ad deleted']);
    }
catch (Exception $e) {
    error_log("Ad Delete Error: " . $e->getMessage());
    respond(['status' => 'error', 'message' => $e->getMessage()], 400);
    } finally {
    $conn->close();
    }
