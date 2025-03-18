<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';

header('Content-Type: application/json; charset=UTF-8');

const SELECT_OTP          = "SELECT id FROM otp WHERE phone = ? AND code = ? AND expires_at > NOW() AND used = 0";
const UPDATE_OTP          = "UPDATE otp SET used = 1 WHERE id = ?";
const SELECT_USER         = "SELECT id FROM users WHERE phone = ?";
const DELETE_ALL_OTP      = "DELETE FROM otp WHERE phone = ?";
const DELETE_ALL_ATTEMPTS = "DELETE FROM otp_attempts WHERE phone = ?";
const UPDATE_OTP_ATTEMPT  = "UPDATE otp_attempts SET status = ? WHERE phone = ? AND ip_address = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1";

// Get and validate input
$data  = json_decode(file_get_contents('php://input'), true);
$data  = validateInput($data, ['phone', 'code']);
$phone = $data['phone'];
$code  = $data['code'];

$ip   = checkRateLimit($phone);
$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Verify OTP
    $stmt = $conn->prepare(SELECT_OTP);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('ss', $phone, $code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Invalid or expired OTP', 400);
        }
    $otpId = $result->fetch_assoc()['id'];

    // Mark OTP as used
    $stmt = $conn->prepare(UPDATE_OTP);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('i', $otpId);
    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        throw new Exception('Failed to update OTP', 500);
        }

    // Clean up: Delete all OTPs and attempts for this phone
    $stmt = $conn->prepare(DELETE_ALL_OTP);
    if (!$stmt)
        throw new Exception('Database error', 500);
    $stmt->bind_param('s', $phone);
    $stmt->execute();

    $stmt = $conn->prepare(DELETE_ALL_ATTEMPTS);
    if (!$stmt)
        throw new Exception('Database error', 500);
    $stmt->bind_param('s', $phone);
    $stmt->execute();

    // Update attempt status to 'success'
    $stmt = $conn->prepare(UPDATE_OTP_ATTEMPT);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $status = 'success';
    $stmt->bind_param('sss', $status, $phone, $ip);
    $stmt->execute();

    // Get user ID for JWT (assuming phone links to a user)
    $stmt = $conn->prepare(SELECT_USER);
    if (!$stmt) {
        throw new Exception('Database error', 500);
        }
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('User not found', 404);
        }
    $userId = $result->fetch_assoc()['id'];

    // Generate JWT
    $token = generateJwt($userId);

    $conn->commit();
    error_log("OTP verified: Phone=$phone, UserID=$userId");
    sendResponse(['message' => 'OTP verified successfully', 'token' => $token]);
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

    error_log("Verify OTP Error: " . $e->getMessage() . " | Phone: $phone, Code: $code");
    sendResponse(['error' => $e->getMessage()], $e->getCode() ?: 400);
    } finally {
    $conn->close();
    }
