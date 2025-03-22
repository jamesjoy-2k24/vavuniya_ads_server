<?php
// api/auth/register.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

const SELECT_USER = "SELECT id FROM users WHERE phone = ?";
const INSERT_USER = "INSERT INTO users (phone, name, password) VALUES (?, ?, ?)";

// Get and validate input
$data     = getInputData();
$data     = validateInput($data, ['phone', 'name', 'password']);
$phone    = $data['phone'];
$name     = $data['name'];
$password = password_hash($data['password'], PASSWORD_BCRYPT);

$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Check if phone is already registered
    $stmt = $conn->prepare(SELECT_USER);
    if (!$stmt) {
        throw new Exception('Database prepare error', 500);
        }
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Phone already registered', 409);
        }

    // Insert new user
    $stmt = $conn->prepare(INSERT_USER);
    if (!$stmt) {
        throw new Exception('Database prepare error', 500);
        }
    $stmt->bind_param('sss', $phone, $name, $password);
    if (!$stmt->execute()) {
        throw new Exception('User registration failed', 500);
        }
    if ($stmt->affected_rows !== 1) {
        throw new Exception('User not inserted', 500);
        }

    $userId = $conn->insert_id;
    $role   = 'user'; // Default role
    $jwt    = generateJwt($userId, $role);

    $conn->commit();
    error_log("User registered: Phone=$phone, Name=$name, ID=$userId");
    sendResponse(['message' => 'User registered successfully', 'token' => $jwt], 201);
    }
catch (Exception $e) {
    $conn->rollback();
    error_log("Register Error: " . $e->getMessage() . " | Phone: $phone");
    sendResponse(['error' => $e->getMessage()], $e->getCode() ?: 400);
    } finally {
    $conn->close();
    }
