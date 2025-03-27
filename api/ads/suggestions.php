<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

$conn = getDbConnection();
try {
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    if (empty($query)) {
        sendResponse(['suggestions' => []], 200);
        exit;
        }

    $sql        = "SELECT title, description, location, price FROM ads WHERE (title LIKE ? OR description LIKE ? OR location LIKE ?) AND status = 'active'";
    $searchTerm = "%$query%";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error, 500);
        }
    $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    // Extract suggestions
    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        if (stripos($row['title'], $query) !== false) {
            $suggestions[] = $row['title'];
            }
        if (stripos($row['description'], $query) !== false) {
            $words = explode(' ', $row['description']);
            foreach ($words as $word) {
                if (stripos($word, $query) !== false) {
                    $suggestions[] = trim($word, ".,!?");
                    }
                }
            }
        if ($row['location'] && stripos($row['location'], $query) !== false) {
            $suggestions[] = $row['location'];
            }
        if (stripos((string) $row['price'], $query) !== false) {
            $suggestions[] = "Rs. " . number_format($row['price'], 2);
            }
        }

    $suggestions = array_unique($suggestions);
    $suggestions = array_slice($suggestions, 0, 5);
    sort($suggestions);

    sendResponse(['suggestions' => $suggestions], 200);
    }
catch (Exception $e) {
    error_log("Suggestions Error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
    sendResponse(['error' => 'Failed to fetch suggestions'], 500);
    } finally {
    $conn->close();
    }
