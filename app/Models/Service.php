<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    use HasFactory;

    protected $attributes = [
        'pricing_type' => ServicePricingType::QUOTE->value,
        'status' => ServiceStatus::DRAFT->value,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'short_description',
        'description',
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

    public function scopePublished($query)
    {
        return $query
            ->where('status', ServiceStatus::PUBLISHED->value)
            ->whereNotNull('published_at');
    }
}
