<?php
define('DB_NAME', 'vavuniya_ads');
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDbConnection()
    {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
        }
    // Enable error reporting for debugging
    $conn->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;
    return $conn;
    }
