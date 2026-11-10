<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstitutionalDocumentVersion extends Model
{
    protected $fillable = ['institutional_document_id', 'version', 'content', 'change_reason', 'status', 'created_by', 'approved_by', 'effective_at', 'published_at'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'effective_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(InstitutionalDocumentAcceptance::class, 'institutional_document_version_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(InstitutionalDocument::class, 'institutional_document_id');
    }
}
