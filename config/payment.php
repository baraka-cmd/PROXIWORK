<?php

use App\Enums\PaymentStatus;

return [
    'fake' => [
        'status' => env('PAYMENT_FAKE_STATUS', PaymentStatus::PENDING->value),
    ],

    'webhooks' => [
        'secrets' => [
            'fake' => env('PAYMENT_WEBHOOK_FAKE_SECRET', ''),
        ],
    ],
];
