<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=UTF-8');

const SELECT_USER     = "SELECT password FROM users WHERE id = ?";
const UPDATE_PASSWORD = "UPDATE users SET password = ? WHERE id = ?";

$userId = $GLOBALS['user_id'] ?? null;

$conn = getDbConnection();
$conn->begin_transaction();

try {
    $data            = getInputData();
    $currentPassword = $data['currentPassword'] ?? null;
    $newPassword     = $data['newPassword'] ?? null;

    if (!$currentPassword || !$newPassword) {
        sendResponse(['error' => 'Missing current or new password'], 400);
        }

    // Fetch current password
    $stmt = $conn->prepare(SELECT_USER);
    if (!$stmt)
        throw new Exception('Failed to prepare query: ' . $conn->error);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($currentPassword, $user['password'])) {
        sendResponse(['error' => 'Current password is incorrect'], 400);
        }

    // Update with new password (hashed)
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt     = $conn->prepare(UPDATE_PASSWORD);
    if (!$updateStmt)
        throw new Exception('Failed to prepare update query: ' . $conn->error);
    $updateStmt->bind_param('si', $hashedPassword, $userId);
    $updateStmt->execute();

    if ($updateStmt->affected_rows > 0) {
        $conn->commit();
        sendResponse(['message' => 'Password updated successfully']);
        }
    else {
        sendResponse(['error' => 'Failed to update password'], 500);
        }
    }
catch (Exception $e) {
    $conn->rollback();
    sendResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
    } finally {
    if (isset($stmt))
        $stmt->close();
    if (isset($updateStmt))
        $updateStmt->close();
    $conn->close();
    }
