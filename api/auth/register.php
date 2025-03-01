<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

header('Content-Type: application/json');

// Queries
const SELECT_USER = "SELECT id FROM users WHERE phone = ?";
const INSERT_USER = "INSERT INTO users (phone, name, password) VALUES (?, ?, ?)";

$data = json_decode(file_get_contents('php://input'), true);
validateInput($data, ['phone', 'name', 'password']);
$phone    = trim($data['phone']);
$name     = trim($data['name']);
$password = password_hash(trim($data['password']), PASSWORD_BCRYPT);

$conn = getDbConnection();
$conn->begin_transaction();

try {
    $stmt = $conn->prepare(SELECT_USER);
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Phone already registered');
        }

    $stmt = $conn->prepare(INSERT_USER);
    $stmt->bind_param('sss', $phone, $name, $password);
    $stmt->execute();

    $conn->commit();
    respond(['message' => 'User registered successfully']);
    }
catch (Exception $e) {
    $conn->rollback();
    error_log("Register Error: " . $e->getMessage());
    respond(['error' => $e->getMessage()], $e->getCode() ?: 400);
    } finally {
    $conn->close();
    }
