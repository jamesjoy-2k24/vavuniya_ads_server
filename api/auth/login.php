<?php
// api/auth/login.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';

header('Content-Type: application/json; charset=UTF-8');

// SQL Queries
const SELECT_USER           = "SELECT id, password FROM users WHERE phone = ?";
const INSERT_LOGIN_ATTEMPT  = "INSERT INTO login_attempts (phone, ip_address, status) VALUES (?, ?, ?)";
const SELECT_LOGIN_ATTEMPTS = "SELECT COUNT(*) as attempts FROM login_attempts WHERE phone = ? AND created_at > NOW() - INTERVAL ? SECOND";

try {
    // Get and validate input
    $data = json_decode(file_get_contents('php://input'), true);
    $data = validateInput($data, ['phone', 'password']);

    $phone    = $data['phone'];
    $password = $data['password'];
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Database connection
    $conn = getDbConnection();
    $conn->begin_transaction();

    // Rate limiting
    $rateWindow = defined('LOGIN_RATE_WINDOW') ? LOGIN_RATE_WINDOW : 300;
    $rateLimit  = defined('LOGIN_RATE_LIMIT') ? LOGIN_RATE_LIMIT : 5;

    $stmt = $conn->prepare(SELECT_LOGIN_ATTEMPTS);
    if (!$stmt) {
        throw new Exception('Database error: Failed to prepare attempt count query', 500);
        }
    $stmt->bind_param('si', $phone, $rateWindow);
    $stmt->execute();
    $attempts = $stmt->get_result()->fetch_assoc()['attempts'];

    if ($attempts >= $rateLimit) {
        throw new Exception('Too many login attempts. Please try again later.', 429);
        }

    // User verification
    $stmt = $conn->prepare(SELECT_USER);
    if (!$stmt) {
        throw new Exception('Database error: Failed to prepare user query', 500);
        }
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        logLoginAttempt($conn, $phone, $ip, 'failed');
        $conn->commit();
        throw new Exception('Phone number not registered', 404);
        }

    $user = $result->fetch_assoc();
    if (!password_verify($password, $user['password'])) {
        logLoginAttempt($conn, $phone, $ip, 'failed');
        $conn->commit();
        throw new Exception('Incorrect password', 401);
        }

    // Successful login
    logLoginAttempt($conn, $phone, $ip, 'success');
    $token = generateJwt($user['id']);

    $conn->commit();
    error_log("User logged in: Phone={$phone}, UserID={$user['id']}");
    sendResponse(['message' => 'Login successful', 'token' => $token]);

    }
catch (Exception $e) {
    $conn->rollback();
    handleError($e, $phone);
    } finally {
    $conn->close();
    }

/**
 * Logs login attempts to the database
 */
function logLoginAttempt($conn, $phone, $ip, $status)
    {
    $stmt = $conn->prepare(INSERT_LOGIN_ATTEMPT);
    if (!$stmt) {
        throw new Exception('Database error: Failed to prepare login attempt log', 500);
        }
    $stmt->bind_param('sss', $phone, $ip, $status);
    $stmt->execute();
    }

/**
 * Handles and formats error responses
 */
function handleError(Exception $e, $phone)
    {
    $code    = $e->getCode() ?: 400;
    $message = $e->getMessage();

    switch ($message) {
        case 'Phone number not registered':
            $code = 404;
            break;
        case 'Incorrect password':
            $code = 401;
            break;
        case 'Too many login attempts. Please try again later.':
            $code = 429;
            break;
        default:
            $message = 'An unexpected error occurred. Please try again.';
            $code = 500;
            error_log("Login Error: {$e->getMessage()} | Phone: {$phone}");
        }

    sendResponse(['error' => $message], $code);
    }
