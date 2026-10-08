<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Support;

use App\Enums\SupportTicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminSupportUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('support.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'priority' => ['nullable', Rule::enum(SupportTicketPriority::class)],
            'status' => ['nullable', Rule::in(['in_progress', 'waiting'])],
        ];
    }
}
