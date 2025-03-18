<?php
// includes/router.php
require_once __DIR__ . '/routes.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/security.php';


// Handle incoming requests
function handleRequest($url, $method)
    {
    $basePath = 'api/';
    if (strpos($url, $basePath) === 0) {
        $url = substr($url, strlen($basePath));
        }

    $parts    = explode('/', $url);
    $resource = isset($parts[0]) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $parts[0]) : '';
    $action   = isset($parts[1]) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $parts[1]) : '';
    $param    = isset($parts[2]) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $parts[2]) : '';

    $routes = getRoutes();
    if (!is_array($routes)) {
        error_log("Routing configuration error: Invalid routes structure");
        sendResponse(['error' => 'Routing configuration error'], 500);
        }

    if (empty($resource)) {
        sendResponse(['error' => 'No resource specified'], 400);
        }

    if (!array_key_exists($resource, $routes)) {
        sendResponse(['error' => "Resource '$resource' not found"], 404);
        }

    if (empty($action)) {
        sendResponse(['error' => "No action specified for resource '$resource'"], 400);
        }

    if (!array_key_exists($action, $routes[$resource])) {
        sendResponse(['error' => "Action '$action' not found for resource '$resource'"], 404);
        }

    // Auth
    $protectedRoutes = ['ads/create', 'ads/update', 'ads/delete'];
    if (in_array("$resource/$action", $protectedRoutes)) {
        require_once __DIR__ . '/functions.php';
        $authHeader = getAuthorizationHeader();
        if (!$authHeader || !preg_match('/Bearer (.+)/', $authHeader, $matches)) {
            sendResponse(['error' => 'Authorization required'], 401);
            }
        $token              = $matches[1];
        $userId             = verifyJwt($token);
        $GLOBALS['user_id'] = $userId; // Pass to endpoint
        }

    // Rate limit for OTP routes
    $otpRoutes = ['auth/send_otp', 'auth/verify_otp'];
    $routeKey  = "$resource/$action";
    if (in_array($routeKey, $otpRoutes)) {
        $data  = json_decode(file_get_contents('php://input'), true) ?? [];
        $phone = $data['phone'] ?? '';
        checkRateLimit($phone);
        }

    // Validate method and endpoint file
    $route         = $routes[$resource][$action];
    $allowedMethod = $route[0];
    $file          = $route[1];
    if ($method !== $allowedMethod) {
        sendResponse(['error' => "Method '$method' not allowed for '$resource/$action'. Use '$allowedMethod'"], 405);
        }

    // Secure file inclusion to prevent path traversal
    $baseDir  = realpath(__DIR__ . '/../api/');
    $filePath = realpath(__DIR__ . '/../' . $file);
    if ($filePath === false || strpos($filePath, $baseDir) !== 0 || !file_exists($filePath)) {
        error_log("Invalid endpoint file for '$resource/$action': $file");
        sendResponse(['error' => "Invalid endpoint file for '$resource/$action'"], 500);
        }

    require_once $filePath;
    }
