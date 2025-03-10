<?php
// Set Routes for the API
function getRoutes()
    {
    return [
        'auth'       => [
            'login'       => ['POST', 'api/auth/login.php'],
            'send_otp'    => ['POST', 'api/auth/send_otp.php'],
            'register'    => ['POST', 'api/auth/register.php'],
            'verify_otp'  => ['POST', 'api/auth/verify_otp.php'],
            'user_exists' => ['POST', 'api/auth/user_exists.php'],
        ],
        'ads'        => [
            'list'   => ['POST', 'api/ads/list.php'],
            'show'   => ['POST', 'api/ads/show.php'],
            'create' => ['POST', 'api/ads/create.php'],
            'update' => ['POST', 'api/ads/update.php'],
            'delete' => ['DELETE', 'api/ads/delete.php'],
        ],
        'categories' => [
            'list'   => ['GET', 'api/categories/list.php'],
            'show'   => ['GET', 'api/categories/show.php'],
            'create' => ['POST', 'api/categories/create.php'],
            'update' => ['POST', 'api/categories/update.php'],
            'delete' => ['DELETE', 'api/categories/delete.php'],
        ]
    ];
    }
