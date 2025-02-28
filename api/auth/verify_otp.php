<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

header('Content-Type: application/json');

// Queries
const UPDATE_OTP = "UPDATE otp SET used = 1 WHERE id = ?";
const DELETE_OTP = "DELETE FROM otp WHERE phone = ? AND (expires_at <= NOW() OR used = 1) AND id !=?";
const SELECT_OTP = "SELECT * FROM otp WHERE phone = ? AND code = ? AND expires_at > NOW() AND used = 0";

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);
validateInput($data, ['phone', 'code']);
$phone = trim($data['phone']);
$code  = trim($data['code']);

// Check the rate limit
checkRateLimit($phone);

$conn = getDbConnection();
$conn->begin_transaction();

try {
    $stmt = $conn->prepare(SELECT_OTP);
    $stmt->bind_param('ss', $phone, $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Invalid or expired OTP');
        }

    $otp = $result->fetch_assoc();

    $stmt = $conn->prepare(UPDATE_OTP);
    $stmt->bind_param('i', $otp['id']);
    $stmt->execute();

    $stmt = $conn->prepare(DELETE_OTP);
    $stmt->bind_param('si', $phone, $otp['id']);
    $stmt->execute();

    $conn->commit();
    respond(['message' => 'OTP verified successfully']);
    }
catch (Exception $e) {
    $conn->rollback();
    error_log("Verify OTP Error: " . $e->getMessage());
    respond(['error' => $e->getMessage()], $e->getCode() ?: 400);
    } finally {
    $conn->close();
    }
