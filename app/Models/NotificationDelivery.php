<?php

declare(strict_types=1);

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class NotificationDelivery extends Model
{
    protected $fillable = [
        'notification_id',
        'user_id',
        'channel',
        'status',
        'attempts',
        'available_at',
        'queued_at',
        'processing_at',
        'sent_at',
        'delivered_at',
        'provider_reference',
        'last_error_code',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'queued_at' => 'datetime',
            'processing_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(\\Illuminate\\Notifications\\DatabaseNotification::class, 'notification_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
