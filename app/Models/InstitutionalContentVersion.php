<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionalContentVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['institutional_content_id', 'version', 'snapshot', 'created_by', 'created_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'created_at' => 'datetime'];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(InstitutionalContent::class, 'institutional_content_id');
    }
}