<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionalDocumentAcceptance extends Model
{
    protected $fillable = ['institutional_document_version_id','user_id','accepted_at','source'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(InstitutionalDocumentVersion::class, 'institutional_document_version_id');
    }
}
