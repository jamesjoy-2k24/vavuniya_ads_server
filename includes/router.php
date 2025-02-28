<?php
require_once 'routes.php';
require_once 'response.php';

function handleRequest($url, $method)
    {
    // Remove 'api/' prefix and split URL
    $basePath = 'api/';
    if (strpos($url, $basePath) === 0) {
        $url = substr($url, strlen($basePath));
        }
    $parts    = explode('/', $url);
    $resource = $parts[0] ?? '';
    $action   = $parts[1] ?? '';
    $param    = $parts[2] ?? '';

    $routes = getRoutes();

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
        sendResponse(['error' => "Method '$method' not allowed for '$resource/$action'. Use '$allowedMethod'"], 405);
        }

    if (file_exists($file)) {
        require_once $file;
        }
    else {
        sendResponse(['error' => "Endpoint file not found for '$resource/$action'"], 500);
        }
    }
