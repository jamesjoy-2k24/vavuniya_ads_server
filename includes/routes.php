<?php
// Set Routes for the API
function getRoutes()
    {
    return [
        'auth' => [
            'send_otp'   => ['POST', 'api/auth/send_otp.php'],
            'verify_otp' => ['POST', 'api/auth/verify_otp.php'],
        ],
    ];
    }
