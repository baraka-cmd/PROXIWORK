<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => CategoryStatus::ACTIVE->value,
        'is_featured' => false,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'icon', 'image_path',
        'status', 'is_featured', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => CategoryStatus::class,
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'category_skill')->withTimestamps();
    }

    public function professionalProfiles(): BelongsToMany
    {
        return $this->belongsToMany(ProfessionalProfile::class, 'professional_categories')->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', CategoryStatus::ACTIVE->value);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }
}
