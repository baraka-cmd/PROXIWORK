<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class CommunicationCampaignNotification extends Notification
{
    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly int $campaignId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'action' => 'communication',
            'url' => '/dashboard',
            'priority' => 'normal',
            'campaign_id' => $this->campaignId,
        ];
    }
}
