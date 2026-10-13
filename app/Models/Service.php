<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceExecutionMode;
use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
use App\Enums\UserAccountStatus;
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
        'execution_mode' => ServiceExecutionMode::CLIENT_LOCATION->value,
        'status' => ServiceStatus::DRAFT->value,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'short_description',
        'description',
        'included_items',
        'excluded_items',
        'client_requirements',
        'deliverables',
        'commercial_conditions',
        'execution_mode',
        'pricing_type',
        'price',
        'price_min',
        'price_max',
        'currency',
        'estimated_duration_minutes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'execution_mode' => ServiceExecutionMode::class,
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
        return $this->belongsToMany(
            Skill::class,
            'service_skills',
            'service_id',
            'skill_id'
        )->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ServiceImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function realizations(): HasMany
    {
        return $this->hasMany(ServiceRealization::class)->latest();
    }

    public function scopePublished($query)
    {
        return $query
            ->where('status', ServiceStatus::PUBLISHED->value)
            ->whereNotNull('published_at');
    }

    /**
     * Restrict request creation to services currently visible to clients.
     *
     * Historical requests keep their relations even when a service later becomes
     * unavailable; this scope is for starting a new request, not reading history.
     */
    public function scopeRequestable($query)
    {
        return $query
            ->published()
            ->whereHas('category', fn ($category) => $category
                ->where('status', CategoryStatus::ACTIVE->value))
            ->whereHas('professionalProfile', fn ($profile) => $profile
                ->where('status', ProfessionalProfile::STATUS_ACTIVE)
                ->where('visibility', ProfessionalProfile::VISIBILITY_PUBLIC)
                ->where('verification_status', ProfessionalVerificationStatus::VERIFIED->value)
                ->whereNotNull('verified_at')
                ->whereHas('user', fn ($user) => $user
                    ->where('account_status', UserAccountStatus::ACTIVE->value)));
    }

    public function isRequestableForClient(): bool
    {
        return $this->status === ServiceStatus::PUBLISHED
            && $this->published_at !== null
            && $this->category?->status === CategoryStatus::ACTIVE
            && $this->professionalProfile?->status === ProfessionalProfile::STATUS_ACTIVE
            && $this->professionalProfile?->visibility === ProfessionalProfile::VISIBILITY_PUBLIC
            && $this->professionalProfile?->verification_status === ProfessionalVerificationStatus::VERIFIED
            && $this->professionalProfile?->verified_at !== null
            && $this->professionalProfile?->user?->account_status === UserAccountStatus::ACTIVE;
    }
}
