<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';

header('Content-Type: application/json; charset=UTF-8');

$conn = getDbConnection();
try {
    // Input parameters with validation
    $query      = isset($_GET['q']) ? trim($_GET['q']) : '';
    $categoryId = isset($_GET['category_id']) ? filter_var($_GET['category_id'], FILTER_VALIDATE_INT) : null;
    $minPrice   = isset($_GET['minPrice']) ? filter_var($_GET['minPrice'], FILTER_VALIDATE_FLOAT, ['options' => ['default' => 0]]) : 0;
    $maxPrice   = isset($_GET['maxPrice']) ? filter_var($_GET['maxPrice'], FILTER_VALIDATE_FLOAT, ['options' => ['default' => PHP_FLOAT_MAX]]) : PHP_FLOAT_MAX;
    $page       = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
    $limit      = min(50, max(1, filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['default' => 10]])));
    $offset     = ($page - 1) * $limit;

    // Validate price range
    if ($minPrice < 0 || $maxPrice < 0 || $minPrice > $maxPrice) {
        sendResponse(['error' => 'Invalid price range'], 400);
        exit;
        }

    // Build dynamic query with JOIN to categories
    $sql      = "SELECT a.id, a.title, a.price, a.location, a.created_at, a.item_condition, a.is_featured, c.name AS category FROM ads a LEFT JOIN categories c ON a.category_id = c.id WHERE a.status = 'active'";
    $countSql = "SELECT COUNT(*) as total FROM ads a WHERE a.status = 'active'";
    $params   = [];
    $types    = '';

    if (!empty($query)) {
        $sql .= " AND (a.title LIKE ? OR a.description LIKE ? OR a.location LIKE ?)";
        $countSql .= " AND (a.title LIKE ? OR a.description LIKE ? OR a.location LIKE ?)";
        $searchTerm = "%$query%";
        $params[]   = $searchTerm;
        $params[]   = $searchTerm;
        $params[]   = $searchTerm;
        $types .= 'sss';
        }
    if ($categoryId) {
        $sql .= " AND a.category_id = ?";
        $countSql .= " AND a.category_id = ?";
        $params[] = $categoryId;
        $types .= 'i';
        }
    if ($minPrice > 0 || $maxPrice < PHP_FLOAT_MAX) {
        $sql .= " AND a.price BETWEEN ? AND ?";
        $countSql .= " AND a.price BETWEEN ? AND ?";
        $params[] = $minPrice;
        $params[] = $maxPrice;
        $types .= 'dd';
        }

    // Count total for pagination
    $stmt = $conn->prepare($countSql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error, 500);
        }
    if ($params) {
        $stmt->bind_param($types, ...$params);
        }
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Fetch ads with pagination
    $sql .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error, 500);
        }
    if ($params) {
        $stmt->bind_param($types, ...$params);
        }
    $stmt->execute();
    $result = $stmt->get_result();
    $ads    = $result->fetch_all(MYSQLI_ASSOC);

    // Format output
    foreach ($ads as &$ad) {
        $ad['price']       = number_format($ad['price'], 2);
        $ad['created_at']  = date('c', strtotime($ad['created_at']));
        $ad['is_featured'] = (bool) $ad['is_featured'];
        }
    unset($ad);

    sendResponse([
        'ads'  => $ads,
        'meta' => [
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => ceil($total / $limit)
        ]
    ], 200);
    }
catch (Exception $e) {
    error_log("Search Error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
    sendResponse(['error' => $e->getMessage()], $e->getCode() ?: 500);
    } finally {
    $conn->close();
    }
