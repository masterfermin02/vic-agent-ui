<?php

return [
    'api_url' => env('VICIDIAL_API_URL', 'http://127.0.0.1/agc/api.php'),
    'ami' => [
        'host' => env('VICIDIAL_AMI_HOST', '127.0.0.1'),
        'port' => (int) env('VICIDIAL_AMI_PORT', 5038),
        'user' => env('VICIDIAL_AMI_USER', ''),
        'secret' => env('VICIDIAL_AMI_SECRET', ''),
    ],
];
