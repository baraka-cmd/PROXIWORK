<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'service_request_id' => ['nullable', 'integer', 'exists:service_requests,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ];
    }
}
