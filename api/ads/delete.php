<?php
require_once '../../config/database.php'; // Adjust path as needed
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Database connection
$conn = getDbConnection();

try {
    // Extract and verify JWT
    $token = null;
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = $_SERVER['HTTP_AUTHORIZATION'];
        }
    elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) { // For CGI/FastCGI
        $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
    elseif (function_exists('apache_request_headers')) { // Fallback for Apache
        $headers = apache_request_headers();
        $token   = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }

    if (!$token || !preg_match('/Bearer\s(\S+)/', $token, $matches)) {
        throw new Exception('Authentication required', 401);
        }

    // Get user_id from JWT, not request body
    $jwt_user_id = verifyJwt($matches[1]);

    // Parse incoming JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id'])) {
        throw new Exception('Missing ad ID', 400);
        }

    $ad_id = filter_var($data['id'], FILTER_VALIDATE_INT);
    if ($ad_id === false || $ad_id <= 0) {
        throw new Exception('Invalid ad ID', 400);
        }

    // Check ownership
    $stmt = $conn->prepare("SELECT user_id FROM ads WHERE id = ? AND status != 'deleted'");
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error, 500);
        }
    $stmt->bind_param('i', $ad_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ad     = $result->fetch_assoc();

    if (!$ad) {
        throw new Exception('Ad not found', 404);
        }
    if ($ad['user_id'] !== $jwt_user_id) {
        throw new Exception('Unauthorized: You do not own this ad', 403);
        }

    // Update ad status to 'deleted'
    $stmt = $conn->prepare("UPDATE ads SET status = 'deleted' WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error, 500);
        }
    $stmt->bind_param('i', $ad_id);
    if (!$stmt->execute()) {
        throw new Exception('Database execute failed: ' . $stmt->error, 500);
        }

    if ($stmt->affected_rows === 0) {
        throw new Exception('No ad was deleted', 404);
        }

    respond(['message' => 'Ad deleted successfully'], 200);

    }
catch (Exception $e) {
    error_log("Ad Delete Error: " . $e->getMessage() . " | Ad ID: " . ($ad_id ?? 'unknown'));
    respond(['error' => $e->getMessage()], $e->getCode() ?: 400);
    } finally {
    if (isset($stmt)) {
        $stmt->close();
        }
    $conn->close();
    }
