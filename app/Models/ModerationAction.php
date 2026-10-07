<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationActionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModerationAction extends Model
{
    protected $fillable = [
        'report_id',
        'moderator_id',
        'action_type',
        'reason_code',
        'note',
        'target_type',
        'target_id',
    ];

    protected function casts(): array
    {
        return [
            'action_type' => ModerationActionType::class,
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
