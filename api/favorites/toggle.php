<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$conn = getDbConnection();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['user_id']) || !isset($data['ad_id'])) {
        throw new Exception('Missing user_id or ad_id');
        }

    $user_id = (int) $data['user_id'];
    $ad_id   = (int) $data['ad_id'];

    // Validate ad exists and is active
    $stmt = $conn->prepare("SELECT id FROM ads WHERE id = ? AND status = 'active'");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
        }
    $stmt->bind_param('i', $ad_id);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
        }
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new Exception('Ad not found or not active');
        }

    // Check if already favorite
    $stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND ad_id = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
        }
    $stmt->bind_param('ii', $user_id, $ad_id);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
        }
    $isFavorite = $stmt->get_result()->fetch_assoc();

    if ($isFavorite) {
        // Remove from favorites
        $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND ad_id = ?");
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
            }
        $stmt->bind_param('ii', $user_id, $ad_id);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
            }
        $message    = 'Removed from favorites';
        $isFavorite = false;
        }
    else {
        // Add to favorites
        $stmt = $conn->prepare("INSERT INTO favorites (user_id, ad_id) VALUES (?, ?)");
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
            }
        $stmt->bind_param('ii', $user_id, $ad_id);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
            }
        $message    = 'Added to favorites';
        $isFavorite = true;
        }

    // Build response
    sendResponse([
        'status'      => 'success',
        'message'     => $message,
        'is_favorite' => $isFavorite
    ]);
    }
catch (Exception $e) {
    error_log("Favorites Toggle Error: " . $e->getMessage());
    sendResponse(['status' => 'error', 'message' => $e->getMessage()], 400); // 400 for bad input, adjust as needed
    } finally {
    $conn->close();
    }
