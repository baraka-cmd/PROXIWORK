<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuotationDurationUnit;
use App\Enums\QuotationOfferActor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationOffer extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'actor_type' => QuotationOfferActor::class,
            'amount' => 'decimal:2',
            'duration_value' => 'integer',
            'duration_unit' => QuotationDurationUnit::class,
            'valid_until' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
