<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Support;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminSupportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('support.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::enum(SupportTicketCategory::class)],
            'priority' => ['nullable', Rule::enum(SupportTicketPriority::class)],
            'status' => ['nullable', Rule::enum(SupportTicketStatus::class)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'sort' => ['nullable', Rule::in(['created_at', '-created_at', 'priority', '-priority', 'last_message_at', '-last_message_at'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }
}
