<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=UTF-8');

const SELECT_ADS = "SELECT id, title, description, images FROM ads WHERE id = ? AND user_id = ? LIMIT 1";
const UPDATE_AD  = "UPDATE ads SET title = ?, description = ?, images = ? WHERE id = ? AND user_id = ?";

$userId = $GLOBALS['user_id'];

$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Edit ad
    $data           = getInputData();
    $requiredFields = ['id'];
    $optionalFields = ['title' => null, 'description' => null, 'images' => null];
    $sanitizedData  = validateInput($data, $requiredFields, $optionalFields);
    $adId           = (int) $sanitizedData['id'];

    // Fetch current ad data
    $stmt = $conn->prepare(SELECT_ADS);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }

    $stmt->bind_param('ii', $adId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Ad not found or you do not have permission to update it', 404);
        }
    $currentAd = $result->fetch_assoc();
    $stmt->close();

    // Merge current data with provided data
    $title       = $sanitizedData['title'] ?? $currentAd['title'];
    $description = $sanitizedData['description'] ?? $currentAd['description'];
    $imageUrl    = $sanitizedData['images'] ?? $currentAd['images'];

    // Update ad
    $stmt = $conn->prepare(UPDATE_AD);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('sssii', $title, $description, $imageUrl, $adId, $userId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update ad', 500);
        }
    if ($stmt->affected_rows === 0) {
        throw new Exception('No changes made to the ad', 200);
        }

    // Commit transaction
    $conn->commit();
    sendResponse(['message' => 'Ad updated successfully']);
    }
catch (Exception $e) {
    $conn->rollback();
    sendResponse(['message' => $e->getMessage()], $e->getCode());
    } finally {
    if (isset($stmt)) {
        $stmt->close();
        }
    $conn->close();
    }
