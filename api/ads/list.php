<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Database connection
$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Get request data (POST only for consistency with HomeController)
    $data        = json_decode(file_get_contents('php://input'), true) ?? [];
    $category_id = isset($data['category_id']) ? filter_var($data['category_id'], FILTER_VALIDATE_INT) : null;
    $search      = $data['search'] ?? null;
    $page        = max(1, isset($data['page']) ? (int) $data['page'] : 1);
    $limit       = min(50, max(1, isset($data['page_size']) ? (int) $data['page_size'] : 10)); // Match frontend 'page_size'
    $offset      = ($page - 1) * $limit;

    if ($category_id === false || $category_id < 0) {
        throw new Exception('Invalid category ID', 400);
        }

    // Build ads query
    $query  = "SELECT id, title, price, images, location, item_condition, description, status, user_id, category_id, is_featured, created_at, updated_at 
              FROM ads WHERE status = 'active'";
    $params = [];
    $types  = '';

    if ($category_id !== null) {
        $query .= " AND category_id = ?";
        $params[] = $category_id;
        $types .= 'i';
        }
    if ($search) {
        $query .= " AND (title LIKE ? OR description LIKE ?)";
        $search_param = "%$search%";
        $params[]     = $search_param;
        $params[]     = $search_param;
        $types .= 'ss';
        }

    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    // Execute ads query
    $ads_stmt = $conn->prepare($query);
    if (!$ads_stmt) {
        throw new Exception('Prepare failed: ' . $conn->error, 500);
        }
    if ($params) {
        $ads_stmt->bind_param($types, ...$params);
        }
    if (!$ads_stmt->execute()) {
        throw new Exception('Execute failed: ' . $ads_stmt->error, 500);
        }
    $ads_result = $ads_stmt->get_result();
    $ads        = $ads_result->fetch_all(MYSQLI_ASSOC);

    // Decode JSON images field
    foreach ($ads as &$ad) {
        $ad['images'] = json_decode($ad['images'], true) ?: [];
        }
    unset($ad); // Clear reference

    // Get total count for pagination
    $count_query  = "SELECT COUNT(*) FROM ads WHERE status = 'active'";
    $count_params = [];
    $count_types  = '';
    if ($category_id !== null) {
        $count_query .= " AND category_id = ?";
        $count_params[] = $category_id;
        $count_types .= 'i';
        }
    if ($search) {
        $count_query .= " AND (title LIKE ? OR description LIKE ?)";
        $count_params[] = $search_param;
        $count_params[] = $search_param;
        $count_types .= 'ss';
        }
    $count_stmt = $conn->prepare($count_query);
    if (!$count_stmt) {
        throw new Exception('Prepare failed: ' . $conn->error, 500);
        }
    if ($count_params) {
        $count_stmt->bind_param($count_types, ...$count_params);
        }
    if (!$count_stmt->execute()) {
        throw new Exception('Execute failed: ' . $count_stmt->error, 500);
        }
    $total = $count_stmt->get_result()->fetch_row()[0];

    // Fetch categories
    $cat_stmt = $conn->prepare("SELECT name FROM categories");
    if (!$cat_stmt) {
        throw new Exception('Prepare failed: ' . $conn->error, 500);
        }
    if (!$cat_stmt->execute()) {
        throw new Exception('Execute failed: ' . $cat_stmt->error, 500);
        }
    $categories_result = $cat_stmt->get_result();
    $categories        = array_column($categories_result->fetch_all(MYSQLI_ASSOC), 'name');

    // Commit transaction
    $conn->commit();

    // Build response matching HomeController expectations
    sendResponse([
        'ads'        => $ads,
        'categories' => $categories,
        'has_more'   => count($ads) == $limit,
        'page'       => $page,
        'page_size'  => $limit,
        'total'      => $total
    ]);

    }
catch (Exception $e) {
    $conn->rollback();
    error_log("Ad List Error: " . $e->getMessage());
    sendResponse(['error' => $e->getMessage()], $e->getCode() ?: 500);
    } finally {
    if (isset($ads_stmt))
        $ads_stmt->close();
    if (isset($count_stmt))
        $count_stmt->close();
    if (isset($cat_stmt))
        $cat_stmt->close();
    $conn->close();
    }
