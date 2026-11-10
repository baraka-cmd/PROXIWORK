<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    protected $fillable = ['key', 'name', 'channel', 'locale', 'subject', 'title', 'body', 'variables', 'status', 'version', 'updated_by'];

    protected function casts(): array
    {
        return ['variables' => 'array', 'version' => 'integer'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(NotificationTemplateVersion::class);
    }
}
