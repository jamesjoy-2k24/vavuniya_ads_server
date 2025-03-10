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

    $title          = $data['title'] ?? null;
    $description    = $data['description'] ?? null;
    $price          = isset($data['price']) ? (float) $data['price'] : null;
    $images         = isset($data['images']) ? json_encode($data['images']) : null;
    $location       = $data['location'] ?? null;
    $category_id    = isset($data['category_id']) ? (int) $data['category_id'] : null;
    $item_condition = $data['item_condition'] ?? null;
    $status         = $data['status'] ?? null;

    $query  = "UPDATE ads SET ";
    $params = [];
    $types  = '';
    if ($title) {
        $query .= "title = ?, ";
        $params[] = $title;
        $types .= 's';
        }
    if ($description) {
        $query .= "description = ?, ";
        $params[] = $description;
        $types .= 's';
        }
    if ($price !== null) {
        $query .= "price = ?, ";
        $params[] = $price;
        $types .= 'd';
        }
    if ($images) {
        $query .= "images = ?, ";
        $params[] = $images;
        $types .= 's';
        }
    if ($location) {
        $query .= "location = ?, ";
        $params[] = $location;
        $types .= 's';
        }
    if ($category_id) {
        $query .= "category_id = ?, ";
        $params[] = $category_id;
        $types .= 'i';
        }
    if ($item_condition) {
        $query .= "item_condition = ?, ";
        $params[] = $item_condition;
        $types .= 's';
        }
    if ($status) {
        $query .= "status = ?, ";
        $params[] = $status;
        $types .= 's';
        }

    $query    = rtrim($query, ', ') . " WHERE id = ?";
    $params[] = $ad_id;
    $types .= 'i';

    $stmt = $conn->prepare($query);
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute())
        throw new Exception('Execute failed: ' . $stmt->error);

    respond(['status' => 'success', 'message' => 'Ad updated']);
    }
catch (Exception $e) {
    error_log("Ad Update Error: " . $e->getMessage());
    respond(['status' => 'error', 'message' => $e->getMessage()], 400);
    } finally {
    $conn->close();
    }
