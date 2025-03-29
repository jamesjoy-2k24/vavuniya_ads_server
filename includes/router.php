<?php
require_once __DIR__ . '/routes.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

function handleRequest($url, $method)
    {
    global $routes;

    $basePath = 'api/';
    if (strpos($url, $basePath) === 0) {
        $url = substr($url, strlen($basePath));
        }
    $url = trim($url, '/');

    $parts    = explode('/', $url);
    $resource = isset($parts[0]) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $parts[0]) : '';
    $action   = isset($parts[1]) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $parts[1]) : '';
    $param    = isset($parts[2]) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $parts[2]) : '';
    $routeKey = "$resource/$action";

    $routes = getRoutes();
    if (!is_array($routes)) {
        error_log("Routing configuration error: Invalid routes structure");
        sendResponse(['error' => 'Internal server error'], 500);
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

    $route         = $routes[$resource][$action];
    $allowedMethod = $route[0];
    $file          = $route[1];
    if ($method !== $allowedMethod) {
        sendResponse(['error' => "Method '$method' not allowed for '$routeKey'. Use '$allowedMethod'"], 405);
        }

    $protectedRoutes = [
        'ads/create'        => true,
        'ads/update'        => true,
        'ads/delete'        => true,
        'ads/my_ads'        => true,
        'categories/create' => true,
        'categories/update' => true,
        'categories/delete' => true,
        'favorites/add'     => true,
        'favorites/remove'  => true,
        'favorites/list'    => true,
        'user/me'           => true,
        'user/my_ads'       => true,
        'user/update_ads'   => true,
        'user/delete_ads'   => true,
        'user/undo_delete'  => true,
        'user/password'     => true,
        'user/update_user'  => true,
    ];

    $routeKey = "$resource/$action";
    if (isset($protectedRoutes[$routeKey])) {
        $authHeader = getAuthorizationHeader();
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            sendResponse(['error' => 'Authorization token required'], 401);
            }
        $token    = $matches[1];
        $userData = verifyJwt($token);
        if (!$userData) {
            sendResponse(['error' => 'Invalid or expired token'], 401);
            }
        $GLOBALS['user_id']   = $userData['id'];
        $GLOBALS['user_role'] = $userData['role'];
        }

    $otpRoutes = ['auth/send_otp', 'auth/verify_otp'];
    if (in_array($routeKey, $otpRoutes)) {
        $data  = getInputData();
        $phone = $data['phone'] ?? '';
        if (empty($phone)) {
            sendResponse(['error' => 'Phone number required for OTP request'], 400);
            }
        checkRateLimit($phone);
        }

    $baseDir  = realpath(__DIR__ . '/../api/');
    $filePath = realpath(__DIR__ . '/../' . $file);
    if ($filePath === false || strpos($filePath, $baseDir) !== 0 || !file_exists($filePath)) {
        error_log("Invalid endpoint file for '$routeKey': $file");
        sendResponse(['error' => 'Internal server error'], 500);
        }

    require_once $filePath;
    }
