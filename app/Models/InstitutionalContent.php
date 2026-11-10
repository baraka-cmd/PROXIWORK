<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstitutionalContent extends Model
{
    protected $fillable = ['key', 'title', 'body', 'status', 'version', 'effective_at', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'effective_at' => 'datetime'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(InstitutionalContentVersion::class);
    }
}
