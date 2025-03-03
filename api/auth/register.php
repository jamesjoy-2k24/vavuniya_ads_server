<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

header('Content-Type: application/json');

const SELECT_USER = "SELECT id FROM users WHERE phone = ?";
const INSERT_USER = "INSERT INTO users (phone, name, password) VALUES (?, ?, ?)";

$data     = json_decode(file_get_contents('php://input'), true);
$data     = validateInput($data, ['phone', 'name', 'password']); // Use returned data
$phone    = $data['phone'];
$name     = $data['name'];
$password = password_hash($data['password'], PASSWORD_BCRYPT);

$conn = getDbConnection();
$conn->begin_transaction();

try {
    $stmt = $conn->prepare(SELECT_USER);
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Phone already registered');
        }

    $stmt = $conn->prepare(INSERT_USER);
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('sss', $phone, $name, $password);
    if (!$stmt->execute())
        throw new Exception('Insert failed: ' . $stmt->error);

    if ($stmt->affected_rows !== 1)
        throw new Exception('No rows inserted');

    $conn->commit();
    error_log("User registered: Phone=$phone, Name=$name");
    respond(['message' => 'User registered successfully']);
    }
catch (Exception $e) {
    $conn->rollback();
    error_log("Register Error: " . $e->getMessage() . " | Phone: $phone");
    respond(['error' => $e->getMessage()], $e->getCode() ?: 400);
    } finally {
    $conn->close();
    }
