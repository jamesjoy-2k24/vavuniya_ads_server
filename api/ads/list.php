<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

const SELECT_ADS        = "SELECT id, title, price, images, location, item_condition, description, status, user_id, category_id, is_featured, created_at, updated_at 
                    FROM ads WHERE status = 'active'";
const COUNT_ADS         = "SELECT COUNT(*) FROM ads WHERE status = 'active'";
const SELECT_CATEGORIES = "SELECT name FROM categories ORDER BY name ASC";

$conn = getDbConnection();

try {
    // Get request data (POST for consistency)
    $data       = json_decode(file_get_contents('php://input'), true) ?? [];
    $categoryId = isset($data['category_id']) ? filter_var($data['category_id'], FILTER_VALIDATE_INT) : null;
    $search     = $data['search'] ?? null;
    $page       = max(1, isset($data['page']) ? (int) $data['page'] : 1);
    $limit      = min(50, max(1, isset($data['page_size']) ? (int) $data['page_size'] : 10));
    $offset     = ($page - 1) * $limit;

    if ($categoryId === false || $categoryId < 0) {
        throw new Exception('Invalid category ID', 400);
        }

    // Build ads query
    $adsQuery = SELECT_ADS;
    $params   = [];
    $types    = '';

    if ($categoryId !== null) {
        $adsQuery .= " AND category_id = ?";
        $params[] = $categoryId;
        $types .= 'i';
        }
    if ($search) {
        $adsQuery .= " AND (title LIKE ? OR description LIKE ?)";
        $searchParam = "%$search%";
        $params[]    = $searchParam;
        $params[]    = $searchParam;
        $types .= 'ss';
        }
    $adsQuery .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    // Execute ads query
    $adsStmt = $conn->prepare($adsQuery);
    if (!$adsStmt) {
        throw new Exception('Database error', 500);
        }
    if ($params) {
        $adsStmt->bind_param($types, ...$params);
        }
    $adsStmt->execute();
    $adsResult = $adsStmt->get_result();
    $ads       = $adsResult->fetch_all(MYSQLI_ASSOC);

    // Decode images
    foreach ($ads as &$ad) {
        $ad['images'] = $ad['images'] ? json_decode($ad['images'], true) : [];
        }
    unset($ad);

    // Build count query
    $countQuery  = COUNT_ADS;
    $countParams = [];
    $countTypes  = '';
    if ($categoryId !== null) {
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

    // Execute count query
    $countStmt = $conn->prepare($countQuery);
    if (!$countStmt) {
        throw new Exception('Database error', 500);
        }
    if ($countParams) {
        $countStmt->bind_param($countTypes, ...$countParams);
        }
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_row()[0];

    // Fetch categories
    $catStmt = $conn->prepare(SELECT_CATEGORIES);
    if (!$catStmt) {
        throw new Exception('Database error', 500);
        }
    $catStmt->execute();
    $categoriesResult = $catStmt->get_result();
    $categories       = array_column($categoriesResult->fetch_all(MYSQLI_ASSOC), 'name');

    // Build response
    $response = [
        'message'    => 'Ads retrieved successfully',
        'ads'        => $ads,
        'categories' => $categories,
        'pagination' => [
            'page'      => $page,
            'page_size' => $limit,
            'total'     => $total,
            'has_more'  => ($page * $limit) < $total
        ]
    ];

    sendResponse($response);
    }
catch (Exception $e) {
    $code    = $e->getCode() ?: 400;
    $message = $e->getMessage();

    switch ($message) {
        case 'Invalid category ID':
            $code = 400;
            break;
        case 'Database error':
            $message = 'Unable to retrieve ads. Please try again.';
            $code = 500;
            break;
        default:
            $message = 'An unexpected error occurred.';
            $code = 500;
        }

    error_log("Ad List Error: " . $e->getMessage() . " | CategoryID: " . ($categoryId ?? 'none') . " | Search: " . ($search ?? 'none'));
    sendResponse(['error' => $message], $code);
    } finally {
    if (isset($adsStmt))
        $adsStmt->close();
    if (isset($countStmt))
        $countStmt->close();
    if (isset($catStmt))
        $catStmt->close();
    $conn->close();
    }
