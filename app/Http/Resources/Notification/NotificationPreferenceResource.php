<?php

declare(strict_types=1);

namespace App\Http\Resources\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'database_enabled' => $this->database_enabled,
            'email_enabled' => $this->email_enabled,
            'sms_enabled' => $this->sms_enabled,
            'push_enabled' => $this->push_enabled,
        ];
    }
}
