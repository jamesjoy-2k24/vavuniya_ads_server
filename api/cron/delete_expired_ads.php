<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

const DELETE_EXPIRED = "DELETE FROM ads WHERE status = 'deleted' AND deleted_at < NOW() - INTERVAL 1 DAY";

$conn = getDbConnection();
if (!$conn) {
    error_log("Database connection failed");
    exit;
    }

try {
    $stmt = $conn->prepare(DELETE_EXPIRED);
    if (!$stmt)
        throw new Exception('Failed to prepare delete query: ' . $conn->error);
    $stmt->execute();
    $deletedRows = $stmt->affected_rows;
    error_log("Deleted $deletedRows expired ads");
    }
catch (Exception $e) {
    error_log("Error deleting expired ads: " . $e->getMessage());
    } finally {
    if (isset($stmt))
        $stmt->close();
    $conn->close();
    }
