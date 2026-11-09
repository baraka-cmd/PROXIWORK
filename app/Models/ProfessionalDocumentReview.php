<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalDocumentReview extends Model
{
    protected $fillable = [
        'professional_document_id',
        'admin_user_id',
        'decision',
        'note',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(ProfessionalDocument::class, 'professional_document_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
