<?php

use App\Enums\PaymentStatus;

return [
    'default_provider' => env('PAYMENT_DEFAULT_PROVIDER', 'fake'),

    'fake' => [
        'status' => env('PAYMENT_FAKE_STATUS', PaymentStatus::PENDING->value),
    ],
];
