<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Queries
const SELECT_COUNT_BY_PHONE = "SELECT COUNT(*) as attempts FROM otp_attempts WHERE phone = ? AND created_at > NOW() - INTERVAL ? SECOND";
const SELECT_COUNT_BY_IP    = "SELECT COUNT(*) as attempts FROM otp_attempts WHERE ip_address = ? AND created_at > NOW() - INTERVAL ? SECOND";

// Rate limit check
function checkRateLimit($phone)
    {
    $conn   = getDbConnection();
    $window = OTP_RATE_WINDOW;
    $limit  = OTP_RATE_LIMIT;
    $ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stmt = $conn->prepare(SELECT_COUNT_BY_PHONE);
    $stmt->bind_param('si', $phone, $window);
    $stmt->execute();
    $phoneAttempts = $stmt->get_result()->fetch_assoc()['attempts'];

    $stmt = $conn->prepare(SELECT_COUNT_BY_IP);
    $stmt->bind_param('si', $ip, $window);
    $stmt->execute();
    $ipAttempts = $stmt->get_result()->fetch_assoc()['attempts'];

    if ($phoneAttempts >= $limit || $ipAttempts >= $limit) {
        respond(['error' => 'Too many OTP requests'], 429);
        }

    $GLOBALS['current_ip'] = $ip;
    }
