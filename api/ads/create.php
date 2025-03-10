<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$conn = getDbConnection();

try {
    // Verify JWT
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s(\S+)/', $token, $matches)) {
        throw new Exception('Authentication required');
        }
    $user_id = verifyJwt($matches[1]);

    $data           = json_decode(file_get_contents('php://input'), true);
    $requiredFields = ['title', 'description', 'price'];
    $data           = validateInput($data, $requiredFields);

    $title          = $data['title'];
    $description    = $data['description'];
    $price          = (float) $data['price'];
    $images         = json_encode($data['images'] ?? []);
    $location       = $data['location'] ?? null;
    $category_id    = isset($data['category_id']) ? (int) $data['category_id'] : null;
    $item_condition = $data['item_condition'] ?? 'used';
    $status         = 'pending';

    $stmt = $conn->prepare("INSERT INTO ads (title, description, price, images, location, status, user_id, category_id, item_condition) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('ssdsssiss', $title, $description, $price, $images, $location, $status, $user_id, $category_id, $item_condition);
    if (!$stmt->execute())
        throw new Exception('Execute failed: ' . $stmt->error);

    $ad_id = $conn->insert_id;
    respond(['status' => 'success', 'message' => 'Ad created', 'ad_id' => $ad_id], 201);
    }
catch (Exception $e) {
    error_log("Ad Create Error: " . $e->getMessage());
    respond(['status' => 'error', 'message' => $e->getMessage()], 400);
    } finally {
    $conn->close();
    }
