<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuotationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function currentOffer(): BelongsTo
    {
        return $this->belongsTo(QuotationOffer::class, 'current_offer_id');
    }

    public function acceptedOffer(): BelongsTo
    {
        return $this->belongsTo(QuotationOffer::class, 'accepted_offer_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(QuotationOffer::class)->orderBy('version');
    }

    public function events(): HasMany
    {
        return $this->hasMany(QuotationEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function latestOffer(): HasOne
    {
        return $this->hasOne(QuotationOffer::class)->latestOfMany('version');
    }
}
