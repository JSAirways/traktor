<?php

return [
    'pin_length' => env('PIN_LENGTH', 4),
    'viewing_session_timeout' => env('VIEWING_SESSION_TIMEOUT', 86400),
    'pin_rate_limit_attempts' => env('PIN_RATE_LIMIT_ATTEMPTS', 5),
    'pin_rate_limit_window' => env('PIN_RATE_LIMIT_WINDOW', 15),
    // Cookie must outlive token TTL + grace so expired tokens can still refresh.
    'device_cookie_expiration' => env('DEVICE_COOKIE_EXPIRATION', 259200), // 180 days (minutes)
    'device_token_ttl_minutes' => env('DEVICE_TOKEN_TTL', 129600), // 90 days
    'device_token_grace_minutes' => env('DEVICE_TOKEN_GRACE_MINUTES', 129600), // 90 days
];

