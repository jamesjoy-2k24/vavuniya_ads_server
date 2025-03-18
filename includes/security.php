<?php
// includes/security.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/response.php';

const SELECT_COUNT_BY_PHONE = "SELECT COUNT(*) as attempts FROM otp_attempts WHERE phone = ? AND created_at > NOW() - INTERVAL ? SECOND";
const SELECT_COUNT_BY_IP    = "SELECT COUNT(*) as attempts FROM otp_attempts WHERE ip_address = ? AND created_at > NOW() - INTERVAL ? SECOND";
const INSERT_OTP_ATTEMPT    = "INSERT INTO otp_attempts (phone, attempt_type, status, ip_address) VALUES (?, ?, ?, ?)";

function checkRateLimit($phone)
    {
    if (is_array($phone)) {
        $phone = $phone[0] ?? '';
        error_log("Phone was an array, converted to: $phone");
        }
    $phone = filter_var($phone, FILTER_SANITIZE_STRING);
    if (empty($phone)) {
        sendResponse(['status' => 'error', 'message' => 'Invalid phone number'], 400);
        }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'];
        $ip        = is_array($forwarded) ? ($forwarded[0] ?? 'unknown') : explode(',', $forwarded)[0];
        }
    $ip = filter_var($ip, FILTER_SANITIZE_STRING);

    $conn   = getDbConnection();
    $window = OTP_RATE_WINDOW;
    $limit  = OTP_RATE_LIMIT;

    try {
        error_log("Binding phone: $phone, window: $window");
        $stmt = $conn->prepare(SELECT_COUNT_BY_PHONE);
        if (!$stmt) {
            throw new Exception('Database prepare error', 500);
            }
        $stmt->bind_param('si', $phone, $window);
        $stmt->execute();
        $phoneAttempts = $stmt->get_result()->fetch_assoc()['attempts'];

        error_log("Binding ip: $ip, window: $window");
        $stmt = $conn->prepare(SELECT_COUNT_BY_IP);
        if (!$stmt) {
            throw new Exception('Database prepare error', 500);
            }
        $stmt->bind_param('si', $ip, $window);
        $stmt->execute();
        $ipAttempts = $stmt->get_result()->fetch_assoc()['attempts'];

        if ($phoneAttempts >= $limit || $ipAttempts >= $limit) {
            sendResponse(['status' => 'error', 'message' => 'Too many OTP requests. Try again later.'], 429);
            }

        // Insert attempt with all fields
        $stmt = $conn->prepare(INSERT_OTP_ATTEMPT);
        if (!$stmt) {
            throw new Exception('Database prepare error', 500);
            }
        $attemptType = 'phone'; // Fixed value as in send_otp.php
        $status      = 'pending';    // Default status, updated in send_otp.php
        $stmt->bind_param('ssss', $phone, $attemptType, $status, $ip);
        $stmt->execute();

        return $ip;
        }
    catch (mysqli_sql_exception $e) {
        error_log("Rate limit check failed: " . $e->getMessage());
        sendResponse(['status' => 'error', 'message' => 'Internal server error'], 500);
        } finally {
        $conn->close();
        }
    }
