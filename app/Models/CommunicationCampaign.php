<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationCampaign extends Model
{
    protected $fillable = ['name', 'subject', 'body', 'channel', 'audience_rules', 'status', 'recipient_count', 'sent_count', 'failed_count', 'scheduled_at', 'started_at', 'completed_at', 'created_by', 'approved_by', 'communication_segment_id'];

    protected function casts(): array
    {
        return ['audience_rules' => 'array', 'scheduled_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(CommunicationSegment::class, 'communication_segment_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CommunicationCampaignRecipient::class, 'campaign_id');
    }
}
