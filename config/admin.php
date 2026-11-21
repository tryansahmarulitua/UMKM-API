<?php

// The only admin account is created by the seeder during setup (PP §4.5: no public registration).
return [
    'name' => env('ADMIN_NAME', 'Admin'),
    'email' => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),
];
