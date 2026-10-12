<?php

declare(strict_types=1);

namespace App\\Http\\Requests\\Notification;

use Illuminate\\Foundation\\Http\\FormRequest;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Preferences belong to the authenticated user; no profile-management
        // permission is needed to update one's own delivery choices.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'email_enabled' => ['sometimes', 'boolean'],
            'sms_enabled' => ['sometimes', 'boolean'],
            'push_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
