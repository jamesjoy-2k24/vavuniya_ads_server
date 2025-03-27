<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

const SELECT_ADS = "SELECT id, title, description, images FROM ads WHERE user_id = ? LIMIT 20";

$userId = $GLOBALS['user_id'];


$conn = getDbConnection();

try {
    $stmt = $conn->prepare(SELECT_ADS);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
        }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $ads = [];
    while ($row = $result->fetch_assoc()) {
        $ads[] = [
            'id'          => $row['id'],
            'title'       => $row['title'] ?? 'Untitled',
            'description' => $row['description'] ?? 'No description',
            'imageUrl'    => $row['image'] ?? null,
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
