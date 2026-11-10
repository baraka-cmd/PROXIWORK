<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementVersion extends Model
{
    protected $fillable = ['announcement_id', 'version', 'content', 'created_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'content' => 'array'];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
