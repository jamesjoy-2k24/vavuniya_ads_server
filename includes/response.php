<?php
// Send API response with proper headers and error handling
function sendResponse($data, $status = 200)
    {
    header('Content-Type: application/json; charset=UTF-8');

    // Validate status code
    $status = filter_var($status, FILTER_VALIDATE_INT, ['options' => ['min_range' => 100, 'max_range' => 599]]) ? $status : 500;

    // Set the HTTP response code
    http_response_code($status);

    // Attempt to encode data as JSON
    try {
        $jsonData = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        echo $jsonData;
        }
    catch (JsonException $e) {
        error_log("JSON encoding failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
        }

    exit;
    }
