<?php

use App\Enums\PaymentStatus;

return [
    // The fake gateway is intentionally unavailable in production unless a real provider is integrated.
    'simulation_enabled' => filter_var(env('PAYMENT_SIMULATION_ENABLED', env('APP_ENV', 'production') !== 'production'), FILTER_VALIDATE_BOOL),

    'fake' => [
        'status' => env('PAYMENT_FAKE_STATUS', PaymentStatus::PENDING->value),
    ],

    'webhooks' => [
        'secrets' => [
            'fake' => env('PAYMENT_WEBHOOK_FAKE_SECRET', ''),
        ],
    ],
];
