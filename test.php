<?php
// includes/router.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/routes.php';

function handleRequest($uri, $method)
    {
    global $routes;

    // Normalize URI
    $uri = trim($uri, '/');
    if (empty($uri)) {
        sendResponse(['error' => 'No endpoint specified'], 400);
        }

    // Check if route exists
    if (!isset($routes[$method][$uri])) {
        sendResponse(['error' => 'Endpoint not found'], 404);
        }

    $file = $routes[$method][$uri];

    // Define protected routes
    $protectedRoutes = [
        'ads/create' => true,
        'ads/update' => true,
        'ads/delete' => true,
    ];

    // Check if route is protected
    if (isset($protectedRoutes[$uri])) {
        $authHeader = getAuthorizationHeader();
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            sendResponse(['error' => 'Authorization token required'], 401);
            }

        $token  = $matches[1];
        $userId = verifyJwt($token); // From functions.php
        if (!$userId) {
            sendResponse(['error' => 'Invalid or expired token'], 401);
            }

        // Store user ID for use in endpoint
        $GLOBALS['user_id'] = $userId;
        }

    // Include the endpoint file
    if (file_exists($file)) {
        require_once $file;
        }
    else {
        sendResponse(['error' => 'Endpoint file not found'], 500);
        }
    }

// Handle the incoming request
$uri    = isset($_GET['uri']) ? $_GET['uri'] : '';
$method = $_SERVER['REQUEST_METHOD'];
handleRequest($uri, $method);
