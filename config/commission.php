<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PROXIWORK commission policy
    |--------------------------------------------------------------------------
    |
    | The rate is configuration, not hard-coded in business logic. Production
    | environments should set PROXIWORK_COMMISSION_RATE explicitly.
    |
    */
    'rate' => (string) env('PROXIWORK_COMMISSION_RATE', '10.00'),
];
