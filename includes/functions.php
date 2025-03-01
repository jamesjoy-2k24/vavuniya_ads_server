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
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            sendResponse(['error' => "$field is required"], 400);
            }
        if ($field === 'phone') {
            $data[$field] = trim($data[$field]);
            $data[$field] = filter_var($data[$field], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
            if (!preg_match('/^\+94\d{9}$/', $data[$field])) {
                sendResponse(['error' => 'Phone must be in +94XXXXXXXXX format'], 400);
                }
            }
        if ($field === 'name') {
            $data[$field] = trim($data[$field]);
            $data[$field] = filter_var($data[$field], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
            if (!preg_match('/^[a-zA-Z ]+$/', $data[$field])) {
                sendResponse(['error' => 'Name must contain only letters and spaces'], 400);
                }
            }

        if ($field === 'password') {
            $data[$field] = trim($data[$field]);
            $data[$field] = filter_var($data[$field], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
            if (strlen($data[$field]) < 6) {
                sendResponse(['error' => 'Password must be at least 6 characters long'], 400);
                }
            }
        }
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
