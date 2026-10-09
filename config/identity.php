<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API token lifetime (minutes)
    |--------------------------------------------------------------------------
    |
    | Personal access tokens issued at register/login expire after this many
    | minutes. Cookie-based Sanctum SPA sessions are unaffected.
    |
    */

    'token_expiration_minutes' => (int) env('IDENTITY_TOKEN_EXPIRATION_MINUTES', 60 * 24 * 30),

    'token_name' => env('IDENTITY_TOKEN_NAME', 'api'),

    /*
    |--------------------------------------------------------------------------
    | Password constraints
    |--------------------------------------------------------------------------
    */

    'password_min' => 12,
    'password_max' => 128,

    /*
    |--------------------------------------------------------------------------
    | Reserved usernames (case-insensitive)
    |--------------------------------------------------------------------------
    */

    'reserved_usernames' => [
        'admin',
        'administrator',
        'api',
        'auth',
        'help',
        'login',
        'logout',
        'me',
        'null',
        'profile',
        'register',
        'root',
        'settings',
        'support',
        'system',
        'user',
        'users',
    ],

];
