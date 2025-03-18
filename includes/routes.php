<?php
// includes/routes.php
function getRoutes()
    {
    $baseEndpointDir = 'api/';
    $allowedMethods  = ['GET', 'POST', 'PUT', 'DELETE'];

    // Routes
    $routes = [
        'auth'       => [
            'login'      => ['POST', $baseEndpointDir . 'auth/login.php'],
            'send_otp'   => ['POST', $baseEndpointDir . 'auth/send_otp.php'],
            'register'   => ['POST', $baseEndpointDir . 'auth/register.php'],
            'verify_otp' => ['POST', $baseEndpointDir . 'auth/verify_otp.php'],
        ],
        'ads'        => [
            'list'   => ['GET', $baseEndpointDir . 'ads/list.php'],
            'show'   => ['GET', $baseEndpointDir . 'ads/show.php'],
            'create' => ['POST', $baseEndpointDir . 'ads/create.php'],
            'update' => ['PUT', $baseEndpointDir . 'ads/update.php'],
            'delete' => ['DELETE', $baseEndpointDir . 'ads/delete.php'],
            'my_ads' => ['GET', $baseEndpointDir . 'ads/my_ads.php'],
        ],
        'categories' => [
            'list'   => ['GET', $baseEndpointDir . 'categories/list.php'],
            'show'   => ['GET', $baseEndpointDir . 'categories/show.php'],
            'create' => ['POST', $baseEndpointDir . 'categories/create.php'],
            'update' => ['PUT', $baseEndpointDir . 'categories/update.php'],
            'delete' => ['DELETE', $baseEndpointDir . 'categories/delete.php'],
        ],
    ];

    // Validate routes during definition
    foreach ($routes as $resource => $actions) {
        foreach ($actions as $action => $route) {
            $method = $route[0];
            $file   = realpath(__DIR__ . '/../' . $route[1]);

            // Validate HTTP method
            if (!in_array($method, $allowedMethods)) {
                error_log("Invalid HTTP method '$method' for route '$resource/$action'. Allowed: " . implode(', ', $allowedMethods));
                }

            // Validate file existence
            if ($file === false || !file_exists($file)) {
                error_log("Route file not found: '{$route[1]}' for '$resource/$action'");
                }
            }
        }

    return $routes;
    }
