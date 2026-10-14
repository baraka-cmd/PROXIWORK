<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'status' => WithdrawalStatus::class,
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'destination_ciphertext' => 'encrypted',
            'requested_at' => 'datetime',
            'processing_at' => 'datetime',
            'completed_at' => 'datetime',
            'review_required_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(WithdrawalStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Return only a masked destination suitable for HTML, logs and notifications.
     */
    public function maskedDestination(): string
    {
        $destination = $this->destination_ciphertext ?: $this->destination;
        $destination = trim((string) $destination);

        if ($destination === '') {
            return 'Destination non disponible';
        }

        $visibleSuffix = mb_substr($destination, -4);

        return str_repeat('•', max(4, mb_strlen($destination) - mb_strlen($visibleSuffix))).$visibleSuffix;
    }
}
