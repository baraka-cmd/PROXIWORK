<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SkillStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => SkillStatus::ACTIVE->value,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => SkillStatus::class,
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): \Database\Factories\SkillFactory
    {
        return \Database\Factories\SkillFactory::new();
    }

    public function professionalProfiles(): BelongsToMany
    {
        return $this->belongsToMany(
            ProfessionalProfile::class,
            'professional_skills',
            'skill_id',
            'professional_profile_id'
        )->withPivot(['proficiency_level', 'years_experience'])->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', SkillStatus::ACTIVE->value);
    }
}
