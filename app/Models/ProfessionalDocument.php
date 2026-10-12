<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalDocument extends Model
{
    use HasFactory;

    public const TYPE_IDENTITY = 'identity';

    public const TYPE_CERTIFICATE = 'certificate';

    public const TYPE_TRAINING = 'training';

    public const TYPE_LICENSE = 'license';

    public const TYPE_REFERENCE = 'reference';

    public const TYPE_OTHER = 'other';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'professional_profile_id',
        'document_type',
        'original_name',
        'path',
        'disk',
        'mime_type',
        'size',
        'status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function professionalProfile(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}