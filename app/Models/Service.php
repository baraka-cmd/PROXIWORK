<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryStatus;
use App\Enums\ServiceExecutionMode;
use App\Enums\ServiceModerationStatus;
use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
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
        'moderation_status' => ServiceModerationStatus::CLEAR->value,
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
            'moderation_status' => ServiceModerationStatus::class,
            'moderated_at' => 'datetime',
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

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function moderationDecisions(): HasMany
    {
        return $this->hasMany(ServiceModerationDecision::class)->latest();
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
            ->where('moderation_status', ServiceModerationStatus::CLEAR->value)
            ->whereHas('category', fn ($category) => $category
                ->where('status', CategoryStatus::ACTIVE->value))
            ->whereHas('professionalProfile', fn ($profile) => $profile->publiclyVisible())
            ->whereDoesntHave('skills', function ($skills): void {
                $skills->where('skills.status', '!=', 'active')
                    ->orWhere(function ($skills): void {
                        $skills->whereNotNull('skills.category_id')
                            ->whereColumn('skills.category_id', '!=', 'services.category_id')
                            ->whereNotExists(function ($query): void {
                                $query->selectRaw('1')->from('categories as service_categories')
                                    ->whereColumn('service_categories.id', 'services.category_id')
                                    ->whereColumn('service_categories.parent_id', 'skills.category_id');
                            })
                            ->whereNotExists(function ($query): void {
                                $query->selectRaw('1')->from('categories as skill_categories')
                                    ->whereColumn('skill_categories.id', 'skills.category_id')
                                    ->whereColumn('skill_categories.parent_id', 'services.category_id');
                            });
                    });
            });
    }

    public function isRequestableForClient(): bool
    {
        $this->loadMissing(['category', 'professionalProfile.user', 'skills.category']);

        if ($this->status !== ServiceStatus::PUBLISHED
            || $this->published_at === null
            || $this->moderation_status !== ServiceModerationStatus::CLEAR
            || $this->category?->status !== CategoryStatus::ACTIVE
            || $this->professionalProfile?->isPubliclyVisible() !== true) {
            return false;
        }

        foreach ($this->skills as $skill) {
            if ($skill->status->value !== 'active') {
                return false;
            }

            if ($skill->category_id !== null
                && (int) $skill->category_id !== (int) $this->category_id
                && (int) $skill->category?->parent_id !== (int) $this->category_id
                && (int) $this->category?->parent_id !== (int) $skill->category_id) {
                return false;
            }
        }

        return true;
    }
}
