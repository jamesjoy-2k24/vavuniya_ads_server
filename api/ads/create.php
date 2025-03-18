<?php
// api/ads/create.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

const INSERT_AD      = "INSERT INTO ads (title, description, price, images, location, status, user_id, category_id, item_condition, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
const CHECK_CATEGORY = "SELECT id FROM categories WHERE id = ?";

// User ID from JWT (set by router.php)
$userId = $GLOBALS['user_id'];

$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Get and validate input
    $data           = getInputData();
    $requiredFields = ['title', 'description', 'price', 'item_condition'];
    $optionalFields = [
        'images'      => [],
        'location'    => null,
        'category_id' => null,
        'is_featured' => false
    ];

    $sanitizedData = validateInput($data, $requiredFields, $optionalFields);

    // Prepare data
    $title         = $sanitizedData['title'];
    $description   = $sanitizedData['description'];
    $price         = (float) $sanitizedData['price'];
    $images        = json_encode($sanitizedData['images']);
    $location      = $sanitizedData['location'];
    $status        = 'pending';
    $categoryId    = $sanitizedData['category_id'] ? (int) $sanitizedData['category_id'] : null;
    $itemCondition = $sanitizedData['item_condition'];
    $isFeatured    = (bool) $sanitizedData['is_featured'];

    // Validate item_condition
    if (!in_array($itemCondition, ['new', 'used'])) {
        throw new Exception('Invalid item_condition. Must be "new" or "used".', 400);
        }

    // Validate category_id if provided
    if ($categoryId !== null) {
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

    // Insert into database
    $stmt = $conn->prepare(INSERT_AD);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }

    $stmt->bind_param(
        'ssdsssiiss',
        $title,
        $description,
        $price,
        $images,
        $location,
        $status,
        $userId,
        $categoryId,
        $itemCondition,
        $isFeatured
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to create ad', 500);
        }

    $adId = $conn->insert_id;
    $conn->commit();
    error_log("Ad created: ID=$adId, UserID=$userId");
    sendResponse(['message' => 'Ad created successfully', 'ad_id' => $adId], 201);
    }
catch (Exception $e) {
    $conn->rollback();
    $code    = $e->getCode() ?: 400;
    $message = $e->getMessage();

    switch ($message) {
        case 'Invalid JSON data':
        case 'Invalid item_condition. Must be "new" or "used".':
        case 'Invalid category_id. Category does not exist.':
            $code = 400;
            break;
        case 'Database error':
        case 'Failed to create ad':
            $message = 'Unable to create ad. Please try again.';
            $code = 500;
            break;
        default:
            $message = 'An unexpected error occurred.';
            $code = 500;
        }

    error_log("Ad Create Error: " . $e->getMessage() . " | UserID: $userId");
    sendResponse(['error' => $message], $code);
    } finally {
    if (isset($stmt)) {
        $stmt->close();
        }
    $conn->close();
    }
