<?php

return [
    'driver' => env('HASH_DRIVER', 'bcrypt'),
    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => true,
        'limit' => 0,
    ],
    'rehash_on_login' => true,
];
