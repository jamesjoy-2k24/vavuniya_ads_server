<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

// SQL Queries with corrected WHERE clauses
const ADS_COUNT       = "SELECT COUNT(*) as adCount FROM ads WHERE user_id = ?";
const SELECT_USER     = "SELECT id, name, phone, created_at, role FROM users WHERE id = ?";
const MESSAGES_COUNT  = "SELECT COUNT(*) as messagesCount FROM messages WHERE sender_id = ?";
const FAVORITES_COUNT = "SELECT COUNT(*) as favoritesCount FROM favorites WHERE user_id = ?";

$userId = $GLOBALS['user_id'];

$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Fetch user details
    $userStmt = $conn->prepare(SELECT_USER);
    if (!$userStmt) {
        throw new Exception('Failed to prepare user query: ' . $conn->error);
        }
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userData   = $userResult->fetch_assoc();

    if (!$userData) {
        sendResponse(['error' => 'User not found'], 404);
        }

    // Fetch ad count
    $adCountStmt = $conn->prepare(ADS_COUNT);
    if (!$adCountStmt) {
        throw new Exception('Failed to prepare ads count query: ' . $conn->error);
        }
    $adCountStmt->bind_param('i', $userId);
    $adCountStmt->execute();
    $adCountData = $adCountStmt->get_result()->fetch_assoc();

    // Fetch favorites count
    $favoritesCountStmt = $conn->prepare(FAVORITES_COUNT);
    if (!$favoritesCountStmt) {
        throw new Exception('Failed to prepare favorites count query: ' . $conn->error);
        }
    $favoritesCountStmt->bind_param('i', $userId);
    $favoritesCountStmt->execute();
    $favoritesCountData = $favoritesCountStmt->get_result()->fetch_assoc();

    // Fetch messages count
    $messagesCountStmt = $conn->prepare(MESSAGES_COUNT);
    if (!$messagesCountStmt) {
        throw new Exception('Failed to prepare messages count query: ' . $conn->error);
        }
    $messagesCountStmt->bind_param('i', $userId);
    $messagesCountStmt->execute();
    $messagesCountData = $messagesCountStmt->get_result()->fetch_assoc();

    // Build response
    $response = [
        'name'           => $userData['name'] ?? 'No Name',
        'phone'          => $userData['phone'] ?? 'No Phone',
        'joined'         => date('Y-m-d', strtotime($userData['created_at'] ?? 'now')),
        'adCount'        => (int) ($adCountData['adCount'] ?? 0),
        'favoritesCount' => (int) ($favoritesCountData['favoritesCount'] ?? 0),
        'messagesCount'  => (int) ($messagesCountData['messagesCount'] ?? 0),
        'role'           => $userData['role'] ?? 'user',
    ];

    $conn->commit();
    sendResponse($response);
    }
catch (Exception $e) {
    $conn->rollback();
    sendResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
    } finally {
    // Close statements safely
    foreach ([$userStmt ?? null, $adCountStmt ?? null, $favoritesCountStmt ?? null, $messagesCountStmt ?? null] as $stmt) {
        if ($stmt)
            $stmt->close();
        }
    $conn->close();
    }
