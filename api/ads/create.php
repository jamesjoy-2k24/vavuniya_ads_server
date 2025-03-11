<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$conn = getDbConnection();

try {
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

    // Get user_id from JWT, not request body
    $jwt_user_id = verifyJwt($matches[1]);

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('Invalid JSON data');
        }

    $requiredFields = ['title', 'description', 'price'];
    $optionalFields = [
        'images'         => [],
        'location'       => null,
        'category_id'    => null,
        'item_condition' => 'used'
    ];

    $sanitizedData = validateInput($data, $requiredFields, $optionalFields);

    // Prepare data for insertion
    $title          = $sanitizedData['title'];
    $description    = $sanitizedData['description'];
    $price          = (float) $sanitizedData['price'];
    $images         = $sanitizedData['images'];
    $location       = $sanitizedData['location'];
    $category_id    = $sanitizedData['category_id'] ? (int) $sanitizedData['category_id'] : null;
    $item_condition = $sanitizedData['item_condition'];
    $status         = 'pending'; // Default status

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO ads (title, description, price, images, location, status, user_id, category_id, item_condition) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
        }

    $stmt->bind_param(
        'ssdsssiss',
        $title,
        $description,
        $price,
        $images,
        $location,
        $status,
        $user_id,
        $category_id,
        $item_condition
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
        }

    $ad_id = $conn->insert_id;
    respond(['message' => 'Ad created successfully', 'ad_id' => $ad_id], 201);

    }
catch (Exception $e) {
    error_log("Ad Create Error: " . $e->getMessage());
    respond(['error' => $e->getMessage()], $e->getCode() ?: 400);
    } finally {
    if (isset($stmt))
        $stmt->close();
    $conn->close();
    }
