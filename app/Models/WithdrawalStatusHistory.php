<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WithdrawalStatusHistory extends Model
{
    protected $fillable = [];

    protected static function booted(): void
    {
        static::updating(static function (self $history): void {
            throw new LogicException('L’historique des statuts de retrait est immuable. Ajoutez une nouvelle transition explicative.');
        });

        static::deleting(static function (self $history): void {
            throw new LogicException('L’historique des statuts de retrait ne peut pas être supprimé.');
        });
    }

    protected function casts(): array
    {
        return [
            'from_status' => WithdrawalStatus::class,
            'to_status' => WithdrawalStatus::class,
            'metadata' => 'array',
        ];
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(Withdrawal::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
