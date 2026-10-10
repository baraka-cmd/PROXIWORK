<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryStatus;
use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
use App\Enums\UserAccountStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $attributes = [
        'pricing_type' => ServicePricingType::QUOTE->value,
        'status' => ServiceStatus::DRAFT->value,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'category_id', 'title', 'slug', 'short_description', 'description',
        'pricing_type', 'price', 'price_min', 'price_max', 'currency',
        'estimated_duration_minutes', 'billing_unit', 'service_area', 'conditions',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'pricing_type' => ServicePricingType::class,
            'status' => ServiceStatus::class,
            'price' => 'decimal:2',
            'price_min' => 'decimal:2',
            'price_max' => 'decimal:2',
            'estimated_duration_minutes' => 'integer',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function professionalProfile(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'service_skills', 'service_id', 'skill_id')->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ServiceImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', ServiceStatus::PUBLISHED->value)
            ->whereNotNull('published_at');
    }

    /**
     * Public catalogue visibility is enforced in the database query so that
     * unpublished services and services owned by private, suspended, or
     * inactive accounts cannot leak through alternate public endpoints.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->published()
            ->whereHas('category', fn (Builder $category) => $category
                ->where('status', CategoryStatus::ACTIVE->value))
            ->whereHas('professionalProfile', fn (Builder $professional) => $professional
                ->where('status', ProfessionalProfile::STATUS_ACTIVE)
                ->where('visibility', ProfessionalProfile::VISIBILITY_PUBLIC)
                ->whereHas('user', fn (Builder $user) => $user
                    ->where('account_status', UserAccountStatus::ACTIVE->value)));
    }
}
