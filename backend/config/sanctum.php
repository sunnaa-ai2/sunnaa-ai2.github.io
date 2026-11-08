<?php

return [
    // The site is on another origin and uses bearer tokens, so no stateful (cookie) domains.
    'stateful' => [],
    'guard' => ['web'],
    'expiration' => null, // per-token expiry is set when the token is created (see AuthController)
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'sai2_'),
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
