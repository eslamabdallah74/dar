<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Super Admin Credentials
    |--------------------------------------------------------------------------
    |
    | Configuration for the platform central administrator. These values
    | are read from environment variables and used for system administration.
    |
    */
    'email' => env('ADMIN_EMAIL', (env('APP_ENV') === 'production' ? null : 'admin@dar.rent')),
    'password' => env('ADMIN_PASSWORD', (env('APP_ENV') === 'production' ? null : 'AdminDar2026!')),
    'name' => env('ADMIN_NAME', 'مدير النظام المركزي'),
];
