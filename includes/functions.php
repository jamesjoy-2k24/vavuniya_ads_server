<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/response.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Generate a 6-digit OTP
function generateOtp()
    {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

// Validate and sanitize input fields
function validateInput($data, $requiredFields = [], $optionalFields = [])
    {
    $sanitizedData = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && empty(trim($data[$field])))) {
            sendResponse(['error' => "$field is required"], 400);
            }
        $sanitizedData[$field] = sanitizeField($data[$field], $field);
        }
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
                sendResponse(['error' => 'Phone number must be in +94XXXXXXXXX format'], 400);
                }
            break;
        case 'password':
            $value = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
            if (strlen($value) < 6) {
                sendResponse(['error' => 'Password must be at least 6 characters'], 400);
                }
            break;
        case 'name': // For users or categories
            $value = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
            if (!preg_match('/^[a-zA-Z ]+$/', $value)) {
                sendResponse(['error' => 'Name must contain only letters and spaces'], 400);
                }
            break;
        case 'code':
            $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            if (!preg_match('/^\d{6}$/', $value)) {
                sendResponse(['error' => 'OTP must be a 6-digit number'], 400);
                }
            break;
        case 'title':
            $value = filter_var($value, FILTER_SANITIZE_STRING);
            if (strlen($value) < 3) {
                sendResponse(['error' => 'Title must be at least 3 characters'], 400);
                }
            break;
        case 'description':
            $value = filter_var($value, FILTER_SANITIZE_STRING);
            break;
        case 'price':
            $value = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            if (!is_numeric($value) || $value < 0) {
                sendResponse(['error' => 'Price must be a positive number'], 400);
                }
            break;
        case 'item_condition':
            $value = strtolower(filter_var($value, FILTER_SANITIZE_STRING));
            if (!in_array($value, ['new', 'used'])) {
                sendResponse(['error' => 'Item condition must be "new" or "used"'], 400);
                }
            break;
        case 'status':
            $value = strtolower(filter_var($value, FILTER_SANITIZE_STRING));
            if (!in_array($value, ['active', 'pending', 'sold', 'deleted'])) {
                sendResponse(['error' => 'Status must be "active", "pending", "sold", or "deleted"'], 400);
                }
            break;
        case 'category_id':
            $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            if ($value !== null && (!is_numeric($value) || $value <= 0)) {
                sendResponse(['error' => 'Invalid category ID'], 400);
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

// Generate JWT (using firebase/php-jwt)
function generateJwt($userId)
    {
    require_once __DIR__ . '/../config/config.php';
    $payload = [
        'iat' => time(),
        'exp' => time() + 3600, // 1 hour
        'sub' => $userId
    ];
    return JWT::encode($payload, JWT_SECRET, 'HS256');
    }

// Verify JWT
function verifyJwt($token)
    {
    require_once __DIR__ . '/../config/config.php';
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        return $decoded->sub;
        }
    catch (Exception $e) {
        error_log("JWT Error: " . $e->getMessage());
        sendResponse(['error' => 'Invalid or expired token'], 401);
        }
    }
// Get Auth Header
function getAuthorizationHeader(): ?string
    {
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return $_SERVER['HTTP_AUTHORIZATION'];
        }
    elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
    elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        return $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }
    return null;
    }

// Get Input Data
function getInputData(): array
    {
    if (isset($_POST['data'])) {
        $data = json_decode($_POST['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in data field', 400);
            }
        return $data;
        }
    return json_decode(file_get_contents('php://input'), true) ?? [];
    }
