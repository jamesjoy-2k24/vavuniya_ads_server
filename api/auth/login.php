<?php
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Sanitize and validate inputs
$data = json_decode(file_get_contents('php://input'), true);
validateInput($data, ['phone', 'password']);

$phone    = trim($data['phone']);
$password = trim($data['password']);

checkRateLimit($phone);

$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Fetch user
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE phone = ?");
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('User not found');
        }

    $user = $result->fetch_assoc();

    // Verify password
    if (!password_verify($password, $user['password'])) {
        throw new Exception('Invalid password');
        }

    $token = generateJwt($user['id']);
    $conn->commit();
    respond([
        'message' => 'Login successful',
        'user_id' => $user['id'],
        'token'   => $token,
    ]);
    }
catch (Exception $e) {
    $conn->rollback();
    error_log("Login Error: " . $e->getMessage() . " | Phone: $phone");
    respond(['error' => $e->getMessage()], $e->getCode() ?: 401);
    } finally {
    $conn->close();
    }
