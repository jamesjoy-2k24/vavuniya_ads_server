<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=UTF-8');

// SQL Query to fetch ads
const SELECT_ADS = "SELECT a.id, a.title, a.description, a.price, a.images, a.location, a.status, a.rejection_reason, a.created_at, u.name AS user_name, c.name AS category_name
                    FROM ads a
                    LEFT JOIN users u ON a.user_id = u.id
                    LEFT JOIN categories c ON a.category_id = c.id
                    WHERE a.status IN ('pending', 'active', 'rejected', 'sold', 'deleted')
                    ORDER BY a.created_at DESC";

$userId   = $GLOBALS['user_id'];
$userRole = $GLOBALS['user_role'];

if (!$userId || $userRole !== 'admin') {
    sendResponse(['error' => 'Unauthorized: Admin access required'], 403);
    }

$conn = getDbConnection();
$conn->begin_transaction();

try {
    $stmt = $conn->prepare(SELECT_ADS);
    if (!$stmt)
        throw new Exception('Failed to prepare query: ' . $conn->error);
    $stmt->execute();
    $result = $stmt->get_result();

    $ads = [];
    while ($row = $result->fetch_assoc()) {
        $ads[] = [
            'id'               => $row['id'],
            'title'            => $row['title'] ?? 'Untitled',
            'description'      => $row['description'] ?? 'No description',
            'price'            => (float) $row['price'],
            'images'           => $row['images'] ? json_decode($row['images'], true) : [],
            'location'         => $row['location'] ?? 'Unknown',
            'status'           => $row['status'],
            'rejection_reason' => $row['rejection_reason'],
            'created_at'       => $row['created_at'],
            'user_name'        => $row['user_name'] ?? 'Unknown',
            'category_name'    => $row['category_name'] ?? 'Uncategorized',
        ];
        }
    sendResponse(['ads' => $ads]);
    }
catch (Exception $e) {
    sendResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
    } finally {
    if (isset($stmt))
        $stmt->close();
    $conn->close();
    }
