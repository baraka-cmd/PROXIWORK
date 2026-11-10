<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstitutionalDocument extends Model
{
    protected $fillable = ['title','slug','document_type','status','current_version','requires_acceptance'];

    protected function casts(): array
    {
        return ['current_version' => 'integer', 'requires_acceptance' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(InstitutionalDocumentVersion::class);
    }
}
