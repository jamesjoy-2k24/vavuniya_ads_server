<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

header('Content-Type: application/json');

// Queries with consistent spacing
const SELECT_OTP = "SELECT * FROM otp WHERE phone = ? AND code = ? AND expires_at > NOW() AND used = 0";
const UPDATE_OTP = "UPDATE otp SET used = 1 WHERE id = ?";
const DELETE_OTP = "DELETE FROM otp WHERE phone = ? AND (expires_at <= NOW() OR used = 1) AND id != ?";

// Get and validate request data
$data  = json_decode(file_get_contents('php://input'), true);
$data  = validateInput($data, ['phone', 'code']);
$phone = $data['phone'];
$code  = $data['code'];

checkRateLimit($phone);

$conn = getDbConnection();
$conn->begin_transaction();

try {
    // Verify OTP existence
    $stmt = $conn->prepare(SELECT_OTP);
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('ss', $phone, $code);
    if (!$stmt->execute())
        throw new Exception('Execute failed: ' . $stmt->error);
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Invalid or expired OTP');
        }

    $otp = $result->fetch_assoc();

    // Mark OTP as used
    $stmt = $conn->prepare(UPDATE_OTP);
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('i', $otp['id']);
    if (!$stmt->execute())
        throw new Exception('Update failed: ' . $stmt->error);
    if ($stmt->affected_rows !== 1)
        throw new Exception('No OTP updated');

    // Clean up expired/used OTPs
    $stmt = $conn->prepare(DELETE_OTP);
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('si', $phone, $otp['id']);
    if (!$stmt->execute())
        throw new Exception('Delete failed: ' . $stmt->error);

    $conn->commit();
    error_log("OTP verified successfully for phone: $phone");
    respond(['message' => 'OTP verified successfully']);
    }
catch (Exception $e) {
    $conn->rollback();
    error_log("Verify OTP Error: " . $e->getMessage() . " | Phone: $phone, Code: $code");
    respond(['error' => $e->getMessage()], $e->getCode() ?: 400);
    } finally {
    $conn->close();
    }
