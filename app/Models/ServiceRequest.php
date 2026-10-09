<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'address_id',
        'title',
        'description',
        'budget_min',
        'budget_max',
        'currency',
        'desired_at',
    ];

    protected $attributes = [
        'status' => ServiceRequestStatus::DRAFT->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => ServiceRequestStatus::class,
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'desired_at' => 'datetime',
            'requested_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ServiceRequestStatusHistory::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function quotation(): HasOne
    {
        return $this->hasOne(Quotation::class);
    }
}
