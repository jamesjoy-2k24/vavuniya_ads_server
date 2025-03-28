<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

const SELECT_AD      = "SELECT title, description, price, images, location, status, category_id, item_condition, is_featured FROM ads WHERE id = ? AND user_id = ?";
const UPDATE_AD      = "UPDATE ads SET title = ?, description = ?, price = ?, images = ?, location = ?, status = ?, category_id = ?, item_condition = ?, is_featured = ? WHERE id = ? AND user_id = ?";
const CHECK_CATEGORY = "SELECT id FROM categories WHERE id = ?";

// User ID from JWT (set by router.php)
$userId = $GLOBALS['user_id'];

$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Get and validate input
    $data = getInputData();

    // Required field: ad ID
    $requiredFields = ['id'];
    $optionalFields = [
        'title'          => null,
        'description'    => null,
        'price'          => null,
        'images'         => null,
        'location'       => null,
        'status'         => null,
        'category_id'    => null,
        'item_condition' => null,
        'is_featured'    => null
    ];

    $sanitizedData = validateInput($data, $requiredFields, $optionalFields);
    $adId          = (int) $sanitizedData['id'];

    // Fetch current ad data
    $stmt = $conn->prepare(SELECT_AD);
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
    $title         = $sanitizedData['title'] ?? $currentAd['title'];
    $description   = $sanitizedData['description'] ?? $currentAd['description'];
    $price         = $sanitizedData['price'] !== null ? (float) $sanitizedData['price'] : $currentAd['price'];
    $images        = $sanitizedData['images'] !== null ? json_encode($sanitizedData['images']) : $currentAd['images'];
    $location      = $sanitizedData['location'] ?? $currentAd['location'];
    $status        = $sanitizedData['status'] ?? $currentAd['status'];
    $categoryId    = $sanitizedData['category_id'] !== null ? (int) $sanitizedData['category_id'] : $currentAd['category_id'];
    $itemCondition = $sanitizedData['item_condition'] ?? $currentAd['item_condition'];
    $isFeatured    = $sanitizedData['is_featured'] !== null ? (bool) $sanitizedData['is_featured'] : $currentAd['is_featured'];

    // Validate status if provided
    if ($sanitizedData['status'] !== null && !in_array($status, ['active', 'pending', 'sold', 'deleted'])) {
        throw new Exception('Invalid status. Must be "active", "pending", "sold", or "deleted".', 400);
        }

    // Validate item_condition if provided
    if ($sanitizedData['item_condition'] !== null && !in_array($itemCondition, ['new', 'used'])) {
        throw new Exception('Invalid item_condition. Must be "new" or "used".', 400);
        }

    // Validate category_id if provided
    if ($sanitizedData['category_id'] !== null) {
        $stmt = $conn->prepare(CHECK_CATEGORY);
        if (!$stmt) {
            throw new Exception('Database error', 500);
            }
        $stmt->bind_param('i', $categoryId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception('Invalid category_id. Category does not exist.', 400);
            }
        $stmt->close();
        }

    // Update ad
    $stmt = $conn->prepare(UPDATE_AD);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }

    $stmt->bind_param(
        'ssdsssissii',
        $title,
        $description,
        $price,
        $images,
        $location,
        $status,
        $categoryId,
        $itemCondition,
        $isFeatured,
        $adId,
        $userId
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to update ad', 500);
        }
    if ($stmt->affected_rows === 0) {
        throw new Exception('No changes made to the ad', 200);
        }

    $conn->commit();
    sendResponse(['message' => 'Ad updated successfully']);
    }
catch (Exception $e) {
    $conn->rollback();
    $code    = $e->getCode() ?: 400;
    $message = $e->getMessage();

    switch ($message) {
        case 'Invalid JSON data':
        case 'Invalid status. Must be "active", "pending", "sold", "deleted".':
        case 'Invalid item_condition. Must be "new" or "used".':
        case 'Invalid category_id. Category does not exist.':
            $code = 400;
            break;
        case 'Ad not found or you do not have permission to update it':
            $code = 404;
            break;
        case 'No changes made to the ad':
            $code = 200;
            break;
        case 'Database error':
        case 'Failed to update ad':
            $message = 'Unable to update ad. Please try again.';
            $code = 500;
            break;
        default:
            $message = 'An unexpected error occurred.';
            $code = 500;
        }

    error_log("Ad Update Error: " . $e->getMessage() . " | AdID: $adId, UserID: $userId");
    sendResponse(['error' => $message], $code);
    } finally {
    if (isset($stmt)) {
        $stmt->close();
        }
    $conn->close();
    }
