<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Get request data (filters and pagination)
    $data       = json_decode(file_get_contents('php://input'), true) ?? [];
    $categoryId = $data['category_id'] ?? ($_GET['category_id'] ?? null);
    $search     = $data['search'] ?? ($_GET['search'] ?? null);
    $page       = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit      = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
    $offset     = ($page - 1) * $limit;

    // Build ads query
    $query  = "SELECT id, title, price, images, location, item_condition, description, status, user_id, category_id, is_featured, created_at, updated_at FROM ads WHERE status = 'active'";
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

    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
        }
    if ($params) {
        $stmt->bind_param($types, ...$params);
        }
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
        }
    $ads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Decode JSON images field
    foreach ($ads as &$ad) {
        $ad['images'] = json_decode($ad['images'], true);
        }

    // Get total count for pagination
    $countQuery  = "SELECT COUNT(*) FROM ads WHERE status = 'active'";
    $countParams = [];
    $countTypes  = '';
    if ($categoryId) {
        $countQuery .= " AND category_id = ?";
        $countParams[] = $categoryId;
        $countTypes .= 'i';
        }
    if ($search) {
        $countQuery .= " AND (title LIKE ? OR description LIKE ?)";
        $countParams[] = $searchParam;
        $countParams[] = $searchParam;
        $countTypes .= 'ss';
        }
    $countStmt = $conn->prepare($countQuery);
    if (!$countStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
        }
    if ($countParams) {
        $countStmt->bind_param($countTypes, ...$countParams);
        }
    if (!$countStmt->execute()) {
        throw new Exception('Execute failed: ' . $countStmt->error);
        }
    $total = $countStmt->get_result()->fetch_row()[0];

    // Fetch categories
    $stmt = $conn->prepare("SELECT name FROM categories");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
        }
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
        }
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Build response
    respond([
        'status'     => 'success',
        'data'       => $ads,
        'categories' => array_column($categories, 'name'),
        'pagination' => [
            'page'  => $page,
            'limit' => $limit,
            'total' => $total
        ]
    ]);
    }
catch (Exception $e) {
    error_log("Ad List Error: " . $e->getMessage());
    respond(['status' => 'error', 'message' => $e->getMessage()], 500);
    } finally {
    $conn->close();
    }
