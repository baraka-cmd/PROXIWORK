<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    protected $fillable = ['name', 'code', 'event_key', 'category', 'channel', 'locale', 'subject', 'title', 'body', 'status', 'version', 'created_by', 'updated_by', 'published_at'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'published_at' => 'datetime'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(NotificationTemplateVersion::class);
    }
}
