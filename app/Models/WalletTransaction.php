<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [];

    protected static function booted(): void
    {
        static::updating(static function (self $transaction): void {
            throw new LogicException('Les écritures du registre financier sont immuables. Créez une écriture de correction au lieu de modifier celle-ci.');
        });

        static::deleting(static function (self $transaction): void {
            throw new LogicException('Les écritures du registre financier ne peuvent pas être supprimées.');
        });
    }

    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'direction' => WalletTransactionDirection::class,
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
