<?php

// Generate OTP
function generateOtp()
    {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

// Respond with JSON data
function respond($data, $status = 200)
    {
    http_response_code($status);
    echo json_encode($data);
    exit;
    }

// Validate input fields
function validateInput($data, $fields)
    {
    $sanitizedData = $data; // Preserve original array
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            sendResponse(['error' => "$field is required"], 400);
            }

        $value = trim($data[$field]);
        switch ($field) {
            case 'phone':
                $value = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
                if (!preg_match('/^\+94\d{9}$/', $value)) {
                    sendResponse(['error' => 'Phone number is invalid'], 400);
                    }
                break;
            case 'password':
                $value = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
                if (strlen($value) < 6) {
                    sendResponse(['error' => 'Password must be at least 6 characters'], 400);
                    }
                break;
            case 'name':
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
            }
        $sanitizedData[$field] = $value; 
        }
    return $sanitizedData;
    }

// Generate JWT
function generateJwt($userId)
    {
    require_once 'config/config.php';
    $header    = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload   = base64_encode(json_encode(['user_id' => $userId, 'exp' => time() + 3600]));
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    return "$header.$payload.$signature";
    }
