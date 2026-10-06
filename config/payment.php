<?php

use App\Enums\PaymentStatus;

return [
    'fake' => [
        'status' => env('PAYMENT_FAKE_STATUS', PaymentStatus::PENDING->value),
    ],
];
