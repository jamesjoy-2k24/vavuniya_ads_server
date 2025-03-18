<?php
function getRoutes()
    {
    $baseEndpointDir = 'api/';
    $allowedMethods  = ['GET', 'POST', 'PUT', 'DELETE'];

    // Routes
    $routes = [
        'auth'       => [
            'login'       => ['POST', $baseEndpointDir . 'auth/login.php'],
            'send_otp'    => ['POST', $baseEndpointDir . 'auth/send_otp.php'],
            'register'    => ['POST', $baseEndpointDir . 'auth/register.php'],
            'verify_otp'  => ['POST', $baseEndpointDir . 'auth/verify_otp.php'],
            'user_exists' => ['POST', $baseEndpointDir . 'auth/user_exists.php'],
        ],
        'ads'        => [
            'list'   => ['GET', $baseEndpointDir . 'ads/list.php'],
            'show'   => ['GET', $baseEndpointDir . 'ads/show.php'],
            'create' => ['POST', $baseEndpointDir . 'ads/create.php'],
            'update' => ['PUT', $baseEndpointDir . 'ads/update.php'],
            'delete' => ['DELETE', $baseEndpointDir . 'ads/delete.php'],
        ],
        'categories' => [
            'list'   => ['GET', $baseEndpointDir . 'categories/list.php'],
            'show'   => ['GET', $baseEndpointDir . 'categories/show.php'],
            'create' => ['POST', $baseEndpointDir . 'categories/create.php'],
            'update' => ['PUT', $baseEndpointDir . 'categories/update.php'],
            'delete' => ['DELETE', $baseEndpointDir . 'categories/delete.php'],
        ],
        'uploads'    => [
            'get' => ['GET', $baseEndpointDir . 'uploads/get.php'],
        ],
    ];

    // Validate routes during definition
    foreach ($routes as $resource => $actions) {
        foreach ($actions as $action => $route) {
            $method = $route[0];
            $file   = $route[1];

            // Check if method is valid
            if (!in_array($method, $allowedMethods)) {
                error_log("Invalid HTTP method '$method' for route '$resource/$action'");
                }

            // Check if file exists (optional during development)
            if (!file_exists($file)) {
                error_log("Route file not found: '$file' for '$resource/$action'");
                }
            }
        }

    return $routes;
    }
