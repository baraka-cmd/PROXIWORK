<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalDocument extends Model
{
    protected $fillable = [
        'document_type',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'review_status',
        'review_notes',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'size_bytes' => 'integer'];
    }

    public function professionalProfile(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class);
    }
}
