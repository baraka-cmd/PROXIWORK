<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecuritySession extends Model
{
    protected $fillable = [
        'user_id', 'guard', 'identifier_hash', 'identifier_ciphertext', 'token_id',
        'device_name', 'ip_address', 'user_agent', 'last_seen_at', 'expires_at',
        'revoked_at', 'revoked_by', 'revoke_reason',
    ];

    protected function casts(): array
    {
        return [
            'identifier_ciphertext' => 'encrypted',
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isCurrent(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
