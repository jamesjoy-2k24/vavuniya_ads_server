<?php
// Set response headers
function sendResponse($data, $status = 200)
    {
    http_response_code($status);
    echo json_encode($data);
    exit;
    }
