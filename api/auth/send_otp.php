<?php
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Queries
const INSERT_OTP         = "INSERT INTO otp (phone, code, expires_at) VALUES (?, ?, ?)";
const INSERT_OTP_ATTEMPT = "INSERT INTO otp_attempts (phone, attempt_type, status, ip_address) VALUES (?, 'phone', ?, ?)";

// Get and validate request data
$data  = json_decode(file_get_contents('php://input'), true);
$data  = validateInput($data, ['phone']); // Use sanitized data
$phone = $data['phone'];

checkRateLimit($phone); // Sets $GLOBALS['current_ip']

$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Generate OTP and expiration
    $code      = generateOtp();
    $expiresAt = $conn->query("SELECT NOW() + INTERVAL 10 MINUTE AS expires_at")
        ->fetch_assoc()['expires_at']; // Use MySQL time

    // Insert OTP
    $stmt = $conn->prepare(INSERT_OTP);
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('sss', $phone, $code, $expiresAt);
    if (!$stmt->execute())
        throw new Exception('Execute failed: ' . $stmt->error);
    if ($stmt->affected_rows !== 1)
        throw new Exception('No OTP inserted');

    // Log attempt
    $stmt = $conn->prepare(INSERT_OTP_ATTEMPT);
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $status = 'success';
    $ip     = $GLOBALS['current_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown'; // Fallback IP
    $stmt->bind_param('sss', $phone, $status, $ip);
    if (!$stmt->execute())
        throw new Exception('Execute failed: ' . $stmt->error);
    if ($stmt->affected_rows !== 1)
        throw new Exception('No attempt logged');

    $conn->commit();
    error_log("OTP generated successfully: Phone=$phone, Code=$code, IP=$ip");
    respond(['message' => 'OTP generated successfully', 'otp' => $code]);
    }
catch (Exception $e) {
    $conn->rollback();

    $stmt = $conn->prepare(INSERT_OTP_ATTEMPT);
    if ($stmt) { // Only attempt logging if prepare succeeds
        $status = 'failed';
        $ip     = $GLOBALS['current_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->bind_param('sss', $phone, $status, $ip);
        $stmt->execute();
        }

    error_log("Send OTP Error: " . $e->getMessage() . " | Phone: $phone");
    respond(['error' => 'Failed to generate OTP', 'details' => $e->getMessage()], 500);
    } finally {
    $conn->close();
    }
