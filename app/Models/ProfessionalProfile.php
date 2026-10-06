<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProfessionalAvailabilityStatus;
use App\Enums\ProfessionalVerificationStatus;
use Database\Factories\ProfessionalProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfessionalProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'professional_title',
    ];

    protected function casts(): array
    {
        return [
            'verification_status' => ProfessionalVerificationStatus::class,
            'availability_status' => ProfessionalAvailabilityStatus::class,
            'rating_average' => 'decimal:2',
            'rating_count' => 'integer',
        ];
    }

    protected static function newFactory(): ProfessionalProfileFactory
    {
        return ProfessionalProfileFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(
            Skill::class,
            'professional_skills',
            'professional_profile_id',
            'skill_id'
        )->withPivot(['proficiency_level', 'years_experience'])->withTimestamps();
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
