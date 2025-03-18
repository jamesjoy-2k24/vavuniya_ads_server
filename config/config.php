<?php
if (!isset($_ENV['JWT_SECRET'])) {
    require_once __DIR__ . '/../vendor/autoload.php'; // Adjust path if needed
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
    }

// Configuration constants
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'vavuniya_ads_2025'); // Secret key for JWT tokens
define('OTP_RATE_LIMIT', (int) ($_ENV['OTP_RATE_LIMIT'] ?? 5));    // Max OTP requests per window
define('OTP_RATE_WINDOW', (int) ($_ENV['OTP_RATE_WINDOW'] ?? 15 * 60)); // Window in seconds (15 minutes)

