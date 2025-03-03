<?php
// api/ads/list.php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$conn       = getDbConnection();
$data       = json_decode(file_get_contents('php://input'), true);
$categoryId = $data['category_id'] ?? null;
$search     = $data['search'] ?? null;

try {
    $query  = "SELECT id, title, price, images, location, condition FROM ads WHERE status = 'active'";
    $params = [];
    $types  = '';
    if ($categoryId) {
        $query .= " AND category_id = ?";
        $params[] = $categoryId;
        $types .= 'i';
        }
    if ($search) {
        $query .= " AND (title LIKE ? OR description LIKE ?)";
        $searchParam = "%$search%";
        $params[]    = $searchParam;
        $params[]    = $searchParam;
        $types .= 'ss';
        }

    $stmt = $conn->prepare($query);
    if ($params)
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $ads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare("SELECT name FROM categories");
    $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    respond(['ads' => $ads, 'categories' => array_column($categories, 'name')]);
    }
catch (Exception $e) {
    error_log("Ad List Error: " . $e->getMessage());
    respond(['error' => $e->getMessage()], 500);
    } finally {
    $conn->close();
    }
