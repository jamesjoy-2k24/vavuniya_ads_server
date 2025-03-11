<?php
define('DB_NAME', 'vavuniya_ads');
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDbConnection()
    {
    // Use require_once to ensure functions.php is available
    require_once 'includes/functions.php';

    // Set strict mode before connection for consistency
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        // Use respond() for consistent error format
        respond(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error], 500);
        }

    // Set UTF-8 charset
    if (!$conn->set_charset('utf8mb4')) {
        respond(['status' => 'error', 'message' => 'Failed to set charset: ' . $conn->error], 500);
        }

    return $conn;
    }
