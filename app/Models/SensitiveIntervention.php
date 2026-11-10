<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensitiveIntervention extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id', 'audit_log_id', 'domain', 'action', 'subject_type', 'subject_id',
        'result', 'reason_hash', 'reason', 'amount', 'currency', 'changes',
        'correlation_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class);
    }
}
