<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationCampaign extends Model
{
    protected $fillable = ['name', 'subject', 'body', 'channel', 'audience_rules', 'status', 'recipient_count', 'sent_count', 'failed_count', 'scheduled_at', 'started_at', 'completed_at', 'created_by', 'approved_by'];

    protected function casts(): array
    {
        return ['audience_rules' => 'array', 'scheduled_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CommunicationCampaignRecipient::class, 'campaign_id');
    }
}
