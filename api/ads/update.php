<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Database connection
$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Extract and verify JWT
    $token = null;
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = $_SERVER['HTTP_AUTHORIZATION'];
        }
    elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
    elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $token   = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }

    if (!$token || !preg_match('/Bearer\s(\S+)/', $token, $matches)) {
        throw new Exception('Authentication required', 401);
        }
    $jwt_user_id = verifyJwt($matches[1]);

    // Parse and validate input
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id'])) {
        throw new Exception('Missing ad ID', 400);
        }

    $ad_id = filter_var($data['id'], FILTER_VALIDATE_INT);
    if ($ad_id === false || $ad_id <= 0) {
        throw new Exception('Invalid ad ID', 400);
        }

    // Check ownership
    $stmt = $conn->prepare("SELECT user_id FROM ads WHERE id = ? AND status != 'deleted'");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error, 500);
        }
    $stmt->bind_param('i', $ad_id);
    $stmt->execute();
    $ad = $stmt->get_result()->fetch_assoc();
    if (!$ad) {
        throw new Exception('Ad not found', 404);
        }
    if ($ad['user_id'] !== $jwt_user_id) {
        throw new Exception('Unauthorized: You do not own this ad', 403);
        }

    // Validate and sanitize optional fields
    $updates = [];
    $params  = [];
    $types   = '';

    if (isset($data['title'])) {
        $title = filter_var($data['title'], FILTER_SANITIZE_STRING);
        if (strlen($title) < 3) {
            throw new Exception('Title must be at least 3 characters', 400);
            }
        $updates[] = "title = ?";
        $params[]  = $title;
        $types .= 's';
        }

    if (isset($data['description'])) {
        $description = filter_var($data['description'], FILTER_SANITIZE_STRING);
        $updates[]   = "description = ?";
        $params[]    = $description;
        $types .= 's';
        }

    if (isset($data['price'])) {
        $price = filter_var($data['price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        if (!is_numeric($price) || $price < 0) {
            throw new Exception('Price must be a positive number', 400);
            }
        $updates[] = "price = ?";
        $params[]  = (float) $price;
        $types .= 'd';
        }

    if (isset($data['images'])) {
        if (!is_array($data['images'])) {
            throw new Exception('Images must be an array', 400);
            }
        $images    = json_encode($data['images']);
        $updates[] = "images = ?";
        $params[]  = $images;
        $types .= 's';
        }

    if (isset($data['location'])) {
        $location  = filter_var($data['location'], FILTER_SANITIZE_STRING);
        $updates[] = "location = ?";
        $params[]  = $location;
        $types .= 's';
        }

    if (isset($data['category_id'])) {
        $category_id = filter_var($data['category_id'], FILTER_VALIDATE_INT);
        if ($category_id === false || $category_id <= 0) {
            throw new Exception('Invalid category ID', 400);
            }
        $updates[] = "category_id = ?";
        $params[]  = $category_id;
        $types .= 'i';
        }

    if (isset($data['item_condition'])) {
        $item_condition = strtolower(filter_var($data['item_condition'], FILTER_SANITIZE_STRING));
        if (!in_array($item_condition, ['new', 'used'])) {
            throw new Exception('Item condition must be "new" or "used"', 400);
            }
        $updates[] = "item_condition = ?";
        $params[]  = $item_condition;
        $types .= 's';
        }

    if (isset($data['status'])) {
        $status = strtolower(filter_var($data['status'], FILTER_SANITIZE_STRING));
        if (!in_array($status, ['active', 'pending', 'sold'])) { // Exclude 'deleted' for safety
            throw new Exception('Status must be "active", "pending", or "sold"', 400);
            }
        $updates[] = "status = ?";
        $params[]  = $status;
        $types .= 's';
        }

    // Ensure there’s something to update
    if (empty($updates)) {
        throw new Exception('No fields provided to update', 400);
        }

    // Build and execute query
    $query    = "UPDATE ads SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
    $params[] = $ad_id;
    $types .= 'i';

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error, 500);
        }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error, 500);
        }

    if ($stmt->affected_rows === 0) {
        throw new Exception('No changes made to the ad', 400);
        }

    $conn->commit();
    respond(['message' => 'Ad updated successfully'], 200);

    }
catch (Exception $e) {
    $conn->rollback();
    error_log("Ad Update Error: " . $e->getMessage() . " | Ad ID: " . ($ad_id ?? 'unknown'));
    respond(['error' => $e->getMessage()], $e->getCode() ?: 400);
    } finally {
    if (isset($stmt)) {
        $stmt->close();
        }
    $conn->close();
    }
