<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

const INSERT_OTP         = "INSERT INTO otp (phone, code, expires_at) VALUES (?, ?, ?)";
const UPDATE_OTP_ATTEMPT = "UPDATE otp_attempts SET status = ? WHERE phone = ? AND ip_address = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1";

// Get and validate input
$data  = json_decode(file_get_contents('php://input'), true);
$data  = validateInput($data, ['phone']);
$phone = $data['phone'];

$ip   = checkRateLimit($phone);
$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Generate OTP and expiration
    $code      = generateOtp();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Insert OTP
    $stmt = $conn->prepare(INSERT_OTP);
    if (!$stmt) {
        throw new Exception('Database prepare error', 500);
        }
    $stmt->bind_param('sss', $phone, $code, $expiresAt);
    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        throw new Exception('OTP insertion failed', 500);
        }

    // Update attempt status to 'success'
    $stmt = $conn->prepare(UPDATE_OTP_ATTEMPT);
    if (!$stmt) {
        throw new Exception('Database prepare error', 500);
        }
    $status = 'success';
    $stmt->bind_param('sss', $status, $phone, $ip);
    $stmt->execute();

    $conn->commit();
    error_log("OTP sent: Phone=$phone, Code=$code, IP=$ip");
    sendResponse(['message' => 'OTP sent successfully', 'otp' => $code]);
    }
catch (Exception $e) {
    $conn->rollback();

    // Update attempt status to 'failed'
    $stmt = $conn->prepare(UPDATE_OTP_ATTEMPT);
    if ($stmt) {
        $status = 'failed';
        $stmt->bind_param('sss', $status, $phone, $ip);
        $stmt->execute();
        }

    error_log("Send OTP Error: " . $e->getMessage() . " | Phone: $phone");
    sendResponse(['error' => 'Failed to send OTP'], 500);
    } finally {
    $conn->close();
    }
