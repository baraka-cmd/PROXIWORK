<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationCampaign extends Model
{
    protected $fillable = ['title', 'body', 'audience', 'channels', 'priority', 'status', 'scheduled_at', 'started_at', 'completed_at', 'audience_count', 'created_count', 'failed_count', 'idempotency_key', 'created_by'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'channels' => 'array', 'scheduled_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
