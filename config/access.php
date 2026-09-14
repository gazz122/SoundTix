<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Access Whitelist
    |--------------------------------------------------------------------------
    |
    | Only email addresses listed here may authenticate with the `admin` role.
    | The system owner seeds the admin account via `ADMIN_EMAIL` in the
    | environment file.
    |
    */

    'admin_emails' => array_values(array_filter(array_map(
        fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAIL', '')),
    ))),

    'admin_password' => (string) env('ADMIN_PASSWORD', 'password'),
];
