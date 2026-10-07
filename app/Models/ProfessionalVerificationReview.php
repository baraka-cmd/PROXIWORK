<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProfessionalVerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalVerificationReview extends Model
{
    protected $fillable = [
        'professional_profile_id',
        'admin_user_id',
        'from_status',
        'to_status',
        'reason_code',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => ProfessionalVerificationStatus::class,
            'to_status' => ProfessionalVerificationStatus::class,
        ];
    }

    public function professionalProfile(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
