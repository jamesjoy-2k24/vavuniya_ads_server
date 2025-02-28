<?php
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Queries
const INSERT_OTP         = "INSERT INTO otp (phone, code, expires_at) VALUES (?, ?, ?)";
const INSERT_OTP_ATTEMPT = "INSERT INTO otp_attempts (phone, attempt_type, status, ip_address) VALUES (?, 'phone', ?, ?)";

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
validateInput($data, ['phone']);
$phone = trim($data['phone']);

// Check rate limit
checkRateLimit($phone);

$conn = getDbConnection();
$conn->begin_transaction();

try {
    $code      = generateOtp();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $stmt = $conn->prepare(INSERT_OTP);
    $stmt->bind_param('sss', $phone, $code, $expiresAt);
    $stmt->execute();

    $stmt   = $conn->prepare(INSERT_OTP_ATTEMPT);
    $status = 'success';
    $ip     = $GLOBALS['current_ip'];
    $stmt->bind_param('sss', $phone, $status, $ip);
    $stmt->execute();

    $conn->commit();
    respond(['message' => 'OTP generated successfully', 'otp' => $code]);
    }
catch (Exception $e) {
    $conn->rollback();

    $stmt   = $conn->prepare(INSERT_OTP_ATTEMPT);
    $status = 'failed';
    $ip     = $GLOBALS['current_ip'];
    $stmt->bind_param('sss', $phone, $status, $ip);
    $stmt->execute();

    error_log("Send OTP Error: " . $e->getMessage());
    respond(['error' => 'Failed to generate OTP', 'details' => $e->getMessage()], 500);
    } finally {
    $conn->close();
    }
