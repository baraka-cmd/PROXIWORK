<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalStatusHistory extends Model
{
    protected $fillable = [];

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
