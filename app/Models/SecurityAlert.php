<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAlert extends Model
{
    protected $fillable = [
        'user_id', 'source_audit_log_id', 'rule_key', 'fingerprint', 'severity',
        'status', 'title', 'description', 'context', 'occurrences', 'first_seen_at',
        'last_seen_at', 'assigned_to', 'resolution_note', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceEvent(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'source_audit_log_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
