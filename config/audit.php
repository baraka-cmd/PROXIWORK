<?php

declare(strict_types=1);

return [
    'retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 180),
];
