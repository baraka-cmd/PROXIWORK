<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTemplateVersion extends Model
{
    protected $fillable = ['notification_template_id', 'version', 'content', 'created_by'];

    protected function casts(): array
    {
        return ['content' => 'array', 'version' => 'integer'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }
}
