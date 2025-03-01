<?php
// Set Routes for the API
function getRoutes()
    {
    return [
        'auth' => [
            'login'       => ['POST', 'api/auth/login.php'],
            'send_otp'    => ['POST', 'api/auth/send_otp.php'],
            'register'    => ['POST', 'api/auth/register.php'],
            'verify_otp'  => ['POST', 'api/auth/verify_otp.php'],
            'user_exists' => ['POST', 'api/auth/user_exists.php'],
        ],
    ];
    }
