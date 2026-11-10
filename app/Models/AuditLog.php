<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'category', 'outcome', 'severity', 'correlation_id',
        'reason', 'changes', 'subject_type', 'subject_id', 'ip_address',
        'user_agent', 'metadata',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Les journaux d’audit sont immuables. Ajoutez un nouvel événement.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Les journaux d’audit ne peuvent être supprimés que par la commande de rétention.');
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'changes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
