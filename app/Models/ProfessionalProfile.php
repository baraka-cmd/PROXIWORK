<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProfessionalAvailabilityStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\UserAccountStatus;
use Database\Factories\ProfessionalProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProfessionalProfile extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CLOSED = 'closed';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'visibility' => self::VISIBILITY_PRIVATE,
    ];

    protected $fillable = [
        'professional_title',
        'business_name',
        'description',
        'languages',
        'years_experience',
        'starting_price',
        'currency',
        'province',
        'city',
        'commune',
        'service_radius_km',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'verification_status' => ProfessionalVerificationStatus::class,
            'availability_status' => ProfessionalAvailabilityStatus::class,
            'languages' => 'array',
            'rating_average' => 'decimal:2',
            'rating_count' => 'integer',
            'starting_price' => 'decimal:2',
            'years_experience' => 'integer',
            'service_radius_km' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'verified_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ProfessionalProfileFactory
    {
        return ProfessionalProfileFactory::new();
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('visibility', self::VISIBILITY_PUBLIC)
            ->where('verification_status', ProfessionalVerificationStatus::VERIFIED->value)
            ->whereNotNull('verified_at')
            ->whereHas('user', fn (Builder $user) => $user
                ->where('account_status', UserAccountStatus::ACTIVE->value)
                ->whereNotNull('email_verified_at'));
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->visibility === self::VISIBILITY_PUBLIC
            && $this->verification_status === ProfessionalVerificationStatus::VERIFIED
            && $this->verified_at !== null
            && $this->user?->account_status === UserAccountStatus::ACTIVE
            && $this->user?->hasVerifiedEmail() === true;
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

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'professional_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function wallet(string $currency = 'USD'): HasOne
    {
        return $this->hasOne(Wallet::class, 'professional_id')->where('currency', strtoupper($currency));
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'professional_id');
    }

    public function verificationReviews(): HasMany
    {
        return $this->hasMany(ProfessionalVerificationReview::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProfessionalDocument::class);
    }
}
