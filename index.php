<?php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Set the default timezone
date_default_timezone_set($_ENV['TIMEZONE']);

// Basic error handling for production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Enable error reporting for development
error_reporting(E_ALL);

// Load headers and routing logic with safety checks
$requiredFiles = ['includes/headers.php', 'includes/router.php'];
foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        http_response_code(500);
        echo json_encode(['error' => 'Server configuration error']);
        exit;
        }
    require_once $file;
    }

// Parse URL and method with sanitization
$url = isset($_GET['url']) ? filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL) : '';

// Whitelist allowed HTTP methods
$allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
$method         = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, $allowedMethods)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
    }

// Handle the request
handleRequest($url, $method);
