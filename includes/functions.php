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
        if ($field === 'phone' && !preg_match('/^\+94\d{9}$/', $data[$field])) {
            sendResponse(['error' => 'Phone must be in +94XXXXXXXXX format'], 400);
            }
        }
    }
