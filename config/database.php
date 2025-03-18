<?php
function getDbConnection()
    {
    // Load environment variables if not already loaded
    if (!isset($_ENV['DB_HOST'])) {
        require_once __DIR__ . '/../vendor/autoload.php'; // Adjust path if needed
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
        }

    // Use environment variables with defaults
    $host   = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? 'vavuniya_ads';
    $user   = $_ENV['DB_USER'] ?? 'root';
    $pass   = $_ENV['DB_PASS'] ?? '';

    // Ensure response.php is available for error handling
    require_once __DIR__ . '/../includes/response.php'; // Adjust path if needed

    // Set strict mode for mysqli
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn = new mysqli($host, $user, $pass, $dbname);
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            sendResponse(['status' => 'error', 'message' => 'Database connection error'], 500);
            }

        // Set UTF-8 charset
        if (!$conn->set_charset('utf8mb4')) {
            error_log("Failed to set charset: " . $conn->error);
            sendResponse(['status' => 'error', 'message' => 'Database configuration error'], 500);
            }

        return $conn;
        }
    catch (mysqli_sql_exception $e) {
        error_log("Database error: " . $e->getMessage());
        sendResponse(['status' => 'error', 'message' => 'Internal server error'], 500);
        }
    }
