<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalProfile extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CLOSED = 'closed';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    public const VERIFICATION_UNVERIFIED = 'unverified';

    public const VERIFICATION_PENDING = 'pending';

    public const VERIFICATION_VERIFIED = 'verified';

    public const VERIFICATION_REJECTED = 'rejected';

    protected $fillable = [
        'professional_title',
        'description',
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
            'starting_price' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
