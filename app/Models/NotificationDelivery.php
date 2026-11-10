<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\DatabaseNotification;

class NotificationDelivery extends Model
{
    protected static function booted(): void
    {
        static::updated(function (NotificationDelivery $delivery): void {
            if ($delivery->wasChanged('attempts') && $delivery->status === 'PROCESSING') {
                $delivery->recordAttemptStart();
            }

            if ($delivery->wasChanged('status') && $delivery->status !== 'PROCESSING') {
                $delivery->recordAttemptFinish();
            }
        });
    }

    protected $fillable = [
        'notification_id',
        'user_id',
        'recipient_hint',
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

    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(NotificationDeliveryAttempt::class, 'notification_delivery_id')->orderBy('attempt_number');
    }

    public function recordAttemptStart(): void
    {
        if ($this->attempts < 1) {
            return;
        }

        $this->attempts()->firstOrCreate(
            ['attempt_number' => $this->attempts],
            [
                'channel' => $this->channel,
                'status' => 'PROCESSING',
                'started_at' => $this->processing_at ?? now(),
            ],
        );
    }

    public function recordAttemptFinish(): void
    {
        $attempt = $this->attempts()->where('attempt_number', $this->attempts)->first();
        if ($attempt === null) {
            return;
        }

        $attempt->update([
            'status' => $this->status,
            'provider_reference' => $this->provider_reference,
            'error_code' => $this->last_error_code,
            'finished_at' => now(),
        ]);
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(DatabaseNotification::class, 'notification_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
