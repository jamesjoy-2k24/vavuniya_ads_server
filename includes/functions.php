<?php

// Generate a 6-digit OTP
function generateOtp()
    {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

// Send JSON response with appropriate status code
function respond($data, $status = 200)
    {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
    }

// Validate and sanitize input fields
function validateInput($data, $requiredFields = [], $optionalFields = [])
    {
    $sanitizedData = [];

    // Check required fields
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && empty(trim($data[$field])))) {
            respond(['error' => "$field is required"], 400);
            }
        $sanitizedData[$field] = sanitizeField($data[$field], $field);
        }

    // Handle optional fields with defaults
    foreach ($optionalFields as $field => $default) {
        $sanitizedData[$field] = isset($data[$field]) ? sanitizeField($data[$field], $field) : $default;
        }

    return $sanitizedData;
    }

// Helper function to sanitize and validate specific fields
function sanitizeField($value, $field)
    {
    $value = is_string($value) ? trim($value) : $value;
    switch ($field) {
        case 'phone':
            $value = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
            if (!preg_match('/^\+94\d{9}$/', $value)) {
                respond(['error' => 'Phone number must be in +94XXXXXXXXX format'], 400);
                }
            break;
        case 'password':
            $value = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
            if (strlen($value) < 6) {
                respond(['error' => 'Password must be at least 6 characters'], 400);
                }
            break;
        case 'name': // For users or categories
            $value = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
            if (!preg_match('/^[a-zA-Z ]+$/', $value)) {
                respond(['error' => 'Name must contain only letters and spaces'], 400);
                }
            break;
        case 'code':
            $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            if (!preg_match('/^\d{6}$/', $value)) {
                respond(['error' => 'OTP must be a 6-digit number'], 400);
                }
            break;
        case 'title':
            $value = filter_var($value, FILTER_SANITIZE_STRING);
            if (strlen($value) < 3) {
                respond(['error' => 'Title must be at least 3 characters'], 400);
                }
            break;
        case 'description':
            $value = filter_var($value, FILTER_SANITIZE_STRING);
            break;
        case 'price':
            $value = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            if (!is_numeric($value) || $value < 0) {
                respond(['error' => 'Price must be a positive number'], 400);
                }
            break;
        case 'item_condition':
            $value = strtolower(filter_var($value, FILTER_SANITIZE_STRING));
            if (!in_array($value, ['new', 'used'])) {
                respond(['error' => 'Item condition must be "new" or "used"'], 400);
                }
            break;
        case 'status':
            $value = strtolower(filter_var($value, FILTER_SANITIZE_STRING));
            if (!in_array($value, ['active', 'pending', 'sold', 'deleted'])) {
                respond(['error' => 'Status must be "active", "pending", "sold", or "deleted"'], 400);
                }
            break;
        case 'category_id':
            $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            if ($value !== null && (!is_numeric($value) || $value <= 0)) {
                respond(['error' => 'Invalid category ID'], 400);
                }
            break;
        case 'images':
            $value = is_array($value) ? json_encode($value) : json_encode([]);
            break;
        case 'location':
            $value = filter_var($value, FILTER_SANITIZE_STRING);
            break;
        }
    return $value;
    }

// Generate JWT token
function generateJwt($userId)
    {
    require_once 'config/config.php';
    if (!defined('JWT_SECRET')) {
        error_log("JWT_SECRET not defined in config.php");
        respond(['error' => 'Server configuration error'], 500);
        }
    $header    = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload   = base64_encode(json_encode(['user_id' => $userId, 'exp' => time() + 3600])); // 1-hour expiry
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    return "$header.$payload.$signature";
    }

// Verify JWT
function verifyJwt($token)
    {
    require_once 'config/config.php';
    if (!defined('JWT_SECRET')) {
        error_log("JWT_SECRET not defined in config.php");
        respond(['error' => 'Server configuration error'], 500);
        }
    if (!$token || !preg_match('/^([^.]+)\.([^.]+)\.([^.]+)$/', $token, $matches)) {
        respond(['error' => 'Invalid JWT format'], 401);
        }
    $header            = $matches[1];
    $payload           = $matches[2];
    $signature         = $matches[3];
    $expectedSignature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if ($signature !== $expectedSignature) {
        respond(['error' => 'Invalid JWT signature'], 401);
        }
    $decodedPayload = json_decode(base64_decode($payload), true);
    if (!$decodedPayload || !isset($decodedPayload['user_id']) || !isset($decodedPayload['exp'])) {
        respond(['error' => 'Invalid JWT payload'], 401);
        }
    if ($decodedPayload['exp'] < time()) {
        respond(['error' => 'JWT expired'], 401);
        }
    return $decodedPayload['user_id'];
    }
