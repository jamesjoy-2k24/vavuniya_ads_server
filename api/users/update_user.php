<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=UTF-8');

const UPDATE_USER = "UPDATE users SET name = ?, phone2 = ? WHERE id = ?";

$userId = $GLOBALS['user_id'];

$conn = getDbConnection();
$conn->begin_transaction();

try {
    $data  = getInputData();
    $name  = $data['name'] ?? null;
    $phone = $data['phone'] ?? null;

    $stmt = $conn->prepare(UPDATE_USER);
    if (!$stmt)
        throw new Exception('Failed to prepare update query: ' . $conn->error);
    $stmt->bind_param('ssi', $name, $phone, $userId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $conn->commit();
        sendResponse(['message' => 'Profile updated successfully']);
        }
    else {
        sendResponse(['error' => 'No changes made or user not found'], 404);
        }
    }
catch (Exception $e) {
    $conn->rollback();
    sendResponse(['message' => $e->getMessage()], $e->getCode());
    } finally {
    if (isset($stmt)) {
        $stmt->close();
        }
    $conn->close();
    }
